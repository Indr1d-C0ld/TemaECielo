<?php

declare(strict_types=1);

namespace App\Cielo;

use App\Core\Database;

/**
 * Il cielo com'era: dal catalogo alle coordinate sull'orizzonte del luogo.
 *
 * Questa classe non usa la Swiss Ephemeris. Le stelle non hanno bisogno di
 * effemeridi: hanno coordinate fisse all'epoca J2000, e per portarle alla data
 * bastano la precessione e due formule di trigonometria sferica. I pianeti,
 * quelli si', arrivano gia' calcolati dal motore.
 */
final class Volta
{
    /**
     * Precessione degli equinozi, dall'epoca J2000 alla data.
     *
     * L'asse terrestre descrive un cono in circa ventiseimila anni: una stella
     * si sposta di un grado ogni settant'anni. Per una carta del cielo di
     * cinquant'anni fa lo scarto e' gia' visibile a occhio, e usare le
     * coordinate di riferimento senza correggerle sarebbe sbagliato quanto
     * usare l'ora sbagliata.
     *
     * Formule IAU 1976: accurate al secondo d'arco su qualche secolo, che e'
     * mille volte piu' di quanto serva a disegnare.
     *
     * @return array{0:float,1:float} ascensione retta e declinazione alla data, in gradi
     */
    public static function precessa(float $ar, float $decl, float $jd): array
    {
        $T = ($jd - 2451545.0) / 36525.0;

        // Gli angoli di Newcomb, in secondi d'arco.
        $zeta  = 2306.2181 * $T + 0.30188 * $T * $T + 0.017998 * $T * $T * $T;
        $z     = 2306.2181 * $T + 1.09468 * $T * $T + 0.018203 * $T * $T * $T;
        $theta = 2004.3109 * $T - 0.42665 * $T * $T - 0.041833 * $T * $T * $T;

        $zeta  = deg2rad($zeta / 3600.0);
        $z     = deg2rad($z / 3600.0);
        $theta = deg2rad($theta / 3600.0);

        $a = deg2rad($ar);
        $d = deg2rad($decl);

        $A = cos($d) * sin($a + $zeta);
        $B = cos($theta) * cos($d) * cos($a + $zeta) - sin($theta) * sin($d);
        $C = sin($theta) * cos($d) * cos($a + $zeta) + cos($theta) * sin($d);

        return [
            fmod(rad2deg(atan2($A, $B) + $z) + 360.0, 360.0),
            rad2deg(asin(max(-1.0, min(1.0, $C)))),
        ];
    }

    /**
     * Da coordinate equatoriali ad altezza e azimut sull'orizzonte del luogo.
     *
     * L'azimut e' contato da NORD verso EST, che e' la convenzione
     * astronomica: 0 a nord, 90 a est, 180 a sud, 270 a ovest.
     *
     * @param float $tsl tempo siderale locale, in gradi
     * @return array{0:float,1:float} altezza e azimut, in gradi
     */
    public static function altAzimut(float $ar, float $decl, float $tsl, float $lat): array
    {
        $H = deg2rad($tsl - $ar);   // angolo orario
        $d = deg2rad($decl);
        $p = deg2rad($lat);

        $sinAlt = sin($d) * sin($p) + cos($d) * cos($p) * cos($H);
        $alt = rad2deg(asin(max(-1.0, min(1.0, $sinAlt))));

        $y = -cos($d) * sin($H);
        $x = sin($d) * cos($p) - cos($d) * sin($p) * cos($H);
        $az = fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);

        return [$alt, $az];
    }

    /**
     * Le stelle visibili da un luogo in un istante.
     *
     * Si filtra per magnitudine PRIMA di calcolare: precessare novemila stelle
     * per poi scartarne meta' e' lavoro buttato.
     *
     * @return list<array<string,mixed>>
     */
    public static function stelle(float $jd, float $tsl, float $lat, float $magLimite = 5.6): array
    {
        $righe = Database::righe(
            'SELECT hip, nome, bayer, costellazione, ar, decl, mag, ci
               FROM stelle WHERE mag <= ? ORDER BY mag ASC',
            [$magLimite],
        );

        $fuori = [];
        foreach ($righe as $s) {
            [$ar, $decl] = self::precessa((float) $s['ar'], (float) $s['decl'], $jd);
            [$alt, $az]  = self::altAzimut($ar, $decl, $tsl, $lat);

            // Un filo sotto l'orizzonte si tiene comunque: serve a chiudere i
            // segmenti delle costellazioni che lo attraversano.
            if ($alt < -2.0) {
                continue;
            }

            $fuori[(int) $s['hip']] = [
                'hip'   => (int) $s['hip'],
                'nome'  => $s['nome'],
                'bayer' => (string) $s['bayer'],
                'con'   => (string) $s['costellazione'],
                'mag'   => (float) $s['mag'],
                'ci'    => $s['ci'] === null ? null : (float) $s['ci'],
                'alt'   => $alt,
                'az'    => $az,
            ];
        }

        return $fuori;
    }

    /**
     * I segmenti di costellazione con entrambi gli estremi sopra l'orizzonte.
     *
     * @param array<int,array<string,mixed>> $stelle indicizzate per numero Hipparcos
     * @return list<array{a:int,b:int,con:string}>
     */
    public static function linee(array $stelle): array
    {
        $fuori = [];
        foreach (Database::righe('SELECT costellazione, hip_a, hip_b FROM costellazioni_linee') as $l) {
            $a = (int) $l['hip_a'];
            $b = (int) $l['hip_b'];
            if (!isset($stelle[$a], $stelle[$b])) {
                continue;
            }
            $fuori[] = ['a' => $a, 'b' => $b, 'con' => (string) $l['costellazione']];
        }

        return $fuori;
    }

    /**
     * Il colore di una stella dall'indice B-V.
     *
     * B-V misura quanto una stella e' piu' luminosa nel blu che nel visibile:
     * negativo per le azzurre caldissime, sopra 1,5 per le rosse fredde. E' il
     * motivo per cui su una carta fatta bene Betelgeuse e' rossa e Rigel
     * azzurra, come in cielo.
     */
    public static function colore(?float $ci): string
    {
        if ($ci === null) {
            return '#f4f6ff';
        }

        return match (true) {
            $ci < -0.15 => '#9db8ff',   // O, B — azzurre
            $ci < 0.05  => '#c4d4ff',   // B, A
            $ci < 0.32  => '#f0f2ff',   // A, F — bianche
            $ci < 0.60  => '#fffaf0',   // F, G — bianco-gialle
            $ci < 0.90  => '#ffe9c4',   // G, K — gialle
            $ci < 1.40  => '#ffc98e',   // K — arancioni
            default     => '#ff9f6b',   // M — rosse
        };
    }

    /**
     * Il raggio con cui disegnare una stella, dalla sua magnitudine.
     *
     * La scala delle magnitudini e' logaritmica e rovesciata: piu' il numero e'
     * basso, piu' la stella e' luminosa. Sirio sta a -1,44 e il limite
     * dell'occhio nudo a 6,5.
     */
    public static function raggio(float $mag): float
    {
        return max(0.35, 2.9 - 0.36 * $mag);
    }

    /**
     * Il colore del cielo, dall'altezza del Sole.
     *
     * Chi e' nato alle tre del pomeriggio non vedeva le stelle, e la sua carta
     * del cielo deve dirlo: un fondo azzurro col Sole alto e nessuna stella e'
     * la verita', un fondo nero pieno di costellazioni e' una bugia graziosa.
     *
     * Le soglie sono quelle dei crepuscoli: civile a -6 gradi, nautico a -12,
     * astronomico a -18. Sotto i -18 e' notte piena.
     *
     * @return array{orizzonte:string,zenit:string,nome:string,stelleVisibili:float}
     */
    public static function cielo(float $altezzaSole): array
    {
        return match (true) {
            $altezzaSole > 6.0 => [
                'orizzonte' => '#9fc4e8', 'zenit' => '#3f7fc4',
                'nome' => 'giorno pieno', 'stelleVisibili' => 0.0,
            ],
            $altezzaSole > 0.0 => [
                'orizzonte' => '#e8b98a', 'zenit' => '#4b7fb8',
                'nome' => 'Sole basso sull\'orizzonte', 'stelleVisibili' => 0.0,
            ],
            $altezzaSole > -6.0 => [
                'orizzonte' => '#c98a5e', 'zenit' => '#2d4a78',
                'nome' => 'crepuscolo civile', 'stelleVisibili' => 0.12,
            ],
            $altezzaSole > -12.0 => [
                'orizzonte' => '#6b5470', 'zenit' => '#1b2c52',
                'nome' => 'crepuscolo nautico', 'stelleVisibili' => 0.45,
            ],
            $altezzaSole > -18.0 => [
                'orizzonte' => '#2e3358', 'zenit' => '#101a38',
                'nome' => 'crepuscolo astronomico', 'stelleVisibili' => 0.8,
            ],
            default => [
                'orizzonte' => '#131a38', 'zenit' => '#070b1c',
                'nome' => 'notte piena', 'stelleVisibili' => 1.0,
            ],
        };
    }

    /**
     * Un punto a un grado da A, nella direzione di B lungo il cerchio massimo.
     *
     * Serve a orientare la Luna, ed e' il modo per farlo senza sbagliare.
     *
     * La tentazione e' di tirare una retta sul foglio dalla Luna al Sole e
     * puntare la gobba di la'. Non funziona: la retta sul foglio NON e' l'arco
     * di cerchio massimo, e a grandi elongazioni sbaglia di molto — con la
     * Luna in gobbosa a 134 gradi dal Sole lo scarto misurato era di CENTO
     * SESSANTA gradi, cioe' la falce dalla parte opposta.
     *
     * Ragionare su angoli di posizione e angoli parallattici funziona, ma
     * richiede di azzeccare tre convenzioni di segno in fila — e su una carta
     * con l'est a sinistra una di quelle e' specchiata.
     *
     * Qui invece si costruisce il punto e basta: si va di un grado da A verso
     * B sulla sfera, si proietta, e la direzione sul foglio esce giusta da
     * sola. La proiezione stereografica conserva gli angoli, quindi per passi
     * piccoli la direzione e' esatta.
     *
     * @return array{0:float,1:float} altezza e azimut del punto spostato
     */
    public static function versoIlPunto(float $altA, float $azA, float $altB, float $azB, float $passo = 1.0): array
    {
        // Terna cartesiana dell'orizzonte: x a nord, y a est, z allo zenit.
        $vettore = static function (float $alt, float $az): array {
            $a = deg2rad($alt);
            $z = deg2rad($az);

            return [cos($a) * cos($z), cos($a) * sin($z), sin($a)];
        };

        $A = $vettore($altA, $azA);
        $B = $vettore($altB, $azB);

        // La componente di B perpendicolare ad A: la tangente alla sfera in A
        // che punta verso B.
        $d = $A[0] * $B[0] + $A[1] * $B[1] + $A[2] * $B[2];
        $T = [$B[0] - $d * $A[0], $B[1] - $d * $A[1], $B[2] - $d * $A[2]];
        $n = sqrt($T[0] ** 2 + $T[1] ** 2 + $T[2] ** 2);

        if ($n < 1e-9) {
            return [$altA, $azA];   // i due punti coincidono o sono opposti
        }

        $T = [$T[0] / $n, $T[1] / $n, $T[2] / $n];

        $e = deg2rad($passo);
        $P = [
            $A[0] * cos($e) + $T[0] * sin($e),
            $A[1] * cos($e) + $T[1] * sin($e),
            $A[2] * cos($e) + $T[2] * sin($e),
        ];

        return [
            rad2deg(asin(max(-1.0, min(1.0, $P[2])))),
            fmod(rad2deg(atan2($P[1], $P[0])) + 360.0, 360.0),
        ];
    }
}
