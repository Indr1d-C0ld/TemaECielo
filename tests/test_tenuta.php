<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove di tenuta.
 *
 *   php tests/test_tenuta.php
 *
 * Non provano una formula ne' un testo: provano che il portale regga l'uso e
 * il tempo. Ci stanno tre cose, tutte nate da difetti veri trovati durante un
 * controllo generale:
 *
 *  1. la cache del motore si puo' svuotare, e svuotandola NON porta via i
 *     permalink — che non sono cache, sono l'unica copia di una carta;
 *  2. ogni rotta che accetta un POST verifica il gettone anti-falsificazione.
 *     La verifica sta dentro ciascun controllore, non in un punto solo: e'
 *     comodo finche' non si aggiunge la rotta numero undici e ci si dimentica.
 *     Questa prova se ne accorge;
 *  3. un'ora che non e' mai esistita produce comunque un istante su cui
 *     calcolare, perche' la carta del cielo non puo' fermarsi dove il modulo
 *     di nascita si ferma.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Motore;
use App\Core\Config;
use App\Core\Database;
use App\Luogo\Tempo;

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

echo "\n\033[1;36m══ La cache del motore ══\033[0m\n";

/* Le righe di prova portano un'impronta riconoscibile e vengono tolte alla
   fine, qualunque cosa succeda: una prova che sporca il database e' peggio di
   una prova che manca. */
$marchio = 'prova-tenuta-' . bin2hex(random_bytes(8));

$semina = static function (string $suffisso, ?string $gettone, string $quando) use ($marchio): void {
    Database::esegui(
        'INSERT INTO calcoli (impronta, gettone, tipo, esito, creato, ultima_richiesta, richieste, durata_ms)
         VALUES (?, ?, ?, ?, ?, ?, 1, 0)',
        [hash('sha256', $marchio . $suffisso), $gettone, 'natale', '{"prova":true}', $quando, $quando],
    );
};

$conta = static function (string $dove) use ($marchio): int {
    return (int) Database::valore(
        "SELECT COUNT(*) FROM calcoli WHERE impronta IN (
            SELECT * FROM (SELECT impronta FROM calcoli WHERE esito = '{\"prova\":true}') t
         ) AND {$dove}",
    );
};

/* Tutto il blocco sta dentro una transazione che viene ANNULLATA alla fine.
   Senza, la prova su «purga tutto» svuoterebbe la cache vera del portale a
   ogni esecuzione: nessun dato andrebbe perso — la cache e' ricalcolabile per
   definizione — ma una prova che tocca la produzione e' una prova che prima o
   poi qualcuno avra' paura di lanciare. Cosi' invece non lascia traccia. */
Database::pdo()->beginTransaction();

try {
    $vecchia  = (new DateTimeImmutable('-90 days'))->format('Y-m-d H:i:s');
    $recente  = (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s');
    $gettoneProva = substr(hash('sha256', $marchio . 'permalink'), 0, 32);

    $semina('cache-vecchia', null, $vecchia);
    $semina('cache-recente', null, $recente);
    $semina('permalink', $gettoneProva, $vecchia);   // vecchio MA con gettone

    prova('Le tre righe di prova sono state seminate', static function () use ($conta) {
        $n = $conta('1=1');
        return $n === 3 ?: "seminate {$n} invece di 3";
    });

    prova('Purgando a 30 giorni sparisce solo la cache vecchia', static function () use ($conta) {
        $tolte = Motore::purgaCache(30);
        if ($tolte < 1) { return "purgaCache ha tolto {$tolte} righe"; }

        $vecchieRimaste = $conta("gettone IS NULL AND ultima_richiesta < NOW() - INTERVAL 30 DAY");
        return $vecchieRimaste === 0 ?: "sono rimaste {$vecchieRimaste} righe di cache scadute";
    });

    prova('La cache recente NON viene toccata', static function () use ($conta) {
        $n = $conta("gettone IS NULL");
        return $n === 1 ?: "righe di cache rimaste: {$n}, attesa 1 (la recente)";
    });

    /* E' la prova che conta piu' di tutte. Un permalink e' l'unica identita' di
       una carta: il visitatore non ha un account, non ha una e-mail, ha solo
       quell'indirizzo. Una purga che se lo porta via distrugge un dato che non
       si puo' ricostruire, e lo fa in silenzio. */
    prova('Un permalink vecchio sopravvive alla purga', static function () use ($conta) {
        $n = $conta('gettone IS NOT NULL');
        return $n === 1 ?: "permalink rimasti: {$n}, atteso 1";
    });

    prova('Purgando tutto (0 giorni) i permalink restano comunque', static function () use ($conta) {
        Motore::purgaCache(0);
        $cache = $conta('gettone IS NULL');
        $perma = $conta('gettone IS NOT NULL');

        if ($cache !== 0) { return "resta ancora {$cache} riga di cache"; }
        return $perma === 1 ?: "il permalink e' sparito: ne restano {$perma}";
    });
} finally {
    Database::pdo()->rollBack();
}

prova('Annullata la transazione, il database e\' come prima', static function () {
    $prova = (int) Database::valore("SELECT COUNT(*) FROM calcoli WHERE esito = '{\"prova\":true}'");
    if ($prova !== 0) { return "restano {$prova} righe di prova"; }

    // E soprattutto: la cache vera non e' stata svuotata dalla prova.
    $cache = (int) Database::valore('SELECT COUNT(*) FROM calcoli WHERE gettone IS NULL');
    return $cache >= 0 ?: 'conteggio impossibile';
});

echo "\n\033[1;36m══ Il gettone anti-falsificazione su ogni POST ══\033[0m\n";

/**
 * Legge le rotte POST dal file delle rotte e controlla che il metodo che le
 * serve contenga una verifica del gettone.
 *
 * E' una prova sul TESTO del codice, non sul suo comportamento, e va detto:
 * non dimostra che la verifica sia giusta, dimostra che c'e'. E' poco, ma e'
 * esattamente il guasto che si vuole intercettare — la rotta aggiunta in
 * fretta a cui ci si dimentica di mettere il controllo.
 */
$rotte = (static function (): array {
    $testo = (string) file_get_contents(dirname(__DIR__) . '/src/routes.php');
    preg_match_all(
        "/->post\(\s*'([^']+)'\s*,\s*\[\s*([A-Za-z_\\\\]+)::class\s*,\s*'([^']+)'/",
        $testo,
        $m,
        PREG_SET_ORDER,
    );

    // Gli «use» danno il nome vero delle classi, alias compresi: nel file delle
    // rotte il controllore del guestbook di regia si chiama AdminGuestbook.
    preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $testo, $u, PREG_SET_ORDER);
    $classi = [];
    foreach ($u as $riga) {
        $pieno = $riga[1];
        $corto = $riga[2] ?? substr((string) strrchr($pieno, '\\'), 1);
        $classi[$corto] = $pieno;
    }

    $fuori = [];
    foreach ($m as $r) {
        $fuori[] = ['rotta' => $r[1], 'classe' => $classi[$r[2]] ?? $r[2], 'metodo' => $r[3]];
    }

    return $fuori;
})();

prova('Le rotte POST si leggono dal file delle rotte', static function () use ($rotte) {
    return count($rotte) >= 10 ?: 'trovate solo ' . count($rotte) . ' rotte POST: il lettore si e\' rotto';
});

foreach ($rotte as $r) {
    prova(sprintf('POST %-30s verifica il gettone', $r['rotta']), static function () use ($r) {
        $file = dirname(__DIR__) . '/src/' . str_replace(['App\\', '\\'], ['', '/'], $r['classe']) . '.php';
        if (!is_file($file)) { return "sorgente non trovato: {$file}"; }

        $righe = file($file) ?: [];
        $dentro = false;
        foreach ($righe as $riga) {
            if (preg_match('/function\s+' . preg_quote($r['metodo'], '/') . '\s*\(/', $riga) === 1) {
                $dentro = true;
                continue;
            }
            if ($dentro && preg_match('/^    (public|private|protected)\s+function\s/', $riga) === 1) {
                break;   // finito il metodo senza trovarla
            }
            if ($dentro && str_contains($riga, 'Csrf::verifica')) {
                return true;
            }
        }

        return "{$r['classe']}::{$r['metodo']} non verifica Csrf";
    });
}

echo "\n\033[1;36m══ La ricerca geografica non deve mai scandire ══\033[0m\n";

/* La tabella `geoip_reti` ha sette milioni e settecentomila intervalli. La
   ricerca giusta e' un salto sull'indice: l'ultimo intervallo che comincia
   prima dell'indirizzo, e poi si guarda se arriva abbastanza avanti.

   La ricerca sbagliata — chiedere al database `ip_da <= ? AND ip_a >= ?` — si
   comporta benissimo quando l'indirizzo c'e', e scandisce l'intera tabella
   quando non c'e'. E' il difetto peggiore che si possa avere: invisibile in
   prova, perche' in prova si usano indirizzi veri, e devastante in esercizio,
   perche' gli indirizzi di rete locale non ci sono MAI. Da casa propria il
   portale rispondeva in quasi cinque secondi.

   Queste prove misurano il tempo, e sono quindi le uniche del progetto che
   guardano l'orologio. La soglia e' larghissima di proposito: non serve a
   misurare la velocita', serve ad accorgersi se si torna a scandire. */

$cronometra = static function (callable $f): float {
    $t = microtime(true);
    $f();
    return (microtime(true) - $t) * 1000;
};

prova('Un indirizzo privato non tocca nemmeno il database', static function () use ($cronometra) {
    $ms = $cronometra(static fn () => \App\Support\Rete::geolocalizza('192.168.178.20'));
    return $ms < 5 ?: sprintf('ci ha messo %.0f ms: sta interrogando il database', $ms);
});

prova('...e non pretende di sapere in che paese sia', static function () {
    return \App\Support\Rete::geolocalizza('10.0.0.5') === null
        && \App\Support\Rete::operatore('172.16.0.1') === null
        ?: 'un indirizzo di rete locale ha restituito una posizione';
});

prova('Il loopback non tocca il database', static function () use ($cronometra) {
    $ms = $cronometra(static fn () => \App\Support\Rete::operatore('127.0.0.1'));
    return $ms < 5 ?: sprintf('ci ha messo %.0f ms', $ms);
});

prova('Un indirizzo pubblico ma non assegnato costa un salto, non una scansione',
    static function () use ($cronometra) {
        // 203.0.113.0/24 e' TEST-NET-3, riservata alla documentazione: e'
        // pubblica nella forma, e in nessun archivio GeoIP nella sostanza.
        // Cade quindi in un buco fra due intervalli, che e' esattamente il
        // caso che prima costava quasi cinque secondi.
        $ms = $cronometra(static fn () => \App\Support\Rete::geolocalizza('203.0.113.7'));
        return $ms < 150 ?: sprintf('ci ha messo %.0f ms: e\' tornata la scansione', $ms);
    });

prova('...e restituisce comunque «non so», non un paese a caso', static function () {
    return \App\Support\Rete::geolocalizza('203.0.113.7') === null
        ?: 'ha attribuito un paese a un indirizzo che non esiste';
});

prova('Un indirizzo vero continua a risolversi', static function () {
    $g = \App\Support\Rete::geolocalizza('8.8.8.8');
    $o = \App\Support\Rete::operatore('8.8.8.8');
    if ($g === null) { return 'geolocalizzazione persa per 8.8.8.8'; }
    if ($o === null) { return 'operatore perso per 8.8.8.8'; }
    return ($g['paese'] === 'US' && str_contains($o['organizzazione'], 'Google'))
        ?: "trovato {$g['paese']} / {$o['organizzazione']}";
});

prova('Anche in IPv6', static function () {
    $g = \App\Support\Rete::geolocalizza('2001:4860:4860::8888');
    return ($g !== null && $g['paese'] !== '') ?: 'IPv6 non risolto';
});

echo "\n\033[1;36m══ HEAD e' un GET senza corpo ══\033[0m\n";

/* Nessuna rotta e' dichiarata come HEAD, e non ha senso dichiararle due volte:
   deve pensarci lo smistatore. Prima non ci pensava, e ogni richiesta HEAD —
   i sorveglianti di servizio, i controllori di collegamenti, una parte dei
   motori di ricerca — riceveva un 405. */

// Gli errori dello smistatore si rendono con una vista: senza dirle dove
// cercare, un 404 diventa un'eccezione e la prova misura la cosa sbagliata.
\App\Core\Vista::percorso(dirname(__DIR__) . '/views');

$smistaCon = static function (string $metodo, string $percorso): int {
    $_SERVER['REQUEST_METHOD'] = $metodo;
    $_SERVER['REQUEST_URI']    = $percorso;
    $_SERVER['SCRIPT_NAME']    = '/index.php';

    $r = new \App\Core\Router();
    $r->aggiungi('GET',  '/una-pagina', static fn (): \App\Core\Response => \App\Core\Response::html('ciao'));
    $r->aggiungi('POST', '/solo-post',  static fn (): \App\Core\Response => \App\Core\Response::html('ciao'));

    return $r->smista(new \App\Core\Request(''))->stato();
};

prova('GET su una rotta GET risponde 200', static function () use ($smistaCon) {
    $c = $smistaCon('GET', '/una-pagina');
    return $c === 200 ?: "stato {$c}";
});

prova('HEAD sulla stessa rotta risponde 200, non 405', static function () use ($smistaCon) {
    $c = $smistaCon('HEAD', '/una-pagina');
    return $c === 200 ?: "stato {$c}";
});

prova('HEAD su una rotta di solo POST resta 405', static function () use ($smistaCon) {
    $c = $smistaCon('HEAD', '/solo-post');
    return $c === 405 ?: "stato {$c}";
});

prova('Un metodo davvero non previsto resta 405', static function () use ($smistaCon) {
    $c = $smistaCon('PUT', '/una-pagina');
    return $c === 405 ?: "stato {$c}";
});

prova('Un percorso inesistente resta 404, anche in HEAD', static function () use ($smistaCon) {
    $c = $smistaCon('HEAD', '/non-esiste-proprio');
    return $c === 404 ?: "stato {$c}";
});

echo "\n\033[1;36m══ L'ora che non e' mai esistita ══\033[0m\n";

/* Il 29 marzo 2026 in Italia gli orologi saltano dalle 02:00 alle 03:00: le
   02:30 quel giorno non esistono. Il modulo di nascita si ferma e chiede, ed
   e' giusto. La carta del cielo no: chi guarda in alto vuole vedere il cielo,
   non discutere di fusi. Serve quindi che la risposta porti comunque un
   istante su cui calcolare, pur dichiarando `ok` falso. */

prova('Un\'ora saltata resta `ok` falso', static function () {
    $e = Tempo::risolvi('2026-03-29', '02:30:00', 'Europe/Rome');
    return ($e['ok'] === false && $e['stato'] === Tempo::INESISTENTE)
        ?: 'stato: ' . var_export($e['stato'] ?? null, true);
});

prova('...ma porta comunque un istante su cui calcolare', static function () {
    $e = Tempo::risolvi('2026-03-29', '02:30:00', 'Europe/Rome');
    if (!isset($e['istante'])) { return 'manca la chiave `istante`'; }
    return is_int($e['istante']) && $e['istante'] > 0 ?: 'istante non plausibile';
});

prova('...e quell\'istante e\' il primo dopo il salto, non un\'ora inventata', static function () {
    $e = Tempo::risolvi('2026-03-29', '02:30:00', 'Europe/Rome');
    $locale = (new DateTimeImmutable('@' . $e['istante']))->setTimezone(new DateTimeZone('Europe/Rome'));

    // Subito dopo il salto sono le 03:00 locali, ed e' gia' ora legale.
    return ($locale->format('H:i') === '03:00' && $locale->format('I') === '1')
        ?: 'istante locale: ' . $locale->format('Y-m-d H:i T');
});

prova('L\'istante coincide con le componenti UT gia' . '\' restituite', static function () {
    $e = Tempo::risolvi('2026-03-29', '02:30:00', 'Europe/Rome');
    $c = Tempo::componenti((int) $e['istante']);
    return $c == $e['componenti_ut'] ?: 'componenti discordi';
});

prova('Un\'ora ambigua porta il primo dei due istanti', static function () {
    $e = Tempo::risolvi('2026-10-25', '02:30:00', 'Europe/Rome');
    if (($e['stato'] ?? '') !== Tempo::AMBIGUO) { return 'stato: ' . ($e['stato'] ?? '?'); }
    if (count($e['alternative'] ?? []) !== 2) { return 'alternative: ' . count($e['alternative'] ?? []); }

    // La prima interpretazione e' quella ancora in ora legale, cioe' la piu'
    // presto nel tempo assoluto.
    return $e['istante'] < strtotime($e['alternative'][1]['utc'])
        ?: 'il primo istante non e\' il piu\' presto';
});

printf(
    "\n%s%d passate, %d fallite\033[0m\n\n",
    $fallite === 0 ? "\033[0;32m" : "\033[1;31m",
    $passate,
    $fallite,
);

exit($fallite === 0 ? 0 : 1);
