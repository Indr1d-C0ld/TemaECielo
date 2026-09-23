<?php

declare(strict_types=1);

/**
 * Tema e Cielo — semina dell'archivio pubblico.
 *
 *   php bin/importa-archivio.php [--sostituisci] [--ricalcola] [--prova]
 *
 * Legge db/semi/archivio.php: persone celebri, eventi storici, fondazioni di
 * Stati, ciascuno con la fonte dei dati e la classe Rodden dell'ora. Ogni voce
 * diventa una carta vera — stesso motore, stesso permalink delle carte dei
 * visitatori — con la sua scheda.
 *
 * L'identita' di una voce e' il suo slug, derivato dal nome: rilanciare non
 * duplica nulla. Per impostazione una voce gia' presente non si tocca, perche'
 * la regia puo' averne corretto la scheda dal pannello.
 *
 *   --sostituisci  riallinea ai semi i testi delle schede (nota, fonte, Rodden)
 *   --ricalcola    ricalcola anche la carta: serve quando nei semi cambia
 *                  l'ora o il luogo. Il gettone cambia, lo slug pubblico no.
 *   --prova        controlla i semi e il fuso di ogni voce, senza scrivere
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo da riga di comando.\n");
}

$radice = dirname(__DIR__);
require $radice . '/src/autoload.php';
require $radice . '/src/Support/helpers.php';

use App\Archivio\Archivio;
use App\Astro\Motore;
use App\Controllers\CalcolaController;
use App\Core\Config;
use App\Core\Database;
use App\Luogo\Tempo;

$GLOBALS['__project_root'] = $radice;
Config::load($radice);
date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$sostituisci = in_array('--sostituisci', $argv, true);
$ricalcola   = in_array('--ricalcola', $argv, true);
$prova       = in_array('--prova', $argv, true);

function titolo(string $t): void  { fwrite(STDOUT, "\n\033[1;36m==> {$t}\033[0m\n"); }
function bene(string $t): void    { fwrite(STDOUT, "    \033[0;32m{$t}\033[0m\n"); }
function attento(string $t): void { fwrite(STDOUT, "    \033[0;33m{$t}\033[0m\n"); }
function muori(string $t): never  { fwrite(STDERR, "\n\033[1;31mERRORE: {$t}\033[0m\n"); exit(1); }

Database::disponibile() || muori('Database non raggiungibile.');

$file = $radice . '/db/semi/archivio.php';
is_file($file) || muori('Manca ' . $file);
$semi = require $file;
is_array($semi) || muori('Il seme non restituisce un elenco.');

/**
 * Da una voce del seme ai dati che servono al motore e all'archiviazione.
 *
 * L'istante vero e' `ut`, in Tempo Universale, preso dalla fonte: e' l'unico
 * numero che non dipende da quale orologio si usasse allora (tempo medio
 * locale, ora di Roma, ora legale di guerra). Data e ora locali restano quelle
 * della fonte, e lo scarto fra le due e' quello che la carta mostra. Il fuso
 * del portale fa da controprova: se tzdata vede un'altra ora, lo si dice.
 *
 * @param array<string,mixed> $v
 * @return array{dati:array<string,mixed>,componenti:array{anno:int,mese:int,giorno:int,ora_ut:float},offset:int,precisione:string,avviso:?string}
 */
function prepara(array $v): array
{
    foreach (['nome', 'tipo', 'data', 'luogo', 'lat', 'lon', 'fuso', 'rodden'] as $k) {
        if (!isset($v[$k]) || $v[$k] === '') {
            throw new InvalidArgumentException("manca «{$k}»");
        }
    }
    if (!Tempo::zonaValida((string) $v['fuso'])) {
        throw new InvalidArgumentException("fuso sconosciuto «{$v['fuso']}»");
    }
    $anno = (int) substr((string) $v['data'], 0, 4);
    if ($anno < 1800 || $anno > 2399) {
        throw new InvalidArgumentException('fuori dal 1800-2399 delle effemeridi');
    }

    $ignota = strtoupper((string) $v['rodden']) === 'X' || ($v['ora'] ?? '') === '';
    $precisione = $ignota ? 'ignota' : (strtoupper((string) $v['rodden']) === 'C' ? 'approssimativa' : 'esatta');
    $ora = $ignota ? '12:00' : (string) $v['ora'];

    $tempo = Tempo::risolviNelLuogo((string) $v['data'], $ora, (string) $v['fuso'], (float) $v['lon']);
    $avviso = null;

    if ($ignota || ($v['ut'] ?? '') === '') {
        if (($tempo['ok'] ?? false) !== true) {
            throw new InvalidArgumentException('ora locale non risolvibile: ' . ($tempo['avviso'] ?? $tempo['errore'] ?? '?'));
        }
        $componenti = $tempo['componenti_ut'];
        $offset = (int) $tempo['offset_secondi'];
    } else {
        $ut = DateTimeImmutable::createFromFormat('!Y-m-d H:i', (string) $v['ut'], new DateTimeZone('UTC'));
        $locale = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $v['data'] . ' ' . $ora, new DateTimeZone('UTC'));
        if ($ut === false || $locale === false) {
            throw new InvalidArgumentException('«ut» o «ora» in un formato inatteso');
        }
        $offset = $locale->getTimestamp() - $ut->getTimestamp();
        if (abs($offset) > 15 * 3600) {
            throw new InvalidArgumentException('fra ora locale e UT ci sono ' . round($offset / 3600, 1) . ' ore');
        }
        $componenti = [
            'anno'   => (int) $ut->format('Y'),
            'mese'   => (int) $ut->format('n'),
            'giorno' => (int) $ut->format('j'),
            'ora_ut' => (int) $ut->format('G') + (int) $ut->format('i') / 60,
        ];
        if (($tempo['ok'] ?? false) === true && abs((int) $tempo['offset_secondi'] - $offset) > 60) {
            $avviso = sprintf('la fonte dice UT%+.2fh, tzdata UT%+.2fh (%s): vale la fonte',
                $offset / 3600, $tempo['offset_secondi'] / 3600, (string) ($v['orologio'] ?? 'orologio non indicato'));
        }
    }

    return [
        'dati' => [
            'nome'       => (string) $v['nome'],
            'data'       => (string) $v['data'],
            'ora'        => $ora,
            'luogo_nome' => (string) $v['luogo'],
            'lat'        => round((float) $v['lat'], 6),
            'lon'        => round((float) $v['lon'], 6),
            'altitudine' => (int) ($v['alt'] ?? 0),
            'fuso'       => (string) $v['fuso'],
        ],
        'componenti' => $componenti,
        'offset'     => $offset,
        'precisione' => $precisione,
        'avviso'     => $avviso,
    ];
}

titolo('Archivio: ' . count($semi) . ' voci' . ($prova ? ' (prova, nessuna scrittura)' : ''));

$motore = new Motore();
$calcola = new CalcolaController();
$conti = ['nuove' => 0, 'aggiornate' => 0, 'ricalcolate' => 0, 'invariate' => 0, 'scartate' => 0];
$visti = [];

foreach ($semi as $i => $v) {
    $nome = (string) ($v['nome'] ?? "voce {$i}");
    $slug = Archivio::slugDi($nome);
    if (isset($visti[$slug])) {
        attento("{$nome}: slug «{$slug}» doppio nei semi, salto");
        $conti['scartate']++;
        continue;
    }
    $visti[$slug] = true;

    try {
        $p = prepara($v);
        Archivio::pulisci(['nome' => $nome] + $v);
    } catch (InvalidArgumentException $e) {
        attento("{$nome}: {$e->getMessage()}");
        $conti['scartate']++;
        continue;
    }
    if ($p['avviso'] !== null) {
        attento("{$nome}: {$p['avviso']}");
    }

    $scheda = [
        'tipo' => $v['tipo'], 'nome' => $nome, 'categoria' => $v['categoria'] ?? '',
        'nota' => $v['nota'] ?? '', 'fonte' => $v['fonte'] ?? '', 'url_fonte' => $v['url'] ?? '',
        'rodden' => $v['rodden'], 'pubblicata' => true,
    ];
    $gia = Database::valore('SELECT calcolo_id FROM archivio WHERE slug = ? LIMIT 1', [$slug]);

    if ($prova) {
        bene(sprintf('%-40s %s  UT %04d-%02d-%02d %05.2fh  %s', $nome, $gia === null ? 'nuova' : 'c\'è',
            $p['componenti']['anno'], $p['componenti']['mese'], $p['componenti']['giorno'], $p['componenti']['ora_ut'], $v['rodden']));
        continue;
    }

    if ($gia !== null && !$ricalcola) {
        if ($sostituisci) {
            Archivio::salva((int) $gia, $scheda);
            $conti['aggiornate']++;
        } else {
            $conti['invariate']++;
        }
        continue;
    }

    try {
        $tema = $motore->tema([
            'anno' => $p['componenti']['anno'], 'mese' => $p['componenti']['mese'],
            'giorno' => $p['componenti']['giorno'], 'ora_ut' => $p['componenti']['ora_ut'],
            'lat' => $p['dati']['lat'], 'lon' => $p['dati']['lon'], 'alt' => $p['dati']['altitudine'],
            'sistema_case' => $p['precisione'] === 'ignota' ? 'segni_interi' : 'placido',
            'ora_ignota' => $p['precisione'] === 'ignota',
            'offset_secondi' => $p['offset'],
        ]);
    } catch (Throwable $e) {
        attento("{$nome}: il motore ha rifiutato ({$e->getMessage()})");
        $conti['scartate']++;
        continue;
    }

    // Un ricalcolo tiene lo slug e, se la regia ne aveva cambiato i testi
    // senza --sostituisci, anche la scheda com'era.
    $vecchia = $gia !== null ? Archivio::perCalcolo((int) $gia) : null;
    if ($gia !== null) {
        CalcolaController::cancella((int) $gia);
    }
    $gettone = $calcola->archivia($p['dati'], $p['componenti'], $p['offset'], $p['precisione'], $tema);
    $id = (int) Database::valore('SELECT id FROM calcoli WHERE gettone = ?', [$gettone]);
    Archivio::salva($id, $vecchia !== null && !$sostituisci ? $vecchia : $scheda);
    if ($vecchia !== null && $vecchia['slug'] !== $slug) {
        Database::esegui('UPDATE archivio SET slug = ? WHERE calcolo_id = ?', [$vecchia['slug'], $id]);
    }
    $conti[$gia === null ? 'nuove' : 'ricalcolate']++;
}

bene(implode(', ', array_map(static fn ($k, $n) => "{$n} {$k}", array_keys($conti), $conti)));
exit($conti['scartate'] > 0 ? 2 : 0);
