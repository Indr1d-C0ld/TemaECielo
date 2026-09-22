<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove delle carte derivate.
 *
 *   php tests/test_derivate.php
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Corpi;
use App\Astro\Derivate;
use App\Astro\Motore;
use App\Core\Config;

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

$motore = new Motore();
$natale = $motore->tema(['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 21.2333,
                         'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido']);
$jdNatale = (float) $natale['tempo']['jd_ut'];

echo "\n\033[1;36m══ Rivoluzione solare ══\033[0m\n";

prova('Il Sole torna esattamente sul grado natale', static function () use ($motore, $natale, $jdNatale) {
    $lon = (float) $natale['corpi']['sole']['lon'];
    $r = $motore->ritorno(['corpo' => 'sole', 'longitudine' => $lon,
                           'jd_da' => $jdNatale + 47 * 365.25, 'jd_a' => $jdNatale + 49 * 365.25]);

    return abs((float) $r['scarto']) < 1e-6
        ? true : sprintf('scarto %.2e gradi', abs((float) $r['scarto']));
});

prova('La rivoluzione cade vicino al compleanno', static function () use ($motore, $natale, $jdNatale) {
    // Non IL compleanno: il Sole impiega 365 giorni e un quarto, quindi
    // l'istante scivola di quasi sei ore all'anno e ogni tanto cambia giorno.
    $lon = (float) $natale['corpi']['sole']['lon'];
    $r = $motore->ritorno(['corpo' => 'sole', 'longitudine' => $lon,
                           'jd_da' => $jdNatale + 47 * 365.25, 'jd_a' => $jdNatale + 49 * 365.25]);

    return ($r['mese'] === 6 && abs($r['giorno'] - 12) <= 1)
        ? true : sprintf('%d-%02d-%02d', $r['anno'], $r['mese'], $r['giorno']);
});

prova('Di anno in anno l\'istante slitta di circa sei ore', static function () use ($motore, $natale, $jdNatale) {
    $lon = (float) $natale['corpi']['sole']['lon'];
    $a = $motore->ritorno(['corpo' => 'sole', 'longitudine' => $lon,
                           'jd_da' => $jdNatale + 40 * 365.25, 'jd_a' => $jdNatale + 41 * 365.25]);
    $b = $motore->ritorno(['corpo' => 'sole', 'longitudine' => $lon,
                           'jd_da' => $jdNatale + 41 * 365.25, 'jd_a' => $jdNatale + 42 * 365.25]);

    // L'anno tropico e' 365,2422 giorni: lo scarto dall'anno civile e' 0,2422
    // giorni, cioe' cinque ore e quarantotto minuti.
    $slitta = ((float) $b['jd_ut'] - (float) $a['jd_ut']) - 365.0;

    return abs($slitta - 0.2422) < 0.02
        ? true : sprintf('slitta di %.4f giorni', $slitta);
});

prova('La rivoluzione lunare si trova entro il mese', static function () use ($motore, $natale, $jdNatale) {
    $lon = (float) $natale['corpi']['luna']['lon'];
    $r = $motore->ritorno(['corpo' => 'luna', 'longitudine' => $lon,
                           'jd_da' => $jdNatale + 10000.0, 'jd_a' => $jdNatale + 10030.0]);

    return abs((float) $r['scarto']) < 1e-4
        ? true : sprintf('scarto %.2e', abs((float) $r['scarto']));
});

echo "\n\033[1;36m══ Progressioni secondarie ══\033[0m\n";

prova('Un giorno vale un anno', static function () use ($jdNatale) {
    // A quarant'anni si guarda il cielo del quarantesimo giorno dopo la nascita.
    $jd40 = $jdNatale + 40 * 365.24219;
    $prog = Derivate::jdProgresso($jdNatale, $jd40);

    return abs(($prog - $jdNatale) - 40.0) < 0.001
        ? true : sprintf('%.4f giorni invece di 40', $prog - $jdNatale);
});

prova('Il Sole progredito avanza di circa un grado all\'anno', static function () use ($motore, $natale, $jdNatale) {
    $prog = $motore->tema(['anno' => 1978, 'mese' => 7, 'giorno' => 22, 'ora_ut' => 21.2333,
                           'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido']);
    // Quaranta giorni dopo la nascita = quarant'anni di vita.
    $arco = Derivate::arcoSolare($natale, $prog);

    return ($arco > 36.0 && $arco < 42.0)
        ? true : sprintf('arco di %.2f gradi in quarant\'anni', $arco);
});

prova('L\'arco vero della Luna conta i giri', static function () use ($motore, $natale, $jdNatale) {
    // In cinquanta giorni la Luna fa quasi due rivoluzioni: la differenza
    // ridotta al giro direbbe che e' andata all'indietro.
    $jd = $jdNatale + 48 * 365.24219;
    $prog = $motore->tema(componenti(Derivate::jdProgresso($jdNatale, $jd)) + [
        'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido',
    ]);

    $giorni = (float) $prog['tempo']['jd_ut'] - $jdNatale;
    $arco = Derivate::arcoVero('luna', (float) $natale['corpi']['luna']['lon'],
                               (float) $prog['corpi']['luna']['lon'], $giorni);

    // 48 giorni a circa 13,2 gradi al giorno: fra 600 e 680 gradi.
    return ($arco > 600.0 && $arco < 680.0)
        ? true : sprintf('%.1f gradi in %.1f giorni', $arco, $giorni);
});

prova('L\'arco vero del Sole resta quello ridotto', static function () use ($natale) {
    // Il Sole in ottant'anni di progressioni non fa un giro: nessuna
    // rivoluzione da ricostruire.
    $arco = Derivate::arcoVero('sole', 81.53, 127.33, 48.0);

    return abs($arco - 45.8) < 0.01 ? true : sprintf('%.2f', $arco);
});

prova('I pianeti lenti possono avere arco negativo', static function () {
    // Retrogradi durante i giorni della progressione: l'arco e' all'indietro,
    // ed e' giusto mostrarlo col segno.
    $arco = Derivate::arcoVero('urano', 222.55, 221.95, 48.0);

    return $arco < 0.0 ? true : sprintf('%.2f, atteso negativo', $arco);
});

echo "\n\033[1;36m══ Direzioni di arco solare ══\033[0m\n";

prova('Ogni punto avanza dello stesso arco', static function () use ($natale) {
    $d = Derivate::direzioni($natale, 45.8);

    foreach ($d['corpi'] as $k => $c) {
        $atteso = Corpi::norma((float) $natale['corpi'][$k]['lon'] + 45.8);
        if (Corpi::distanza((float) $c['lon'], $atteso) > 0.001) {
            return "{$k} non e' avanzato dell'arco giusto";
        }
    }

    return true;
});

prova('Anche gli assi avanzano', static function () use ($natale) {
    $d = Derivate::direzioni($natale, 45.8);

    return isset($d['punti']['asc'], $d['punti']['mc']) ? true : 'gli assi non sono stati diretti';
});

prova('Con arco nullo la carta resta se stessa', static function () use ($natale) {
    $d = Derivate::direzioni($natale, 0.0);

    foreach ($d['corpi'] as $k => $c) {
        if (Corpi::distanza((float) $c['lon'], (float) $natale['corpi'][$k]['lon']) > 1e-9) {
            return "{$k} si e' spostato";
        }
    }

    return true;
});

echo "\n\033[1;36m══ Profezioni ══\033[0m\n";

prova('A dodici anni si torna al segno di partenza', static function () use ($natale) {
    $zero = Derivate::profezione($natale, 0);
    $dodici = Derivate::profezione($natale, 12);

    return ($zero !== null && $dodici !== null && $zero['segno'] === $dodici['segno'])
        ? true : 'il ciclo di dodici anni non si chiude';
});

prova('Ogni anno avanza di un segno', static function () use ($natale) {
    for ($a = 0; $a < 24; $a++) {
        $p = Derivate::profezione($natale, $a);
        $q = Derivate::profezione($natale, $a + 1);
        if ($p === null || $q === null) {
            return 'profezione non calcolata';
        }
        if (($p['segno'] + 1) % 12 !== $q['segno']) {
            return "fra {$a} e " . ($a + 1) . " anni il segno non avanza di uno";
        }
    }

    return true;
});

prova('A zero anni il segno e\' quello dell\'Ascendente', static function () use ($natale) {
    $p = Derivate::profezione($natale, 0);

    return ($p !== null && $p['segno'] === Corpi::segnoDi((float) $natale['punti']['asc']['lon']))
        ? true : 'non parte dall\'Ascendente';
});

prova('La casa profetta segue il ciclo di dodici', static function () use ($natale) {
    foreach ([0 => 1, 5 => 6, 11 => 12, 12 => 1, 48 => 1] as $anni => $casa) {
        $p = Derivate::profezione($natale, $anni);
        if ($p === null || $p['casa'] !== $casa) {
            return "a {$anni} anni: casa " . ($p['casa'] ?? '—') . ", attesa {$casa}";
        }
    }

    return true;
});

prova('Il signore dell\'anno e\' quello tradizionale del segno', static function () use ($natale) {
    for ($a = 0; $a < 12; $a++) {
        $p = Derivate::profezione($natale, $a);
        $atteso = Corpi::segni()[$p['segno']]['domicilio'];
        if ($p['signore'] !== $atteso) {
            return "a {$a} anni: {$p['signore']} invece di {$atteso}";
        }
    }

    return true;
});

prova('Senza ora di nascita non si profetta', static function () use ($motore) {
    // Senza Ascendente non c'e' niente da far avanzare, e inventarlo sarebbe
    // peggio che non rispondere.
    $ignota = $motore->tema(['anno' => 1955, 'mese' => 6, 'giorno' => 15, 'ora_ut' => 11.0,
                             'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122,
                             'sistema_case' => 'segni_interi', 'ora_ignota' => true]);

    return Derivate::profezione($ignota, 30) === null ? true : 'ha profettato comunque';
});

echo "\n\033[1;36m══ Contatti col cielo natale ══\033[0m\n";

prova('Gli orbi delle progressioni sono strettissimi', static function () use ($motore, $natale, $jdNatale) {
    // Nelle progressioni un grado vale un anno di vita: con gli orbi di una
    // carta natale un aspetto resterebbe attivo per sedici anni.
    $prog = $motore->tema(componenti(Derivate::jdProgresso($jdNatale, $jdNatale + 48 * 365.24219)) + [
        'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido',
    ]);

    foreach (Derivate::contatti($prog, $natale, 1.0) as $c) {
        if ((float) $c['orbe'] > 1.0) {
            return "orbe {$c['orbe']} oltre il grado";
        }
    }

    return true;
});

prova('Con l\'ora ignota i contatti agli assi spariscono', static function () use ($motore) {
    $ignota = $motore->tema(['anno' => 1955, 'mese' => 6, 'giorno' => 15, 'ora_ut' => 11.0,
                             'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122,
                             'sistema_case' => 'segni_interi', 'ora_ignota' => true]);
    $prog = $motore->tema(['anno' => 1955, 'mese' => 8, 'giorno' => 4, 'ora_ut' => 11.0,
                           'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'segni_interi']);

    foreach (Derivate::contatti($prog, $ignota) as $c) {
        if (in_array($c['b'], ['asc', 'mc'], true)) {
            return 'e\' uscito un contatto a un asse inesistente';
        }
    }

    return true;
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);

/** Da giorno giuliano ai componenti che vuole il motore. */
function componenti(float $jd): array
{
    $unix = (int) round(($jd - 2440587.5) * 86400.0);
    $t = new \DateTimeImmutable('@' . $unix);

    return ['anno' => (int) $t->format('Y'), 'mese' => (int) $t->format('n'),
            'giorno' => (int) $t->format('j'),
            'ora_ut' => (int) $t->format('G') + (int) $t->format('i') / 60.0];
}
