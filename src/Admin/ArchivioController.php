<?php

declare(strict_types=1);

namespace App\Admin;

use App\Archivio\Archivio;
use App\Auth\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Vista;

/**
 * La regia delle carte: tutte quelle salvate, e le schede dell'archivio.
 *
 * Le carte dei visitatori erano gia' nel database di chi gestisce il portale;
 * qui diventano consultabili, e l'informativa lo dice. Da qui una carta
 * qualunque — tipicamente una che la regia stessa ha calcolato per studio —
 * puo' ricevere una scheda e finire nell'archivio pubblico.
 */
final class ArchivioController
{
    private const PER_PAGINA = 50;

    /** GET /admin/carte */
    public function elenco(Request $r): Response
    {
        $pagina = min(1_000_000, max(1, (int) ($r->query('p') ?? '1')));
        $q      = trim(mb_substr((string) ($r->query('q') ?? ''), 0, 80));
        $quali  = in_array($r->query('quali'), ['visitatori', 'archivio'], true) ? (string) $r->query('quali') : 'tutte';

        $dove = ['c.gettone IS NOT NULL'];
        $par  = [];
        if ($q !== '') {
            $dove[] = '(s.nome LIKE ? OR s.luogo_nome LIKE ? OR a.nome LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($par, $like, $like, $like);
        }
        if ($quali === 'visitatori') { $dove[] = 'a.id IS NULL'; }
        if ($quali === 'archivio')   { $dove[] = 'a.id IS NOT NULL'; }

        $da = 'FROM calcoli c
               JOIN calcoli_soggetti cs ON cs.calcolo_id = c.id AND cs.ruolo = \'primo\'
               JOIN soggetti s ON s.id = cs.soggetto_id
               LEFT JOIN archivio a ON a.calcolo_id = c.id
              WHERE ' . implode(' AND ', $dove);

        return Response::html(Vista::pagina('admin/carte', [
            'titolo'  => 'Carte salvate',
            'sezione' => 'admin',
            'q'       => $q,
            'quali'   => $quali,
            'pagina'  => $pagina,
            'per'     => self::PER_PAGINA,
            'totale'  => (int) Database::valore('SELECT COUNT(*) ' . $da, $par),
            'righe'   => Database::righe(
                'SELECT c.gettone, c.creato, c.richieste, s.nome, s.data_nascita, s.ora_nascita,
                        s.precisione_ora, s.luogo_nome, a.slug, a.tipo, a.pubblicata, a.rodden
                 ' . $da . ' ORDER BY c.creato DESC
                 LIMIT ' . self::PER_PAGINA . ' OFFSET ' . (($pagina - 1) * self::PER_PAGINA),
                $par,
            ),
            'numeri'  => [
                'carte'      => (int) Database::valore('SELECT COUNT(*) FROM calcoli WHERE gettone IS NOT NULL'),
                'archivio'   => (int) Database::valore('SELECT COUNT(*) FROM archivio'),
                'pubblicate' => (int) Database::valore('SELECT COUNT(*) FROM archivio WHERE pubblicata = 1'),
            ],
        ]));
    }

    /** GET /admin/carte/{gettone} — la scheda d'archivio di una carta */
    public function scheda(Request $r, array $argomenti): Response
    {
        $carta = $this->carta((string) ($argomenti['gettone'] ?? ''));
        if ($carta === null) {
            return $this->nonTrovata();
        }

        $scheda = Archivio::perCalcolo((int) $carta['id']);
        // I dati di un salvataggio rifiutato valgono una volta sola.
        $rifiutata = Session::get('__scheda');
        $errore = Session::get('__scheda_errore');
        Session::togli('__scheda');
        Session::togli('__scheda_errore');

        return Response::html(Vista::pagina('admin/scheda', [
            'titolo'  => 'Scheda d\'archivio',
            'sezione' => 'admin',
            'carta'   => $carta,
            'scheda'  => $rifiutata ?? $scheda ?? [
                'tipo' => 'persona', 'nome' => (string) $carta['nome'], 'categoria' => '',
                'nota' => '', 'fonte' => '', 'url_fonte' => '', 'rodden' => 'C', 'pubblicata' => 1,
            ],
            'esiste'  => $scheda !== null,
            'errore'  => $errore,
        ]))->conIntestazione('X-Robots-Tag', 'noindex');
    }

    /** POST /admin/carte/{gettone} */
    public function salva(Request $r, array $argomenti): Response
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', (string) ($argomenti['gettone'] ?? '')) ?? '';
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione è scaduta. Riprova.');
            return Response::redirect(url('/admin/carte/' . $gettone));
        }
        $carta = $this->carta($gettone);
        if ($carta === null) {
            return $this->nonTrovata();
        }
        Session::togli('__scheda');
        Session::togli('__scheda_errore');

        if ($r->post('azione') === 'ritira') {
            Archivio::ritira((int) $carta['id']);
            Auth::traccia('archivio:ritirata', $gettone);
            Session::lampo('bene', 'La scheda è stata tolta dall\'archivio. La carta resta.');
            return Response::redirect(url('/admin/carte'));
        }

        $dati = [
            'tipo' => $r->post('tipo'), 'nome' => $r->post('nome'), 'categoria' => $r->post('categoria'),
            'nota' => $r->post('nota'), 'fonte' => $r->post('fonte'), 'url_fonte' => $r->post('url_fonte'),
            'rodden' => $r->post('rodden'), 'pubblicata' => $r->post('pubblicata') === '1',
        ];
        try {
            $slug = Archivio::salva((int) $carta['id'], $dati);
        } catch (\InvalidArgumentException $e) {
            Session::set('__scheda', $dati);
            Session::set('__scheda_errore', $e->getMessage());
            return Response::redirect(url('/admin/carte/' . $gettone));
        }

        Auth::traccia('archivio:scheda', $slug, (string) $dati['nome']);
        Session::lampo('bene', 'Scheda salvata' . ($dati['pubblicata'] ? ': è nell\'archivio pubblico.' : ', non ancora pubblicata.'));

        return Response::redirect(url('/admin/carte/' . $gettone));
    }

    /** @return array<string,mixed>|null */
    private function carta(string $gettone): ?array
    {
        $gettone = preg_replace('/[^a-f0-9]/', '', $gettone) ?? '';

        return Database::riga(
            'SELECT c.id, c.gettone, c.creato, s.nome, s.data_nascita, s.ora_nascita, s.precisione_ora,
                    s.luogo_nome, s.fuso
               FROM calcoli c
               JOIN calcoli_soggetti cs ON cs.calcolo_id = c.id AND cs.ruolo = \'primo\'
               JOIN soggetti s ON s.id = cs.soggetto_id
              WHERE c.gettone = ?
              ORDER BY cs.soggetto_id
              LIMIT 1',
            [$gettone],
        );
    }

    private function nonTrovata(): Response
    {
        return Response::html(Vista::pagina('errors/generico', [
            'titolo' => 'Carta non trovata', 'stato' => 404,
            'messaggio' => 'Nessuna carta con questo indirizzo.',
        ]), 404);
    }
}
