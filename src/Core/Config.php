<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Configurazione dell'applicazione, con accesso a punti: Config::get('db.name').
 *
 * Il file reale non entra in nessun repository: nel progetto resta solo
 * `config/config.esempio.php`, con segnaposto al posto di ogni valore.
 *
 * Dove cercarlo e' una questione di portabilita'. Scrivere qui il percorso di
 * una macchina precisa — `/qualcosa/temaecielo-config/config.php` — funziona
 * su quella macchina e rende il progetto non installabile altrove. Si cerca
 * quindi in quattro posti, dal piu' esplicito al piu' comodo, e il primo che
 * esiste vince:
 *
 *   1. il percorso indicato dalla variabile d'ambiente TEC_CONFIG
 *   2. /etc/temaecielo/config.php, la collocazione di sistema
 *   3. una cartella «temaecielo-config» accanto al DocumentRoot, cioe' due
 *      livelli sopra il progetto: e' fuori dalla radice del web, che e' il
 *      punto, e si esprime in modo relativo
 *   4. config/config.php dentro il progetto — comodo per lo sviluppo, protetto
 *      dalla conf Apache, ma meno solido dei primi tre
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $dati = [];
    private static bool $caricata = false;
    private static string $origine = '';

    /**
     * I percorsi da provare, in ordine.
     *
     * @return list<string>
     */
    private static function percorsi(string $radiceProgetto): array
    {
        $fuori = [];

        $ambiente = getenv('TEC_CONFIG');
        if (is_string($ambiente) && $ambiente !== '') {
            $fuori[] = $ambiente;
        }

        $fuori[] = '/etc/temaecielo/config.php';

        // Due livelli sopra il progetto: se il progetto sta in
        // <radice-web>/temaecielo, questa e' <padre della radice web>/temaecielo-config
        // — fuori da tutto cio' che il web server serve, che e' il punto, e
        // espressa in modo relativo, che la rende portabile.
        $fuori[] = dirname($radiceProgetto, 2) . '/temaecielo-config/config.php';

        $fuori[] = $radiceProgetto . '/config/config.php';

        return $fuori;
    }

    public static function load(string $radiceProgetto): void
    {
        // Un percorso relativo («.») farebbe cercare la configurazione due livelli
        // sopra la cartella corrente e non al progetto: si risolve prima.
        $radiceProgetto = realpath($radiceProgetto) ?: $radiceProgetto;

        foreach (self::percorsi($radiceProgetto) as $percorso) {
            if (!is_file($percorso)) {
                continue;
            }
            if (!is_readable($percorso)) {
                throw new RuntimeException(
                    "Il file di configurazione {$percorso} esiste ma non e' leggibile. "
                    . "Controlla che l'utente del web server appartenga al gruppo www-data."
                );
            }

            $dati = require $percorso;
            if (!is_array($dati)) {
                throw new RuntimeException("{$percorso} non restituisce un array.");
            }

            self::$dati     = $dati;
            self::$caricata = true;
            self::$origine  = $percorso;
            return;
        }

        throw new RuntimeException(
            'Nessun file di configurazione trovato. Cercato in: '
            . implode(', ', self::percorsi($radiceProgetto))
            . '. Esegui deploy/00-bootstrap.sh, oppure indica il percorso con la variabile TEC_CONFIG.'
        );
    }

    public static function get(string $chiave, mixed $predefinito = null): mixed
    {
        if (!self::$caricata) {
            throw new RuntimeException('Config::load() non è stata chiamata.');
        }

        $nodo = self::$dati;
        foreach (explode('.', $chiave) as $pezzo) {
            if (!is_array($nodo) || !array_key_exists($pezzo, $nodo)) {
                return $predefinito;
            }
            $nodo = $nodo[$pezzo];
        }

        return $nodo;
    }

    public static function origine(): string
    {
        return self::$origine;
    }

    public static function caricata(): bool
    {
        return self::$caricata;
    }
}
