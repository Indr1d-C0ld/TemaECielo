<?php

declare(strict_types=1);

namespace App\Astro;

use RuntimeException;

/**
 * Il lavoratore delle effemeridi: sta dietro bin/effemeridi.php e gira SOLO
 * da riga di comando, dove l'FFI e' disponibile.
 *
 * Una sola invocazione produce tutto cio' che serve per una carta: posizioni,
 * case, punti calcolati, stelle fisse, fenomeni, levate e tramonti, coordinate
 * orizzontali. Il costo e' un fork di qualche millisecondo, pagato una volta
 * per carta e poi mai piu' grazie alla cache per impronta.
 */
final class Worker
{
    /** L'indice speciale che chiede alla libreria obliquita' e nutazione. */
    private const ECL_NUT = -1;

    public function __construct(private Sweph $swe)
    {
    }

    /**
     * @param array<string,mixed> $domanda
     * @return array<string,mixed>
     */
    public function esegui(array $domanda): array
    {
        return match ((string) ($domanda['operazione'] ?? '')) {
            'tema'      => $this->tema($domanda),
            'posizioni' => $this->posizioni($domanda),
            'stato'     => $this->stato(),
            'ritorno'   => $this->ritorno($domanda),
            default     => throw new RuntimeException('Operazione sconosciuta: ' . (string) ($domanda['operazione'] ?? '')),
        };
    }

    /**
     * Ogni operazione DEVE restituire `ok => true`: e' il contratto su cui si
     * regge Astro\Motore, che senza quel campo considera fallita la risposta.
     *
     * @return array<string,mixed>
     */
    private function stato(): array
    {
        return [
            'ok'         => true,
            'versione'   => $this->swe->versione(),
            'effemeride' => $this->swe->conFileEffemeridi() ? 'swieph' : 'moshier',
            'con_file'   => $this->swe->conFileEffemeridi(),
        ];
    }

    /**
     * Il grosso: la carta grezza.
     *
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function tema(array $d): array
    {
        $anno   = (int) $d['anno'];
        $mese   = (int) $d['mese'];
        $giorno = (int) $d['giorno'];
        $oraUt  = (float) $d['ora_ut'];
        // Esattamente al polo le case non esistono: ogni meridiano e' il
        // meridiano, piu' cuspidi coincidono, e una casa di ampiezza zero faceva
        // cadere tutti i pianeti in prima. Un centesimo di grado dal polo — poco
        // piu' di un chilometro — basta a ridare un senso alla geometria, e non
        // cambia nulla di cio' che si vede in cielo.
        $lat    = max(-89.99, min(89.99, (float) $d['lat']));
        $lon    = (float) $d['lon'];
        $alt    = (float) ($d['alt'] ?? 0.0);

        $this->scartoCivile = isset($d['offset_secondi']) && is_numeric($d['offset_secondi'])
            ? (float) $d['offset_secondi'] / 86400.0
            : null;

        $sistema = (string) ($d['sistema_case'] ?? 'placido');
        $sistemi = Corpi::sistemiCase();
        if (!isset($sistemi[$sistema])) {
            throw new RuntimeException("Sistema di case sconosciuto: {$sistema}");
        }

        $topocentrico = (bool) ($d['topocentrico'] ?? false);
        // Il luogo dell'osservatore si imposta sempre: serve anche quando la
        // carta e' geocentrica, per mettere la Luna nel cielo vero (vedi sotto).
        $this->swe->impostaTopocentrico($lon, $lat, $alt);

        $jd     = $this->swe->giornoGiuliano($anno, $mese, $giorno, $oraUt);
        $deltaT = $this->swe->deltaT($jd);

        // --- corpi -----------------------------------------------------------
        $elenco = Corpi::elenco();
        $voluti = $d['corpi'] ?? array_keys($elenco);
        $corpi  = [];
        $errori = [];

        foreach ($voluti as $chiave) {
            if (!isset($elenco[$chiave])) {
                continue;
            }
            $ipl = $elenco[$chiave]['ipl'];

            try {
                $p = $this->swe->posizione($jd, $ipl, $this->swe->bandieraBase() | ($topocentrico ? Sweph::TOPOCTR : 0));
                $q = $this->swe->posizioneEquatoriale($jd, $ipl);
                // Dove si VEDE il corpo: per la Luna conta la parallasse, che arriva
                // a quasi un grado — a quella distanza, spostarsi dal centro della
                // Terra alla sua superficie cambia la direzione in cui la si vede.
                // Le longitudini della carta restano geocentriche, come vuole la
                // convenzione; altezza e azimut sulla volta devono essere quelli
                // veri, altrimenti vicino all'orizzonte la Luna era disegnata un
                // grado piu' in alto di dove stava.
                $v = ($ipl === Corpi::LUNA && !$topocentrico)
                    ? $this->swe->posizione($jd, $ipl, $this->swe->bandieraBase() | Sweph::TOPOCTR)
                    : $p;
                $o = $this->swe->orizzonte($jd, $v['lon'], $v['lat'], $v['dist'], $lat, $lon, $alt);
            } catch (\Throwable $e) {
                $errori[$chiave] = $e->getMessage();
                continue;
            }

            $corpi[$chiave] = [
                'nome'        => $elenco[$chiave]['nome'],
                'tipo'        => $elenco[$chiave]['tipo'],
                'lon'         => $p['lon'],
                'lat'         => $p['lat'],
                'dist'        => $p['dist'],
                'vel_lon'     => $p['vel_lon'],
                'vel_lat'     => $p['vel_lat'],
                'ar'          => $q['ar'],
                'decl'        => $q['decl'],
                'retrogrado'  => $p['vel_lon'] < 0.0,
                // «Stazionario» non e' velocita' zero esatta, che non capita mai:
                // e' velocita' sotto un millesimo di grado al giorno, dove il
                // moto apparente si ferma per l'osservatore.
                'stazionario' => abs($p['vel_lon']) < 0.001,
                'azimut'      => $o['azimut'],
                'altezza'     => $o['altezza_apparente'],
                // Quella vera, senza rifrazione: e' questa che decide se il Sole
                // e' sopra l'orizzonte astronomico. L'apparente serve a disegnare.
                'altezza_vera' => $o['altezza_vera'],
            ];
        }

        // --- case ------------------------------------------------------------
        $case = $this->swe->case($jd, $lat, $lon, $sistemi[$sistema]['codice']);

        // Con l'ora di nascita ignota le cuspidi non esistono: dipendono dalla
        // rotazione terrestre, che in ventiquattro ore percorre tutto lo
        // zodiaco. Si costruisce allora la CARTA SOLARE, che e' la convenzione
        // per questo caso: il Sole sulla cuspide della prima casa e case per
        // segni interi. Non e' una scappatoia — e' una carta diversa, e viene
        // dichiarata come tale, con tutto cio' che dipende dall'ora marcato
        // inattendibile.
        $oraIgnota = (bool) ($d['ora_ignota'] ?? false);
        if ($oraIgnota && isset($corpi['sole'])) {
            $inizioSegno = Corpi::segnoDi((float) $corpi['sole']['lon']) * 30.0;
            $cuspidi = [];
            for ($i = 0; $i < 12; $i++) {
                $cuspidi[] = Corpi::norma($inizioSegno + $i * 30.0);
            }
            $case['cuspidi'] = $cuspidi;
            $case['asc']     = $inizioSegno;
            $case['mc']      = Corpi::norma($inizioSegno + 270.0);
        }

        // --- obliquita' e nutazione -----------------------------------------
        $eclNut = $this->swe->posizione($jd, self::ECL_NUT, $this->swe->bandieraBase());

        // --- punti calcolati -------------------------------------------------
        // Carta diurna se il Sole sta sopra l'orizzonte VERO. Con l'altezza
        // apparente, che la rifrazione alza di mezzo grado, per qualche minuto
        // prima dell'alba — molto di piu' alle alte latitudini — la carta
        // risultava diurna col Sole ancora sotto l'Ascendente, e la Parte di
        // Fortuna saltava di cento gradi.
        $diurna = isset($corpi['sole']) && $corpi['sole']['altezza_vera'] > 0.0;
        $punti  = $this->punti($corpi, $case, $diurna);

        // --- tempo siderale --------------------------------------------------
        $stGreenwich = $this->swe->tempoSiderale($jd);
        $stLocale    = fmod($stGreenwich + $lon / 15.0 + 24.0, 24.0);

        $fuori = [
            'ok'          => true,
            'versione'    => $this->swe->versione(),
            'effemeride'  => $this->swe->conFileEffemeridi() ? 'swieph' : 'moshier',
            'tempo' => [
                'jd_ut'              => $jd,
                'jd_et'              => $jd + $deltaT,
                'delta_t_secondi'    => $deltaT * 86400.0,
                'siderale_greenwich' => $stGreenwich,
                'siderale_locale'    => $stLocale,
                'obliquita_vera'     => $eclNut['lon'],
                'obliquita_media'    => $eclNut['lat'],
                'nutazione_lon'      => $eclNut['dist'],
                'nutazione_obl'      => $eclNut['vel_lon'],
            ],
            'luogo' => ['lat' => $lat, 'lon' => $lon, 'alt' => $alt],
            'carta' => [
                'diurna'          => $diurna,
                'sistema_case'    => $oraIgnota ? 'solare' : $sistema,
                'sistema_nome'    => $oraIgnota ? 'Carta solare (ora ignota)' : $sistemi[$sistema]['nome'],
                'case_degeneri'   => $case['degenere'],
                'topocentrico'    => $topocentrico,
                'ora_ignota'      => $oraIgnota,
                // Tutto cio' che l'ora ignota rende inattendibile, elencato una
                // volta sola qui perche' chi disegna e chi scrive non debbano
                // ricordarselo a memoria.
                'inattendibili'   => $oraIgnota
                    // «setta»: diurna o notturna non si sa. A mezzogiorno
                    // convenzionale la carta risulta sempre diurna, e da questo
                    // dipendono i signori di triplicita' e la Parte di Fortuna.
                    ? ['asc', 'mc', 'dsc', 'ic', 'vertex', 'fortuna', 'spirito', 'case', 'setta']
                    : [],
            ],
            'corpi' => $corpi,
            'punti' => $punti,
            'case'  => [
                'cuspidi'     => $case['cuspidi'],
                'asc'         => $case['asc'],
                'mc'          => $case['mc'],
                'dsc'         => Corpi::norma($case['asc'] + 180.0),
                'ic'          => Corpi::norma($case['mc'] + 180.0),
                'armc'        => $case['armc'],
                'vertex'      => $case['vertex'],
                'equatoriale' => $case['equatoriale'],
            ],
        ];

        if ($errori !== []) {
            $fuori['errori_corpi'] = $errori;
        }

        // Se per qualche corpo la libreria ha dovuto rinunciare ai file e usare
        // il modello analitico, la carta lo dice. Capita ai margini dell'arco
        // coperto dai file, e agli asteroidi fuori dal loro intervallo.
        if ($this->swe->ripieghi() !== []) {
            $nomi = [];
            foreach (Corpi::elenco() as $k => $info) {
                if (in_array($info['ipl'], $this->swe->ripieghi(), true)) {
                    $nomi[] = $info['nome'];
                }
            }
            $fuori['carta']['effemeride_ripiego'] = $nomi;
        }

        if ((bool) ($d['stelle'] ?? true)) {
            $fuori['stelle'] = $this->stelle($jd, $corpi);
        }

        if ((bool) ($d['effemeridi_giorno'] ?? true)) {
            $fuori['giorno'] = $this->effemeridiGiorno($jd, $lat, $lon, $alt);
        }

        if ($oraIgnota) {
            $fuori['arco_giornaliero'] = $this->arcoGiornaliero($jd, $lat, $lon);
        }

        $sizigia = (bool) ($d['sizigia'] ?? true) ? $this->sizigiaPrenatale($jd) : null;
        if ($sizigia !== null) {
            $fuori['sizigia'] = $sizigia;
        }

        if ((bool) ($d['fenomeni'] ?? true)) {
            $fuori['fenomeni'] = $this->fenomeniLunari($jd, $corpi, $sizigia);
        }

        return $fuori;
    }

    /**
     * Parte di Fortuna, Parte di Spirito e i punti d'angolo.
     *
     * La Parte di Fortuna ha DUE formule, che si scambiano fra carta diurna e
     * notturna. E' l'errore piu' diffuso nei calcolatori gratuiti: chi usa
     * sempre ASC+Luna-Sole sbaglia la meta' delle carte. Il giorno e la notte
     * qui si decidono dall'altezza vera del Sole sull'orizzonte, non dalla casa,
     * cosi' il risultato non dipende dal sistema di domificazione scelto.
     *
     * @param array<string,array<string,mixed>> $corpi
     * @param array<string,mixed> $case
     * @return array<string,array{lon:float,nome:string}>
     */
    private function punti(array $corpi, array $case, bool $diurna): array
    {
        $punti = [
            'asc'    => ['lon' => (float) $case['asc'], 'nome' => 'Ascendente'],
            'mc'     => ['lon' => (float) $case['mc'],  'nome' => 'Medio Cielo'],
            'dsc'    => ['lon' => Corpi::norma((float) $case['asc'] + 180.0), 'nome' => 'Discendente'],
            'ic'     => ['lon' => Corpi::norma((float) $case['mc'] + 180.0),  'nome' => 'Fondo Cielo'],
            'vertex' => ['lon' => (float) $case['vertex'], 'nome' => 'Vertex'],
        ];

        if (isset($corpi['sole'], $corpi['luna'])) {
            $asc  = (float) $case['asc'];
            $sole = (float) $corpi['sole']['lon'];
            $luna = (float) $corpi['luna']['lon'];

            $punti['fortuna'] = [
                'lon'  => Corpi::norma($diurna ? $asc + $luna - $sole : $asc + $sole - $luna),
                'nome' => 'Parte di Fortuna',
            ];
            $punti['spirito'] = [
                'lon'  => Corpi::norma($diurna ? $asc + $sole - $luna : $asc + $luna - $sole),
                'nome' => 'Parte di Spirito',
            ];
        }

        if (isset($corpi['nodo'])) {
            $punti['nodo_sud'] = [
                'lon'  => Corpi::norma((float) $corpi['nodo']['lon'] + 180.0),
                'nome' => 'Nodo Sud',
            ];
        }

        return $punti;
    }

    /**
     * Stelle fisse in congiunzione entro un grado.
     *
     * Le posizioni sono precessate ALLA DATA, non alla J2000: una stella si
     * sposta di circa un grado ogni settant'anni, e usare la posizione di
     * riferimento falserebbe ogni congiunzione. La libreria lo fa per conto suo
     * quando le si passa il giorno giuliano vero.
     *
     * @param array<string,array<string,mixed>> $corpi
     * @return list<array<string,mixed>>
     */
    private function stelle(float $jd, array $corpi): array
    {
        $fuori = [];

        foreach (Corpi::stelleFisse() as $nomeSwe => $nomeIta) {
            $s = $this->swe->stellaFissa($nomeSwe, $jd);
            if ($s === null) {
                continue;
            }

            foreach ($corpi as $chiave => $c) {
                $orbe = Corpi::distanza((float) $c['lon'], $s['lon']);
                if ($orbe > 1.0) {
                    continue;
                }
                $fuori[] = [
                    'stella' => $nomeIta,
                    'corpo'  => $chiave,
                    'lon'    => $s['lon'],
                    'lat'    => $s['lat'],
                    'orbe'   => $orbe,
                ];
            }
        }

        usort($fuori, static fn (array $a, array $b): int => $a['orbe'] <=> $b['orbe']);

        return $fuori;
    }

    /**
     * Fase lunare, illuminazione, eta'.
     *
     * @param array<string,array<string,mixed>> $corpi
     * @param array{tipo:string,jd:float,lon:float}|null $sizigia
     * @return array<string,mixed>
     */
    private function fenomeniLunari(float $jd, array $corpi, ?array $sizigia = null): array
    {
        $luna = $this->swe->fenomeni($jd, Corpi::LUNA);
        $sole = $this->swe->fenomeni($jd, Corpi::SOLE);

        $elong = isset($corpi['sole'], $corpi['luna'])
            ? Corpi::norma((float) $corpi['luna']['lon'] - (float) $corpi['sole']['lon'])
            : $luna['elongazione'];

        return [
            'luna' => [
                'illuminazione' => $luna['fase'],
                'angolo_fase'   => $luna['angolo_fase'],
                'elongazione'   => $elong,
                'diametro'      => $luna['diametro'],
                'fase_nome'     => self::nomeFase($elong),
                // Due eta' diverse, ed e' giusto cosi'. Quella «media» ricava i
                // giorni dall'elongazione supponendo moto uniforme: comoda, ma
                // sbaglia fino a mezza giornata perche' la Luna accelera al
                // perigeo. Quella «vera» conta i giorni dall'ultimo novilunio
                // effettivo, e c'e' solo quando la sizigia prenatale e' un
                // novilunio — se e' un plenilunio, l'ultimo novilunio e' piu'
                // indietro e non e' stato cercato.
                'eta_media_giorni' => $elong / 360.0 * 29.530588853,
                'eta_vera_giorni'  => ($sizigia !== null && $sizigia['tipo'] === 'novilunio')
                    ? $jd - $sizigia['jd']
                    : null,
                'crescente'     => $elong < 180.0,
            ],
            'sole' => [
                'diametro'    => $sole['diametro'],
                'magnitudine' => $sole['magnitudine'],
            ],
        ];
    }

    private static function nomeFase(float $elongazione): string
    {
        return match (true) {
            $elongazione < 11.25  => 'Luna nuova',
            $elongazione < 78.75  => 'Falce crescente',
            $elongazione < 101.25 => 'Primo quarto',
            $elongazione < 168.75 => 'Gibbosa crescente',
            $elongazione < 191.25 => 'Luna piena',
            $elongazione < 258.75 => 'Gibbosa calante',
            $elongazione < 281.25 => 'Ultimo quarto',
            $elongazione < 348.75 => 'Falce calante',
            default               => 'Luna nuova',
        };
    }

    /**
     * Alba, tramonto, crepuscoli e passaggi al meridiano del giorno di nascita.
     *
     * Ogni voce puo' essere nulla: sopra il circolo polare, d'estate, il Sole
     * non tramonta affatto. Un portale che stampasse comunque un orario direbbe
     * una bugia.
     *
     * @return array<string,mixed>
     */
    /**
     * Il giorno giuliano della mezzanotte locale che precede l'istante.
     *
     * Se il chiamante ha passato lo scarto civile (`offset_secondi`), e' la
     * mezzanotte dell'orologio a muro; altrimenti quella del tempo medio della
     * longitudine, che ne differisce di rado di piu' di un'ora.
     */
    private function mezzanotteLocale(float $jd, float $lon): float
    {
        $scarto = $this->scartoCivile ?? $lon / 360.0;

        return floor($jd + $scarto - 0.5) + 0.5 - $scarto;
    }

    /** Scarto civile dal Tempo Universale, in frazioni di giorno; null se ignoto. */
    private ?float $scartoCivile = null;

    private function effemeridiGiorno(float $jd, float $lat, float $lon, float $alt): array
    {
        // Si parte dalla mezzanotte del giorno CIVILE del luogo, non dall'istante
        // di nascita: altrimenti chi nasce alle 23 si vedrebbe l'alba del giorno
        // dopo. E non dalla mezzanotte di Greenwich, come si faceva: a Tokyo alle
        // 8 del mattino l'alba risultava del giorno giusto e il tramonto di
        // quello prima, per una giornata lunga meno dodici ore.
        $mezzanotte = $this->mezzanotteLocale($jd, $lon);

        $fuori = ['sole' => [], 'corpi' => []];

        foreach (['levata' => Sweph::LEVATA, 'tramonto' => Sweph::TRAMONTO,
                  'culminazione' => Sweph::CULMINAZIONE, 'anticulminazione' => Sweph::ANTICULMINE] as $nome => $quale) {
            $t = $this->swe->levataTramonto($mezzanotte, Corpi::SOLE, $quale, $lat, $lon, $alt);
            $fuori['sole'][$nome] = $t;
        }

        $elenco = Corpi::elenco();
        foreach (Corpi::dieci() as $chiave) {
            if ($chiave === 'sole') {
                continue;
            }
            $ipl = $elenco[$chiave]['ipl'];
            $fuori['corpi'][$chiave] = [
                'levata'       => $this->swe->levataTramonto($mezzanotte, $ipl, Sweph::LEVATA, $lat, $lon, $alt),
                'tramonto'     => $this->swe->levataTramonto($mezzanotte, $ipl, Sweph::TRAMONTO, $lat, $lon, $alt),
                'culminazione' => $this->swe->levataTramonto($mezzanotte, $ipl, Sweph::CULMINAZIONE, $lat, $lon, $alt),
            ];
        }

        $fuori['durata_giorno_ore'] = ($fuori['sole']['levata'] !== null && $fuori['sole']['tramonto'] !== null)
            ? ($fuori['sole']['tramonto'] - $fuori['sole']['levata']) * 24.0
            : null;

        return $fuori;
    }

    /**
     * Sizigia prenatale: l'ultimo novilunio o plenilunio prima della nascita.
     *
     * Si cerca all'indietro il cambio di segno dell'elongazione ridotta a
     * [-180, 180) e poi si affina per bisezione. Trenta giorni bastano sempre:
     * il ciclo sinodico e' di 29 giorni e mezzo.
     *
     * @return array{tipo:string,jd:float,lon:float}|null
     */
    private function sizigiaPrenatale(float $jd): ?array
    {
        $scarto = function (float $t): float {
            $s = $this->swe->posizione($t, Corpi::SOLE)['lon'];
            $l = $this->swe->posizione($t, Corpi::LUNA)['lon'];
            $d = Corpi::norma($l - $s);

            // Riportato a [-90, 90): si annulla sia al novilunio sia al plenilunio.
            $d = fmod($d, 180.0);

            return $d > 90.0 ? $d - 180.0 : $d;
        };

        $passo = 0.5;
        $prec  = $scarto($jd);

        for ($i = 1; $i <= 62; $i++) {
            $t = $jd - $i * $passo;
            $cur = $scarto($t);

            // Un salto di segno con entrambi i valori piccoli e' un passaggio
            // vero; un salto con valori grandi e' solo il giro dell'angolo.
            if ($prec * $cur < 0 && abs($prec) < 45.0 && abs($cur) < 45.0) {
                $a = $t; $b = $t + $passo;
                for ($k = 0; $k < 40; $k++) {
                    $m = ($a + $b) / 2.0;
                    if ($scarto($a) * $scarto($m) <= 0) { $b = $m; } else { $a = $m; }
                }
                $jdS = ($a + $b) / 2.0;

                $lonS = $this->swe->posizione($jdS, Corpi::LUNA)['lon'];
                $lonSole = $this->swe->posizione($jdS, Corpi::SOLE)['lon'];

                return [
                    'tipo' => Corpi::distanza($lonS, $lonSole) < 90.0 ? 'novilunio' : 'plenilunio',
                    'jd'   => $jdS,
                    'lon'  => $lonS,
                ];
            }
            $prec = $cur;
        }

        return null;
    }

    /**
     * Quanto si muovono i corpi nelle ventiquattro ore del giorno di nascita.
     *
     * Quando l'ora non si sa, questo e' il dato piu' onesto che si possa dare:
     * «quel giorno la Luna e' passata da 4 a 17 gradi dei Gemelli, quindi in
     * Gemelli ci resta comunque» e' un'informazione vera e utile. «L'Ascendente
     * ha percorso l'intero zodiaco» lo e' altrettanto, e dice al lettore di non
     * fidarsi delle case.
     *
     * @return array<string,mixed>
     */
    private function arcoGiornaliero(float $jd, float $lat, float $lon): array
    {
        $mezzanotte = $this->mezzanotteLocale($jd, $lon);
        $fuori = [];

        foreach (Corpi::dieci() as $chiave) {
            $ipl = Corpi::elenco()[$chiave]['ipl'];
            try {
                $a = $this->swe->posizione($mezzanotte, $ipl)['lon'];
                $b = $this->swe->posizione($mezzanotte + 1.0, $ipl)['lon'];
            } catch (\Throwable) {
                continue;
            }

            $segnoA = Corpi::segnoDi($a);
            $segnoB = Corpi::segnoDi($b);

            // L'arco va col segno. Ridotto al giro, un pianeta retrogrado che
            // e' tornato indietro di due primi risulterebbe averne percorsi
            // 359 gradi e 58 primi: vero in aritmetica, assurdo da leggere.
            $arco = Corpi::norma($b - $a);
            if ($arco > 180.0) {
                $arco -= 360.0;
            }

            $fuori[$chiave] = [
                'da'          => $a,
                'a'           => $b,
                'da_testo'    => Corpi::formatta($a, false),
                'a_testo'     => Corpi::formatta($b, false),
                'arco'        => round($arco, 4),
                'retrogrado'  => $arco < 0.0,
                // Se il corpo non cambia segno nell'arco della giornata, quel
                // dato e' certo anche senza sapere l'ora: vale la pena dirlo.
                'cambia_segno' => $segnoA !== $segnoB,
            ];
        }

        // L'Ascendente percorre tutto lo zodiaco in un giorno per definizione:
        // non c'e' niente da calcolare, e nessuna sua posizione e' attendibile.
        $fuori['asc'] = [
            'da' => 0.0, 'a' => 360.0, 'da_testo' => '—', 'a_testo' => '—',
            'arco' => 360.0, 'cambia_segno' => true,
        ];

        return $fuori;
    }

    /**
     * L'istante in cui un corpo torna a una longitudine data.
     *
     * Serve alla RIVOLUZIONE SOLARE — il momento in cui il Sole ripassa
     * esattamente sul grado che occupava alla nascita — e alla rivoluzione
     * lunare. Non e' il compleanno: il Sole ci mette 365 giorni e un quarto a
     * tornare, quindi l'istante casca ogni anno a un'ora diversa e puo'
     * scivolare al giorno prima o al giorno dopo.
     *
     * Si cerca per bisezione sullo scarto ridotto a [-180, 180): cosi' la
     * funzione cambia segno una volta sola nell'intorno, e il metodo converge
     * sempre. Cercare il minimo della distanza, invece, si ferma su minimi
     * falsi quando il corpo e' retrogrado.
     *
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function ritorno(array $d): array
    {
        $ipl = Corpi::elenco()[(string) ($d['corpo'] ?? 'sole')]['ipl'] ?? Corpi::SOLE;
        $bersaglio = Corpi::norma((float) $d['longitudine']);
        $da = (float) $d['jd_da'];
        $a  = (float) ($d['jd_a'] ?? ($da + 400.0));

        $scarto = function (float $t) use ($ipl, $bersaglio): float {
            $x = Corpi::norma($this->swe->posizione($t, $ipl)['lon'] - $bersaglio);

            return $x > 180.0 ? $x - 360.0 : $x;
        };

        // Il passo grossolano dev'essere piu' corto del tempo che il corpo
        // impiega a percorrere mezzo giro, o si salta il passaggio.
        $passo = $ipl === Corpi::LUNA ? 0.5 : 5.0;

        $prec = $scarto($da);
        for ($t = $da + $passo; $t <= $a; $t += $passo) {
            $cur = $scarto($t);

            if ($prec * $cur < 0 && abs($prec) < 90.0 && abs($cur) < 90.0) {
                $x = $t - $passo;
                $y = $t;
                for ($i = 0; $i < 60; $i++) {
                    $m = ($x + $y) / 2.0;
                    if ($scarto($x) * $scarto($m) <= 0) { $y = $m; } else { $x = $m; }
                }
                $jd = ($x + $y) / 2.0;
                $data = $this->swe->daGiornoGiuliano($jd);

                return [
                    'ok'       => true,
                    'jd_ut'    => $jd,
                    'anno'     => $data['anno'],
                    'mese'     => $data['mese'],
                    'giorno'   => $data['giorno'],
                    'ora_ut'   => $data['ora'],
                    'scarto'   => $this->swe->posizione($jd, $ipl)['lon'] - $bersaglio,
                ];
            }
            $prec = $cur;
        }

        throw new RuntimeException('Nessun ritorno trovato nell\'intervallo richiesto.');
    }

    /**
     * Sole posizioni, per la pagina «Oggi» e per i transiti.
     *
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function posizioni(array $d): array
    {
        $jd = isset($d['jd_ut'])
            ? (float) $d['jd_ut']
            : $this->swe->giornoGiuliano((int) $d['anno'], (int) $d['mese'], (int) $d['giorno'], (float) $d['ora_ut']);

        $elenco = Corpi::elenco();
        $corpi  = [];

        foreach ($d['corpi'] ?? Corpi::dieci() as $chiave) {
            if (!isset($elenco[$chiave])) {
                continue;
            }
            try {
                $p = $this->swe->posizione($jd, $elenco[$chiave]['ipl']);
            } catch (\Throwable) {
                continue;
            }
            $corpi[$chiave] = [
                'nome'       => $elenco[$chiave]['nome'],
                'lon'        => $p['lon'],
                'vel_lon'    => $p['vel_lon'],
                'retrogrado' => $p['vel_lon'] < 0.0,
            ];
        }

        return ['ok' => true, 'jd_ut' => $jd, 'corpi' => $corpi];
    }
}
