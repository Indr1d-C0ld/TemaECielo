<?php

declare(strict_types=1);

/**
 * Tema e Cielo — console di servizio.
 *
 *   php bin/console.php stato            stato di configurazione, database, Swiss Ephemeris
 *   php bin/console.php migra            applica le migrazioni pendenti
 *   php bin/console.php migra:stato      elenca migrazioni applicate e pendenti
 *   php bin/console.php admin:password   crea o cambia la password dell'amministratore
 *   php bin/console.php admin:esiste     esce con 0 se esiste gia' un amministratore
 *   php bin/console.php partizioni       aggiunge le partizioni mensili mancanti ad `accessi`
 *   php bin/console.php astro            stato del motore astronomico
 *   php bin/console.php astro:prova      calcola una carta di prova e la stampa
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Questa console si usa solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Migrazioni;

$GLOBALS['__project_root'] = $radice;

function scrivi(string $t = ''): void { fwrite(STDOUT, $t . "\n"); }
function titolo(string $t): void      { fwrite(STDOUT, "\n\033[1;36m==> {$t}\033[0m\n"); }
function bene(string $t): void        { fwrite(STDOUT, "    \033[0;32m{$t}\033[0m\n"); }
function attento(string $t): void     { fwrite(STDOUT, "    \033[0;33m{$t}\033[0m\n"); }
function male(string $t): int         { fwrite(STDERR, "\n\033[1;31mERRORE: {$t}\033[0m\n"); return 1; }

/** Legge una password da tastiera senza mostrarla. */
function chiediSegreto(string $invito): string
{
    fwrite(STDOUT, $invito);
    if (function_exists('shell_exec') && stripos(PHP_OS_FAMILY, 'win') === false) {
        @shell_exec('stty -echo 2>/dev/null');
    }
    $v = rtrim((string) fgets(STDIN), "\r\n");
    if (function_exists('shell_exec') && stripos(PHP_OS_FAMILY, 'win') === false) {
        @shell_exec('stty echo 2>/dev/null');
    }
    fwrite(STDOUT, "\n");

    return $v;
}

function chiedi(string $invito, string $predefinito = ''): string
{
    fwrite(STDOUT, $invito);
    $v = rtrim((string) fgets(STDIN), "\r\n");

    return $v === '' ? $predefinito : $v;
}

try {
    Config::load($radice);
} catch (\Throwable $e) {
    exit(male($e->getMessage()));
}
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$comando = $argv[1] ?? 'aiuto';

exit(match ($comando) {
    'stato'          => cmdStato($radice),
    'migra'          => cmdMigra($radice),
    'migra:stato'    => cmdMigraStato($radice),
    'admin:password' => cmdAdminPassword(),
    'admin:esiste'   => cmdAdminEsiste(),
    'partizioni'     => cmdPartizioni(),
    'cache:purga'    => cmdCachePurga($argv),
    'astro'          => cmdAstro(),
    'astro:prova'    => cmdAstroProva($argv),
    default          => cmdAiuto(),
});

// ---------------------------------------------------------------------------

function cmdAiuto(): int
{
    scrivi("Tema e Cielo — console\n");
    scrivi("  stato            configurazione, database, Swiss Ephemeris");
    scrivi("  migra            applica le migrazioni pendenti");
    scrivi("  migra:stato      elenca migrazioni applicate e pendenti");
    scrivi("  admin:password   crea o cambia la password dell'amministratore");
    scrivi("  admin:esiste     esce con 0 se un amministratore esiste gia'");
    scrivi("  partizioni       aggiunge le partizioni mensili mancanti ad `accessi`");
    scrivi("  cache:purga      butta la cache del motore   [giorni, 0 = tutta]");
    scrivi("  astro            stato del motore astronomico");
    scrivi("  astro:prova      calcola una carta di prova   [AAAA-MM-GG HH:MM lat lon]");
    scrivi('');

    return 0;
}

/**
 * Butta la cache del motore. I permalink non si toccano.
 *
 * Senza argomento tiene quello che qualcuno ha richiesto negli ultimi trenta
 * giorni. Con «0» svuota tutto: serve dopo aver alzato `Motore::VERSIONE`,
 * quando le righe vecchie hanno una struttura che il portale non sa piu'
 * leggere e tenerle e' solo peso.
 */
function cmdCachePurga(array $argv): int
{
    $giorni = isset($argv[2]) && is_numeric($argv[2]) ? max(0, (int) $argv[2]) : 30;

    titolo('Cache del motore');

    $prima = (int) \App\Core\Database::valore('SELECT COUNT(*) FROM calcoli WHERE gettone IS NULL');
    $permalink = (int) \App\Core\Database::valore('SELECT COUNT(*) FROM calcoli WHERE gettone IS NOT NULL');

    $tolte = \App\Astro\Motore::purgaCache($giorni);

    bene(sprintf('righe di cache prima:  %d', $prima));
    bene(sprintf('tolte:                 %d%s', $tolte,
        $giorni > 0 ? "  (non richieste da oltre {$giorni} giorni)" : '  (tutte)'));
    bene(sprintf('righe di cache dopo:   %d', $prima - $tolte));
    bene(sprintf('permalink intatti:     %d', $permalink));

    return 0;
}

function cmdStato(string $radice): int
{
    titolo('Configurazione');
    bene('file      ' . Config::origine());
    bene('ambiente  ' . (string) Config::get('app.env') . (Config::get('app.debug') ? '  (debug attivo)' : ''));
    bene('indirizzo ' . (string) Config::get('app.url_pubblico'));

    titolo('Database');
    if (!Database::disponibile()) {
        attento('non raggiungibile');
    } else {
        $ver = (string) Database::valore('SELECT VERSION()');
        bene('MariaDB ' . $ver . '  —  ' . (string) Config::get('db.name'));
        $m = new Migrazioni($radice . '/db/migrazioni');
        $pend = $m->pendenti();
        bene(count($m->applicate()) . ' migrazioni applicate, ' . count($pend) . ' pendenti');
        foreach ($pend as $p) {
            attento('pendente: ' . $p);
        }
        $n = (int) Database::valore("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        bene($n . ' tabelle');
    }

    titolo('Swiss Ephemeris');
    $lib = (string) Config::get('astro.libswe');
    $eph = (string) Config::get('astro.effemeridi');
    is_file($lib) ? bene('libreria   ' . $lib) : attento('libreria NON trovata: ' . $lib);
    $se1 = is_dir($eph) ? (glob($eph . '/*.se1') ?: []) : [];
    $se1 !== []
        ? bene('effemeridi ' . $eph . '  (' . count($se1) . ' file .se1)')
        : attento('effemeridi assenti in ' . $eph . ': si ripieghera\' su Moshier');
    bene('FFI in CLI ' . (extension_loaded('FFI') ? 'disponibile' : 'ASSENTE'));

    scrivi('');

    return 0;
}

function cmdMigraStato(string $radice): int
{
    $m = new Migrazioni($radice . '/db/migrazioni');
    $applicate = $m->applicate();

    titolo('Migrazioni');
    foreach ($m->disponibili() as $nome) {
        in_array($nome, $applicate, true) ? bene('[x] ' . $nome) : attento('[ ] ' . $nome);
    }
    scrivi('');

    return 0;
}

function cmdMigra(string $radice): int
{
    $m = new Migrazioni($radice . '/db/migrazioni');
    $pend = $m->pendenti();

    if ($pend === []) {
        titolo('Migrazioni');
        bene('niente da applicare.');
        return 0;
    }

    titolo('Migrazioni — ' . count($pend) . ' da applicare');
    try {
        $m->applica(static function (string $nome, string $tempo): void {
            bene($nome . '   ' . $tempo);
        });
    } catch (\Throwable $e) {
        return male($e->getMessage());
    }

    return 0;
}

function cmdAdminEsiste(): int
{
    if (!Database::disponibile()) {
        return 1;
    }
    try {
        $n = (int) Database::valore('SELECT COUNT(*) FROM amministratori WHERE attivo = 1');
    } catch (\Throwable) {
        return 1;
    }

    return $n > 0 ? 0 : 1;
}

function cmdAdminPassword(): int
{
    titolo('Amministratore');

    $esistente = Database::riga('SELECT id, utente FROM amministratori ORDER BY id LIMIT 1');
    $utente = $esistente !== null
        ? (string) $esistente['utente']
        : chiedi('    Nome utente [admin]: ', 'admin');

    if ($esistente !== null) {
        bene('account esistente: ' . $utente . '  (cambio password)');
    }

    $p1 = chiediSegreto('    Password: ');
    if (strlen($p1) < 8) {
        return male('La password deve avere almeno 8 caratteri.');
    }
    $p2 = chiediSegreto('    Ripeti:   ');
    if (!hash_equals($p1, $p2)) {
        return male('Le due password non coincidono.');
    }

    $hash = Auth::hash($p1);
    sodium_memzero($p1);
    sodium_memzero($p2);

    if ($esistente !== null) {
        Database::esegui(
            'UPDATE amministratori SET password_hash = ?, attivo = 1 WHERE id = ?',
            [$hash, (int) $esistente['id']],
        );
    } else {
        Database::esegui(
            'INSERT INTO amministratori (utente, password_hash, attivo, creato) VALUES (?, ?, 1, NOW())',
            [$utente, $hash],
        );
    }

    Database::esegui(
        'INSERT INTO admin_registro (quando, admin_id, admin_utente, azione, oggetto, dettaglio)
         VALUES (NOW(), NULL, ?, ?, ?, ?)',
        [$utente, 'admin:password', $utente, 'impostata da console'],
    );

    bene('fatto. La password non e\' stata scritta in nessun file: resta solo l\'hash Argon2id.');
    scrivi('');

    return 0;
}

/**
 * Aggiunge ad `accessi` le partizioni mensili mancanti, fino a dodici mesi avanti.
 *
 * pMAX esiste per non perdere mai una riga, ma una partizione MAXVALUE non si
 * puo' «estendere»: si riorganizza, staccando da lei i mesi nuovi. E' il motivo
 * per cui questo comando fa REORGANIZE e non ADD.
 */
function cmdPartizioni(): int
{
    titolo('Partizioni di `accessi`');

    $db = (string) Config::get('db.name');
    $presenti = array_map(
        static fn (array $r): string => (string) $r['PARTITION_NAME'],
        Database::righe(
            'SELECT PARTITION_NAME FROM information_schema.PARTITIONS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
            [$db, 'accessi'],
        ),
    );

    if ($presenti === []) {
        return male('La tabella `accessi` non risulta partizionata.');
    }

    $nuove = [];
    $mese  = new DateTimeImmutable('first day of this month 00:00:00');
    for ($i = 0; $i < 12; $i++) {
        $mese = $mese->modify('+1 month');
        $nome = 'p' . $mese->format('Y_m');
        if (in_array($nome, $presenti, true)) {
            continue;
        }
        $nuove[$nome] = $mese->modify('+1 month')->format('Y-m-01');
    }

    if ($nuove === []) {
        bene('nessuna partizione da aggiungere.');
        scrivi('');
        return 0;
    }

    $pezzi = [];
    foreach ($nuove as $nome => $limite) {
        $pezzi[] = sprintf("PARTITION %s VALUES LESS THAN ('%s')", $nome, $limite);
    }
    $pezzi[] = 'PARTITION pMAX VALUES LESS THAN (MAXVALUE)';

    $sql = 'ALTER TABLE accessi REORGANIZE PARTITION pMAX INTO (' . implode(', ', $pezzi) . ')';

    try {
        Database::pdo()->exec($sql);
    } catch (\Throwable $e) {
        return male($e->getMessage());
    }

    foreach (array_keys($nuove) as $nome) {
        bene('aggiunta  ' . $nome);
    }
    scrivi('');

    return 0;
}

function cmdAstro(): int
{
    titolo('Motore astronomico');
    try {
        $m = new \App\Astro\Motore();
        $s = $m->stato();
        bene('Swiss Ephemeris ' . $s['versione']);
        bene('effemeridi: ' . ($s['con_file'] ? 'file .se1' : 'analitiche (Moshier)'));
        bene('risposta in ' . $s['durata_ms'] . ' ms');
    } catch (\Throwable $e) {
        return male($e->getMessage());
    }
    scrivi('');

    return 0;
}

/** @param list<string> $argv */
function cmdAstroProva(array $argv): int
{
    $data = $argv[2] ?? '1978-06-12';
    $ora  = $argv[3] ?? '21:14';
    $lat  = (float) ($argv[4] ?? 45.4642);
    $lon  = (float) ($argv[5] ?? 9.19);

    [$A, $M, $G] = array_map('intval', explode('-', $data));
    [$h, $min]   = array_map('intval', explode(':', $ora));

    titolo(sprintf('Carta di prova — %s %s UT a %.4f / %.4f', $data, $ora, $lat, $lon));

    try {
        $m = new \App\Astro\Motore();
        $t = $m->tema([
            'anno' => $A, 'mese' => $M, 'giorno' => $G, 'ora_ut' => $h + $min / 60,
            'lat' => $lat, 'lon' => $lon, 'alt' => 0, 'sistema_case' => 'placido',
        ]);
    } catch (\Throwable $e) {
        return male($e->getMessage());
    }

    $rom = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    bene(sprintf('%s — carta %s — %s ms%s',
        $t['carta']['sistema_nome'],
        $t['carta']['diurna'] ? 'diurna' : 'notturna',
        (string) ($t['meta']['durata_ms'] ?? '?'),
        ($t['meta']['da_cache'] ?? false) ? ' (dalla cache)' : ''));
    scrivi('');

    foreach ($t['corpi'] as $c) {
        scrivi(sprintf('    %-10s %-22s casa %-5s %s',
            $c['nome'], $c['posizione'] . ($c['retrogrado'] ? ' R' : ''),
            $rom[$c['casa']],
            isset($c['punteggio_totale']) ? sprintf('%+d', $c['punteggio_totale']) : ''));
    }
    scrivi('');
    foreach (['asc', 'mc', 'fortuna'] as $p) {
        if (isset($t['punti'][$p])) {
            scrivi(sprintf('    %-18s %s', $t['punti'][$p]['nome'], $t['punti'][$p]['posizione']));
        }
    }
    scrivi('');
    bene(sprintf('%d aspetti, %d configurazioni, figura: %s',
        $t['aspetti']['conteggio']['totale'],
        count($t['configurazioni']),
        $t['bilanci']['figura']['nome']));
    scrivi('');

    return 0;
}
