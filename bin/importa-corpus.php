<?php

declare(strict_types=1);

/**
 * Tema e Cielo — importazione del corpus interpretativo.
 *
 *   php bin/importa-corpus.php [--sostituisci]
 *
 * Carica i semi di db/semi/corpus-*.php. Per impostazione NON sovrascrive i
 * testi gia' in tabella: l'admin li corregge dal pannello, e un import non
 * deve cancellargli il lavoro. Con --sostituisci si riallinea tutto ai semi.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Database;

$GLOBALS['__project_root'] = $radice;
Config::load($radice);
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$sostituisci = in_array('--sostituisci', $argv, true);

function titolo(string $t): void  { fwrite(STDOUT, "\n\033[1;36m==> {$t}\033[0m\n"); }
function bene(string $t): void    { fwrite(STDOUT, "    \033[0;32m{$t}\033[0m\n"); }
function attento(string $t): void { fwrite(STDOUT, "    \033[0;33m{$t}\033[0m\n"); }
function muori(string $t): never  { fwrite(STDERR, "\n\033[1;31mERRORE: {$t}\033[0m\n"); exit(1); }

Database::disponibile() || muori('Database non raggiungibile.');

titolo('Semi');
$semi = glob($radice . '/db/semi/corpus-*.php') ?: [];
$semi === [] && muori('Nessun file di semi in db/semi/');

$voci = [];
foreach ($semi as $file) {
    $dati = require $file;
    if (!is_array($dati)) {
        muori(basename($file) . ' non restituisce un array.');
    }
    bene(sprintf('%-28s %d voci', basename($file), count($dati)));
    foreach ($dati as $v) {
        $voci[] = $v;
    }
}

titolo('Controllo');

// Una voce doppia nei semi vorrebbe dire che due file si contraddicono, e
// l'ultimo caricato vincerebbe in silenzio.
$viste = [];
foreach ($voci as $i => $v) {
    if (count($v) < 6) {
        muori("La voce {$i} ha " . count($v) . ' campi: ne servono sei (ambito, chiave, registro, titolo, corpo, peso).');
    }
    [$ambito, $chiave, $registro] = $v;
    if (!in_array($registro, ['tradizionale', 'moderno'], true)) {
        muori("Registro sconosciuto in {$ambito}/{$chiave}: {$registro}");
    }
    $k = "{$ambito}/{$chiave}/{$registro}";
    if (isset($viste[$k])) {
        muori("Voce doppia nei semi: {$k}");
    }
    $viste[$k] = true;
}
bene(count($voci) . ' voci, nessuna doppia');

// I frammenti devono essere completi: se manca un solo segno, tutte le voci
// composte di quel segno spariscono senza che nulla lo segnali.
$attesi = [
    'pianeta'           => ['sole', 'luna', 'mercurio', 'venere', 'marte', 'giove', 'saturno', 'urano', 'nettuno', 'plutone'],
    'segno_modo'        => ['ariete', 'toro', 'gemelli', 'cancro', 'leone', 'vergine', 'bilancia',
                            'scorpione', 'sagittario', 'capricorno', 'acquario', 'pesci'],
    'casa_campo'        => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
    'aspetto_relazione' => ['congiunzione', 'opposizione', 'trigono', 'quadrato', 'sestile'],
];

$buchi = 0;
foreach ($attesi as $ambito => $chiavi) {
    foreach (['tradizionale', 'moderno'] as $reg) {
        foreach ($chiavi as $ch) {
            if (!isset($viste["{$ambito}/{$ch}/{$reg}"])) {
                attento("frammento mancante: {$ambito}/{$ch} [{$reg}]");
                $buchi++;
            }
        }
    }
}
$buchi === 0
    ? bene('i frammenti coprono tutti i segni, tutte le case, tutti gli aspetti maggiori')
    : attento("{$buchi} frammenti mancanti: le voci composte che li usano non usciranno");

// --- caricamento ------------------------------------------------------------
titolo($sostituisci ? 'Caricamento (sostituisce i testi esistenti)' : 'Caricamento (non tocca i testi esistenti)');

$sql = $sostituisci
    ? 'INSERT INTO testi (ambito, chiave, registro, titolo, corpo, peso, etichette, stato, creato, aggiornato)
       VALUES (?,?,?,?,?,?,?,\'pubblicato\',NOW(),NOW())
       ON DUPLICATE KEY UPDATE titolo=VALUES(titolo), corpo=VALUES(corpo), peso=VALUES(peso),
                               etichette=VALUES(etichette), aggiornato=NOW()'
    : 'INSERT IGNORE INTO testi (ambito, chiave, registro, titolo, corpo, peso, etichette, stato, creato, aggiornato)
       VALUES (?,?,?,?,?,?,?,\'pubblicato\',NOW(),NOW())';

$pdo = Database::pdo();
$pdo->beginTransaction();
$ins = $pdo->prepare($sql);

$nuove = 0;
foreach ($voci as $v) {
    [$ambito, $chiave, $registro, $titolo, $corpo, $peso] = $v;
    $etichette = $v[6] ?? '';

    $ins->execute([$ambito, $chiave, $registro, $titolo, $corpo, (int) $peso, $etichette]);
    $nuove += $ins->rowCount() > 0 ? 1 : 0;
}
$pdo->commit();

bene($nuove . ' voci scritte, ' . (count($voci) - $nuove) . ' gia' . "'" . ' presenti e lasciate stare');

// --- copertura --------------------------------------------------------------
titolo('Copertura');
foreach (['tradizionale', 'moderno'] as $reg) {
    $n = (int) Database::valore('SELECT COUNT(*) FROM testi WHERE registro = ?', [$reg]);
    $frammenti = (int) Database::valore(
        'SELECT COUNT(*) FROM testi WHERE registro = ? AND ambito IN (?,?,?,?)',
        [$reg, 'pianeta', 'segno_modo', 'casa_campo', 'aspetto_relazione'],
    );
    bene(sprintf('%-14s %3d voci  (%d frammenti, %d complete)', $reg, $n, $frammenti, $n - $frammenti));
}

$scritte = (int) Database::valore('SELECT COUNT(*) FROM testi WHERE ambito IN (?,?,?)',
    ['pianeta_segno', 'pianeta_casa', 'aspetto']);
bene($scritte . ' voci scritte a mano su ' . (465 * 2) . ' combinazioni possibili');
bene('le restanti si compongono dai frammenti: nessuna carta resta muta');
fwrite(STDOUT, "\n");
