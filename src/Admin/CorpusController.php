<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Vista;

/**
 * La regia del corpus.
 *
 * I testi stanno in tabella apposta: si correggono da qui, senza toccare un
 * file e senza un rilascio.
 *
 * La parte che conta davvero, pero', non e' l'editor: e' il quadro di quello
 * che MANCA. Scrivere a mano millecinquecento schede per registro e' il lavoro
 * di anni, e farlo in ordine alfabetico sarebbe uno spreco. Qui si vede quali
 * voci il portale ha davvero composto, quante volte, e quindi che cosa
 * conviene scrivere per primo.
 */
final class CorpusController
{
    private const PER_PAGINA = 40;

    /** GET /admin/corpus */
    public function elenco(Request $r): Response
    {
        $filtri = [
            'ambito'   => (string) ($r->query('ambito') ?? ''),
            'registro' => in_array($r->query('registro'), ['tradizionale', 'moderno'], true)
                ? (string) $r->query('registro') : '',
            'cerca'    => mb_substr(trim((string) ($r->query('cerca') ?? '')), 0, 80),
        ];
        $pagina = max(1, (int) ($r->query('p') ?? '1'));

        // Le colonne si qualificano sempre con «t.»: l'elenco unisce `testi` a
        // `testi_uso`, che hanno entrambe `ambito` e `chiave`, e senza prefisso
        // il database non sa quale intendiamo. Anche il conteggio usa lo stesso
        // alias, cosi' la condizione e' una sola e non puo' divergere.
        $dove = ['1=1'];
        $par  = [];

        if ($filtri['ambito'] !== '')   { $dove[] = 't.ambito = ?';   $par[] = $filtri['ambito']; }
        if ($filtri['registro'] !== '') { $dove[] = 't.registro = ?'; $par[] = $filtri['registro']; }
        if ($filtri['cerca'] !== '') {
            $dove[] = '(t.chiave LIKE ? OR t.titolo LIKE ? OR t.corpo LIKE ?)';
            $like = '%' . $filtri['cerca'] . '%';
            array_push($par, $like, $like, $like);
        }
        $sqlDove = implode(' AND ', $dove);

        $totale = (int) Database::valore("SELECT COUNT(*) FROM testi t WHERE {$sqlDove}", $par);

        return Response::html(Vista::pagina('admin/corpus', [
            'titolo'   => 'Corpus',
            'sezione'  => 'admin',
            'filtri'   => $filtri,
            'pagina'   => $pagina,
            'per'      => self::PER_PAGINA,
            'totale'   => $totale,
            'ambiti'   => Database::righe('SELECT ambito, COUNT(*) AS n FROM testi GROUP BY ambito ORDER BY ambito'),
            'testi'    => Database::righe(
                "SELECT t.id, t.ambito, t.chiave, t.registro, t.titolo, t.corpo, t.peso, t.stato, t.aggiornato,
                        COALESCE(u.usi, 0) AS usi
                   FROM testi t
                   LEFT JOIN testi_uso u ON u.ambito = t.ambito AND u.chiave = t.chiave
                  WHERE {$sqlDove}
                  ORDER BY t.ambito, t.chiave, t.registro
                  LIMIT " . self::PER_PAGINA . ' OFFSET ' . (($pagina - 1) * self::PER_PAGINA),
                $par,
            ),
        ]));
    }

    /** GET /admin/corpus/copertura */
    public function copertura(Request $r): Response
    {
        return Response::html(Vista::pagina('admin/corpus-copertura', [
            'titolo'   => 'Copertura del corpus',
            'sezione'  => 'admin',
            'perRegistro' => Database::righe(
                'SELECT registro, ambito, COUNT(*) AS n FROM testi GROUP BY registro, ambito ORDER BY ambito, registro'
            ),
            // Che cosa il portale ha composto piu' spesso, e che quindi conviene
            // scrivere per primo. E' l'unico criterio sensato per decidere
            // l'ordine di un lavoro lungo anni.
            'daScrivere' => Database::righe(
                'SELECT u.ambito, u.chiave, u.usi, u.ultimo,
                        SUM(t.registro = \'tradizionale\') AS ha_trad,
                        SUM(t.registro = \'moderno\') AS ha_mod
                   FROM testi_uso u
                   LEFT JOIN testi t ON t.ambito = u.ambito AND t.chiave = u.chiave
                  WHERE u.ambito IN (\'pianeta_segno\',\'pianeta_casa\',\'aspetto\')
                  GROUP BY u.ambito, u.chiave, u.usi, u.ultimo
                 HAVING ha_trad = 0 OR ha_mod = 0 OR ha_trad IS NULL
                  ORDER BY u.usi DESC
                  LIMIT 40'
            ),
            'usate' => (int) Database::valore('SELECT COUNT(*) FROM testi_uso'),
            'letture' => (int) Database::valore('SELECT COALESCE(SUM(usi),0) FROM testi_uso'),
        ]));
    }

    /** GET /admin/corpus/{id} */
    public function modulo(Request $r, array $argomenti): Response
    {
        $testo = Database::riga('SELECT * FROM testi WHERE id = ? LIMIT 1', [(int) ($argomenti['id'] ?? 0)]);

        if ($testo === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Voce non trovata', 'stato' => 404,
                'messaggio' => 'Non c\'e\' nessuna voce con questo numero.',
            ]), 404);
        }

        return Response::html(Vista::pagina('admin/corpus-voce', [
            'titolo'  => 'Voce: ' . $testo['ambito'] . '/' . $testo['chiave'],
            'sezione' => 'admin',
            'testo'   => $testo,
            'usi'     => (int) Database::valore(
                'SELECT COALESCE(usi,0) FROM testi_uso WHERE ambito = ? AND chiave = ?',
                [$testo['ambito'], $testo['chiave']],
            ),
        ]));
    }

    /** POST /admin/corpus/{id} */
    public function salva(Request $r, array $argomenti): Response
    {
        $id = (int) ($argomenti['id'] ?? 0);

        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta. Riprova.');

            return Response::redirect(url('/admin/corpus/' . $id));
        }

        $testo = Database::riga('SELECT ambito, chiave, registro, corpo FROM testi WHERE id = ? LIMIT 1', [$id]);
        if ($testo === null) {
            return Response::redirect(url('/admin/corpus'));
        }

        $corpo = trim((string) $r->post('corpo', ''));
        if ($corpo === '') {
            Session::lampo('male', 'Il corpo non puo\' restare vuoto.');

            return Response::redirect(url('/admin/corpus/' . $id));
        }

        // I frammenti degli aspetti e le dignita' hanno un %s dove va il nome
        // del corpo: toglierlo per sbaglio produrrebbe frasi monche in ogni
        // carta, e nessuno se ne accorgerebbe subito.
        $servePosto = in_array($testo['ambito'], ['aspetto_relazione', 'dignita'], true)
            && str_contains((string) $testo['corpo'], '%s');
        if ($servePosto && !str_contains($corpo, '%s')) {
            Session::lampo('male', 'In questo ambito il testo deve contenere %s, dove va il nome del corpo celeste.');

            return Response::redirect(url('/admin/corpus/' . $id));
        }

        Database::esegui(
            'UPDATE testi SET titolo = ?, corpo = ?, peso = ?, etichette = ?, stato = ?, aggiornato = NOW()
              WHERE id = ?',
            [
                mb_substr(trim((string) $r->post('titolo', '')), 0, 160),
                $corpo,
                max(0, min(10, (int) ($r->post('peso') ?? 5))),
                mb_substr(trim((string) $r->post('etichette', '')), 0, 190),
                in_array($r->post('stato'), ['bozza', 'pubblicato'], true) ? (string) $r->post('stato') : 'pubblicato',
                $id,
            ],
        );

        Auth::traccia('corpus:modifica', $testo['ambito'] . '/' . $testo['chiave'] . ' [' . $testo['registro'] . ']');
        Session::lampo('bene', 'Voce salvata.');

        return Response::redirect(url('/admin/corpus/' . $id));
    }
}
