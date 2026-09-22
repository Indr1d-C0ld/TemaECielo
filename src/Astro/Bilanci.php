<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Bilanci della carta: elementi, modalita', emisferi, quadranti, figura planetaria.
 */
final class Bilanci
{
    /**
     * Peso di ciascun corpo nei conteggi.
     *
     * Un conteggio grezzo mette Plutone alla pari del Sole, il che non e' quello
     * che intende nessuno quando dice «e' una carta d'acqua». Qui i luminari
     * pesano il doppio e l'Ascendente entra nel conto, perche' il segno che
     * sorge colora l'intera carta.
     */
    private const PESI = [
        'sole' => 2.0, 'luna' => 2.0, 'asc' => 2.0,
        'mercurio' => 1.0, 'venere' => 1.0, 'marte' => 1.0,
        'giove' => 1.0, 'saturno' => 1.0,
        'urano' => 0.5, 'nettuno' => 0.5, 'plutone' => 0.5,
        'mc' => 1.0,
    ];

    /**
     * @param array<string,array{lon:float}> $punti  corpi + asc/mc
     * @return array<string,mixed>
     */
    public static function calcola(array $punti): array
    {
        $segni = Corpi::segni();

        $elementi  = ['fuoco' => 0.0, 'terra' => 0.0, 'aria' => 0.0, 'acqua' => 0.0];
        $modalita  = ['cardinale' => 0.0, 'fisso' => 0.0, 'mobile' => 0.0];
        $polarita  = ['diurna' => 0.0, 'notturna' => 0.0];
        $grezzo    = ['elementi' => ['fuoco' => 0, 'terra' => 0, 'aria' => 0, 'acqua' => 0],
                      'modalita' => ['cardinale' => 0, 'fisso' => 0, 'mobile' => 0]];

        foreach ($punti as $chiave => $p) {
            $peso = self::PESI[$chiave] ?? null;
            if ($peso === null) {
                continue; // i corpi minori non pesano sui bilanci
            }
            $s = $segni[Corpi::segnoDi($p['lon'])];

            $elementi[$s['elemento']] += $peso;
            $modalita[$s['modalita']] += $peso;
            $polarita[$s['polarita']] += $peso;

            $grezzo['elementi'][$s['elemento']]++;
            $grezzo['modalita'][$s['modalita']]++;
        }

        return [
            'elementi'       => $elementi,
            'modalita'       => $modalita,
            'polarita'       => $polarita,
            'grezzo'         => $grezzo,
            'elemento_forte' => self::massimo($elementi),
            'elemento_debole'=> self::minimo($elementi),
            'modalita_forte' => self::massimo($modalita),
        ];
    }

    /**
     * Emisferi e quadranti: dove sta il peso della carta rispetto agli assi.
     *
     * Si conta per CASA, non per arco di longitudine. Con Placido le case sono
     * disuguali — in questa carta la prima e' larga 49 gradi e la quinta 19 —
     * e dividere il cerchio in quattro archi da novanta gradi darebbe quadranti
     * che non coincidono con gli angoli. I quadranti sono definiti dagli assi
     * Ascendente-Discendente e Medio Cielo-Fondo Cielo, cioe' dalle case.
     *
     * @param array<string,array{lon:float,casa?:int}> $corpi  con la casa gia' assegnata
     * @return array<string,mixed>
     */
    public static function emisferi(array $corpi): array
    {
        $sopra = 0; $sotto = 0; $est = 0; $ovest = 0;
        $quadranti = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        foreach (Corpi::dieci() as $chiave) {
            $casa = $corpi[$chiave]['casa'] ?? null;
            if ($casa === null) {
                continue;
            }

            // Case I-VI sotto l'orizzonte, VII-XII sopra.
            if ($casa <= 6) { $sotto++; } else { $sopra++; }

            // L'asse Medio Cielo-Fondo Cielo divide est e ovest. La meta'
            // orientale e' quella che contiene l'Ascendente: case X, XI, XII,
            // I, II, III. Le altre sei sono a ovest.
            if ($casa >= 10 || $casa <= 3) { $est++; } else { $ovest++; }

            $quadranti[(int) ceil($casa / 3)]++;
        }

        return [
            'sopra_orizzonte' => $sopra,
            'sotto_orizzonte' => $sotto,
            'est'             => $est,
            'ovest'           => $ovest,
            'quadranti'       => $quadranti,
        ];
    }

    /**
     * Figura planetaria secondo Marc Edmund Jones.
     *
     * Si ricava dalla sola distribuzione angolare dei dieci corpi, e dice in
     * una parola com'e' ripartita l'energia della carta.
     *
     * @param array<string,array{lon:float}> $corpi
     * @return array{tipo:string,nome:string,ampiezza:float,vuoto_massimo:float,
     *               manico:?string,descrizione:string}
     */
    public static function figura(array $corpi): array
    {
        $lon = [];
        foreach (Corpi::dieci() as $chiave) {
            if (isset($corpi[$chiave])) {
                $lon[$chiave] = Corpi::norma($corpi[$chiave]['lon']);
            }
        }
        if (count($lon) < 10) {
            return ['tipo' => 'indeterminata', 'nome' => 'Indeterminata', 'ampiezza' => 0.0,
                    'vuoto_massimo' => 0.0, 'manico' => null,
                    'descrizione' => 'Servono tutti e dieci i corpi.'];
        }

        asort($lon);
        $chiavi = array_keys($lon);
        $valori = array_values($lon);
        $n      = count($valori);

        // Tutti i vuoti fra corpi consecutivi, girando.
        $vuoti = [];
        for ($i = 0; $i < $n; $i++) {
            $succ = ($i + 1) % $n;
            $vuoti[] = [
                'ampiezza' => Corpi::norma($valori[$succ] - $valori[$i]),
                'dopo'     => $chiavi[$i],
                'prima'    => $chiavi[$succ],
            ];
        }
        usort($vuoti, static fn (array $a, array $b): int => $b['ampiezza'] <=> $a['ampiezza']);

        $vuotoMax = $vuoti[0]['ampiezza'];
        $ampiezza = 360.0 - $vuotoMax;

        // Un «manico» e' un corpo isolato fra due vuoti grandi: e' quello che
        // distingue il secchio dalla ciotola e la fionda dal fascio.
        $manico = null;
        if (count($vuoti) > 1 && $vuoti[0]['ampiezza'] >= 60.0 && $vuoti[1]['ampiezza'] >= 60.0
            && $vuoti[0]['prima'] === $vuoti[1]['dopo']) {
            $manico = $vuoti[0]['prima'];
        }

        [$tipo, $nome, $desc] = match (true) {
            $ampiezza <= 120.0 && $manico !== null =>
                ['fionda', 'Fionda', 'Tutti i corpi in un quarto di cielo, meno uno che tira dall\'altra parte.'],
            $ampiezza <= 120.0 =>
                ['fascio', 'Fascio', 'Tutti i corpi raccolti in un quarto di cielo: energia concentrata su pochi fronti.'],
            $ampiezza <= 186.0 && $manico !== null =>
                ['secchio', 'Secchio', 'I corpi in meta' . "'" . ' cielo, con uno isolato che fa da manico: tutto passa di li' . "'" . '.'],
            $ampiezza <= 186.0 =>
                ['ciotola', 'Ciotola', 'I corpi in una meta' . "'" . ' di cielo: un emisfero pieno e uno vuoto.'],
            $vuotoMax >= 90.0 && $vuotoMax <= 130.0 =>
                ['locomotiva', 'Locomotiva', 'Due terzi di cielo occupati e un terzo vuoto: una spinta che traina.'],
            self::altalena($vuoti) =>
                ['altalena', 'Altalena', 'Due gruppi contrapposti separati da due vuoti: una carta che vive di contrasti.'],
            $vuotoMax < 60.0 =>
                ['spruzzo', 'Spruzzo', 'Corpi sparsi su tutto il cerchio: molti interessi, poca concentrazione.'],
            default =>
                ['locomotiva', 'Locomotiva', 'Distribuzione ampia con un vuoto marcato.'],
        };

        return [
            'tipo'          => $tipo,
            'nome'          => $nome,
            'ampiezza'      => round($ampiezza, 2),
            'vuoto_massimo' => round($vuotoMax, 2),
            'manico'        => $manico,
            'descrizione'   => $desc,
        ];
    }

    /** @param list<array{ampiezza:float,dopo:string,prima:string}> $vuoti */
    private static function altalena(array $vuoti): bool
    {
        return count($vuoti) >= 2 && $vuoti[0]['ampiezza'] >= 60.0 && $vuoti[1]['ampiezza'] >= 60.0;
    }

    /**
     * Albero dei dispositori: chi governa chi, fino al dispositore finale.
     *
     * Un pianeta e' «disposto» dal signore del segno in cui si trova. Seguendo
     * la catena si arriva o a un pianeta nel proprio domicilio — il dispositore
     * finale, che governa tutta la carta — oppure a un anello chiuso, due o piu'
     * pianeti che si governano a vicenda senza che nessuno comandi. Le carte
     * senza dispositore finale sono la maggioranza, e l'anello e' esso stesso
     * un'informazione.
     *
     * @param array<string,array{lon:float}> $corpi
     * @return array<string,mixed>
     */
    public static function dispositori(array $corpi, bool $moderni = false): array
    {
        $segni  = Corpi::segni();
        $chiave = $moderni ? 'domicilio_moderno' : 'domicilio';
        $insieme = $moderni ? Corpi::dieci() : Corpi::sette();

        $disposto = [];
        foreach ($insieme as $c) {
            if (!isset($corpi[$c])) {
                continue;
            }
            $disposto[$c] = $segni[Corpi::segnoDi($corpi[$c]['lon'])][$chiave];
        }

        $finali = [];
        $anelli = [];

        foreach (array_keys($disposto) as $partenza) {
            $visti   = [];
            $attuale = $partenza;

            while (isset($disposto[$attuale]) && !isset($visti[$attuale])) {
                $visti[$attuale] = true;
                $succ = $disposto[$attuale];
                if ($succ === $attuale) {
                    $finali[$attuale] = true;
                    break;
                }
                $attuale = $succ;
            }

            if (isset($visti[$attuale]) && $disposto[$attuale] !== $attuale) {
                // chiuso su se stesso: e' un anello
                $anello = [];
                $c = $attuale;
                do {
                    $anello[] = $c;
                    $c = $disposto[$c];
                } while ($c !== $attuale && count($anello) < 12);

                sort($anello);
                $anelli[implode('-', $anello)] = $anello;
            }
        }

        return [
            'catena'            => $disposto,
            'dispositori_finali'=> array_keys($finali),
            'anelli'            => array_values($anelli),
        ];
    }

    /** @param array<string,float> $m */
    private static function massimo(array $m): string
    {
        return (string) array_search(max($m), $m, true);
    }

    /** @param array<string,float> $m */
    private static function minimo(array $m): string
    {
        return (string) array_search(min($m), $m, true);
    }
}
