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
 * La coda di moderazione.
 *
 * Ogni azione lascia traccia nel registro dell'admin: e' la ragione per cui
 * questo portale ha credenziali proprie invece di un file di password
 * condiviso. Chi ha rifiutato un messaggio, e quando, si deve poter sapere.
 */
final class GuestbookController
{
    private const PER_PAGINA = 30;

    /** GET /admin/guestbook */
    public function elenco(Request $r): Response
    {
        $stato = in_array($r->query('stato'), ['coda', 'approvato', 'rifiutato', 'cestino'], true)
            ? (string) $r->query('stato') : 'coda';
        $pagina = max(1, (int) ($r->query('p') ?? '1'));

        return Response::html(Vista::pagina('admin/guestbook', [
            'titolo'   => 'Guestbook',
            'sezione'  => 'admin',
            'stato'    => $stato,
            'pagina'   => $pagina,
            'per'      => self::PER_PAGINA,
            'totale'   => (int) Database::valore('SELECT COUNT(*) FROM guestbook WHERE stato = ?', [$stato]),
            'conteggi' => $this->conteggi(),
            'messaggi' => Database::righe(
                'SELECT g.*, c.gettone
                   FROM guestbook g
                   LEFT JOIN calcoli c ON c.id = g.calcolo_id
                  WHERE g.stato = ?
                  ORDER BY g.creato DESC
                  LIMIT ' . self::PER_PAGINA . ' OFFSET ' . (($pagina - 1) * self::PER_PAGINA),
                [$stato],
            ),
        ]));
    }

    /** POST /admin/guestbook/{id} */
    public function azione(Request $r, array $argomenti): Response
    {
        $id = (int) ($argomenti['id'] ?? 0);
        $torna = url('/admin/guestbook?stato=' . (string) ($r->post('stato_attuale') ?? 'coda'));

        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta.');

            return Response::redirect($torna);
        }

        $m = Database::riga('SELECT id, ip, nome, stato FROM guestbook WHERE id = ? LIMIT 1', [$id]);
        if ($m === null) {
            return Response::redirect($torna);
        }

        $azione = (string) ($r->post('azione') ?? '');

        switch ($azione) {
            case 'approva':
            case 'rifiuta':
            case 'cestina':
                $nuovo = ['approva' => 'approvato', 'rifiuta' => 'rifiutato', 'cestina' => 'cestino'][$azione];
                Database::esegui(
                    'UPDATE guestbook SET stato = ?, moderato_il = NOW(), moderato_da = ?, nota_admin = ?
                      WHERE id = ?',
                    [$nuovo, Auth::nome(), mb_substr((string) $r->post('nota', ''), 0, 500), $id],
                );
                Auth::traccia('guestbook:' . $azione, '#' . $id, (string) $m['nome']);
                Session::lampo('bene', 'Messaggio ' . $nuovo . '.');
                break;

            case 'modifica':
                $testo = trim((string) $r->post('messaggio', ''));
                if ($testo === '') {
                    Session::lampo('male', 'Il messaggio non puo\' restare vuoto.');
                    break;
                }
                Database::esegui(
                    'UPDATE guestbook SET messaggio = ?, moderato_il = NOW(), moderato_da = ?, nota_admin = ?
                      WHERE id = ?',
                    [$testo, Auth::nome(), mb_substr((string) $r->post('nota', ''), 0, 500), $id],
                );
                Auth::traccia('guestbook:modifica', '#' . $id, (string) $m['nome']);
                Session::lampo('bene', 'Testo corretto.');
                break;

            case 'rispondi':
                $corpo = trim((string) $r->post('risposta', ''));
                if ($corpo === '') {
                    break;
                }
                Database::esegui(
                    'INSERT INTO guestbook_risposte (messaggio_id, corpo, autore, creato) VALUES (?,?,?,NOW())',
                    [$id, mb_substr($corpo, 0, 4000), Auth::nome()],
                );
                Auth::traccia('guestbook:risposta', '#' . $id);
                Session::lampo('bene', 'Risposta pubblicata.');
                break;

            case 'blocca':
                // Si blocca l'indirizzo singolo, non la rete: dietro un solo
                // indirizzo pubblico ci puo' stare un intero condominio o un
                // ateneo, e bloccare un /24 per un messaggio e' sproporzionato.
                // La rete si blocca a mano, dalla pagina dei blocchi.
                $ip = (string) $m['ip'];
                if ($ip !== '') {
                    Database::esegui(
                        'INSERT IGNORE INTO blocchi (cidr, motivo, creato, creato_da) VALUES (?,?,NOW(),?)',
                        [$ip, 'guestbook #' . $id, Auth::nome()],
                    );
                    Database::esegui(
                        'UPDATE guestbook SET stato = ?, moderato_il = NOW(), moderato_da = ? WHERE id = ?',
                        ['rifiutato', Auth::nome(), $id],
                    );
                    Auth::traccia('blocco:aggiunto', $ip, 'da guestbook #' . $id);
                    Session::lampo('bene', 'Indirizzo ' . $ip . ' bloccato e messaggio rifiutato.');
                }
                break;

            default:
                Session::lampo('male', 'Azione sconosciuta.');
        }

        return Response::redirect($torna);
    }

    /** GET /admin/blocchi */
    public function blocchi(Request $r): Response
    {
        return Response::html(Vista::pagina('admin/blocchi', [
            'titolo'  => 'Blocchi',
            'sezione' => 'admin',
            'blocchi' => Database::righe(
                'SELECT id, cidr, motivo, creato, scade, creato_da FROM blocchi ORDER BY creato DESC'
            ),
        ]));
    }

    /** POST /admin/blocchi */
    public function salvaBlocco(Request $r): Response
    {
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta.');

            return Response::redirect(url('/admin/blocchi'));
        }

        if ((string) $r->post('azione') === 'togli') {
            $id = (int) ($r->post('id') ?? 0);
            $cidr = Database::valore('SELECT cidr FROM blocchi WHERE id = ?', [$id]);
            Database::esegui('DELETE FROM blocchi WHERE id = ?', [$id]);
            Auth::traccia('blocco:tolto', (string) $cidr);
            Session::lampo('bene', 'Blocco rimosso.');

            return Response::redirect(url('/admin/blocchi'));
        }

        $cidr = trim((string) $r->post('cidr', ''));
        if ($cidr === '' || preg_match('#^[0-9a-fA-F:.]+(/\d{1,3})?$#', $cidr) !== 1) {
            Session::lampo('male', 'Indirizzo o rete non validi. Esempi: 203.0.113.9 oppure 203.0.113.0/24.');

            return Response::redirect(url('/admin/blocchi'));
        }

        Database::esegui(
            'INSERT IGNORE INTO blocchi (cidr, motivo, creato, creato_da) VALUES (?,?,NOW(),?)',
            [$cidr, mb_substr((string) $r->post('motivo', ''), 0, 255), Auth::nome()],
        );
        Auth::traccia('blocco:aggiunto', $cidr);
        Session::lampo('bene', 'Blocco aggiunto.');

        return Response::redirect(url('/admin/blocchi'));
    }

    /** @return array<string,int> */
    private function conteggi(): array
    {
        $c = ['coda' => 0, 'approvato' => 0, 'rifiutato' => 0, 'cestino' => 0];
        foreach (Database::righe('SELECT stato, COUNT(*) AS n FROM guestbook GROUP BY stato') as $r) {
            $c[(string) $r['stato']] = (int) $r['n'];
        }

        return $c;
    }
}
