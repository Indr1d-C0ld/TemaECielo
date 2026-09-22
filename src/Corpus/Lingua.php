<?php

declare(strict_types=1);

namespace App\Corpus;

/**
 * Le preposizioni articolate.
 *
 * Comporre due frammenti in italiano non e' incollare due stringhe. Se il
 * primo finisce con «in» e il secondo comincia con «il bisogno di senso»,
 * l'incollatura da' «in il bisogno di senso», che e' sbagliato e si vede
 * subito. Serve «nel bisogno di senso».
 *
 * Qui si fa solo questo, e si fa bene: sei preposizioni per sette articoli,
 * piu' il caso dei nomi propri che l'articolo non ce l'hanno affatto — «a
 * Mercurio» resta «a Mercurio», e guai a scriverlo «al Mercurio».
 */
final class Lingua
{
    /** @var array<string,array<string,string>> */
    private const CONTRAZIONI = [
        'a'   => ['il' => 'al',   'lo' => 'allo',  'la' => 'alla',  "l'" => "all'",
                  'i'  => 'ai',   'gli' => 'agli', 'le' => 'alle'],
        'in'  => ['il' => 'nel',  'lo' => 'nello', 'la' => 'nella', "l'" => "nell'",
                  'i'  => 'nei',  'gli' => 'negli','le' => 'nelle'],
        'di'  => ['il' => 'del',  'lo' => 'dello', 'la' => 'della', "l'" => "dell'",
                  'i'  => 'dei',  'gli' => 'degli','le' => 'delle'],
        'da'  => ['il' => 'dal',  'lo' => 'dallo', 'la' => 'dalla', "l'" => "dall'",
                  'i'  => 'dai',  'gli' => 'dagli','le' => 'dalle'],
        'su'  => ['il' => 'sul',  'lo' => 'sullo', 'la' => 'sulla', "l'" => "sull'",
                  'i'  => 'sui',  'gli' => 'sugli','le' => 'sulle'],
        'con' => ['il' => 'col',  'i'  => 'coi'],
    ];

    /**
     * Sostituisce il segnaposto contraendo, se serve, la preposizione che lo precede.
     *
     * @param string $modello testo con un %s
     * @param string $gruppo  gruppo nominale, con o senza articolo
     */
    public static function inserisci(string $modello, string $gruppo): string
    {
        $posizione = mb_strpos($modello, '%s');
        if ($posizione === false) {
            return $modello;
        }

        $prima = mb_substr($modello, 0, $posizione);

        // L'ultima parola prima del segnaposto: e' li' che puo' esserci una
        // preposizione da contrarre.
        if (preg_match('/(?:^|\s)([a-zà-ù]+)\s+$/u', $prima, $m) !== 1) {
            return str_replace('%s', $gruppo, $modello);
        }

        $preposizione = mb_strtolower($m[1], 'UTF-8');
        if (!isset(self::CONTRAZIONI[$preposizione])) {
            return str_replace('%s', $gruppo, $modello);
        }

        [$articolo, $resto] = self::scomponi($gruppo);

        // Nome proprio senza articolo: «a Mercurio» va benissimo cosi'.
        if ($articolo === null) {
            return str_replace('%s', $gruppo, $modello);
        }

        $contratta = self::CONTRAZIONI[$preposizione][$articolo] ?? null;
        if ($contratta === null) {
            return str_replace('%s', $gruppo, $modello);
        }

        // Si toglie la preposizione sciolta e si mette quella articolata.
        $senza = mb_substr($prima, 0, mb_strlen($prima) - mb_strlen($m[1]) - 1);
        $dopo  = mb_substr($modello, $posizione + 2);

        // L'articolo elidibile si attacca alla parola: «all'Ascendente».
        $giunzione = str_ends_with($contratta, "'") ? '' : ' ';

        return $senza . $contratta . $giunzione . $resto . $dopo;
    }

    /**
     * Separa l'articolo dal resto del gruppo nominale.
     *
     * @return array{0:?string,1:string}
     */
    public static function scomponi(string $gruppo): array
    {
        $gruppo = ltrim($gruppo);

        // L'apostrofo si attacca: «l'urgenza», non «l' urgenza».
        if (preg_match("/^(l')(.+)$/ui", $gruppo, $m) === 1) {
            return ["l'", $m[2]];
        }

        if (preg_match('/^(il|lo|la|i|gli|le)\s+(.+)$/ui', $gruppo, $m) === 1) {
            return [mb_strtolower($m[1], 'UTF-8'), $m[2]];
        }

        return [null, $gruppo];
    }

    /** Maiuscola iniziale, anche sulle lettere accentate. */
    public static function maiuscola(string $s): string
    {
        if ($s === '') {
            return '';
        }

        return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
    }
}
