<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove di comunita' e regia.
 *
 *   php tests/test_comunita.php
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Core\Config;
use App\Core\Database;
use App\Support\Impostazioni;
use App\Support\Markdown;
use App\Support\Rete;

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

echo "\n\033[1;36m══ Indirizzi di rete ══\033[0m\n";

prova('Ogni indirizzo diventa sedici byte, anche gli IPv4', static function () {
    // Mescolare chiavi da quattro e da sedici byte nella stessa colonna rompe
    // ogni confronto d'intervallo, e il guaio non si vede: le ricerche
    // tornano semplicemente vuote.
    foreach (['192.0.2.1', '10.0.0.1', '::1', '2001:db8::1', '255.255.255.255'] as $ip) {
        $b = Rete::binario($ip);
        if ($b === null || strlen($b) !== 16) {
            return "{$ip}: " . ($b === null ? 'nullo' : strlen($b) . ' byte');
        }
    }

    return true;
});

prova('Un IPv4 usa la forma mappata ::ffff:', static function () {
    $b = Rete::binario('192.0.2.1');

    return str_starts_with(bin2hex($b), '00000000000000000000ffff')
        ? true : bin2hex($b);
});

prova('Indirizzi non validi danno null, non un\'eccezione', static function () {
    foreach (['non-un-ip', '999.1.1.1', '', 'http://esempio'] as $x) {
        if (Rete::binario($x) !== null) {
            return "«{$x}» e' stato accettato";
        }
    }

    return true;
});

$reti = [
    ['192.0.2.42',  '192.0.2.0/24',   true,  'dentro un /24'],
    ['192.0.3.42',  '192.0.2.0/24',   false, 'fuori dal /24'],
    ['192.0.2.42',  '192.0.2.42',     true,  'indirizzo singolo, uguale'],
    ['192.0.2.43',  '192.0.2.42',     false, 'indirizzo singolo, diverso'],
    ['10.1.2.3',    '10.0.0.0/8',     true,  'dentro un /8'],
    ['11.1.2.3',    '10.0.0.0/8',     false, 'fuori dall\'/8'],
    ['192.0.2.42',  '192.0.2.0/25',   true,  'meta' . "'" . ' bassa di un /25'],
    ['192.0.2.200', '192.0.2.0/25',   false, 'meta' . "'" . ' alta di un /25'],
    ['2001:db8::5', '2001:db8::/32',  true,  'dentro un /32 IPv6'],
    ['2001:db9::5', '2001:db8::/32',  false, 'fuori dal /32 IPv6'],
    ['192.0.2.42',  '0.0.0.0/0',      true,  'la rete che contiene tutto'],
];
foreach ($reti as [$ip, $cidr, $atteso, $eti]) {
    prova("{$eti}: {$ip} in {$cidr}", static function () use ($ip, $cidr, $atteso) {
        $r = Rete::dentro($ip, $cidr);

        return $r === $atteso ? true : ($r ? 'dice dentro' : 'dice fuori');
    });
}

prova('Una maschera IPv4 viene traslata di 96 bit', static function () {
    // Nella forma mappata i primi 96 bit sono il prefisso ::ffff:, e un /24
    // scritto per IPv4 corrisponde a un /120. Senza la traslazione, «/24»
    // significherebbe «i primi 24 bit di ::ffff:», cioe' tutto lo spazio.
    return (Rete::dentro('192.0.2.1', '192.0.2.0/24') && !Rete::dentro('8.8.8.8', '192.0.2.0/24'))
        ? true : 'la maschera non e\' stata traslata';
});

echo "\n\033[1;36m══ Geolocalizzazione ══\033[0m\n";

if ((int) Database::valore('SELECT COUNT(*) FROM geoip_reti') === 0) {
    echo "  \033[1;33m(archivio geografico vuoto: prove saltate)\033[0m\n";
} else {
    $noti = [
        ['8.8.8.8',   'US', 'Google'],
        ['1.1.1.1',   'AU', 'Cloudflare'],
        ['151.1.1.1', 'IT', null],
    ];
    foreach ($noti as [$ip, $paese, $org]) {
        prova("{$ip} risulta in {$paese}", static function () use ($ip, $paese) {
            $g = Rete::geolocalizza($ip);

            return ($g !== null && $g['paese'] === $paese)
                ? true : 'ottenuto ' . ($g['paese'] ?? 'niente');
        });
        if ($org !== null) {
            prova("{$ip} appartiene a {$org}", static function () use ($ip, $org) {
                $o = Rete::operatore($ip);

                return ($o !== null && str_contains($o['organizzazione'], $org))
                    ? true : 'ottenuto ' . ($o['organizzazione'] ?? 'niente');
            });
        }
    }

    prova('Un indirizzo privato non viene geolocalizzato', static function () {
        // Gli indirizzi di rete locale non stanno in nessun paese: dire che
        // 192.168.1.1 e' in Kansas sarebbe peggio che non dire niente.
        foreach (['192.168.1.1', '10.0.0.1', '127.0.0.1'] as $ip) {
            $g = Rete::geolocalizza($ip);
            if ($g !== null) {
                return "{$ip} risulta in {$g['paese']}";
            }
        }

        return true;
    });

    prova('Il paese esce col nome italiano', static function () {
        $g = Rete::geolocalizza('8.8.8.8');

        return ($g !== null && $g['paese_nome'] === 'Stati Uniti')
            ? true : 'ottenuto ' . ($g['paese_nome'] ?? '—');
    });
}

echo "\n\033[1;36m══ Impostazioni ══\033[0m\n";

prova('Le impostazioni di partenza ci sono tutte', static function () {
    foreach (['guestbook_attivo', 'guestbook_moderazione', 'guestbook_tetto_ora',
              'guestbook_parole', 'statistiche_pubbliche', 'manutenzione'] as $k) {
        if (Database::valore('SELECT valore FROM impostazioni WHERE chiave = ?', [$k]) === null) {
            return "manca {$k}";
        }
    }

    return true;
});

prova('Una chiave assente restituisce il valore predefinito', static function () {
    // Il portale non deve cadere perche' manca un'impostazione.
    return (Impostazioni::attiva('chiave-che-non-esiste', true) === true
        && Impostazioni::attiva('chiave-che-non-esiste', false) === false
        && Impostazioni::intero('chiave-che-non-esiste', 42) === 42)
        ? true : 'i predefiniti non funzionano';
});

prova('Solo «1» vale come vero', static function () {
    // Una casella non spuntata non viene inviata affatto, e il controller la
    // salva come «0»: qualunque altra cosa dev'essere falsa.
    $prima = Database::valore('SELECT valore FROM impostazioni WHERE chiave = ?', ['manutenzione']);
    Impostazioni::imposta('manutenzione', '0');
    $falso = Impostazioni::attiva('manutenzione', true);
    Impostazioni::imposta('manutenzione', '1');
    $vero = Impostazioni::attiva('manutenzione', false);
    Impostazioni::imposta('manutenzione', (string) $prima);

    return (!$falso && $vero) ? true : "zero da' " . var_export($falso, true) . ", uno da' " . var_export($vero, true);
});

echo "\n\033[1;36m══ Markdown delle pagine redazionali ══\033[0m\n";

prova('I tag HTML non passano', static function () {
    $r = Markdown::rendi('<script>alert(1)</script>');

    return (!str_contains($r, '<script') && str_contains($r, '&lt;script&gt;'))
        ? true : $r;
});

prova('Gli attributi che eseguono codice non passano', static function () {
    $r = Markdown::rendi('<img src=x onerror=alert(1)>');

    // Deve restare testo inerte: nessun tag vero, quindi nessun attributo vero.
    return (!preg_match('/<img/i', $r) && str_contains($r, '&lt;img'))
        ? true : $r;
});

prova('I collegamenti javascript: vengono neutralizzati', static function () {
    $r = Markdown::rendi('[clicca](javascript:alert(1))');

    return !preg_match('/href="javascript:/i', $r) ? true : $r;
});

prova('I collegamenti normali funzionano', static function () {
    $r = Markdown::rendi('[esempio](https://esempio.tld) e [interno](/pagina/x)');

    return (str_contains($r, 'href="https://esempio.tld"') && str_contains($r, 'href="/pagina/x"'))
        ? true : $r;
});

prova('I collegamenti esterni portano rel="noopener"', static function () {
    $r = Markdown::rendi('[fuori](https://esempio.tld)');

    return str_contains($r, 'rel="noopener noreferrer"') ? true : $r;
});

prova('Titoli, elenchi, grassetto e codice si rendono', static function () {
    $r = Markdown::rendi("## Titolo\n\n- primo\n- secondo\n\nTesto con **grassetto** e `codice`.");

    foreach (['<h3>', '<ul>', '<li>', '<strong>', '<code>'] as $tag) {
        if (!str_contains($r, $tag)) {
            return "manca {$tag}";
        }
    }

    return true;
});

echo "\n\033[1;36m══ Statistiche pubbliche ══\033[0m\n";

prova('Le statistiche non espongono dati personali', static function () {
    // Aggregate e basta: nessun nome, nessuna data singola, nessun indirizzo.
    $c = new \App\Controllers\StatisticheController();
    $m = new \ReflectionMethod($c, 'generale');
    $m->setAccessible(true);
    $g = $m->invoke($c);

    foreach ($g as $k => $v) {
        if (!is_int($v)) {
            return "«{$k}» non e' un conteggio ma un " . gettype($v);
        }
    }

    return true;
});

prova('L\'Ascendente non si conta quando l\'ora e\' ignota', static function () {
    // Sarebbe rumore, e falserebbe proprio il grafico piu' delicato: la
    // distribuzione degli ascendenti non e' uniforme nemmeno in teoria.
    $c = new \App\Controllers\StatisticheController();
    $m = new \ReflectionMethod($c, 'distribuzioneSegni');
    $m->setAccessible(true);
    $d = $m->invoke($c);

    $senzaOra = (int) Database::valore(
        'SELECT COUNT(*) FROM calcoli WHERE tipo = ? AND esito LIKE ?',
        ['natale', '%"ora_ignota":true%'],
    );
    $carteConEsito = (int) Database::valore('SELECT COUNT(*) FROM calcoli WHERE tipo = ? AND esito IS NOT NULL', ['natale']);

    if ($senzaOra === 0) {
        return true;   // niente da verificare in questo archivio
    }

    return $d['asc']['totale'] <= $carteConEsito - $senzaOra
        ? true
        : sprintf('%d ascendenti contati su %d carte con ora nota',
            $d['asc']['totale'], $carteConEsito - $senzaOra);
});

prova('Le quote dei grafici stanno fra 0 e 100', static function () {
    $c = new \App\Controllers\StatisticheController();
    $m = new \ReflectionMethod($c, 'distribuzioneSegni');
    $m->setAccessible(true);

    foreach ($m->invoke($c) as $chiave => $blocco) {
        foreach ($blocco['righe'] as $r) {
            if ($r['quota'] < 0 || $r['quota'] > 100) {
                return "{$chiave}/{$r['segno']}: quota {$r['quota']}";
            }
        }
    }

    return true;
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
