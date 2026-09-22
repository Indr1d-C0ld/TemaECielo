<?php

declare(strict_types=1);

/**
 * Tema e Cielo — importazione del gazetteer GeoNames.
 *
 *   php bin/importa-luoghi.php [cartella-fonti]
 *
 * Legge i dump scaricati da bin/scarica-fonti.sh e li carica in `luoghi` e
 * `luoghi_alias`. Idempotente: rilanciarlo aggiorna senza duplicare.
 *
 * File attesi nella cartella delle fonti:
 *   cities500.txt           luoghi di tutto il mondo con oltre 500 abitanti
 *   IT.txt                  TUTTI i luoghi abitati italiani (molti comuni
 *                           stanno sotto i 500 abitanti e in cities500 non ci sono)
 *   admin1CodesASCII.txt    nomi delle regioni
 *   admin2Codes.txt         nomi delle province
 *   alt-it/IT.txt           nomi alternativi italiani, con codice di lingua:
 *                           e' quello che fa diventare «Rome» → «Roma»
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Luogo\Normalizza;

$GLOBALS['__project_root'] = $radice;
Config::load($radice);
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$fonti = rtrim($argv[1] ?? ($radice . '/fonti'), '/');

function dice(string $t = ''): void  { fwrite(STDOUT, $t . "\n"); }
function titolo(string $t): void     { fwrite(STDOUT, "\n\033[1;36m==> {$t}\033[0m\n"); }
function bene(string $t): void       { fwrite(STDOUT, "    \033[0;32m{$t}\033[0m\n"); }
function attento(string $t): void    { fwrite(STDOUT, "    \033[0;33m{$t}\033[0m\n"); }
function muori(string $t): never     { fwrite(STDERR, "\n\033[1;31mERRORE: {$t}\033[0m\n"); exit(1); }

/** Barra di avanzamento sulla stessa riga: l'import dura minuti, il silenzio inquieta. */
function avanza(string $eti, int $fatti, int $totale, float $t0): void
{
    static $ultimo = 0.0;
    // Il ritorno a capo senza avanzamento riga serve a riscrivere sempre la
    // stessa riga: se l'uscita non e' un terminale (redirezione, cron, pipe)
    // non viene interpretato e si ottengono centinaia di righe di sporcizia.
    static $tty = null;
    $tty ??= function_exists('posix_isatty') && @posix_isatty(STDOUT);

    $ora = microtime(true);
    if (!$tty && $fatti < $totale) {
        return;   // fuori dal terminale si stampa solo la riga finale
    }
    if ($ora - $ultimo < 0.25 && $fatti < $totale) {
        return;
    }
    $ultimo = $ora;

    $q = $totale > 0 ? $fatti / $totale : 1.0;
    $larghezza = 34;
    $pieno = (int) round($q * $larghezza);
    $vel = $fatti / max(0.001, $ora - $t0);

    fwrite(STDOUT, sprintf(
        "\r    %-16s [%s%s] %5.1f%%  %s righe  %s/s   ",
        $eti,
        str_repeat('#', $pieno),
        str_repeat('.', $larghezza - $pieno),
        $q * 100,
        number_format($fatti, 0, ',', '.'),
        number_format((int) $vel, 0, ',', '.'),
    ));
    if ($fatti >= $totale) {
        fwrite(STDOUT, "\n");
    }
}

function righeDi(string $file): int
{
    $n = 0;
    $f = fopen($file, 'rb');
    if ($f === false) { return 0; }
    while (!feof($f)) {
        $n += substr_count((string) fread($f, 1 << 20), "\n");
    }
    fclose($f);

    return $n;
}

$t0 = microtime(true);

// --- controlli preliminari --------------------------------------------------
titolo('Fonti');
$necessari = ['cities500.txt', 'IT.txt', 'admin1CodesASCII.txt', 'admin2Codes.txt'];
foreach ($necessari as $f) {
    is_file($fonti . '/' . $f) || muori("Manca {$fonti}/{$f}. Esegui prima bin/scarica-fonti.sh");
    bene(sprintf('%-24s %s', $f, number_format(filesize($fonti . '/' . $f) / 1048576, 1, ',', '.') . ' MB'));
}
$altIt = $fonti . '/alt-it/IT.txt';
is_file($altIt)
    ? bene(sprintf('%-24s %s', 'alt-it/IT.txt', number_format(filesize($altIt) / 1048576, 1, ',', '.') . ' MB'))
    : attento('alt-it/IT.txt assente: le citta\' italiane resteranno coi nomi inglesi di GeoNames.');

Database::disponibile() || muori('Database non raggiungibile.');

// --- nomi italiani ----------------------------------------------------------
$nomeItaliano = [];
if (is_file($altIt)) {
    titolo('Nomi italiani ufficiali');
    $f = fopen($altIt, 'rb');
    $n = 0;
    while (($riga = fgets($f)) !== false) {
        $c = explode("\t", rtrim($riga, "\r\n"));
        if (count($c) < 5 || $c[2] !== 'it') {
            continue;
        }
        $id = (int) $c[1];
        // Il nome «preferito» vince su tutti; altrimenti vale il primo trovato.
        if ($c[4] === '1' || !isset($nomeItaliano[$id])) {
            $nomeItaliano[$id] = $c[3];
            $n++;
        }
    }
    fclose($f);
    bene(count($nomeItaliano) . ' luoghi italiani con nome proprio (su ' . $n . ' voci)');
}

// --- tabelle di appoggio ----------------------------------------------------
titolo('Nomi di regioni e province');

/**
 * I file delle suddivisioni portano il geonameid nella quarta colonna. Se di
 * quel geonameid conosciamo il nome italiano, vince quello.
 *
 * @param array<int,string> $nomeItaliano
 * @return array{0:array<string,string>,1:int}
 */
$leggiSuddivisioni = static function (string $file, array $nomeItaliano): array {
    $mappa = [];
    $tradotti = 0;
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $riga) {
        $c = explode("\t", $riga);
        if (count($c) < 2) {
            continue;
        }
        $id = isset($c[3]) ? (int) $c[3] : 0;
        if ($id > 0 && isset($nomeItaliano[$id])) {
            $mappa[$c[0]] = $nomeItaliano[$id];
            $tradotti++;
        } else {
            $mappa[$c[0]] = $c[1];
        }
    }

    return [$mappa, $tradotti];
};

[$admin1, $t1] = $leggiSuddivisioni($fonti . '/admin1CodesASCII.txt', $nomeItaliano);
bene(count($admin1) . ' regioni  (' . $t1 . ' col nome italiano)');

[$admin2, $t2] = $leggiSuddivisioni($fonti . '/admin2Codes.txt', $nomeItaliano);
bene(count($admin2) . ' province  (' . $t2 . ' col nome italiano)');

/** I nomi dei paesi li sa gia' intl, e in italiano. */
$paeseNome = static function (string $cc): string {
    static $cache = [];
    if (!isset($cache[$cc])) {
        $n = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-' . $cc, 'it') : $cc;
        $cache[$cc] = ($n === '' || $n === $cc) ? $cc : $n;
    }

    return $cache[$cc];
};

// --- caricamento ------------------------------------------------------------
titolo('Caricamento dei luoghi');

Database::esegui('SET foreign_key_checks = 0');
Database::esegui('TRUNCATE TABLE luoghi_alias');
Database::esegui('TRUNCATE TABLE luoghi');
Database::esegui('SET foreign_key_checks = 1');

$pdo = Database::pdo();

$sqlLuogo = 'INSERT INTO luoghi
    (id, nome, nome_geonames, nome_ascii, paese, paese_nome, admin1, admin1_nome,
     admin2, admin2_nome, lat, lon, altitudine, popolazione, fuso, codice)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE popolazione = VALUES(popolazione)';
$insLuogo = $pdo->prepare($sqlLuogo);

$sqlAlias = 'INSERT INTO luoghi_alias (luogo_id, nome, nome_norm, lingua, preferito) VALUES (?,?,?,?,?)';
$insAlias = $pdo->prepare($sqlAlias);

$contaLuoghi = 0;
$contaAlias  = 0;
$visti       = [];

/**
 * @param resource $mano
 */
function carica(
    string $file, string $etichetta, bool $soloAbitati,
    array $admin1, array $admin2, array $nomeItaliano, callable $paeseNome,
    \PDOStatement $insLuogo, \PDOStatement $insAlias,
    array &$visti, int &$contaLuoghi, int &$contaAlias,
): void {
    $totale = righeDi($file);
    $f = fopen($file, 'rb');
    $f !== false || muori("Non riesco ad aprire {$file}");

    $pdo = \App\Core\Database::pdo();
    $pdo->beginTransaction();

    $t0 = microtime(true);
    $lette = 0;

    while (($riga = fgets($f)) !== false) {
        $lette++;
        if ($lette % 2000 === 0) {
            avanza($etichetta, $lette, $totale, $t0);
        }

        $c = explode("\t", rtrim($riga, "\r\n"));
        if (count($c) < 19) {
            continue;
        }

        // Classe P = luoghi abitati. Il file completo dell'Italia contiene
        // anche monti, fiumi e chiese: nessuno ci nasce.
        if ($soloAbitati && $c[6] !== 'P') {
            continue;
        }

        $id = (int) $c[0];
        if (isset($visti[$id])) {
            continue;   // gia' caricato da un file precedente
        }
        $visti[$id] = true;

        $paese  = $c[8];
        $a1     = $c[10];
        $a2     = $c[11];
        $chiave1 = $paese . '.' . $a1;
        $chiave2 = $chiave1 . '.' . $a2;

        // L'altitudine sta nella colonna 16 quando e' misurata, altrimenti si
        // usa il modello digitale del terreno (colonna 17).
        $alt = $c[15] !== '' ? (int) $c[15] : (int) $c[16];

        $geonames = $c[1];
        $nome     = $nomeItaliano[$id] ?? $geonames;

        $insLuogo->execute([
            $id, $nome, $geonames, $c[2], $paese, $paeseNome($paese),
            $a1, $admin1[$chiave1] ?? '', $a2, $admin2[$chiave2] ?? '',
            (float) $c[4], (float) $c[5], $alt, (int) $c[14], $c[17], $c[7],
        ]);
        $contaLuoghi++;

        // Tutti i nomi sotto cui il luogo si puo' cercare, deduplicati dopo
        // normalizzazione: «Milano», «MILANO» e «milano» sono la stessa chiave.
        $alias = [$nome, $geonames, $c[2]];
        if ($c[3] !== '') {
            foreach (explode(',', $c[3]) as $a) {
                $alias[] = $a;
            }
        }

        $fatti = [];
        foreach ($alias as $a) {
            $a = trim($a);
            if ($a === '' || mb_strlen($a) > 190) {
                continue;
            }
            $norm = Normalizza::nome($a);
            if ($norm === '' || isset($fatti[$norm])) {
                continue;
            }
            $fatti[$norm] = true;

            $insAlias->execute([$id, $a, $norm, '', $a === $nome ? 1 : 0]);
            $contaAlias++;
        }

        if ($contaLuoghi % 5000 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }

    $pdo->commit();
    fclose($f);
    avanza($etichetta, $totale, $totale, $t0);
}

// cities500 per primo: e' il mondo. Poi IT.txt aggiunge i piccoli comuni
// italiani che in cities500 non ci sono.
carica($fonti . '/cities500.txt', 'mondo', false, $admin1, $admin2, $nomeItaliano, $paeseNome,
       $insLuogo, $insAlias, $visti, $contaLuoghi, $contaAlias);
carica($fonti . '/IT.txt', 'Italia', true, $admin1, $admin2, $nomeItaliano, $paeseNome,
       $insLuogo, $insAlias, $visti, $contaLuoghi, $contaAlias);

$durata = (int) round(microtime(true) - $t0);

Database::esegui(
    'INSERT INTO luoghi_import (quando, sorgente, luoghi, alias, durata_s) VALUES (NOW(), ?, ?, ?, ?)',
    ['GeoNames cities500 + IT', $contaLuoghi, $contaAlias, $durata],
);

titolo('Fatto');
bene(number_format($contaLuoghi, 0, ',', '.') . ' luoghi');
bene(number_format($contaAlias, 0, ',', '.') . ' nomi indicizzati');
bene($durata . ' secondi');
dice('');
