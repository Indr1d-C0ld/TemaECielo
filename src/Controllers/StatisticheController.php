<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Astro\Corpi;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;
use App\Support\Impostazioni;

/**
 * Le statistiche pubbliche.
 *
 * Aggregate e prive di dati personali: nessun nome, nessuna data di nascita
 * singola, nessun indirizzo. Quello che si mostra e' l'archivio che si
 * racconta — ed e' una delle poche cose che un portale del genere puo' offrire
 * e che altrove non si trovano.
 *
 * Una nota metodologica che vale la pena fare in pagina e non solo qui: la
 * distribuzione degli ASCENDENTI non e' uniforme nemmeno in teoria. Alle
 * nostre latitudini certi segni sorgono in un'ora e mezza e altri in venti
 * minuti, e un campione che mostra piu' Bilance che Arieti non sta dicendo
 * niente sulle persone: sta dicendo qualcosa sulla geometria della sfera.
 */
final class StatisticheController
{
    /** GET /statistiche */
    /**
     * Quali righe di `calcoli` sono carte di qualcuno.
     *
     * La tabella tiene anche la cache del motore, e il motore scrive ogni sua
     * riga con tipo «natale» — anche il cielo di adesso, i transiti, le
     * derivate. Contando per tipo, le statistiche mescolavano le carte dei
     * visitatori con ogni cielo guardato: dicevano 62 carte quando ce n'erano
     * 4, e la «distribuzione dei segni solari dei visitatori» era in realta'
     * quella dei giorni in cui qualcuno aveva guardato il cielo. Una carta e'
     * una riga con un permalink, e nient'altro.
     */
    private const CARTE_VERE = "tipo = 'natale' AND gettone IS NOT NULL";

    /** @var list<array<string,mixed>>|null gli esiti decodificati, letti una volta sola */
    private ?array $carte = null;

    /**
     * Gli esiti delle carte, decodificati.
     *
     * Prima si leggevano e decodificavano tre volte, una per grafico, ogni
     * esito pesa una trentina di kilobyte, e la pagina e' pubblica e senza
     * cache: con la cache del motore dentro, ogni visita costava tempo e
     * memoria proporzionali a TUTTA la tabella.
     *
     * @return list<array<string,mixed>>
     */
    private function carte(): array
    {
        if ($this->carte === null) {
            $this->carte = [];
            foreach (Database::righe('SELECT esito FROM calcoli WHERE ' . self::CARTE_VERE . ' AND esito IS NOT NULL') as $r) {
                $t = json_decode((string) $r['esito'], true);
                if (is_array($t)) {
                    $this->carte[] = ['esito' => $t];
                }
            }
        }

        return $this->carte;
    }

    public function pagina(Request $r): Response
    {
        if (!Impostazioni::attiva('statistiche_pubbliche')) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo'    => 'Statistiche non disponibili',
                'stato'     => 403,
                'messaggio' => 'Le statistiche pubbliche sono disattivate in questo momento.',
            ]), 403);
        }

        return Response::html(Vista::pagina('statistiche', [
            'titolo'   => 'Statistiche',
            'sezione'  => 'statistiche',
            'generale' => $this->generale(),
            'segni'    => $this->distribuzioneSegni(),
            'elementi' => $this->elementi(),
            'nascite'  => $this->nascite(),
            'voti'     => $this->voti(),
            'aspetti'  => $this->aspettiFrequenti(),
            'paesi'    => $this->paesi(),
        ]));
    }

    /** @return array<string,int> */
    private function generale(): array
    {
        return [
            'carte'      => (int) Database::valore('SELECT COUNT(*) FROM calcoli WHERE ' . self::CARTE_VERE),
            'richieste'  => (int) Database::valore('SELECT COALESCE(SUM(richieste),0) FROM calcoli WHERE ' . self::CARTE_VERE),
            'soggetti'   => (int) Database::valore('SELECT COUNT(*) FROM soggetti'),
            'luoghi'     => (int) Database::valore('SELECT COUNT(DISTINCT luogo_nome) FROM soggetti'),
            'senza_ora'  => (int) Database::valore('SELECT COUNT(*) FROM soggetti WHERE precisione_ora = ?', ['ignota']),
            'messaggi'   => (int) Database::valore('SELECT COUNT(*) FROM guestbook WHERE stato = ?', ['approvato']),
        ];
    }

    /**
     * Sole, Luna e Ascendente per segno.
     *
     * Si leggono dal JSON della carta: e' l'unico posto dove ci sono, e
     * duplicarli in colonne dedicate vorrebbe dire tenerli allineati per
     * sempre. Le carte sono poche migliaia, e la lettura costa millisecondi.
     *
     * @return array<string,list<array<string,mixed>>>
     */
    private function distribuzioneSegni(): array
    {
        $conta = ['sole' => array_fill(0, 12, 0), 'luna' => array_fill(0, 12, 0), 'asc' => array_fill(0, 12, 0)];
        $totali = ['sole' => 0, 'luna' => 0, 'asc' => 0];

        foreach ($this->carte() as $r) {
            $t = $r['esito'];
            if (!is_array($t)) {
                continue;
            }

            foreach (['sole', 'luna'] as $c) {
                if (isset($t['corpi'][$c]['segno'])) {
                    $conta[$c][(int) $t['corpi'][$c]['segno']]++;
                    $totali[$c]++;
                }
            }
            // L'Ascendente non si conta quando l'ora e' ignota: sarebbe
            // rumore, e falserebbe proprio il grafico piu' delicato.
            if (($t['carta']['ora_ignota'] ?? false) !== true && isset($t['punti']['asc']['segno'])) {
                $conta['asc'][(int) $t['punti']['asc']['segno']]++;
                $totali['asc']++;
            }
        }

        $segni = Corpi::segni();
        $fuori = [];

        foreach ($conta as $chiave => $valori) {
            $massimo = max(1, max($valori));
            $righe = [];
            foreach ($valori as $i => $n) {
                $righe[] = [
                    'segno'    => $segni[$i]['nome'],
                    'glifo'    => $segni[$i]['glifo'],
                    'elemento' => $segni[$i]['elemento'],
                    'quanti'   => $n,
                    'quota'    => (int) round($n / $massimo * 100),
                    'percento' => $totali[$chiave] > 0 ? round($n / $totali[$chiave] * 100, 1) : 0.0,
                ];
            }
            $fuori[$chiave] = ['righe' => $righe, 'totale' => $totali[$chiave]];
        }

        return $fuori;
    }

    /** @return array<string,mixed> */
    private function elementi(): array
    {
        $el = ['fuoco' => 0.0, 'terra' => 0.0, 'aria' => 0.0, 'acqua' => 0.0];
        $mo = ['cardinale' => 0.0, 'fisso' => 0.0, 'mobile' => 0.0];
        $n = 0;

        foreach ($this->carte() as $r) {
            $t = $r['esito'];
            if (!isset($t['bilanci']['segni']['elementi'])) {
                continue;
            }
            foreach ($t['bilanci']['segni']['elementi'] as $k => $v) {
                $el[$k] += (float) $v;
            }
            foreach ($t['bilanci']['segni']['modalita'] as $k => $v) {
                $mo[$k] += (float) $v;
            }
            $n++;
        }

        $quota = static function (array $a): array {
            $s = array_sum($a);
            $f = [];
            foreach ($a as $k => $v) {
                $f[] = ['nome' => $k, 'valore' => $s > 0 ? round($v / $s * 100, 1) : 0.0,
                        'quota' => $s > 0 ? (int) round($v / max(0.001, max($a)) * 100) : 0];
            }

            return $f;
        };

        return ['carte' => $n, 'elementi' => $quota($el), 'modalita' => $quota($mo)];
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function nascite(): array
    {
        $decenni = Database::righe(
            'SELECT FLOOR(YEAR(data_nascita)/10)*10 AS decennio, COUNT(*) AS n
               FROM soggetti GROUP BY decennio ORDER BY decennio'
        );
        $mesi = Database::righe(
            'SELECT MONTH(data_nascita) AS mese, COUNT(*) AS n FROM soggetti GROUP BY mese ORDER BY mese'
        );
        // L'ora la sanno solo quelli che l'hanno dichiarata: il grafico dice
        // qualcosa sulle nascite solo se si esclude chi non la conosce.
        $ore = Database::righe(
            'SELECT HOUR(ora_nascita) AS ora, COUNT(*) AS n FROM soggetti
              WHERE ora_nascita IS NOT NULL AND precisione_ora = ? GROUP BY ora ORDER BY ora',
            ['esatta'],
        );

        $scala = static function (array $righe, string $campo): array {
            $max = 1;
            foreach ($righe as $r) {
                $max = max($max, (int) $r['n']);
            }
            $f = [];
            foreach ($righe as $r) {
                $f[] = ['etichetta' => (string) $r[$campo], 'quanti' => (int) $r['n'],
                        'quota' => (int) round((int) $r['n'] / $max * 100)];
            }

            return $f;
        };

        return [
            'decenni' => $scala($decenni, 'decennio'),
            'mesi'    => $scala($mesi, 'mese'),
            'ore'     => $scala($ore, 'ora'),
        ];
    }

    /** @return array<string,mixed> */
    private function voti(): array
    {
        $r = Database::riga(
            'SELECT AVG(voto_gradimento) AS g, COUNT(voto_gradimento) AS ng,
                    AVG(voto_attinenza) AS a,  COUNT(voto_attinenza) AS na
               FROM guestbook WHERE stato = ?', ['approvato']
        );

        $perValore = [];
        foreach (['voto_gradimento', 'voto_attinenza'] as $campo) {
            $righe = Database::righe(
                "SELECT {$campo} AS v, COUNT(*) AS n FROM guestbook
                  WHERE stato = ? AND {$campo} IS NOT NULL GROUP BY v ORDER BY v", ['approvato']
            );
            $max = 1;
            foreach ($righe as $x) {
                $max = max($max, (int) $x['n']);
            }
            $perValore[$campo] = array_map(
                static fn (array $x): array => ['valore' => (int) $x['v'], 'quanti' => (int) $x['n'],
                                                'quota' => (int) round((int) $x['n'] / $max * 100)],
                $righe,
            );
        }

        return [
            'gradimento'   => $r['g'] === null ? null : round((float) $r['g'], 2),
            'n_gradimento' => (int) ($r['ng'] ?? 0),
            'attinenza'    => $r['a'] === null ? null : round((float) $r['a'], 2),
            'n_attinenza'  => (int) ($r['na'] ?? 0),
            'dettaglio'    => $perValore,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function aspettiFrequenti(): array
    {
        $conta = [];

        foreach ($this->carte() as $r) {
            $t = $r['esito'];
            foreach ($t['aspetti']['elenco'] ?? [] as $a) {
                $conta[(string) $a['aspetto_nome']] = ($conta[(string) $a['aspetto_nome']] ?? 0) + 1;
            }
        }

        arsort($conta);
        $max = max(1, ...array_values($conta ?: [1]));
        $f = [];
        foreach ($conta as $nome => $n) {
            $f[] = ['nome' => $nome, 'quanti' => $n, 'quota' => (int) round($n / $max * 100)];
        }

        return $f;
    }

    /** @return list<array<string,mixed>> */
    private function paesi(): array
    {
        $righe = Database::righe(
            'SELECT paese, COUNT(DISTINCT id) AS n FROM sessioni
              WHERE paese IS NOT NULL AND paese <> ? AND bot = 0
              GROUP BY paese ORDER BY n DESC LIMIT 12', ['']
        );

        $max = 1;
        foreach ($righe as $r) {
            $max = max($max, (int) $r['n']);
        }

        return array_map(static function (array $r) use ($max): array {
            $cc = (string) $r['paese'];

            return [
                'paese' => $cc,
                'nome'  => class_exists(\Locale::class) ? (\Locale::getDisplayRegion('-' . $cc, 'it') ?: $cc) : $cc,
                'quanti' => (int) $r['n'],
                'quota' => (int) round((int) $r['n'] / $max * 100),
            ];
        }, $righe);
    }
}
