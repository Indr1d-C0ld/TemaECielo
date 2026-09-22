<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove del disegno.
 *
 *   php tests/test_grafica.php
 *
 * Un SVG generato male non si rompe: si disegna storto. Queste prove
 * controllano le proprieta' che a occhio non si verificano — che nessun glifo
 * si sovrapponga, che niente esca dalla cornice, che il file autonomo non
 * dipenda da nulla di esterno.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Motore;
use App\Core\Config;
use App\Grafica\GrigliaAspetti;
use App\Grafica\RuotaTema;
use App\Grafica\Svg;

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

echo "\n\033[1;36m══ Anti-collisione dei glifi ══\033[0m\n";

/** La distanza angolare minima fra due qualsiasi delle posizioni date. */
function minimaDistanza(array $g): float
{
    $m = 360.0;
    $n = count($g);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $d = abs($g[$i] - $g[$j]);
            if ($d > 180.0) { $d = 360.0 - $d; }
            $m = min($m, $d);
        }
    }

    return $m;
}

$collisioni = [
    ['quattro corpi in tredici gradi', [81.5, 88.7, 92.3, 94.1], 7.0, 7.0],
    ['tre esattamente sovrapposti',    [100.0, 100.0, 100.0],    7.0, 7.0],
    ['due a cavallo dello zero',       [358.0, 1.0],             7.0, 7.0],
    ['dieci gia\' ben distribuiti',    [10, 45, 80, 120, 160, 200, 240, 280, 310, 340], 7.0, 7.0],
    // Con dodici glifi da 31 gradi l'ingombro supera il giro: nessuna
    // disposizione li separa, e il massimo ottenibile e' 360/12.
    ['ingombro maggiore del giro',     array_map(static fn (int $i): float => 50.0 + $i * 0.5, range(0, 11)), 31.0, 30.0],
];

foreach ($collisioni as [$eti, $gradi, $minimo, $atteso]) {
    prova($eti, static function () use ($gradi, $minimo, $atteso) {
        $r = Svg::sciogli($gradi, $minimo);
        $d = minimaDistanza($r);

        return $d >= $atteso - 0.01
            ? true
            : sprintf('distanza minima %.2f°, attesa almeno %.2f°', $d, $atteso);
    });
}

prova('L\'ordine dei glifi non cambia mai', static function () {
    $g = [81.5, 88.7, 92.3, 94.1, 200.0, 250.5];
    $r = Svg::sciogli($g, 7.0);
    for ($i = 1; $i < count($g); $i++) {
        if ($r[$i] <= $r[$i - 1]) {
            return sprintf('posizione %d (%.2f) non segue la %d (%.2f)', $i, $r[$i], $i - 1, $r[$i - 1]);
        }
    }

    return true;
});

prova('Ogni glifo resta vicino al proprio grado vero', static function () {
    $g = [81.5, 88.7, 92.3, 94.1];
    $r = Svg::sciogli($g, 7.0);
    foreach ($g as $i => $v) {
        $d = abs($r[$i] - $v);
        if ($d > 180.0) { $d = 360.0 - $d; }
        // Quattro corpi in tredici gradi vanno distribuiti su ventuno: lo
        // scostamento non puo' superare la meta' dell'allargamento.
        if ($d > 10.0) {
            return sprintf('il glifo %d si e\' spostato di %.2f°', $i, $d);
        }
    }

    return true;
});

prova('Un solo glifo non viene toccato', static fn () => Svg::sciogli([123.4], 7.0) === [123.4] ?: 'e\' stato spostato');

echo "\n\033[1;36m══ Geometria ══\033[0m\n";

prova('L\'angolo zero sta a sinistra (dov\'e\' l\'Ascendente)', static function () {
    [$x, $y] = Svg::punto(100.0, 100.0, 50.0, 0.0);

    return (abs($x - 50.0) < .01 && abs($y - 100.0) < .01) ? true : "punto ({$x}, {$y})";
});

prova('Gli angoli crescono in senso antiorario', static function () {
    // A novanta gradi dall'Ascendente, in senso antiorario, si sta SOTTO il
    // centro: e' li' che cadono le case II e III.
    [, $y] = Svg::punto(100.0, 100.0, 50.0, 90.0);

    return $y > 100.0 ? true : "y = {$y}, atteso maggiore di 100";
});

prova('Punti opposti stanno da parti opposte', static function () {
    [$x1, $y1] = Svg::punto(0.0, 0.0, 10.0, 37.0);
    [$x2, $y2] = Svg::punto(0.0, 0.0, 10.0, 217.0);

    return (abs($x1 + $x2) < .02 && abs($y1 + $y2) < .02) ? true : "({$x1},{$y1}) e ({$x2},{$y2})";
});

echo "\n\033[1;36m══ La ruota ══\033[0m\n";

$carte = [
    'natale con ora esatta' => ['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 21.2333,
                                'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido'],
    'carta solare, ora ignota' => ['anno' => 1955, 'mese' => 6, 'giorno' => 15, 'ora_ut' => 11.0,
                                   'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122,
                                   'sistema_case' => 'segni_interi', 'ora_ignota' => true],
    'alta latitudine' => ['anno' => 1990, 'mese' => 1, 'giorno' => 15, 'ora_ut' => 3.0,
                          'lat' => 69.65, 'lon' => 18.96, 'alt' => 10, 'sistema_case' => 'segni_interi'],
];

$motore = new Motore();
$temi = [];
foreach ($carte as $nome => $d) {
    $temi[$nome] = $motore->tema($d);
}

foreach ($temi as $nome => $tema) {
    prova("SVG ben formato — {$nome}", static function () use ($tema) {
        $svg = (new RuotaTema($tema, true))->disegna();
        $prec = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($svg);
        $errori = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prec);

        if ($doc === false) {
            return 'XML non valido: ' . ($errori[0]->message ?? '?');
        }

        return true;
    });
}

prova('Ogni corpo della carta ha il suo glifo nella ruota', static function () use ($temi) {
    $tema = $temi['natale con ora esatta'];
    $svg = (new RuotaTema($tema))->disegna();
    foreach (array_keys($tema['corpi']) as $chiave) {
        if (!str_contains($svg, 'data-corpo="' . $chiave . '"')) {
            return "manca {$chiave}";
        }
    }

    return true;
});

prova('Ogni riferimento a un glifo trova il proprio simbolo', static function () use ($temi) {
    $svg = (new RuotaTema($temi['natale con ora esatta'], true))->disegna();

    preg_match_all('/href="#(gl-[a-z0-9-]+)"/', $svg, $usati);
    preg_match_all('/<symbol[^>]*id="(gl-[a-z0-9-]+)"/', $svg, $definiti);

    $mancanti = array_diff(array_unique($usati[1]), $definiti[1]);

    return $mancanti === [] ? true : 'simboli non definiti: ' . implode(', ', $mancanti);
});

prova('Il file autonomo non dipende da niente di esterno', static function () use ($temi) {
    $svg = (new RuotaTema($temi['natale con ora esatta'], true))->disegna();

    if (preg_match('/(href|src)="https?:/', $svg) === 1) {
        return 'contiene un riferimento remoto';
    }
    if (!str_contains($svg, '<style>')) {
        return 'manca lo stile incorporato: si scaricherebbe una ruota incolore';
    }
    if (!str_contains($svg, 'xmlns=')) {
        return 'manca lo spazio dei nomi: non si aprirebbe fuori da una pagina';
    }

    return true;
});

prova('Il file autonomo porta solo i glifi che usa', static function () use ($temi) {
    $svg = (new RuotaTema($temi['natale con ora esatta'], true))->disegna();
    preg_match_all('/<symbol[^>]*id="(gl-[a-z0-9-]+)"/', $svg, $definiti);
    preg_match_all('/href="#(gl-[a-z0-9-]+)"/', $svg, $usati);

    $inutili = array_diff($definiti[1], array_unique($usati[1]));

    // Il glifo del retrogrado si incorpora sempre: se un corpo e' retrogrado
    // serve, e deciderlo caso per caso non varrebbe il risparmio.
    $inutili = array_diff($inutili, ['gl-retrogrado']);

    return $inutili === [] ? true : 'incorporati senza servire: ' . implode(', ', $inutili);
});

prova('Niente esce dalla cornice', static function () use ($temi) {
    $svg = (new RuotaTema($temi['natale con ora esatta'], true))->disegna();

    // Ogni coordinata deve stare dentro il riquadro di 820 unita'.
    preg_match_all('/(?:^|\s)(?:x|y|cx|cy|x1|y1|x2|y2)="(-?[\d.]+)"/', $svg, $m);
    foreach ($m[1] as $v) {
        $f = (float) $v;
        if ($f < -40.0 || $f > 860.0) {
            return "coordinata fuori scala: {$f}";
        }
    }

    return true;
});

prova('Con l\'ora ignota case e assi sono attenuati', static function () use ($temi) {
    $svg = (new RuotaTema($temi['carta solare, ora ignota']))->disegna();

    if (!str_contains($svg, 'ora ignota')) {
        return 'manca la scritta al centro';
    }
    if (preg_match('/class="anello-case" opacity="\.35"/', $svg) !== 1) {
        return 'l\'anello delle case non e\' attenuato';
    }
    if (preg_match('/class="assi" opacity="\.3"/', $svg) !== 1) {
        return 'gli assi non sono attenuati';
    }

    return true;
});

prova('Le dodici cuspidi vengono tracciate', static function () use ($temi) {
    // Le cuspidi grezze devono restare nella carta composta: dimenticarle e'
    // gia' costato un anello di case che non veniva disegnato affatto.
    $tema = $temi['natale con ora esatta'];
    if (!isset($tema['case']['cuspidi']) || count($tema['case']['cuspidi']) !== 12) {
        return 'la carta composta non porta le dodici cuspidi';
    }
    $svg = (new RuotaTema($tema))->disegna();
    preg_match_all('/<text [^>]*>(I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII)<\/text>/', $svg, $m);

    return count($m[1]) === 12 ? true : count($m[1]) . ' numeri di casa su 12';
});

prova('Gli aspetti disegnati sono quelli fra corpi, non quelli agli assi', static function () use ($temi) {
    $tema = $temi['natale con ora esatta'];
    $svg = (new RuotaTema($tema))->disegna();

    $traCorpi = 0;
    foreach ($tema['aspetti']['elenco'] as $a) {
        if (isset($tema['corpi'][$a['a']], $tema['corpi'][$a['b']])) {
            $traCorpi++;
        }
    }

    $disegnati = substr_count($svg, 'class="aspetto"');

    return $disegnati === $traCorpi ? true : "{$disegnati} linee per {$traCorpi} aspetti fra corpi";
});

echo "\n\033[1;36m══ La griglia degli aspetti ══\033[0m\n";

prova('La scaletta e\' triangolare', static function () use ($temi) {
    $h = (new GrigliaAspetti($temi['natale con ora esatta']))->disegna();

    preg_match_all('#<tr>(.*?)</tr>#s', $h, $righe);
    foreach ($righe[1] as $i => $riga) {
        $celle = substr_count($riga, '<td');
        if ($celle !== $i) {
            return sprintf('la riga %d ha %d celle, attese %d', $i + 1, $celle, $i);
        }
    }

    return count($righe[1]) === 12 ? true : count($righe[1]) . ' righe, attese 12';
});

prova('Ogni cella piena corrisponde a un aspetto vero', static function () use ($temi) {
    $tema = $temi['natale con ora esatta'];
    $h = (new GrigliaAspetti($tema))->disegna();

    $nella = substr_count($h, 'scaletta-cella');

    $attesi = 0;
    $dentro = array_merge(\App\Astro\Corpi::dieci(), ['asc', 'mc']);
    foreach ($tema['aspetti']['elenco'] as $a) {
        if (in_array($a['a'], $dentro, true) && in_array($a['b'], $dentro, true)) {
            $attesi++;
        }
    }

    return $nella === $attesi ? true : "{$nella} celle per {$attesi} aspetti";
});

prova('Una carta senza aspetti produce una scaletta vuota ma valida', static function () {
    $finto = [
        'corpi' => ['sole' => ['lon' => 0.0, 'nome' => 'Sole', 'segno' => 0]],
        'punti' => [],
        'aspetti' => ['elenco' => []],
    ];
    $h = (new GrigliaAspetti($finto))->disegna();

    // Con un solo corpo non c'e' niente da incrociare: meglio niente che una
    // tabella di una cella.
    return $h === '' ? true : 'ha prodotto qualcosa: ' . mb_substr($h, 0, 60);
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
