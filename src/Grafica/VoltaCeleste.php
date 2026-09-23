<?php

declare(strict_types=1);

namespace App\Grafica;

use App\Astro\Corpi;
use App\Cielo\Costellazioni;
use App\Cielo\Volta;

/**
 * La volta celeste: il cielo vero di un istante, da un punto della Terra.
 *
 * Non e' lo zodiaco. E' quello che si vedeva alzando gli occhi: le stelle
 * dove stavano, le costellazioni, i pianeti, la Luna nella fase giusta e
 * inclinata come si inclinava davvero. Ed e' la meta' del portale che lo
 * distingue da un calcolatore di temi natali.
 *
 * Proiezione stereografica azimutale centrata sullo zenit: l'orizzonte e' il
 * cerchio esterno, il punto sopra la testa e' il centro. E' la proiezione dei
 * planisferi da secoli, e conserva le forme delle costellazioni — in una
 * proiezione equidistante l'Orsa Maggiore all'orizzonte si schiaccerebbe fino
 * a non riconoscersi piu'.
 */
final class VoltaCeleste
{
    private const MISURA = 820.0;
    private const CX = 410.0;
    private const CY = 410.0;
    private const R  = 372.0;   // orizzonte

    /** Nomi mostrati solo per le stelle davvero notevoli: oltre, e' confusione. */
    private const MAG_ETICHETTA = 2.1;
    private const ALT_ETICHETTA = 7.0;

    /** @param array<string,mixed> $tema */
    public function __construct(
        private array $tema,
        private bool $autonomo = false,
        private float $magLimite = 5.6,
    ) {
    }

    /**
     * Le aree gia' occupate da glifi e nomi, per non scriverci sopra.
     *
     * @var list<array{0:float,1:float,2:float,3:float}>
     */
    private array $occupati = [];

    /** Se un rettangolo e' libero: dentro la cupola e senza toccare nulla di gia' posato. */
    private function libero(array $b): bool
    {
        // Dentro il cerchio dell'orizzonte: fuori viene tagliato.
        foreach ([[$b[0], $b[1]], [$b[2], $b[1]], [$b[0], $b[3]], [$b[2], $b[3]]] as [$x, $y]) {
            if (hypot($x - self::CX, $y - self::CY) > self::R - 2.0) {
                return false;
            }
        }
        foreach ($this->occupati as $o) {
            if ($b[0] < $o[2] && $b[2] > $o[0] && $b[1] < $o[3] && $b[3] > $o[1]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Il rettangolo di un testo, stimato dal numero di caratteri: per il carattere
     * con grazie di questa pagina, poco piu' di mezzo corpo a lettera.
     *
     * @return array{0:float,1:float,2:float,3:float}
     */
    private static function scatola(float $x, float $y, string $testo, float $corpo, string $ancora): array
    {
        $w = mb_strlen($testo) * $corpo * 0.56;
        $x0 = match ($ancora) { 'start' => $x, 'end' => $x - $w, default => $x - $w / 2.0 };

        return [$x0 - 1.0, $y - $corpo * 0.82, $x0 + $w + 1.0, $y + $corpo * 0.28];
    }

    /**
     * Sceglie la prima posizione libera fra quelle proposte, e la occupa.
     *
     * @param list<array{0:float,1:float,2:string}> $candidati [x, y, ancora]
     * @return array{0:float,1:float,2:string}|null
     */
    private function posa(string $testo, float $corpo, array $candidati, bool $obbligato): ?array
    {
        foreach ($candidati as $c) {
            $b = self::scatola($c[0], $c[1], $testo, $corpo, $c[2]);
            if ($this->libero($b)) {
                $this->occupati[] = $b;
                return $c;
            }
        }
        if (!$obbligato) {
            return null;
        }
        // Un pianeta ha sempre il suo nome, anche se non c'e' un posto libero:
        // meglio un nome accavallato che un glifo anonimo. Ma un nome tagliato
        // dal bordo della cupola e' peggio di uno accavallato: si prende allora
        // la prima posizione che almeno sta dentro, sopra quello che c'e'.
        $salvati = $this->occupati;
        $this->occupati = [];
        $scelto = $candidati[0];
        foreach ($candidati as $c) {
            if ($this->libero(self::scatola($c[0], $c[1], $testo, $corpo, $c[2]))) {
                $scelto = $c;
                break;
            }
        }
        $this->occupati = $salvati;
        $this->occupati[] = self::scatola($scelto[0], $scelto[1], $testo, $corpo, $scelto[2]);

        return $scelto;
    }

    public function disegna(): string
    {
        $t = $this->tema;
        $this->occupati = [];

        $jd  = (float) $t['tempo']['jd_ut'];
        $lat = (float) $t['luogo']['lat'];
        // Il tempo siderale locale arriva in ORE dal motore: qui serve in gradi.
        $tsl = (float) $t['tempo']['siderale_locale'] * 15.0;

        $altSole = (float) ($t['corpi']['sole']['altezza'] ?? -90.0);
        $cielo = Volta::cielo($altSole);

        $stelle = Volta::stelle($jd, $tsl, $lat, $this->magLimite);
        $linee  = Volta::linee($stelle);

        $pezzi = [];
        $pezzi[] = $this->intestazione();
        $pezzi[] = $this->fondo($cielo);
        $pezzi[] = '<g clip-path="url(#cupola)">';
        $pezzi[] = $this->reticolo();
        $pezzi[] = $this->cerchiNotevoli($jd, $tsl, $lat, $t);
        $pezzi[] = $this->costellazioni($linee, $stelle, $cielo);
        $pezzi[] = $this->stelle($stelle, $cielo);
        $pezzi[] = $this->corpi($t);
        $pezzi[] = $this->luna($t);
        $pezzi[] = $this->etichette($stelle, $cielo);
        $pezzi[] = '</g>';
        $pezzi[] = $this->orizzonte($cielo, $altSole);
        $pezzi[] = '</svg>';

        return implode("\n", array_filter($pezzi));
    }

    /**
     * Proiezione stereografica: da altezza e azimut a un punto sul foglio.
     *
     * Nord in alto, EST A SINISTRA. Non e' un errore: una carta del cielo si
     * guarda tenendola sopra la testa, e rispetto a una mappa del terreno vista
     * dall'alto i punti cardinali sono specchiati. E' la convenzione dei
     * planisferi.
     *
     * @return array{0:float,1:float}
     */
    private function proietta(float $alt, float $az): array
    {
        $z = deg2rad((90.0 - $alt) / 2.0);
        $r = self::R * tan($z);
        $a = deg2rad($az);

        return [
            round(self::CX - $r * sin($a), 2),
            round(self::CY - $r * cos($a), 2),
        ];
    }

    private function intestazione(): string
    {
        $m = self::MISURA;
        $classe = $this->autonomo ? 'volta volta-autonoma' : 'volta';

        $s = $this->autonomo
            ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $m . ' ' . $m . '" '
              . 'width="' . $m . '" height="' . $m . '" class="' . $classe . '" '
              . 'role="img" aria-label="La volta celeste">'
            : '<svg viewBox="0 0 ' . $m . ' ' . $m . '" class="' . $classe . '" '
              . 'role="img" aria-label="La volta celeste">';

        if ($this->autonomo) {
            $s .= "\n" . $this->glifiIncorporati();
        }

        return $s;
    }

    private function glifiIncorporati(): string
    {
        $percorso = (string) ($GLOBALS['__project_root'] ?? '') . '/assets/img/glifi.svg';
        $sprite = @file_get_contents($percorso);
        if ($sprite === false) {
            return '';
        }

        $servono = [];
        foreach (Corpi::dieci() as $c) {
            $servono[] = 'gl-' . (Corpi::elenco()[$c]['glifo'] ?? 'stella');
        }

        $fuori = [];
        if (preg_match_all('#<symbol\b[^>]*\bid="([^"]+)"[^>]*>.*?</symbol>#s', $sprite, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as $s) {
                if (in_array($s[1], $servono, true)) {
                    $fuori[] = $s[0];
                }
            }
        }

        return $fuori === [] ? '' : "<defs>\n" . implode("\n", $fuori) . "\n</defs>";
    }

    /** @param array<string,mixed> $cielo */
    private function fondo(array $cielo): string
    {
        $cx = self::CX; $cy = self::CY; $r = self::R;
        $zenit = $cielo['zenit'];
        $oriz  = $cielo['orizzonte'];

        return <<<SVG
            <defs>
              <radialGradient id="fondo-cielo">
                <stop offset="0%" stop-color="{$zenit}"/>
                <stop offset="72%" stop-color="{$zenit}"/>
                <stop offset="100%" stop-color="{$oriz}"/>
              </radialGradient>
              <clipPath id="cupola"><circle cx="{$cx}" cy="{$cy}" r="{$r}"/></clipPath>
            </defs>
            <circle cx="{$cx}" cy="{$cy}" r="{$r}" fill="url(#fondo-cielo)"/>
            SVG;
    }

    /** Almucantarat e verticali: la rete di riferimento, appena accennata. */
    private function reticolo(): string
    {
        $fuori = ['<g class="reticolo" fill="none" stroke="rgba(255,255,255,.13)" stroke-width=".7" stroke-dasharray="2 5">'];

        foreach ([30.0, 60.0] as $alt) {
            $z = deg2rad((90.0 - $alt) / 2.0);
            $fuori[] = sprintf('<circle cx="%.1f" cy="%.1f" r="%.2f"/>', self::CX, self::CY, self::R * tan($z));
        }

        for ($az = 0; $az < 360; $az += 45) {
            [$x1, $y1] = $this->proietta(0.0, (float) $az);
            $fuori[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', self::CX, self::CY, $x1, $y1);
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    /**
     * Eclittica ed equatore celeste.
     *
     * L'eclittica e' la strada del Sole, e quindi anche quella dei pianeti: e'
     * il ponte visivo fra questa carta e la ruota dello zodiaco.
     *
     * @param array<string,mixed> $t
     */
    private function cerchiNotevoli(float $jd, float $tsl, float $lat, array $t): string
    {
        $obliquita = deg2rad((float) ($t['tempo']['obliquita_vera'] ?? 23.44));
        $fuori = [];

        // Eclittica: si percorre la longitudine eclittica e si converte.
        $punti = [];
        for ($l = 0; $l <= 360; $l += 2) {
            $lr = deg2rad((float) $l);
            $ar = rad2deg(atan2(sin($lr) * cos($obliquita), cos($lr)));
            $decl = rad2deg(asin(sin($obliquita) * sin($lr)));
            [$alt, $az] = Volta::altAzimut($ar, $decl, $tsl, $lat);
            $punti[] = [$alt, $az];
        }
        $fuori[] = $this->spezzata($punti, 'var(--oro, #c9a227)', 1.3, '.5', '7 5', 'eclittica');

        // Equatore celeste.
        $punti = [];
        for ($a = 0; $a <= 360; $a += 2) {
            [$alt, $az] = Volta::altAzimut((float) $a, 0.0, $tsl, $lat);
            $punti[] = [$alt, $az];
        }
        $fuori[] = $this->spezzata($punti, 'rgba(160,190,255,.5)', 1.0, '.55', '3 6', 'equatore');

        // Il meridiano locale: da nord allo zenit a sud, una retta verticale.
        $fuori[] = sprintf(
            '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="rgba(255,255,255,.18)" '
            . 'stroke-width=".8" stroke-dasharray="4 6" class="meridiano"/>',
            self::CX, self::CY - self::R, self::CX, self::CY + self::R,
        );

        return implode("\n", $fuori);
    }

    /**
     * Una spezzata di punti in coordinate orizzontali, spezzata dove esce.
     *
     * @param list<array{0:float,1:float}> $punti
     */
    private function spezzata(array $punti, string $colore, float $spessore, string $opacita, string $tratteggio, string $classe): string
    {
        $tratti = [];
        $corrente = [];

        foreach ($punti as [$alt, $az]) {
            if ($alt < -1.0) {
                // Sotto l'orizzonte: si chiude il tratto e se ne aprira' un
                // altro quando la curva risale. Senza questo, l'eclittica
                // attraverserebbe il disco con una corda che non esiste.
                if (count($corrente) > 1) {
                    $tratti[] = $corrente;
                }
                $corrente = [];
                continue;
            }
            $corrente[] = $this->proietta($alt, $az);
        }
        if (count($corrente) > 1) {
            $tratti[] = $corrente;
        }

        $d = [];
        foreach ($tratti as $tratto) {
            $c = [];
            foreach ($tratto as $i => [$x, $y]) {
                $c[] = ($i === 0 ? 'M ' : 'L ') . $x . ' ' . $y;
            }
            $d[] = implode(' ', $c);
        }

        return $d === [] ? '' : sprintf(
            '<path d="%s" fill="none" stroke="%s" stroke-width="%.1f" opacity="%s" stroke-dasharray="%s" class="%s"/>',
            implode(' ', $d), $colore, $spessore, $opacita, $tratteggio, $classe,
        );
    }

    /**
     * @param list<array{a:int,b:int,con:string}> $linee
     * @param array<int,array<string,mixed>> $stelle
     * @param array<string,mixed> $cielo
     */
    private function costellazioni(array $linee, array $stelle, array $cielo): string
    {
        if ($linee === []) {
            return '';
        }

        $opacita = max(0.10, (float) $cielo['stelleVisibili']) * 0.55;

        $d = [];
        foreach ($linee as $l) {
            $a = $stelle[$l['a']];
            $b = $stelle[$l['b']];
            if ($a['alt'] < 0.0 || $b['alt'] < 0.0) {
                continue;
            }
            [$x1, $y1] = $this->proietta((float) $a['alt'], (float) $a['az']);
            [$x2, $y2] = $this->proietta((float) $b['alt'], (float) $b['az']);
            $d[] = "M {$x1} {$y1} L {$x2} {$y2}";
        }

        return $d === [] ? '' : sprintf(
            '<path class="costellazioni" d="%s" fill="none" stroke="rgba(170,200,255,.85)" '
            . 'stroke-width=".9" opacity="%.3f"/>',
            implode(' ', $d), $opacita,
        );
    }

    /**
     * Le stelle, raggruppate per colore.
     *
     * Raggruppare fa risparmiare parecchio: l'attributo del colore si scrive
     * una volta per gruppo invece che su ognuna delle duemila stelle.
     *
     * @param array<int,array<string,mixed>> $stelle
     * @param array<string,mixed> $cielo
     */
    private function stelle(array $stelle, array $cielo): string
    {
        // Di giorno le stelle non si vedono, ed e' giusto dirlo. Non si
        // cancellano pero': si attenuano fino al limite del visibile e la
        // didascalia spiega perche'. Sapere DOVE stavano ha senso anche quando
        // nessuno poteva vederle.
        $visibilita = max(0.12, (float) $cielo['stelleVisibili']);

        // Si raggruppa per COLORE e per OPACITA': cosi' entrambi gli attributi
        // si scrivono una volta per gruppo invece che su ognuna delle
        // millecinquecento stelle. Sedici byte a stella sembrano niente e sono
        // venticinque kilobyte a pagina.
        $gruppi = [];
        foreach ($stelle as $s) {
            if ($s['alt'] < 0.0) {
                continue;
            }

            // Le piu' luminose sono anche le piu' opache: cosi' la gerarchia
            // si legge anche in bianco e nero.
            $o = $visibilita * min(1.0, 0.45 + (6.5 - (float) $s['mag']) / 8.0);
            $scaglione = (int) round($o * 12.0);   // dodici livelli bastano all'occhio

            $gruppi[Volta::colore($s['ci']) . '|' . $scaglione][] = $s;
        }

        $fuori = ['<g class="stelle">'];

        foreach ($gruppi as $chiave => $insieme) {
            [$colore, $scaglione] = explode('|', $chiave);
            $opacita = (int) $scaglione / 12.0;

            $cerchi = [];
            foreach ($insieme as $s) {
                [$x, $y] = $this->proietta((float) $s['alt'], (float) $s['az']);
                $cerchi[] = sprintf(
                    '<circle cx="%.1f" cy="%.1f" r="%.1f"/>',
                    $x, $y, Volta::raggio((float) $s['mag']),
                );
            }

            $fuori[] = sprintf('<g fill="%s" opacity="%.2f">%s</g>', $colore, $opacita, implode('', $cerchi));
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    /** @param array<string,mixed> $t */
    private function corpi(array $t): string
    {
        $fuori = ['<g class="pianeti-cielo">'];

        // Prima si segnano i posti dei glifi, tutti: stanno dove sta il corpo e
        // non si spostano. Solo dopo i nomi cercano un posto libero intorno.
        // Nettuno e Saturno a pochi gradi l'uno dall'altro scrivevano i nomi
        // uno sopra l'altro.
        $visibili = [];
        foreach (Corpi::dieci() as $chiave) {
            if ($chiave === 'luna' || !isset($t['corpi'][$chiave])) {
                continue;   // la Luna ha un trattamento suo
            }
            $c = $t['corpi'][$chiave];
            if ((float) $c['altezza'] < 0.0) {
                continue;   // sotto l'orizzonte: non si vede, non si disegna
            }
            [$x, $y] = $this->proietta((float) $c['altezza'], (float) $c['azimut']);
            $visibili[$chiave] = [$x, $y];
            $alto = $chiave === 'sole' ? 37.0 : 11.0;
            $this->occupati[] = [$x - 12.0, $y - $alto, $x + 12.0, $y + 12.0];
        }

        foreach ($visibili as $chiave => [$x, $y]) {
            $c = $t['corpi'][$chiave];
            $sole = $chiave === 'sole';
            $nome = (string) $c['nome'];
            [$nx, $ny, $ancora] = $this->posa($nome, 10.5, [
                [$x, $y + ($sole ? 26.0 : 24.0), 'middle'],
                [$x, $y - ($sole ? 42.0 : 16.0), 'middle'],
                [$x + 15.0, $y + 4.0, 'start'],
                [$x - 15.0, $y + 4.0, 'end'],
                [$x, $y + ($sole ? 38.0 : 36.0), 'middle'],
            ], true);

            if ($sole) {
                $fuori[] = sprintf('<circle cx="%.1f" cy="%.1f" r="26" fill="#ffe9a8" opacity=".22"/>', $x, $y);
                $fuori[] = sprintf('<circle cx="%.1f" cy="%.1f" r="11" fill="#fff3c4"/>', $x, $y);
            } else {
                $fuori[] = sprintf('<circle cx="%.1f" cy="%.1f" r="9" fill="var(--oro, #c9a227)" opacity=".18"/>', $x, $y);
            }

            $fuori[] = sprintf(
                '<g class="pianeta-cielo" data-corpo="%s"><use href="#gl-%s" x="%.1f" y="%.1f" '
                . 'width="22" height="22" color="%s"/>'
                . '<text x="%.1f" y="%.1f" text-anchor="%s" font-size="10.5" fill="rgba(255,255,255,.72)">%s</text></g>',
                htmlspecialchars($chiave, ENT_QUOTES),
                Corpi::elenco()[$chiave]['glifo'] ?? 'stella',
                $x - 11, $y - 11 - ($sole ? 26 : 0),
                $sole ? '#2b2200' : 'var(--oro-chiaro, #e8d18a)',
                $nx, $ny, $ancora,
                htmlspecialchars($nome, ENT_QUOTES),
            );
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    /**
     * La Luna, nella fase vera e inclinata come si inclinava davvero.
     *
     * La gobba punta verso il Sole: e' l'unica regola che serve, ed e'
     * geometricamente esatta. Disegnare sempre una falce rivolta a destra e'
     * l'errore che si vede su meta' delle illustrazioni astronomiche: alle
     * nostre latitudini la falce si corica, e all'equatore diventa una
     * barchetta.
     *
     * @param array<string,mixed> $t
     */
    private function luna(array $t): string
    {
        if (!isset($t['corpi']['luna'])) {
            return '';
        }
        $l = $t['corpi']['luna'];
        $alt = (float) $l['altezza'];
        if ($alt < 0.0) {
            return '';
        }

        [$x, $y] = $this->proietta($alt, (float) $l['azimut']);
        $r = 15.0;

        // Frazione illuminata: dal motore quando c'e', altrimenti
        // dall'elongazione, che e' il modo classico di ricavarla.
        $k = isset($t['fenomeni']['luna']['illuminazione'])
            ? (float) $t['fenomeni']['luna']['illuminazione']
            : (1.0 - cos(deg2rad(Corpi::distanza((float) $l['lon'], (float) ($t['corpi']['sole']['lon'] ?? 0.0))))) / 2.0;
        $k = max(0.0, min(1.0, $k));

        // Da che parte punta la gobba: verso il Sole, lungo il cerchio massimo.
        //
        // Non basta tirare una retta sul foglio fino al Sole: quella retta e'
        // una corda, non l'arco, e a grandi elongazioni sbaglia di parecchio —
        // in prova, con la Luna gobbosa a 134 gradi dal Sole, di centosessanta
        // gradi, cioe' la falce rovesciata. Si fa un passo di un grado sulla
        // sfera e si proietta quello.
        $rotazione = 0.0;
        if (isset($t['corpi']['sole'])) {
            [$altP, $azP] = Volta::versoIlPunto(
                $alt, (float) $l['azimut'],
                (float) $t['corpi']['sole']['altezza'], (float) $t['corpi']['sole']['azimut'],
            );
            [$px, $py] = $this->proietta($altP, $azP);
            $rotazione = rad2deg(atan2($py - $y, $px - $x));
        }

        // Il contorno della parte illuminata: mezzo cerchio piu' mezza ellisse.
        // Il semiasse dell'ellisse vale |2k-1| e il verso del suo arco cambia
        // fra falce e gobba — e' tutto qui il disegno di una fase lunare.
        $q  = 2.0 * $k - 1.0;
        $rx = abs($q) * $r;
        $spazzata = $q >= 0.0 ? 1 : 0;

        $percorso = sprintf(
            'M 0 %.2f A %.2f %.2f 0 0 1 0 %.2f A %.2f %.2f 0 0 %d 0 %.2f Z',
            -$r, $r, $r, $r, $rx, $r, $spazzata, -$r,
        );

        $nome = (string) ($t['fenomeni']['luna']['fase_nome'] ?? 'Luna');

        // Il disco occupa il suo posto; il nome cerca il proprio intorno, e le
        // coordinate si riportano poi al gruppo traslato sul centro della Luna.
        $this->occupati[] = [$x - $r * 1.8, $y - $r * 1.8, $x + $r * 1.8, $y + $r * 1.8];
        [$nx, $ny, $ancora] = $this->posa($nome, 10.5, [
            [$x, $y + $r + 16.0, 'middle'],
            [$x, $y - $r * 1.8 - 5.0, 'middle'],
            [$x + $r * 1.8 + 4.0, $y + 4.0, 'start'],
            [$x - $r * 1.8 - 4.0, $y + 4.0, 'end'],
        ], true);

        return sprintf(
            '<g class="luna-cielo" data-corpo="luna" transform="translate(%.1f,%.1f)">'
            . '<circle r="%.0f" fill="#e8d18a" opacity=".14"/>'
            . '<g transform="rotate(%.1f)">'
            . '<circle r="%.1f" fill="rgba(255,255,255,.07)" stroke="rgba(240,228,192,.35)" stroke-width=".8"/>'
            . '<path d="%s" fill="#f2e6c4"/></g>'
            . '<text x="%.1f" y="%.1f" text-anchor="%s" font-size="10.5" fill="rgba(255,255,255,.72)">%s</text></g>',
            $x, $y, $r * 1.8, $rotazione, $r, $percorso, $nx - $x, $ny - $y, $ancora,
            htmlspecialchars($nome, ENT_QUOTES),
        );
    }

    /**
     * @param array<int,array<string,mixed>> $stelle
     * @param array<string,mixed> $cielo
     */
    private function etichette(array $stelle, array $cielo): string
    {
        $opacita = max(0.25, (float) $cielo['stelleVisibili']);
        $fuori = ['<g class="nomi-stelle" font-size="10" fill="rgba(210,225,255,.8)" opacity="' . sprintf('%.2f', $opacita) . '">'];

        // Dalla piu' brillante: se due nomi si contendono un posto, lo tiene la
        // stella che si vede meglio. Un nome che non trova posto non si scrive —
        // la stella resta disegnata, e un nome sopra un altro non si legge
        // comunque.
        $nominate = array_values(array_filter(
            $stelle,
            static fn (array $s): bool => $s['nome'] !== null
                && $s['mag'] <= self::MAG_ETICHETTA && $s['alt'] >= self::ALT_ETICHETTA,
        ));
        usort($nominate, static fn (array $a, array $b): int => $a['mag'] <=> $b['mag']);

        foreach ($nominate as $s) {
            [$x, $y] = $this->proietta((float) $s['alt'], (float) $s['az']);
            $nome = (string) $s['nome'];
            $posto = $this->posa($nome, 10.0, [
                [$x + 7.0, $y + 3.5, 'start'],
                [$x - 7.0, $y + 3.5, 'end'],
                [$x, $y - 6.0, 'middle'],
                [$x, $y + 13.0, 'middle'],
            ], false);
            if ($posto === null) {
                continue;
            }
            $fuori[] = sprintf(
                '<text x="%.1f" y="%.1f"%s>%s</text>',
                $posto[0], $posto[1],
                $posto[2] === 'start' ? '' : ' text-anchor="' . $posto[2] . '"',
                htmlspecialchars($nome, ENT_QUOTES),
            );
        }

        $fuori[] = '</g>';

        return implode("\n", $fuori);
    }

    /** @param array<string,mixed> $cielo */
    private function orizzonte(array $cielo, float $altSole): string
    {
        $fuori = [];
        $fuori[] = sprintf(
            '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="var(--oro, #c9a227)" stroke-width="1.7" opacity=".8"/>',
            self::CX, self::CY, self::R,
        );
        $fuori[] = sprintf(
            '<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="var(--filo, rgba(201,162,39,.32))" stroke-width=".9"/>',
            self::CX, self::CY, self::R + 9.0,
        );

        // Est a sinistra, ovest a destra: e' la convenzione dei planisferi, e
        // scritta sul disegno non si puo' sbagliare.
        foreach ([['N', 0.0], ['E', 90.0], ['S', 180.0], ['O', 270.0]] as [$sigla, $az]) {
            // A -2,5 gradi e non a -5: a -5 la N toccava il bordo superiore e la
            // E quello sinistro, e il riquadro le tagliava.
            [$x, $y] = $this->proietta(-2.5, $az);
            $fuori[] = Svg::testo($x, $y + 5, $sigla, [
                'font-size' => 15, 'fill' => 'var(--oro-chiaro, #e8d18a)', 'letter-spacing' => '.1em',
            ]);
        }

        // Nell'angolo in alto a sinistra, che il cerchio lascia vuoto: sopra la N
        // le due scritte si sarebbero toccate.
        $fuori[] = Svg::testo(14.0, 20.0, mb_strtoupper('zenit al centro · ' . $cielo['nome'], 'UTF-8'), [
            'font-size' => 10, 'fill' => 'var(--attenuato, #9aa3c4)', 'letter-spacing' => '.18em',
            'text-anchor' => 'start',
        ]);

        return implode("\n", $fuori);
    }
}
