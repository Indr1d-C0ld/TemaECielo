<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove del gazetteer.
 *
 *   php tests/test_luoghi.php
 *
 * Richiede il gazetteer gia' importato (bin/importa-luoghi.php).
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Luogo\Gazetteer;
use App\Luogo\Normalizza;

$GLOBALS['__project_root'] = dirname(__DIR__);
Config::load(dirname(__DIR__));

$passate = 0;
$fallite = 0;

function prova(string $titolo, callable $f): void
{
    global $passate, $fallite;
    try {
        $e = $f();
        if ($e === true) { $passate++; printf("  \033[0;32mOK \033[0m %s\n", $titolo); }
        else { $fallite++; printf("  \033[1;31mNO \033[0m %s\n        %s\n", $titolo, is_string($e) ? $e : 'falso'); }
    } catch (\Throwable $ex) {
        $fallite++; printf("  \033[1;31mERR\033[0m %s\n        %s\n", $titolo, $ex->getMessage());
    }
}

if (!Database::disponibile() || (int) Database::valore('SELECT COUNT(*) FROM luoghi') === 0) {
    exit("\n\033[1;33mGazetteer vuoto: esegui prima php bin/importa-luoghi.php\033[0m\n\n");
}

echo "\n\033[1;36m══ Normalizzazione dei nomi ══\033[0m\n";

$norme = [
    ['Città di Castello',  'citta di castello'],
    ['CITTÀ DI CASTELLO',  'citta di castello'],
    ["Sant'Agata de' Goti", 'sant agata de goti'],
    ['München',            'munchen'],
    ["  Reggio   Emilia  ", 'reggio emilia'],
];
foreach ($norme as [$in, $atteso]) {
    prova("«{$in}» → «{$atteso}»", static function () use ($in, $atteso) {
        $r = Normalizza::nome($in);

        return $r === $atteso ? true : "ottenuto «{$r}»";
    });
}

echo "\n\033[1;36m══ Ricerca per nome ══\033[0m\n";

/** Il primo risultato deve essere il luogo atteso. */
function primoE(string $query, string $nomeAtteso, ?string $paese = null): bool|string
{
    $r = Gazetteer::cerca($query, $paese);
    if ($r === []) {
        return 'nessun risultato';
    }

    return $r[0]['nome'] === $nomeAtteso
        ? true
        : sprintf('primo risultato «%s» (%s), atteso «%s»', $r[0]['nome'], $r[0]['contesto'], $nomeAtteso);
}

prova('«roma» trova Roma, non Rome in Georgia',        static fn () => primoE('roma', 'Roma'));
prova('«milano» trova Milano',                          static fn () => primoE('milano', 'Milano'));
prova('«napoli» trova Napoli, non Naples',              static fn () => primoE('napoli', 'Napoli'));
prova('«firenze» trova Firenze, non Florence',          static fn () => primoE('firenze', 'Firenze'));
prova('«londra» trova London (esonimo italiano)',       static fn () => primoE('londra', 'London'));
prova('«parigi» trova Paris',                           static fn () => primoE('parigi', 'Paris'));
prova('«citta di castello» senza accento funziona',     static fn () => primoE('citta di castello', 'Città di Castello'));
prova('«CITTÀ DI CASTELLO» maiuscolo funziona',         static fn () => primoE('CITTÀ DI CASTELLO', 'Città di Castello'));
prova('«reggio» filtrato su IT resta in Italia', static function () {
    foreach (Gazetteer::cerca('reggio', 'IT') as $r) {
        if ($r['paese'] !== 'IT') {
            return 'e\' uscito ' . $r['nome'] . ' (' . $r['paese'] . ')';
        }
    }

    return true;
});

prova('Sotto i tre caratteri non si cerca', static function () {
    // Con due caratteri il prefisso pesca decine di migliaia di alias e le
    // corrispondenze esatte su traslitterazioni oscure scavalcano le citta'.
    return Gazetteer::cerca('mi') === [] && Gazetteer::cerca('a') === []
        ? true
        : 'ha restituito risultati';
});

prova('Ogni risultato porta il fuso orario', static function () {
    foreach (Gazetteer::cerca('milano') as $r) {
        if ($r['fuso'] === '') {
            return $r['nome'] . ' senza fuso';
        }
        if (!\App\Luogo\Tempo::zonaValida($r['fuso'])) {
            return $r['nome'] . ' ha un fuso non valido: ' . $r['fuso'];
        }
    }

    return true;
});

prova('Quando il nome trovato differisce, viene mostrato', static function () {
    $r = Gazetteer::cerca('londra');

    return ($r[0]['trovato_come'] ?? null) === 'Londra'
        ? true
        : 'trovato_come = ' . var_export($r[0]['trovato_come'] ?? null, true);
});

prova('Quando coincide, non si ripete', static function () {
    $r = Gazetteer::cerca('milano');

    return ($r[0]['trovato_come'] ?? null) === null
        ? true
        : 'trovato_come = ' . var_export($r[0]['trovato_come'], true);
});

echo "\n\033[1;36m══ Copertura ══\033[0m\n";

prova('I piccoli comuni italiani ci sono (sotto i 500 abitanti)', static function () {
    $n = (int) Database::valore('SELECT COUNT(*) FROM luoghi WHERE paese = ? AND popolazione < 500', ['IT']);

    return $n > 30000 ? true : "solo {$n}: manca l'import di IT.txt";
});

prova('Le regioni italiane hanno il nome italiano', static function () {
    $r = Gazetteer::cerca('milano');
    $c = $r[0]['contesto'];

    return str_contains($c, 'Lombardia') && str_contains($c, 'Italia')
        ? true
        : "contesto: «{$c}»";
});

echo "\n\033[1;36m══ Il luogo di un punto sulla mappa ══\033[0m\n";

/**
 * Cliccando sul centro di una citta' deve uscire la citta', non il quartiere
 * in cui e' caduto il puntatore, e non la metropoli vicina.
 */
$click = [
    [41.9028,  12.4964, 'Roma',          'centro di Roma: non il rione Trevi'],
    [45.4642,   9.1900, 'Milano',        'centro di Milano'],
    [35.6812, 139.7671, 'Tokyo',         'stazione di Tokyo: non il quartiere Chūō'],
    [40.7128, -74.0060, 'New York City', 'Manhattan'],
    [51.5074,  -0.1278, 'London',        'Westminster'],
    [45.5845,   9.2744, 'Monza',         'Monza non viene inghiottita da Milano'],
    [45.6983,   9.6773, 'Bergamo',       'Bergamo resta Bergamo'],
];
foreach ($click as [$la, $lo, $atteso, $nota]) {
    prova($nota, static function () use ($la, $lo, $atteso) {
        $v = Gazetteer::piuVicino($la, $lo);
        if ($v === null) {
            return 'nessun luogo trovato';
        }

        return $v['nome'] === $atteso
            ? true
            : sprintf('«%s» a %.1f km (%s ab.), atteso «%s»',
                $v['nome'], $v['distanza_km'], number_format($v['popolazione']), $atteso);
    });
}

prova('In mezzo all\'oceano non si inventa un luogo', static function () {
    return Gazetteer::piuVicino(35.0, -40.0) === null
        ? true
        : 'ha restituito un luogo a centinaia di chilometri';
});

echo "\n\033[1;36m══ Fuso orario da coordinate ══\033[0m\n";

$fusi = [
    [45.4642,   9.1900, 'Europe/Rome',      'Milano'],
    [40.7128, -74.0060, 'America/New_York', 'New York'],
    [35.6812, 139.7671, 'Asia/Tokyo',       'Tokyo'],
    [-33.8688,151.2093, 'Australia/Sydney', 'Sydney'],
    [28.6139,  77.2090, 'Asia/Kolkata',     'Delhi'],
];
foreach ($fusi as [$la, $lo, $atteso, $che]) {
    prova("{$che} → {$atteso}", static function () use ($la, $lo, $atteso) {
        $f = Gazetteer::fusoDi($la, $lo);

        return $f['fuso'] === $atteso ? true : "ottenuto {$f['fuso']} ({$f['fonte']})";
    });
}

prova('In oceano si ripiega sul fuso nautico', static function () {
    $f = Gazetteer::fusoDi(35.0, -40.0);

    return $f['fonte'] === 'longitudine' && \App\Luogo\Tempo::zonaValida($f['fuso'])
        ? true
        : "fonte {$f['fonte']}, fuso {$f['fuso']}";
});

echo "\n\033[1;36m══ Tempi di risposta ══\033[0m\n";

/**
 * Il tempo migliore su tre esecuzioni. La prova vuole scoprire una ricerca
 * lenta per costruzione — un indice che manca, una scansione dell'intera
 * tabella — e quella resta lenta tutte e tre le volte. Una sola misura a freddo
 * falliva invece per ragioni che col codice non c'entrano: la cache del
 * database svuotata dal dump notturno, o un altro servizio che carica la
 * macchina.
 */
function migliore(callable $f): float
{
    $meglio = INF;
    for ($i = 0; $i < 3; $i++) {
        $t0 = microtime(true);
        $f();
        $meglio = min($meglio, (microtime(true) - $t0) * 1000);
    }

    return $meglio;
}

prova('Una ricerca per nome sta sotto i 150 ms', static function () {
    $peggio = 0.0;
    $lento = '';
    foreach (['roma', 'milano', 'sant agata', 'new york', 'reggio', 'firenze'] as $q) {
        $ms = migliore(static fn () => Gazetteer::cerca($q));
        if ($ms > $peggio) { $peggio = $ms; $lento = $q; }
    }

    return $peggio < 150.0 ? true : sprintf('«%s» ha impiegato %.0f ms', $lento, $peggio);
});

prova('Un click sulla mappa sta sotto i 150 ms', static function () {
    $peggio = 0.0;
    foreach ([[41.9, 12.5], [45.46, 9.19], [35.68, 139.77], [23.4, 25.1]] as [$la, $lo]) {
        $peggio = max($peggio, migliore(static fn () => Gazetteer::piuVicino($la, $lo)));
    }

    return $peggio < 150.0 ? true : sprintf('%.0f ms nel caso peggiore', $peggio);
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
