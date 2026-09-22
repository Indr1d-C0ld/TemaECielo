<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove di regressione del motore astronomico.
 *
 *   TEC_LIBSWE=... TEC_EPHE=... php tests/test_astro.php
 *
 * I riferimenti non sono presi da altri programmi di astrologia — che
 * potrebbero sbagliare insieme a noi — ma da istanti astronomici pubblicati e
 * verificabili: equinozi, solstizi, noviluni. A quegli istanti il Sole sta per
 * definizione a 0, 90, 180 o 270 gradi, e la Luna e' congiunta al Sole.
 *
 * Il Sole percorre circa 0,0007 gradi al minuto: una tolleranza di un
 * centesimo di grado corrisponde a una quindicina di minuti di orologio, ed e'
 * larga abbastanza da assorbire l'arrotondamento degli istanti pubblicati.
 */

require dirname(__DIR__) . '/src/autoload.php';

use App\Astro\Aspetti;
use App\Astro\Bilanci;
use App\Astro\Corpi;
use App\Astro\Dignita;
use App\Astro\Sweph;
use App\Astro\Tema;
use App\Astro\Worker;

$libreria   = getenv('TEC_LIBSWE') ?: '/usr/lib/x86_64-linux-gnu/libswe.so.2';
$effemeridi = getenv('TEC_EPHE') ?: '/usr/share/libswe/ephe';

$passate = 0;
$fallite = 0;

function prova(string $titolo, callable $f): void
{
    global $passate, $fallite;
    try {
        $esito = $f();
        if ($esito === true) {
            $passate++;
            printf("  \033[0;32mOK \033[0m %s\n", $titolo);
        } else {
            $fallite++;
            printf("  \033[1;31mNO \033[0m %s\n        %s\n", $titolo, is_string($esito) ? $esito : 'esito falso');
        }
    } catch (\Throwable $e) {
        $fallite++;
        printf("  \033[1;31mERR\033[0m %s\n        %s\n", $titolo, $e->getMessage());
    }
}

function vicino(float $ottenuto, float $atteso, float $tolleranza, string $unita = '°'): bool|string
{
    $scarto = abs($ottenuto - $atteso);
    if ($scarto > 180.0 && $unita === '°') {
        $scarto = 360.0 - $scarto;   // il giro dell'angolo
    }

    return $scarto <= $tolleranza
        ? true
        : sprintf('ottenuto %.6f%s, atteso %.6f%s, scarto %.6f%s (tolleranza %.6f)',
            $ottenuto, $unita, $atteso, $unita, $scarto, $unita, $tolleranza);
}

echo "\n\033[1;36m══ Swiss Ephemeris ══\033[0m\n";

$swe = new Sweph($libreria, $effemeridi);
printf("  libreria %s, effemeridi %s\n\n", $swe->versione(), $swe->conFileEffemeridi() ? 'da file .se1' : 'analitiche (Moshier)');

// ---------------------------------------------------------------------------
echo "\033[1;36m══ Istanti astronomici noti ══\033[0m\n";

/** Equinozi e solstizi, istante UTC pubblicato e longitudine solare per definizione. */
$stagioni = [
    ['Equinozio di marzo 2024',    2024,  3, 20,  3 +  6/60,   0.0],
    ['Solstizio di giugno 2024',   2024,  6, 20, 20 + 51/60,  90.0],
    ['Equinozio di settembre 2024',2024,  9, 22, 12 + 44/60, 180.0],
    ['Solstizio di dicembre 2024', 2024, 12, 21,  9 + 21/60, 270.0],
    ['Equinozio di marzo 2025',    2025,  3, 20,  9 +  1/60,   0.0],
    ['Solstizio di giugno 2025',   2025,  6, 21,  2 + 42/60,  90.0],
];

foreach ($stagioni as [$nome, $a, $m, $g, $ora, $atteso]) {
    prova($nome . ': Sole a ' . $atteso . '°', static function () use ($swe, $a, $m, $g, $ora, $atteso) {
        $jd  = $swe->giornoGiuliano($a, $m, $g, $ora);
        $lon = $swe->posizione($jd, Corpi::SOLE)['lon'];

        return vicino($lon, $atteso, 0.01);
    });
}

// Eclisse solare totale dell'8 aprile 2024: novilunio alle 18:21 UTC.
prova('Novilunio dell\'8 aprile 2024: elongazione nulla', static function () use ($swe) {
    $jd    = $swe->giornoGiuliano(2024, 4, 8, 18 + 21 / 60);
    $sole  = $swe->posizione($jd, Corpi::SOLE)['lon'];
    $luna  = $swe->posizione($jd, Corpi::LUNA)['lon'];

    return vicino(Corpi::distanza($sole, $luna), 0.0, 0.05);
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Grandezze fondamentali ══\033[0m\n";

// L'indice -1 chiede obliquita' e nutazione insieme. La libreria restituisce
// quattro grandezze diverse nello stesso vettore, ed e' facile scambiarle:
//   lon      = obliquita' VERA (nutazione compresa)
//   lat      = obliquita' MEDIA
//   dist     = nutazione in longitudine
//   vel_lon  = nutazione in obliquita'
// Il valore da manuale 23,4392911° e' quello MEDIO: confrontarlo con quello
// vero fa sbagliare di circa sei secondi d'arco, che e' proprio la nutazione.
prova('Obliquita\' MEDIA a J2000 = 23,4392911°', static function () use ($swe) {
    $e = $swe->posizione(2451545.0, -1);

    return vicino($e['lat'], 23.4392911, 0.0001);
});

prova('Obliquita\' vera = media + nutazione in obliquita\'', static function () use ($swe) {
    $e = $swe->posizione(2451545.0, -1);

    return vicino($e['lon'], $e['lat'] + $e['vel_lon'], 1e-9);
});

prova('Nutazione in obliquita\' a J2000 ≈ -5,8 secondi d\'arco', static function () use ($swe) {
    $e = $swe->posizione(2451545.0, -1);

    return vicino($e['vel_lon'] * 3600.0, -5.77, 0.3, '"');
});

prova('Delta T nel 2024 ≈ 69 secondi', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(2024, 1, 1, 0.0);

    return vicino($swe->deltaT($jd) * 86400.0, 69.2, 1.5, ' s');
});

prova('Giorno giuliano di J2000.0 (1 gennaio 2000, 12:00 UT) = 2451545', static function () use ($swe) {
    return vicino($swe->giornoGiuliano(2000, 1, 1, 12.0), 2451545.0, 1e-6, ' g');
});

prova('Andata e ritorno fra data e giorno giuliano', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(1978, 6, 12, 21.2333333);
    $d  = $swe->daGiornoGiuliano($jd);

    return $d['anno'] === 1978 && $d['mese'] === 6 && $d['giorno'] === 12 && abs($d['ora'] - 21.2333333) < 1e-6
        ? true
        : sprintf('%d-%02d-%02d %.6f', $d['anno'], $d['mese'], $d['giorno'], $d['ora']);
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Levate, tramonti, orizzonte ══\033[0m\n";

// Milano, 12 giugno 1978: alba 05:34 e tramonto 21:12 ora locale estiva (UTC+2).
prova('Alba a Milano il 12 giugno 1978 alle 03:34 UT', static function () use ($swe) {
    $mezzanotte = $swe->giornoGiuliano(1978, 6, 12, 0.0);
    $t = $swe->levataTramonto($mezzanotte, Corpi::SOLE, Sweph::LEVATA, 45.4642, 9.19, 122.0);
    if ($t === null) {
        return 'nessuna levata calcolata';
    }

    return vicino(($t - $mezzanotte) * 24.0, 3 + 34 / 60, 0.05, ' h');
});

prova('Tramonto a Milano il 12 giugno 1978 alle 19:12 UT', static function () use ($swe) {
    $mezzanotte = $swe->giornoGiuliano(1978, 6, 12, 0.0);
    $t = $swe->levataTramonto($mezzanotte, Corpi::SOLE, Sweph::TRAMONTO, 45.4642, 9.19, 122.0);
    if ($t === null) {
        return 'nessun tramonto calcolato';
    }

    return vicino(($t - $mezzanotte) * 24.0, 19 + 12 / 60, 0.05, ' h');
});

prova('Notte polare: a Longyearbyen il 21 dicembre il Sole non sorge', static function () use ($swe) {
    $mezzanotte = $swe->giornoGiuliano(2024, 12, 21, 0.0);
    $t = $swe->levataTramonto($mezzanotte, Corpi::SOLE, Sweph::LEVATA, 78.22, 15.63, 0.0);

    return $t === null ? true : 'ha restituito una levata a JD ' . $t;
});

prova('Sole di mezzanotte: a Longyearbyen il 21 giugno il Sole non tramonta', static function () use ($swe) {
    $mezzanotte = $swe->giornoGiuliano(2024, 6, 21, 0.0);
    $t = $swe->levataTramonto($mezzanotte, Corpi::SOLE, Sweph::TRAMONTO, 78.22, 15.63, 0.0);

    return $t === null ? true : 'ha restituito un tramonto a JD ' . $t;
});

prova('A mezzogiorno vero il Sole culmina a sud (azimut 180°)', static function () use ($swe) {
    $mezzanotte = $swe->giornoGiuliano(2024, 6, 21, 0.0);
    $t = $swe->levataTramonto($mezzanotte, Corpi::SOLE, Sweph::CULMINAZIONE, 45.4642, 9.19, 122.0);
    $p = $swe->posizione($t, Corpi::SOLE);
    $o = $swe->orizzonte($t, $p['lon'], $p['lat'], $p['dist'], 45.4642, 9.19, 122.0);

    return vicino($o['azimut'], 180.0, 0.5);
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Case ══\033[0m\n";

prova('Segni Interi: ogni cuspide cade su un confine di segno', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(1978, 6, 12, 21.2333333);
    $c  = $swe->case($jd, 45.4642, 9.19, 'W');
    foreach ($c['cuspidi'] as $i => $cu) {
        if (abs(fmod($cu, 30.0)) > 1e-6) {
            return sprintf('cuspide %d a %.6f, non su un confine', $i + 1, $cu);
        }
    }

    return true;
});

prova('Le dodici ampiezze di casa sommano a 360°', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(1978, 6, 12, 21.2333333);
    $c  = $swe->case($jd, 45.4642, 9.19, 'P');
    $somma = 0.0;
    for ($i = 0; $i < 12; $i++) {
        $somma += Corpi::norma($c['cuspidi'][($i + 1) % 12] - $c['cuspidi'][$i]);
    }

    return vicino($somma, 360.0, 1e-6);
});

prova('Case opposte distano esattamente 180°', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(1978, 6, 12, 21.2333333);
    $c  = $swe->case($jd, 45.4642, 9.19, 'P');
    for ($i = 0; $i < 6; $i++) {
        $d = Corpi::distanza($c['cuspidi'][$i], $c['cuspidi'][$i + 6]);
        if (abs($d - 180.0) > 1e-6) {
            return sprintf('case %d e %d distano %.9f°', $i + 1, $i + 7, $d);
        }
    }

    return true;
});

prova('Placido oltre il circolo polare viene segnalato come degenere', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(2024, 6, 21, 12.0);
    $c  = $swe->case($jd, 78.22, 15.63, 'P');

    return $c['degenere'] === true ? true : 'non segnalato a latitudine 78,22°';
});

prova('Ogni corpo finisce in una casa fra 1 e 12', static function () use ($swe) {
    $jd = $swe->giornoGiuliano(1978, 6, 12, 21.2333333);
    $c  = $swe->case($jd, 45.4642, 9.19, 'P');
    for ($g = 0.0; $g < 360.0; $g += 0.37) {
        $casa = Tema::casaDi($g, $c['cuspidi']);
        if ($casa < 1 || $casa > 12) {
            return sprintf('longitudine %.2f -> casa %d', $g, $casa);
        }
    }

    return true;
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Parte di Fortuna ══\033[0m\n";

prova('Carta diurna: Fortuna = ASC + Luna - Sole', static function () use ($swe, $effemeridi, $libreria) {
    // Milano, 12 giugno 1978 alle 10:00 UT: il Sole e' alto, la carta e' diurna.
    $w = new Worker(new Sweph($libreria, $effemeridi));
    $r = $w->esegui(['operazione' => 'tema', 'anno' => 1978, 'mese' => 6, 'giorno' => 12,
                     'ora_ut' => 10.0, 'lat' => 45.4642, 'lon' => 9.19, 'alt' => 122,
                     'sistema_case' => 'placido', 'stelle' => false, 'effemeridi_giorno' => false,
                     'sizigia' => false, 'fenomeni' => false]);
    if (!$r['carta']['diurna']) {
        return 'alle 10:00 UT la carta dovrebbe essere diurna';
    }
    $atteso = Corpi::norma($r['case']['asc'] + $r['corpi']['luna']['lon'] - $r['corpi']['sole']['lon']);

    return vicino($r['punti']['fortuna']['lon'], $atteso, 1e-9);
});

prova('Carta notturna: Fortuna = ASC + Sole - Luna (formula invertita)', static function () use ($libreria, $effemeridi) {
    $w = new Worker(new Sweph($libreria, $effemeridi));
    $r = $w->esegui(['operazione' => 'tema', 'anno' => 1978, 'mese' => 6, 'giorno' => 12,
                     'ora_ut' => 21.2333333, 'lat' => 45.4642, 'lon' => 9.19, 'alt' => 122,
                     'sistema_case' => 'placido', 'stelle' => false, 'effemeridi_giorno' => false,
                     'sizigia' => false, 'fenomeni' => false]);
    if ($r['carta']['diurna']) {
        return 'alle 21:14 UT la carta dovrebbe essere notturna';
    }
    $atteso = Corpi::norma($r['case']['asc'] + $r['corpi']['sole']['lon'] - $r['corpi']['luna']['lon']);

    return vicino($r['punti']['fortuna']['lon'], $atteso, 1e-9);
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Dignita' ══\033[0m\n";

$dignitaNote = [
    ['sole', 130.0, true,  8,  'Sole in Leone di giorno: domicilio + triplicita\''],
    ['marte', 5.0,  true,  6,  'Marte a 5° Ariete: domicilio + decano'],
    ['venere', 5.0, true, -5,  'Venere a 5° Ariete: esilio'],
    ['saturno', 200.0, true, 7, 'Saturno a 20° Bilancia: esaltazione + triplicita\''],
    ['luna', 95.0, false,  6,  'Luna a 5° Cancro di notte: domicilio + partecipante'],
];
foreach ($dignitaNote as [$c, $lon, $diurna, $atteso, $eti]) {
    prova($eti . ' = ' . sprintf('%+d', $atteso), static function () use ($c, $lon, $diurna, $atteso) {
        $p = Dignita::essenziali($c, $lon, $diurna)['punteggio'];

        return $p === $atteso ? true : "ottenuto {$p}";
    });
}

prova('Ogni grado dello zodiaco ha un termine e un decano', static function () {
    for ($g = 0.0; $g < 360.0; $g += 0.25) {
        $t = Dignita::termine(Corpi::segnoDi($g), $g - Corpi::segnoDi($g) * 30.0);
        $d = Dignita::decano($g);
        if ($t === '' || $d === '') {
            return sprintf('grado %.2f scoperto', $g);
        }
    }

    return true;
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Aspetti ══\033[0m\n";

prova('Applicativo con moto diretto', static fn () => Aspetti::applicativo(8.0, 0.6, 99.0, 0.03, 90.0) === true ?: 'atteso true');
prova('Separativo con moto diretto',  static fn () => Aspetti::applicativo(10.0, 0.6, 99.0, 0.03, 90.0) === false ?: 'atteso false');
prova('Applicativo con moto retrogrado', static fn () => Aspetti::applicativo(10.0, -0.4, 99.0, 0.0, 90.0) === true ?: 'atteso true');
prova('Fra due punti fissi la domanda non si pone', static fn () => Aspetti::applicativo(10.0, 0.0, 100.0, 0.0, 90.0) === null ?: 'atteso null');

prova('L\'orbe non scavalca mai il massimo ammesso', static function () {
    $punti = [];
    for ($i = 0; $i < 10; $i++) {
        $punti['c' . $i] = ['lon' => $i * 37.3, 'vel' => 1.0, 'nome' => 'C' . $i];
    }
    foreach (Aspetti::calcola($punti, ['maggiore', 'minore']) as $a) {
        if ($a['orbe'] > $a['orbe_massimo'] + 1e-9) {
            return sprintf('%s-%s orbe %.4f > massimo %.4f', $a['a'], $a['b'], $a['orbe'], $a['orbe_massimo']);
        }
        if ($a['forza'] < 0.0 || $a['forza'] > 1.0) {
            return sprintf('forza fuori scala: %.4f', $a['forza']);
        }
    }

    return true;
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Bilanci ══\033[0m\n";

prova('Il peso totale dei bilanci e\' sempre lo stesso', static function () {
    $punti = [];
    foreach (array_merge(Corpi::dieci(), ['asc', 'mc']) as $i => $c) {
        $punti[$c] = ['lon' => $i * 31.7];
    }
    $b = Bilanci::calcola($punti);
    $somma = array_sum($b['elementi']);

    // 2+2+2 (Sole, Luna, ASC) + 5 x 1 + 3 x 0,5 + 1 (MC) = 13,5
    return vicino($somma, 13.5, 1e-9, '')
        && vicino(array_sum($b['modalita']), 13.5, 1e-9, '');
});

prova('Figura planetaria: dieci corpi in un quarto di cielo = Fascio', static function () {
    $corpi = [];
    foreach (Corpi::dieci() as $i => $c) {
        $corpi[$c] = ['lon' => 10.0 + $i * 10.0];   // ampiezza 90°
    }

    $f = Bilanci::figura($corpi);

    return $f['tipo'] === 'fascio' ? true : 'ottenuto ' . $f['tipo'] . ' (ampiezza ' . $f['ampiezza'] . '°)';
});

prova('Figura planetaria: dieci corpi sparsi = Spruzzo', static function () {
    $corpi = [];
    foreach (Corpi::dieci() as $i => $c) {
        $corpi[$c] = ['lon' => $i * 36.0];   // perfettamente distribuiti
    }

    $f = Bilanci::figura($corpi);

    return $f['tipo'] === 'spruzzo' ? true : 'ottenuto ' . $f['tipo'] . ' (vuoto massimo ' . $f['vuoto_massimo'] . '°)';
});

prova('Dispositore finale: un pianeta nel proprio domicilio', static function () {
    // Mercurio in Gemelli governa se stesso; tutto il resto ricade su di lui.
    $corpi = ['mercurio' => ['lon' => 79.5], 'sole' => ['lon' => 81.5], 'luna' => ['lon' => 159.4]];
    $d = Bilanci::dispositori($corpi);

    return $d['dispositori_finali'] === ['mercurio'] ? true : json_encode($d['dispositori_finali']);
});

// ---------------------------------------------------------------------------
echo "\n\033[1;36m══ Contratto del lavoratore ══\033[0m\n";

// Astro\Motore considera fallita qualunque risposta senza `ok => true`.
// La regola vale per OGNI operazione, non solo per quelle che calcolano: e'
// gia' costata un comando di console che non funzionava mentre il motore
// sottostante andava benissimo.
$operazioni = [
    'stato'     => ['operazione' => 'stato'],
    'posizioni' => ['operazione' => 'posizioni', 'anno' => 2024, 'mese' => 1, 'giorno' => 1, 'ora_ut' => 12.0],
    'tema'      => ['operazione' => 'tema', 'anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 21.23,
                    'lat' => 45.4642, 'lon' => 9.19, 'alt' => 122, 'sistema_case' => 'placido',
                    'stelle' => false, 'effemeridi_giorno' => false, 'sizigia' => false],
];
foreach ($operazioni as $nome => $domanda) {
    prova("L'operazione «{$nome}» restituisce ok = true", static function () use ($libreria, $effemeridi, $domanda) {
        $r = (new Worker(new Sweph($libreria, $effemeridi)))->esegui($domanda);

        return ($r['ok'] ?? null) === true ? true : 'campo ok: ' . var_export($r['ok'] ?? null, true);
    });
}

prova('Un\'operazione sconosciuta solleva un errore', static function () use ($libreria, $effemeridi) {
    try {
        (new Worker(new Sweph($libreria, $effemeridi)))->esegui(['operazione' => 'inesistente']);
    } catch (\Throwable) {
        return true;
    }

    return 'non ha sollevato niente';
});

prova('Un sistema di case sconosciuto solleva un errore', static function () use ($libreria, $effemeridi) {
    try {
        (new Worker(new Sweph($libreria, $effemeridi)))->esegui([
            'operazione' => 'tema', 'anno' => 2000, 'mese' => 1, 'giorno' => 1, 'ora_ut' => 12.0,
            'lat' => 45.0, 'lon' => 9.0, 'sistema_case' => 'inventato',
        ]);
    } catch (\Throwable) {
        return true;
    }

    return 'non ha sollevato niente';
});

// ---------------------------------------------------------------------------
printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
