<?php

declare(strict_types=1);

namespace App\Corpus;

use App\Astro\Corpi;

/**
 * La lettura mondiale: una carta di evento o di fondazione.
 *
 * Il corpus del portale e' scritto per una persona — «il centro della
 * coscienza», «la vita emotiva» — e su una Repubblica o su un terremoto quelle
 * frasi non vogliono dire niente. L'astrologia mondiale legge gli stessi
 * simboli su un soggetto collettivo: il Sole e' chi governa, la Luna il popolo,
 * la casa X il vertice dello Stato. Le corrispondenze sono quelle della
 * tradizione raccolta da Baigent, Campion e Harvey («Mundane Astrology», 1984),
 * che a sua volta risale a Tolomeo e ad Abu Ma'shar.
 *
 * Nella carta di un evento conta soprattutto cio' che sta sugli angoli: un
 * pianeta che sorge o culmina nell'istante e' quello che «firma» l'accaduto.
 * La lettura mette quindi in testa i pianeti angolari, e li segnala.
 */
final class Mondana
{
    /** Gli angoli contano entro questo orbe, in gradi di longitudine. */
    public const ORBE_ANGOLO = 8.0;

    /** Aspetti fra pianeti: solo i piu' stretti, che in una carta mondiale fanno la trama. */
    private const ORBE_ASPETTO = 3.0;

    /** @var array<string,array{0:string,1:string}> significato pieno e forma breve */
    private const PIANETI = [
        'sole'     => ['il capo dello Stato o del governo, l\'autorità centrale, l\'identità collettiva', 'il governo'],
        'luna'     => ['il popolo, l\'opinione pubblica, la vita quotidiana della gente comune', 'il popolo'],
        'mercurio' => ['le comunicazioni, la stampa, i commerci, i trasporti, i negoziati', 'i commerci'],
        'venere'   => ['la diplomazia, le alleanze, le arti, il benessere e la pace', 'la diplomazia'],
        'marte'    => ['le forze armate, il conflitto, l\'industria, lo slancio che si impone', 'la forza armata'],
        'giove'    => ['la legge, la religione, la prosperità, l\'espansione oltre i confini', 'la legge'],
        'saturno'  => ['le istituzioni, la burocrazia, i limiti, le crisi, la tenuta nel tempo', 'le istituzioni'],
        'urano'    => ['le rivoluzioni, le riforme improvvise, la tecnica, le rotture col passato', 'la riforma'],
        'nettuno'  => ['gli ideali collettivi, le utopie, la propaganda, gli inganni, il mare', 'gli ideali'],
        'plutone'  => ['il potere nascosto, le trasformazioni radicali, le crisi estreme e la rinascita', 'il potere profondo'],
    ];

    /** @var array<int,string> */
    private const CASE = [
        1  => 'il paese e il suo popolo nel loro insieme, l\'immagine che danno di sé',
        2  => 'la ricchezza nazionale, la finanza, la moneta, le risorse',
        3  => 'i trasporti, le comunicazioni, la stampa, la scuola, i paesi vicini',
        4  => 'la terra e il territorio, l\'agricoltura, le radici, l\'opposizione a chi governa',
        5  => 'lo spettacolo, lo sport, i giovani, la natalità, i piaceri',
        6  => 'il lavoro, la sanità pubblica, l\'amministrazione e le forze dell\'ordine',
        7  => 'le relazioni internazionali, i trattati, gli alleati e i nemici dichiarati',
        8  => 'il debito, le tasse, le finanze intrecciate con altri, la morte e le crisi',
        9  => 'la legge, la religione, le università, il commercio e i viaggi lontani',
        10 => 'il governo, il capo dello Stato, il prestigio e la reputazione nel mondo',
        11 => 'il parlamento, le assemblee, gli amici e le alleanze di lungo corso',
        12 => 'le istituzioni chiuse, ospedali e carceri, i nemici nascosti, le congiure',
    ];

    /** @var list<string> il tono di un segno su un soggetto collettivo */
    private const SEGNI = [
        'con impeto, iniziativa e una certa precipitazione',
        'con lentezza, concretezza e attaccamento alla stabilità',
        'in modo mobile, dialettico, su più fronti insieme',
        'in modo protettivo, legato alle radici e alla difesa di ciò che è proprio',
        'con orgoglio, teatralità e tendenza ad accentrare',
        'con metodo, spirito amministrativo e attenzione al dettaglio',
        'cercando equilibrio, accordi e il consenso delle parti',
        'con intensità, segretezza e capacità di trasformarsi',
        'in modo espansivo, idealista, proiettato lontano',
        'in modo strutturato, gerarchico e paziente',
        'con spirito riformatore, collettivo e anticonvenzionale',
        'in modo fluido, idealista, a volte confuso',
    ];

    /** @var array<string,string> */
    private const ASPETTI = [
        'congiunzione' => 'si fondono in un\'unica spinta',
        'opposizione'  => 'si fronteggiano: una tensione che chiede un compromesso',
        'quadrato'     => 'sono in attrito: una sfida che produce crisi e decisioni',
        'trigono'      => 'si sostengono con naturalezza',
        'sestile'      => 'trovano occasioni di collaborazione',
    ];

    /**
     * Per ogni tipo di carta: chi si mostra all'Ascendente, chi sta al Medio
     * Cielo, e come si presenta la lettura.
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    private const VOCI = [
        'nazione' => [
            'Il paese',
            'Chi governa e ciò a cui il paese aspira si esprimono',
            'Una carta di fondazione è il «tema natale» di uno Stato o di un\'istituzione: l\'istante in cui '
            . 'nasce sulla carta, per legge o per proclamazione. Si legge con le chiavi dell\'astrologia '
            . 'mondiale: il Sole è chi governa, la Luna il popolo, la casa X il vertice dello Stato, la VII '
            . 'le relazioni con gli altri paesi.',
        ],
        'evento' => [
            'L\'evento',
            'Ciò che l\'evento consacra o abbatte, la sua eredità pubblica, si esprime',
            'Una carta di evento non descrive una persona ma un istante che ha cambiato le cose. '
            . 'Si legge con le chiavi dell\'astrologia mondiale: il Sole è chi comanda, la Luna la gente, '
            . 'le case sono i campi della vita collettiva. Contano soprattutto i pianeti sugli angoli, '
            . 'che qui vengono per primi.',
        ],
        'ingresso' => [
            'Il paese, nella stagione che si apre,',
            'Chi governa, nella stagione che si apre, agisce',
            'La carta dell\'ingresso del Sole in un segno cardinale, eretta per una capitale, è la carta '
            . 'della stagione per quel paese; quella dell\'ingresso in Ariete, per tradizione, vale per '
            . 'l\'anno intero. Conta soprattutto ciò che sta sugli angoli della capitale: un pianeta che '
            . 'sorge o culmina nell\'istante dà il tono al periodo.',
        ],
        'novilunio' => [
            'Il paese, nel mese che si apre,',
            'Chi governa, nel mese che si apre, agisce',
            'Il novilunio apre il mese lunare: Sole e Luna si uniscono, e la carta eretta per una capitale '
            . 'dà il tono alle quattro settimane che seguono. È la più piccola delle carte mondiali, e si '
            . 'legge soprattutto per i pianeti angolari.',
        ],
        'plenilunio' => [
            'Il paese, al culmine del mese,',
            'Chi governa, al culmine del mese, agisce',
            'Il plenilunio è il culmine del mese lunare: Sole e Luna si fronteggiano, e ciò che il novilunio '
            . 'aveva seminato viene alla luce. La carta si legge per i pianeti angolari e per l\'asse fra '
            . 'le case in cui cadono i due luminari.',
        ],
        'eclissi' => [
            'Il paese, sotto l\'eclissi,',
            'Chi governa, sotto l\'eclissi, agisce',
            'Un\'eclissi è una lunazione con più forza: la tradizione le dà effetti che durano mesi, o anni '
            . 'per le totali, sui paesi da cui è visibile e sulle carte in cui il suo grado cade su un '
            . 'pianeta o su un angolo.',
        ],
        'congiunzione' => [
            'Il paese, nel ciclo che si apre,',
            'Chi governa, nel ciclo che si apre, agisce',
            'La congiunzione di due pianeti lenti apre un ciclo di anni o di decenni. La carta dell\'istante '
            . 'esatto, eretta per una capitale, è per la tradizione il seme del ciclo in quel paese: si '
            . 'guarda in quale casa cade la congiunzione e chi sta sugli angoli.',
        ],
    ];

    /** @var array<string,string> */
    private const ANGOLI = [
        'asc' => 'sorge all\'Ascendente',
        'mc'  => 'culmina al Medio Cielo',
        'dsc' => 'tramonta al Discendente',
        'ic'  => 'sta al Fondo Cielo',
    ];

    /**
     * @param array<string,mixed> $tema
     * @param string $tipo 'evento' | 'nazione'
     * @return array{introduzione:string,sezioni:list<array{titolo:string,voci:list<array{titolo:string,perche:string,corpo:string,corpi:list<string>}>}>}
     */
    public static function monta(array $tema, string $tipo): array
    {
        $segni  = Corpi::segni();
        $ignota = ($tema['carta']['ora_ignota'] ?? false) === true;
        [$soggetto, $vertice, $introduzione] = self::VOCI[$tipo] ?? self::VOCI['nazione'];

        $angoli = [];
        if (!$ignota && isset($tema['punti']['asc']['lon'], $tema['punti']['mc']['lon'])) {
            $asc = (float) $tema['punti']['asc']['lon'];
            $mc  = (float) $tema['punti']['mc']['lon'];
            $angoli = ['asc' => $asc, 'mc' => $mc, 'dsc' => fmod($asc + 180, 360), 'ic' => fmod($mc + 180, 360)];
        }

        // --- il quadro: gli angoli ------------------------------------------
        $quadro = [];
        if ($angoli !== []) {
            $sa = (int) $tema['punti']['asc']['segno'];
            $sm = (int) $tema['punti']['mc']['segno'];
            $quadro[] = [
                'titolo' => 'Ascendente in ' . $segni[$sa]['nome'], 'perche' => 'come si presenta', 'corpi' => ['asc'],
                'corpo'  => $soggetto . ' si mostra al mondo ' . self::SEGNI[$sa] . '. In una carta mondiale '
                    . 'l\'Ascendente è il volto collettivo: la gente, il territorio, la prima impressione.',
            ];
            $quadro[] = [
                'titolo' => 'Medio Cielo in ' . $segni[$sm]['nome'], 'perche' => 'dove punta', 'corpi' => ['mc'],
                'corpo'  => $vertice . ' '
                    . self::SEGNI[$sm] . '. Il Medio Cielo è il vertice: '
                    . 'l\'autorità, la reputazione, la direzione dichiarata.',
            ];
        }

        // --- i pianeti, gli angolari per primi -------------------------------
        $pianeti = [];
        foreach (self::PIANETI as $k => [$pieno, $breve]) {
            $c = $tema['corpi'][$k] ?? null;
            if (!is_array($c)) {
                continue;
            }
            $angolo = null;
            $distanza = 99.0;
            foreach ($angoli as $a => $lon) {
                $d = abs(fmod((float) $c['lon'] - $lon + 540, 360) - 180);
                if ($d <= self::ORBE_ANGOLO && $d < $distanza) {
                    [$angolo, $distanza] = [$a, $d];
                }
            }
            $segno = (int) $c['segno'];
            $casa  = (int) ($c['casa'] ?? 0);

            $corpo = ucfirst($pieno) . ': ' . self::SEGNI[$segno] . '.';
            if (!$ignota && isset(self::CASE[$casa])) {
                $corpo .= ' Agisce nel campo della casa ' . self::romano($casa) . ': ' . self::CASE[$casa] . '.';
            }
            if ($angolo !== null) {
                $corpo .= ' Nell\'istante ' . self::ANGOLI[$angolo] . ' (a ' . number_format($distanza, 1, ',', '')
                    . '°): è uno dei pianeti che danno il tono all\'intera carta.';
            }
            if (($c['retrogrado'] ?? false) === true) {
                $corpo .= ' È retrogrado: la sua spinta torna su sé stessa, ripensa, rimanda.';
            }

            $pianeti[] = [
                'ordine' => $angolo !== null ? $distanza : 100 + count($pianeti),
                'titolo' => $c['nome'] . ' in ' . $segni[$segno]['nome'] . (!$ignota && $casa > 0 ? ', casa ' . self::romano($casa) : ''),
                'perche' => $angolo !== null ? 'angolare' : $breve,
                'corpo'  => $corpo,
                'corpi'  => [$k],
            ];
        }
        usort($pianeti, static fn (array $a, array $b): int => $a['ordine'] <=> $b['ordine']);
        $pianeti = array_map(static function (array $p): array { unset($p['ordine']); return $p; }, $pianeti);

        // --- la trama: gli aspetti stretti ----------------------------------
        $trama = [];
        foreach ($tema['aspetti']['elenco'] ?? [] as $a) {
            if (!isset(self::PIANETI[$a['a']], self::PIANETI[$a['b']], self::ASPETTI[$a['aspetto']])
                || (float) $a['orbe'] > self::ORBE_ASPETTO) {
                continue;
            }
            $trama[] = [
                'orbe'   => (float) $a['orbe'],
                'titolo' => $a['nome_a'] . ' ' . mb_strtolower((string) $a['aspetto_nome']) . ' ' . $a['nome_b'],
                'perche' => 'orbe ' . number_format((float) $a['orbe'], 1, ',', '') . '°',
                'corpo'  => ucfirst(self::PIANETI[$a['a']][1]) . ' e ' . self::PIANETI[$a['b']][1] . ' '
                    . self::ASPETTI[$a['aspetto']] . '.',
                'corpi'  => [(string) $a['a'], (string) $a['b']],
            ];
        }
        usort($trama, static fn (array $x, array $y): int => $x['orbe'] <=> $y['orbe']);
        $trama = array_map(static function (array $t): array { unset($t['orbe']); return $t; }, array_slice($trama, 0, 8));

        $sezioni = [];
        if ($quadro !== []) {
            $sezioni[] = ['titolo' => 'Il quadro', 'voci' => $quadro];
        }
        $sezioni[] = ['titolo' => 'I pianeti', 'voci' => $pianeti];
        if ($trama !== []) {
            $sezioni[] = ['titolo' => 'La trama', 'voci' => $trama];
        }

        if ($ignota) {
            $introduzione .= ' L\'ora non è nota: angoli e case non si leggono, restano i pianeti nei segni.';
        }

        return ['introduzione' => $introduzione, 'sezioni' => $sezioni];
    }

    private static function romano(int $n): string
    {
        return ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$n] ?? (string) $n;
    }
}
