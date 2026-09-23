<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Vista;
use App\Support\Impostazioni;
use App\Support\Markdown;

final class PannelloController
{
    public function cruscotto(Request $r): Response
    {
        return Response::html(Vista::pagina('admin/cruscotto', [
            'titolo'  => 'Regia',
            'sezione' => 'admin',
            'numeri'  => $this->numeri(),
            'ultimi'  => Database::righe(
                'SELECT quando, ip, percorso, stato, durata_ms, ua_famiglia, ua_so, dispositivo, bot
                   FROM accessi ORDER BY quando DESC LIMIT 25'
            ),
        ]));
    }

    public function accessi(Request $r): Response
    {
        $pagina = max(1, (int) ($r->query('p') ?? '1'));
        $per    = 100;

        return Response::html(Vista::pagina('admin/accessi', [
            'titolo'  => 'Registro accessi',
            'sezione' => 'admin',
            'pagina'  => $pagina,
            'totale'  => (int) Database::valore('SELECT COUNT(*) FROM accessi'),
            'per'     => $per,
            'righe'   => Database::righe(
                'SELECT quando, ip, paese, regione, citta, asn, operatore, metodo, percorso, stato,
                        byte_inviati, durata_ms, ua_famiglia, ua_so, dispositivo, bot, referente
                   FROM accessi ORDER BY quando DESC LIMIT ? OFFSET ?',
                [$per, ($pagina - 1) * $per],
            ),
            'paesi'   => Database::righe(
                'SELECT paese, COUNT(*) AS n FROM accessi WHERE paese IS NOT NULL
                  GROUP BY paese ORDER BY n DESC LIMIT 10'
            ),
            'reti'    => Database::righe(
                'SELECT operatore, asn, COUNT(*) AS n FROM accessi WHERE operatore IS NOT NULL
                  GROUP BY operatore, asn ORDER BY n DESC LIMIT 10'
            ),
        ]));
    }

    public function pagine(Request $r): Response
    {
        return Response::html(Vista::pagina('admin/pagine', [
            'titolo'  => 'Pagine',
            'sezione' => 'admin',
            'pagine'  => Database::righe('SELECT id, slug, titolo, stato, in_menu, ordine, aggiornata FROM pagine ORDER BY ordine, titolo'),
        ]));
    }

    /** GET /admin/pagine/{id} — 0 per una pagina nuova */
    public function pagina(Request $r, array $argomenti): Response
    {
        $id = (int) ($argomenti['id'] ?? 0);

        $p = $id > 0
            ? Database::riga('SELECT * FROM pagine WHERE id = ? LIMIT 1', [$id])
            : ['id' => 0, 'slug' => '', 'titolo' => '', 'sottotitolo' => '', 'corpo' => '',
               'stato' => 'bozza', 'in_menu' => 0, 'ordine' => 0, 'aggiornata' => date('Y-m-d H:i:s')];

        if ($p === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Pagina non trovata', 'stato' => 404,
                'messaggio' => 'Non c\'e\' nessuna pagina con questo numero.',
            ]), 404);
        }

        return Response::html(Vista::pagina('admin/pagina', [
            'titolo'    => $id > 0 ? 'Pagina: ' . $p['titolo'] : 'Pagina nuova',
            'sezione'   => 'admin',
            'p'         => $p,
            'anteprima' => $p['corpo'] !== '' ? Markdown::rendi((string) $p['corpo']) : '',
        ]));
    }

    /** POST /admin/pagine/{id} */
    public function salvaPagina(Request $r, array $argomenti): Response
    {
        $id = (int) ($argomenti['id'] ?? 0);

        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta.');

            return Response::redirect(url('/admin/pagine/' . $id));
        }

        if ((string) $r->post('azione') === 'elimina' && $id > 0) {
            $slug = Database::valore('SELECT slug FROM pagine WHERE id = ?', [$id]);
            Database::esegui('DELETE FROM pagine WHERE id = ?', [$id]);
            Auth::traccia('pagina:eliminata', (string) $slug);
            Session::lampo('bene', 'Pagina eliminata.');

            return Response::redirect(url('/admin/pagine'));
        }

        // Lo slug diventa l'indirizzo pubblico: si ripulisce a monte, cosi'
        // non puo' contenere niente che debba essere scappato dopo.
        $slug = preg_replace('/[^a-z0-9-]+/', '-', mb_strtolower(trim((string) $r->post('slug', '')), 'UTF-8'));
        $slug = trim((string) $slug, '-');

        $titolo = mb_substr(trim((string) $r->post('titolo', '')), 0, 160);
        $corpo  = trim((string) $r->post('corpo', ''));

        if ($slug === '' || $titolo === '') {
            Session::lampo('male', 'Servono almeno lo slug e il titolo.');

            return Response::redirect(url('/admin/pagine/' . $id));
        }

        $doppio = Database::valore('SELECT id FROM pagine WHERE slug = ? AND id <> ?', [$slug, $id]);
        if ($doppio !== null) {
            Session::lampo('male', 'Esiste gia\' una pagina con lo slug «' . $slug . '».');

            return Response::redirect(url('/admin/pagine/' . $id));
        }

        $valori = [
            $slug, $titolo,
            mb_substr(trim((string) $r->post('sottotitolo', '')), 0, 255),
            $corpo,
            in_array($r->post('stato'), ['bozza', 'pubblicata'], true) ? (string) $r->post('stato') : 'bozza',
            $r->haPost('in_menu') ? 1 : 0,
            (int) ($r->post('ordine') ?? 0),
        ];

        if ($id > 0) {
            Database::esegui(
                'UPDATE pagine SET slug=?, titolo=?, sottotitolo=?, corpo=?, stato=?, in_menu=?, ordine=?,
                        aggiornata=NOW() WHERE id = ?',
                array_merge($valori, [$id]),
            );
            Auth::traccia('pagina:modificata', $slug);
        } else {
            Database::esegui(
                'INSERT INTO pagine (slug, titolo, sottotitolo, corpo, stato, in_menu, ordine, creata, aggiornata)
                 VALUES (?,?,?,?,?,?,?,NOW(),NOW())',
                $valori,
            );
            $id = Database::ultimoId();
            Auth::traccia('pagina:creata', $slug);
        }

        Session::lampo('bene', 'Pagina salvata.');

        return Response::redirect(url('/admin/pagine/' . $id));
    }

    /** GET /admin/impostazioni */
    public function impostazioni(Request $r): Response
    {
        return Response::html(Vista::pagina('admin/impostazioni', [
            'titolo'       => 'Impostazioni',
            'sezione'      => 'admin',
            'impostazioni' => Impostazioni::elenco(),
        ]));
    }

    /** POST /admin/impostazioni */
    public function salvaImpostazioni(Request $r): Response
    {
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta.');

            return Response::redirect(url('/admin/impostazioni'));
        }

        $cambiate = [];
        foreach (Impostazioni::elenco() as $imp) {
            $chiave = (string) $imp['chiave'];
            $vecchio = (string) $imp['valore'];

            $nuovo = match ((string) $imp['tipo']) {
                // Una casella non spuntata non viene inviata affatto: l'assenza
                // e' l'informazione, e va letta come zero.
                'booleano' => $r->haPost('i_' . $chiave) ? '1' : '0',
                'intero'   => (string) max(0, (int) ($r->post('i_' . $chiave) ?? 0)),
                default    => mb_substr((string) ($r->post('i_' . $chiave) ?? ''), 0, 2000),
            };

            if ($nuovo !== $vecchio) {
                Impostazioni::imposta($chiave, $nuovo);
                $cambiate[] = $chiave . ': ' . $vecchio . ' → ' . $nuovo;
            }
        }

        if ($cambiate !== []) {
            Auth::traccia('impostazioni', '', implode('; ', $cambiate));
        }

        Session::lampo('bene', $cambiate === []
            ? 'Niente da cambiare.'
            : count($cambiate) . ' impostazion' . (count($cambiate) === 1 ? 'e' : 'i') . ' aggiornat' . (count($cambiate) === 1 ? 'a' : 'e') . '.');

        return Response::redirect(url('/admin/impostazioni'));
    }

    public function registro(Request $r): Response
    {
        return Response::html(Vista::pagina('admin/registro', [
            'titolo'  => 'Registro azioni',
            'sezione' => 'admin',
            'righe'   => Database::righe(
                'SELECT quando, admin_utente, azione, oggetto, dettaglio
                   FROM admin_registro ORDER BY quando DESC LIMIT 200'
            ),
        ]));
    }

    public function manutenzione(Request $r): Response
    {
        $lib = (string) \App\Core\Config::get('astro.libswe');
        $eph = (string) \App\Core\Config::get('astro.effemeridi');

        return Response::html(Vista::pagina('admin/manutenzione', [
            'titolo'  => 'Manutenzione',
            'sezione' => 'admin',
            'stato'   => [
                'PHP'                 => PHP_VERSION,
                'MariaDB'             => (string) Database::valore('SELECT VERSION()'),
                'FFI (web)'           => extension_loaded('FFI') ? 'caricata, ma ffi.enable=' . (string) ini_get('ffi.enable') : 'assente',
                'libswe'              => is_file($lib) ? $lib : 'NON trovata: ' . $lib,
                'effemeridi'          => is_dir($eph) ? count(glob($eph . '/*.se1') ?: []) . ' file .se1 in ' . $eph : 'cartella assente: ' . $eph,
                'purga accessi'       => ((int) \App\Core\Config::get('privacy.purga_accessi_giorni', 0)) === 0
                    ? 'disattivata (conservazione illimitata)'
                    : (string) \App\Core\Config::get('privacy.purga_accessi_giorni') . ' giorni',
                'anonimizzazione IP'  => \App\Core\Config::get('privacy.anonimizza_ip') ? 'attiva' : 'disattivata',
            ],
            'partizioni' => Database::righe(
                'SELECT PARTITION_NAME AS nome, TABLE_ROWS AS righe
                   FROM information_schema.PARTITIONS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'accessi\' AND PARTITION_NAME IS NOT NULL
                  ORDER BY PARTITION_ORDINAL_POSITION'
            ),
            // La tabella `calcoli` fa due mestieri: i permalink delle carte,
            // che sono per sempre, e la cache del motore, che e' buttabile.
            // Vanno contati separati, o il numero non dice niente.
            'cache' => Database::riga(
                'SELECT COUNT(*)                                        AS righe,
                        COALESCE(SUM(gettone IS NOT NULL), 0)           AS permalink,
                        COALESCE(SUM(gettone IS NULL), 0)               AS cache,
                        ROUND(COALESCE(SUM(LENGTH(esito)), 0)/1048576, 1) AS mb,
                        MIN(CASE WHEN gettone IS NULL THEN ultima_richiesta END) AS piu_vecchia
                   FROM calcoli'
            ) ?? [],
        ]));
    }

    /** @return array<string,int> */
    private function numeri(): array
    {
        return [
            'accessi_oggi'   => (int) Database::valore('SELECT COUNT(*) FROM accessi WHERE quando >= CURDATE()'),
            'accessi_totale' => (int) Database::valore('SELECT COUNT(*) FROM accessi'),
            'sessioni_oggi'  => (int) Database::valore('SELECT COUNT(*) FROM sessioni WHERE ultima_vista >= CURDATE()'),
            'bot_oggi'       => (int) Database::valore('SELECT COUNT(*) FROM accessi WHERE quando >= CURDATE() AND bot = 1'),
            'pagine'         => (int) Database::valore('SELECT COUNT(*) FROM pagine'),
            'eventi_oggi'    => (int) Database::valore('SELECT COUNT(*) FROM eventi WHERE quando >= CURDATE()'),
        ];
    }
}
