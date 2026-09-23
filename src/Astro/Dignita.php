<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Dignita' essenziali e accidentali.
 *
 * Le essenziali dicono quanto un pianeta e' «a casa propria» nel grado in cui
 * si trova; le accidentali quanto e' in condizione di agire. Il punteggio
 * numerico segue la tavola di William Lilly (Christian Astrology, 1647), che
 * e' la piu' usata e la piu' facile da verificare.
 */
final class Dignita
{
    /** Soglie solari, in gradi. */
    private const CAZIMI      = 0.2833; // 17 primi: il pianeta e' «nel cuore» del Sole
    private const COMBUSTIONE = 8.5;
    private const SOTTO_RAGGI = 17.0;

    /**
     * Dignita' essenziali di un corpo, con punteggio.
     *
     * @return array<string,mixed>
     */
    public static function essenziali(string $corpo, float $lon, bool $diurna): array
    {
        $iSegno = Corpi::segnoDi($lon);
        $segno  = Corpi::segni()[$iSegno];
        $grado  = Corpi::norma($lon) - $iSegno * 30.0;

        $voci      = [];
        $punteggio = 0;

        if ($segno['domicilio'] === $corpo) {
            $voci[] = ['tipo' => 'domicilio', 'nome' => 'Domicilio', 'punti' => 5];
            $punteggio += 5;
        }

        if ($segno['esaltazione'] !== null && $segno['esaltazione']['corpo'] === $corpo) {
            $esatto = abs($grado - $segno['esaltazione']['grado']) < 1.0;
            $voci[] = ['tipo' => 'esaltazione', 'nome' => 'Esaltazione' . ($esatto ? ' (grado esatto)' : ''), 'punti' => 4];
            $punteggio += 4;
        }

        $tri = Corpi::triplicita()[$segno['elemento']];
        $signoreTri = $diurna ? $tri['giorno'] : $tri['notte'];
        if ($signoreTri === $corpo) {
            $voci[] = ['tipo' => 'triplicita', 'nome' => 'Triplicita\' ' . ($diurna ? 'diurna' : 'notturna'), 'punti' => 3];
            $punteggio += 3;
        } elseif ($tri['partecipante'] === $corpo) {
            $voci[] = ['tipo' => 'triplicita_part', 'nome' => 'Triplicita\' (partecipante)', 'punti' => 1];
            $punteggio += 1;
        }

        $termine = self::termine($iSegno, $grado);
        if ($termine === $corpo) {
            $voci[] = ['tipo' => 'termine', 'nome' => 'Termine egizio', 'punti' => 2];
            $punteggio += 2;
        }

        $decano = self::decano($lon);
        if ($decano === $corpo) {
            $voci[] = ['tipo' => 'decano', 'nome' => 'Decano (faccia)', 'punti' => 1];
            $punteggio += 1;
        }

        if ($segno['esilio'] === $corpo) {
            $voci[] = ['tipo' => 'esilio', 'nome' => 'Esilio (detrimento)', 'punti' => -5];
            $punteggio -= 5;
        }

        if ($segno['caduta'] === $corpo) {
            $voci[] = ['tipo' => 'caduta', 'nome' => 'Caduta', 'punti' => -4];
            $punteggio -= 4;
        }

        // Peregrino: nessuna dignita' essenziale, ne' in bene ne' in male.
        // Vale solo per i sette della tradizione: i tre moderni non hanno
        // termini ne' decani, e dichiararli peregrini sarebbe privo di senso.
        $peregrino = false;
        if ($voci === [] && in_array($corpo, Corpi::sette(), true)) {
            $peregrino = true;
            $voci[] = ['tipo' => 'peregrino', 'nome' => 'Peregrino', 'punti' => -5];
            $punteggio -= 5;
        }

        return [
            'voci'           => $voci,
            'punteggio'      => $punteggio,
            'peregrino'      => $peregrino,
            'termine'        => $termine,
            'decano'         => $decano,
            'signore_segno'  => $segno['domicilio'],
            'signore_moderno'=> $segno['domicilio_moderno'],
        ];
    }

    /**
     * Dignita' accidentali: la condizione del pianeta, non la sua sede.
     *
     * @param array<string,mixed> $corpo
     * @return array<string,mixed>
     */
    public static function accidentali(string $chiave, array $corpo, ?int $casa, ?array $sole, float $velocitaMedia): array
    {
        $voci      = [];
        $punteggio = 0;

        if ($casa !== null) {
            if (in_array($casa, [1, 10], true)) {
                $voci[] = ['tipo' => 'angolare', 'nome' => 'In casa angolare forte', 'punti' => 5];
                $punteggio += 5;
            } elseif (in_array($casa, [4, 7, 11], true)) {
                $voci[] = ['tipo' => 'angolare', 'nome' => 'In casa angolare o succedente forte', 'punti' => 4];
                $punteggio += 4;
            } elseif (in_array($casa, [2, 5], true)) {
                $voci[] = ['tipo' => 'succedente', 'nome' => 'In casa succedente', 'punti' => 3];
                $punteggio += 3;
            } elseif ($casa === 9) {
                $voci[] = ['tipo' => 'cadente', 'nome' => 'In nona casa', 'punti' => 2];
                $punteggio += 2;
            } elseif ($casa === 3) {
                $voci[] = ['tipo' => 'cadente', 'nome' => 'In terza casa', 'punti' => 1];
                $punteggio += 1;
            } elseif ($casa === 12) {
                // Lilly, Christian Astrology: la dodicesima e' la peggiore (-5),
                // la sesta e l'ottava valgono -2. Prima erano tutte e tre -4.
                $voci[] = ['tipo' => 'cadente', 'nome' => 'In dodicesima casa', 'punti' => -5];
                $punteggio -= 5;
            } elseif (in_array($casa, [6, 8], true)) {
                $voci[] = ['tipo' => 'cadente', 'nome' => 'In casa cadente debole', 'punti' => -2];
                $punteggio -= 2;
            }
        }

        // Il moto. I luminari non retrogradano mai: per loro la voce non esiste.
        if (!in_array($chiave, ['sole', 'luna'], true)) {
            if ($corpo['retrogrado']) {
                $voci[] = ['tipo' => 'retrogrado', 'nome' => 'Retrogrado', 'punti' => -5];
                $punteggio -= 5;
            }
            if ($corpo['stazionario']) {
                $voci[] = ['tipo' => 'stazionario', 'nome' => 'Stazionario', 'punti' => 0];
            }
        }

        if ($velocitaMedia > 0.0 && !$corpo['retrogrado']) {
            $rapporto = abs((float) $corpo['vel_lon']) / $velocitaMedia;
            if ($rapporto > 1.15) {
                $voci[] = ['tipo' => 'veloce', 'nome' => 'Piu\' veloce del suo passo medio', 'punti' => 2];
                $punteggio += 2;
            } elseif ($rapporto < 0.85) {
                $voci[] = ['tipo' => 'lento', 'nome' => 'Piu\' lento del suo passo medio', 'punti' => -2];
                $punteggio -= 2;
            }
        }

        // Rapporto col Sole.
        $relazioneSole = null;
        if ($sole !== null && $chiave !== 'sole') {
            $d = Corpi::distanza((float) $corpo['lon'], (float) $sole['lon']);

            if ($d <= self::CAZIMI) {
                $relazioneSole = 'cazimi';
                $voci[] = ['tipo' => 'cazimi', 'nome' => 'Cazimi: nel cuore del Sole', 'punti' => 5];
                $punteggio += 5;
            } elseif ($d <= self::COMBUSTIONE) {
                $relazioneSole = 'combusto';
                $voci[] = ['tipo' => 'combusto', 'nome' => 'Combusto', 'punti' => -5];
                $punteggio -= 5;
            } elseif ($d <= self::SOTTO_RAGGI) {
                $relazioneSole = 'sotto_raggi';
                $voci[] = ['tipo' => 'sotto_raggi', 'nome' => 'Sotto i raggi del Sole', 'punti' => -4];
                $punteggio -= 4;
            }

            // Orientale = sorge prima del Sole. Si decide dal segno della
            // differenza di longitudine ridotta a [-180, 180).
            $scarto = Corpi::norma((float) $corpo['lon'] - (float) $sole['lon']);
            $orientale = $scarto > 180.0;
            $voci[] = [
                'tipo'  => $orientale ? 'orientale' : 'occidentale',
                'nome'  => $orientale ? 'Orientale (sorge prima del Sole)' : 'Occidentale (tramonta dopo il Sole)',
                'punti' => 0,
            ];
        }

        return [
            'voci'           => $voci,
            'punteggio'      => $punteggio,
            'relazione_sole' => $relazioneSole,
        ];
    }

    public static function termine(int $iSegno, float $grado): string
    {
        foreach (Corpi::termini()[$iSegno] as [$signore, $fino]) {
            if ($grado < $fino) {
                return $signore;
            }
        }

        return 'saturno';
    }

    public static function decano(float $lon): string
    {
        return Corpi::decani()[(int) floor(Corpi::norma($lon) / 10.0) % 36];
    }

    /** Velocita' media giornaliera, per giudicare se un corpo e' veloce o lento. */
    public static function velocitaMedia(string $corpo): float
    {
        return match ($corpo) {
            'sole'     => 0.9856,
            'luna'     => 13.1764,
            'mercurio' => 1.3833,
            'venere'   => 1.2,
            'marte'    => 0.5242,
            'giove'    => 0.0831,
            'saturno'  => 0.0335,
            'urano'    => 0.0117,
            'nettuno'  => 0.006,
            'plutone'  => 0.004,
            default    => 0.0,
        };
    }
}
