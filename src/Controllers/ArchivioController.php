<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Archivio\Archivio;
use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;
use App\Support\Telemetria;

/**
 * L'archivio pubblico: le carte di persone, eventi e nazioni che la regia ha
 * calcolato e documentato.
 *
 * Ogni voce e' una carta come le altre, e si apre con tutto quello che il
 * portale sa fare: letture, volta, transiti, carte del tempo, confronto con la
 * propria carta.
 */
final class ArchivioController
{
    private const PER_PAGINA = 60;

    /** GET /archivio */
    public function elenco(Request $r): Response
    {
        $filtri = [
            'tipo'      => (string) ($r->query('tipo') ?? ''),
            'categoria' => preg_replace('/[^a-z]/', '', (string) ($r->query('categoria') ?? '')) ?? '',
            'q'         => trim(mb_substr((string) ($r->query('q') ?? ''), 0, 80)),
            'secolo'    => (int) ($r->query('secolo') ?? 0),
        ];
        if (!isset(Archivio::TIPI[$filtri['tipo']])) {
            $filtri['tipo'] = '';
        }
        $pagina = min(10_000, max(1, (int) ($r->query('p') ?? '1')));

        $elenco = Archivio::elenco($filtri, $pagina, self::PER_PAGINA);
        Telemetria::evento('archivio', $filtri['tipo'] . '/' . $filtri['categoria'], $filtri['q']);

        return Response::html(Vista::pagina('archivio', [
            'titolo'    => 'Archivio',
            'sezione'   => 'archivio',
            'filtri'    => $filtri,
            'pagina'    => $pagina,
            'per'       => self::PER_PAGINA,
            'righe'     => $elenco['righe'],
            'totale'    => $elenco['totale'],
            'conteggi'  => Archivio::conteggi(),
        ]));
    }

    /** GET /archivio/{slug} */
    public function mostra(Request $r, array $argomenti): Response
    {
        $slug = preg_replace('/[^a-z0-9-]/', '', (string) ($argomenti['slug'] ?? '')) ?? '';
        $voce = Archivio::perSlug($slug);
        if ($voce === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Non in archivio', 'stato' => 404,
                'messaggio' => 'Nell\'archivio non c\'e\' nessuna voce con questo nome.',
            ]), 404);
        }

        Telemetria::evento('archivio_voce', $slug);

        return (new CalcolaController())->mostraCarta($r, (string) $voce['gettone']);
    }
}
