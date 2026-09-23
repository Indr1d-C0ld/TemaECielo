<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove della revisione generale.
 *
 *   php tests/test_revisione.php
 *
 * Ogni prova qui dentro corrisponde a un difetto vero, trovato durante la
 * revisione completa del portale e corretto. Non sono prove «di copertura»:
 * sono la memoria di cosa si era rotto, perche' non si rompa di nuovo senza
 * che nessuno se ne accorga. Nessuna lascia traccia nel database: quelle che
 * scrivono lo fanno dentro una transazione che poi annullano.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Aspetti;
use App\Astro\Bilanci;
use App\Astro\Configurazioni;
use App\Astro\Corpi;
use App\Astro\Tema;
use App\Core\Config;
use App\Core\Database;
use App\Core\Vista;
use App\Corpus\Corpus;
use App\Corpus\Lingua;
use App\Corpus\Montatore;
use App\Grafica\Svg;
use App\Luogo\Tempo;
use App\Support\Rete;

$radice = dirname(__DIR__);
$GLOBALS['__project_root'] = $radice;
Config::load($radice);
Vista::percorso($radice . '/views');

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

/** Il worker delle effemeridi, chiamato direttamente: niente cache, niente database. */
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

function carta(array $extra = []): array
{
    return Tema::componi(worker($extra + [
        'operazione' => 'tema', 'anno' => 1990, 'mese' => 6, 'giorno' => 15, 'ora_ut' => 10.0,
        'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 120, 'sistema_case' => 'placido',
    ]));
}

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ Le viste ricevono i loro dati ══\033[0m\n";

/* `Vista::rendi` faceva extract() accanto alle proprie variabili locali
   $nome, $dati e $file: le chiavi con quei nomi venivano scartate, e il modulo
   dell'ora ambigua non riceveva mai i dati di nascita. */
prova('Una chiave «dati» arriva alla vista, e non il pacchetto intero', static function () {
    $tempo = Tempo::risolvi('2026-10-25', '02:30:00', 'Europe/Rome');
    $h = Vista::rendi('ora-ambigua', ['tempo' => $tempo, 'dati' => [
        'nome' => 'Prova', 'data' => '2026-10-25', 'ora' => '02:30', 'precisione' => 'esatta',
        'luogo_id' => 3169070, 'luogo_nome' => 'Roma', 'lat' => 41.89, 'lon' => 12.51,
        'altitudine' => 20, 'fuso' => 'Europe/Rome', 'sistema' => 'placido',
    ]]);
    return (str_contains($h, 'name="data" value="2026-10-25"') && str_contains($h, 'name="fuso" value="Europe/Rome"'))
        ?: 'i campi nascosti non portano i dati di nascita';
});

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ Il disegno ══\033[0m\n";

/* Il ritorno del settore riusava arco() con gli estremi scambiati: 330 gradi
   invece di 30, e ogni spicchio dello zodiaco colorava tutto il disco tranne sé. */
prova('Un settore di 30 gradi ha entrambi gli archi corti, andata e ritorno', static function () {
    $d = Svg::settore(410, 410, 300, 340, 0.0, 30.0);
    preg_match_all('/A [\d.]+ [\d.]+ 0 (\d) (\d)/', $d, $m, PREG_SET_ORDER);
    if (count($m) !== 2) { return 'archi trovati: ' . count($m); }
    return ($m[0][1] === '0' && $m[1][1] === '0' && $m[0][2] !== $m[1][2])
        ?: 'bandiere degli archi: ' . json_encode($m);
});

prova('I quattro asteroidi hanno glifi propri, non quello della Parte di Fortuna', static function () {
    $sprite = (string) file_get_contents(dirname(__DIR__) . '/assets/img/glifi.svg');
    foreach (['cerere', 'pallade', 'giunone', 'vesta'] as $a) {
        if ((Corpi::elenco()[$a]['glifo'] ?? '') !== $a) { return "{$a} usa il glifo " . (Corpi::elenco()[$a]['glifo'] ?? '?'); }
        if (!str_contains($sprite, 'id="gl-' . $a . '"')) { return "manca il simbolo gl-{$a}"; }
    }
    return true;
});

prova('Lo sprite dei glifi ha dimensione zero (niente riquadro vuoto in cima alle pagine)', static function () {
    $sprite = (string) file_get_contents(dirname(__DIR__) . '/assets/img/glifi.svg');
    return preg_match('/<svg[^>]*width="0"[^>]*height="0"/', $sprite) === 1 ?: 'lo sprite non ha width/height 0';
});

prova('I nomi sulla volta non si sovrappongono', static function () {
    $v = (new \App\Grafica\VoltaCeleste(carta(['anno' => 2026, 'mese' => 1, 'giorno' => 15, 'ora_ut' => 21.0,
        'lat' => 41.9, 'lon' => 12.5])))->disegna();
    preg_match('#<g class="nomi-stelle".*?</g>#s', $v, $g);
    preg_match_all('#<text x="([\d.]+)" y="([\d.]+)"(?: text-anchor="(\w+)")?>([^<]+)</text>#', $g[0] ?? '', $m, PREG_SET_ORDER);
    $scatole = [];
    foreach ($m as [, $x, $y, $anc, $t]) {
        $w = mb_strlen(html_entity_decode($t)) * 10 * 0.56;
        $x0 = $anc === 'end' ? $x - $w : ($anc === 'middle' ? $x - $w / 2 : (float) $x);
        foreach ($scatole as [$a0, $b0, $a1, $b1, $n]) {
            if ($x0 < $a1 && $x0 + $w > $a0 && $y - 8.2 < $b1 && $y + 2.8 > $b0) { return "«{$t}» sopra «{$n}»"; }
        }
        $scatole[] = [$x0, $y - 8.2, $x0 + $w, $y + 2.8, $t];
    }
    return count($scatole) > 5 ?: 'troppo pochi nomi: ' . count($scatole);
});

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ Il calcolo ══\033[0m\n";

prova('«Equale dal MC» chiede alla libreria il sistema D, e le case sono di 30 gradi', static function () {
    if (Corpi::sistemiCase()['equale_mc']['codice'] !== 'D') { return 'codice ' . Corpi::sistemiCase()['equale_mc']['codice']; }
    $c = array_values(carta(['sistema_case' => 'equale_mc'])['case']['cuspidi']);
    for ($i = 0; $i < 12; $i++) {
        $l = fmod($c[($i + 1) % 12] - $c[$i] + 720, 360);
        if (abs($l - 30.0) > 1e-6) { return "casa " . ($i + 1) . " larga {$l}"; }
    }
    return true;
});

prova('Un aspetto che diventa esatto fra pochi minuti e\' applicativo', static function () {
    return Aspetti::applicativo(0.0, 0.985, 89.9, 13.0, 90.0) === true ?: 'risulta separativo';
});

prova('Il secchio si riconosce, anche allo specchio', static function () {
    $k = Corpi::dieci();
    foreach ([[0, 20, 40, 60, 80, 100, 120, 140, 150, 250], [110, 130, 150, 170, 190, 210, 230, 250, 260, 20]] as $l) {
        $f = Bilanci::figura(array_combine($k, array_map(static fn ($x) => ['lon' => (float) $x], $l)));
        if ($f['tipo'] !== 'secchio') { return 'riconosciuto come ' . $f['tipo']; }
    }
    return true;
});

prova('Lo Yod si trova, benche\' la quinconce sia un aspetto minore', static function () {
    $p = ['sole' => ['lon' => 10.0, 'nome' => 'Sole'], 'luna' => ['lon' => 70.0, 'nome' => 'Luna'],
          'marte' => ['lon' => 220.5, 'nome' => 'Marte']];
    $asp = Aspetti::calcola(array_map(static fn ($x) => $x + ['vel' => 0.0], $p), ['maggiore']);
    foreach (Configurazioni::trova($asp, $p) as $c) { if ($c['tipo'] === 'yod') { return true; } }
    return 'nessuno Yod';
});

prova('Un grado non scrive mai «30°»', static function () {
    foreach ([29.99999, 359.99999, 59.9999999] as $l) {
        if (str_starts_with(Corpi::formatta($l), '30°')) { return Corpi::formatta($l); }
    }
    return true;
});

prova('Con l\'ora ignota gli assi segnaposto non entrano negli aspetti ne\' negli emisferi', static function () {
    $t = carta(['ora_ignota' => true, 'sistema_case' => 'segni_interi']);
    foreach ($t['aspetti']['elenco'] as $a) {
        if (in_array($a['a'], ['asc', 'mc'], true) || in_array($a['b'], ['asc', 'mc'], true)) { return 'aspetto con ' . $a['a'] . '/' . $a['b']; }
    }
    if ($t['bilanci']['emisferi'] !== null) { return 'emisferi calcolati'; }
    return in_array('setta', $t['carta']['inattendibili'], true) ?: 'la setta non e\' dichiarata inattendibile';
});

prova('Alba e tramonto sono dello stesso giorno civile (Tokyo, 8 del mattino)', static function () {
    $o = worker(['operazione' => 'tema', 'anno' => 2000, 'mese' => 3, 'giorno' => 9, 'ora_ut' => 23.0,
        'lat' => 35.68, 'lon' => 139.69, 'alt' => 0, 'sistema_case' => 'placido', 'offset_secondi' => 32400]);
    $a = (float) ($o['giorno']['sole']['levata']['jd'] ?? $o['giorno']['sole']['levata'] ?? 0);
    $b = (float) ($o['giorno']['sole']['tramonto']['jd'] ?? $o['giorno']['sole']['tramonto'] ?? 0);
    return ($b > $a && $b - $a < 0.6) ?: sprintf('giornata di %.2f ore', ($b - $a) * 24);
});

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ Il tempo prima dei fusi ══\033[0m\n";

prova('Milano 1880: ora locale media di Milano, non ora di Roma', static function () {
    $r = Tempo::risolviNelLuogo('1880-05-10', '12:00:00', 'Europe/Rome', 9.18951);
    return ($r['abbreviazione'] === 'LMT' && $r['offset_testo'] === '+00:36:45') ?: $r['offset_testo'] . ' ' . $r['abbreviazione'];
});

prova('Francia 1895: l\'ora di Parigi era gia\' legale, e resta tale', static function () {
    $r = Tempo::risolviNelLuogo('1895-05-10', '12:00:00', 'Europe/Paris', 5.37);
    return $r['abbreviazione'] === 'PMT' ?: $r['abbreviazione'];
});

prova('Dopo il 1893 l\'Italia e\' a ora dell\'Europa centrale', static function () {
    $r = Tempo::risolviNelLuogo('1900-05-10', '12:00:00', 'Europe/Rome', 9.18951);
    return $r['abbreviazione'] === 'CET' ?: $r['abbreviazione'];
});

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ L'italiano composto ══\033[0m\n";

prova('Gli aggettivi si accordano col soggetto', static function () {
    $v = (new Corpus('tradizionale'))->aspetto('luna', 'venere', 'congiunzione');
    return ($v !== null && $v->titolo === 'La Luna congiunta a Venere' && str_contains($v->corpo, "e' congiunta a"))
        ?: ($v?->titolo . ' / ' . mb_substr((string) $v?->corpo, 0, 40));
});

prova('Il titolo di un aspetto porta articolo e preposizione articolata', static function () {
    $v = (new Corpus('moderno'))->aspetto('mercurio', 'sole', 'congiunzione');
    return $v?->titolo === 'Mercurio unito al Sole' ?: (string) $v?->titolo;
});

prova('Nessuna graffa di flessione arriva in pagina, in nessuna combinazione', static function () {
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        foreach (Corpi::dieci() as $p) {
            for ($s = 0; $s < 12; $s++) {
                $v = $c->pianetaInSegno($p, $s);
                if ($v !== null && preg_match('/[{}|]/', $v->titolo . $v->corpo)) { return "{$reg} {$p}/{$s}: " . $v->corpo; }
            }
            foreach (['caduta', 'esilio', 'domicilio', 'esaltazione', 'peregrino', 'retrogrado', 'combusto', 'cazimi'] as $d) {
                $v = $c->scritta('dignita', $d, [$p], $p);
                if ($v !== null && preg_match('/[{}|]/', $v->titolo . $v->corpo)) { return "{$reg} {$d}/{$p}: " . $v->corpo; }
            }
        }
    }
    return true;
});

prova('La stessa condizione su due pianeti non ripete il paragrafo', static function () {
    $l = (new Montatore(new Corpus('moderno')))->monta(carta(['anno' => 2026, 'mese' => 9, 'giorno' => 1, 'ora_ut' => 12.0]));
    $visti = [];
    foreach ($l['sezioni'] as $s) {
        foreach ($s['voci'] as $v) {
            $chiave = preg_replace('/\b[A-Z][a-z]+\b/u', 'X', $v->corpo);
            if (isset($visti[$chiave])) { return 'ripetuto: ' . $v->titolo; }
            $visti[$chiave] = true;
        }
    }
    return true;
});

prova('Davanti a un nome, la preposizione si contrae con l\'articolo', static function () {
    return Lingua::inserisci('i pianeti di %s', 'la seconda carta') === 'i pianeti della seconda carta'
        ?: Lingua::inserisci('i pianeti di %s', 'la seconda carta');
});

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ Accesso e freni ══\033[0m\n";

prova('Dopo l\'accesso si va solo a percorsi di questo portale', static function () {
    $m = new ReflectionMethod(\App\Admin\AccessoController::class, 'destinazione');
    foreach (['//altrove.com', '/\\altrove.com', 'https://altrove.com', 'javascript:alert(1)'] as $d) {
        if ($m->invoke(null, $d) !== '/admin') { return "accettato: {$d}"; }
    }
    return $m->invoke(null, '/admin/corpus?ambito=pianeta') === '/admin/corpus?ambito=pianeta' ?: 'rifiutato un percorso buono';
});

prova('L\'impronta finta e\' un Argon2id vero, gia\' calcolato', static function () {
    $h = (new ReflectionClassConstant(\App\Auth\Auth::class, 'HASH_FINTO'))->getValue();
    return password_get_info($h)['algoName'] === 'argon2id' ?: 'algoritmo ' . password_get_info($h)['algoName'];
});

prova('Un nome utente lungo e con caratteri invisibili viene comunque contato', static function () {
    Database::pdo()->beginTransaction();
    try {
        $ip = '198.51.100.250';
        $prima = (int) Database::valore('SELECT COUNT(*) FROM accessi_admin WHERE ip = ?', [$ip]);
        \App\Auth\Auth::entra('admin' . str_repeat("\u{200B}", 20), 'sbagliata', $ip);
        $dopo = (int) Database::valore('SELECT COUNT(*) FROM accessi_admin WHERE ip = ?', [$ip]);
        return $dopo === $prima + 1 ?: 'il tentativo non e\' stato registrato';
    } finally {
        Database::pdo()->rollBack();
    }
});

prova('Il freno ferma la richiesta oltre il tetto', static function () {
    Database::pdo()->beginTransaction();
    try {
        $esiti = [];
        for ($i = 0; $i < 3; $i++) { $esiti[] = \App\Support\Freno::consenti('prova', 2, '198.51.100.251'); }
        return $esiti === [true, true, false] ?: json_encode($esiti);
    } finally {
        Database::pdo()->rollBack();
    }
});

prova('In IPv6 si conta la rete /64, non l\'indirizzo', static function () {
    return Rete::chiaveCliente('2a01:4f8:1:2:3:4:5:6') === Rete::chiaveCliente('2a01:4f8:1:2:ffff::1')
        ?: Rete::chiaveCliente('2a01:4f8:1:2:3:4:5:6');
});

// ─────────────────────────────────────────────────────────────────────────────
echo "\n\033[1;36m══ Carte e statistiche ══\033[0m\n";

prova('Ogni carta ha un solo soggetto «primo»', static function () {
    $n = (int) Database::valore("SELECT COUNT(*) FROM (SELECT calcolo_id FROM calcoli_soggetti WHERE ruolo = 'primo'
                                  GROUP BY calcolo_id HAVING COUNT(*) > 1) t");
    return $n === 0 ?: "{$n} carte condivise fra piu' persone";
});

prova('Le statistiche contano le carte dei visitatori: non la cache del motore, non l\'archivio', static function () {
    $m = new ReflectionMethod(\App\Controllers\StatisticheController::class, 'generale');
    $g = $m->invoke(new \App\Controllers\StatisticheController());
    $vere = (int) Database::valore("SELECT COUNT(*) FROM calcoli WHERE tipo = 'natale' AND gettone IS NOT NULL
                                     AND id NOT IN (SELECT calcolo_id FROM archivio)");
    return $g['carte'] === $vere ?: "dice {$g['carte']}, sono {$vere}";
});

printf(
    "\n%s%d passate, %d fallite\033[0m\n\n",
    $fallite === 0 ? "\033[0;32m" : "\033[1;31m",
    $passate,
    $fallite,
);

exit($fallite === 0 ? 0 : 1);
