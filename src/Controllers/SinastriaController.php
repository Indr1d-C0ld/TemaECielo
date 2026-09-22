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
        $tempo = Tempo::risolvi($dati['data'], $ora, $dati['fuso']);

        if (($tempo['ok'] ?? false) !== true) {
            Session::set('__modulo2', $dati);
            Session::set('__errori2', ['ora' => $tempo['avviso'] ?? 'Ora non valida.']);

            return Response::redirect(url('/carta/' . $gettone . '/sinastria'));
        }

        $c = $tempo['componenti_ut'];

        try {
            $secondo = (new Motore())->tema([
                'anno' => $c['anno'], 'mese' => $c['mese'], 'giorno' => $c['giorno'],
                'ora_ut' => $c['ora_ut'], 'lat' => $dati['lat'], 'lon' => $dati['lon'],
                'alt' => $dati['altitudine'],
                'sistema_case' => $dati['precisione'] === 'ignota' ? 'segni_interi' : $dati['sistema'],
                'ora_ignota' => $dati['precisione'] === 'ignota',
            ]);
        } catch (\Throwable $e) {
            registro('sinastria: calcolo fallito — ' . $e->getMessage(), 'error');
            Session::set('__errori2', ['motore' => 'Il motore non ha risposto. Riprova fra poco.']);

            return Response::redirect(url('/carta/' . $gettone . '/sinastria'));
        }

        Session::togli('__modulo2');
        Session::togli('__errori2');
        Telemetria::evento('sinastria_completa', $primo['nome'] . '+' . $dati['nome']);

        return Response::html(Vista::pagina('sinastria-esito', [
            'titolo'    => 'Sinastria',
            'sezione'   => 'sinastria',
            'primo'     => $primo,
            'temaA'     => $primo['tema'],
            'temaB'     => $secondo,
            'nomeB'     => $dati['nome'] !== '' ? $dati['nome'] : 'la seconda carta',
            'luogoB'    => $dati['luogo_nome'],
            'dataB'     => $dati['data'],
            'sinastria' => Sinastria::fra(
                $primo['tema'], $secondo,
                $primo['nome'] !== '' ? $primo['nome'] : 'Primo',
                $dati['nome'] !== '' ? $dati['nome'] : 'Secondo',
            ),
            'gettone'   => $gettone,
        ]))->conIntestazione('X-Robots-Tag', 'noindex, nofollow, noarchive');
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

        $quando = (string) ($r->query('data') ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $quando) !== 1) {
            $quando = date('Y-m-d');
        }

        [$anno, $mese, $giorno] = array_map('intval', explode('-', $quando));

        // I transiti si calcolano sul luogo di NASCITA: cio' che conta e' dove
        // cadono nelle case natali, e quelle sono ancorate li'.
        $cielo = (new Motore())->tema([
            'anno' => $anno, 'mese' => $mese, 'giorno' => $giorno, 'ora_ut' => 12.0,
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
