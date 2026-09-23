<?php

declare(strict_types=1);

/**
 * Mappa degli indirizzi. Un solo file, letto dall'alto in basso.
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\ApiController;
use App\Controllers\CalcolaController;
use App\Controllers\CieloController;
use App\Controllers\DerivateController;
use App\Controllers\GuestbookController;
use App\Controllers\HomeController;
use App\Controllers\SinastriaController;
use App\Controllers\StatisticheController;
use App\Controllers\PagineController;
use App\Admin\AccessoController;
use App\Admin\ArchivioController as AdminArchivio;
use App\Controllers\ArchivioController;
use App\Controllers\MondoController;
use App\Admin\CorpusController;
use App\Admin\GuestbookController as AdminGuestbook;
use App\Admin\PannelloController;

// --- Pubblico ---------------------------------------------------------------
$router->get('/',           [HomeController::class, 'home']);
$router->get('/calcola',    [CalcolaController::class, 'modulo']);
$router->post('/calcola',   [CalcolaController::class, 'calcola']);
$router->get('/carta/{gettone}', [CalcolaController::class, 'carta']);
$router->get('/carta/{gettone}/ruota.svg', [CalcolaController::class, 'ruota']);
$router->post('/carta/{gettone}/elimina',  [CalcolaController::class, 'elimina']);
$router->get('/carta/{gettone}/cielo.svg', [CalcolaController::class, 'cielo']);
$router->get('/carta/{gettone}/sinastria',  [SinastriaController::class, 'modulo']);
$router->post('/carta/{gettone}/sinastria', [SinastriaController::class, 'calcola']);
$router->get('/carta/{gettone}/transiti',   [SinastriaController::class, 'transiti']);
$router->get('/carta/{gettone}/derivate',   [DerivateController::class, 'pagina']);
$router->get('/cielo',      [CieloController::class, 'cielo']);
$router->get('/cielo/volta.svg', [CieloController::class, 'volta']);
$router->get('/sinastria',  [SinastriaController::class, 'rapida']);
$router->get('/oggi',       [CieloController::class, 'oggi']);
$router->get('/statistiche',[StatisticheController::class, 'pagina']);
$router->get('/guestbook',  [GuestbookController::class, 'elenco']);
$router->post('/guestbook', [GuestbookController::class, 'firma']);

// --- Interfaccia di programmazione -----------------------------------------
// Sola lettura, tutta servita da qui: cercare un luogo di nascita non deve far
// uscire un carattere verso terzi.
$router->get('/api/luoghi',       [ApiController::class, 'luoghi']);
$router->get('/api/luogo-vicino', [ApiController::class, 'vicino']);
$router->get('/api/fuso',         [ApiController::class, 'fuso']);

// Pagine redazionali gestite dall'admin: sempre per ultime fra le GET pubbliche,
// perche' la loro regola e' la piu' larga e mangerebbe le rotte fisse.
$router->get('/pagina/{slug}', [PagineController::class, 'mostra']);

// L'archivio pubblico: persone, eventi, nazioni.
$router->get('/archivio',        [ArchivioController::class, 'elenco']);
$router->get('/archivio/{slug}', [ArchivioController::class, 'mostra']);

// L'astrologia mondiale: l'anno, le eclissi, i cicli, e la carta di un istante.
$router->get('/mondo',          [MondoController::class, 'indice']);
$router->get('/mondo/anno',     [MondoController::class, 'anno']);
$router->get('/mondo/eclissi',  [MondoController::class, 'eclissi']);
$router->get('/mondo/cicli',    [MondoController::class, 'cicli']);
$router->get('/mondo/carta',    [MondoController::class, 'carta']);

// --- Accesso ----------------------------------------------------------------
$router->get('/accesso',  [AccessoController::class, 'modulo']);
$router->post('/accesso', [AccessoController::class, 'entra']);
$router->post('/uscita',  [AccessoController::class, 'esci']);

// --- Regia ------------------------------------------------------------------
$router->get('/admin',            [PannelloController::class, 'cruscotto'], ['admin']);
$router->get('/admin/accessi',    [PannelloController::class, 'accessi'],   ['admin']);
$router->get('/admin/pagine',     [PannelloController::class, 'pagine'],    ['admin']);
$router->get('/admin/pagine/{id}',  [PannelloController::class, 'pagina'],      ['admin']);
$router->post('/admin/pagine/{id}', [PannelloController::class, 'salvaPagina'], ['admin']);
$router->get('/admin/impostazioni',  [PannelloController::class, 'impostazioni'],      ['admin']);
$router->post('/admin/impostazioni', [PannelloController::class, 'salvaImpostazioni'], ['admin']);
$router->get('/admin/registro',   [PannelloController::class, 'registro'],  ['admin']);
$router->get('/admin/manutenzione',[PannelloController::class, 'manutenzione'], ['admin']);
$router->get('/admin/corpus',            [CorpusController::class, 'elenco'],     ['admin']);
$router->get('/admin/corpus/copertura',  [CorpusController::class, 'copertura'],  ['admin']);
$router->get('/admin/corpus/{id}',       [CorpusController::class, 'modulo'],     ['admin']);
$router->post('/admin/corpus/{id}',      [CorpusController::class, 'salva'],      ['admin']);
$router->get('/admin/guestbook',         [AdminGuestbook::class, 'elenco'],       ['admin']);
$router->post('/admin/guestbook/{id}',   [AdminGuestbook::class, 'azione'],       ['admin']);
$router->get('/admin/blocchi',           [AdminGuestbook::class, 'blocchi'],      ['admin']);
$router->get('/admin/carte',             [AdminArchivio::class, 'elenco'],        ['admin']);
$router->get('/admin/carte/{gettone}',   [AdminArchivio::class, 'scheda'],        ['admin']);
$router->post('/admin/carte/{gettone}',  [AdminArchivio::class, 'salva'],         ['admin']);
$router->post('/admin/blocchi',          [AdminGuestbook::class, 'salvaBlocco'],  ['admin']);
