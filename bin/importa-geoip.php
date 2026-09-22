<?php

declare(strict_types=1);

/**
 * Tema e Cielo — importazione della geolocalizzazione degli indirizzi.
 *
 *   php bin/importa-geoip.php [cartella-fonti]
 *
 * File attesi: dbip-city.csv.gz e dbip-asn.csv.gz (DB-IP Lite, CC BY 4.0).
 *
 * Sono quasi otto milioni di intervalli. Inserirli uno per uno con
 * un'istruzione preparata ciascuno richiederebbe ore: si compongono
 * istruzioni da mille righe, con i valori gia' dentro il testo. Sembra
 * sporco, e invece e' l'unico modo per stare nei minuti — e non c'e' rischio
 * di iniezione, perche' ogni valore passa da quote() o da una conversione
 * numerica.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Support\Rete;

$GLOBALS['__project_root'] = $radice;
Config::load($radice);

$fonti = rtrim($argv[1] ?? ($radice . '/fonti'), '/');
$lotto = 1000;

function titolo(string $t): void  { fwrite(STDOUT, "\n\033[1;36m==> {$t}\033[0m\n"); }
function bene(string $t): void    { fwrite(STDOUT, "    \033[0;32m{$t}\033[0m\n"); }
function attento(string $t): void { fwrite(STDOUT, "    \033[0;33m{$t}\033[0m\n"); }
function muori(string $t): never  { fwrite(STDERR, "\n\033[1;31mERRORE: {$t}\033[0m\n"); exit(1); }

function avanza(string $eti, int $fatti, float $t0, bool $fine = false): void
{
    static $ultimo = 0.0;
    static $tty = null;
    $tty ??= function_exists('posix_isatty') && @posix_isatty(STDOUT);

    $ora = microtime(true);
    if (!$tty && !$fine) {
        return;
    }
    if (!$fine && $ora - $ultimo < 0.5) {
        return;
    }
    $ultimo = $ora;

    fwrite(STDOUT, sprintf(
        "\r    %-10s %s righe  %s/s  %ds   ",
        $eti,
        number_format($fatti, 0, ',', '.'),
        number_format((int) ($fatti / max(0.001, $ora - $t0)), 0, ',', '.'),
        (int) ($ora - $t0),
    ));
    if ($fine) {
        fwrite(STDOUT, "\n");
    }
}

Database::disponibile() || muori('Database non raggiungibile.');
$pdo = Database::pdo();

/**
 * Carica un CSV compresso dentro una tabella.
 *
 * @param callable(array<int,string>):?array<int,string> $riga  da riga CSV a valori SQL, o null per saltare
 */
function carica(string $file, string $tabella, int $colonne, callable $riga, string $eti, int $lotto): int
{
    $pdo = Database::pdo();
    $pdo->exec('TRUNCATE TABLE ' . $tabella);

    $f = gzopen($file, 'rb');
    $f !== false || muori("Non riesco ad aprire {$file}");

    $t0 = microtime(true);
    $fatti = 0;
    $gruppo = [];

    // Senza transazione ogni inserimento e' una scrittura sincrona sul disco:
    // con otto milioni di righe vuol dire ore invece di minuti.
    $pdo->beginTransaction();

    while (($linea = gzgets($f)) !== false) {
        $c = str_getcsv(rtrim($linea, "\r\n"), ',', '"', '');
        if (count($c) < $colonne) {
            continue;
        }

        $valori = $riga($c);
        if ($valori === null) {
            continue;
        }

        $gruppo[] = '(' . implode(',', $valori) . ')';

        if (count($gruppo) >= $lotto) {
            // INSERT IGNORE: i dump hanno qualche intervallo ripetuto, e una
            // chiave doppia non deve fermare un import da otto milioni di righe.
            $pdo->exec('INSERT IGNORE INTO ' . $tabella . ' VALUES ' . implode(',', $gruppo));
            $fatti += count($gruppo);
            $gruppo = [];
            avanza($eti, $fatti, $t0);

            if ($fatti % 100000 === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
        }
    }

    if ($gruppo !== []) {
        $pdo->exec('INSERT IGNORE INTO ' . $tabella . ' VALUES ' . implode(',', $gruppo));
        $fatti += count($gruppo);
    }

    $pdo->commit();
    gzclose($f);
    avanza($eti, $fatti, $t0, true);

    return $fatti;
}

/** Un indirizzo testuale come letterale binario SQL a sedici byte. */
function ipSql(string $ip): ?string
{
    $b = Rete::binario(trim($ip));

    return $b === null ? null : "UNHEX('" . bin2hex($b) . "')";
}

$t0 = microtime(true);

// --- ASN --------------------------------------------------------------------
$asn = $fonti . '/dbip-asn.csv.gz';
if (is_file($asn)) {
    titolo('Operatori di rete (ASN)');
    $n = carica($asn, 'geoip_asn', 4, static function (array $c) use ($pdo): ?array {
        $da = ipSql($c[0]);
        $a  = ipSql($c[1]);
        if ($da === null || $a === null) {
            return null;
        }

        return [$da, $a, (string) (int) $c[2], $pdo->quote(mb_substr($c[3], 0, 160))];
    }, 'ASN', $lotto);
    bene(number_format($n, 0, ',', '.') . ' intervalli');
} else {
    attento('dbip-asn.csv.gz assente: gli operatori non saranno disponibili');
}

// --- citta' -----------------------------------------------------------------
$city = $fonti . '/dbip-city.csv.gz';
if (is_file($city)) {
    titolo('Geolocalizzazione (citta\')');
    $n = carica($city, 'geoip_reti', 8, static function (array $c) use ($pdo): ?array {
        $da = ipSql($c[0]);
        $a  = ipSql($c[1]);
        if ($da === null || $a === null) {
            return null;
        }

        // «ZZ» e' il segnaposto di DB-IP per gli indirizzi non assegnati:
        // tenerli vorrebbe dire dire «paese ZZ» invece di «non si sa».
        if ($c[3] === 'ZZ' || $c[3] === '') {
            return null;
        }

        $lat = is_numeric($c[6]) ? (float) $c[6] : null;
        $lon = is_numeric($c[7]) ? (float) $c[7] : null;

        return [
            $da, $a,
            $pdo->quote(mb_substr($c[3], 0, 2)),
            $pdo->quote(mb_substr($c[4], 0, 80)),
            $pdo->quote(mb_substr($c[5], 0, 120)),
            $lat === null ? 'NULL' : sprintf('%.6f', $lat),
            $lon === null ? 'NULL' : sprintf('%.6f', $lon),
        ];
    }, 'citta\'', $lotto);
    bene(number_format($n, 0, ',', '.') . ' intervalli');
} else {
    attento('dbip-city.csv.gz assente: la geolocalizzazione non sara\' disponibile');
}

// --- verifica ---------------------------------------------------------------
titolo('Verifica');
foreach (['8.8.8.8' => 'US', '1.1.1.1' => null, '151.1.1.1' => 'IT'] as $ip => $attesoPaese) {
    $g = Rete::geolocalizza($ip);
    $o = Rete::operatore($ip);
    $riga = sprintf('%-16s %s', $ip, $g === null
        ? 'non localizzato'
        : trim($g['citta'] . ' ' . $g['regione'] . ' (' . $g['paese'] . ')'));
    if ($o !== null) {
        $riga .= '  —  AS' . $o['asn'] . ' ' . mb_substr($o['organizzazione'], 0, 34);
    }
    bene($riga);
}

$mb = (int) Database::valore(
    'SELECT ROUND(SUM(data_length+index_length)/1048576) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name LIKE ?', ['geoip%']
);
bene($mb . ' MB di tabelle');
bene((int) round(microtime(true) - $t0) . ' secondi in tutto');
fwrite(STDOUT, "\n");
