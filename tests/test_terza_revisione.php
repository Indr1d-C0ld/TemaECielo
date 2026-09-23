<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove della terza revisione.
 *
 *   php tests/test_terza_revisione.php
 *
 * Una prova per ogni difetto trovato nella revisione che ha seguito l'archivio
 * e la sezione Mondo. Quelle che scrivono nel database lo fanno dentro una
 * transazione che poi annullano.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Corpi;
use App\Controllers\MondoController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Response;
use App\Corpus\Corpus;
use App\Mondo\Mondo;
use App\Support\Manutenzione;
use App\Support\Markdown;

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

echo "\n== Calcolo ==\n";

prova('Una longitudine di -1e-15 cade in Ariete, non in un tredicesimo segno', static function () {
    return (Corpi::segnoDi(-1e-15) === 0 && Corpi::norma(-1e-15) < 360.0) ?: (string) Corpi::segnoDi(-1e-15);
});

prova('Gli aspetti agli angoli hanno una frase, in tutti e due i registri', static function () {
    foreach (['tradizionale', 'moderno'] as $reg) {
        foreach (['asc', 'mc'] as $angolo) {
            $v = (new Corpus($reg))->aspetto('sole', $angolo, 'congiunzione');
            if ($v === null || !str_contains($v->titolo, $angolo === 'asc' ? 'Ascendente' : 'Medio Cielo')) {
                return "{$reg}/{$angolo}: " . ($v?->titolo ?? 'nessuna voce');
            }
        }
    }
    return true;
});

prova('Nel corpus niente piu\' accenti ad apostrofo («e\'», «piu\'»)', static function () {
    $n = (int) Database::valore("SELECT COUNT(*) FROM testi WHERE CONCAT(titolo, ' ', corpo) REGEXP '[aeiou]\\'([^a-zA-Z]|$)'");
    return $n === 0 ?: "{$n} testi";
});

echo "\n== Mondo ==\n";

prova('Una carta mondiale si intitola «novilunio» solo se il cielo lo conferma', static function () {
    $novilunio = ['corpi' => ['sole' => ['lon' => 100.0], 'luna' => ['lon' => 100.4, 'lat' => 3.0]]];
    $mezzoMese = ['corpi' => ['sole' => ['lon' => 100.0], 'luna' => ['lon' => 190.0, 'lat' => 0.1]]];
    $eclissi   = ['corpi' => ['sole' => ['lon' => 100.0], 'luna' => ['lon' => 100.3, 'lat' => 0.4]]];
    $ingresso  = ['corpi' => ['sole' => ['lon' => 359.99], 'luna' => ['lon' => 50.0, 'lat' => 0.0]]];
    return (MondoController::confermato($novilunio, 'novilunio', 'sole', '')
        && !MondoController::confermato($mezzoMese, 'novilunio', 'sole', '')
        && !MondoController::confermato($novilunio, 'eclissi', 'sole', '')   // Luna lontana dal nodo
        && MondoController::confermato($eclissi, 'eclissi', 'sole', '')
        && MondoController::confermato($ingresso, 'ingresso', 'sole', '')
        && !MondoController::confermato($mezzoMese, 'ingresso', 'sole', '')) ?: 'geometria non verificata';
});

prova('Dal luogo scritto a mano si torna a una capitale', static function () {
    $a = Mondo::luogo('tokyo', 'Milano', 'Milano');   // il campo ripropone il luogo di prima
    $b = Mondo::luogo('tokyo', 'Napoli', 'Milano');   // scritto adesso: vince il testo
    $c = Mondo::luogo('', 'Milano', 'Milano');        // nessuna capitale scelta
    return ($a['nome'] === 'Tokyo' && $b['nome'] !== 'Tokyo' && $c['nome'] !== 'Roma') ?: json_encode([$a['nome'], $b['nome'], $c['nome']]);
});

echo "\n== Privacy e manutenzione ==\n";

prova('La purga cancella solo cio\' che e\' oltre il limite', static function () {
    Database::pdo()->beginTransaction();
    try {
        Database::esegui("INSERT INTO eventi (quando, sessione, tipo, oggetto, valore) VALUES (NOW() - INTERVAL 400 DAY, 'prova', 'prova_purga', '', ''), (NOW(), 'prova', 'prova_purga', '', '')");
        $n = Manutenzione::purga(365);
        $restano = (int) Database::valore("SELECT COUNT(*) FROM eventi WHERE tipo = 'prova_purga'");
        return ($restano === 1 && $n['eventi'] >= 1) ?: json_encode([$n, $restano]);
    } finally {
        Database::pdo()->rollBack();
    }
});

prova('Le partizioni di `accessi` coprono almeno i prossimi tre mesi', static fn () =>
    Manutenzione::mesiCoperti() >= 3 ?: (string) Manutenzione::mesiCoperti());

prova('Gli eventi di telemetria non portano dati di nascita', static function () {
    $sorgenti = implode("\n", array_map('file_get_contents', glob(dirname(__DIR__) . '/src/Controllers/*.php')));
    foreach (["evento('ora_ambigua', \$dati", "evento('calcolo_riuscito', \$dati['luogo_nome']", "evento('sinastria_completa', \$primo",
              "evento('ricerca_luogo', mb_substr(\$q", "evento('click_mappa', sprintf"] as $vecchio) {
        if (str_contains($sorgenti, $vecchio)) { return $vecchio; }
    }
    return true;
});

prova('Le richieste alle API non lasciano la domanda nel registro accessi', static function () {
    $t = (string) file_get_contents(dirname(__DIR__) . '/src/Support/Telemetria.php');
    return str_contains($t, "str_contains(\$richiesta->percorso(), '/api/') ? ''") ?: 'filtro assente';
});

echo "\n== Pagine ==\n";

prova('Un JSON con testo non UTF-8 non esce vuoto', static function () {
    $r = Response::json(['q' => "\xff\xff"]);
    $p = new ReflectionProperty($r, 'corpo');
    $corpo = (string) $p->getValue($r);
    return ($corpo !== '' && json_decode($corpo, true) !== null) ?: 'vuoto';
});

prova('Nei testi redazionali i collegamenti interni restano nel portale, «//altrove» no', static function () {
    $GLOBALS['__base_path'] = '/temaecielo';
    $h = Markdown::rendi('[a](/archivio) [b](//evil.example) [c](/temaecielo/mondo)');
    return (str_contains($h, 'href="/temaecielo/archivio"') && !str_contains($h, 'evil.example"')
        && str_contains($h, 'href="/temaecielo/mondo"') && !str_contains($h, '/temaecielo/temaecielo')) ?: $h;
});

prova('Il nome del luogo non si ripete nel contesto («Tokyo, Tokyo»)', static function () {
    $r = \App\Luogo\Gazetteer::cerca('Tokyo', null, 1)[0] ?? null;
    return ($r !== null && !str_starts_with((string) $r['contesto'], 'Tokyo')) ?: json_encode($r['contesto'] ?? null);
});

prova('Gli errori di un modulo valgono una volta sola', static function () {
    $_SESSION = ['__errori' => ['data' => 'x']];
    $a = \App\Core\Session::prendi('__errori', []);
    $b = \App\Core\Session::prendi('__errori', []);
    return ($a === ['data' => 'x'] && $b === []) ?: json_encode([$a, $b]);
});

printf(
    "\n%s%d passate, %d fallite\033[0m\n\n",
    $fallite === 0 ? "\033[0;32m" : "\033[1;31m",
    $passate,
    $fallite,
);

exit($fallite === 0 ? 0 : 1);
