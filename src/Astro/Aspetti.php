<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Calcolo degli aspetti fra i punti di una carta.
 */
final class Aspetti
{
    /**
     * Tutti gli aspetti fra i punti dati.
     *
     * @param array<string,array{lon:float,vel:float,nome:string}> $punti
     * @param list<string> $gradi  quali gradi di aspetto ammettere: maggiore, minore
     * @return list<array<string,mixed>>
     */
    public static function calcola(array $punti, array $gradi = ['maggiore'], float $moltiplicatoreOrbe = 1.0): array
    {
        $definizioni = array_filter(
            Corpi::aspetti(),
            static fn (array $a): bool => in_array($a['grado'], $gradi, true),
        );

        $chiavi = array_keys($punti);
        $fuori  = [];

        for ($i = 0; $i < count($chiavi); $i++) {
            for ($j = $i + 1; $j < count($chiavi); $j++) {
                $a = $chiavi[$i];
                $b = $chiavi[$j];

                $separazione = Corpi::distanza($punti[$a]['lon'], $punti[$b]['lon']);

                foreach ($definizioni as $nome => $def) {
                    $orbeMax = $def['orbe'] * Corpi::fattoreOrbe($a, $b) * $moltiplicatoreOrbe;
                    $scarto  = abs($separazione - $def['angolo']);

                    if ($scarto > $orbeMax) {
                        continue;
                    }

                    $fuori[] = [
                        'a'            => $a,
                        'b'            => $b,
                        'nome_a'       => $punti[$a]['nome'],
                        'nome_b'       => $punti[$b]['nome'],
                        'aspetto'      => $nome,
                        'aspetto_nome' => $def['nome'],
                        'glifo'        => $def['glifo'],
                        'natura'       => $def['natura'],
                        'grado'        => $def['grado'],
                        'angolo'       => $def['angolo'],
                        'separazione'  => $separazione,
                        'orbe'         => $scarto,
                        'orbe_massimo' => $orbeMax,
                        'forza'        => round(1.0 - $scarto / $orbeMax, 4),
                        'applicativo'  => self::applicativo(
                            $punti[$a]['lon'], $punti[$a]['vel'],
                            $punti[$b]['lon'], $punti[$b]['vel'],
                            $def['angolo'],
                        ),
                    ];

                    // Un paio di punti puo' formare un aspetto solo: trovato
                    // quello, non si cercano gli altri.
                    break;
                }
            }
        }

        usort($fuori, static fn (array $x, array $y): int => $y['forza'] <=> $x['forza']);

        return $fuori;
    }

    /**
     * Se l'aspetto si sta formando (applicativo) o sciogliendo (separativo).
     *
     * E' la distinzione che conta davvero: un quadrato che sta per compiersi e
     * uno che si sta gia' allentando dicono cose opposte, e molti calcolatori
     * gratuiti non la fanno affatto.
     *
     * Si decide guardando avanti di un'ora: se lo scarto dall'aspetto esatto
     * diminuisce, l'aspetto e' applicativo. Numerico invece che analitico
     * perche' cosi' funziona anche con corpi retrogradi e con i punti a
     * velocita' nulla, senza casi speciali.
     */
    public static function applicativo(
        float $lonA, float $velA, float $lonB, float $velB, float $angolo,
    ): ?bool {
        if ($velA === 0.0 && $velB === 0.0) {
            return null; // fra due punti fissi non c'e' moto: la domanda non si pone
        }

        // Conta il verso in cui si muove lo scarto ADESSO, cioe' il segno della
        // sua derivata. Prima si confrontava lo scarto di ora con quello fra
        // un'ora: se l'aspetto diventava esatto dentro quell'ora, fra un'ora lo
        // scarto era gia' ricresciuto, e un aspetto che si stava stringendo
        // risultava separativo. Con la Luna capitava a ogni aspetto entro un
        // quarto di grado dall'esatto.
        //
        // Il passo e' di circa un secondo: abbastanza piccolo da non scavalcare
        // l'esatto, abbastanza grande da non perdersi negli arrotondamenti.
        $passo = 1.0e-5;

        $ora  = Corpi::distanza($lonA, $lonB);
        $poi  = Corpi::distanza($lonA + $velA * $passo, $lonB + $velB * $passo);
        $scarto = $ora - $angolo;   // con segno: > 0 oltre l'aspetto, < 0 prima
        $moto   = $poi - $ora;      // come cambia la distanza

        if (abs($moto) < 1e-12 || abs($scarto) < 1e-9) {
            return null; // stazionario rispetto all'aspetto, o esatto adesso
        }

        // Applicativo se la distanza va verso l'angolo dell'aspetto.
        return $scarto * $moto < 0.0;
    }

    /**
     * Paralleli e contro-paralleli di declinazione.
     *
     * Sono aspetti a pieno titolo e quasi nessun portale gratuito li calcola.
     * Due corpi alla stessa declinazione agiscono come in congiunzione anche
     * se in longitudine sono lontanissimi.
     *
     * @param array<string,array{decl:float,nome:string}> $punti
     * @return list<array<string,mixed>>
     */
    public static function declinazioni(array $punti, float $orbe = 1.0): array
    {
        $chiavi = array_keys($punti);
        $fuori  = [];

        for ($i = 0; $i < count($chiavi); $i++) {
            for ($j = $i + 1; $j < count($chiavi); $j++) {
                $a = $chiavi[$i];
                $b = $chiavi[$j];
                $da = $punti[$a]['decl'];
                $db = $punti[$b]['decl'];

                $parallelo      = abs($da - $db);
                $controparallelo = abs($da + $db);

                if ($parallelo <= $orbe) {
                    $fuori[] = [
                        'a' => $a, 'b' => $b,
                        'nome_a' => $punti[$a]['nome'], 'nome_b' => $punti[$b]['nome'],
                        'tipo' => 'parallelo', 'tipo_nome' => 'Parallelo',
                        'natura' => 'neutro', 'orbe' => $parallelo,
                        'decl_a' => $da, 'decl_b' => $db,
                    ];
                } elseif ($controparallelo <= $orbe) {
                    $fuori[] = [
                        'a' => $a, 'b' => $b,
                        'nome_a' => $punti[$a]['nome'], 'nome_b' => $punti[$b]['nome'],
                        'tipo' => 'controparallelo', 'tipo_nome' => 'Contro-parallelo',
                        'natura' => 'tensione', 'orbe' => $controparallelo,
                        'decl_a' => $da, 'decl_b' => $db,
                    ];
                }
            }
        }

        usort($fuori, static fn (array $x, array $y): int => $x['orbe'] <=> $y['orbe']);

        return $fuori;
    }

    /**
     * Antiscia e contrantiscia: le riflessioni sull'asse Cancro-Capricorno.
     *
     * Due gradi sono in antiscia quando hanno la stessa declinazione per
     * simmetria rispetto ai solstizi. Antiscio di L = 180 - L, riportato al giro.
     *
     * @param array<string,array{lon:float,nome:string}> $punti
     * @return list<array<string,mixed>>
     */
    public static function antiscia(array $punti, float $orbe = 1.0): array
    {
        $chiavi = array_keys($punti);
        $fuori  = [];

        for ($i = 0; $i < count($chiavi); $i++) {
            for ($j = $i + 1; $j < count($chiavi); $j++) {
                $a = $chiavi[$i];
                $b = $chiavi[$j];

                $antiscio      = Corpi::norma(180.0 - $punti[$a]['lon']);
                $contrantiscio = Corpi::norma(360.0 - $punti[$a]['lon']);

                $d1 = Corpi::distanza($antiscio, $punti[$b]['lon']);
                $d2 = Corpi::distanza($contrantiscio, $punti[$b]['lon']);

                if ($d1 <= $orbe) {
                    $fuori[] = ['a' => $a, 'b' => $b, 'nome_a' => $punti[$a]['nome'],
                                'nome_b' => $punti[$b]['nome'], 'tipo' => 'antiscia',
                                'tipo_nome' => 'Antiscia', 'orbe' => $d1, 'punto' => $antiscio];
                } elseif ($d2 <= $orbe) {
                    $fuori[] = ['a' => $a, 'b' => $b, 'nome_a' => $punti[$a]['nome'],
                                'nome_b' => $punti[$b]['nome'], 'tipo' => 'contrantiscia',
                                'tipo_nome' => 'Contrantiscia', 'orbe' => $d2, 'punto' => $contrantiscio];
                }
            }
        }

        usort($fuori, static fn (array $x, array $y): int => $x['orbe'] <=> $y['orbe']);

        return $fuori;
    }
}
