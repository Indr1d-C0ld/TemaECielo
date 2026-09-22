<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove della catena del fuso orario storico.
 *
 *   php tests/test_tempo.php
 *
 * Questi sono i casi che fanno sbagliare i calcolatori di temi natali: l'ora
 * legale italiana irregolare della prima meta' del Novecento, l'ora locale
 * media prima del 1893, e le due ore all'anno che non identificano un istante.
 */

require dirname(__DIR__) . '/src/autoload.php';

use App\Luogo\Tempo;

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

function attesoOffset(array $r, int $ore, ?bool $dst = null): bool|string
{
    if (!($r['ok'] ?? false)) {
        return 'non risolto: ' . ($r['errore'] ?? '?');
    }
    if ($r['offset_secondi'] !== $ore * 3600) {
        return sprintf('offset %s, atteso %+d:00', $r['offset_testo'], $ore);
    }
    if ($dst !== null && $r['ora_legale'] !== $dst) {
        return 'ora legale ' . var_export($r['ora_legale'], true) . ', attesa ' . var_export($dst, true);
    }

    return true;
}

echo "\n\033[1;36m══ Italia: ora legale attraverso la storia ══\033[0m\n";

// Ogni riga: data, ora, offset atteso in ore, ora legale attesa, nota.
$italia = [
    ['1978-06-12', '23:14', 2, true,  'estate 1978, ora legale in vigore'],
    ['1978-01-15', '10:00', 1, false, 'inverno 1978, ora solare'],
    ['2024-08-15', '12:00', 2, true,  'oggi, estate'],
    ['2024-01-15', '12:00', 1, false, 'oggi, inverno'],
    // L'Italia sospese l'ora legale dal 1949 al 1965: un giugno di quegli anni
    // e' UTC+1, non UTC+2. E' l'errore classico su chi e' nato in quel periodo.
    ['1955-06-15', '14:30', 1, false, 'giugno 1955: ora legale ABOLITA in Italia'],
    ['1960-07-20', '09:00', 1, false, 'luglio 1960: ancora nessuna ora legale'],
    ['1966-06-15', '14:30', 2, true,  'giugno 1966: ora legale reintrodotta'],
    // Gli anni di guerra, con regole proprie.
    ['1943-06-15', '14:30', 2, true,  'giugno 1943, in guerra'],
    ['1942-12-15', '08:00', 1, false, 'dicembre 1942'],
    ['1917-06-15', '12:00', 2, true,  'giugno 1917, prima guerra'],
];

foreach ($italia as [$d, $o, $off, $dst, $nota]) {
    prova(sprintf('%s %s  →  %+d:00  (%s)', $d, $o, $off, $nota),
        static fn () => attesoOffset(Tempo::risolvi($d, $o, 'Europe/Rome'), $off, $dst));
}

echo "\n\033[1;36m══ Prima dei fusi orari ══\033[0m\n";

// L'Italia adotto' il fuso dell'Europa centrale il 1 novembre 1893. Prima,
// Roma andava a ora locale media: +0:49:56 rispetto a Greenwich.
prova('1880: Roma va a ora locale media, non a UTC+1', static function () {
    $r = Tempo::risolvi('1880-05-10', '12:00', 'Europe/Rome');
    if (!$r['ok']) { return 'non risolto'; }
    if ($r['offset_secondi'] === 3600) { return 'ha applicato UTC+1: il tzdata storico non e\' stato usato'; }

    return abs($r['offset_secondi'] - 2996) <= 5
        ? true
        : sprintf('offset %s (%d s), atteso circa +00:49:56', $r['offset_testo'], $r['offset_secondi']);
});

prova('Ora locale media da longitudine: Milano 9,19°E = +00:36:46', static function () {
    $r = Tempo::oraLocaleMedia('1850-03-01', '12:00', 9.19);

    return abs($r['offset_secondi'] - 2206) <= 2
        ? true
        : sprintf('offset %s (%d s)', $r['offset_testo'], $r['offset_secondi']);
});

prova('Ora locale media a ovest di Greenwich ha offset negativo', static function () {
    $r = Tempo::oraLocaleMedia('1850-03-01', '12:00', -74.0);

    return $r['offset_secondi'] < 0 && abs($r['offset_secondi'] + 17760) <= 2
        ? true
        : sprintf('offset %s', $r['offset_testo']);
});

echo "\n\033[1;36m══ Le due ore all'anno che non identificano un istante ══\033[0m\n";

prova('Ora AMBIGUA: 27 ottobre 2024, 02:30 in Italia esiste due volte', static function () {
    $r = Tempo::risolvi('2024-10-27', '02:30', 'Europe/Rome');
    if ($r['stato'] !== Tempo::AMBIGUO) { return 'stato ' . $r['stato'] . ', atteso ambiguo'; }
    if (count($r['alternative']) !== 2) { return count($r['alternative']) . ' alternative, attese 2'; }
    // La prima deve essere quella ancora in ora legale.
    if ($r['alternative'][0]['offset_secondi'] !== 7200) { return 'la prima alternativa non e\' +02:00'; }
    if ($r['alternative'][1]['offset_secondi'] !== 3600) { return 'la seconda alternativa non e\' +01:00'; }
    // I due istanti devono distare esattamente un'ora.
    $d = strtotime($r['alternative'][1]['utc']) - strtotime($r['alternative'][0]['utc']);

    return $d === 3600 ? true : "i due istanti distano {$d} s, attesi 3600";
});

prova('Ora INESISTENTE: 31 marzo 2024, 02:30 in Italia e\' stata saltata', static function () {
    $r = Tempo::risolvi('2024-03-31', '02:30', 'Europe/Rome');
    if ($r['stato'] !== Tempo::INESISTENTE) { return 'stato ' . $r['stato'] . ', atteso inesistente'; }
    if (($r['ok'] ?? true) !== false) { return 'non e\' stato marcato come non risolto'; }
    if (($r['proposta_ora'] ?? '') !== '03:00') { return 'proposta ' . ($r['proposta_ora'] ?? '—') . ', attesa 03:00'; }

    return true;
});

prova('Un\'ora normale non viene mai dichiarata ambigua', static function () {
    foreach (['2024-06-15 12:00', '2024-01-15 12:00', '1978-06-12 23:14'] as $q) {
        [$d, $o] = explode(' ', $q);
        $r = Tempo::risolvi($d, $o, 'Europe/Rome');
        if ($r['stato'] !== Tempo::UNICO) {
            return "{$q} dichiarata {$r['stato']}";
        }
    }

    return true;
});

echo "\n\033[1;36m══ Fusi di altri paesi ══\033[0m\n";

$altri = [
    ['America/New_York', '2024-07-04', '12:00', -4, true,  'New York d\'estate: EDT'],
    ['America/New_York', '2024-01-04', '12:00', -5, false, 'New York d\'inverno: EST'],
    ['Asia/Tokyo',       '2024-07-04', '12:00',  9, false, 'Tokyo: nessuna ora legale'],
    ['Australia/Sydney', '2024-01-15', '12:00', 11, true,  'Sydney a gennaio: e\' estate'],
    ['Australia/Sydney', '2024-07-15', '12:00', 10, false, 'Sydney a luglio: e\' inverno'],
    ['UTC',              '2024-07-04', '12:00',  0, false, 'UTC'],
];
foreach ($altri as [$z, $d, $o, $off, $dst, $nota]) {
    prova(sprintf('%-20s %s  →  %+d:00  (%s)', $z, $d, $off, $nota),
        static fn () => attesoOffset(Tempo::risolvi($d, $o, $z), $off, $dst));
}

// India e Nepal hanno offset non interi: se l'aritmetica fosse in ore intere,
// qui si romperebbe tutto.
prova('India: offset di mezz\'ora (+05:30)', static function () {
    $r = Tempo::risolvi('2024-07-04', '12:00', 'Asia/Kolkata');

    return $r['offset_secondi'] === 19800 ? true : 'offset ' . $r['offset_testo'];
});

prova('Nepal: offset di tre quarti d\'ora (+05:45)', static function () {
    $r = Tempo::risolvi('2024-07-04', '12:00', 'Asia/Kathmandu');

    return $r['offset_secondi'] === 20700 ? true : 'offset ' . $r['offset_testo'];
});

echo "\n\033[1;36m══ Conversione in Tempo Universale ══\033[0m\n";

prova('12 giugno 1978, 23:14 a Milano = 21:14 UT', static function () {
    $r = Tempo::risolvi('1978-06-12', '23:14', 'Europe/Rome');
    $c = $r['componenti_ut'];

    return ($c['anno'] === 1978 && $c['mese'] === 6 && $c['giorno'] === 12 && abs($c['ora_ut'] - 21.2333333) < 1e-4)
        ? true
        : sprintf('%d-%02d-%02d %.6f UT', $c['anno'], $c['mese'], $c['giorno'], $c['ora_ut']);
});

prova('Una nascita poco dopo mezzanotte scavalca il giorno all\'indietro', static function () {
    // 1 gennaio 2024 alle 00:30 a Roma = 31 dicembre 2023 alle 23:30 UT
    $c = Tempo::risolvi('2024-01-01', '00:30', 'Europe/Rome')['componenti_ut'];

    return ($c['anno'] === 2023 && $c['mese'] === 12 && $c['giorno'] === 31 && abs($c['ora_ut'] - 23.5) < 1e-6)
        ? true
        : sprintf('%d-%02d-%02d %.4f UT', $c['anno'], $c['mese'], $c['giorno'], $c['ora_ut']);
});

prova('Una nascita serale a Tokyo scavalca il giorno in avanti', static function () {
    // 1 gennaio 2024 alle 23:30 a Tokyo = 1 gennaio alle 14:30 UT (stesso giorno)
    // 1 gennaio 2024 alle 08:00 a Tokyo = 31 dicembre 2023 alle 23:00 UT
    $c = Tempo::risolvi('2024-01-01', '08:00', 'Asia/Tokyo')['componenti_ut'];

    return ($c['anno'] === 2023 && $c['mese'] === 12 && $c['giorno'] === 31 && abs($c['ora_ut'] - 23.0) < 1e-6)
        ? true
        : sprintf('%d-%02d-%02d %.4f UT', $c['anno'], $c['mese'], $c['giorno'], $c['ora_ut']);
});

echo "\n\033[1;36m══ Ingressi malformati ══\033[0m\n";

prova('Fuso inventato: errore, non eccezione', static fn () => (Tempo::risolvi('2024-01-01', '12:00', 'Europa/Milano')['ok'] ?? true) === false ?: 'ha accettato il fuso');
prova('31 febbraio: errore', static fn () => (Tempo::risolvi('2024-02-31', '12:00', 'Europe/Rome')['ok'] ?? true) === false ?: 'ha accettato la data');
prova('Ora 25:00: errore', static fn () => (Tempo::risolvi('2024-01-01', '25:00', 'Europe/Rome')['ok'] ?? true) === false ?: 'ha accettato l\'ora');

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
