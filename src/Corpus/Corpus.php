<?php

declare(strict_types=1);

namespace App\Corpus;

use App\Astro\Corpi;
use App\Core\Database;

/**
 * L'accesso ai testi: li cerca scritti a mano, e se non ci sono li compone.
 *
 * La copertura e' totale fin dal primo giorno. Le voci scritte a mano
 * sostituiscono progressivamente quelle composte, a cominciare da quelle che
 * compaiono piu' spesso — ed e' per questo che si tiene il conto degli usi.
 */
final class Corpus
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $cache = null;

    public function __construct(private string $registro = 'moderno')
    {
    }

    public function registro(): string
    {
        return $this->registro;
    }

    /**
     * Tutti i testi del registro, caricati una volta sola.
     *
     * Sono poche centinaia di righe corte: tenerle in memoria costa meno che
     * interrogare il database una volta per voce, e una relazione ne consulta
     * facilmente una cinquantina.
     *
     * @return array<string,array<string,mixed>>
     */
    private function tutti(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        try {
            $righe = Database::righe(
                'SELECT ambito, chiave, titolo, corpo, peso, etichette
                   FROM testi WHERE registro = ? AND stato = ?',
                [$this->registro, 'pubblicato'],
            );
        } catch (\Throwable $e) {
            registro('corpus non leggibile: ' . $e->getMessage(), 'warn');

            return $this->cache;
        }

        foreach ($righe as $r) {
            $this->cache[$r['ambito'] . '/' . $r['chiave']] = [
                'titolo'    => (string) $r['titolo'],
                'corpo'     => (string) $r['corpo'],
                'peso'      => (int) $r['peso'],
                'etichette' => array_values(array_filter(explode(' ', (string) $r['etichette']))),
            ];
        }

        return $this->cache;
    }

    /** @return array<string,mixed>|null */
    public function trova(string $ambito, string $chiave): ?array
    {
        return $this->tutti()[$ambito . '/' . $chiave] ?? null;
    }

    /**
     * Una voce scritta a mano, se esiste.
     *
     * `$nominato` riempie i %s del titolo e del corpo. Le voci di dignita' e
     * condizione ne hanno bisogno: un paragrafo che dice «si trova nel segno di
     * cui e' signore» senza mai nominare il pianeta lascia il lettore a
     * chiedersi di chi si stia parlando — e in una relazione lunga e' un
     * difetto grave.
     */
    public function scritta(string $ambito, string $chiave, array $soggetti = [], ?string $nominato = null): ?Voce
    {
        $t = $this->trova($ambito, $chiave);
        if ($t === null) {
            return null;
        }

        $titolo = $t['titolo'];
        $corpo  = $t['corpo'];

        if ($nominato !== null) {
            $nome = self::nomeDi($nominato);
            // Lingua::inserisci e non sprintf: se il segnaposto e' preceduto
            // da una preposizione, va contratta con l'articolo del nome.
            $titolo = Lingua::inserisci($titolo, $nome);
            $corpo  = Lingua::inserisci($corpo, $nome);
        }

        return new Voce(
            $ambito, $chiave, $titolo, $corpo,
            (float) $t['peso'], 'scritto', $t['etichette'], $soggetti,
        );
    }

    /**
     * Pianeta in segno: scritta a mano se c'e', altrimenti composta.
     */
    public function pianetaInSegno(string $pianeta, int $segno): ?Voce
    {
        $nomeSegno = self::chiaveSegno($segno);
        $chiave = $pianeta . '.' . $nomeSegno;

        $scritta = $this->scritta('pianeta_segno', $chiave, [$pianeta]);
        if ($scritta !== null) {
            return $scritta;
        }

        $p = $this->trova('pianeta', $pianeta);
        $s = $this->trova('segno_modo', $nomeSegno);
        if ($p === null || $s === null) {
            return null;
        }

        return new Voce(
            'pianeta_segno', $chiave,
            Corpi::elenco()[$pianeta]['nome'] . ' ' . $s['titolo'],
            Lingua::maiuscola($p['titolo']) . ' ' . $s['corpo'],
            (float) max($p['peso'], 1),
            'composto',
            array_merge($p['etichette'], $s['etichette'], ['frammento:segno_modo.' . $nomeSegno]),
            [$pianeta],
        );
    }

    /** Pianeta in casa. */
    public function pianetaInCasa(string $pianeta, int $casa): ?Voce
    {
        $chiave = $pianeta . '.' . $casa;

        $scritta = $this->scritta('pianeta_casa', $chiave, [$pianeta]);
        if ($scritta !== null) {
            return $scritta;
        }

        $p = $this->trova('pianeta', $pianeta);
        $c = $this->trova('casa_campo', (string) $casa);
        if ($p === null || $c === null) {
            return null;
        }

        return new Voce(
            'pianeta_casa', $chiave,
            Corpi::elenco()[$pianeta]['nome'] . ' ' . $c['titolo'],
            Lingua::maiuscola($p['titolo']) . ' ' . $c['corpo'],
            (float) max($p['peso'], 1),
            'composto',
            array_merge($p['etichette'], $c['etichette'], ['frammento:casa_campo.' . $casa]),
            [$pianeta],
        );
    }

    /**
     * Aspetto fra due corpi.
     *
     * La chiave e' ordinata alfabeticamente: «luna.sole.opposizione» e non
     * «sole.luna.opposizione», altrimenti servirebbero due testi per ogni
     * coppia. Il testo composto, pero', mette per primo il piu' veloce dei
     * due, che e' l'ordine in cui si legge un aspetto.
     */
    public function aspetto(string $a, string $b, string $aspetto): ?Voce
    {
        $ordinati = [$a, $b];
        sort($ordinati);
        $chiave = implode('.', $ordinati) . '.' . $aspetto;

        $scritta = $this->scritta('aspetto', $chiave, [$a, $b]);
        if ($scritta !== null) {
            return $scritta;
        }

        // Il piu' veloce per primo: e' il soggetto naturale della frase.
        [$primo, $secondo] = self::perVelocita($a, $b);

        $pa = $this->trova('pianeta', $primo);
        $pb = $this->trova('pianeta', $secondo);
        $rel = $this->trova('aspetto_relazione', $aspetto);
        if ($pa === null || $pb === null || $rel === null) {
            return null;
        }

        $nomeA = self::nomeDi($primo);
        $nomeB = self::nomeDi($secondo);

        return new Voce(
            'aspetto', $chiave,
            $nomeA . ' ' . $rel['titolo'] . ' ' . $nomeB,
            Lingua::maiuscola($pa['titolo']) . ' ' . Lingua::inserisci($rel['corpo'], $pb['titolo']),
            (float) $rel['peso'],
            'composto',
            array_merge($pa['etichette'], $pb['etichette'], $rel['etichette']),
            [$primo, $secondo],
        );
    }

    /**
     * La compatibilita' fra due segni, composta da tre frammenti.
     *
     * Le coppie sono settantotto e i frammenti quarantasei: bastano perche'
     * quello che conta in una coppia di segni sono tre cose — di che elemento
     * sono, quanto distano e con che modalita' si muovono.
     *
     * @return array<string,mixed>|null
     */
    public function segnoConSegno(int $a, int $b): ?array
    {
        $segni = Corpi::segni();
        if (!isset($segni[$a], $segni[$b])) {
            return null;
        }

        $sa = $segni[$a];
        $sb = $segni[$b];

        // Le chiavi si ordinano alfabeticamente: «fuoco.acqua» e «acqua.fuoco»
        // sono la stessa combinazione e devono trovare lo stesso testo.
        $elementi = [$sa['elemento'], $sb['elemento']];
        sort($elementi);

        $modalita = [$sa['modalita'], $sb['modalita']];
        sort($modalita);

        // La distanza si conta sull'arco piu' corto: dall'Ariete ai Pesci
        // ci sono undici segni in avanti ma uno solo indietro, e in sinastria
        // conta quello.
        $passi = abs($a - $b);
        if ($passi > 6) {
            $passi = 12 - $passi;
        }

        $parti = [
            'elemento' => $this->trova('sinastria_elemento', implode('.', $elementi)),
            'distanza' => $this->trova('sinastria_distanza', (string) $passi),
            'modalita' => $this->trova('sinastria_modalita', implode('.', $modalita)),
        ];

        if ($parti['elemento'] === null || $parti['distanza'] === null || $parti['modalita'] === null) {
            return null;
        }

        return [
            'segni'    => [$a, $b],
            'nomi'     => [$sa['nome'], $sb['nome']],
            'elementi' => [$sa['elemento'], $sb['elemento']],
            'modalita' => [$sa['modalita'], $sb['modalita']],
            'passi'    => $passi,
            'aspetto'  => match ($passi) {
                0 => 'stesso segno', 1 => 'segni contigui', 2 => 'sestile',
                3 => 'quadratura',   4 => 'trigono',       5 => 'quinconce',
                default => 'opposizione',
            },
            'titolo'   => $sa['nome'] . ' e ' . $sb['nome'],
            'paragrafi' => [
                ['titolo' => $parti['distanza']['titolo'], 'corpo' => $parti['distanza']['corpo']],
                ['titolo' => $parti['elemento']['titolo'], 'corpo' => $parti['elemento']['corpo']],
                ['titolo' => $parti['modalita']['titolo'], 'corpo' => $parti['modalita']['corpo']],
            ],
        ];
    }

    /** @return array{0:string,1:string} */
    private static function perVelocita(string $a, string $b): array
    {
        $ordine = array_flip(['luna', 'mercurio', 'venere', 'sole', 'marte', 'giove',
                              'saturno', 'urano', 'nettuno', 'plutone', 'asc', 'mc']);

        return ($ordine[$a] ?? 99) <= ($ordine[$b] ?? 99) ? [$a, $b] : [$b, $a];
    }

    public static function nomeDi(string $chiave): string
    {
        return match ($chiave) {
            'asc' => 'Ascendente',
            'mc'  => 'Medio Cielo',
            default => Corpi::elenco()[$chiave]['nome'] ?? ucfirst($chiave),
        };
    }

    public static function chiaveSegno(int $i): string
    {
        static $chiavi = ['ariete', 'toro', 'gemelli', 'cancro', 'leone', 'vergine',
                          'bilancia', 'scorpione', 'sagittario', 'capricorno', 'acquario', 'pesci'];

        return $chiavi[$i] ?? 'ariete';
    }

    /** @deprecated usare Lingua::maiuscola */
    public static function maiuscola(string $s): string
    {
        return Lingua::maiuscola($s);
    }

    /** Segna che una voce e' stata usata: serve a sapere cosa scrivere per primo. */
    public function segnaUso(string $ambito, string $chiave): void
    {
        try {
            Database::esegui(
                'INSERT INTO testi_uso (ambito, chiave, usi, ultimo) VALUES (?,?,1,NOW())
                 ON DUPLICATE KEY UPDATE usi = usi + 1, ultimo = NOW()',
                [$ambito, $chiave],
            );
        } catch (\Throwable) {
            // il conteggio e' un di piu': non deve mai impedire una lettura
        }
    }
}
