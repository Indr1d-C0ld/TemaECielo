<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;
use App\Support\Markdown;

final class PagineController
{
    public function mostra(Request $r, array $argomenti): Response
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($argomenti['slug'] ?? ''));

        $pagina = Database::riga(
            'SELECT titolo, sottotitolo, corpo, aggiornata FROM pagine
              WHERE slug = ? AND stato = \'pubblicata\' LIMIT 1',
            [$slug],
        );

        if ($pagina === null) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo'    => 'Pagina non trovata',
                'stato'     => 404,
                'messaggio' => 'Non c\'è nessuna pagina a questo indirizzo.',
            ]), 404);
        }

        return Response::html(Vista::pagina('pagina', [
            'titolo'      => (string) $pagina['titolo'],
            'sottotitolo' => (string) $pagina['sottotitolo'],
            'corpo'       => Markdown::rendi((string) $pagina['corpo']),
            'aggiornata'  => (string) $pagina['aggiornata'],
            'sezione'     => 'pagina',
        ]));
    }
}
