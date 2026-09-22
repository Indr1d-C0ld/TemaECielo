<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Le carte derivate: progressioni, direzioni, profezioni.
 *
 * Sono tre modi diversi di far avanzare una carta nel tempo, e non si
 * sostituiscono l'uno all'altro.
 *
 *   PROGRESSIONI SECONDARIE  un giorno di effemeridi vale un anno di vita.
 *                            La carta del ventesimo giorno dopo la nascita e'
 *                            la carta del ventesimo anno. La Luna progredita
 *                            percorre un segno in due anni e mezzo ed e' il
 *                            corpo che dice di piu'.
 *   ARCO SOLARE              tutto avanza dello stesso arco che ha percorso il
 *                            Sole progredito. Piu' grossolano, ma mette in
 *                            moto anche i pianeti lenti, che nelle
 *                            progressioni restano quasi fermi.
 *   PROFEZIONI               l'Ascendente avanza di un segno intero all'anno.
 *                            E' la tecnica piu' antica delle tre e la piu'
 *                            semplice: un solo dato, il signore dell'anno.
 */
final class Derivate
{
    /** L'anno tropico: il tempo fra due equinozi di primavera. */
    private const ANNO = 365.24219;

    /**
     * L'eta' in anni, contata sul Sole e non sul calendario.
     *
     * @return float anni trascorsi, con la parte decimale
     */
    public static function eta(float $jdNascita, float $jdQuando): float
    {
        return ($jdQuando - $jdNascita) / self::ANNO;
    }

    /**
     * Il giorno giuliano della carta progredita.
     *
     * Un giorno per un anno: a quarantasette anni si guarda il cielo del
     * quarantasettesimo giorno dopo la nascita.
     */
    public static function jdProgresso(float $jdNascita, float $jdQuando): float
    {
        return $jdNascita + self::eta($jdNascita, $jdQuando);
    }

    /**
     * L'arco solare: di quanto e' avanzato il Sole nella progressione.
     *
     * @param array<string,mixed> $natale
     * @param array<string,mixed> $progresso
     */
    public static function arcoSolare(array $natale, array $progresso): float
    {
        $a = (float) ($natale['corpi']['sole']['lon'] ?? 0.0);
        $b = (float) ($progresso['corpi']['sole']['lon'] ?? 0.0);

        // Ridotto a [-180, 180): l'arco di una vita umana sta sotto i cento
        // gradi, e prenderlo modulo 360 lo farebbe diventare negativo a caso.
        $arco = Corpi::norma($b - $a);

        return $arco > 180.0 ? $arco - 360.0 : $arco;
    }

    /**
     * Le direzioni di arco solare.
     *
     * Ogni punto della carta natale avanza dello stesso arco. E' una tecnica
     * rozza — tutto si muove alla stessa velocita', che in cielo non succede —
     * ma ha il pregio di mettere in moto anche Saturno e i tre lenti, che nelle
     * progressioni secondarie in ottant'anni si spostano di pochi gradi.
     *
     * @param array<string,mixed> $natale
     * @return array<string,mixed>
     */
    public static function direzioni(array $natale, float $arco): array
    {
        $corpi = [];
        foreach ($natale['corpi'] as $k => $c) {
            $lon = Corpi::norma((float) $c['lon'] + $arco);
            $corpi[$k] = [
                'nome'       => (string) $c['nome'],
                'lon'        => $lon,
                'segno'      => Corpi::segnoDi($lon),
                'posizione'  => Corpi::formatta($lon),
                'natale'     => (string) $c['posizione'],
                'retrogrado' => false,
            ];
        }

        $punti = [];
        foreach ($natale['punti'] as $k => $p) {
            $lon = Corpi::norma((float) $p['lon'] + $arco);
            $punti[$k] = [
                'nome'      => (string) $p['nome'],
                'lon'       => $lon,
                'segno'     => Corpi::segnoDi($lon),
                'posizione' => Corpi::formatta($lon),
                'natale'    => (string) $p['posizione'],
            ];
        }

        return ['arco' => $arco, 'corpi' => $corpi, 'punti' => $punti];
    }

    /**
     * La profezione annuale.
     *
     * L'Ascendente avanza di un segno intero per ogni anno compiuto: a dodici
     * anni torna al segno di partenza, a ventiquattro pure. Il signore del
     * segno profetto e' il «signore dell'anno», e la casa natale che porta
     * quel segno indica il settore di vita che l'anno mette in primo piano.
     *
     * @param array<string,mixed> $natale
     * @return array<string,mixed>|null
     */
    public static function profezione(array $natale, int $anniCompiuti): ?array
    {
        if (($natale['carta']['ora_ignota'] ?? false) === true) {
            return null;   // senza Ascendente non c'e' niente da far avanzare
        }
        if (!isset($natale['punti']['asc']['lon'])) {
            return null;
        }

        $segni = Corpi::segni();
        $segnoNatale = Corpi::segnoDi((float) $natale['punti']['asc']['lon']);
        $segnoAnno = ($segnoNatale + $anniCompiuti) % 12;

        $signore = $segni[$segnoAnno]['domicilio'];

        // In che casa natale cade il segno profetto, e dove sta il suo signore.
        $casa = ($anniCompiuti % 12) + 1;
        $doveSignore = $natale['corpi'][$signore]['casa'] ?? null;

        return [
            'anni'           => $anniCompiuti,
            'segno'          => $segnoAnno,
            'segno_nome'     => $segni[$segnoAnno]['nome'],
            'segno_glifo'    => $segni[$segnoAnno]['glifo'],
            'elemento'       => $segni[$segnoAnno]['elemento'],
            'casa'           => $casa,
            'signore'        => $signore,
            'signore_nome'   => Corpi::elenco()[$signore]['nome'] ?? ucfirst($signore),
            'signore_posizione' => $natale['corpi'][$signore]['posizione'] ?? null,
            'signore_casa'   => $doveSignore,
            'signore_moderno' => $segni[$segnoAnno]['domicilio_moderno'],
        ];
    }

    /**
     * Quanto un corpo si e' DAVVERO spostato fra due carte.
     *
     * La differenza fra due longitudini, ridotta al giro, va bene per il Sole
     * e per i pianeti: in ottant'anni di progressioni non fanno un giro. Per
     * la Luna no — in cinquanta giorni ne fa quasi due — e la differenza
     * ridotta dice «meno settantasette gradi» quando in realta' ne ha
     * percorsi seicentoquaranta in avanti.
     *
     * Si ricostruiscono i giri usando la velocita' media del corpo: il numero
     * di rivoluzioni e' quello che avvicina di piu' il risultato all'arco
     * atteso.
     */
    public static function arcoVero(string $corpo, float $lonNatale, float $lonDerivata, float $giorni): float
    {
        $ridotto = Corpi::norma($lonDerivata - $lonNatale);
        if ($ridotto > 180.0) {
            $ridotto -= 360.0;
        }

        $atteso = Dignita::velocitaMedia($corpo) * $giorni;
        if ($atteso <= 0.0) {
            return $ridotto;
        }

        $giri = round(($atteso - $ridotto) / 360.0);

        return $ridotto + $giri * 360.0;
    }

    /**
     * Gli aspetti fra una carta derivata e quella natale.
     *
     * Gli orbi sono strettissimi — un grado per i maggiori — perche' nelle
     * progressioni un grado vale un anno di vita: con l'orbe di una carta
     * natale, un aspetto resterebbe «attivo» per sedici anni e non direbbe
     * piu' niente.
     *
     * @param array<string,mixed> $derivata
     * @param array<string,mixed> $natale
     * @return list<array<string,mixed>>
     */
    public static function contatti(array $derivata, array $natale, float $orbe = 1.0): array
    {
        $definizioni = array_filter(
            Corpi::aspetti(),
            static fn (array $a): bool => $a['grado'] === 'maggiore',
        );

        $fuori = [];

        foreach ($derivata['corpi'] as $ka => $ca) {
            if (!in_array($ka, Corpi::dieci(), true)) {
                continue;
            }

            foreach (Corpi::dieci() as $kb) {
                if (!isset($natale['corpi'][$kb])) {
                    continue;
                }
                $sep = Corpi::distanza((float) $ca['lon'], (float) $natale['corpi'][$kb]['lon']);

                foreach ($definizioni as $nome => $def) {
                    $scarto = abs($sep - $def['angolo']);
                    if ($scarto > $orbe) {
                        continue;
                    }

                    $fuori[] = [
                        'a' => $ka, 'b' => $kb,
                        'nome_a' => (string) $ca['nome'],
                        'nome_b' => (string) $natale['corpi'][$kb]['nome'],
                        'aspetto' => $nome,
                        'aspetto_nome' => $def['nome'],
                        'glifo' => $def['glifo'],
                        'natura' => $def['natura'],
                        'orbe' => round($scarto, 4),
                        'forza' => round(1.0 - $scarto / $orbe, 4),
                    ];
                    break;
                }
            }

            // Anche gli assi natali ricevono: una progressione sull'Ascendente
            // o sul Medio Cielo e' uno dei passaggi piu' netti che ci siano.
            foreach (['asc', 'mc'] as $asse) {
                if (!isset($natale['punti'][$asse]) || ($natale['carta']['ora_ignota'] ?? false) === true) {
                    continue;
                }
                $sep = Corpi::distanza((float) $ca['lon'], (float) $natale['punti'][$asse]['lon']);

                foreach ($definizioni as $nome => $def) {
                    $scarto = abs($sep - $def['angolo']);
                    if ($scarto > $orbe) {
                        continue;
                    }
                    $fuori[] = [
                        'a' => $ka, 'b' => $asse,
                        'nome_a' => (string) $ca['nome'],
                        'nome_b' => (string) $natale['punti'][$asse]['nome'],
                        'aspetto' => $nome,
                        'aspetto_nome' => $def['nome'],
                        'glifo' => $def['glifo'],
                        'natura' => $def['natura'],
                        'orbe' => round($scarto, 4),
                        'forza' => round(1.0 - $scarto / $orbe, 4),
                    ];
                    break;
                }
            }
        }

        usort($fuori, static fn (array $x, array $y): int => $y['forza'] <=> $x['forza']);

        return $fuori;
    }
}
