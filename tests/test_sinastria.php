<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove della sinastria, dei transiti e della doppia ruota.
 *
 *   php tests/test_sinastria.php
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Corpi;
use App\Astro\Motore;
use App\Astro\Sinastria;
use App\Core\Config;
use App\Corpus\Corpus;
use App\Grafica\RuotaTema;

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

echo "\n\033[1;36m══ Punti medi ══\033[0m\n";

// Il punto medio va preso sull'arco PIU' CORTO. Fra 350 e 10 gradi e' a zero,
// non a 180: la media aritmetica darebbe il punto opposto del cielo.
$medi = [
    [350.0, 10.0, 0.0,   'a cavallo dello zero'],
    [10.0,  350.0, 0.0,  'lo stesso, invertito'],
    [359.0, 1.0,  0.0,   'un grado per parte'],
    [100.0, 120.0, 110.0,'caso banale'],
    [0.0,   180.0, 90.0, 'opposti esatti'],
    [45.0,  45.0,  45.0, 'coincidenti'],
];
foreach ($medi as [$a, $b, $atteso, $eti]) {
    prova(sprintf('%.0f° e %.0f° → %.0f° (%s)', $a, $b, $atteso, $eti), static function () use ($a, $b, $atteso) {
        $r = Sinastria::puntoMedio($a, $b);
        $d = min(abs($r - $atteso), abs($r - $atteso - 360.0), abs($r - $atteso + 360.0));

        return $d < 0.001 ? true : sprintf('ottenuto %.4f°', $r);
    });
}

prova('Il punto medio e\' simmetrico', static function () {
    for ($i = 0; $i < 360; $i += 17) {
        for ($j = 0; $j < 360; $j += 23) {
            $x = Sinastria::puntoMedio((float) $i, (float) $j);
            $y = Sinastria::puntoMedio((float) $j, (float) $i);
            // Fra due punti esattamente opposti i punti medi sono due, e
            // scambiare l'ordine da' l'altro: e' geometria, non un difetto.
            if (abs(Corpi::distanza($x, $y)) > 0.001 && abs(Corpi::distanza((float) $i, (float) $j) - 180.0) > 0.001) {
                return "{$i}/{$j}: {$x} contro {$y}";
            }
        }
    }

    return true;
});

echo "\n\033[1;36m══ Luogo medio ══\033[0m\n";

prova('Fra Milano e Roma cade in mezzo', static function () {
    [$lat, $lon] = Sinastria::luogoMedio(45.4642, 9.1895, 41.8919, 12.5113);

    return ($lat > 43.0 && $lat < 44.5 && $lon > 10.0 && $lon < 12.0)
        ? true : sprintf('%.3f, %.3f', $lat, $lon);
});

prova('Sull\'antimeridiano non salta a Greenwich', static function () {
    // La media aritmetica di 179 e -179 fa zero: il meridiano di Greenwich
    // invece del Pacifico, mezzo mondo di distanza.
    [$lat, $lon] = Sinastria::luogoMedio(0.0, 179.0, 0.0, -179.0);

    return abs(abs($lon) - 180.0) < 0.001
        ? true : sprintf('longitudine %.3f, attesa ±180', $lon);
});

echo "\n\033[1;36m══ Aspetti incrociati ══\033[0m\n";

$motore = new Motore();
$A = $motore->tema(['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 21.2333,
                    'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido']);
$B = $motore->tema(['anno' => 1982, 'mese' => 11, 'giorno' => 3, 'ora_ut' => 7.5,
                    'lat' => 41.89193, 'lon' => 12.51133, 'alt' => 20, 'sistema_case' => 'placido']);
$Ignota = $motore->tema(['anno' => 1955, 'mese' => 6, 'giorno' => 15, 'ora_ut' => 11.0,
                         'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122,
                         'sistema_case' => 'segni_interi', 'ora_ignota' => true]);

prova('Ogni aspetto incrocia le due carte, nessuno resta interno', static function () use ($A, $B) {
    foreach (Sinastria::aspettiIncrociati($A, $B) as $x) {
        // Un aspetto «Sole di A con Luna di A» sarebbe un aspetto natale
        // finito per sbaglio nella sinastria.
        if (!isset($A['corpi'][$x['a']], $B['corpi'][$x['b']])
            && !isset($A['punti'][$x['a']], $B['punti'][$x['b']])
            && !isset($A['corpi'][$x['a']], $B['punti'][$x['b']])
            && !isset($A['punti'][$x['a']], $B['corpi'][$x['b']])) {
            return "{$x['a']}/{$x['b']} non incrocia le due carte";
        }
    }

    return true;
});

prova('Gli orbi sono piu\' stretti che in una carta singola', static function () use ($A, $B) {
    // Fra due carte le coppie sono cento invece di quarantacinque: con gli
    // orbi normali uscirebbero ottanta aspetti che non direbbero piu' niente.
    foreach (Sinastria::aspettiIncrociati($A, $B) as $x) {
        $pieno = Corpi::aspetti()[$x['aspetto']]['orbe'] * Corpi::fattoreOrbe($x['a'], $x['b']);
        if ($x['orbe_massimo'] >= $pieno) {
            return "{$x['aspetto']} {$x['a']}/{$x['b']}: orbe {$x['orbe_massimo']} non ridotto";
        }
    }

    return true;
});

prova('In sinastria gli assi dell\'altra persona contano', static function () use ($A, $B) {
    $con = Sinastria::aspettiIncrociati($A, $B, true, true);
    foreach ($con as $x) {
        if (in_array($x['b'], ['asc', 'mc'], true)) {
            return true;
        }
    }

    return 'nessun aspetto agli assi della seconda carta';
});

prova('Nei transiti gli assi del cielo NON contano', static function () use ($A, $B) {
    // L'Ascendente del cielo di un giorno percorre tutto lo zodiaco in
    // ventiquattro ore: come transito e' rumore.
    foreach (Sinastria::aspettiIncrociati($A, $B, true, false) as $x) {
        if (in_array($x['b'], ['asc', 'mc'], true)) {
            return "e' uscito un aspetto a «{$x['b']}» del cielo";
        }
    }

    return true;
});

prova('Con l\'ora ignota gli assi spariscono da soli', static function () use ($A, $Ignota) {
    foreach (Sinastria::aspettiIncrociati($A, $Ignota) as $x) {
        if (in_array($x['b'], ['asc', 'mc'], true)) {
            return 'e\' uscito un asse di una carta senza ora';
        }
    }

    return true;
});

prova('Gli aspetti sono ordinati per forza decrescente', static function () use ($A, $B) {
    $prec = INF;
    foreach (Sinastria::aspettiIncrociati($A, $B) as $x) {
        if ($x['forza'] > $prec + 1e-9) {
            return 'ordine rotto';
        }
        $prec = $x['forza'];
    }

    return true;
});

echo "\n\033[1;36m══ Punteggi per area ══\033[0m\n";

$sin = Sinastria::fra($A, $B, 'Anna', 'Bruno');

prova('Le quattro aree ci sono tutte', static function () use ($sin) {
    foreach (['attrazione', 'intesa', 'tenuta', 'attrito'] as $a) {
        if (!isset($sin['punteggi'][$a])) {
            return "manca {$a}";
        }
    }

    return true;
});

prova('I punteggi stanno fra 0 e 100', static function () use ($sin) {
    foreach ($sin['punteggi'] as $k => $p) {
        if ($p['valore'] < 0 || $p['valore'] > 100) {
            return "{$k}: {$p['valore']}";
        }
    }

    return true;
});

prova('Sotto «Attrito» non compaiono aspetti armonici', static function () use ($sin) {
    // Un trigono non e' attrito. Dargli un pesino sembrava prudente e
    // produceva «Marte trigono Marte» elencato sotto «dove si litiga».
    foreach ($sin['punteggi']['attrito']['contributi'] as $c) {
        if ($c['natura'] === 'armonico') {
            return "«{$c['testo']}» e' armonico";
        }
    }

    return true;
});

prova('Ogni punteggio dichiara da che cosa viene', static function () use ($sin) {
    foreach ($sin['punteggi'] as $k => $p) {
        if ($p['valore'] > 0 && $p['contributi'] === []) {
            return "{$k} vale {$p['valore']}% senza nessun contributo elencato";
        }
        if ($p['valore'] === 0 && $p['quanti'] > 0) {
            return "{$k} vale zero ma dichiara {$p['quanti']} contatti";
        }
    }

    return true;
});

prova('Due carte identiche danno intesa e tenuta alte', static function () use ($A) {
    // Chi si confronta con se' stesso ha ogni pianeta congiunto al proprio.
    $s = Sinastria::fra($A, $A, 'X', 'X');

    return ($s['punteggi']['tenuta']['valore'] > 50 && $s['punteggi']['intesa']['valore'] > 50)
        ? true
        : sprintf('tenuta %d%%, intesa %d%%', $s['punteggi']['tenuta']['valore'], $s['punteggi']['intesa']['valore']);
});

echo "\n\033[1;36m══ Sovrapposizione delle case ══\033[0m\n";

prova('Ogni pianeta cade in una casa fra 1 e 12', static function () use ($A, $B) {
    $o = Sinastria::sovrapposizione($A, $B);
    if ($o === null) {
        return 'non calcolata';
    }
    foreach ($o as $x) {
        if ($x['casa'] < 1 || $x['casa'] > 12) {
            return "{$x['corpo']} in casa {$x['casa']}";
        }
    }

    return count($o) === 10 ? true : count($o) . ' pianeti invece di 10';
});

prova('Con l\'ora ignota non si inventano le case', static function () use ($A, $Ignota) {
    // Le cuspidi non esistono: dire «il tuo Marte cade nella sua quinta»
    // sarebbe inventare.
    return Sinastria::sovrapposizione($A, $Ignota) === null
        ? true : 'ha restituito delle case';
});

prova('Nell\'altro verso invece si calcola', static function () use ($A, $Ignota) {
    // Chi non sa l'ora puo' comunque mettere i propri pianeti nelle case
    // dell'altro: i suoi pianeti esistono, sono le sue case a mancare.
    return Sinastria::sovrapposizione($Ignota, $A) !== null
        ? true : 'non calcolata pur avendo le case';
});

echo "\n\033[1;36m══ Composita ══\033[0m\n";

prova('Ogni corpo composito e\' il punto medio dei due', static function () use ($A, $B, $sin) {
    foreach ($sin['composita']['corpi'] as $k => $c) {
        $atteso = Sinastria::puntoMedio((float) $A['corpi'][$k]['lon'], (float) $B['corpi'][$k]['lon']);
        if (Corpi::distanza((float) $c['lon'], $atteso) > 0.001) {
            return "{$k}: {$c['lon']} invece di {$atteso}";
        }
    }

    return true;
});

prova('La composita di una carta con se\' stessa e\' la carta stessa', static function () use ($A) {
    $s = Sinastria::composita($A, $A);
    foreach ($s['corpi'] as $k => $c) {
        if (Corpi::distanza((float) $c['lon'], (float) $A['corpi'][$k]['lon']) > 0.001) {
            return "{$k} si e' spostato";
        }
    }

    return true;
});

prova('Nella composita nessun corpo e\' retrogrado', static function () use ($sin) {
    // Non e' un cielo, e' una costruzione: la retrogradazione non ha senso.
    foreach ($sin['composita']['corpi'] as $k => $c) {
        if ($c['retrogrado'] !== false) {
            return "{$k} risulta retrogrado";
        }
    }

    return true;
});

echo "\n\033[1;36m══ Sinastria rapida ══\033[0m\n";

prova('Tutte e 78 le coppie di segni producono un testo', static function () {
    // Le chiavi degli elementi vanno scritte in ordine alfabetico: sbagliarlo
    // non da' errore, fa sparire novanta coppie su centocinquantasei.
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        for ($i = 0; $i < 12; $i++) {
            for ($j = $i; $j < 12; $j++) {
                if ($c->segnoConSegno($i, $j) === null) {
                    return "{$reg}: manca la coppia {$i}/{$j}";
                }
            }
        }
    }

    return true;
});

prova('L\'ordine dei due segni non cambia il risultato', static function () {
    $c = new Corpus('moderno');
    $x = $c->segnoConSegno(0, 6);
    $y = $c->segnoConSegno(6, 0);

    $cx = array_column($x['paragrafi'], 'corpo');
    $cy = array_column($y['paragrafi'], 'corpo');

    return $cx === $cy ? true : 'i due versi danno testi diversi';
});

prova('La distanza si conta sull\'arco piu\' corto', static function () {
    // Dall'Ariete ai Pesci ci sono undici segni in avanti e uno indietro: in
    // sinastria conta quello, e sono segni contigui.
    $c = new Corpus('moderno');

    return $c->segnoConSegno(0, 11)['passi'] === 1
        ? true : 'passi = ' . $c->segnoConSegno(0, 11)['passi'];
});

prova('Segni opposti sono riconosciuti come tali', static function () {
    $c = new Corpus('moderno');
    foreach ([[0, 6], [1, 7], [5, 11]] as [$i, $j]) {
        $r = $c->segnoConSegno($i, $j);
        if ($r['aspetto'] !== 'opposizione') {
            return "{$i}/{$j}: «{$r['aspetto']}»";
        }
    }

    return true;
});

echo "\n\033[1;36m══ La doppia ruota ══\033[0m\n";

prova('La ruota doppia e\' XML valido', static function () use ($A, $B) {
    $svg = (new RuotaTema($A, true, null, $B, Sinastria::aspettiIncrociati($A, $B), 'ALTRO'))->disegna();

    return simplexml_load_string($svg) !== false ? true : 'XML non valido';
});

prova('La corona esterna sta fuori dallo zodiaco, l\'interna dentro', static function () use ($A, $B) {
    $svg = (new RuotaTema($A, true, null, $B, Sinastria::aspettiIncrociati($A, $B)))->disegna();

    $raggio = static function (string $patt) use ($svg): array {
        $out = [];
        if (preg_match_all($patt, $svg, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $x) {
                $out[] = sqrt(((float) $x[1] + 12.0 - 410.0) ** 2 + ((float) $x[2] + 12.0 - 410.0) ** 2);
            }
        }

        return $out;
    };

    $est = $raggio('/class="pianeta pianeta-esterno"[^>]*>\s*<use[^>]*x="([\d.]+)" y="([\d.]+)"/');
    $int = $raggio('/class="pianeta" data-corpo="[^"]*"><use[^>]*x="([\d.]+)" y="([\d.]+)"/');

    if ($est === [] || $int === []) {
        return 'corone vuote';
    }
    if (min($est) <= max($int)) {
        return sprintf('le corone si accavallano: esterna da %.0f, interna fino a %.0f', min($est), max($int));
    }

    return true;
});

prova('I glifi della corona esterna non si sovrappongono', static function () use ($A, $B) {
    $svg = (new RuotaTema($A, true, null, $B, Sinastria::aspettiIncrociati($A, $B)))->disegna();

    $pos = [];
    if (preg_match_all('/class="pianeta pianeta-esterno"[^>]*>\s*<use[^>]*x="([\d.]+)" y="([\d.]+)"/', $svg, $m, PREG_SET_ORDER) > 0) {
        foreach ($m as $x) {
            $pos[] = [(float) $x[1] + 12.0, (float) $x[2] + 12.0];
        }
    }

    for ($i = 0; $i < count($pos); $i++) {
        for ($j = $i + 1; $j < count($pos); $j++) {
            $d = sqrt(($pos[$i][0] - $pos[$j][0]) ** 2 + ($pos[$i][1] - $pos[$j][1]) ** 2);
            if ($d < 24.0) {
                return sprintf('due glifi a %.0f unita\' (il glifo e\' largo 24)', $d);
            }
        }
    }

    return true;
});

prova('La ruota singola non e\' cambiata', static function () use ($A) {
    $svg = (new RuotaTema($A, true))->disegna();

    return (simplexml_load_string($svg) !== false
        && !str_contains($svg, 'corona-esterna')
        && str_contains($svg, 'corona-pianeti'))
        ? true : 'la ruota singola ha preso pezzi della doppia';
});

prova('Il file autonomo porta i glifi di entrambe le carte', static function () use ($A, $B) {
    $svg = (new RuotaTema($A, true, null, $B, Sinastria::aspettiIncrociati($A, $B)))->disegna();

    preg_match_all('/href="#(gl-[a-z0-9-]+)"/', $svg, $usati);
    preg_match_all('/<symbol[^>]*id="(gl-[a-z0-9-]+)"/', $svg, $definiti);

    $mancanti = array_diff(array_unique($usati[1]), $definiti[1]);

    return $mancanti === [] ? true : 'simboli non definiti: ' . implode(', ', $mancanti);
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
