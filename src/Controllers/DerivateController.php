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
        $anno = (int) ($r->query('anno') ?? date('Y'));
        $anno = max(1800, min(2199, $anno));

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

        // --- progressioni secondarie ----------------------------------------
        $progresso = null;
        $contatti = [];
        try {
            $jdProg = Derivate::jdProgresso($jdNatale, $jdQuando);
            $progresso = $motore->tema($this->componentiDa($jdProg) + [
                'lat' => (float) $primo['lat'], 'lon' => (float) $primo['lon'],
                'alt' => (int) $primo['altitudine'], 'sistema_case' => 'placido',
            ]);
            $contatti = Derivate::contatti($progresso, $natale);
        } catch (\Throwable $e) {
            $errori['progressioni'] = $e->getMessage();
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
                    'alt' => (int) $primo['altitudine'], 'sistema_case' => 'placido',
                ]),
            ];
        } catch (\Throwable $e) {
            $errori['rivoluzione'] = $e->getMessage();
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
              WHERE c.gettone = ? LIMIT 1',
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
