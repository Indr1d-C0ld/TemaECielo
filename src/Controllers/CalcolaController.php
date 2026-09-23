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
use App\Corpus\Mondana;
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
            'dati'     => Session::prendi('__modulo', []),
            'errori'   => Session::prendi('__errori', []),
            'sistemi'  => \App\Astro\Corpi::sistemiCase(),
        ]));
    }

    /** POST /calcola */
    public function calcola(Request $r): Response
    {
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione è scaduta. Riprova.');

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

        $tempo = Tempo::risolviNelLuogo($dati['data'], $ora, $dati['fuso'], (float) $dati['lon']);

        // L'ora ambigua non si sceglie di nascosto: si chiede.
        if (($tempo['stato'] ?? '') === Tempo::AMBIGUO && ($dati['ambigua'] ?? '') === '') {
            Session::set('__modulo', $dati);
            Session::set('__ambigua', $tempo);
            // Senza data e ora: sono dati di nascita, e gli eventi non si cancellano
            // con la carta.
            Telemetria::evento('ora_ambigua');

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
            Telemetria::evento('ora_inesistente');

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
                // Lo scarto civile serve al worker per sapere quale sia il
                // giorno di nascita sull'orologio a muro (alba, tramonto).
                'offset_secondi' => $offset,
            ]);
        } catch (\Throwable $e) {
            registro('calcolo fallito: ' . $e->getMessage(), 'error');
            Telemetria::evento('calcolo_fallito', '', mb_substr($e->getMessage(), 0, 200));
            Session::set('__modulo', $dati);
            Session::set('__errori', ['motore' => $e instanceof \App\Support\TroppeRichieste
                ? 'Troppe richieste di calcolo da questo indirizzo. Riprova fra un minuto.'
                : 'Il motore di calcolo non ha risposto. Riprova fra poco.']);

            return Response::redirect(url('/calcola'));
        }

        // --- archiviazione ---------------------------------------------------
        $gettone = $this->archivia($dati, $componenti, $offset, $precisione, $tema);

        Telemetria::evento('calcolo_riuscito', '', (string) ($tema['meta']['durata_ms'] ?? 0));
        Session::togli('__modulo');
        Session::togli('__errori');
        Session::togli('__ambigua');

        // La regia puo' mettere la carta nell'archivio pubblico gia' dal modulo.
        if (($dati['archivio'] ?? '') === '1' && \App\Auth\Auth::amministratore()) {
            $calcoloId = (int) Database::valore('SELECT id FROM calcoli WHERE gettone = ?', [$gettone]);
            // La classe Rodden e la precisione dell'ora devono dire la stessa
            // cosa: un'ora ignota e' X, e X con un'ora data diventa C.
            $rodden = strtoupper((string) ($dati['archivio_rodden'] ?? 'C'));
            if ($precisione === 'ignota') {
                $rodden = 'X';
            } elseif ($rodden === 'X') {
                $rodden = 'C';
            }
            try {
                $slug = \App\Archivio\Archivio::salva($calcoloId, [
                    'nome' => $dati['nome'], 'tipo' => $dati['archivio_tipo'] ?? 'persona',
                    'categoria' => $dati['archivio_categoria'] ?? '', 'rodden' => $rodden,
                    'fonte' => $dati['archivio_fonte'] ?? '', 'pubblicata' => true,
                ]);
                \App\Auth\Auth::traccia('archivio:scheda', $slug, $dati['nome']);
                Session::lampo('bene', 'La carta è nell\'archivio. Completa la scheda con una nota e la fonte.');
                return Response::redirect(url('/admin/carte/' . $gettone));
            } catch (\InvalidArgumentException $e) {
                Session::lampo('male', 'Carta salvata, ma non in archivio: ' . $e->getMessage());
            }
        }

        return Response::redirect(url('/carta/' . $gettone));
    }

    /**
     * POST /carta/{gettone}/elimina
     *
     * La home e il modulo promettevano che chi conserva l'indirizzo puo'
     * cancellare la propria carta, e non c'era modo di farlo. L'indirizzo e'
     * l'unica chiave — non c'e' account — quindi chi lo possiede puo'
     * cancellare: e' lo stesso principio per cui puo' leggerla.
     *
     * Si cancellano la carta e i dati di nascita della persona. Il guestbook
     * perde soltanto il riferimento (la chiave esterna mette NULL): un messaggio
     * pubblicato resta del suo autore.
     */
    public function elimina(Request $r, array $argomenti): Response
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? '')) ?? '';

        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione è scaduta. Riprova.');
            return Response::redirect(url('/carta/' . $gettone));
        }
        if ($r->post('conferma') !== 'si') {
            Session::lampo('male', 'Per cancellare la carta spunta la conferma: non si torna indietro.');
            return Response::redirect(url('/carta/' . $gettone));
        }

        $id = Database::valore('SELECT id FROM calcoli WHERE gettone = ? LIMIT 1', [$gettone]);
        // Una carta dell'archivio ha l'indirizzo pubblico: chiunque la vede, e
        // chiunque potrebbe cancellarla. Si toglie solo dalla regia.
        if ($id !== null && \App\Archivio\Archivio::perCalcolo((int) $id) !== null && !\App\Auth\Auth::amministratore()) {
            Session::lampo('male', 'Questa carta fa parte dell\'archivio: non si cancella da qui.');
            return Response::redirect(url('/carta/' . $gettone));
        }
        if ($id === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Carta non trovata', 'stato' => 404,
                'messaggio' => 'Questa carta non esiste, o è già stata cancellata.',
            ]), 404);
        }

        self::cancella((int) $id);

        if (Session::get('__ultima_carta') === $gettone) {
            Session::togli('__ultima_carta');
        }
        Telemetria::evento('carta_cancellata');
        Session::lampo('bene', 'La carta e i dati di nascita sono stati cancellati. L\'indirizzo non porta più a nulla.');

        return Response::redirect(url('/'));
    }

    /**
     * Cancella una carta e i dati di nascita di chi non compare in altre carte.
     * La usa anche l'importatore dell'archivio, per ricalcolare una scheda.
     */
    public static function cancella(int $calcoloId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $soggetti = array_map('intval', array_column(
                Database::righe('SELECT soggetto_id FROM calcoli_soggetti WHERE calcolo_id = ?', [$calcoloId]),
                'soggetto_id',
            ));
            Database::esegui('DELETE FROM calcoli WHERE id = ?', [$calcoloId]);
            foreach ($soggetti as $s) {
                // Solo se la persona non compare in nessun'altra carta.
                Database::esegui(
                    'DELETE FROM soggetti WHERE id = ? AND NOT EXISTS
                       (SELECT 1 FROM calcoli_soggetti WHERE soggetto_id = ?)',
                    [$s, $s],
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** GET /carta/{gettone} */
    public function carta(Request $r, array $argomenti): Response
    {
        return $this->mostraCarta($r, (string) preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? '')));
    }

    /**
     * La pagina di una carta. La usa anche l'archivio pubblico, che mostra la
     * stessa carta sotto un indirizzo leggibile e con la sua scheda.
     */
    public function mostraCarta(Request $r, string $gettone): Response
    {

        $riga = Database::riga(
            'SELECT c.id, c.esito, c.creato, c.richieste, s.nome, s.data_nascita, s.ora_nascita,
                    s.precisione_ora, s.luogo_nome, s.lat, s.lon, s.altitudine, s.fuso, s.offset_minuti
               FROM calcoli c
               JOIN calcoli_soggetti cs ON cs.calcolo_id = c.id AND cs.ruolo = \'primo\'
               JOIN soggetti s ON s.id = cs.soggetto_id
              WHERE c.gettone = ?
              ORDER BY cs.soggetto_id
              LIMIT 1',
            [$gettone],
        );

        if ($riga === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo'    => 'Carta non trovata',
                'stato'     => 404,
                'messaggio' => 'Questo indirizzo non corrisponde a nessuna carta. '
                    . 'Il gettone è l\'unica chiave: se è stato perso, la carta va rifatta.',
            ]), 404);
        }

        $tema = json_decode((string) $riga['esito'], true);
        if (!is_array($tema)) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Carta illeggibile', 'stato' => 500,
                'messaggio' => 'I dati di questa carta non sono leggibili.',
            ]), 500);
        }

        $scheda = \App\Archivio\Archivio::perCalcolo((int) $riga['id']);
        $pubblica = $scheda !== null && (int) $scheda['pubblicata'] === 1;
        // Protetta anche da bozza: la cancellazione resta alla regia.
        $protetta = $scheda !== null;
        // Una scheda non ancora pubblicata la vede solo la regia, anche se il
        // gettone della carta e' gia' in giro.
        if ($scheda !== null && !$pubblica && !\App\Auth\Auth::amministratore()) {
            $scheda = null;
        }
        $mondiale = $scheda !== null && in_array($scheda['tipo'], ['evento', 'nazione'], true);

        // Il registro scelto resta in sessione: chi legge in tradizionale
        // vuole leggere in tradizionale anche la carta dopo.
        $registro = (string) ($r->query('registro') ?? '');
        if (!in_array($registro, ['tradizionale', 'moderno'], true)) {
            $registro = (string) Session::get('__registro', 'moderno');
        }
        Session::set('__registro', $registro);
        // Serve al guestbook: un voto di attinenza senza la carta a cui si
        // riferisce non e' verificabile da nessuno. Una carta dell'archivio non
        // e' la propria, e non si vota.
        if (!$pubblica) {
            Session::set('__ultima_carta', $gettone);
        }

        $lettura = null;
        $mondana = null;
        try {
            // Una Repubblica o un terremoto non hanno «una vita emotiva»: le
            // carte di evento e di fondazione si leggono con le chiavi mondiali.
            if ($mondiale) {
                $mondana = Mondana::monta($tema, (string) $scheda['tipo']);
            } else {
                $lettura = (new Montatore(new Corpus($registro)))->monta($tema);
            }
        } catch (\Throwable $e) {
            registro('montaggio della lettura fallito: ' . $e->getMessage(), 'warn');
            // Una carta senza parole resta una carta: i dati ci sono tutti.
        }

        $risposta = Response::html(Vista::pagina('carta', [
            'scheda'   => $scheda,
            'protetta' => $protetta,
            'titolo'   => $scheda !== null ? (string) $scheda['nome']
                : 'Tema di ' . ($riga['nome'] !== '' ? $riga['nome'] : 'anonimo'),
            'sezione'  => 'carta',
            'soggetto' => $riga,
            'tema'     => $tema,
            'gettone'  => $gettone,
            'lettura'  => $lettura,
            'mondana'  => $mondana,
            'registro' => $registro,
        ]));

        // Una carta d'archivio pubblicata e' fatta per essere trovata; quella di
        // un visitatore no, mai.
        return $pubblica ? $risposta : $risposta->conIntestazione('X-Robots-Tag', 'noindex, nofollow, noarchive');
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

        Telemetria::evento('scarico_svg');

        // Non in cache condivisa: l'indirizzo porta il gettone della carta, e
        // dopo una cancellazione il disegno — con i dati di nascita — non deve
        // sopravvivere un anno nelle cache dei browser e dei proxy, come
        // accadeva con «public, immutable».
        return Response::svg((new RuotaTema($tema, true))->disegna())
            ->conIntestazione('Cache-Control', 'private, no-store')
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

        Telemetria::evento('scarico_cielo');

        return Response::svg((new VoltaCeleste($tema, true))->disegna())
            ->conIntestazione('Cache-Control', 'private, no-store')
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
        $lat = $r->post('lat');
        $lon = $r->post('lon');
        $fusoInviato = (string) ($r->post('fuso') ?? '');
        $nomeInviato = (string) ($r->post('luogo_nome') ?? '');
        $altInviata  = (string) ($r->post('altitudine') ?? '');

        // Il nome nel campo di ricerca e' cambiato dopo l'ultima scelta (il
        // modulo ne tiene una copia in `luogo_era`): le coordinate nascoste sono
        // quelle del luogo di PRIMA. Si butta la scelta vecchia e si cerca il
        // nome scritto, come senza JavaScript.
        $era = $r->post('luogo_era');
        $scritto = trim((string) $r->post('luogo_testo', ''));
        if ($era !== null && $scritto !== '' && $scritto !== trim($era)) {
            [$luogoId, $lat, $lon, $fusoInviato, $nomeInviato, $altInviata] = [0, null, null, '', '', ''];
        }
        $luogo = $luogoId > 0 ? Gazetteer::perId($luogoId) : null;

        // Senza JavaScript arriva solo cio' che si e' scritto nel campo di
        // ricerca: niente identificativo, niente coordinate, niente fuso. Il
        // server fa allora quello che avrebbe fatto il completamento automatico,
        // e prende il primo luogo che la ricerca restituisce. Il modulo
        // prometteva di funzionare senza script, e prima non partiva.
        $testo = trim((string) $r->post('luogo_testo', ''));
        if ($luogo === null && (!is_numeric($lat) || !is_numeric($lon)) && mb_strlen($testo) >= 3) {
            $trovato = Gazetteer::cerca(mb_substr($testo, 0, 120), null, 1)[0] ?? null;
            if ($trovato !== null) {
                $luogo   = $trovato;
                $luogoId = (int) $trovato['id'];
                $lat     = (string) $trovato['lat'];
                $lon     = (string) $trovato['lon'];
            }
        }

        // Il fuso, se lo script non l'ha messo, si ricava dalle coordinate con
        // la stessa funzione che usa la mappa.
        $fuso = $fusoInviato !== '' ? $fusoInviato : (string) ($luogo['fuso'] ?? '');
        if ($fuso === '' && is_numeric($lat) && is_numeric($lon)
            && abs((float) $lat) <= 90 && abs((float) $lon) <= 180) {
            $fuso = (string) (Gazetteer::fusoDi((float) $lat, (float) $lon)['fuso'] ?? '');
        }

        $nomeLuogo = mb_substr(trim($nomeInviato), 0, 190);
        if ($nomeLuogo === '' && $luogo !== null) {
            $nomeLuogo = (string) $luogo['nome'] . (($luogo['contesto'] ?? '') !== '' ? ', ' . $luogo['contesto'] : '');
        }

        return [
            'nome'        => mb_substr(trim((string) $r->post('nome', '')), 0, 120),
            'data'        => trim((string) $r->post('data', '')),
            'ora'         => trim((string) $r->post('ora', '')),
            'precisione'  => in_array($r->post('precisione'), ['esatta', 'approssimativa', 'ignota'], true)
                ? (string) $r->post('precisione') : 'esatta',
            'luogo_id'    => $luogoId,
            'luogo_nome'  => $nomeLuogo,
            'lat'         => is_numeric($lat) ? round((float) $lat, 6) : null,
            'lon'         => is_numeric($lon) ? round((float) $lon, 6) : null,
            // La colonna e' uno SMALLINT e il database e' in modalita' stretta:
            // un'altitudine fuori scala farebbe fallire l'archiviazione dopo il
            // calcolo. Il modulo dice -500..9000, e qui lo si fa valere.
            'altitudine'  => max(-500, min(9000, (int) ($altInviata ?: ($luogo['altitudine'] ?? 0)))),
            'fuso'        => $fuso,
            'sistema'     => array_key_exists((string) $r->post('sistema'), \App\Astro\Corpi::sistemiCase())
                ? (string) $r->post('sistema') : 'placido',
            'ambigua'     => (string) ($r->post('ambigua') ?? ''),
        ] + (\App\Auth\Auth::amministratore() ? [
            // I campi della regia viaggiano con gli altri: devono sopravvivere
            // alla pagina dell'ora ambigua e a un modulo rifiutato.
            'archivio'           => $r->post('archivio') === '1' ? '1' : '',
            'archivio_tipo'      => mb_substr((string) $r->post('archivio_tipo', ''), 0, 20),
            'archivio_categoria' => mb_substr((string) $r->post('archivio_categoria', ''), 0, 40),
            'archivio_rodden'    => mb_substr((string) $r->post('archivio_rodden', ''), 0, 2),
            'archivio_fonte'     => mb_substr((string) $r->post('archivio_fonte', ''), 0, 500),
        ] : []);
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
     * Scrive la persona e il permalink della sua carta. Pubblica perche' la
     * usa anche bin/importa-archivio.php: le carte dell'archivio nascono
     * esattamente come quelle dei visitatori.
     *
     * @param array<string,mixed> $d
     * @param array{anno:int,mese:int,giorno:int,ora_ut:float} $componenti
     * @param array<string,mixed> $tema
     */
    public function archivia(array $d, array $componenti, int $offset, string $precisione, array $tema): string
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

            // Ogni persona riceve un permalink SUO, anche quando il calcolo e'
            // identico a quello di qualcun altro.
            //
            // Prima non era cosi': la cache e' per impronta, e se due persone
            // inserivano gli stessi dati di nascita la seconda veniva mandata al
            // permalink della prima — e ne vedeva il NOME, perche' la pagina
            // della carta mostra il soggetto legato a quel calcolo. Valeva anche
            // all'indietro: con due soggetti sullo stesso calcolo, la carta
            // della prima persona poteva cominciare a mostrare il nome della
            // seconda. Il calcolo si puo' condividere; l'identita' no.
            //
            // Quindi: una riga di sola cache (senza gettone) si promuove a
            // permalink, come prima; una riga che ha GIA' un gettone appartiene a
            // qualcuno, e per la nuova persona si scrive una riga nuova. La sua
            // impronta viene derivata da quella vera e dal gettone, cosi' resta
            // unica e il motore non la scambia per la propria cache.
            $gettone = bin2hex(random_bytes(16));
            $esistente = Database::riga('SELECT id, gettone FROM calcoli WHERE impronta = ? LIMIT 1', [$impronta]);

            $promossa = $esistente !== null && $esistente['gettone'] === null
                // `AND gettone IS NULL`: se due richieste promuovono la stessa
                // riga nello stesso istante, una sola vince e l'altra lo sa.
                && Database::esegui(
                    'UPDATE calcoli SET gettone = ? WHERE id = ? AND gettone IS NULL',
                    [$gettone, (int) $esistente['id']],
                )->rowCount() === 1;

            if ($promossa) {
                $calcoloId = (int) $esistente['id'];
            } else {
                Database::esegui(
                    'INSERT INTO calcoli (gettone, impronta, tipo, esito, richieste, durata_ms, creato, ultima_richiesta)
                     VALUES (?,?,?,?,1,?,NOW(),NOW())',
                    [
                        $gettone,
                        $esistente === null ? $impronta : hash('sha256', $impronta . ':' . $gettone),
                        'natale',
                        (string) json_encode($tema, JSON_UNESCAPED_UNICODE),
                        (int) round((float) ($tema['meta']['durata_ms'] ?? 0)),
                    ],
                );
                $calcoloId = Database::ultimoId();
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
