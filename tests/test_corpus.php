<?php

declare(strict_types=1);

/**
 * Tema e Cielo — prove del corpus e del montaggio.
 *
 *   php tests/test_corpus.php
 *
 * Richiede il corpus importato (bin/importa-corpus.php).
 *
 * Un testo sbagliato non fa cadere niente: esce una frase storta che nessuno
 * rilegge. Queste prove guardano le cose che a occhio sfuggono — le
 * preposizioni articolate, i frammenti mancanti, le voci che si ripetono, e la
 * regola che schiaccia in fondo le posizioni di generazione.
 */

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use App\Astro\Motore;
use App\Core\Config;
use App\Core\Database;
use App\Corpus\Corpus;
use App\Corpus\Lingua;
use App\Corpus\Montatore;

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

if (!Database::disponibile() || (int) Database::valore('SELECT COUNT(*) FROM testi') === 0) {
    exit("\n\033[1;33mCorpus vuoto: esegui prima php bin/importa-corpus.php\033[0m\n\n");
}

echo "\n\033[1;36m══ Preposizioni articolate ══\033[0m\n";

// Incollare due frammenti in italiano non e' concatenare stringhe: «in» piu'
// «il bisogno» fa «nel bisogno», non «in il bisogno».
$contrazioni = [
    ['trova appoggio in %s.',   'il bisogno di senso',        'nel bisogno di senso'],
    ['sta di fronte a %s.',     'la porosita dei confini',    'alla porosita'],
    ['e\' in attrito con %s.',  'il centro della coscienza',  'col centro'],
    ['e\' congiunto a %s.',     'il Sole',                    'al Sole'],
    ['e\' congiunto a %s.',     'la Luna',                    'alla Luna'],
    ['scorre insieme a %s.',    "l'Ascendente",               "all'Ascendente"],
    ['e\' in quadrato a %s.',   'lo Scorpione',               'allo Scorpione'],
    ['parla di %s.',            'la struttura',               'della struttura'],
    ['dipende da %s.',          'il tempo',                   'dal tempo'],
    ['poggia su %s.',           'le radici',                  'sulle radici'],
];
foreach ($contrazioni as [$modello, $gruppo, $atteso]) {
    prova("«{$gruppo}» → «{$atteso}»", static function () use ($modello, $gruppo, $atteso) {
        $r = Lingua::inserisci($modello, $gruppo);

        return str_contains($r, $atteso) ? true : "ottenuto: {$r}";
    });
}

prova('Un nome proprio non prende l\'articolo', static function () {
    // «al Mercurio» sarebbe sbagliato quanto «in il bisogno».
    $r = Lingua::inserisci('e\' congiunto a %s.', 'Mercurio');

    return str_contains($r, 'a Mercurio') && !str_contains($r, 'al Mercurio')
        ? true : "ottenuto: {$r}";
});

prova('Senza preposizione davanti non si tocca niente', static function () {
    $r = Lingua::inserisci('%s governa la carta.', 'il Sole');

    return $r === 'Il Sole governa la carta.' || $r === 'il Sole governa la carta.'
        ? true : "ottenuto: {$r}";
});

prova('Maiuscola iniziale anche sulle accentate', static function () {
    return Lingua::maiuscola('è il caso') === 'È il caso' ? true : Lingua::maiuscola('è il caso');
});

echo "\n\033[1;36m══ Completezza dei frammenti ══\033[0m\n";

// Se manca un solo frammento, tutte le voci composte che lo usano spariscono
// in silenzio: la relazione esce piu' corta e nessuno sa perche'.
$attesi = [
    'pianeta'           => ['sole', 'luna', 'mercurio', 'venere', 'marte', 'giove', 'saturno', 'urano', 'nettuno', 'plutone'],
    'segno_modo'        => ['ariete', 'toro', 'gemelli', 'cancro', 'leone', 'vergine', 'bilancia',
                            'scorpione', 'sagittario', 'capricorno', 'acquario', 'pesci'],
    'casa_campo'        => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
    'aspetto_relazione' => ['congiunzione', 'opposizione', 'trigono', 'quadrato', 'sestile'],
];
foreach (['tradizionale', 'moderno'] as $reg) {
    prova("I frammenti sono completi — registro {$reg}", static function () use ($attesi, $reg) {
        $c = new Corpus($reg);
        foreach ($attesi as $ambito => $chiavi) {
            foreach ($chiavi as $ch) {
                if ($c->trova($ambito, $ch) === null) {
                    return "manca {$ambito}/{$ch}";
                }
            }
        }

        return true;
    });
}

prova('Ogni pianeta ha un titolo che comincia minuscolo', static function () {
    // Il titolo del pianeta viene incollato dopo una preposizione: se
    // cominciasse maiuscolo uscirebbe «nel Bisogno di senso».
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        foreach ($attesi = ['sole', 'luna', 'mercurio', 'venere', 'marte'] as $p) {
            $t = $c->trova('pianeta', $p);
            if ($t === null) {
                continue;
            }
            $primo = mb_substr((string) $t['titolo'], 0, 1, 'UTF-8');
            // I nomi propri fanno eccezione: «Mercurio» e' giusto maiuscolo.
            if ($primo === mb_strtoupper($primo, 'UTF-8') && !in_array($p, ['mercurio', 'venere', 'marte', 'giove', 'saturno', 'urano', 'nettuno', 'plutone'], true)) {
                return "{$reg}/{$p}: titolo «{$t['titolo']}» comincia maiuscolo";
            }
        }
    }

    return true;
});

prova('Le voci con %s lo hanno in entrambi i registri', static function () {
    // Un %s presente in un registro e assente nell'altro produce una frase
    // monca solo in uno dei due, ed e' il genere di cosa che sfugge.
    $righe = Database::righe(
        'SELECT ambito, chiave,
                SUM(registro = \'tradizionale\' AND corpo LIKE ?) AS t,
                SUM(registro = \'moderno\' AND corpo LIKE ?) AS m,
                COUNT(*) AS n
           FROM testi WHERE ambito IN (?,?) GROUP BY ambito, chiave HAVING n = 2',
        ['%\\%s%', '%\\%s%', 'aspetto_relazione', 'dignita'],
    );
    foreach ($righe as $r) {
        if ((int) $r['t'] !== (int) $r['m']) {
            return "{$r['ambito']}/{$r['chiave']}: il segnaposto c'e' in un registro solo";
        }
    }

    return true;
});

echo "\n\033[1;36m══ Composizione ══\033[0m\n";

prova('Un pianeta in segno si compone anche senza testo scritto', static function () {
    $v = (new Corpus('moderno'))->pianetaInSegno('venere', 3);

    if ($v === null) {
        return 'nessuna voce prodotta';
    }
    if ($v->fonte !== 'composto') {
        return 'fonte ' . $v->fonte;
    }

    return str_contains($v->corpo, 'Cancro') ? true : 'il segno non compare: ' . $v->corpo;
});

prova('Ogni combinazione di pianeta e segno produce una voce', static function () {
    // Centoventi combinazioni per registro: se una sola manca, quella carta
    // esce con un buco.
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        foreach (\App\Astro\Corpi::dieci() as $p) {
            for ($s = 0; $s < 12; $s++) {
                if ($c->pianetaInSegno($p, $s) === null) {
                    return "{$reg}: {$p} nel segno {$s} non produce niente";
                }
            }
        }
    }

    return true;
});

prova('Ogni combinazione di pianeta e casa produce una voce', static function () {
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        foreach (\App\Astro\Corpi::dieci() as $p) {
            for ($h = 1; $h <= 12; $h++) {
                if ($c->pianetaInCasa($p, $h) === null) {
                    return "{$reg}: {$p} in casa {$h} non produce niente";
                }
            }
        }
    }

    return true;
});

prova('Ogni coppia di pianeti in aspetto maggiore produce una voce', static function () {
    $dieci = \App\Astro\Corpi::dieci();
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        foreach (['congiunzione', 'opposizione', 'trigono', 'quadrato', 'sestile'] as $a) {
            for ($i = 0; $i < count($dieci); $i++) {
                for ($j = $i + 1; $j < count($dieci); $j++) {
                    if ($c->aspetto($dieci[$i], $dieci[$j], $a) === null) {
                        return "{$reg}: {$dieci[$i]}/{$dieci[$j]} {$a} non produce niente";
                    }
                }
            }
        }
    }

    return true;
});

prova('L\'aspetto mette per primo il corpo piu\' veloce', static function () {
    // «La Luna e' in trigono a Saturno», non il contrario: e' l'ordine in cui
    // un aspetto si legge.
    $v = (new Corpus('moderno'))->aspetto('saturno', 'luna', 'trigono');

    return ($v !== null && $v->soggetti[0] === 'luna') ? true : 'primo soggetto: ' . ($v->soggetti[0] ?? '—');
});

prova('La chiave di un aspetto non dipende dall\'ordine', static function () {
    $c = new Corpus('moderno');
    $a = $c->aspetto('sole', 'marte', 'quadrato');
    $b = $c->aspetto('marte', 'sole', 'quadrato');

    return ($a !== null && $b !== null && $a->chiave === $b->chiave)
        ? true : ($a?->chiave ?? '—') . ' vs ' . ($b?->chiave ?? '—');
});

prova('Le voci di dignita' . "'" . ' nominano il pianeta', static function () {
    // Un paragrafo che dice «si trova nel segno di cui e' signore» senza mai
    // nominarlo lascia il lettore a chiedersi di chi si parli.
    foreach (['tradizionale', 'moderno'] as $reg) {
        $c = new Corpus($reg);
        foreach (['domicilio', 'esilio', 'caduta', 'retrogrado'] as $d) {
            $v = $c->scritta('dignita', $d, ['saturno'], 'saturno');
            if ($v === null) {
                continue;
            }
            if (!str_contains($v->corpo, 'Saturno')) {
                return "{$reg}/{$d}: il corpo non nomina Saturno";
            }
            if (str_contains($v->corpo, '%s')) {
                return "{$reg}/{$d}: il segnaposto e' rimasto nel testo";
            }
        }
    }

    return true;
});

echo "\n\033[1;36m══ Il montaggio ══\033[0m\n";

$motore = new Motore();
$carte = [
    'normale' => ['anno' => 1978, 'mese' => 6, 'giorno' => 12, 'ora_ut' => 21.2333,
                  'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122, 'sistema_case' => 'placido'],
    'ignota'  => ['anno' => 1955, 'mese' => 6, 'giorno' => 15, 'ora_ut' => 11.0,
                  'lat' => 45.46427, 'lon' => 9.18951, 'alt' => 122,
                  'sistema_case' => 'segni_interi', 'ora_ignota' => true],
];
$temi = [];
foreach ($carte as $n => $d) {
    $temi[$n] = $motore->tema($d);
}

foreach (['tradizionale', 'moderno'] as $reg) {
    prova("Una carta produce una lettura — registro {$reg}", static function () use ($temi, $reg) {
        $r = (new Montatore(new Corpus($reg)))->monta($temi['normale']);

        if ($r['sezioni'] === []) {
            return 'nessuna sezione';
        }
        $voci = array_sum(array_map(static fn (array $s): int => count($s['voci']), $r['sezioni']));

        return $voci >= 10 ? true : "solo {$voci} voci";
    });
}

prova('I due registri dicono cose diverse', static function () use ($temi) {
    $t = (new Montatore(new Corpus('tradizionale')))->monta($temi['normale']);
    $m = (new Montatore(new Corpus('moderno')))->monta($temi['normale']);

    $corpiT = [];
    foreach ($t['sezioni'] as $s) { foreach ($s['voci'] as $v) { $corpiT[] = $v->corpo; } }
    $corpiM = [];
    foreach ($m['sezioni'] as $s) { foreach ($s['voci'] as $v) { $corpiM[] = $v->corpo; } }

    $comuni = array_intersect($corpiT, $corpiM);

    return count($comuni) === 0
        ? true
        : count($comuni) . ' paragrafi identici fra i due registri';
});

prova('I luminari vengono prima di tutto il resto', static function () use ($temi) {
    $r = (new Montatore(new Corpus('moderno')))->monta($temi['normale']);
    $prime = array_slice($r['sezioni']['nucleo']['voci'] ?? [], 0, 2);

    foreach ($prime as $v) {
        if (!in_array('sole', $v->soggetti, true) && !in_array('luna', $v->soggetti, true)) {
            return 'in cima c\'e\' ' . $v->titolo;
        }
    }

    return count($prime) === 2 ? true : 'meno di due voci nel nucleo';
});

prova('Le posizioni di generazione non scalano la classifica', static function () use ($temi) {
    // «Plutone in Bilancia» ce l'hanno tutti i nati fra il 1971 e il 1984: in
    // una lettura personale non dice niente, e non deve stare in cima.
    $r = (new Montatore(new Corpus('moderno')))->monta($temi['normale']);

    foreach ($r['sezioni']['facolta']['voci'] ?? [] as $i => $v) {
        if ($v->ambito === 'pianeta_segno'
            && array_intersect($v->soggetti, ['urano', 'nettuno', 'plutone']) !== []
            && $i < 3) {
            return "«{$v->titolo}» e' in posizione " . ($i + 1) . ' fra le facolta\'';
        }
    }

    return true;
});

prova('Nessun paragrafo si ripete parola per parola', static function () use ($temi) {
    foreach (['tradizionale', 'moderno'] as $reg) {
        $r = (new Montatore(new Corpus($reg)))->monta($temi['normale']);
        $visti = [];
        foreach ($r['sezioni'] as $s) {
            foreach ($s['voci'] as $v) {
                if (isset($visti[$v->corpo])) {
                    return "{$reg}: «" . mb_substr($v->corpo, 0, 50) . '…» compare due volte';
                }
                $visti[$v->corpo] = true;
            }
        }
    }

    return true;
});

prova('Con l\'ora ignota non si parla di case ne\' di assi', static function () use ($temi) {
    // Le cuspidi non esistono: dire in che casa cade un pianeta sarebbe
    // inventare, e la carta lo dichiara gia' altrove.
    $r = (new Montatore(new Corpus('moderno')))->monta($temi['ignota']);

    foreach ($r['sezioni'] as $s) {
        foreach ($s['voci'] as $v) {
            if ($v->ambito === 'pianeta_casa') {
                return 'parla di case: ' . $v->titolo;
            }
            if (array_intersect($v->soggetti, ['asc', 'mc']) !== []) {
                return 'parla degli assi: ' . $v->titolo;
            }
        }
    }

    return true;
});

prova('Nessun corpo monopolizza la lettura', static function () use ($temi) {
    $r = (new Montatore(new Corpus('moderno')))->monta($temi['normale']);
    $conta = [];
    foreach ($r['sezioni'] as $s) {
        foreach ($s['voci'] as $v) {
            foreach ($v->soggetti as $c) {
                $conta[$c] = ($conta[$c] ?? 0) + 1;
            }
        }
    }
    foreach ($conta as $c => $n) {
        if ($n > 4) {
            return "{$c} compare {$n} volte";
        }
    }

    return true;
});

prova('Il conteggio di scritte e composte torna', static function () use ($temi) {
    $r = (new Montatore(new Corpus('moderno')))->monta($temi['normale']);
    $voci = array_sum(array_map(static fn (array $s): int => count($s['voci']), $r['sezioni']));
    $somma = $r['conteggio']['scritte'] + $r['conteggio']['composte'];

    return $somma === $voci ? true : "{$somma} dichiarate, {$voci} presenti";
});

prova('Ogni voce dichiara da dove viene', static function () use ($temi) {
    $r = (new Montatore(new Corpus('moderno')))->monta($temi['normale']);
    foreach ($r['sezioni'] as $s) {
        foreach ($s['voci'] as $v) {
            if (!in_array($v->fonte, ['scritto', 'composto'], true)) {
                return "fonte «{$v->fonte}» in {$v->chiave}";
            }
            if (trim($v->corpo) === '') {
                return "corpo vuoto in {$v->chiave}";
            }
        }
    }

    return true;
});

printf("\n\033[1m%d passate, %d fallite\033[0m\n\n", $passate, $fallite);
exit($fallite === 0 ? 0 : 1);
