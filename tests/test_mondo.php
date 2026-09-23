<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove dell'astrologia mondiale.
 *
 *   php tests/test_mondo.php
 *
 * Il motore contro istanti pubblicati: equinozi e solstizi, eclissi con la
 * loro serie di Saros, le grandi congiunzioni. Poi le parole: i nomi delle
 * eclissi, le congiunzioni triple raccolte in una, i minimi dell'indice.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Sweph;
use App\Core\Config;
use App\Corpus\Mondana;
use App\Mondo\Cicli;
use App\Mondo\Eclissi;
use App\Mondo\Mondo;

$radice = dirname(__DIR__);
$GLOBALS['__project_root'] = $radice;
Config::load($radice);

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

function worker(array $domanda): array
{
    $p = proc_open(['php', dirname(__DIR__) . '/bin/effemeridi.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $t, dirname(__DIR__));
    fwrite($t[0], (string) json_encode($domanda));
    fclose($t[0]);
    $o = json_decode((string) stream_get_contents($t[1]), true);
    proc_close($p);

    return is_array($o) ? $o : [];
}

/** «2026-03-20 14:46» */
function ut(float $jd): string
{
    return gmdate('Y-m-d H:i', Mondo::unix($jd));
}

/** Differenza in minuti fra un giorno giuliano e un'ora UT scritta. */
function scarto(float $jd, string $atteso): float
{
    return abs(Mondo::unix($jd) - (int) strtotime($atteso . ' UTC')) / 60.0;
}

echo "\n== L'anno ==\n";

$anno = worker(['operazione' => 'anno', 'anno' => 2026]);

prova('Equinozi e solstizi del 2026 entro due minuti dai valori pubblicati', static function () use ($anno) {
    // USNO: 20/3 14:46, 21/6 08:24, 23/9 00:05, 21/12 20:50 UT.
    $attesi = ['2026-03-20 14:46', '2026-06-21 08:24', '2026-09-23 00:05', '2026-12-21 20:50'];
    foreach ($anno['ingressi'] ?? [] as $i => $x) {
        if (scarto((float) $x['jd'], $attesi[$i]) > 2) {
            return ut((float) $x['jd']) . " invece di {$attesi[$i]}";
        }
    }
    return count($anno['ingressi'] ?? []) === 4 ?: 'ingressi: ' . count($anno['ingressi'] ?? []);
});

prova('Nel 2026 ci sono 25 lunazioni, alternate', static function () use ($anno) {
    $l = $anno['lunazioni'] ?? [];
    foreach ($l as $i => $x) {
        if ($i > 0 && $x['tipo'] === $l[$i - 1]['tipo']) {
            return 'due ' . $x['tipo'] . ' di fila: ' . ut((float) $x['jd']);
        }
    }
    return count($l) === 25 ?: 'lunazioni: ' . count($l);
});

prova('Il plenilunio del 3 gennaio 2026 alle 10:03 UT', static function () use ($anno) {
    $p = $anno['lunazioni'][0];
    return ($p['tipo'] === 'plenilunio' && scarto((float) $p['jd'], '2026-01-03 10:03') <= 2) ?: ut((float) $p['jd']);
});

echo "\n== Le eclissi ==\n";

$ecl = worker(['operazione' => 'eclissi', 'jd_da' => Mondo::jdAnno(2026), 'jd_a' => Mondo::jdAnno(2027),
               'lat' => 40.4165, 'lon' => -3.70256, 'alt' => 667]);
$per = static fn (string $data) => array_values(array_filter($ecl['eclissi'] ?? [], static fn (array $e): bool => gmdate('Y-m-d', Mondo::unix((float) $e['jd'])) === $data))[0] ?? null;

prova('Quattro eclissi nel 2026', static fn () => count($ecl['eclissi'] ?? []) === 4 ?: (string) count($ecl['eclissi'] ?? []));

prova('12/8/2026: totale di Sole, Saros 126, massima nell\'Artico', static function () use ($per) {
    $e = $per('2026-08-12');
    return ($e !== null && Eclissi::genere((int) $e['tipo'], 'sole') === 'totale' && $e['saros'] === 126
        && $e['lat'] > 60 && scarto((float) $e['jd'], '2026-08-12 17:46') <= 3) ?: json_encode($e);
});

prova('...e da Madrid si vede, col Sole sopra l\'orizzonte', static function () use ($per) {
    $l = $per('2026-08-12')['locale'] ?? null;
    return ($l !== null && $l['altezza'] > 0 && $l['oscuramento'] > 0.99) ?: json_encode($l);
});

prova('17/2/2026 anulare; 3/3/2026 totale di Luna', static function () use ($per) {
    $a = $per('2026-02-17');
    $b = $per('2026-03-03');
    return ($a !== null && Eclissi::genere((int) $a['tipo'], 'sole') === 'anulare'
        && $b !== null && $b['corpo'] === 'luna' && Eclissi::genere((int) $b['tipo'], 'luna') === 'totale') ?: json_encode([$a, $b]);
});

prova('Il grado dell\'eclissi e\' quello del Sole: 19°-20° del Leone il 12/8/2026', static function () use ($per) {
    $lon = (float) $per('2026-08-12')['lon_eclittica'];
    return ($lon > 139 && $lon < 140.5) ?: (string) $lon;
});

prova('Il nome delle eclissi dalle bandiere della libreria', static function () {
    $casi = [
        [Sweph::ECL_CENTRALE | Sweph::ECL_TOTALE, 'totale'], [Sweph::ECL_CENTRALE | Sweph::ECL_ANULARE, 'anulare'],
        [Sweph::ECL_IBRIDA | Sweph::ECL_CENTRALE, 'ibrida'], [Sweph::ECL_PARZIALE, 'parziale'], [Sweph::ECL_PENOMBRALE, 'di penombra'],
    ];
    foreach ($casi as [$t, $atteso]) {
        if (Eclissi::genere($t, 'sole') !== $atteso) { return "{$t} → " . Eclissi::genere($t, 'sole'); }
    }
    return true;
});

prova('Un\'eclissi di penombra si misura con la magnitudine di penombra', static fn () =>
    Eclissi::magnitudine(-0.1, 0.896) === 'magnitudine di penombra 0,896' ?: Eclissi::magnitudine(-0.1, 0.896));

echo "\n== I cicli ==\n";

$cicli = worker(['operazione' => 'cicli', 'jd_da' => Mondo::jdAnno(1800), 'jd_a' => Mondo::jdAnno(2400), 'passo' => 10]);

prova('La grande congiunzione del 21/12/2020 a 0°29\' dell\'Acquario', static function () use ($cicli) {
    foreach (Cicli::passaggi($cicli['congiunzioni'] ?? [], 'giove-saturno') as $p) {
        if (gmdate('Y', Mondo::unix($p['jd'])) === '2020') {
            return (scarto($p['jd'], '2020-12-21 18:20') < 30 && abs($p['lon'] - 300.49) < 0.05) ?: ut($p['jd']) . ' ' . $p['lon'];
        }
    }
    return 'non trovata';
});

prova('Giove-Saturno: 30 congiunzioni in sei secoli, la tripla del 1940-41 contata una volta', static function () use ($cicli) {
    $p = Cicli::passaggi($cicli['congiunzioni'] ?? [], 'giove-saturno');
    $tripla = array_values(array_filter($p, static fn (array $x): bool => gmdate('Y', Mondo::unix($x['jd'])) === '1940'))[0] ?? null;
    return (count($p) >= 29 && count($p) <= 31 && $tripla !== null && count($tripla['passaggi']) === 3) ?: count($p) . ' ' . json_encode($tripla);
});

prova('Le mutazioni come le intende la tradizione: 2020 in aria; 1980 e 2000 isolate; 1842 no', static function () use ($cicli) {
    $m = [];
    foreach (Cicli::passaggi($cicli['congiunzioni'] ?? [], 'giove-saturno') as $p) {
        $m[gmdate('Y', Mondo::unix($p['jd']))] = [$p['elemento'], $p['mutazione'], $p['fuori_serie']];
    }
    // Il 1980-81 in Bilancia anticipa l'aria, il 2000 in Toro torna alla
    // terra: nessuno dei due apre una serie. La serie d'aria comincia nel 2020.
    return (($m['2020'] ?? null) === ['aria', true, false] && ($m['1980'] ?? null) === ['aria', false, true]
        && ($m['2000'] ?? null) === ['terra', false, true] && ($m['1842'] ?? null) === ['terra', false, false]
        && ($m['1802'] ?? null) === ['terra', false, false]) ?: json_encode($m);
});

prova('Saturno-Plutone il 12/1/2020, Nettuno-Plutone nel 1891-92', static function () use ($cicli) {
    $sp = array_map(static fn (array $p): string => gmdate('Y-m-d', Mondo::unix($p['jd'])), Cicli::passaggi($cicli['congiunzioni'] ?? [], 'saturno-plutone'));
    $np = array_map(static fn (array $p): string => gmdate('Y', Mondo::unix($p['jd'])), Cicli::passaggi($cicli['congiunzioni'] ?? [], 'nettuno-plutone'));
    return (in_array('2020-01-12', $sp, true) && in_array($np[0] ?? '', ['1891', '1892'], true)) ?: json_encode([$sp, $np]);
});

prova('L\'indice ciclico: un valore al mese, fra 200 e 1200 gradi', static function () use ($cicli) {
    $v = array_column($cicli['indice'] ?? [], 1);
    return (count($v) > 7000 && min($v) > 200 && max($v) < 1200) ?: count($v) . ' ' . min($v) . ' ' . max($v);
});

prova('Fra i minimi dell\'indice ci sono il 1943 e il 1983', static function () use ($cicli) {
    $anni = array_map(static fn (array $m): string => gmdate('Y', Mondo::unix($m[0])), Cicli::minimi($cicli['indice'] ?? [], 12.0));
    return (in_array('1943', $anni, true) && in_array('1983', $anni, true)) ?: implode(',', $anni);
});

prova('Minimi e interpolazione su una serie nota', static function () {
    $serie = [];
    for ($i = 0; $i < 400; $i++) { $serie[] = [(float) $i * 30, 500 + 300 * cos($i / 400 * 4 * M_PI)]; }
    $min = Cicli::minimi($serie, 1.0);
    $v = Cicli::valore([[0.0, 10.0], [10.0, 20.0]], 2.5);
    return (count($min) === 2 && abs($v - 12.5) < 1e-9) ?: count($min) . ' ' . $v;
});

echo "\n== Il resto ==\n";

prova('Un luogo sconosciuto e\' Roma; una capitale e\' se stessa', static function () {
    return (Mondo::luogo('atlantide')['nome'] === 'Roma' && Mondo::luogo('tokyo')['fuso'] === 'Asia/Tokyo') ?: 'no';
});

prova('Il giorno giuliano del 1° gennaio 2000 a mezzanotte e\' 2451544,5', static fn () =>
    abs(Mondo::jdAnno(2000) - 2451544.5) < 1e-9 ?: (string) Mondo::jdAnno(2000));

prova('La carta d\'ingresso parla di stagione, non di paese «nato»', static function () {
    $tema = ['carta' => ['ora_ignota' => false], 'punti' => ['asc' => ['lon' => 5.0, 'segno' => 0], 'mc' => ['lon' => 280.0, 'segno' => 9]],
             'corpi' => ['sole' => ['nome' => 'Sole', 'lon' => 0.0, 'segno' => 0, 'casa' => 12]], 'aspetti' => ['elenco' => []]];
    $m = Mondana::monta($tema, 'ingresso');
    $tutto = json_encode($m, JSON_UNESCAPED_UNICODE);
    return (str_contains($tutto, 'nella stagione che si apre') && str_contains($m['introduzione'], 'ingresso')
        && $m['sezioni'][1]['voci'][0]['perche'] === 'angolare') ?: $tutto;
});

printf(
    "\n%s%d passate, %d fallite\033[0m\n\n",
    $fallite === 0 ? "\033[0;32m" : "\033[1;31m",
    $passate,
    $fallite,
);

exit($fallite === 0 ? 0 : 1);
