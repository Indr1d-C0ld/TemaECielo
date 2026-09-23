<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Il rapporto fra due carte.
 *
 * Quattro modi di guardarlo, che dicono cose diverse e non si sostituiscono:
 *
 *   ASPETTI INCROCIATI   i corpi dell'una rispetto a quelli dell'altra: che
 *                        cosa si tocca fra le due persone.
 *   SOVRAPPOSIZIONE      dove cadono i pianeti dell'una nelle case dell'altra:
 *                        in che settori di vita l'altro entra. Nella pratica
 *                        dice piu' degli aspetti.
 *   COMPOSITA            la carta dei punti medi: il rapporto come se fosse
 *                        esso stesso una persona.
 *   DAVISON              la carta dell'istante e del luogo di mezzo fra le due
 *                        nascite. Non e' la stessa cosa della composita, ed e'
 *                        un errore comune confonderle: la composita e' una
 *                        costruzione astratta, la Davison e' un cielo che c'e'
 *                        stato davvero.
 */
final class Sinastria
{
    /**
     * Gli orbi si stringono.
     *
     * Fra due carte i corpi sono venti, non dieci, e le coppie possibili
     * passano da quarantacinque a cento. Con gli orbi di una carta singola
     * uscirebbero ottanta aspetti e non direbbero piu' niente.
     */
    private const STRETTA = 0.65;

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     * @return array<string,mixed>
     */
    public static function fra(array $a, array $b, string $nomeA = 'Primo', string $nomeB = 'Secondo'): array
    {
        $incrociati = self::aspettiIncrociati($a, $b);

        return [
            'nomi'            => ['a' => $nomeA, 'b' => $nomeB],
            'aspetti'         => $incrociati,
            'sovrapposizioni' => [
                'a_in_b' => self::sovrapposizione($a, $b),
                'b_in_a' => self::sovrapposizione($b, $a),
            ],
            'punteggi'        => self::punteggi($incrociati),
            'composita'       => self::composita($a, $b),
            'conteggio'       => self::conteggio($incrociati),
        ];
    }

    /**
     * Gli aspetti fra i corpi dell'una e quelli dell'altra.
     *
     * Si incrociano tutti con tutti: qui non esistono aspetti «interni», sono
     * tutti fra le due persone. Entrano anche gli assi, perche' un pianeta sul
     * tuo Ascendente e' uno dei contatti piu' forti che esistano.
     *
     * `$assiB` esiste per i TRANSITI. Nella sinastria l'Ascendente della
     * seconda persona e' un punto vero e conta; nel cielo di un giorno, invece,
     * l'Ascendente e' solo dove capitava l'orizzonte all'ora del calcolo —
     * percorre l'intero zodiaco ogni ventiquattro ore e come transito non
     * significa niente. Senza questo interruttore uscivano righe come
     * «Medio Cielo in transito quadrato Giove natale», che sono rumore.
     *
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     * @return list<array<string,mixed>>
     */
    public static function aspettiIncrociati(array $a, array $b, bool $assiA = true, bool $assiB = true): array
    {
        $puntiA = self::puntiDi($a, $assiA);
        $puntiB = self::puntiDi($b, $assiB);

        $definizioni = array_filter(
            Corpi::aspetti(),
            static fn (array $x): bool => $x['grado'] === 'maggiore',
        );

        $fuori = [];

        foreach ($puntiA as $ka => $pa) {
            foreach ($puntiB as $kb => $pb) {
                $separazione = Corpi::distanza($pa['lon'], $pb['lon']);

                foreach ($definizioni as $nome => $def) {
                    $orbeMax = $def['orbe'] * Corpi::fattoreOrbe($ka, $kb) * self::STRETTA;
                    $scarto  = abs($separazione - $def['angolo']);

                    if ($scarto > $orbeMax) {
                        continue;
                    }

                    $fuori[] = [
                        'a'            => $ka,
                        'b'            => $kb,
                        'nome_a'       => $pa['nome'],
                        'nome_b'       => $pb['nome'],
                        'aspetto'      => $nome,
                        'aspetto_nome' => $def['nome'],
                        'glifo'        => $def['glifo'],
                        'natura'       => $def['natura'],
                        'orbe'         => round($scarto, 4),
                        'orbe_massimo' => round($orbeMax, 4),
                        'forza'        => round(1.0 - $scarto / $orbeMax, 4),
                    ];
                    break;
                }
            }
        }

        usort($fuori, static fn (array $x, array $y): int => $y['forza'] <=> $x['forza']);

        return $fuori;
    }

    /**
     * Dove cadono i corpi dell'una nelle case dell'altra.
     *
     * Non si calcola quando l'ora di nascita di chi ospita le case e' ignota:
     * le cuspidi non esistono, e dire «il tuo Marte cade nella sua quinta»
     * sarebbe inventare.
     *
     * @param array<string,mixed> $ospite  chi mette i pianeti
     * @param array<string,mixed> $padrone chi mette le case
     * @return list<array<string,mixed>>|null
     */
    public static function sovrapposizione(array $ospite, array $padrone): ?array
    {
        if (($padrone['carta']['ora_ignota'] ?? false) === true) {
            return null;
        }

        $cuspidi = $padrone['case']['cuspidi'] ?? null;
        if (!is_array($cuspidi) || count($cuspidi) !== 12) {
            return null;
        }

        $fuori = [];
        foreach (Corpi::dieci() as $k) {
            if (!isset($ospite['corpi'][$k])) {
                continue;
            }
            $lon = (float) $ospite['corpi'][$k]['lon'];

            $fuori[] = [
                'corpo'     => $k,
                'nome'      => (string) $ospite['corpi'][$k]['nome'],
                'posizione' => Corpi::formatta($lon, false),
                'casa'      => Tema::casaDi($lon, $cuspidi),
            ];
        }

        return $fuori;
    }

    /**
     * I punteggi per area.
     *
     * Un numero solo — «compatibilita' 73%» — non significa niente e non si
     * puo' verificare. Qui si separano quattro cose che nella vita si sentono
     * separate, e di ciascuna si dice DA CHE COSA viene: un punteggio senza il
     * suo perche' e' un oroscopo da rivista.
     *
     * @param list<array<string,mixed>> $aspetti
     * @return array<string,array<string,mixed>>
     */
    public static function punteggi(array $aspetti): array
    {
        $aree = [
            'attrazione' => [
                'nome'  => 'Attrazione',
                'cosa'  => 'Il tiro reciproco: quanto ci si cerca.',
                'coppie'=> [
                    ['sole', 'luna'], ['venere', 'marte'], ['sole', 'venere'], ['luna', 'marte'],
                    ['venere', 'asc'], ['marte', 'asc'], ['sole', 'asc'], ['luna', 'venere'],
                    ['venere', 'plutone'], ['marte', 'plutone'],
                ],
            ],
            'intesa' => [
                'nome'  => 'Intesa mentale',
                'cosa'  => 'Quanto ci si capisce parlando.',
                'coppie'=> [
                    ['mercurio', 'mercurio'], ['mercurio', 'luna'], ['mercurio', 'giove'],
                    ['mercurio', 'urano'], ['mercurio', 'asc'], ['giove', 'giove'],
                    ['mercurio', 'sole'], ['mercurio', 'mc'],
                ],
            ],
            'tenuta' => [
                'nome'  => 'Tenuta nel tempo',
                'cosa'  => 'Quanto il legame regge quando passa l\'entusiasmo.',
                'coppie'=> [
                    ['saturno', 'sole'], ['saturno', 'luna'], ['saturno', 'venere'],
                    ['saturno', 'asc'], ['saturno', 'saturno'], ['sole', 'mc'],
                    ['luna', 'luna'], ['giove', 'venere'],
                ],
            ],
            'attrito' => [
                'nome'  => 'Attrito',
                'cosa'  => 'Dove si finisce per litigare.',
                'coppie'=> [
                    ['marte', 'saturno'], ['marte', 'marte'], ['marte', 'plutone'],
                    ['saturno', 'luna'], ['urano', 'venere'], ['plutone', 'sole'],
                    ['plutone', 'luna'], ['marte', 'urano'], ['saturno', 'sole'],
                ],
                'rovesciata' => true,   // qui la tensione fa punteggio, non l'armonia
            ],
        ];

        $fuori = [];

        foreach ($aree as $chiave => $area) {
            $punti = 0.0;
            $massimo = 0.0;
            $contributi = [];

            foreach ($area['coppie'] as [$x, $y]) {
                // Ogni coppia puo' presentarsi nei due versi: il Sole di lui
                // con la Luna di lei, e la Luna di lui col Sole di lei. Sono
                // due contatti distinti e contano tutti e due.
                $massimo += 2.0;

                foreach ($aspetti as $asp) {
                    $corrisponde = ($asp['a'] === $x && $asp['b'] === $y)
                                || ($asp['a'] === $y && $asp['b'] === $x);
                    if (!$corrisponde) {
                        continue;
                    }

                    // Nell'area dell'attrito un aspetto ARMONICO vale zero e
                    // non compare fra i contributi. Dargli un pesino sembrava
                    // prudente, e produceva «Marte trigono Marte» elencato
                    // sotto «dove si finisce per litigare»: falso e ridicolo.
                    $segno = ($area['rovesciata'] ?? false)
                        ? ($asp['natura'] === 'tensione' ? 1.0 : ($asp['natura'] === 'neutro' ? 0.6 : 0.0))
                        : ($asp['natura'] === 'armonico' ? 1.0 : ($asp['natura'] === 'neutro' ? 0.8 : 0.3));

                    $valore = $segno * (float) $asp['forza'];
                    if ($valore <= 0.0) {
                        continue;
                    }

                    $punti += $valore;

                    $contributi[] = [
                        'testo' => $asp['nome_a'] . ' ' . mb_strtolower((string) $asp['aspetto_nome'], 'UTF-8')
                            . ' ' . $asp['nome_b'],
                        'natura' => $asp['natura'],
                        'peso'   => round($valore, 3),
                    ];
                }
            }

            usort($contributi, static fn (array $p, array $q): int => $q['peso'] <=> $p['peso']);

            $fuori[$chiave] = [
                'nome'       => $area['nome'],
                'cosa'       => $area['cosa'],
                // Il punteggio si satura: dieci contatti non valgono il doppio
                // di cinque, perche' oltre una certa soglia se ne sente uno solo.
                'valore'     => (int) round(100.0 * (1.0 - exp(-2.4 * $punti / max(1.0, $massimo / 4.0)))),
                'contributi' => array_slice($contributi, 0, 5),
                'quanti'     => count($contributi),
            ];
        }

        return $fuori;
    }

    /**
     * La carta composita, fatta di punti medi.
     *
     * Per ogni coppia di corpi si prende il punto di mezzo fra le due
     * longitudini — quello sull'arco PIU' CORTO. E' il dettaglio che si sbaglia
     * facilmente: fra 350 e 10 gradi il punto medio e' a zero, non a 180, e
     * prendere la media aritmetica darebbe il punto opposto del cielo.
     *
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     * @return array<string,mixed>
     */
    public static function composita(array $a, array $b): array
    {
        $corpi = [];

        foreach (Corpi::elenco() as $k => $info) {
            if (!isset($a['corpi'][$k], $b['corpi'][$k])) {
                continue;
            }
            $lon = self::puntoMedio((float) $a['corpi'][$k]['lon'], (float) $b['corpi'][$k]['lon']);

            $corpi[$k] = [
                'nome'      => $info['nome'],
                'lon'       => $lon,
                'segno'     => Corpi::segnoDi($lon),
                'posizione' => Corpi::formatta($lon),
                // Nella composita la retrogradazione non ha senso: non e' un
                // cielo, e' una costruzione. Si dichiara e basta.
                'retrogrado' => false,
            ];
        }

        // Gli assi della composita esistono solo se esistono in entrambe le
        // carte: il punto medio fra un Ascendente vero e il segnaposto di una
        // carta a ora ignota non e' niente, e prima veniva calcolato lo stesso
        // e messo in aspetto coi pianeti.
        $ignota = static fn (array $t): bool => (bool) ($t['carta']['ora_ignota'] ?? false);
        $punti = [];
        foreach ($ignota($a) || $ignota($b) ? [] : ['asc', 'mc'] as $p) {
            if (!isset($a['punti'][$p], $b['punti'][$p])) {
                continue;
            }
            $lon = self::puntoMedio((float) $a['punti'][$p]['lon'], (float) $b['punti'][$p]['lon']);
            $punti[$p] = [
                'nome'      => $a['punti'][$p]['nome'],
                'lon'       => $lon,
                'segno'     => Corpi::segnoDi($lon),
                'posizione' => Corpi::formatta($lon),
            ];
        }

        // Gli aspetti interni alla composita: il rapporto ha le sue tensioni,
        // che non sono la somma di quelle dei due.
        $perAspetti = [];
        foreach ($corpi as $k => $c) {
            $perAspetti[$k] = ['lon' => $c['lon'], 'vel' => 0.0, 'nome' => $c['nome']];
        }
        foreach ($punti as $k => $p) {
            $perAspetti[$k] = ['lon' => $p['lon'], 'vel' => 0.0, 'nome' => $p['nome']];
        }

        return [
            'corpi'   => $corpi,
            'punti'   => $punti,
            'aspetti' => Aspetti::calcola($perAspetti, ['maggiore']),
        ];
    }

    /**
     * Il punto di mezzo fra due longitudini, sull'arco piu' corto.
     */
    public static function puntoMedio(float $a, float $b): float
    {
        $a = Corpi::norma($a);
        $b = Corpi::norma($b);

        $diff = Corpi::norma($b - $a);
        if ($diff > 180.0) {
            $diff -= 360.0;
        }

        return Corpi::norma($a + $diff / 2.0);
    }

    /**
     * Il punto di mezzo geografico fra due luoghi, per la carta di Davison.
     *
     * Non si fa la media delle coordinate: fra 179 e -179 gradi di longitudine
     * darebbe zero, cioe' il meridiano di Greenwich invece del Pacifico. Si
     * passa per i vettori sulla sfera.
     *
     * @return array{0:float,1:float} latitudine e longitudine
     */
    public static function luogoMedio(float $latA, float $lonA, float $latB, float $lonB): array
    {
        $la = deg2rad($latA); $loa = deg2rad($lonA);
        $lb = deg2rad($latB); $lob = deg2rad($lonB);

        $x = (cos($la) * cos($loa) + cos($lb) * cos($lob)) / 2.0;
        $y = (cos($la) * sin($loa) + cos($lb) * sin($lob)) / 2.0;
        $z = (sin($la) + sin($lb)) / 2.0;

        $ipot = sqrt($x * $x + $y * $y);

        // I due punti sono antipodali: il punto di mezzo non e' definito.
        if ($ipot < 1e-9 && abs($z) < 1e-9) {
            return [0.0, 0.0];
        }

        return [rad2deg(atan2($z, $ipot)), rad2deg(atan2($y, $x))];
    }

    /**
     * @param array<string,mixed> $tema
     * @return array<string,array{lon:float,nome:string}>
     */
    private static function puntiDi(array $tema, bool $conAssi = true): array
    {
        $fuori = [];

        foreach (Corpi::dieci() as $k) {
            if (isset($tema['corpi'][$k])) {
                $fuori[$k] = [
                    'lon'  => (float) $tema['corpi'][$k]['lon'],
                    'nome' => (string) $tema['corpi'][$k]['nome'],
                ];
            }
        }

        // Gli assi entrano solo se richiesti e se l'ora e' nota.
        if ($conAssi && ($tema['carta']['ora_ignota'] ?? false) !== true) {
            foreach (['asc', 'mc'] as $p) {
                if (isset($tema['punti'][$p])) {
                    $fuori[$p] = [
                        'lon'  => (float) $tema['punti'][$p]['lon'],
                        'nome' => (string) $tema['punti'][$p]['nome'],
                    ];
                }
            }
        }

        return $fuori;
    }

    /**
     * @param list<array<string,mixed>> $aspetti
     * @return array<string,int>
     */
    private static function conteggio(array $aspetti): array
    {
        $c = ['totale' => count($aspetti), 'armonico' => 0, 'tensione' => 0, 'neutro' => 0, 'stretti' => 0];

        foreach ($aspetti as $a) {
            $c[$a['natura']]++;
            if ((float) $a['orbe'] < 1.0) {
                $c['stretti']++;
            }
        }

        return $c;
    }
}
