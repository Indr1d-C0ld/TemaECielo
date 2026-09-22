<?php

declare(strict_types=1);

/**
 * Tema e Cielo — importazione del catalogo stellare e delle costellazioni.
 *
 *   php bin/importa-stelle.php [cartella-fonti] [magnitudine-limite]
 *
 * File attesi:
 *   hyg_v40.csv             catalogo HYG (CC BY-SA 4.0)
 *   stellarium/modern.json  linee delle costellazioni (GPL-2.0)
 *
 * La magnitudine limite predefinita e' 6,5: e' il limite dell'occhio nudo in
 * un cielo davvero buio, e sono circa novemila stelle. Andare oltre riempie il
 * database di stelle che nessuno vedrebbe mai.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Cielo\Costellazioni;
use App\Core\Config;
use App\Core\Database;

$GLOBALS['__project_root'] = $radice;
Config::load($radice);

$fonti   = rtrim($argv[1] ?? ($radice . '/fonti'), '/');
$limite  = (float) ($argv[2] ?? 6.5);

function dice(string $t = ''): void { fwrite(STDOUT, $t . "\n"); }
function titolo(string $t): void    { fwrite(STDOUT, "\n\033[1;36m==> {$t}\033[0m\n"); }
function bene(string $t): void      { fwrite(STDOUT, "    \033[0;32m{$t}\033[0m\n"); }
function attento(string $t): void   { fwrite(STDOUT, "    \033[0;33m{$t}\033[0m\n"); }
function muori(string $t): never    { fwrite(STDERR, "\n\033[1;31mERRORE: {$t}\033[0m\n"); exit(1); }

$t0 = microtime(true);

$csv  = $fonti . '/hyg_v40.csv';
$json = $fonti . '/stellarium/modern.json';

titolo('Fonti');
is_file($csv)  || muori("Manca {$csv}. Vedi docs/FONTI.md");
is_file($json) || muori("Manca {$json}. Vedi docs/FONTI.md");
bene(sprintf('%-28s %s MB', basename($csv), number_format(filesize($csv) / 1048576, 1, ',', '.')));
bene(sprintf('%-28s %s KB', 'stellarium/modern.json', number_format(filesize($json) / 1024, 0, ',', '.')));
Database::disponibile() || muori('Database non raggiungibile.');

// --- costellazioni ----------------------------------------------------------
titolo('Costellazioni');
Database::esegui('TRUNCATE TABLE costellazioni_linee');
Database::esegui('DELETE FROM costellazioni');

$ins = Database::pdo()->prepare(
    'INSERT INTO costellazioni (abbr, nome_it, nome_lat, genitivo) VALUES (?,?,?,?)'
);
foreach (Costellazioni::elenco() as $abbr => [$it, $lat, $gen]) {
    $ins->execute([$abbr, $it, $lat, $gen]);
}
bene(count(Costellazioni::elenco()) . ' costellazioni');

// --- linee ------------------------------------------------------------------
$dati = json_decode((string) file_get_contents($json), true, 64, JSON_THROW_ON_ERROR);
$segmenti = 0;
$servono  = [];

$pdo = Database::pdo();
$pdo->beginTransaction();
$insLinea = $pdo->prepare('INSERT INTO costellazioni_linee (costellazione, hip_a, hip_b) VALUES (?,?,?)');

foreach ($dati['constellations'] ?? [] as $c) {
    // L'identificativo di Stellarium e' del tipo «CON modern Aql»: l'ultimo
    // pezzo e' l'abbreviazione IAU, l'unica cosa che ci interessa.
    $pezzi = explode(' ', (string) ($c['id'] ?? ''));
    $abbr  = end($pezzi);
    if ($abbr === '' || !isset(Costellazioni::elenco()[$abbr])) {
        continue;
    }

    // Le linee sono polilinee: una sequenza di stelle da unire in catena. Si
    // spezzano in coppie, cosi' il disegno puo' saltare i singoli segmenti che
    // hanno un estremo sotto l'orizzonte, senza ricostruire la spezzata.
    foreach ($c['lines'] ?? [] as $polilinea) {
        for ($i = 0, $n = count($polilinea) - 1; $i < $n; $i++) {
            $a = (int) $polilinea[$i];
            $b = (int) $polilinea[$i + 1];
            if ($a <= 0 || $b <= 0) {
                continue;
            }
            $insLinea->execute([$abbr, $a, $b]);
            $servono[$a] = true;
            $servono[$b] = true;
            $segmenti++;
        }
    }
}
$pdo->commit();
bene($segmenti . ' segmenti, su ' . count($servono) . ' stelle');

// --- stelle -----------------------------------------------------------------
titolo('Catalogo stellare (magnitudine fino a ' . number_format($limite, 1, ',', '') . ')');

Database::esegui('TRUNCATE TABLE stelle');

$italiani = Costellazioni::nomiItaliani();
$costell  = Costellazioni::elenco();

$f = fopen($csv, 'rb');
$f !== false || muori("Non riesco ad aprire {$csv}");

$intestazione = fgetcsv($f, 0, ',', '"', '');
$col = array_flip(array_map(static fn ($s): string => trim((string) $s, '"'), $intestazione ?: []));

foreach (['hip', 'proper', 'ra', 'dec', 'mag', 'ci', 'bayer', 'flam', 'con', 'spect', 'dist'] as $c) {
    isset($col[$c]) || muori("Colonna mancante nel CSV: {$c}");
}

$pdo->beginTransaction();
$insStella = $pdo->prepare(
    'INSERT INTO stelle (hip, nome, bayer, flamsteed, costellazione, ar, decl, mag, ci, spettro, distanza)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE mag = VALUES(mag)'
);

$messe = 0;
$lette = 0;
$forzate = 0;
$vistoHip = [];

while (($r = fgetcsv($f, 0, ',', '"', '')) !== false) {
    $lette++;

    $hip = (int) ($r[$col['hip']] ?? 0);
    if ($hip <= 0) {
        continue;   // senza numero Hipparcos non si puo' agganciare alle linee
    }
    if (isset($vistoHip[$hip])) {
        continue;   // il catalogo ha piu' righe per le stelle doppie
    }

    $mag = $r[$col['mag']] === '' ? 99.0 : (float) $r[$col['mag']];

    // Oltre il limite si scarta, TRANNE se la stella serve a disegnare una
    // costellazione: un segmento con un estremo mancante lascerebbe un buco
    // in una figura che tutti riconoscono.
    $serve = isset($servono[$hip]);
    if ($mag > $limite && !$serve) {
        continue;
    }
    if ($mag > $limite) {
        $forzate++;
    }

    $vistoHip[$hip] = true;

    $nome = trim((string) ($r[$col['proper']] ?? ''));
    $nome = $italiani[$nome] ?? $nome;

    $con = trim((string) ($r[$col['con']] ?? ''));
    if (!isset($costell[$con])) {
        $con = '';
    }

    $insStella->execute([
        $hip,
        $nome !== '' ? $nome : null,
        trim((string) ($r[$col['bayer']] ?? '')),
        $r[$col['flam']] === '' ? null : (int) $r[$col['flam']],
        $con,
        // L'ascensione retta nel catalogo e' in ORE: qui si tiene in gradi,
        // come tutto il resto del portale.
        (float) $r[$col['ra']] * 15.0,
        (float) $r[$col['dec']],
        $mag,
        $r[$col['ci']] === '' ? null : (float) $r[$col['ci']],
        mb_substr(trim((string) ($r[$col['spect']] ?? '')), 0, 20),
        $r[$col['dist']] === '' ? null : (float) $r[$col['dist']],
    ]);
    $messe++;

    if ($messe % 2000 === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();
fclose($f);

bene(number_format($messe, 0, ',', '.') . ' stelle su ' . number_format($lette, 0, ',', '.') . ' lette');
if ($forzate > 0) {
    bene($forzate . ' oltre il limite, tenute perche' . "'" . ' servono alle costellazioni');
}

// --- verifica ---------------------------------------------------------------
titolo('Verifica');
$orfane = (int) Database::valore(
    'SELECT COUNT(*) FROM costellazioni_linee l
      WHERE NOT EXISTS (SELECT 1 FROM stelle s WHERE s.hip = l.hip_a)
         OR NOT EXISTS (SELECT 1 FROM stelle s WHERE s.hip = l.hip_b)'
);
$orfane === 0
    ? bene('tutti i segmenti hanno entrambi gli estremi nel catalogo')
    : attento($orfane . ' segmenti hanno un estremo mancante e non verranno disegnati');

$conNome = (int) Database::valore('SELECT COUNT(*) FROM stelle WHERE nome IS NOT NULL');
bene($conNome . ' stelle con nome proprio');

dice('');
dice('    ' . (int) round(microtime(true) - $t0) . ' secondi');
dice('');
