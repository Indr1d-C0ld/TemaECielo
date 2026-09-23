<?php

declare(strict_types=1);

namespace App\Astro;

use App\Core\Config;
use App\Core\Database;
use RuntimeException;

/**
 * Il ponte fra il livello web e il motore delle effemeridi.
 *
 * Sotto Apache l'FFI e' bloccato (`ffi.enable=preload`), quindi qui non si
 * tocca libswe: si invoca bin/effemeridi.php con proc_open e gli si parla in
 * JSON. Nessuna modifica a php.ini, nessun compilatore, nessun demone.
 *
 * Il calcolo e' deterministico, percio' si mette in cache per IMPRONTA: stessi
 * dati di nascita e stesse opzioni danno la stessa carta, si calcola una volta
 * sola e si contano le richieste. Un beneficio inatteso e' statistico — il
 * portale sa dire quante persone distinte hanno chiesto lo stesso cielo.
 */
final class Motore
{
    /**
     * Versione della struttura della carta.
     *
     * Entra nell'impronta, e quindi nella chiave di cache. Va alzata OGNI
     * VOLTA che cambia la forma di cio' che `Tema::componi()` produce —
     * campi aggiunti, rinominati, spostati.
     *
     * Senza, una carta salvata ieri con la vecchia struttura resta valida per
     * sempre e il codice nuovo ci lavora sopra trovandoci dei buchi. E'
     * successo davvero: le cuspidi grezze sono state aggiunte alla carta, ma
     * dalla cache continuava ad arrivare la versione senza, e l'anello delle
     * case non veniva disegnato senza che nulla segnalasse il perche'.
     */
    private const VERSIONE = 2;

    /** Quanti giorni si tiene una riga di cache che nessuno richiede piu'. */
    private const CACHE_GIORNI = 30;

    /** Ogni quante scritture si controlla se c'e' cache vecchia da buttare. */
    private const SFOLTIMENTO_UNA_SU = 200;

    public function __construct(
        private ?string $radice = null,
        private ?string $php = null,
        private ?int $timeout = null,
    ) {
        $this->radice ??= (string) ($GLOBALS['__project_root'] ?? dirname(__DIR__, 2));
        $this->php     ??= (string) Config::get('astro.php_cli', PHP_BINARY);
        $this->timeout ??= (int) Config::get('astro.timeout', 20);
    }

    /**
     * Carta natale completa, dalla cache se c'e'.
     *
     * @param array<string,mixed> $dati
     * @return array<string,mixed>
     */
    public function tema(array $dati, bool $aspettiMinori = false): array
    {
        $domanda = ['operazione' => 'tema'] + $dati;
        $impronta = self::impronta($domanda + ['minori' => $aspettiMinori, 'v' => self::VERSIONE]);

        $inCache = $this->daCache($impronta);
        if ($inCache !== null) {
            $inCache['meta']['da_cache'] = true;

            return $inCache;
        }

        $grezzo = $this->invoca($domanda);
        $tema   = Tema::componi($grezzo, $aspettiMinori);
        $tema['meta']['impronta'] = $impronta;
        $tema['meta']['da_cache'] = false;

        $this->inCache($impronta, $tema);

        return $tema;
    }

    /**
     * Sole posizioni, per la pagina «Oggi» e per i transiti.
     *
     * @param array<string,mixed> $dati
     * @return array<string,mixed>
     */
    public function posizioni(array $dati): array
    {
        return $this->invoca(['operazione' => 'posizioni'] + $dati);
    }

    /**
     * L'istante in cui un corpo torna a una longitudine: la rivoluzione solare
     * e quella lunare.
     *
     * @param array<string,mixed> $dati
     * @return array<string,mixed>
     */
    public function ritorno(array $dati): array
    {
        return $this->invoca(['operazione' => 'ritorno'] + $dati);
    }

    /** @return array<string,mixed> */
    public function stato(): array
    {
        return $this->invoca(['operazione' => 'stato']);
    }

    /**
     * Invoca il lavoratore e ne legge la risposta.
     *
     * @param array<string,mixed> $domanda
     * @return array<string,mixed>
     */
    private function invoca(array $domanda): array
    {
        $comando = [$this->php, $this->radice . '/bin/effemeridi.php'];

        $canali = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Ambiente ridotto all'osso: il lavoratore non ha bisogno di sapere
        // niente del processo che l'ha chiamato.
        $processo = @proc_open($comando, $canali, $tubi, $this->radice, ['PATH' => '/usr/bin:/bin']);

        if (!is_resource($processo)) {
            throw new RuntimeException('Impossibile avviare il motore delle effemeridi.');
        }

        fwrite($tubi[0], (string) json_encode($domanda, JSON_UNESCAPED_UNICODE));
        fclose($tubi[0]);

        stream_set_blocking($tubi[1], false);
        stream_set_blocking($tubi[2], false);

        $uscita = '';
        $errore = '';
        $scadenza = microtime(true) + $this->timeout;

        while (true) {
            $leggi  = array_filter([$tubi[1], $tubi[2]], static fn ($t) => is_resource($t) && !feof($t));
            if ($leggi === []) {
                break;
            }
            if (microtime(true) > $scadenza) {
                proc_terminate($processo, 9);
                proc_close($processo);
                throw new RuntimeException("Il motore non ha risposto entro {$this->timeout} secondi.");
            }

            $scrivi = null;
            $eccez  = null;
            if (@stream_select($leggi, $scrivi, $eccez, 0, 200000) === false) {
                break;
            }

            foreach ($leggi as $tubo) {
                $pezzo = fread($tubo, 65536);
                if ($pezzo === false || $pezzo === '') {
                    continue;
                }
                if ($tubo === $tubi[1]) { $uscita .= $pezzo; } else { $errore .= $pezzo; }
            }
        }

        fclose($tubi[1]);
        fclose($tubi[2]);
        $codice = proc_close($processo);

        if (trim($uscita) === '') {
            throw new RuntimeException(
                'Il motore non ha prodotto nulla (uscita ' . $codice . ')'
                . ($errore !== '' ? ': ' . trim($errore) : '.'),
            );
        }

        try {
            $risposta = json_decode($uscita, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Il motore ha risposto con JSON non valido: ' . $e->getMessage());
        }

        if (!is_array($risposta) || ($risposta['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'Motore: ' . (string) ($risposta['errore'] ?? 'errore non specificato')
                . ' [' . (string) ($risposta['tipo'] ?? 'ignoto') . ']',
            );
        }

        return $risposta;
    }

    /**
     * L'impronta di una domanda.
     *
     * Le chiavi si ordinano prima di serializzare, altrimenti due domande
     * identiche scritte in ordine diverso produrrebbero due impronte diverse e
     * la cache non servirebbe a niente.
     *
     * @param array<string,mixed> $domanda
     */
    public static function impronta(array $domanda): string
    {
        $ordina = static function (array $a) use (&$ordina): array {
            ksort($a);
            foreach ($a as $k => $v) {
                if (is_array($v)) {
                    $a[$k] = $ordina($v);
                }
            }

            return $a;
        };

        return hash('sha256', (string) json_encode($ordina($domanda), JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string,mixed>|null */
    private function daCache(string $impronta): ?array
    {
        if (!Database::disponibile()) {
            return null;
        }

        try {
            $riga = Database::riga(
                'SELECT esito FROM calcoli WHERE impronta = ? AND esito IS NOT NULL LIMIT 1',
                [$impronta],
            );
            if ($riga === null) {
                return null;
            }
            Database::esegui(
                'UPDATE calcoli SET richieste = richieste + 1, ultima_richiesta = NOW() WHERE impronta = ?',
                [$impronta],
            );

            $dati = json_decode((string) $riga['esito'], true);

            return is_array($dati) ? $dati : null;
        } catch (\Throwable $e) {
            // Una cache che non funziona non deve impedire un calcolo.
            registro('cache carte in lettura: ' . $e->getMessage(), 'warn');

            return null;
        }
    }

    /** @param array<string,mixed> $tema */
    private function inCache(string $impronta, array $tema): void
    {
        if (!Database::disponibile()) {
            return;
        }

        try {
            Database::esegui(
                'INSERT INTO calcoli (impronta, tipo, esito, creato, ultima_richiesta, richieste, durata_ms)
                 VALUES (?, ?, ?, NOW(), NOW(), 1, ?)
                 ON DUPLICATE KEY UPDATE richieste = richieste + 1, ultima_richiesta = NOW()',
                [
                    $impronta,
                    'natale',
                    (string) json_encode($tema, JSON_UNESCAPED_UNICODE),
                    (int) round((float) ($tema['meta']['durata_ms'] ?? 0)),
                ],
            );
            $this->sfoltisci();
        } catch (\Throwable $e) {
            registro('cache carte in scrittura: ' . $e->getMessage(), 'warn');
        }
    }

    /**
     * La valvola: ogni tanto, e senza che nessuno se ne accorga, butta via la
     * cache vecchia.
     *
     * Serve perche' la crescita di `calcoli` non ha piu' un tetto naturale.
     * Finche' la volta celeste mostrava solo «adesso», le righe possibili erano
     * un minuto per luogo; da quando si puo' chiedere una data e un luogo
     * qualunque, sono tutte le date per tutti i luoghi. A trentun kilobyte
     * l'una, trentamila richieste fanno un gigabyte, e riempire il disco di
     * qualcun altro non deve costare cosi' poco.
     *
     * Si fa qui e non in un compito periodico perche' un compito periodico
     * bisogna ricordarsi di installarlo, e chi installa il portale su un'altra
     * macchina non lo sa. Una volta su duecento scritture e' abbastanza spesso
     * da non lasciare accumulare nulla, e abbastanza raro da non pesare: la
     * cancellazione va sull'indice `i_ultima_richiesta` e tocca solo le righe
     * senza gettone.
     *
     * I PERMALINK NON SI TOCCANO MAI. Una riga con `gettone` non e' cache: e'
     * l'unica copia di una carta, e il suo indirizzo e' l'unica chiave che il
     * visitatore possiede. Da qui il `gettone IS NULL`, che e' la riga piu'
     * importante di questo metodo.
     */
    private function sfoltisci(): void
    {
        if (random_int(1, self::SFOLTIMENTO_UNA_SU) !== 1) {
            return;
        }

        try {
            $tolte = Database::esegui(
                'DELETE FROM calcoli
                  WHERE gettone IS NULL
                    AND ultima_richiesta < NOW() - INTERVAL ? DAY
                  LIMIT 500',
                [self::CACHE_GIORNI],
            )->rowCount();

            if ($tolte > 0) {
                registro("cache carte: sfoltite {$tolte} righe non piu' richieste", 'info');
            }
        } catch (\Throwable $e) {
            registro('cache carte in sfoltimento: ' . $e->getMessage(), 'warn');
        }
    }

    /**
     * Svuota la cache del motore, lasciando intatti i permalink.
     *
     * La usano la console (`cache:purga`) e il pannello di manutenzione.
     *
     * @return int quante righe sono state tolte
     */
    public static function purgaCache(int $giorni = 0): int
    {
        if (!Database::disponibile()) {
            return 0;
        }

        // Zero giorni vuol dire «tutta»: si usa dopo aver alzato la VERSIONE
        // della struttura, quando le righe vecchie non sono piu' leggibili.
        $sql = $giorni > 0
            ? 'DELETE FROM calcoli WHERE gettone IS NULL AND ultima_richiesta < NOW() - INTERVAL ? DAY'
            : 'DELETE FROM calcoli WHERE gettone IS NULL';

        return Database::esegui($sql, $giorni > 0 ? [$giorni] : [])->rowCount();
    }
}
