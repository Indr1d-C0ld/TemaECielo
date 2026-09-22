<?php

declare(strict_types=1);

namespace App\Grafica;

/**
 * Attrezzi minimi per disegnare in SVG dal server.
 *
 * Niente librerie: un SVG e' testo, e le sole cose che servono davvero sono
 * convertire angoli in punti e comporre archi. Il resto e' concatenazione.
 */
final class Svg
{
    /**
     * Un punto sulla circonferenza.
     *
     * La convenzione e' quella delle carte astrologiche, non quella della
     * matematica: l'angolo zero sta a SINISTRA (dov'e' l'Ascendente) e cresce
     * in senso ANTIORARIO, che e' il verso in cui si succedono le case.
     *
     * @return array{0:float,1:float}
     */
    public static function punto(float $cx, float $cy, float $raggio, float $gradi): array
    {
        $a = (180.0 + $gradi) * M_PI / 180.0;

        return [
            round($cx + $raggio * cos($a), 2),
            round($cy - $raggio * sin($a), 2),
        ];
    }

    /**
     * Arco di circonferenza da un angolo all'altro, come comando di percorso.
     *
     * `$grande` dice se prendere la via lunga: serve perche' un arco da 10 a
     * 350 gradi puo' essere di 20 gradi o di 340, e i due punti da soli non
     * bastano a distinguerli.
     */
    public static function arco(float $cx, float $cy, float $raggio, float $da, float $a, bool $inizia = true): string
    {
        [$x1, $y1] = self::punto($cx, $cy, $raggio, $da);
        [$x2, $y2] = self::punto($cx, $cy, $raggio, $a);

        $ampiezza = fmod($a - $da + 360.0, 360.0);
        $grande   = $ampiezza > 180.0 ? 1 : 0;

        // Spazzata 0 perche' si gira in senso antiorario, e in SVG l'asse y
        // punta in giu': il verso si rovescia.
        return ($inizia ? "M {$x1} {$y1} " : '') . "A {$raggio} {$raggio} 0 {$grande} 0 {$x2} {$y2}";
    }

    /** Il settore compreso fra due raggi e due angoli: la fetta di un anello. */
    public static function settore(float $cx, float $cy, float $rInt, float $rEst, float $da, float $a): string
    {
        [$x1, $y1] = self::punto($cx, $cy, $rInt, $da);
        [$x4, $y4] = self::punto($cx, $cy, $rEst, $da);

        return "M {$x1} {$y1} "
            . self::arco($cx, $cy, $rInt, $da, $a, false) . ' '
            . 'L ' . implode(' ', self::punto($cx, $cy, $rEst, $a)) . ' '
            . self::arco($cx, $cy, $rEst, $a, $da, false) . ' '
            . "L {$x4} {$y4} Z";
    }

    /** Segmento radiale fra due raggi allo stesso angolo. */
    public static function raggio(float $cx, float $cy, float $r1, float $r2, float $gradi): string
    {
        [$x1, $y1] = self::punto($cx, $cy, $r1, $gradi);
        [$x2, $y2] = self::punto($cx, $cy, $r2, $gradi);

        return "M {$x1} {$y1} L {$x2} {$y2}";
    }

    public static function attributi(array $a): string
    {
        $fuori = [];
        foreach ($a as $nome => $valore) {
            if ($valore === null || $valore === '') {
                continue;
            }
            $fuori[] = $nome . '="' . htmlspecialchars((string) $valore, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return implode(' ', $fuori);
    }

    public static function testo(float $x, float $y, string $contenuto, array $attributi = []): string
    {
        $attributi = ['x' => $x, 'y' => $y, 'text-anchor' => 'middle'] + $attributi;

        return '<text ' . self::attributi($attributi) . '>'
            . htmlspecialchars($contenuto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</text>';
    }

    /**
     * Anti-collisione dei glifi, per rilassamento.
     *
     * Quando tre pianeti stanno in due gradi i simboli si sovrappongono e la
     * carta diventa illeggibile: e' il dettaglio che distingue una carta fatta
     * bene da una fatta male.
     *
     * Ogni glifo ha una posizione desiderata — il grado reale — e un ingombro
     * minimo. Si itera spingendo via i vicini troppo stretti finche' nessuno si
     * tocca. Il glifo spostato conserva la propria linea guida verso la tacca
     * del grado vero, cosi' la verita' resta visibile.
     *
     * @param list<float> $gradi  le posizioni desiderate, in gradi
     * @return list<float>        le posizioni dopo il rilassamento
     */
    public static function sciogli(array $gradi, float $minimo, int $iterazioni = 400): array
    {
        $n = count($gradi);
        if ($n < 2) {
            return $gradi;
        }

        // Se l'ingombro totale supera il giro, nessuna disposizione li separa
        // tutti: si distribuiscono a pettine e si lascia che siano le linee
        // guida a dire dove stanno davvero.
        if ($minimo * $n >= 360.0) {
            $passo = 360.0 / $n;
            $fuori = [];
            for ($i = 0; $i < $n; $i++) {
                $fuori[] = fmod($gradi[0] + $i * $passo, 360.0);
            }

            return $fuori;
        }

        // Il cerchio si SROTOLA prima di lavorarci.
        //
        // Lavorare direttamente sugli angoli modulo 360 sembra naturale ma non
        // funziona: appena due glifi si scambiano di posto, il «vicino
        // successivo» calcolato col modulo non e' piu' quello giusto e la
        // spinta va nella direzione sbagliata. Con tre corpi esattamente
        // sovrapposti l'ordine si scombinava e uno restava fermo in mezzo agli
        // altri due.
        //
        // Srotolato, l'ordine e' garantito da costruzione: si ordina, si
        // trasforma in una sequenza crescente sommando i divari, e la
        // chiusura del cerchio diventa una coppia in piu' da controllare.
        $indici = range(0, $n - 1);
        usort($indici, static fn (int $a, int $b): int => $gradi[$a] <=> $gradi[$b]);

        $u = [];
        foreach ($indici as $k => $i) {
            if ($k === 0) {
                $u[0] = $gradi[$i];
                continue;
            }
            $divario = fmod($gradi[$i] - $gradi[$indici[$k - 1]] + 360.0, 360.0);
            // Due glifi allo stesso grado esatto: si separano di un millesimo,
            // giusto per dare un ordine stabile a cui la spinta possa appigliarsi.
            $u[$k] = $u[$k - 1] + ($divario > 0.0 ? $divario : 0.001);
        }

        for ($giro = 0; $giro < $iterazioni; $giro++) {
            $mosso = false;

            for ($k = 0; $k < $n - 1; $k++) {
                $d = $u[$k + 1] - $u[$k];
                if ($d >= $minimo - 1e-9) {
                    continue;
                }
                $spinta = ($minimo - $d) / 2.0;
                $u[$k]     -= $spinta;
                $u[$k + 1] += $spinta;
                $mosso = true;
            }

            // La chiusura: l'ultimo e il primo sono vicini anche loro, passando
            // per lo zero.
            $d = ($u[0] + 360.0) - $u[$n - 1];
            if ($d < $minimo - 1e-9) {
                $spinta = ($minimo - $d) / 2.0;
                $u[$n - 1] -= $spinta;
                $u[0]      += $spinta;
                $mosso = true;
            }

            if (!$mosso) {
                break;
            }
        }

        // Si riavvolge e si rimettono i valori al posto che avevano in partenza.
        $pos = [];
        foreach ($indici as $k => $i) {
            $pos[$i] = fmod($u[$k] + 720.0, 360.0);
        }
        ksort($pos);

        return $pos;
    }
}
