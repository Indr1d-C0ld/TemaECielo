<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove della volta celeste.
 *
 *   php tests/test_cielo.php
 *
 * Richiede il catalogo stellare importato (bin/importa-stelle.php).
 *
 * Un cielo disegnato male non si rompe: si disegna sbagliato, e nessuno se ne
 * accorge se non conosce il cielo. Queste prove confrontano il calcolo con
 * fatti astronomici verificabili — l'altezza della Polare, la precessione di
 * Regolo, il verso della falce lunare.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Motore;
use App\Cielo\Costellazioni;
use App\Cielo\Volta;
use App\Core\Config;
use App\Core\Database;
use App\Grafica\VoltaCeleste;

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

function vicino(float $ottenuto, float $atteso, float $tolleranza, string $unita = '°'): bool|string
{
    return abs($ottenuto - $atteso) <= $tolleranza
        ? true
        : sprintf('ottenuto %.4f%s, atteso %.4f%s (tolleranza %.4f)', $ottenuto, $unita, $atteso, $unita, $tolleranza);
}

if (!Database::disponibile() || (int) Database::valore('SELECT COUNT(*) FROM stelle') === 0) {
    exit("\n\033[1;33mCatalogo vuoto: esegui prima php bin/importa-stelle.php\033[0m\n\n");
}

echo "\n\033[1;36m══ Precessione degli equinozi ══\033[0m\n";

prova('A J2000 la precessione non sposta nulla', static function () {
    [$ar, $decl] = Volta::precessa(83.0, 10.0, 2451545.0);

    return (abs($ar - 83.0) < 1e-6 && abs($decl - 10.0) < 1e-6) ? true : "{$ar} / {$decl}";
});

prova('In un secolo una stella si sposta di circa 1,4 gradi', static function () {
    // La precessione vale 50,3 secondi d'arco all'anno: 1,396 gradi al secolo.
    [$ar, $decl] = Volta::precessa(152.0930, 11.9672, 2451545.0 + 36525.0);
    $d = sqrt((($ar - 152.0930) * cos(deg2rad(11.97))) ** 2 + ($decl - 11.9672) ** 2);

    return vicino($d, 1.396, 0.02);
});

prova('La Stella Polare si avvicina al polo col passare dei secoli', static function () {
    $d2000 = 90.0 - Volta::precessa(37.9529, 89.2641, 2451545.0)[1];
    $d2025 = 90.0 - Volta::precessa(37.9529, 89.2641, 2460676.5)[1];

    return $d2025 < $d2000
        ? true
        : sprintf('nel 2000 distava %.1f primi, nel 2025 %.1f', $d2000 * 60, $d2025 * 60);
});

echo "\n\033[1;36m══ Altezza e azimut ══\033[0m\n";

/** La Polare sta a nord, a un'altezza pari alla latitudine: e' come si misura la latitudine da sempre. */
foreach ([[45.46, 'Milano'], [41.89, 'Roma'], [0.0, 'equatore'], [78.22, 'Svalbard']] as [$lat, $dove]) {
    prova("Da {$dove} la Polare sta a un'altezza pari alla latitudine", static function () use ($lat) {
        [$ar, $decl] = Volta::precessa(37.9529, 89.2641, 2460676.5);
        [$alt, $az] = Volta::altAzimut($ar, $decl, 0.0, $lat);

        if (abs($az) > 3.0 && abs($az - 360.0) > 3.0) {
            return sprintf('azimut %.1f°, atteso circa 0 (nord)', $az);
        }

        return vicino($alt, $lat, 0.8);
    });
}

prova('L\'equatore celeste al meridiano sta a 90° meno la latitudine', static function () {
    [$alt, $az] = Volta::altAzimut(0.0, 0.0, 0.0, 45.46);

    return vicino($alt, 44.54, 0.01) === true && vicino($az, 180.0, 0.01) === true
        ? true
        : sprintf('alt %.2f°, az %.2f°', $alt, $az);
});

prova('Sei ore prima quella stessa stella sorge a est', static function () {
    [$alt, $az] = Volta::altAzimut(90.0, 0.0, 0.0, 45.46);

    return vicino($alt, 0.0, 0.01) === true && vicino($az, 90.0, 0.01) === true
        ? true
        : sprintf('alt %.2f°, az %.2f°', $alt, $az);
});

prova('Nell\'emisfero australe la Polare non si vede', static function () {
    [$ar, $decl] = Volta::precessa(37.9529, 89.2641, 2460676.5);
    [$alt] = Volta::altAzimut($ar, $decl, 0.0, -33.87);

    return $alt < 0.0 ? true : sprintf('altezza %.1f° da Sydney', $alt);
});

echo "\n\033[1;36m══ Il verso della falce lunare ══\033[0m\n";

/**
 * Il punto costruito deve avvicinarsi al bersaglio: e' l'invariante che rende
 * il metodo verificabile senza dover ragionare su convenzioni di segno.
 */
function separazione(float $alt1, float $az1, float $alt2, float $az2): float
{
    $a1 = deg2rad($alt1); $z1 = deg2rad($az1);
    $a2 = deg2rad($alt2); $z2 = deg2rad($az2);
    $c = sin($a1) * sin($a2) + cos($a1) * cos($a2) * cos($z1 - $z2);

    return rad2deg(acos(max(-1.0, min(1.0, $c))));
}

$situazioni = [
    ['Luna alta a sud, Sole tramontato a nord-ovest', 29.6, 190.2, -38.3, 315.1],
    ['Luna bassa a ovest, Sole appena sotto l\'orizzonte', 12.0, 265.0, -4.0, 280.0],
    ['Luna a est all\'alba, Sole che sorge',              20.0,  95.0,  -2.0,  85.0],
    ['Luna allo zenit',                                   89.0,   0.0,  30.0, 180.0],
    ['Sole e Luna quasi congiunti',                       30.0, 180.0,  31.0, 181.0],
];

foreach ($situazioni as [$eti, $altL, $azL, $altS, $azS]) {
    prova("Il passo si avvicina al Sole — {$eti}", static function () use ($altL, $azL, $altS, $azS) {
        $prima = separazione($altL, $azL, $altS, $azS);
        [$ap, $zp] = Volta::versoIlPunto($altL, $azL, $altS, $azS, 1.0);
        $dopo = separazione($ap, $zp, $altS, $azS);

        if ($dopo >= $prima) {
            return sprintf('separazione da %.2f° a %.2f°: si allontana', $prima, $dopo);
        }

        // Un passo di un grado deve ridurre la separazione di un grado.
        return vicino($prima - $dopo, 1.0, 0.02);
    });
}

prova('Con Luna e Sole coincidenti non si inventa una direzione', static function () {
    [$alt, $az] = Volta::versoIlPunto(30.0, 180.0, 30.0, 180.0);

    return (abs($alt - 30.0) < 1e-9 && abs($az - 180.0) < 1e-9) ? true : "{$alt} / {$az}";
});

prova('La gobba di una Luna serale punta a ovest, non a est', static function () {
    // Il caso che ha smascherato la scorciatoia: tirare una retta sul foglio
    // fra Luna e Sole sbagliava di centosessanta gradi.
    $altL = 29.6; $azL = 190.2; $altS = -38.3; $azS = 315.1;

    $R = 372.0; $CX = 410.0; $CY = 410.0;
    $proietta = static function (float $alt, float $az) use ($R, $CX, $CY): array {
        $r = $R * tan(deg2rad((90.0 - $alt) / 2.0));
        $a = deg2rad($az);

        return [$CX - $r * sin($a), $CY - $r * cos($a)];
    };

    [$lx, $ly] = $proietta($altL, $azL);
    [$ap, $zp] = Volta::versoIlPunto($altL, $azL, $altS, $azS);
    [$px, $py] = $proietta($ap, $zp);

    // Sulla carta l'ovest e' a destra: la componente orizzontale deve essere positiva.
    return ($px - $lx) > 0.0
        ? true
        : sprintf('la gobba punta a sinistra (verso est): scarto orizzontale %.2f', $px - $lx);
});

echo "\n\033[1;36m══ Stelle e colori ══\033[0m\n";

prova('Le stelle azzurre e le rosse hanno colori diversi', static function () {
    $azzurra = Volta::colore(-0.30);
    $rossa   = Volta::colore(1.85);

    return $azzurra !== $rossa ? true : 'stesso colore per entrambe';
});

prova('Il colore segue l\'indice B-V in modo monotono', static function () {
    // Piu' l'indice cresce, piu' la componente blu deve calare: e' la fisica.
    $blu = null;
    foreach ([-0.3, 0.0, 0.2, 0.45, 0.75, 1.2, 1.9] as $ci) {
        $c = Volta::colore($ci);
        $b = (int) hexdec(substr($c, 5, 2));
        if ($blu !== null && $b > $blu) {
            return "con B-V {$ci} il blu risale: {$c}";
        }
        $blu = $b;
    }

    return true;
});

prova('Le stelle piu' . "'" . ' luminose si disegnano piu' . "'" . ' grandi', static function () {
    $prec = INF;
    foreach ([-1.44, 0.0, 2.0, 4.0, 6.0, 6.5] as $m) {
        $r = Volta::raggio($m);
        if ($r >= $prec) {
            return "magnitudine {$m}: raggio {$r} non minore del precedente";
        }
        $prec = $r;
    }

    return $prec > 0.0 ? true : 'raggio nullo o negativo al limite';
});

echo "\n\033[1;36m══ Il colore del cielo ══\033[0m\n";

$cieli = [
    [45.0,  'giorno pieno',            0.0],
    [-3.0,  'crepuscolo civile',       null],
    [-9.0,  'crepuscolo nautico',      null],
    [-15.0, 'crepuscolo astronomico',  null],
    [-30.0, 'notte piena',             1.0],
];
foreach ($cieli as [$h, $nome, $visibilita]) {
    prova(sprintf('Sole a %+.0f° → %s', $h, $nome), static function () use ($h, $nome, $visibilita) {
        $c = Volta::cielo($h);
        if ($c['nome'] !== $nome) {
            return "ottenuto «{$c['nome']}»";
        }

        return $visibilita === null || abs($c['stelleVisibili'] - $visibilita) < 1e-9
            ? true
            : "visibilita' {$c['stelleVisibili']}";
    });
}

prova('Di giorno le stelle sono dichiarate invisibili', static function () {
    return Volta::cielo(20.0)['stelleVisibili'] === 0.0 ? true : 'visibilita\' non nulla';
});

echo "\n\033[1;36m══ Catalogo ══\033[0m\n";

prova('Il catalogo copre il cielo a occhio nudo', static function () {
    $n = (int) Database::valore('SELECT COUNT(*) FROM stelle WHERE mag <= 6.5');

    return $n > 8000 ? true : "solo {$n} stelle fino alla magnitudine 6,5";
});

prova('Tutte e 88 le costellazioni hanno il nome italiano', static function () {
    $n = (int) Database::valore('SELECT COUNT(*) FROM costellazioni');
    if ($n !== 88) {
        return "{$n} costellazioni in tabella";
    }
    foreach (Costellazioni::elenco() as $abbr => [$it]) {
        if ($it === '') {
            return "{$abbr} senza nome italiano";
        }
        // Un nome uguale alla sigla di solito vuol dire traduzione dimenticata,
        // ma la Gru fa eccezione per davvero: in italiano si chiama «Gru» e la
        // sua sigla IAU e' «Gru». La coincidenza e' autentica.
        if ($it === $abbr && $abbr !== 'Gru') {
            return "{$abbr} sembra non tradotta";
        }
    }

    return true;
});

prova('Nessun segmento di costellazione ha un estremo mancante', static function () {
    // Se una stella serve a una figura ma e' piu' debole del limite, va tenuta
    // lo stesso: altrimenti resta un buco in un disegno che tutti riconoscono.
    $orfani = (int) Database::valore(
        'SELECT COUNT(*) FROM costellazioni_linee l
          WHERE NOT EXISTS (SELECT 1 FROM stelle s WHERE s.hip = l.hip_a)
             OR NOT EXISTS (SELECT 1 FROM stelle s WHERE s.hip = l.hip_b)'
    );

    return $orfani === 0 ? true : "{$orfani} segmenti con un estremo assente";
});

prova('Le stelle piu' . "'" . ' note ci sono, col nome giusto', static function () {
    $attesi = ['Sirio' => 32349, 'Stella Polare' => 11767, 'Vega' => 91262,
               'Arturo' => 69673, 'Betelgeuse' => 27989, 'Regolo' => 49669];
    foreach ($attesi as $nome => $hip) {
        $trovato = Database::valore('SELECT nome FROM stelle WHERE hip = ?', [$hip]);
        if ($trovato !== $nome) {
            return "HIP {$hip}: «" . var_export($trovato, true) . "» invece di «{$nome}»";
        }
    }

    return true;
});

echo "\n\033[1;36m══ Il disegno ══\033[0m\n";

$motore = new Motore();
$scene = [
    'notte'  => ['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 21.2333,
                 'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido'],
    'giorno' => ['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 11.0,
                 'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido'],
    'australe' => ['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 13.0,
                   'lat' => -33.87, 'lon' => 151.21, 'alt' => 10, 'sistema_case' => 'placido'],
];
$temi = [];
foreach ($scene as $n => $d) {
    $temi[$n] = $motore->tema($d);
}

foreach ($temi as $nome => $tema) {
    prova("SVG ben formato — {$nome}", static function () use ($tema) {
        $prec = libxml_use_internal_errors(true);
        $doc = simplexml_load_string((new VoltaCeleste($tema, true))->disegna());
        $err = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prec);

        return $doc !== false ? true : 'XML non valido: ' . ($err[0]->message ?? '?');
    });
}

prova('Sotto l\'orizzonte non si disegna niente', static function () use ($temi) {
    // Un corpo sotto l'orizzonte non si vede: disegnarlo sul disco vorrebbe
    // dire metterlo in un cielo dove non c'era.
    $tema = $temi['notte'];
    $svg = (new VoltaCeleste($tema))->disegna();

    foreach (\App\Astro\Corpi::dieci() as $k) {
        if (!isset($tema['corpi'][$k])) {
            continue;
        }
        $sotto = (float) $tema['corpi'][$k]['altezza'] < 0.0;
        $disegnato = str_contains($svg, 'data-corpo="' . $k . '"');
        if ($sotto && $disegnato) {
            return "{$k} e' a " . round((float) $tema['corpi'][$k]['altezza'], 1) . "° e viene disegnato";
        }
    }

    return true;
});

prova('Di giorno le stelle si attenuano ma non spariscono', static function () use ($temi) {
    // Chi e' nato di giorno non vedeva le stelle, e va detto. Cancellarle del
    // tutto toglierebbe pero' un'informazione vera: DOVE stavano.
    $giorno = (new VoltaCeleste($temi['giorno']))->disegna();
    $notte  = (new VoltaCeleste($temi['notte']))->disegna();

    preg_match_all('/<g fill="#[0-9a-f]{6}" opacity="([\d.]+)"/', $giorno, $g);
    preg_match_all('/<g fill="#[0-9a-f]{6}" opacity="([\d.]+)"/', $notte, $n);

    if ($g[1] === [] || $n[1] === []) {
        return 'nessun gruppo di stelle trovato';
    }

    $maxGiorno = max(array_map('floatval', $g[1]));
    $maxNotte  = max(array_map('floatval', $n[1]));

    if ($maxGiorno <= 0.0) {
        return 'di giorno le stelle sono del tutto invisibili';
    }

    return $maxGiorno < $maxNotte
        ? true
        : sprintf('di giorno opacita\' %.2f, di notte %.2f', $maxGiorno, $maxNotte);
});

prova('L\'est sta a sinistra, come sui planisferi', static function () use ($temi) {
    $svg = (new VoltaCeleste($temi['notte']))->disegna();

    preg_match_all('/<text x="([\d.]+)" y="([\d.]+)"[^>]*>([NESO])<\/text>/', $svg, $m, PREG_SET_ORDER);
    $p = [];
    foreach ($m as $t) {
        $p[$t[3]] = [(float) $t[1], (float) $t[2]];
    }

    foreach (['N', 'E', 'S', 'O'] as $c) {
        if (!isset($p[$c])) {
            return "manca il punto cardinale {$c}";
        }
    }

    if ($p['E'][0] >= 410.0) { return 'l\'est non sta a sinistra'; }
    if ($p['O'][0] <= 410.0) { return 'l\'ovest non sta a destra'; }
    if ($p['N'][1] >= 410.0) { return 'il nord non sta in alto'; }
    if ($p['S'][1] <= 410.0) { return 'il sud non sta in basso'; }

    return true;
});

prova('Le figure delle costellazioni non attraversano il disco', static function () use ($temi) {
    // Un segmento con un estremo sotto l'orizzonte non va chiuso con una corda
    // che passa da un'altra parte del cielo.
    $tema = $temi['notte'];
    $jd = (float) $tema['tempo']['jd_ut'];
    $tsl = (float) $tema['tempo']['siderale_locale'] * 15.0;
    $stelle = Volta::stelle($jd, $tsl, (float) $tema['luogo']['lat']);

    foreach (Volta::linee($stelle) as $l) {
        if ($stelle[$l['a']]['alt'] < -2.0 || $stelle[$l['b']]['alt'] < -2.0) {
            return 'un segmento ha un estremo troppo sotto l\'orizzonte';
        }
    }

    return true;
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
