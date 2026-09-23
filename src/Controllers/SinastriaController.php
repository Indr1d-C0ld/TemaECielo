<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Astro\Corpi;
use App\Astro\Motore;
use App\Astro\Sinastria;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Vista;
use App\Corpus\Corpus;
use App\Luogo\Tempo;
use App\Support\Telemetria;

/**
 * Sinastria e transiti: le due cose che danno un motivo per tornare.
 *
 * La sinastria esiste in due forme, e sono due funzioni diverse:
 *
 *   RAPIDA     due segni e basta, nessuna data. E' quella che si condivide,
 *              e non pretende di essere un'analisi.
 *   COMPLETA   carta contro carta: aspetti incrociati, sovrapposizione delle
 *              case, composita, punteggi per area.
 */
final class SinastriaController
{
    /** GET /sinastria */
    public function rapida(Request $r): Response
    {
        $a = $this->segnoDa($r->query('a'));
        $b = $this->segnoDa($r->query('b'));

        $registro = (string) Session::get('__registro', 'moderno');
        $esito = null;

        if ($a !== null && $b !== null) {
            $esito = (new Corpus($registro))->segnoConSegno($a, $b);
            Telemetria::evento('sinastria_rapida', Corpi::segni()[$a]['nome'] . '+' . Corpi::segni()[$b]['nome']);
        }

        return Response::html(Vista::pagina('sinastria', [
            'titolo'   => 'Sinastria',
            'sezione'  => 'sinastria',
            'a'        => $a,
            'b'        => $b,
            'esito'    => $esito,
            'registro' => $registro,
        ]));
    }

    /** GET /carta/{gettone}/sinastria — il modulo per la seconda persona */
    public function modulo(Request $r, array $argomenti): Response
    {
        $primo = $this->carta((string) ($argomenti['gettone'] ?? ''));
        if ($primo === null) {
            return $this->nonTrovata();
        }

        return Response::html(Vista::pagina('sinastria-modulo', [
            'titolo'   => 'Sinastria con ' . ($primo['nome'] !== '' ? $primo['nome'] : 'questa carta'),
            'sezione'  => 'sinastria',
            'mappa'    => true,
            'primo'    => $primo,
            'gettone'  => $primo['gettone'],
            'dati'     => Session::get('__modulo2', []),
            'errori'   => Session::get('__errori2', []),
            'sistemi'  => Corpi::sistemiCase(),
        ]));
    }

    /** POST /carta/{gettone}/sinastria */
    public function calcola(Request $r, array $argomenti): Response
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? ''));
        $primo = $this->carta($gettone);

        if ($primo === null) {
            return $this->nonTrovata();
        }
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta. Riprova.');

            return Response::redirect(url('/carta/' . $gettone . '/sinastria'));
        }

        // La seconda persona passa dalla stessa validazione della prima: non
        // esiste una versione «facile» dei dati di nascita.
        $dati = (new CalcolaController())->leggiPubblico($r);
        $errori = (new CalcolaController())->validaPubblico($dati);

        if ($errori !== []) {
            Session::set('__modulo2', $dati);
            Session::set('__errori2', $errori);

            return Response::redirect(url('/carta/' . $gettone . '/sinastria'));
        }

        $ora = $dati['precisione'] === 'ignota' ? '12:00' : $dati['ora'];
        $tempo = Tempo::risolviNelLuogo($dati['data'], $ora, $dati['fuso'], (float) $dati['lon']);

        if (($tempo['ok'] ?? false) !== true) {
            Session::set('__modulo2', $dati);
            Session::set('__errori2', ['ora' => $tempo['avviso'] ?? 'Ora non valida.']);

            return Response::redirect(url('/carta/' . $gettone . '/sinastria'));
        }

        $c = $tempo['componenti_ut'];

        // Un'ora ambigua — la notte in cui finisce l'ora legale, quando un'ora si
        // ripete — qui non si chiede come fa il modulo di nascita: questa
        // pagina non si conserva, e rifare il confronto costa un clic. Ma la
        // scelta si dichiara, invece di farla di nascosto.
        $avvisoOra = ($tempo['stato'] ?? '') === Tempo::AMBIGUO
            ? sprintf(
                'Quella notte le %s sono esistite due volte, alla fine dell\'ora legale: il confronto usa la prima (%s). '
                . 'Se la nascita e\' avvenuta dopo il cambio, il risultato si sposta di un\'ora.',
                substr((string) $tempo['ora_locale'], 0, 5),
                (string) ($tempo['abbreviazione'] ?? ''),
            )
            : null;

        try {
            $secondo = (new Motore())->tema([
                'anno' => $c['anno'], 'mese' => $c['mese'], 'giorno' => $c['giorno'],
                'ora_ut' => $c['ora_ut'], 'lat' => $dati['lat'], 'lon' => $dati['lon'],
                'alt' => $dati['altitudine'],
                'sistema_case' => $dati['precisione'] === 'ignota' ? 'segni_interi' : $dati['sistema'],
                'ora_ignota' => $dati['precisione'] === 'ignota',
                'offset_secondi' => (int) ($tempo['offset_secondi'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            registro('sinastria: calcolo fallito — ' . $e->getMessage(), 'error');
            Session::set('__errori2', ['motore' => 'Il motore non ha risposto. Riprova fra poco.']);

            return Response::redirect(url('/carta/' . $gettone . '/sinastria'));
        }

        Session::togli('__modulo2');
        Session::togli('__errori2');
        Telemetria::evento('sinastria_completa', $primo['nome'] . '+' . $dati['nome']);

        $davison = $this->davison($primo['tema'], $secondo);

        return Response::html(Vista::pagina('sinastria-esito', [
            'titolo'    => 'Sinastria',
            'sezione'   => 'sinastria',
            'primo'     => $primo,
            'temaA'     => $primo['tema'],
            'temaB'     => $secondo,
            'nomeB'     => $dati['nome'] !== '' ? $dati['nome'] : 'la seconda carta',
            'avvisoOra' => $avvisoOra,
            'luogoB'    => $dati['luogo_nome'],
            'dataB'     => $dati['data'],
            'sinastria' => Sinastria::fra(
                $primo['tema'], $secondo,
                $primo['nome'] !== '' ? $primo['nome'] : 'Primo',
                $dati['nome'] !== '' ? $dati['nome'] : 'Secondo',
            ),
            'gettone'   => $gettone,
            'davison'   => $davison,
        ]))->conIntestazione('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    /**
     * La carta di Davison: il cielo del punto medio nel tempo fra le due
     * nascite, visto dal punto medio nello spazio fra i due luoghi.
     *
     * Era annunciata in home e nel documento di progetto, e il punto medio
     * geografico era gia' scritto — `Sinastria::luogoMedio`, sulla sfera e non
     * sulla carta piana — ma nessuno lo chiamava: la carta non esisteva.
     *
     * A differenza della composita non e' una costruzione: e' un cielo che c'e'
     * stato davvero, in un istante e in un luogo precisi, e si calcola come
     * qualunque altra carta. Se l'ora di una delle due nascite non si sa,
     * l'istante medio e' incerto di sei ore: la carta diventa solare, come le
     * carte a ora ignota.
     *
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     * @return array<string,mixed>|null
     */
    private function davison(array $a, array $b): ?array
    {
        try {
            $jd = ((float) $a['tempo']['jd_ut'] + (float) $b['tempo']['jd_ut']) / 2.0;
            [$lat, $lon] = Sinastria::luogoMedio(
                (float) $a['luogo']['lat'], (float) $a['luogo']['lon'],
                (float) $b['luogo']['lat'], (float) $b['luogo']['lon'],
            );
            $ignota = (bool) ($a['carta']['ora_ignota'] ?? false) || (bool) ($b['carta']['ora_ignota'] ?? false);

            $istante = (int) round(($jd - 2440587.5) * 86400.0);
            $c = Tempo::componenti($istante);

            $tema = (new Motore())->tema([
                'anno' => $c['anno'], 'mese' => $c['mese'], 'giorno' => $c['giorno'], 'ora_ut' => $c['ora_ut'],
                'lat' => round($lat, 6), 'lon' => round($lon, 6), 'alt' => 0,
                'sistema_case' => $ignota ? 'segni_interi' : 'placido',
                'ora_ignota' => $ignota,
            ]);

            $vicino = \App\Luogo\Gazetteer::piuVicino($lat, $lon);

            return [
                'tema'    => $tema,
                'utc'     => gmdate('Y-m-d H:i', $istante),
                'lat'     => $lat,
                'lon'     => $lon,
                'luogo'   => $vicino !== null
                    ? (string) $vicino['nome'] . (($vicino['contesto'] ?? '') !== '' ? ', ' . $vicino['contesto'] : '')
                    : null,
                'ignota'  => $ignota,
            ];
        } catch (\App\Support\TroppeRichieste $e) {
            throw $e;
        } catch (\Throwable $e) {
            registro('davison: ' . $e->getMessage(), 'warn');
            return null;   // la sinastria resta valida anche senza
        }
    }

    /**
     * GET /carta/{gettone}/transiti
     *
     * Il cielo di oggi — o di una data scelta — sopra la carta natale.
     */
    public function transiti(Request $r, array $argomenti): Response
    {
        $primo = $this->carta((string) ($argomenti['gettone'] ?? ''));
        if ($primo === null) {
            return $this->nonTrovata();
        }

        // Una data vera, e dentro l'arco delle effemeridi. Prima bastava la forma
        // AAAA-MM-GG: il 30 febbraio passava, e la pagina lo mostrava come
        // «1/1/1970»; il 9999 faceva lavorare il motore fuori dai suoi file.
        $quando = (string) ($r->query('data') ?? '');
        $pezzi  = array_map('intval', explode('-', $quando));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $quando) !== 1
            || !checkdate($pezzi[1], $pezzi[2], $pezzi[0])
            || $pezzi[0] < 1800 || $pezzi[0] > 2399) {
            $quando = date('Y-m-d');
        }

        [$anno, $mese, $giorno] = array_map('intval', explode('-', $quando));

        // I transiti si calcolano sul luogo di NASCITA: cio' che conta e' dove
        // cadono nelle case natali, e quelle sono ancorate li'.
        $cielo = (new Motore())->tema([
            // Mezzogiorno LOCALE del luogo di nascita, non di Greenwich: per chi
            // e' nato a Tokyo il mezzogiorno UT e' gia' sera, e a Los Angeles e'
            // l'alba. La longitudine basta: dodici ore meno un'ora ogni 15 gradi.
            'anno' => $anno, 'mese' => $mese, 'giorno' => $giorno,
            'ora_ut' => 12.0 - (float) $primo['lon'] / 15.0,
            'lat' => (float) $primo['lat'], 'lon' => (float) $primo['lon'],
            'alt' => (int) $primo['altitudine'], 'sistema_case' => 'placido',
        ]);

        Telemetria::evento('transiti', $quando);

        // Gli assi del cielo del giorno non entrano: percorrono tutto lo
        // zodiaco in ventiquattro ore e come transiti non dicono niente.
        // Quelli NATALI invece restano: «Saturno transita sul tuo Ascendente»
        // e' uno dei transiti piu' significativi che esistano.
        $incrociati = Sinastria::aspettiIncrociati($primo['tema'], $cielo, true, false);

        return Response::html(Vista::pagina('transiti', [
            'titolo'    => 'Transiti',
            'sezione'   => 'carta',
            'primo'     => $primo,
            'temaA'     => $primo['tema'],
            'cielo'     => $cielo,
            'quando'    => $quando,
            'aspetti'   => $incrociati,
            'inCase'    => Sinastria::sovrapposizione($cielo, $primo['tema']),
            'gettone'   => $primo['gettone'],
        ]))->conIntestazione('X-Robots-Tag', 'noindex, nofollow');
    }

    // ------------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function carta(string $gettone): ?array
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', $gettone);

        $riga = Database::riga(
            'SELECT c.esito, s.nome, s.data_nascita, s.ora_nascita, s.precisione_ora,
                    s.luogo_nome, s.lat, s.lon, s.altitudine, s.fuso
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

    private function segnoDa(?string $v): ?int
    {
        if ($v === null || !is_numeric($v)) {
            return null;
        }
        $i = (int) $v;

        return ($i >= 0 && $i <= 11) ? $i : null;
    }

    private function nonTrovata(): Response
    {
        return Response::html(Vista::pagina('errors/generico', [
            'titolo'    => 'Carta non trovata',
            'stato'     => 404,
            'messaggio' => 'Questo indirizzo non corrisponde a nessuna carta.',
        ]), 404);
    }
}
