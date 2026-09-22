<?php

declare(strict_types=1);

/**
 * Tema e Cielo — lavoratore delle effemeridi.
 *
 * Legge UN oggetto JSON da stdin, scrive UN oggetto JSON su stdout, esce.
 *
 * Esiste perche' PHP sotto Apache ha `ffi.enable=preload` e quindi non puo'
 * usare l'FFI, mentre in CLI l'FFI e' sempre attivo. Il livello web lo invoca
 * con proc_open: nessuna modifica a php.ini, nessun compilatore, nessun demone.
 *
 * Non esegue MAI codice che venga dall'ingresso: legge un oggetto, chiama un
 * metodo scelto da una lista chiusa, risponde.
 *
 * Prova a mano:
 *   echo '{"operazione":"stato"}' | php bin/effemeridi.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Questo programma si usa solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';

use App\Astro\Sweph;
use App\Astro\Worker;
use App\Core\Config;

function rispondi(array $dati, int $uscita = 0): never
{
    fwrite(STDOUT, (string) json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    exit($uscita);
}

function fallisci(string $messaggio, string $tipo = 'errore'): never
{
    rispondi(['ok' => false, 'tipo' => $tipo, 'errore' => $messaggio], 1);
}

// --- configurazione ---------------------------------------------------------
// Si accettano scavalchi da ambiente: servono alle prove e al collaudo, dove la
// libreria puo' stare altrove rispetto all'installazione di sistema.
$libreria   = getenv('TEC_LIBSWE') ?: null;
$effemeridi = getenv('TEC_EPHE') ?: null;

if ($libreria === null || $effemeridi === null) {
    try {
        Config::load($radice);
    } catch (\Throwable $e) {
        fallisci('Configurazione non caricabile: ' . $e->getMessage(), 'configurazione');
    }
    $libreria   ??= (string) Config::get('astro.libswe');
    $effemeridi ??= (string) Config::get('astro.effemeridi');
}

// --- ingresso ---------------------------------------------------------------
$grezzo = stream_get_contents(STDIN);
if ($grezzo === false || trim($grezzo) === '') {
    fallisci('Nessun ingresso su stdin.', 'ingresso');
}
if (strlen($grezzo) > 262144) {
    fallisci('Ingresso troppo grande.', 'ingresso');
}

try {
    $domanda = json_decode($grezzo, true, 32, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    fallisci('JSON non valido: ' . $e->getMessage(), 'ingresso');
}
if (!is_array($domanda)) {
    fallisci('L\'ingresso deve essere un oggetto JSON.', 'ingresso');
}

// --- calcolo ----------------------------------------------------------------
$t0 = microtime(true);

try {
    $swe = new Sweph($libreria, $effemeridi);
} catch (\Throwable $e) {
    fallisci($e->getMessage(), 'libreria');
}

try {
    $esito = (new Worker($swe))->esegui($domanda);
} catch (\Throwable $e) {
    fallisci($e->getMessage(), 'calcolo');
}

$esito['durata_ms'] = round((microtime(true) - $t0) * 1000, 2);

rispondi($esito);
