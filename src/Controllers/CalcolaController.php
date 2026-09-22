<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Astro\Motore;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Vista;
use App\Corpus\Corpus;
use App\Corpus\Montatore;
use App\Luogo\Gazetteer;
use App\Grafica\RuotaTema;
use App\Grafica\VoltaCeleste;
use App\Luogo\Tempo;
use App\Support\Telemetria;

/**
 * Il modulo di nascita e il calcolo che ne segue.
 */
final class CalcolaController
{
    /** GET /calcola */
    public function modulo(Request $r): Response
    {
        Telemetria::evento('modulo_aperto');

        return Response::html(Vista::pagina('calcola', [
            'titolo'   => 'Calcola il tuo tema',
            'sezione'  => 'home',
            'mappa'    => true,
            'dati'     => Session::get('__modulo', []),
            'errori'   => Session::get('__errori', []),
            'sistemi'  => \App\Astro\Corpi::sistemiCase(),
        ]));
    }

    /** POST /calcola */
    public function calcola(Request $r): Response
    {
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta. Riprova.');

            return Response::redirect(url('/calcola'));
        }

        $dati   = $this->leggi($r);
        $errori = $this->valida($dati);

        if ($errori !== []) {
            Session::set('__modulo', $dati);
            Session::set('__errori', $errori);
            Telemetria::evento('modulo_rifiutato', implode(',', array_keys($errori)));

            return Response::redirect(url('/calcola'));
        }

        // --- l'ora locale diventa Tempo Universale ---------------------------
        [$ora, $precisione] = match ($dati['precisione']) {
            'ignota'         => ['12:00', 'ignota'],
            'approssimativa' => [$dati['ora'], 'approssimativa'],
            default          => [$dati['ora'], 'esatta'],
        };

        $tempo = Tempo::risolvi($dati['data'], $ora, $dati['fuso']);

        // L'ora ambigua non si sceglie di nascosto: si chiede.
        if (($tempo['stato'] ?? '') === Tempo::AMBIGUO && ($dati['ambigua'] ?? '') === '') {
            Session::set('__modulo', $dati);
            Session::set('__ambigua', $tempo);
            Telemetria::evento('ora_ambigua', $dati['data'] . ' ' . $ora);

            return Response::html(Vista::pagina('ora-ambigua', [
                'titolo'  => 'Quale delle due?',
                'sezione' => 'home',
                'tempo'   => $tempo,
                'dati'    => $dati,
            ]));
        }

        if (($tempo['ok'] ?? false) !== true) {
            Session::set('__modulo', $dati);
            Session::set('__errori', ['ora' => $tempo['avviso'] ?? $tempo['errore'] ?? 'Ora non valida.']);
            Telemetria::evento('ora_inesistente', $dati['data'] . ' ' . $ora);

            return Response::redirect(url('/calcola'));
        }

        // Se l'utente ha scelto fra le due letture di un'ora ambigua, si prende quella.
        $componenti = $tempo['componenti_ut'];
        $offset     = $tempo['offset_secondi'];
        if (($dati['ambigua'] ?? '') !== '' && isset($tempo['alternative'][(int) $dati['ambigua']])) {
            $scelta     = $tempo['alternative'][(int) $dati['ambigua']];
            $componenti = $scelta['componenti_ut'];
            $offset     = $scelta['offset_secondi'];
        }

        // --- il calcolo ------------------------------------------------------
        try {
            $motore = new Motore();
            $tema = $motore->tema([
                'anno'         => $componenti['anno'],
                'mese'         => $componenti['mese'],
                'giorno'       => $componenti['giorno'],
                'ora_ut'       => $componenti['ora_ut'],
                'lat'          => $dati['lat'],
                'lon'          => $dati['lon'],
                'alt'          => $dati['altitudine'],
                // Con l'ora ignota le cuspidi non hanno senso: Segni Interi e'
                // l'unico sistema che non finge una precisione che non c'e'.
                'sistema_case' => $precisione === 'ignota' ? 'segni_interi' : $dati['sistema'],
                'ora_ignota'   => $precisione === 'ignota',
            ]);
        } catch (\Throwable $e) {
            registro('calcolo fallito: ' . $e->getMessage(), 'error');
            Telemetria::evento('calcolo_fallito', '', mb_substr($e->getMessage(), 0, 200));
            Session::set('__modulo', $dati);
            Session::set('__errori', ['motore' => 'Il motore di calcolo non ha risposto. Riprova fra poco.']);

            return Response::redirect(url('/calcola'));
        }

        // --- archiviazione ---------------------------------------------------
        $gettone = $this->archivia($dati, $componenti, $offset, $precisione, $tema);

        Telemetria::evento('calcolo_riuscito', $dati['luogo_nome'], (string) ($tema['meta']['durata_ms'] ?? 0));
        Session::togli('__modulo');
        Session::togli('__errori');
        Session::togli('__ambigua');

        return Response::redirect(url('/carta/' . $gettone));
    }

    /** GET /carta/{gettone} */
    public function carta(Request $r, array $argomenti): Response
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? ''));

        $riga = Database::riga(
            'SELECT c.id, c.esito, c.creato, c.richieste, s.nome, s.data_nascita, s.ora_nascita,
                    s.precisione_ora, s.luogo_nome, s.lat, s.lon, s.altitudine, s.fuso, s.offset_minuti
               FROM calcoli c
               JOIN calcoli_soggetti cs ON cs.calcolo_id = c.id AND cs.ruolo = \'primo\'
               JOIN soggetti s ON s.id = cs.soggetto_id
              WHERE c.gettone = ? LIMIT 1',
            [$gettone],
        );

        if ($riga === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo'    => 'Carta non trovata',
                'stato'     => 404,
                'messaggio' => 'Questo indirizzo non corrisponde a nessuna carta. '
                    . 'Il gettone e\' l\'unica chiave: se e\' stato perso, la carta va rifatta.',
            ]), 404);
        }

        $tema = json_decode((string) $riga['esito'], true);
        if (!is_array($tema)) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Carta illeggibile', 'stato' => 500,
                'messaggio' => 'I dati di questa carta non sono leggibili.',
            ]), 500);
        }

        // Il registro scelto resta in sessione: chi legge in tradizionale
        // vuole leggere in tradizionale anche la carta dopo.
        $registro = (string) ($r->query('registro') ?? '');
        if (!in_array($registro, ['tradizionale', 'moderno'], true)) {
            $registro = (string) Session::get('__registro', 'moderno');
        }
        Session::set('__registro', $registro);
        // Serve al guestbook: un voto di attinenza senza la carta a cui si
        // riferisce non e' verificabile da nessuno.
        Session::set('__ultima_carta', $gettone);

        try {
            $lettura = (new Montatore(new Corpus($registro)))->monta($tema);
        } catch (\Throwable $e) {
            registro('montaggio della lettura fallito: ' . $e->getMessage(), 'warn');
            // Una carta senza parole resta una carta: i dati ci sono tutti.
            $lettura = null;
        }

        return Response::html(Vista::pagina('carta', [
            'titolo'   => 'Tema di ' . ($riga['nome'] !== '' ? $riga['nome'] : 'anonimo'),
            'sezione'  => 'carta',
            'soggetto' => $riga,
            'tema'     => $tema,
            'gettone'  => $gettone,
            'lettura'  => $lettura,
            'registro' => $registro,
        ]))->conIntestazione('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    /**
     * GET /carta/{gettone}/ruota.svg
     *
     * La ruota come file autonomo: glifi e stile viaggiano dentro il file, cosi'
     * si apre in un editor vettoriale, si stampa, entra in un documento.
     */
    public function ruota(Request $r, array $argomenti): Response
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? ''));

        $esito = Database::valore('SELECT esito FROM calcoli WHERE gettone = ? LIMIT 1', [$gettone]);
        $tema  = is_string($esito) ? json_decode($esito, true) : null;

        if (!is_array($tema)) {
            return Response::testo('Carta non trovata.', 404);
        }

        Telemetria::evento('scarico_svg', $gettone);

        // Immutabile per costruzione: la chiave e' l'impronta dei dati di
        // nascita, e quella carta non cambiera' mai piu'.
        return Response::svg((new RuotaTema($tema, true))->disegna(), 200, true)
            ->conIntestazione('Content-Disposition', 'attachment; filename="tema-' . substr($gettone, 0, 8) . '.svg"')
            ->conIntestazione('X-Robots-Tag', 'noindex, nofollow');
    }

    /** GET /carta/{gettone}/cielo.svg — la volta celeste di quell'istante */
    public function cielo(Request $r, array $argomenti): Response
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? ''));

        $esito = Database::valore('SELECT esito FROM calcoli WHERE gettone = ? LIMIT 1', [$gettone]);
        $tema  = is_string($esito) ? json_decode($esito, true) : null;

        if (!is_array($tema)) {
            return Response::testo('Carta non trovata.', 404);
        }

        Telemetria::evento('scarico_cielo', $gettone);

        return Response::svg((new VoltaCeleste($tema, true))->disegna(), 200, true)
            ->conIntestazione('Content-Disposition', 'attachment; filename="cielo-' . substr($gettone, 0, 8) . '.svg"')
            ->conIntestazione('X-Robots-Tag', 'noindex, nofollow');
    }

    // ------------------------------------------------------------------------

    /**
     * Lettura e validazione del modulo, aperte anche alla sinastria.
     *
     * La seconda persona di una sinastria passa esattamente dagli stessi
     * controlli della prima: non esiste una versione «facile» dei dati di
     * nascita, e duplicare la validazione vorrebbe dire vederla divergere.
     *
     * @return array<string,mixed>
     */
    public function leggiPubblico(Request $r): array
    {
        return $this->leggi($r);
    }

    /**
     * @param array<string,mixed> $d
     * @return array<string,string>
     */
    public function validaPubblico(array $d): array
    {
        return $this->valida($d);
    }

    /** @return array<string,mixed> */
    private function leggi(Request $r): array
    {
        $luogoId = (int) ($r->post('luogo_id') ?? 0);
        $luogo   = $luogoId > 0 ? Gazetteer::perId($luogoId) : null;

        $lat = $r->post('lat');
        $lon = $r->post('lon');

        return [
            'nome'        => mb_substr(trim((string) $r->post('nome', '')), 0, 120),
            'data'        => trim((string) $r->post('data', '')),
            'ora'         => trim((string) $r->post('ora', '')),
            'precisione'  => in_array($r->post('precisione'), ['esatta', 'approssimativa', 'ignota'], true)
                ? (string) $r->post('precisione') : 'esatta',
            'luogo_id'    => $luogoId,
            'luogo_nome'  => mb_substr(trim((string) $r->post('luogo_nome', '')), 0, 190),
            'lat'         => is_numeric($lat) ? round((float) $lat, 6) : null,
            'lon'         => is_numeric($lon) ? round((float) $lon, 6) : null,
            'altitudine'  => (int) ($r->post('altitudine') ?? ($luogo['altitudine'] ?? 0)),
            'fuso'        => (string) ($r->post('fuso') ?: ($luogo['fuso'] ?? '')),
            'sistema'     => array_key_exists((string) $r->post('sistema'), \App\Astro\Corpi::sistemiCase())
                ? (string) $r->post('sistema') : 'placido',
            'ambigua'     => (string) ($r->post('ambigua') ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $d
     * @return array<string,string>
     */
    private function valida(array $d): array
    {
        $e = [];

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['data']) !== 1) {
            $e['data'] = 'Serve una data di nascita.';
        } else {
            [$a, $m, $g] = array_map('intval', explode('-', (string) $d['data']));
            if (!checkdate($m, $g, $a)) {
                $e['data'] = 'Quella data non esiste.';
            } elseif ($a < 1800 || $a > (int) date('Y') + 1) {
                // I file di effemeridi installati coprono dal 1800: oltre quel
                // limite la libreria ripiegherebbe in silenzio su un calcolo
                // meno preciso, ed e' meglio dirlo che lasciarlo accadere.
                $e['data'] = 'Per ora il portale calcola dal 1800 in avanti.';
            }
        }

        if ($d['precisione'] !== 'ignota' && preg_match('/^\d{1,2}:\d{2}$/', (string) $d['ora']) !== 1) {
            $e['ora'] = 'Serve un\'ora, oppure dichiara di non conoscerla.';
        }

        if ($d['lat'] === null || $d['lon'] === null) {
            $e['luogo'] = 'Scegli un luogo di nascita dalla ricerca o dalla mappa.';
        } elseif (abs((float) $d['lat']) > 90 || abs((float) $d['lon']) > 180) {
            $e['luogo'] = 'Coordinate fuori scala.';
        }

        if ($d['fuso'] === '' || !Tempo::zonaValida((string) $d['fuso'])) {
            $e['fuso'] = 'Fuso orario mancante o non riconosciuto.';
        }

        return $e;
    }

    /**
     * @param array<string,mixed> $d
     * @param array{anno:int,mese:int,giorno:int,ora_ut:float} $componenti
     * @param array<string,mixed> $tema
     */
    private function archivia(array $d, array $componenti, int $offset, string $precisione, array $tema): string
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            Database::esegui(
                'INSERT INTO soggetti
                   (nome, data_nascita, ora_nascita, precisione_ora, luogo_nome, luogo_paese,
                    lat, lon, altitudine, fuso, offset_minuti, ora_ut, creato)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
                [
                    $d['nome'],
                    $d['data'],
                    $precisione === 'ignota' ? null : $d['ora'] . ':00',
                    $precisione,
                    $d['luogo_nome'],
                    null,
                    $d['lat'],
                    $d['lon'],
                    $d['altitudine'],
                    $d['fuso'],
                    (int) round($offset / 60),
                    // L'ORA in Tempo Universale, non il giorno giuliano: la
                    // colonna e' un DECIMAL(9,6) e arriva a 999, mentre un
                    // giorno giuliano vale oltre due milioni. Il giorno
                    // giuliano sta gia' dentro la carta, in `tempo.jd_ut`.
                    round($componenti['ora_ut'], 6),
                ],
            );
            $soggettoId = Database::ultimoId();

            $impronta = (string) ($tema['meta']['impronta'] ?? '');

            // Il calcolo puo' esistere gia': la cache e' per impronta, e la
            // stessa carta chiesta da due persone e' una riga sola. Il gettone,
            // pero', deve restare quello della prima volta, altrimenti il
            // permalink gia' consegnato smetterebbe di funzionare.
            $esistente = Database::riga('SELECT id, gettone FROM calcoli WHERE impronta = ? LIMIT 1', [$impronta]);

            if ($esistente !== null && $esistente['gettone'] !== null) {
                $calcoloId = (int) $esistente['id'];
                $gettone   = (string) $esistente['gettone'];
            } else {
                $gettone = bin2hex(random_bytes(16));
                if ($esistente !== null) {
                    Database::esegui('UPDATE calcoli SET gettone = ? WHERE id = ?', [$gettone, (int) $esistente['id']]);
                    $calcoloId = (int) $esistente['id'];
                } else {
                    Database::esegui(
                        'INSERT INTO calcoli (gettone, impronta, tipo, esito, richieste, durata_ms, creato, ultima_richiesta)
                         VALUES (?,?,?,?,1,?,NOW(),NOW())',
                        [
                            $gettone, $impronta, 'natale',
                            (string) json_encode($tema, JSON_UNESCAPED_UNICODE),
                            (int) round((float) ($tema['meta']['durata_ms'] ?? 0)),
                        ],
                    );
                    $calcoloId = Database::ultimoId();
                }
            }

            Database::esegui(
                'INSERT IGNORE INTO calcoli_soggetti (calcolo_id, soggetto_id, ruolo) VALUES (?,?,\'primo\')',
                [$calcoloId, $soggettoId],
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $gettone;
    }
}
