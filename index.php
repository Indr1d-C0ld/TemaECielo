<?php

declare(strict_types=1);

/**
 * Tema e Cielo — front controller unico.
 * Ogni richiesta applicativa passa da qui (RewriteRule ^ index.php [L]).
 */

define('TEMAECIELO', true);

$inizio = microtime(true);
$radice = __DIR__;

require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Csp;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Vista;
use App\Support\Telemetria;

$GLOBALS['__project_root'] = $radice;

// --- Configurazione ----------------------------------------------------------
try {
    Config::load($radice);
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Tema e Cielo — configurazione</title>'
        . '<style>body{font:16px/1.7 Georgia,serif;background:#0b1026;color:#e6e9f5;'
        . 'max-width:44rem;margin:4rem auto;padding:0 1.5rem}code{background:#121a3a;'
        . 'padding:.15em .4em;border-radius:2px}h1{color:#c9a227;font-weight:400}</style>'
        . '<h1>Configurazione mancante</h1><p>Il portale non puo\' avviarsi:</p>'
        . '<pre><code>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</code></pre>'
        . '<p>Esegui <code>sudo bash deploy/00-bootstrap.sh</code>, poi '
        . '<code>bash deploy/01-installa.sh</code>.</p>';
    exit;
}

date_default_timezone_set((string) Config::get('app.timezone', 'Europe/Rome'));

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

Vista::percorso($radice . '/views');

// --- Richiesta ---------------------------------------------------------------
$basePath = Config::get('app.base_path');
$richiesta = new Request(is_string($basePath) && $basePath !== '' ? $basePath : null);
$GLOBALS['__base_path'] = $richiesta->basePath();

Session::avvia();

// --- Manutenzione ------------------------------------------------------------
// Si controlla PRIMA di instradare, cosi' vale per ogni pagina compresi gli
// indirizzi delle carte. L'admin passa: altrimenti non potrebbe rientrare per
// spegnere la modalita', e resterebbe chiuso fuori dal proprio portale.
$percorso = $richiesta->percorso();
$sempreAperti = ['/accesso', '/uscita'];

if (!in_array($percorso, $sempreAperti, true)
    && !str_starts_with($percorso, '/admin')
    && \App\Core\Database::disponibile()
    && \App\Support\Impostazioni::attiva('manutenzione', false)
    && !\App\Auth\Auth::amministratore()) {

    $risposta = Response::html(Vista::pagina('errors/generico', [
        'titolo'    => 'Chiuso per manutenzione',
        'stato'     => 503,
        'messaggio' => 'Il portale e\' momentaneamente chiuso. Torna fra poco.',
    ]), 503)->conIntestazione('Retry-After', '1800');

    foreach (Csp::intestazioni() as $nome => $valore) {
        $risposta = $risposta->conIntestazione($nome, $valore);
    }
    $risposta->invia();
    exit;
}

// --- Instradamento -----------------------------------------------------------
$router = new Router();
require $radice . '/src/routes.php';

try {
    $risposta = $router->smista($richiesta);
} catch (\Throwable $e) {
    registro(sprintf('%s: %s @ %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()), 'error');

    $eDb = str_contains(strtolower($e->getMessage()), 'database');

    $risposta = Response::html(Vista::pagina('errors/generico', [
        'titolo'    => $eDb ? 'Servizio non disponibile' : 'Errore interno',
        'stato'     => $eDb ? 503 : 500,
        'messaggio' => $debug
            ? $e::class . ': ' . $e->getMessage()
            : ($eDb
                ? 'Il portale non riesce a raggiungere il proprio archivio. Riprova fra poco.'
                : 'Si e\' verificato un errore imprevisto. L\'incidente e\' stato registrato.'),
    ]), $eDb ? 503 : 500);
}

foreach (Csp::intestazioni() as $nome => $valore) {
    $risposta = $risposta->conIntestazione($nome, $valore);
}

$risposta->invia();

// --- Telemetria, dopo aver risposto -----------------------------------------
// Il conto delle visite non deve stare fra il visitatore e il contenuto.
//
// Con PHP-FPM `fastcgi_finish_request` chiude davvero la risposta e quel che
// segue non costa niente a chi guarda. Con mod_php quella funzione NON ESISTE,
// il blocco qui sotto non fa nulla, e la telemetria resta dentro il tempo di
// risposta: vale la pena saperlo, perche' e' facile credersi al riparo.
//
// Percio' cio' che sta dopo dev'essere veloce per conto proprio, e non perche'
// qualcuno non lo sta guardando. Oggi lo e' — una manciata di millisecondi —
// ma lo e' diventato: la ricerca geografica dell'indirizzo, prima di essere
// corretta, ne costava quattromila. Vedi `Rete::geolocalizza`.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (Database::disponibile()) {
    Telemetria::registra($richiesta, $risposta, $inizio);
}
