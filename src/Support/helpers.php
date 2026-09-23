<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csp;
use App\Core\Csrf;
use App\Core\Vista;

/** Scappa per HTML. Il nome e' corto perche' si usa in ogni riga delle viste. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Indirizzo assoluto dentro il portale: url('/carta/abc') -> /temaecielo/carta/abc */
function url(string $percorso = '/'): string
{
    $base = rtrim((string) ($GLOBALS['__base_path'] ?? ''), '/');

    return $base . '/' . ltrim($percorso, '/');
}

/** Indirizzo di una risorsa statica, con marca di versione per la cache. */
function risorsa(string $percorso): string
{
    $rel  = '/assets/' . ltrim($percorso, '/');
    $file = (string) ($GLOBALS['__project_root'] ?? '') . $rel;
    $v    = is_file($file) ? substr((string) filemtime($file), -6) : '0';

    return url($rel) . '?v=' . $v;
}

function nonce(): string
{
    return Csp::nonce();
}

function csrf(): string
{
    return Csrf::campo();
}

/** @param array<string,mixed> $dati */
function vista(string $nome, array $dati = []): string
{
    return Vista::rendi($nome, $dati);
}

/** @param array<string,mixed> $dati */
function pagina(string $nome, array $dati = []): string
{
    return Vista::pagina($nome, $dati);
}

/**
 * Riga di registro su storage/log/YYYY-MM.log.
 * Volutamente elementare: i log applicativi qui servono alla diagnosi, non
 * all'analisi — quella passa dalle tabelle di telemetria.
 */
function registro(string $messaggio, string $livello = 'info'): void
{
    $dir = (string) ($GLOBALS['__project_root'] ?? sys_get_temp_dir()) . '/storage/log';
    if (!is_dir($dir)) {
        @mkdir($dir, 02775, true);
    }
    $file = $dir . '/' . date('Y-m') . '.log';
    $nuovo = !is_file($file);
    $riga = sprintf("[%s] %-5s %s\n", date('Y-m-d H:i:s'), strtoupper($livello), $messaggio);
    @file_put_contents($file, $riga, FILE_APPEND | LOCK_EX);
    // Chi apre il file del mese lo apre per tutti: da riga di comando nasceva
    // con i permessi dell'utente, e il web server non poteva piu' scriverci —
    // per un mese intero, in silenzio, mentre la pagina d'errore diceva
    // «l'incidente e' stato registrato».
    if ($nuovo) {
        @chmod($file, 0664);
    }
}

/**
 * Glifo dallo sprite incorporato in pagina: glifo('sole'), glifo('bilancia').
 * Il riferimento e' locale al documento, non a un file esterno.
 */
function glifo(string $nome, string $classe = ''): string
{
    $id = 'gl-' . preg_replace('/[^a-z0-9_-]/', '', strtolower($nome));

    return '<svg class="glifo' . ($classe !== '' ? ' ' . e($classe) : '') . '" aria-hidden="true">'
        . '<use href="#' . e((string) $id) . '"></use></svg>';
}

function debug(): bool
{
    return Config::caricata() && (bool) Config::get('app.debug', false);
}

/**
 * Le pagine redazionali da mostrare nel pie' di pagina: quelle pubblicate e
 * segnate «nel menu» dalla regia.
 *
 * Il campo esisteva, la regia lo faceva spuntare, e nessuna pagina lo leggeva:
 * spuntarlo non cambiava niente. Qui si legge una volta per richiesta; se
 * l'archivio non risponde, il pie' di pagina resta senza, e la pagina esce lo
 * stesso.
 *
 * @return list<array{slug:string,titolo:string}>
 */
function pagine_in_menu(): array
{
    static $pagine = null;
    if ($pagine !== null) {
        return $pagine;
    }
    try {
        $pagine = array_map(
            static fn (array $r): array => ['slug' => (string) $r['slug'], 'titolo' => (string) $r['titolo']],
            \App\Core\Database::righe(
                "SELECT slug, titolo FROM pagine WHERE stato = 'pubblicata' AND in_menu = 1 ORDER BY ordine, titolo"
            ),
        );
    } catch (\Throwable) {
        $pagine = [];
    }

    return $pagine;
}
