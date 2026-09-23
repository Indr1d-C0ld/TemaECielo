<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Astro\Derivate;
use App\Astro\Motore;
use App\Astro\Sinastria;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;
use App\Support\Telemetria;

/**
 * Le carte derivate di una carta natale.
 *
 * Tutte partono dal permalink: chi ha il gettone puo' vedere come la propria
 * carta si muove nel tempo, senza che serva ricalcolare niente e senza account.
 */
final class DerivateController
{
    /** GET /carta/{gettone}/derivate */
    public function pagina(Request $r, array $argomenti): Response
    {
        $primo = $this->carta((string) ($argomenti['gettone'] ?? ''));
        if ($primo === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Carta non trovata', 'stato' => 404,
                'messaggio' => 'Questo indirizzo non corrisponde a nessuna carta.',
            ]), 404);
        }

        $natale = $primo['tema'];
        $jdNatale = (float) $natale['tempo']['jd_ut'];

        // L'anno su cui si guarda: quello corrente, o quello chiesto.
        // Non prima della nascita — una rivoluzione solare dell'anno prima non
        // esiste — e non oltre le effemeridi, lasciando un anno per cercare il
        // ritorno del Sole.
        $natoNel = (int) date('Y', strtotime((string) $primo['data_nascita']));
        $anno = (int) ($r->query('anno') ?? date('Y'));
        $anno = max($natoNel, min(2398, $anno));

        // Il momento a cui riferire progressioni e profezioni: il compleanno
        // di quell'anno, che e' il modo in cui si guardano di solito.
        $quando = new \DateTimeImmutable(sprintf(
            '%04d-%02d-%02d 12:00:00',
            $anno,
            (int) date('n', strtotime((string) $primo['data_nascita'])),
            (int) date('j', strtotime((string) $primo['data_nascita'])),
        ), new \DateTimeZone('UTC'));

        $jdQuando = $this->jd($quando);
        $eta = Derivate::eta($jdNatale, $jdQuando);

        // Gli anni compiuti si contano sul CALENDARIO, non sull'eta' solare
        // frazionaria. Chi e' nato alle 21:14 e guarda il proprio compleanno a
        // mezzogiorno non ha ancora compiuto l'anno per nove ore, e la
        // profezione tornava indietro di un segno: l'Ascendente profetto
        // dipendeva dall'ora a cui si apriva la pagina, che e' assurdo.
        $anniCompiuti = max(0, $anno - (int) date('Y', strtotime((string) $primo['data_nascita'])));

        $motore = new Motore();
        $errori = [];

        // Il sistema di case della carta natale, non Placido d'ufficio: una
        // progressione o una rivoluzione si confrontano con la natale, e
        // confrontare case di sistemi diversi e' confrontare misure diverse.
        // Per la carta solare (ora ignota) restano i segni interi.
        $sistema = (string) ($natale['carta']['sistema_case'] ?? 'placido');
        if (!array_key_exists($sistema, \App\Astro\Corpi::sistemiCase())) {
            $sistema = 'segni_interi';
        }

        // --- progressioni secondarie ----------------------------------------
        $progresso = null;
        $contatti = [];
        try {
            $jdProg = Derivate::jdProgresso($jdNatale, $jdQuando);
            $progresso = $motore->tema($this->componentiDa($jdProg) + [
                'lat' => (float) $primo['lat'], 'lon' => (float) $primo['lon'],
                'alt' => (int) $primo['altitudine'], 'sistema_case' => $sistema,
            ]);
            $contatti = Derivate::contatti($progresso, $natale);
        } catch (\Throwable $e) {
            // Il messaggio vero va nel diario, non nella pagina: puo' contenere
            // l'uscita d'errore del processo di calcolo, con percorsi del server.
            registro('derivate/progressioni: ' . $e->getMessage(), 'warn');
            $errori['progressioni'] = 'Il calcolo non è riuscito. Riprova fra poco.';
        }

        // --- direzioni di arco solare ---------------------------------------
        $direzioni = null;
        if ($progresso !== null) {
            $direzioni = Derivate::direzioni($natale, Derivate::arcoSolare($natale, $progresso));
        }

        // --- rivoluzione solare ---------------------------------------------
        $rivoluzione = null;
        try {
            // Si parte dieci giorni prima del compleanno: il Sole torna sul
            // grado natale a un'ora diversa ogni anno, e l'istante puo'
            // scivolare al giorno prima.
            $ritorno = $motore->ritorno([
                'corpo'        => 'sole',
                'longitudine'  => (float) $natale['corpi']['sole']['lon'],
                'jd_da'        => $jdQuando - 10.0,
                'jd_a'         => $jdQuando + 30.0,
            ]);

            $rivoluzione = [
                'istante' => $ritorno,
                'tema'    => $motore->tema([
                    'anno' => $ritorno['anno'], 'mese' => $ritorno['mese'],
                    'giorno' => $ritorno['giorno'], 'ora_ut' => $ritorno['ora_ut'],
                    'lat' => (float) $primo['lat'], 'lon' => (float) $primo['lon'],
                    'alt' => (int) $primo['altitudine'], 'sistema_case' => $sistema,
                ]),
            ];
        } catch (\Throwable $e) {
            // Il messaggio vero va nel diario, non nella pagina: puo' contenere
            // l'uscita d'errore del processo di calcolo, con percorsi del server.
            registro('derivate/rivoluzione: ' . $e->getMessage(), 'warn');
            $errori['rivoluzione'] = 'Il calcolo non è riuscito. Riprova fra poco.';
        }

        Telemetria::evento('derivate', (string) $anno);

        return Response::html(Vista::pagina('derivate', [
            'titolo'       => 'Le carte del tempo',
            'sezione'      => 'carta',
            'primo'        => $primo,
            'natale'       => $natale,
            'anno'         => $anno,
            'eta'          => $eta,
            'anniCompiuti' => $anniCompiuti,
            'progresso'    => $progresso,
            'contatti'     => $contatti,
            'direzioni'    => $direzioni,
            'rivoluzione'  => $rivoluzione,
            'profezione'   => Derivate::profezione($natale, $anniCompiuti),
            'incrociati'   => $rivoluzione !== null
                ? Sinastria::aspettiIncrociati($natale, $rivoluzione['tema'], true, true)
                : [],
            'errori'       => $errori,
            'gettone'      => $primo['gettone'],
        ]))->conIntestazione('X-Robots-Tag', 'noindex, nofollow');
    }

    // ------------------------------------------------------------------------

    private function jd(\DateTimeImmutable $t): float
    {
        // Il giorno giuliano parte da mezzogiorno, il tempo Unix da mezzanotte.
        return $t->getTimestamp() / 86400.0 + 2440587.5;
    }

    /** @return array<string,mixed> */
    private function componentiDa(float $jd): array
    {
        $unix = (int) round(($jd - 2440587.5) * 86400.0);
        $t = new \DateTimeImmutable('@' . $unix);

        return [
            'anno'   => (int) $t->format('Y'),
            'mese'   => (int) $t->format('n'),
            'giorno' => (int) $t->format('j'),
            'ora_ut' => (int) $t->format('G') + (int) $t->format('i') / 60.0 + (int) $t->format('s') / 3600.0,
        ];
    }

    /** @return array<string,mixed>|null */
    private function carta(string $gettone): ?array
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', $gettone);

        $riga = Database::riga(
            'SELECT c.esito, s.nome, s.data_nascita, s.lat, s.lon, s.altitudine, s.luogo_nome
               FROM calcoli c
               JOIN calcoli_soggetti cs ON cs.calcolo_id = c.id AND cs.ruolo = \'primo\'
               JOIN soggetti s ON s.id = cs.soggetto_id
              WHERE c.gettone = ?
              ORDER BY cs.soggetto_id
              LIMIT 1',
            [$gettone],
        );

        if ($riga === null) {
            return null;
        }

        $tema = json_decode((string) $riga['esito'], true);
        if (!is_array($tema)) {
            return null;
        }

        $riga['tema'] = $tema;
        $riga['gettone'] = $gettone;

        return $riga;
    }
}
