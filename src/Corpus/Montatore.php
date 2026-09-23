<?php

declare(strict_types=1);

namespace App\Corpus;

use App\Astro\Corpi;

/**
 * Il montaggio della relazione.
 *
 * Non e' una concatenazione. Se si stampassero tutte le voci che una carta
 * produce — centoventi fra posizioni, case e aspetti — il risultato sarebbe
 * illeggibile e, peggio, piatto: tutto avrebbe lo stesso peso.
 *
 * Qui invece si fa quello che fa un astrologo quando scrive: si decide che
 * cosa conta, si scarta cio' che ripete, si mette in ordine.
 *
 * Le tre regole, in ordine di importanza:
 *
 *   1. RILEVANZA.  Un Sole congiunto all'Ascendente conta piu' di un Nettuno
 *      in undicesima. Il punteggio parte dal peso della voce e viene corretto
 *      da cio' che la carta dice davvero: orbe stretto, casa angolare,
 *      dignita' estrema, signoria dell'Ascendente.
 *
 *   2. GENERAZIONE.  Urano, Nettuno e Plutone restano nello stesso segno per
 *      anni: «Plutone in Bilancia» lo hanno tutti i nati fra il 1971 e il
 *      1984, e in una lettura personale non dice niente. Quelle voci vengono
 *      schiacciate in fondo. Gli stessi pianeti in CASA o in ASPETTO, invece,
 *      sono personali eccome.
 *
 *   3. NON RIPETERSI.  Due voci che dicono la stessa cosa con parole diverse
 *      stancano. Si tengono a freno sia le ripetizioni di argomento sia il
 *      numero di voci per ciascun corpo.
 */
final class Montatore
{
    /** Quante voci al massimo per ciascun corpo, in tutta la relazione. */
    private const TETTO_PER_CORPO = 4;

    /** Quante voci al massimo per sezione. */
    private const TETTO_SEZIONE = [
        'nucleo'    => 6,
        'facolta'   => 8,
        'rapporti'  => 9,
        'forma'     => 6,
    ];

    public function __construct(private Corpus $corpus)
    {
    }

    /**
     * @param array<string,mixed> $tema
     * @return array<string,mixed>
     */
    public function monta(array $tema): array
    {
        $sezioni = [
            'nucleo'   => ['titolo' => 'Il nucleo',            'voci' => $this->nucleo($tema)],
            'facolta'  => ['titolo' => 'Le facolt&agrave;',    'voci' => $this->facolta($tema)],
            'rapporti' => ['titolo' => 'Tensioni e accordi',   'voci' => $this->rapporti($tema)],
            'forma'    => ['titolo' => 'La forma della carta', 'voci' => $this->forma($tema)],
        ];

        $usatePerCorpo = [];
        $etichetteViste = [];
        $totaleScritte = 0;
        $totaleComposte = 0;

        foreach ($sezioni as $nome => &$sezione) {
            // Prima si ordina per rilevanza, poi si taglia: tagliare prima
            // butterebbe via proprio le voci che contano.
            usort($sezione['voci'], static fn (Voce $a, Voce $b): int => $b->rilevanza <=> $a->rilevanza);

            $tenute = [];
            foreach ($sezione['voci'] as $v) {
                if (count($tenute) >= self::TETTO_SEZIONE[$nome]) {
                    break;
                }
                if ($this->troppoPerQuestoCorpo($v, $usatePerCorpo)) {
                    continue;
                }
                if ($this->giaDetto($v, $etichetteViste)) {
                    continue;
                }
                // Un rimando («vale quanto detto per Saturno») resta solo se la voce
                // a cui rimanda e' rimasta: il tetto per pianeta puo' averla tolta.
                foreach ($v->etichette as $e) {
                    if (!str_starts_with($e, 'rimando:')) {
                        continue;
                    }
                    [, $chiaveRimando, $pianetaRimando] = explode(':', $e, 3);
                    $presente = false;
                    foreach ($tenute as $t) {
                        if ($t->ambito === 'dignita' && $t->chiave === $chiaveRimando
                            && ($t->soggetti[0] ?? '') === $pianetaRimando) {
                            $presente = true;
                        }
                    }
                    if (!$presente) {
                        continue 2;
                    }
                }

                foreach ($v->soggetti as $c) {
                    $usatePerCorpo[$c] = ($usatePerCorpo[$c] ?? 0) + 1;
                }
                foreach ($v->etichette as $e) {
                    $etichetteViste[$e] = ($etichetteViste[$e] ?? 0) + 1;
                }

                $this->corpus->segnaUso($v->ambito, $v->chiave);
                $v->fonte === 'scritto' ? $totaleScritte++ : $totaleComposte++;
                $tenute[] = $v;
            }

            $sezione['voci'] = $tenute;
        }
        unset($sezione);

        return [
            'registro'  => $this->corpus->registro(),
            'sezioni'   => array_filter($sezioni, static fn (array $s): bool => $s['voci'] !== []),
            'conteggio' => [
                'scritte'  => $totaleScritte,
                'composte' => $totaleComposte,
            ],
        ];
    }

    // ── il nucleo: Sole, Luna, Ascendente e il suo signore ──────────────────

    /**
     * @param array<string,mixed> $tema
     * @return list<Voce>
     */
    private function nucleo(array $tema): array
    {
        $voci = [];
        $ignota = ($tema['carta']['ora_ignota'] ?? false) === true;

        foreach (['sole', 'luna'] as $c) {
            if (!isset($tema['corpi'][$c])) {
                continue;
            }
            $corpo = $tema['corpi'][$c];

            $v = $this->corpus->pianetaInSegno($c, (int) $corpo['segno']);
            if ($v !== null) {
                $voci[] = $v->con($v->rilevanza * 3.0, $c === 'sole' ? 'il luminare del giorno' : 'il luminare della notte');
            }

            // Con l'ora ignota le case non esistono: parlarne sarebbe inventare.
            if (!$ignota) {
                $v = $this->corpus->pianetaInCasa($c, (int) $corpo['casa']);
                if ($v !== null) {
                    $voci[] = $v->con($v->rilevanza * 2.4);
                }
            }
        }

        if (!$ignota) {
            // Il signore dell'Ascendente governa l'intera carta: e' la voce che
            // un astrologo tradizionale guarda per prima, subito dopo i luminari.
            $signore = $this->signoreAscendente($tema);
            if ($signore !== null && isset($tema['corpi'][$signore])) {
                $c = $tema['corpi'][$signore];

                $v = $this->corpus->pianetaInCasa($signore, (int) $c['casa']);
                if ($v !== null) {
                    $voci[] = $v->con($v->rilevanza * 2.8, 'signore dell\'Ascendente');
                }

                $v = $this->corpus->pianetaInSegno($signore, (int) $c['segno']);
                if ($v !== null && !$this->generazionale($signore)) {
                    $voci[] = $v->con($v->rilevanza * 2.2, 'signore dell\'Ascendente');
                }
            }
        }

        return $voci;
    }

    // ── le facolta': gli altri pianeti ──────────────────────────────────────

    /**
     * @param array<string,mixed> $tema
     * @return list<Voce>
     */
    private function facolta(array $tema): array
    {
        /** @var array<string,string> condizione di dignita' → primo pianeta che l'ha avuta */
        $dignitaViste = [];
        $voci = [];
        $ignota = ($tema['carta']['ora_ignota'] ?? false) === true;
        $signore = $this->signoreAscendente($tema);

        foreach (Corpi::dieci() as $c) {
            if (in_array($c, ['sole', 'luna'], true) || !isset($tema['corpi'][$c])) {
                continue;
            }
            $corpo = $tema['corpi'][$c];

            // --- in segno ---
            $v = $this->corpus->pianetaInSegno($c, (int) $corpo['segno']);
            if ($v !== null) {
                $r = $v->rilevanza;
                $perche = '';

                // I tre lenti restano nello stesso segno per anni: in segno
                // sono un dato di generazione, non di persona.
                if ($this->generazionale($c)) {
                    $r *= 0.15;
                    $perche = 'posizione di generazione';
                }

                $dignita = $this->dignitaNotevole($corpo);
                if ($dignita !== null) {
                    $r *= 1.6;
                    $perche = $dignita;
                }

                $voci[] = $v->con($r, $perche);
            }

            // --- in casa ---
            if (!$ignota) {
                $v = $this->corpus->pianetaInCasa($c, (int) $corpo['casa']);
                if ($v !== null) {
                    $r = $v->rilevanza;
                    $perche = '';

                    if (in_array((int) $corpo['casa'], [1, 4, 7, 10], true)) {
                        $r *= 1.7;
                        $perche = 'in casa angolare';
                    }
                    if ($c === $signore) {
                        $r *= 1.4;
                    }

                    $voci[] = $v->con($r, $perche);
                }
            }

            // --- dignita' e condizione ---
            foreach ($this->condizioni($c, $corpo) as [$chiave, $moltiplicatore, $perche]) {
                $v = $this->corpus->scritta('dignita', $chiave, [$c], $c);
                if ($v === null) {
                    continue;
                }
                // La stessa condizione su un secondo pianeta non ripete il
                // paragrafo: e' lo stesso testo con un altro nome, e letto due
                // volte di fila — «Urano retrogrado», «Nettuno retrogrado» —
                // sembrava un errore di stampa. Il secondo rimanda al primo.
                if (isset($dignitaViste[$chiave])) {
                    $v = \App\Corpus\Voce::nuova(
                        $v->ambito, $v->chiave, $v->titolo,
                        sprintf(
                            'Vale per %s quanto detto per %s: stessa condizione, stessa lettura.',
                            Corpus::conArticolo($c),
                            Corpus::conArticolo($dignitaViste[$chiave]),
                        ),
                        $v->rilevanza * 0.5, $v->fonte, ['rimando:' . $chiave . ':' . $dignitaViste[$chiave]], $v->soggetti,
                    );
                } else {
                    $dignitaViste[$chiave] = $c;
                }
                $voci[] = $v->con($v->rilevanza * $moltiplicatore, $perche);
            }
        }

        return $voci;
    }

    // ── tensioni e accordi: gli aspetti ─────────────────────────────────────

    /**
     * @param array<string,mixed> $tema
     * @return list<Voce>
     */
    private function rapporti(array $tema): array
    {
        $voci = [];
        $ignota = ($tema['carta']['ora_ignota'] ?? false) === true;

        foreach ($tema['aspetti']['elenco'] as $a) {
            // Con l'ora ignota gli assi non esistono e i loro aspetti nemmeno.
            if ($ignota && (in_array($a['a'], ['asc', 'mc'], true) || in_array($a['b'], ['asc', 'mc'], true))) {
                continue;
            }

            $v = $this->corpus->aspetto((string) $a['a'], (string) $a['b'], (string) $a['aspetto']);
            if ($v === null) {
                continue;
            }

            // La forza dell'aspetto conta piu' di ogni altra cosa: un quadrato
            // esatto e' un fatto della vita, uno a sette gradi e' una sfumatura.
            $r = $v->rilevanza * (0.4 + 1.6 * (float) $a['forza']);
            $perche = '';

            if ((float) $a['orbe'] < 1.0) {
                $r *= 1.5;
                $perche = 'aspetto quasi esatto';
            }

            // Un aspetto che coinvolge un luminare o un asse parla della
            // persona, non di due funzioni fra loro.
            foreach ([$a['a'], $a['b']] as $c) {
                if (in_array($c, ['sole', 'luna', 'asc', 'mc'], true)) {
                    $r *= 1.6;
                    break;
                }
            }

            if ($a['applicativo'] === true) {
                $perche = $perche !== '' ? $perche . ', applicativo' : 'applicativo';
            }

            $voci[] = $v->con($r, $perche);
        }

        return $voci;
    }

    // ── la forma: figura, configurazioni, fase lunare ───────────────────────

    /**
     * @param array<string,mixed> $tema
     * @return list<Voce>
     */
    private function forma(array $tema): array
    {
        $voci = [];

        foreach ($tema['configurazioni'] ?? [] as $c) {
            $v = $this->corpus->scritta('configurazione', (string) $c['tipo'], $c['corpi'] ?? []);
            if ($v !== null) {
                $voci[] = $v->con(
                    $v->rilevanza * (1.0 + (float) $c['forza']),
                    implode(', ', $c['nomi'] ?? []),
                );
            }
        }

        $figura = $tema['bilanci']['figura']['tipo'] ?? null;
        if (is_string($figura)) {
            $v = $this->corpus->scritta('figura', $figura);
            if ($v !== null) {
                $voci[] = $v->con($v->rilevanza * 1.2);
            }
        }

        $fase = $tema['fenomeni']['luna']['fase_nome'] ?? null;
        if (is_string($fase)) {
            $v = $this->corpus->scritta('fase_luna', $fase, ['luna']);
            if ($v !== null) {
                $voci[] = $v->con($v->rilevanza * 1.1);
            }
        }

        return $voci;
    }

    // ── regole di servizio ──────────────────────────────────────────────────

    /** I tre lenti: in segno sono un dato di generazione. */
    private function generazionale(string $corpo): bool
    {
        return in_array($corpo, ['urano', 'nettuno', 'plutone'], true);
    }

    /** @param array<string,mixed> $tema */
    private function signoreAscendente(array $tema): ?string
    {
        $asc = $tema['punti']['asc']['lon'] ?? null;
        if ($asc === null) {
            return null;
        }

        return Corpi::segni()[Corpi::segnoDi((float) $asc)]['domicilio'];
    }

    /**
     * Se il corpo ha una dignita' essenziale forte, in bene o in male.
     *
     * Serve a dare piu' peso alla posizione in segno: «Saturno in Leone» conta
     * piu' di «Saturno in Sagittario» perche' nel Leone e' in esilio, e quella
     * e' un'informazione, non una collocazione qualunque.
     *
     * @param array<string,mixed> $corpo
     */
    private function dignitaNotevole(array $corpo): ?string
    {
        foreach ((array) ($corpo['dignita']['voci'] ?? []) as $d) {
            if (in_array($d['tipo'], ['domicilio', 'esaltazione', 'esilio', 'caduta'], true)) {
                return mb_strtolower((string) $d['nome'], 'UTF-8');
            }
        }

        return null;
    }

    /**
     * La condizione del corpo, quando e' degna di nota.
     *
     * @param array<string,mixed> $corpo
     * @return list<array{0:string,1:float,2:string}>
     */
    private function condizioni(string $chiave, array $corpo): array
    {
        $fuori = [];

        foreach ((array) ($corpo['dignita']['voci'] ?? []) as $d) {
            if (in_array($d['tipo'], ['domicilio', 'esaltazione', 'esilio', 'caduta', 'peregrino'], true)) {
                $fuori[] = [$d['tipo'], 1.4, mb_strtolower((string) $d['nome'], 'UTF-8')];
            }
        }

        $sole = $corpo['condizione']['relazione_sole'] ?? null;
        if ($sole === 'cazimi') {
            $fuori[] = ['cazimi', 2.0, 'nel cuore del Sole'];
        } elseif ($sole === 'combusto') {
            $fuori[] = ['combusto', 1.5, 'combusto'];
        }

        if (($corpo['retrogrado'] ?? false) === true && !in_array($chiave, ['sole', 'luna'], true)) {
            $fuori[] = ['retrogrado', 1.1, 'retrogrado'];
        }

        return $fuori;
    }

    /** @param array<string,int> $usate */
    private function troppoPerQuestoCorpo(Voce $v, array $usate): bool
    {
        foreach ($v->soggetti as $c) {
            if (($usate[$c] ?? 0) >= self::TETTO_PER_CORPO) {
                return true;
            }
        }

        return false;
    }

    /**
     * Se una voce ripete quello che e' gia' stato detto.
     *
     * Si guarda quante etichette condivide con cio' che e' gia' passato: due
     * etichette gia' viste piu' volte a testa vogliono dire che si sta
     * girando intorno allo stesso argomento.
     *
     * @param array<string,int> $viste
     */
    private function giaDetto(Voce $v, array $viste): bool
    {
        $ripetute = 0;

        foreach ($v->etichette as $e) {
            // Un frammento gia' usato vuol dire testo IDENTICO parola per
            // parola — «agisce in settima casa, di fronte a te: ...» dopo che
            // lo si e' gia' letto due righe sopra. Basta una sola occorrenza
            // per fermarlo.
            if (str_starts_with($e, 'frammento:') && ($viste[$e] ?? 0) >= 1) {
                return true;
            }
            if (($viste[$e] ?? 0) >= 2) {
                $ripetute++;
            }
        }

        return $ripetute >= 2;
    }
}
