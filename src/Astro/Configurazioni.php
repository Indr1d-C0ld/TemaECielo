<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Riconoscimento delle figure d'aspetto: stellium, gran trigono, T-quadrata,
 * gran croce, yod, rettangolo mistico, aquilone.
 *
 * Si lavora sull'elenco degli aspetti gia' calcolato, non sulle longitudini:
 * cosi' le figure rispettano gli stessi orbi degli aspetti che le compongono,
 * e non compaiono gran trigoni fatti di trigoni che non esistono.
 */
final class Configurazioni
{
    /**
     * @param list<array<string,mixed>> $aspetti
     * @param array<string,array{lon:float,nome:string}> $punti
     * @return list<array<string,mixed>>
     */
    public static function trova(array $aspetti, array $punti): array
    {
        $mappa = self::conQuinconci(self::mappa($aspetti), $punti);
        $fuori = [];

        foreach (self::stellium($punti) as $s)            { $fuori[] = $s; }
        foreach (self::triangoli($mappa, $punti) as $s)   { $fuori[] = $s; }
        foreach (self::quadrilateri($mappa, $punti) as $s){ $fuori[] = $s; }

        usort($fuori, static fn (array $a, array $b): int => $b['forza'] <=> $a['forza']);

        return $fuori;
    }

    /**
     * Indice rapido: mappa['sole']['marte'] = 'quadrato'.
     *
     * @param list<array<string,mixed>> $aspetti
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function mappa(array $aspetti): array
    {
        $m = [];
        foreach ($aspetti as $a) {
            $m[$a['a']][$a['b']] = $a;
            $m[$a['b']][$a['a']] = $a;
        }

        return $m;
    }

    /**
     * Aggiunge all'indice le quinconce che mancano, calcolandole dalle posizioni.
     *
     * Lo Yod e' fatto di due quinconce e un sestile, e la quinconce e' un
     * aspetto «minore»: nessuna pagina chiede gli aspetti minori, quindi le
     * quinconce non arrivavano mai qui, e lo Yod — che il codice sapeva
     * riconoscere — non veniva trovato mai. Qui si cercano direttamente, con
     * l'orbe stretto che la tradizione usa proprio per lo Yod. Nessun'altra
     * figura contiene quinconce, quindi aggiungerle non cambia le altre.
     *
     * @param array<string,array<string,array<string,mixed>>> $mappa
     * @param array<string,array{lon:float,nome:string}> $punti
     * @return array<string,array<string,array<string,mixed>>>
     */
    private static function conQuinconci(array $mappa, array $punti): array
    {
        $orbe = 3.0;
        $chiavi = array_keys($punti);
        for ($i = 0; $i < count($chiavi); $i++) {
            for ($j = $i + 1; $j < count($chiavi); $j++) {
                $a = $chiavi[$i]; $b = $chiavi[$j];
                if (isset($mappa[$a][$b])) {
                    continue;
                }
                $scarto = abs(Corpi::distanza((float) $punti[$a]['lon'], (float) $punti[$b]['lon']) - 150.0);
                if ($scarto <= $orbe) {
                    $voce = ['a' => $a, 'b' => $b, 'aspetto' => 'quinconce', 'forza' => round(1.0 - $scarto / $orbe, 3)];
                    $mappa[$a][$b] = $voce;
                    $mappa[$b][$a] = $voce;
                }
            }
        }

        return $mappa;
    }

    /**
     * Stellium: tre o piu' corpi raccolti in poco spazio.
     *
     * Si richiede sia la vicinanza (entro dieci gradi fra estremi) sia, se
     * possibile, lo stesso segno: tre pianeti a cavallo di due segni sono una
     * cosa diversa da tre pianeti nello stesso segno, e vale la pena dirlo.
     *
     * @param array<string,array{lon:float,nome:string}> $punti
     * @return list<array<string,mixed>>
     */
    private static function stellium(array $punti): array
    {
        $lon = [];
        foreach (Corpi::dieci() as $c) {
            if (isset($punti[$c])) {
                $lon[$c] = Corpi::norma($punti[$c]['lon']);
            }
        }
        if (count($lon) < 3) {
            return [];
        }

        asort($lon);
        $chiavi = array_keys($lon);
        $n      = count($chiavi);
        $fuori  = [];
        $usati  = [];

        for ($i = 0; $i < $n; $i++) {
            $gruppo = [$chiavi[$i]];
            for ($k = 1; $k < $n; $k++) {
                $j = ($i + $k) % $n;
                if (Corpi::norma($lon[$chiavi[$j]] - $lon[$chiavi[$i]]) <= 10.0) {
                    $gruppo[] = $chiavi[$j];
                } else {
                    break;
                }
            }

            if (count($gruppo) < 3) {
                continue;
            }

            sort($gruppo);

            // Un gruppo gia' contenuto in uno piu' grande non si ripete: un
            // quadruplo stellium non deve comparire anche come i quattro
            // tripli che contiene. Va fatto con un vero controllo di
            // inclusione fra insiemi — confrontare le firme come stringhe non
            // funziona, perche' «marte-mercurio-venere» non e' sottostringa di
            // «marte-mercurio-sole-venere» pur essendone un sottoinsieme.
            foreach ($usati as $vecchio) {
                if (array_diff($gruppo, $vecchio) === []) {
                    continue 2;
                }
            }
            $usati[] = $gruppo;

            $segni = array_unique(array_map(
                static fn (string $c): int => Corpi::segnoDi($punti[$c]['lon']),
                $gruppo,
            ));

            $fuori[] = [
                'tipo'    => 'stellium',
                'nome'    => 'Stellium',
                'corpi'   => $gruppo,
                'nomi'    => array_map(static fn (string $c): string => $punti[$c]['nome'], $gruppo),
                'dettaglio' => count($segni) === 1
                    ? 'Tutti in ' . Corpi::segni()[reset($segni)]['nome']
                    : 'A cavallo di ' . count($segni) . ' segni',
                'unico_segno' => count($segni) === 1,
                'forza'   => round(min(1.0, count($gruppo) / 4.0), 3),
            ];
        }

        return $fuori;
    }

    /**
     * Figure a tre: gran trigono, T-quadrata, yod.
     *
     * @param array<string,array<string,array<string,mixed>>> $mappa
     * @param array<string,array{lon:float,nome:string}> $punti
     * @return list<array<string,mixed>>
     */
    private static function triangoli(array $mappa, array $punti): array
    {
        $chiavi = array_keys($punti);
        $fuori  = [];

        for ($i = 0; $i < count($chiavi); $i++) {
            for ($j = $i + 1; $j < count($chiavi); $j++) {
                for ($k = $j + 1; $k < count($chiavi); $k++) {
                    $a = $chiavi[$i]; $b = $chiavi[$j]; $c = $chiavi[$k];

                    $ab = $mappa[$a][$b]['aspetto'] ?? null;
                    $bc = $mappa[$b][$c]['aspetto'] ?? null;
                    $ac = $mappa[$a][$c]['aspetto'] ?? null;

                    if ($ab === null || $bc === null || $ac === null) {
                        continue;
                    }

                    $tris  = [$ab, $bc, $ac];
                    sort($tris);
                    $forza = round(($mappa[$a][$b]['forza'] + $mappa[$b][$c]['forza'] + $mappa[$a][$c]['forza']) / 3.0, 3);
                    $nomi  = [$punti[$a]['nome'], $punti[$b]['nome'], $punti[$c]['nome']];

                    // Gran trigono: tre trigoni.
                    if ($tris === ['trigono', 'trigono', 'trigono']) {
                        $elementi = array_unique(array_map(
                            static fn (string $x): string => Corpi::segni()[Corpi::segnoDi($punti[$x]['lon'])]['elemento'],
                            [$a, $b, $c],
                        ));
                        $fuori[] = [
                            'tipo' => 'gran_trigono', 'nome' => 'Gran Trigono',
                            'corpi' => [$a, $b, $c], 'nomi' => $nomi, 'forza' => $forza,
                            'dettaglio' => count($elementi) === 1
                                ? 'In elemento ' . reset($elementi)
                                : 'Dissociato, fra ' . implode(' e ', $elementi),
                        ];
                        continue;
                    }

                    // T-quadrata: un'opposizione e due quadrati sul punto focale.
                    if ($tris === ['opposizione', 'quadrato', 'quadrato']) {
                        $focale = match ('opposizione') {
                            $ab => $c,
                            $bc => $a,
                            default => $b,
                        };
                        $fuori[] = [
                            'tipo' => 't_quadrata', 'nome' => 'T-quadrata',
                            'corpi' => [$a, $b, $c], 'nomi' => $nomi, 'forza' => $forza,
                            'focale' => $focale,
                            'dettaglio' => 'Punto focale: ' . $punti[$focale]['nome'],
                        ];
                        continue;
                    }

                    // Yod: un sestile e due quinconci che convergono.
                    if ($tris === ['quinconce', 'quinconce', 'sestile']) {
                        $focale = match ('sestile') {
                            $ab => $c,
                            $bc => $a,
                            default => $b,
                        };
                        $fuori[] = [
                            'tipo' => 'yod', 'nome' => 'Yod (dito di Dio)',
                            'corpi' => [$a, $b, $c], 'nomi' => $nomi, 'forza' => $forza,
                            'focale' => $focale,
                            'dettaglio' => 'Apice: ' . $punti[$focale]['nome'],
                        ];
                    }
                }
            }
        }

        return $fuori;
    }

    /**
     * Figure a quattro: gran croce, rettangolo mistico, aquilone.
     *
     * @param array<string,array<string,array<string,mixed>>> $mappa
     * @param array<string,array{lon:float,nome:string}> $punti
     * @return list<array<string,mixed>>
     */
    private static function quadrilateri(array $mappa, array $punti): array
    {
        $chiavi = array_keys($punti);
        $n      = count($chiavi);
        $fuori  = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                for ($k = $j + 1; $k < $n; $k++) {
                    for ($l = $k + 1; $l < $n; $l++) {
                        $q = [$chiavi[$i], $chiavi[$j], $chiavi[$k], $chiavi[$l]];

                        $tipi = [];
                        $forze = [];
                        $completo = true;
                        for ($x = 0; $x < 4; $x++) {
                            for ($y = $x + 1; $y < 4; $y++) {
                                $asp = $mappa[$q[$x]][$q[$y]]['aspetto'] ?? null;
                                if ($asp === null) {
                                    $completo = false;
                                    break 2;
                                }
                                $tipi[] = $asp;
                                $forze[] = $mappa[$q[$x]][$q[$y]]['forza'];
                            }
                        }
                        if (!$completo) {
                            continue;
                        }

                        $conto = array_count_values($tipi);
                        $forza = round(array_sum($forze) / count($forze), 3);
                        $nomi  = array_map(static fn (string $c): string => $punti[$c]['nome'], $q);

                        // Gran croce: due opposizioni e quattro quadrati.
                        if (($conto['opposizione'] ?? 0) === 2 && ($conto['quadrato'] ?? 0) === 4) {
                            $modi = array_unique(array_map(
                                static fn (string $x): string => Corpi::segni()[Corpi::segnoDi($punti[$x]['lon'])]['modalita'],
                                $q,
                            ));
                            $fuori[] = ['tipo' => 'gran_croce', 'nome' => 'Gran Croce',
                                        'corpi' => $q, 'nomi' => $nomi, 'forza' => $forza,
                                        'dettaglio' => count($modi) === 1
                                            ? 'In modalita\' ' . reset($modi) : 'Dissociata'];
                            continue;
                        }

                        // Rettangolo mistico: due opposizioni, due trigoni, due sestili.
                        if (($conto['opposizione'] ?? 0) === 2 && ($conto['trigono'] ?? 0) === 2
                            && ($conto['sestile'] ?? 0) === 2) {
                            $fuori[] = ['tipo' => 'rettangolo_mistico', 'nome' => 'Rettangolo mistico',
                                        'corpi' => $q, 'nomi' => $nomi, 'forza' => $forza,
                                        'dettaglio' => 'Tensione e sostegno intrecciati'];
                            continue;
                        }

                        // Aquilone: un gran trigono piu' un corpo opposto a uno dei tre.
                        if (($conto['trigono'] ?? 0) === 3 && ($conto['opposizione'] ?? 0) === 1
                            && ($conto['sestile'] ?? 0) === 2) {
                            $fuori[] = ['tipo' => 'aquilone', 'nome' => 'Aquilone',
                                        'corpi' => $q, 'nomi' => $nomi, 'forza' => $forza,
                                        'dettaglio' => 'Gran trigono con una punta di tensione'];
                        }
                    }
                }
            }
        }

        return $fuori;
    }
}
