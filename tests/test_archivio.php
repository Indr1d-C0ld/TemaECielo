<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove dell'archivio pubblico e della lettura mondiale.
 *
 *   php tests/test_archivio.php
 *
 * Quelle che scrivono nel database lo fanno dentro una transazione che poi
 * annullano: nessuna scheda di prova resta in giro.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Archivio\Archivio;
use App\Astro\Tema;
use App\Core\Config;
use App\Core\Database;
use App\Corpus\Mondana;
use App\Luogo\Tempo;

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

/** Una carta finta nel database, con la sua persona: vive solo dentro la transazione. */
function cartaFinta(string $nome, string $data = '1900-01-01'): int
{
    Database::esegui(
        'INSERT INTO soggetti (nome, data_nascita, ora_nascita, precisione_ora, luogo_nome, lat, lon, altitudine,
                               fuso, offset_minuti, ora_ut, creato)
         VALUES (?, ?, \'12:00:00\', \'esatta\', \'Roma\', 41.9, 12.5, 0, \'Europe/Rome\', 60, 11, NOW())',
        [$nome, $data],
    );
    $s = Database::ultimoId();
    Database::esegui(
        'INSERT INTO calcoli (gettone, impronta, tipo, esito, richieste, durata_ms, creato, ultima_richiesta)
         VALUES (?, ?, \'natale\', \'{}\', 1, 0, NOW(), NOW())',
        [bin2hex(random_bytes(16)), bin2hex(random_bytes(32))],
    );
    $c = Database::ultimoId();
    Database::esegui('INSERT INTO calcoli_soggetti (calcolo_id, soggetto_id, ruolo) VALUES (?, ?, \'primo\')', [$c, $s]);

    return $c;
}

function inTransazione(callable $f): mixed
{
    Database::pdo()->beginTransaction();
    try {
        return $f();
    } finally {
        Database::pdo()->rollBack();
    }
}

echo "\n== Le schede ==\n";

prova('Lo slug si legge: accenti, apostrofi e lettere straniere diventano ASCII', static function () {
    $casi = [
        "Gabriele D'Annunzio" => 'gabriele-d-annunzio',
        'Giovanni Paolo II (Karol Wojtyła)' => 'giovanni-paolo-ii-karol-wojtyla',
        "Incidente di Černobyl'" => 'incidente-di-cernobyl',
        '!!!' => 'carta',
    ];
    foreach ($casi as $nome => $atteso) {
        if (Archivio::slugDi($nome) !== $atteso) {
            return "{$nome} → " . Archivio::slugDi($nome);
        }
    }
    return true;
});

prova('Una scheda senza nome o di tipo sconosciuto viene rifiutata', static function () {
    foreach ([['nome' => '  ', 'tipo' => 'persona'], ['nome' => 'X', 'tipo' => 'pianeta']] as $d) {
        try { Archivio::pulisci($d); return 'accettata: ' . json_encode($d); } catch (\InvalidArgumentException) {}
    }
    return true;
});

prova('Categoria e Rodden fuori elenco ripiegano su valori ammessi', static function () {
    $d = Archivio::pulisci(['nome' => 'X', 'tipo' => 'nazione', 'categoria' => 'musica', 'rodden' => 'zz']);
    $e = Archivio::pulisci(['nome' => 'X', 'tipo' => 'persona', 'rodden' => 'aa']);
    return ($d['categoria'] === 'fondazione' && $d['rodden'] === 'C' && $e['rodden'] === 'AA') ?: json_encode([$d, $e]);
});

prova('Nel collegamento alla fonte passa solo http(s): niente «javascript:»', static function () {
    $male = Archivio::pulisci(['nome' => 'X', 'url_fonte' => 'javascript:alert(1)'])['url_fonte'];
    $bene = Archivio::pulisci(['nome' => 'X', 'url_fonte' => 'https://example.org/a'])['url_fonte'];
    return ($male === '' && $bene === 'https://example.org/a') ?: "{$male} / {$bene}";
});

prova('Due schede con lo stesso nome hanno slug diversi', static fn () => inTransazione(static function () {
    $a = Archivio::salva(cartaFinta('A'), ['nome' => 'Omonimo di prova', 'tipo' => 'persona']);
    $b = Archivio::salva(cartaFinta('B'), ['nome' => 'Omonimo di prova', 'tipo' => 'persona']);
    return ($a === 'omonimo-di-prova' && $b === 'omonimo-di-prova-2') ?: "{$a} / {$b}";
}));

prova('Risalvare la stessa carta aggiorna la scheda, non ne crea un\'altra', static fn () => inTransazione(static function () {
    $c = cartaFinta('A');
    Archivio::salva($c, ['nome' => 'Prima versione', 'tipo' => 'persona', 'nota' => 'uno']);
    $slug = Archivio::salva($c, ['nome' => 'Seconda versione', 'tipo' => 'persona', 'nota' => 'due']);
    $n = (int) Database::valore('SELECT COUNT(*) FROM archivio WHERE calcolo_id = ?', [$c]);
    return ($n === 1 && $slug === 'seconda-versione' && Archivio::perCalcolo($c)['nota'] === 'due') ?: "{$n} {$slug}";
}));

prova('Uno slug scelto a mano sopravvive al cambio di nome', static fn () => inTransazione(static function () {
    $c = cartaFinta('A');
    Archivio::salva($c, ['nome' => 'Nome lungo e scomodo', 'tipo' => 'persona']);
    Database::esegui('UPDATE archivio SET slug = ? WHERE calcolo_id = ?', ['scelto-a-mano', $c]);
    $slug = Archivio::salva($c, ['nome' => 'Nome nuovo', 'tipo' => 'persona']);
    return $slug === 'scelto-a-mano' ?: $slug;
}));

prova('Una scheda non pubblicata non si trova dall\'indirizzo pubblico', static fn () => inTransazione(static function () {
    $c = cartaFinta('A');
    $slug = Archivio::salva($c, ['nome' => 'Bozza di prova', 'tipo' => 'evento', 'pubblicata' => false]);
    $prima = Archivio::perSlug($slug);
    Archivio::salva($c, ['nome' => 'Bozza di prova', 'tipo' => 'evento', 'pubblicata' => true]);
    return ($prima === null && Archivio::perSlug($slug) !== null) ?: 'visibilita\' sbagliata';
}));

prova('L\'elenco filtra per tipo e per secolo', static fn () => inTransazione(static function () {
    Archivio::salva(cartaFinta('A', '1850-05-05'), ['nome' => 'Zzprova Ottocento', 'tipo' => 'evento', 'categoria' => 'guerra', 'pubblicata' => true]);
    Archivio::salva(cartaFinta('B', '1950-05-05'), ['nome' => 'Zzprova Novecento', 'tipo' => 'evento', 'categoria' => 'guerra', 'pubblicata' => true]);
    $nomi = static fn (array $f): array => array_column(Archivio::elenco($f + ['q' => 'Zzprova'])['righe'], 'nome');
    $xix = $nomi(['secolo' => 19]);
    $ev = $nomi(['tipo' => 'evento']);
    $per = $nomi(['tipo' => 'persona']);
    return ($xix === ['Zzprova Ottocento'] && count($ev) === 2 && $per === []) ?: json_encode([$xix, $ev, $per]);
}));

prova('Ritirare una scheda lascia la carta', static fn () => inTransazione(static function () {
    $c = cartaFinta('A');
    Archivio::salva($c, ['nome' => 'Da ritirare', 'tipo' => 'persona']);
    Archivio::ritira($c);
    return (Archivio::perCalcolo($c) === null && Database::valore('SELECT id FROM calcoli WHERE id = ?', [$c]) !== null) ?: 'no';
}));

prova('Cancellare la carta porta via la scheda (chiave esterna a cascata)', static fn () => inTransazione(static function () {
    $c = cartaFinta('A');
    Archivio::salva($c, ['nome' => 'Da cancellare', 'tipo' => 'persona']);
    Database::esegui('DELETE FROM calcoli WHERE id = ?', [$c]);
    return Archivio::perCalcolo($c) === null ?: 'la scheda e\' rimasta orfana';
}));

prova('Le statistiche dei visitatori non contano le carte dell\'archivio', static function () {
    $r = new ReflectionClass(\App\Controllers\StatisticheController::class);
    return (str_contains((string) $r->getConstant('CARTE_VERE'), 'archivio')
        && str_contains((string) $r->getConstant('VISITATORI'), 'archivio')) ?: 'filtro mancante';
});

echo "\n== I semi ==\n";

$semi = require $radice . '/db/semi/archivio.php';

prova('Ogni voce dei semi e\' una scheda valida, con categoria del suo tipo', static function () use ($semi) {
    foreach ($semi as $v) {
        $d = Archivio::pulisci($v + ['url_fonte' => $v['url'] ?? '']);
        if (!in_array($v['categoria'], Archivio::CATEGORIE[$v['tipo']], true)) {
            return "{$v['nome']}: categoria «{$v['categoria']}» non ammessa per {$v['tipo']}";
        }
        if ($d['url_fonte'] === '' || $d['fonte'] === '') {
            return "{$v['nome']}: senza fonte";
        }
        if (!isset(Archivio::RODDEN[$v['rodden']])) {
            return "{$v['nome']}: Rodden «{$v['rodden']}»";
        }
    }
    return true;
});

prova('Nessuno slug doppio nei semi', static function () use ($semi) {
    $slug = array_map(static fn (array $v): string => Archivio::slugDi($v['nome']), $semi);
    $doppi = array_keys(array_filter(array_count_values($slug), static fn (int $n): bool => $n > 1));
    return $doppi === [] ?: implode(', ', $doppi);
});

prova('Date dentro le effemeridi, fusi riconosciuti, UT coerente con l\'ora locale', static function () use ($semi) {
    foreach ($semi as $v) {
        $anno = (int) substr($v['data'], 0, 4);
        if ($anno < 1800 || $anno > 2399) { return "{$v['nome']}: {$anno}"; }
        if (!Tempo::zonaValida($v['fuso'])) { return "{$v['nome']}: fuso {$v['fuso']}"; }
        if (($v['ora'] ?? '') === '' || $v['rodden'] === 'X') { continue; }
        $ut = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $v['ut'], new DateTimeZone('UTC'));
        $lo = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $v['data'] . ' ' . $v['ora'], new DateTimeZone('UTC'));
        if ($ut === false || $lo === false) { return "{$v['nome']}: formato"; }
        // Nessun orologio civile sta oltre ±14 ore dal Tempo Universale, e il
        // segno dello scarto deve seguire la longitudine (est avanti, ovest indietro).
        $scarto = ($lo->getTimestamp() - $ut->getTimestamp()) / 3600;
        if (abs($scarto) > 14 || ($scarto !== 0.0 && abs($v['lon']) > 20 && ($scarto > 0) !== ($v['lon'] > 0))) {
            return "{$v['nome']}: scarto {$scarto} h con longitudine {$v['lon']}";
        }
    }
    return true;
});

prova('Le coordinate stanno sulla Terra (non sulla Luna)', static function () use ($semi) {
    foreach ($semi as $v) {
        if (str_contains($v['luogo'], 'Luna') || abs($v['lat']) > 90 || abs($v['lon']) > 180) {
            return $v['nome'];
        }
    }
    return true;
});

echo "\n== La lettura mondiale ==\n";

$repubblica = Tema::componi(worker([
    'operazione' => 'tema', 'anno' => 1946, 'mese' => 6, 'giorno' => 10, 'ora_ut' => 16.0,
    'lat' => 41.901, 'lon' => 12.4787, 'alt' => 20, 'sistema_case' => 'placido',
]));

prova('Una carta di fondazione ha quadro, pianeti e trama', static function () use ($repubblica) {
    $m = Mondana::monta($repubblica, 'nazione');
    $titoli = array_column($m['sezioni'], 'titolo');
    return $titoli === ['Il quadro', 'I pianeti', 'La trama'] ?: json_encode($titoli);
});

prova('Il pianeta angolare viene per primo, e lo dice (Marte al Medio Cielo nel 1946)', static function () use ($repubblica) {
    $pianeti = Mondana::monta($repubblica, 'nazione')['sezioni'][1]['voci'];
    return ($pianeti[0]['corpi'] === ['marte'] && $pianeti[0]['perche'] === 'angolare'
        && str_contains($pianeti[0]['corpo'], 'culmina al Medio Cielo')) ?: json_encode($pianeti[0], JSON_UNESCAPED_UNICODE);
});

prova('Ci sono tutti e dieci i pianeti, e nessun asteroide', static function () use ($repubblica) {
    $corpi = array_merge(...array_column(Mondana::monta($repubblica, 'evento')['sezioni'][1]['voci'], 'corpi'));
    sort($corpi);
    $attesi = ['giove', 'luna', 'marte', 'mercurio', 'nettuno', 'plutone', 'saturno', 'sole', 'urano', 'venere'];
    return $corpi === $attesi ?: implode(',', $corpi);
});

prova('La trama tiene solo aspetti stretti fra pianeti, dal piu\' stretto', static function () use ($repubblica) {
    $trama = Mondana::monta($repubblica, 'nazione')['sezioni'][2]['voci'];
    $orbi = array_map(static fn (array $v): float => (float) str_replace(',', '.', substr($v['perche'], 5)), $trama);
    $ordinati = $orbi;
    sort($ordinati);
    return ($orbi === $ordinati && max($orbi) <= 3.0 && count($trama) <= 8) ?: json_encode($orbi);
});

prova('Con l\'ora ignota spariscono angoli e case, restano i segni', static function () use ($repubblica) {
    $t = $repubblica;
    $t['carta']['ora_ignota'] = true;
    $m = Mondana::monta($t, 'nazione');
    $tutto = json_encode($m['sezioni'], JSON_UNESCAPED_UNICODE);
    return ($m['sezioni'][0]['titolo'] === 'I pianeti' && !str_contains($tutto, 'casa ') && !str_contains($tutto, 'angolare')
        && str_contains($m['introduzione'], 'non è nota')) ?: $tutto;
});

prova('Un evento non viene chiamato «paese»', static function () use ($repubblica) {
    $quadro = Mondana::monta($repubblica, 'evento')['sezioni'][0]['voci'];
    return (str_starts_with($quadro[0]['corpo'], 'L\'evento') && !str_contains($quadro[1]['corpo'], 'paese'))
        ?: $quadro[0]['corpo'] . ' / ' . $quadro[1]['corpo'];
});

printf(
    "\n%s%d passate, %d fallite\033[0m\n\n",
    $fallite === 0 ? "\033[0;32m" : "\033[1;31m",
    $passate,
    $fallite,
);

exit($fallite === 0 ? 0 : 1);
