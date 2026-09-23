<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * La carta completa: prende il calcolo grezzo delle effemeridi e ne ricava
 * tutto il resto — case dei corpi, aspetti, dignita', bilanci, configurazioni,
 * signorie, intercettazioni.
 *
 * Da qui in giu' e' matematica in PHP, senza effemeridi: gira anche sotto
 * Apache, e si puo' provare senza libswe.
 */
final class Tema
{
    /**
     * @param array<string,mixed> $grezzo  l'uscita di bin/effemeridi.php
     * @return array<string,mixed>
     */
    public static function componi(array $grezzo, bool $aspettiMinori = false): array
    {
        $corpi   = $grezzo['corpi'];
        $punti   = $grezzo['punti'];
        $cuspidi = $grezzo['case']['cuspidi'];
        $diurna  = (bool) $grezzo['carta']['diurna'];

        // Con l'ora ignota l'Ascendente e il Medio Cielo che il worker mette
        // nella carta sono SEGNAPOSTI — l'inizio del segno del Sole, e novanta
        // gradi prima — che servono a disegnare una carta solare, non a leggerla.
        // Qui si tengono fuori da tutto cio' che li tratterebbe come veri:
        // aspetti, bilanci, emisferi, la casa nelle dignita' accidentali.
        $oraIgnota = (bool) ($grezzo['carta']['ora_ignota'] ?? false);

        // --- casa di ogni corpo e di ogni punto ------------------------------
        foreach ($corpi as $chiave => $c) {
            $corpi[$chiave]['casa']    = self::casaDi((float) $c['lon'], $cuspidi);
            $corpi[$chiave]['segno']   = Corpi::segnoDi((float) $c['lon']);
            $corpi[$chiave]['posizione'] = Corpi::formatta((float) $c['lon']);
        }
        foreach ($punti as $chiave => $p) {
            $punti[$chiave]['casa']  = self::casaDi((float) $p['lon'], $cuspidi);
            $punti[$chiave]['segno'] = Corpi::segnoDi((float) $p['lon']);
            $punti[$chiave]['posizione'] = Corpi::formatta((float) $p['lon']);
        }

        // --- dignita' --------------------------------------------------------
        foreach ($corpi as $chiave => $c) {
            if (!in_array($chiave, Corpi::dieci(), true)) {
                continue;
            }
            $corpi[$chiave]['dignita'] = Dignita::essenziali($chiave, (float) $c['lon'], $diurna);
            $corpi[$chiave]['condizione'] = Dignita::accidentali(
                $chiave,
                $corpi[$chiave],
                // Nella carta solare il Sole sta sempre in prima casa: senza
                // questa riga prendeva +5 «angolare» su ogni carta a ora ignota.
                $oraIgnota ? null : $corpi[$chiave]['casa'],
                $corpi['sole'] ?? null,
                Dignita::velocitaMedia($chiave),
            );
            $corpi[$chiave]['punteggio_totale'] =
                $corpi[$chiave]['dignita']['punteggio'] + $corpi[$chiave]['condizione']['punteggio'];
        }

        // --- aspetti ---------------------------------------------------------
        // Gli assi entrano negli aspetti ma con velocita' zero: il loro moto e'
        // quello del cielo, non loro proprio, e contarlo darebbe applicativi
        // fasulli su ogni carta.
        $perAspetti = [];
        foreach ($corpi as $chiave => $c) {
            $perAspetti[$chiave] = ['lon' => (float) $c['lon'], 'vel' => (float) $c['vel_lon'], 'nome' => (string) $c['nome']];
        }
        foreach ($oraIgnota ? [] : ['asc', 'mc'] as $asse) {
            if (isset($punti[$asse])) {
                $perAspetti[$asse] = ['lon' => (float) $punti[$asse]['lon'], 'vel' => 0.0, 'nome' => (string) $punti[$asse]['nome']];
            }
        }

        $gradi = $aspettiMinori ? ['maggiore', 'minore'] : ['maggiore'];
        $aspetti = Aspetti::calcola($perAspetti, $gradi);

        $perDecl = [];
        foreach ($corpi as $chiave => $c) {
            if (isset($c['decl'])) {
                $perDecl[$chiave] = ['decl' => (float) $c['decl'], 'nome' => (string) $c['nome']];
            }
        }

        // --- case ------------------------------------------------------------
        $case = self::case($cuspidi, $corpi);

        // --- bilanci ---------------------------------------------------------
        $perBilanci = [];
        foreach ($corpi as $chiave => $c) {
            $perBilanci[$chiave] = ['lon' => (float) $c['lon']];
        }
        if (!$oraIgnota) {
            $perBilanci['asc'] = ['lon' => (float) $punti['asc']['lon']];
            $perBilanci['mc']  = ['lon' => (float) $punti['mc']['lon']];
        }

        $perConfig = [];
        foreach (Corpi::dieci() as $chiave) {
            if (isset($corpi[$chiave])) {
                $perConfig[$chiave] = ['lon' => (float) $corpi[$chiave]['lon'], 'nome' => (string) $corpi[$chiave]['nome']];
            }
        }

        return [
            'meta' => [
                'versione_swe' => $grezzo['versione'] ?? null,
                'effemeride'   => $grezzo['effemeride'] ?? null,
                'durata_ms'    => $grezzo['durata_ms'] ?? null,
            ],
            'tempo'  => $grezzo['tempo'],
            'luogo'  => $grezzo['luogo'],
            'carta'  => $grezzo['carta'],
            'corpi'  => $corpi,
            'punti'  => $punti,
            'case'   => $case,
            'aspetti' => [
                'elenco'        => $aspetti,
                'declinazioni'  => Aspetti::declinazioni($perDecl),
                'antiscia'      => Aspetti::antiscia($perConfig),
                'conteggio'     => self::conteggioAspetti($aspetti),
            ],
            'bilanci' => [
                'segni'        => Bilanci::calcola($perBilanci),
                // Gli emisferi si contano sulle case, e le case di una carta
                // solare non dicono dove stava l'orizzonte.
                'emisferi'     => $oraIgnota ? null : Bilanci::emisferi($corpi),
                'figura'       => Bilanci::figura($corpi),
                'dispositori'  => Bilanci::dispositori($corpi),
                'dispositori_moderni' => Bilanci::dispositori($corpi, true),
            ],
            'configurazioni' => Configurazioni::trova($aspetti, $perConfig),
            // I corpi che il motore non ha potuto calcolare per questa data (gli
            // asteroidi fuori dal loro intervallo): la pagina lo deve dire.
            'errori_corpi' => $grezzo['errori_corpi'] ?? [],
            'stelle'   => $grezzo['stelle']   ?? [],
            'fenomeni' => $grezzo['fenomeni'] ?? [],
            'giorno'   => $grezzo['giorno']   ?? [],
            'sizigia'  => $grezzo['sizigia']  ?? null,
            // C'e' solo quando l'ora di nascita e' ignota, ed e' proprio li'
            // che conta: dice quali posizioni restano certe nonostante tutto.
            'arco_giornaliero' => $grezzo['arco_giornaliero'] ?? null,
        ];
    }

    /**
     * In quale casa cade una longitudine.
     *
     * Non basta confrontare i numeri: le cuspidi girano, e la dodicesima casa
     * scavalca quasi sempre lo zero dell'Ariete. Si misura l'arco percorso a
     * partire da ogni cuspide e si guarda se ci si sta dentro.
     *
     * @param list<float> $cuspidi
     */
    public static function casaDi(float $lon, array $cuspidi): int
    {
        $lon = Corpi::norma($lon);

        for ($i = 0; $i < 12; $i++) {
            $a = Corpi::norma((float) $cuspidi[$i]);
            $b = Corpi::norma((float) $cuspidi[($i + 1) % 12]);

            $ampiezza = Corpi::norma($b - $a);
            if ($ampiezza === 0.0) {
                $ampiezza = 360.0;
            }

            if (Corpi::norma($lon - $a) < $ampiezza) {
                return $i + 1;
            }
        }

        return 12;
    }

    /**
     * Le dodici case con signore, contenuto, intercettazioni.
     *
     * @param list<float> $cuspidi
     * @param array<string,array<string,mixed>> $corpi
     * @return array<string,mixed>
     */
    private static function case(array $cuspidi, array $corpi): array
    {
        $segni   = Corpi::segni();
        $romani  = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $elenco  = [];
        $suCuspide = [];

        for ($i = 0; $i < 12; $i++) {
            $lon   = Corpi::norma((float) $cuspidi[$i]);
            $iSeg  = Corpi::segnoDi($lon);
            $succ  = Corpi::norma((float) $cuspidi[($i + 1) % 12]);
            $suCuspide[] = $iSeg;

            $dentro = [];
            foreach ($corpi as $chiave => $c) {
                if (self::casaDi((float) $c['lon'], $cuspidi) === $i + 1) {
                    $dentro[] = $chiave;
                }
            }

            $elenco[] = [
                'numero'          => $i + 1,
                'romano'          => $romani[$i],
                'cuspide'         => $lon,
                'posizione'       => Corpi::formatta($lon, false),
                'segno'           => $iSeg,
                'segno_nome'      => $segni[$iSeg]['nome'],
                'ampiezza'        => round(Corpi::norma($succ - $lon), 3),
                'signore'         => $segni[$iSeg]['domicilio'],
                'signore_moderno' => $segni[$iSeg]['domicilio_moderno'],
                'contiene'        => $dentro,
                'tipo'            => match (true) {
                    in_array($i + 1, [1, 4, 7, 10], true) => 'angolare',
                    in_array($i + 1, [2, 5, 8, 11], true) => 'succedente',
                    default                               => 'cadente',
                },
            ];
        }

        // Segni intercettati: non compaiono su nessuna cuspide, quindi stanno
        // interi dentro una casa. Succede alle latitudini medio-alte con
        // Placido e Koch, e non e' un errore: e' geometria.
        $intercettati = [];
        $duplicati    = [];
        $conteggio    = array_count_values($suCuspide);

        for ($s = 0; $s < 12; $s++) {
            if (!in_array($s, $suCuspide, true)) {
                $casa = self::casaDi($s * 30.0 + 15.0, $cuspidi);
                $intercettati[] = ['segno' => $s, 'nome' => $segni[$s]['nome'], 'casa' => $casa];
            } elseif (($conteggio[$s] ?? 0) > 1) {
                $duplicati[] = ['segno' => $s, 'nome' => $segni[$s]['nome'], 'volte' => $conteggio[$s]];
            }
        }

        return [
            // Le cuspidi grezze restano accanto all'elenco ragionato: chi
            // disegna ha bisogno dei dodici numeri, non di dodici strutture da
            // cui riestrarli. Dimenticarle qui e' costato un anello di case che
            // non veniva tracciato affatto.
            'cuspidi'      => array_map(static fn (float $c): float => Corpi::norma($c), $cuspidi),
            'elenco'       => $elenco,
            'intercettati' => $intercettati,
            'duplicati'    => $duplicati,
        ];
    }

    /**
     * @param list<array<string,mixed>> $aspetti
     * @return array<string,int>
     */
    private static function conteggioAspetti(array $aspetti): array
    {
        $c = ['totale' => count($aspetti), 'armonico' => 0, 'tensione' => 0, 'neutro' => 0,
              'applicativi' => 0, 'separativi' => 0];

        foreach ($aspetti as $a) {
            $c[$a['natura']]++;
            if ($a['applicativo'] === true)  { $c['applicativi']++; }
            if ($a['applicativo'] === false) { $c['separativi']++; }
        }

        return $c;
    }
}
