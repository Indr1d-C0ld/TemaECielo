<?php

declare(strict_types=1);

namespace App\Grafica;

use App\Astro\Corpi;

/**
 * La ruota del tema natale, generata in SVG dal server.
 *
 * Esce dal server e non dal browser: funziona senza JavaScript, si stampa
 * nitida a qualsiasi misura, si scarica come file unico, entra in un PDF.
 * Lo strato JavaScript, dove c'e', aggiunge evidenziazioni — mai contenuto.
 *
 * Gli anelli, dall'esterno verso il centro:
 *   cornice · zodiaco (dodici settori per elemento) · tacche di grado ·
 *   corona dei pianeti · anello delle case · tela degli aspetti.
 */
final class RuotaTema
{
    private const MISURA = 820.0;
    private const CX = 410.0;
    private const CY = 410.0;

    /**
     * I raggi di ogni anello, in due assetti.
     *
     * Con la ruota doppia — sinastria, transiti, rivoluzioni — serve posto per
     * una seconda corona di pianeti FUORI dallo zodiaco, e tutto il resto si
     * stringe. Tenere due tabelle di raggi invece di una sola con dei termini
     * correttivi rende evidente, guardando il codice, come e' fatto ciascuno
     * dei due disegni.
     *
     * @var array<string,array<string,float>>
     */
    private const RAGGI = [
        'singola' => [
            'cornice' => 402.0, 'cornice2' => 394.0,
            'zod_est' => 382.0, 'zod_int'  => 332.0, 'segno' => 357.0,
            'guida'   => 318.0, 'pianeta'  => 296.0, 'grado' => 264.0,
            'casa_est' => 232.0, 'casa_int' => 212.0, 'casa_num' => 222.0,
            'tela'    => 210.0,
            'est_guida' => 0.0, 'est_pianeta' => 0.0, 'est_grado' => 0.0,
        ],
        'doppia' => [
            'cornice' => 402.0, 'cornice2' => 394.0,
            // La corona esterna sta fra la cornice e lo zodiaco.
            'est_pianeta' => 368.0, 'est_grado' => 340.0, 'est_guida' => 322.0,
            'zod_est' => 314.0, 'zod_int'  => 272.0, 'segno' => 293.0,
            'guida'   => 262.0, 'pianeta'  => 242.0, 'grado' => 214.0,
            'casa_est' => 190.0, 'casa_int' => 172.0, 'casa_num' => 181.0,
            'tela'    => 170.0,
        ],
    ];

    /** @var array<string,float> */
    private array $r;

    /**
     * Distanza angolare minima fra due glifi.
     *
     * A 296 di raggio, un glifo da 28 unita' con un margine di respiro occupa
     * circa sei gradi e mezzo di arco. Sette e mezzo lascia il margine. Con la
     * ruota doppia la corona interna si stringe a 242, e l'ingombro angolare
     * cresce in proporzione.
     */
    private const INGOMBRO = 7.5;

    private const ROMANI = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /**
     * @param array<string,mixed> $tema     la carta radice, al centro
     * @param array<string,mixed>|null $esterno  la carta sovrapposta: l'altra
     *        persona in sinastria, il cielo del giorno nei transiti
     * @param list<array<string,mixed>> $incrociati aspetti fra le due carte
     */
    public function __construct(
        private array $tema,
        private bool $autonomo = false,
        private ?string $spritePath = null,
        private ?array $esterno = null,
        private array $incrociati = [],
        private string $etichettaEsterna = '',
    ) {
        $this->r = self::RAGGI[$this->esterno === null ? 'singola' : 'doppia'];
    }

    public function doppia(): bool
    {
        return $this->esterno !== null;
    }

    public function disegna(): string
    {
        $t = $this->tema;
        $asc = (float) $t['punti']['asc']['lon'];
        $ignota = ($t['carta']['ora_ignota'] ?? false) === true;

        // Tutto ruota in modo che l'Ascendente cada a sinistra, come sulle
        // carte a stampa: da li' le case si succedono in senso antiorario.
        $ruota = static fn (float $lon): float => Corpi::norma($lon - $asc);

        $pezzi = [];
        $pezzi[] = $this->intestazione();
        $pezzi[] = $this->fondo();
        $pezzi[] = $this->zodiaco($ruota);
        $pezzi[] = $this->tacche($ruota);
        $pezzi[] = $this->case($ruota, $ignota);
        $pezzi[] = $this->tela($ruota);
        $pezzi[] = $this->telaIncrociata($ruota);
        $pezzi[] = $this->pianeti($ruota);
        $pezzi[] = $this->pianetiEsterni($ruota);
        $pezzi[] = $this->assi($ruota, $ignota);
        $pezzi[] = '</svg>';

        return implode("\n", array_filter($pezzi));
    }

    // -- impalcatura ---------------------------------------------------------

    private function intestazione(): string
    {
        $m = self::MISURA;
        $classe = $this->autonomo ? 'ruota ruota-autonoma' : 'ruota';

        $s = $this->autonomo
            ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $m . ' ' . $m . '" '
              . 'width="' . $m . '" height="' . $m . '" class="' . $classe . '" '
              . 'role="img" aria-label="Ruota del tema natale">'
            : '<svg viewBox="0 0 ' . $m . ' ' . $m . '" class="' . $classe . '" '
              . 'role="img" aria-label="Ruota del tema natale">';

        // In un file autonomo i glifi devono viaggiare dentro il file: fuori
        // dalla pagina non c'e' nessuno sprite a cui riferirsi.
        if ($this->autonomo) {
            $s .= "\n" . $this->stileIncorporato() . "\n" . $this->glifiIncorporati();
        }

        return $s;
    }

    /** Lo stile va dentro il file autonomo, altrimenti si scarica una ruota incolore. */
    private function stileIncorporato(): string
    {
        return <<<'CSS'
            <style>
              .ruota-autonoma { --notte:#0b1026; --pannello:#121a3a; --oro:#c9a227; --oro-chiaro:#e8d18a;
                --testo:#e6e9f5; --attenuato:#9aa3c4; --filo:rgba(201,162,39,.32);
                --el-fuoco:#c05a2e; --el-terra:#6b7f4a; --el-aria:#c9a227; --el-acqua:#41739c;
                --armonico:#3f8f7a; --tensione:#b4432f; }
              .ruota-autonoma text { font-family: Georgia, "Times New Roman", serif; }
            </style>
            CSS;
    }

    /**
     * Solo i simboli che servono davvero a questa carta: incorporare tutti e
     * trentacinque i glifi quando se ne usano diciotto e' peso inutile in un
     * file che qualcuno scarichera'.
     */
    private function glifiIncorporati(): string
    {
        $percorso = $this->spritePath ?? ((string) ($GLOBALS['__project_root'] ?? '') . '/assets/img/glifi.svg');
        $sprite = @file_get_contents($percorso);
        if ($sprite === false) {
            return '';
        }

        $servono = ['gl-retrogrado'];
        foreach ($this->tema['corpi'] as $chiave => $c) {
            $servono[] = 'gl-' . (Corpi::elenco()[$chiave]['glifo'] ?? 'stella');
        }
        foreach (($this->esterno['corpi'] ?? []) as $chiave => $c) {
            $servono[] = 'gl-' . (Corpi::elenco()[$chiave]['glifo'] ?? 'stella');
        }
        foreach (Corpi::segni() as $s) {
            $servono[] = 'gl-' . $s['glifo'];
        }
        $servono = array_unique($servono);

        $fuori = [];
        if (preg_match_all('#<symbol\b[^>]*\bid="([^"]+)"[^>]*>.*?</symbol>#s', $sprite, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $trovato) {
                if (in_array($trovato[1], $servono, true)) {
                    $fuori[] = $trovato[0];
                }
            }
        }

        return $fuori === [] ? '' : "<defs>\n" . implode("\n", $fuori) . "\n</defs>";
    }

    private function fondo(): string
    {
        $cx = self::CX; $cy = self::CY;
        $rc = $this->r['cornice']; $rc2 = $this->r['cornice2'];

        return <<<SVG
            <defs>
              <radialGradient id="fondo-ruota">
                <stop offset="0%" stop-color="var(--pannello, #121a3a)"/>
                <stop offset="100%" stop-color="var(--notte, #0b1026)"/>
              </radialGradient>
            </defs>
            <circle cx="{$cx}" cy="{$cy}" r="{$rc}" fill="url(#fondo-ruota)"/>
            <circle cx="{$cx}" cy="{$cy}" r="{$rc}" fill="none" stroke="var(--oro, #c9a227)" stroke-width="1.6" opacity=".8"/>
            <circle cx="{$cx}" cy="{$cy}" r="{$rc2}" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".9"/>
            SVG;
    }

    // -- zodiaco -------------------------------------------------------------

    /** @param callable(float):float $ruota */
    private function zodiaco(callable $ruota): string
    {
        $fuori = ['<g class="anello-zodiaco">'];
        $segni = Corpi::segni();

        foreach ($segni as $i => $segno) {
            $da = $ruota($i * 30.0);
            $a  = $ruota($i * 30.0 + 30.0);

            $settore = Svg::settore(self::CX, self::CY, $this->r['zod_int'], $this->r['zod_est'], $da, $a);
            $colore  = 'var(--el-' . $segno['elemento'] . ')';

            $fuori[] = '<path d="' . $settore . '" fill="' . $colore . '" opacity=".14"'
                . ' stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".8"/>';

            [$x, $y] = Svg::punto(self::CX, self::CY, $this->r['segno'], $ruota($i * 30.0 + 15.0));
            $fuori[] = sprintf(
                '<use href="#gl-%s" x="%.1f" y="%.1f" width="30" height="30" color="%s" opacity=".95"/>',
                $segno['glifo'], $x - 15, $y - 15, $colore,
            );
        }

        $fuori[] = '<circle cx="' . self::CX . '" cy="' . self::CY . '" r="' . $this->r['zod_int']
            . '" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width="1"/>';
        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    /** @param callable(float):float $ruota */
    private function tacche(callable $ruota): string
    {
        $fuori = ['<g class="tacche" stroke="var(--oro, #c9a227)" fill="none">'];

        // Una tacca ogni grado: qui SI, a differenza dell'ornamento della home.
        // Su una carta vera servono a leggere la posizione esatta, e sono la
        // ragione per cui una carta a stampa sembra uno strumento.
        for ($g = 0; $g < 360; $g++) {
            $angolo = $ruota((float) $g);

            if ($g % 30 === 0) {
                [$r1, $w, $o] = [$this->r['zod_est'], 1.1, '.55'];
            } elseif ($g % 10 === 0) {
                [$r1, $w, $o] = [$this->r['zod_int'] - 16.0, .9, '.42'];
            } elseif ($g % 5 === 0) {
                [$r1, $w, $o] = [$this->r['zod_int'] - 11.0, .7, '.3'];
            } else {
                [$r1, $w, $o] = [$this->r['zod_int'] - 6.0, .5, '.16'];
            }

            $fuori[] = '<path d="' . Svg::raggio(self::CX, self::CY, $r1, $this->r['zod_int'], $angolo)
                . '" stroke-width="' . $w . '" opacity="' . $o . '"/>';
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    // -- case ----------------------------------------------------------------

    /** @param callable(float):float $ruota */
    private function case(callable $ruota, bool $ignota): string
    {
        $cuspidi = $this->tema['case']['cuspidi'];
        $opacita = $ignota ? '.35' : '1';

        $fuori = ['<g class="anello-case" opacity="' . $opacita . '">'];
        $fuori[] = '<circle cx="' . self::CX . '" cy="' . self::CY . '" r="' . $this->r['casa_est']
            . '" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".9"/>';
        $fuori[] = '<circle cx="' . self::CX . '" cy="' . self::CY . '" r="' . $this->r['casa_int']
            . '" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".9"/>';

        foreach ($cuspidi as $i => $cuspide) {
            $angolo = $ruota((float) $cuspide);
            // Le cuspidi degli angoli sono marcate, le altre tratteggiate:
            // e' la distinzione che si vede a colpo d'occhio su una carta a stampa.
            $angolare = in_array($i, [0, 3, 6, 9], true);

            $fuori[] = '<path d="' . Svg::raggio(self::CX, self::CY, $this->r['tela'], $this->r['casa_est'], $angolo)
                . '" stroke="var(--oro, #c9a227)" stroke-width="' . ($angolare ? '1.6' : '.9')
                . '" opacity="' . ($angolare ? '.85' : '.4') . '"'
                . ($angolare ? '' : ' stroke-dasharray="5 4"') . '/>';

            $succ = (float) $cuspidi[($i + 1) % 12];
            $meta = $ruota((float) $cuspide + Corpi::norma($succ - (float) $cuspide) / 2.0);
            [$x, $y] = Svg::punto(self::CX, self::CY, $this->r['casa_num'], $meta);

            $fuori[] = Svg::testo($x, $y + 5, self::ROMANI[$i], [
                'font-size' => 15, 'fill' => 'var(--attenuato, #9aa3c4)', 'letter-spacing' => '.08em',
            ]);
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    // -- aspetti -------------------------------------------------------------

    /** @param callable(float):float $ruota */
    private function tela(callable $ruota): string
    {
        $fuori = ['<g class="tela-aspetti">'];
        $fuori[] = '<circle cx="' . self::CX . '" cy="' . self::CY . '" r="' . $this->r['tela']
            . '" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".9"/>';

        $corpi = $this->tema['corpi'];

        foreach ($this->tema['aspetti']['elenco'] as $a) {
            // Gli aspetti agli assi non si disegnano: partirebbero dal bordo e
            // attraverserebbero la tela senza dire niente di leggibile.
            if (!isset($corpi[$a['a']], $corpi[$a['b']])) {
                continue;
            }

            [$x1, $y1] = Svg::punto(self::CX, self::CY, $this->r['tela'], $ruota((float) $corpi[$a['a']]['lon']));
            [$x2, $y2] = Svg::punto(self::CX, self::CY, $this->r['tela'], $ruota((float) $corpi[$a['b']]['lon']));

            $forza  = (float) $a['forza'];
            $colore = match ($a['natura']) {
                'armonico' => 'var(--armonico, #3f8f7a)',
                'tensione' => 'var(--tensione, #b4432f)',
                default    => 'var(--oro, #c9a227)',
            };

            // Spessore e opacita' seguono la forza: un aspetto esatto si vede,
            // uno al limite dell'orbe si intuisce appena. E' cosi' che la tela
            // racconta qualcosa invece di essere un groviglio uniforme.
            $fuori[] = sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="%.2f" '
                . 'opacity="%.2f" class="aspetto" data-corpi="%s %s"%s/>',
                $x1, $y1, $x2, $y2, $colore,
                0.6 + $forza * 2.0,
                0.22 + $forza * 0.55,
                htmlspecialchars((string) $a['a'], ENT_QUOTES),
                htmlspecialchars((string) $a['b'], ENT_QUOTES),
                $a['grado'] === 'minore' ? ' stroke-dasharray="4 4"' : '',
            );
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    // -- pianeti -------------------------------------------------------------

    /** @param callable(float):float $ruota */
    private function pianeti(callable $ruota): string
    {
        $corpi  = $this->tema['corpi'];
        $chiavi = array_keys($corpi);

        $veri = [];
        foreach ($chiavi as $k) {
            $veri[] = $ruota((float) $corpi[$k]['lon']);
        }

        $sciolti = Svg::sciogli($veri, self::INGOMBRO);

        $fuori = ['<g class="corona-pianeti">'];

        foreach ($chiavi as $i => $chiave) {
            $c = $corpi[$chiave];
            $vero    = $veri[$i];
            $mostra  = $sciolti[$i];
            $elemento = Corpi::segni()[(int) $c['segno']]['elemento'];
            $colore  = 'var(--el-' . $elemento . ')';

            // La linea guida dal glifo spostato alla sua tacca di grado esatta:
            // il simbolo puo' essersi spostato per far posto ai vicini, ma la
            // verita' resta visibile.
            [$gx, $gy] = Svg::punto(self::CX, self::CY, $this->r['guida'], $mostra);
            [$tx, $ty] = Svg::punto(self::CX, self::CY, $this->r['zod_int'], $vero);
            $fuori[] = sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="var(--oro, #c9a227)" '
                . 'stroke-width=".8" opacity=".45"/>',
                $gx, $gy, $tx, $ty,
            );

            [$px, $py] = Svg::punto(self::CX, self::CY, $this->r['pianeta'], $mostra);
            $fuori[] = sprintf(
                '<g class="pianeta" data-corpo="%s"><use href="#gl-%s" x="%.1f" y="%.1f" '
                . 'width="28" height="28" color="%s"/>',
                htmlspecialchars((string) $chiave, ENT_QUOTES),
                Corpi::elenco()[$chiave]['glifo'] ?? 'stella',
                $px - 14, $py - 14, $colore,
            );

            $p = Corpi::scomponi((float) $c['lon']);
            [$dx, $dy] = Svg::punto(self::CX, self::CY, $this->r['grado'], $mostra);
            $fuori[] = Svg::testo($dx, $dy + 4, sprintf('%d°%02d\'', $p['grado'], $p['primo']), [
                'font-size' => 11.5, 'fill' => 'var(--attenuato, #9aa3c4)',
            ]);

            if ($c['retrogrado']) {
                [$rx, $ry] = Svg::punto(self::CX, self::CY, $this->r['grado'] - 17.0, $mostra);
                $fuori[] = sprintf(
                    '<use href="#gl-retrogrado" x="%.1f" y="%.1f" width="13" height="13" '
                    . 'color="var(--tensione, #b4432f)"/>',
                    $rx - 6.5, $ry - 6.5,
                );
            }

            $fuori[] = '</g>';
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    // -- la carta sovrapposta ------------------------------------------------

    /**
     * I pianeti della seconda carta, nella corona esterna.
     *
     * Sono disegnati fuori dallo zodiaco e con un tratto piu' leggero: il
     * cielo sovrapposto — l'altra persona, o il giorno dei transiti — non e'
     * la carta, ci passa sopra. Confonderli visivamente sarebbe l'errore che
     * rende illeggibile una doppia ruota.
     *
     * @param callable(float):float $ruota
     */
    private function pianetiEsterni(callable $ruota): string
    {
        if ($this->esterno === null) {
            return '';
        }

        $corpi = [];
        foreach (Corpi::dieci() as $k) {
            if (isset($this->esterno['corpi'][$k])) {
                $corpi[$k] = $this->esterno['corpi'][$k];
            }
        }
        if ($corpi === []) {
            return '';
        }

        $chiavi = array_keys($corpi);
        $veri = [];
        foreach ($chiavi as $k) {
            $veri[] = $ruota((float) $corpi[$k]['lon']);
        }

        // L'ingombro angolare a raggio maggiore e' minore: lo stesso glifo
        // occupa meno gradi di arco.
        $ingombro = self::INGOMBRO * $this->r['pianeta'] / $this->r['est_pianeta'];
        $sciolti  = Svg::sciogli($veri, $ingombro);

        $fuori = ['<g class="corona-esterna">'];
        $fuori[] = '<circle cx="' . self::CX . '" cy="' . self::CY . '" r="' . $this->r['est_guida']
            . '" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".9" stroke-dasharray="3 5"/>';

        foreach ($chiavi as $i => $chiave) {
            $c = $corpi[$chiave];
            $vero   = $veri[$i];
            $mostra = $sciolti[$i];

            [$gx, $gy] = Svg::punto(self::CX, self::CY, $this->r['est_guida'], $mostra);
            [$tx, $ty] = Svg::punto(self::CX, self::CY, $this->r['zod_est'], $vero);
            $fuori[] = sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="var(--oro, #c9a227)" '
                . 'stroke-width=".7" opacity=".35"/>',
                $gx, $gy, $tx, $ty,
            );

            [$px, $py] = Svg::punto(self::CX, self::CY, $this->r['est_pianeta'], $mostra);
            $fuori[] = sprintf(
                '<g class="pianeta pianeta-esterno" data-corpo="est:%s" opacity=".85">'
                . '<use href="#gl-%s" x="%.1f" y="%.1f" width="24" height="24" '
                . 'color="var(--oro-chiaro, #e8d18a)"/>',
                htmlspecialchars((string) $chiave, ENT_QUOTES),
                Corpi::elenco()[$chiave]['glifo'] ?? 'stella',
                $px - 12, $py - 12,
            );

            $p = Corpi::scomponi((float) $c['lon']);
            [$dx, $dy] = Svg::punto(self::CX, self::CY, $this->r['est_grado'], $mostra);
            $fuori[] = Svg::testo($dx, $dy + 4, sprintf('%d°%02d\'', $p['grado'], $p['primo']), [
                'font-size' => 10.5, 'fill' => 'var(--attenuato, #9aa3c4)',
            ]);

            if (($c['retrogrado'] ?? false) === true) {
                [$rx, $ry] = Svg::punto(self::CX, self::CY, $this->r['est_grado'] - 15.0, $mostra);
                $fuori[] = sprintf(
                    '<use href="#gl-retrogrado" x="%.1f" y="%.1f" width="11" height="11" '
                    . 'color="var(--tensione, #b4432f)"/>',
                    $rx - 5.5, $ry - 5.5,
                );
            }

            $fuori[] = '</g>';
        }

        if ($this->etichettaEsterna !== '') {
            $fuori[] = Svg::testo(self::CX, self::CY - $this->r['cornice'] + 20.0, $this->etichettaEsterna, [
                'font-size' => 11, 'fill' => 'var(--oro-chiaro, #e8d18a)', 'letter-spacing' => '.2em',
            ]);
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    /**
     * Gli aspetti fra le due carte.
     *
     * Partono dalla corona esterna e arrivano a quella interna: si vede a
     * colpo d'occhio che cosa dell'uno tocca che cosa dell'altro. Gli aspetti
     * interni a ciascuna carta restano al centro e non si confondono con
     * questi.
     *
     * @param callable(float):float $ruota
     */
    private function telaIncrociata(callable $ruota): string
    {
        if ($this->esterno === null || $this->incrociati === []) {
            return '';
        }

        $fuori = ['<g class="tela-incrociata">'];

        foreach ($this->incrociati as $a) {
            $interno = $this->tema['corpi'][$a['a']] ?? $this->tema['punti'][$a['a']] ?? null;
            $esterno = $this->esterno['corpi'][$a['b']] ?? $this->esterno['punti'][$a['b']] ?? null;
            if ($interno === null || $esterno === null) {
                continue;
            }

            [$x1, $y1] = Svg::punto(self::CX, self::CY, $this->r['tela'], $ruota((float) $interno['lon']));
            [$x2, $y2] = Svg::punto(self::CX, self::CY, $this->r['tela'], $ruota((float) $esterno['lon']));

            $forza  = (float) $a['forza'];
            $colore = match ($a['natura']) {
                'armonico' => 'var(--armonico, #3f8f7a)',
                'tensione' => 'var(--tensione, #b4432f)',
                default    => 'var(--oro, #c9a227)',
            };

            $fuori[] = sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="%.2f" '
                . 'opacity="%.2f" class="aspetto aspetto-incrociato" data-corpi="%s est:%s"/>',
                $x1, $y1, $x2, $y2, $colore,
                0.7 + $forza * 2.2,
                0.3 + $forza * 0.6,
                htmlspecialchars((string) $a['a'], ENT_QUOTES),
                htmlspecialchars((string) $a['b'], ENT_QUOTES),
            );
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    // -- assi ----------------------------------------------------------------

    /** @param callable(float):float $ruota */
    private function assi(callable $ruota, bool $ignota): string
    {
        $punti = $this->tema['punti'];
        $opacita = $ignota ? '.3' : '1';

        $fuori = ['<g class="assi" opacity="' . $opacita . '">'];

        foreach ([['asc', 'AC'], ['dsc', 'DC'], ['mc', 'MC'], ['ic', 'IC']] as [$chiave, $sigla]) {
            if (!isset($punti[$chiave])) {
                continue;
            }
            $angolo = $ruota((float) $punti[$chiave]['lon']);
            $principale = in_array($chiave, ['asc', 'mc'], true);

            $fuori[] = '<path d="' . Svg::raggio(self::CX, self::CY, $this->r['casa_est'], $this->r['cornice'], $angolo)
                . '" stroke="var(--oro-chiaro, #e8d18a)" stroke-width="' . ($principale ? '1.8' : '1.2')
                . '" opacity="' . ($principale ? '.8' : '.5') . '"/>';

            [$x, $y] = Svg::punto(self::CX, self::CY, $this->r['cornice'] - 14.0, $angolo);
            $fuori[] = Svg::testo($x, $y + 4, $sigla, [
                'font-size' => 13, 'fill' => 'var(--oro-chiaro, #e8d18a)',
                'letter-spacing' => '.12em', 'font-weight' => '400',
            ]);
        }

        if ($ignota) {
            $fuori[] = Svg::testo(self::CX, self::CY + 6, 'ora ignota', [
                'font-size' => 14, 'fill' => 'var(--attenuato, #9aa3c4)',
                'letter-spacing' => '.3em', 'opacity' => '.7',
            ]);
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }
}
