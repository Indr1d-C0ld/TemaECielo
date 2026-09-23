<?php

declare(strict_types=1);

namespace App\Grafica;

use App\Mondo\Cicli;
use App\Mondo\Mondo;

/**
 * Il grafico dell'indice ciclico di Barbault, dal 1800 al 2399.
 *
 * Sotto la curva, una tacca per ogni evento e fondazione dell'archivio: e'
 * il confronto che Barbault faceva a mano, fra i minimi dell'indice e i
 * momenti in cui la storia ha girato. Il grafico non dimostra niente — mette
 * le due cose una accanto all'altra, e chi guarda giudica.
 */
final class IndiceCiclico
{
    private const L = 960.0;
    private const H = 330.0;
    private const SX = 46.0;   // margine sinistro, per le etichette dei valori
    private const DX = 12.0;
    private const SU = 18.0;
    private const GIU = 64.0;  // spazio per gli anni e per le tacche degli eventi

    /**
     * @param list<array{0:float,1:float}> $indice
     * @param list<array{nome:string,slug:string,jd:float,tipo:string}> $eventi
     */
    public function __construct(private array $indice, private array $eventi = [], private ?float $oggi = null)
    {
    }

    public function disegna(): string
    {
        if (count($this->indice) < 2) {
            return '';
        }
        $valori = array_column($this->indice, 1);
        $vMin = floor(min($valori) / 100) * 100;
        $vMax = ceil(max($valori) / 100) * 100;
        $jd0 = Mondo::jdAnno(Mondo::ANNO_MIN);
        $jd1 = Mondo::jdAnno(Mondo::ANNO_MAX + 1);
        $larg = self::L - self::SX - self::DX;
        $alt = self::H - self::SU - self::GIU;
        $x = static fn (float $jd): float => self::SX + ($jd - $jd0) / ($jd1 - $jd0) * $larg;
        $y = static fn (float $v): float => self::SU + (1 - ($v - $vMin) / max(1.0, $vMax - $vMin)) * $alt;

        // Niente role="img": dentro ci sono collegamenti, e un'immagine li
        // nasconderebbe a chi naviga con un lettore di schermo.
        $h = [sprintf('<svg class="indice-ciclico" viewBox="0 0 %d %d" role="group" aria-labelledby="ic-titolo" '
            . 'xmlns="http://www.w3.org/2000/svg">', self::L, self::H)];
        $h[] = '<title id="ic-titolo">Indice ciclico di Barbault dal ' . Mondo::ANNO_MIN . ' al ' . Mondo::ANNO_MAX . '</title>';

        // La griglia: una riga ogni cento gradi, una colonna ogni cinquant'anni.
        for ($v = $vMin; $v <= $vMax; $v += 100) {
            $h[] = sprintf('<line class="ic-griglia" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', self::SX, $y($v), self::L - self::DX, $y($v));
            $h[] = Svg::testo(self::SX - 6, $y($v) + 3.5, (string) (int) $v, ['class' => 'ic-etichetta', 'text-anchor' => 'end']);
        }
        for ($anno = Mondo::ANNO_MIN; $anno <= Mondo::ANNO_MAX + 1; $anno += 50) {
            $xa = $x(Mondo::jdAnno($anno));
            $h[] = sprintf('<line class="ic-griglia%s" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>',
                $anno % 100 === 0 ? ' ic-secolo' : '', $xa, self::SU, $xa, self::SU + $alt);
            if ($anno % 100 === 0) {
                $h[] = Svg::testo($xa, self::SU + $alt + 15, (string) $anno, ['class' => 'ic-etichetta']);
            }
        }

        // La media, per dare un senso a «basso» e «alto».
        $media = Cicli::media($this->indice);
        $h[] = sprintf('<line class="ic-media" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', self::SX, $y($media), self::L - self::DX, $y($media));

        // La curva.
        $punti = [];
        foreach ($this->indice as [$t, $v]) {
            $punti[] = sprintf('%.1f,%.1f', $x($t), $y($v));
        }
        $h[] = '<polyline class="ic-curva" points="' . implode(' ', $punti) . '"/>';

        // I minimi, con l'anno.
        foreach (Cicli::minimi($this->indice, 12.0) as [$t, $v]) {
            $anno = (int) gmdate('Y', Mondo::unix($t));
            $h[] = sprintf('<circle class="ic-minimo" cx="%.1f" cy="%.1f" r="3"><title>Minimo: %d, indice %d</title></circle>',
                $x($t), $y($v), $anno, (int) round($v));
            $h[] = Svg::testo($x($t), $y($v) + 14, (string) $anno, ['class' => 'ic-anno']);
        }

        // Oggi.
        if ($this->oggi !== null && $this->oggi > $jd0 && $this->oggi < $jd1) {
            $xo = $x($this->oggi);
            $h[] = sprintf('<line class="ic-oggi" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>', $xo, self::SU, $xo, self::SU + $alt);
            $h[] = Svg::testo($xo + 4, self::SU + 10, 'oggi', ['class' => 'ic-anno', 'text-anchor' => 'start']);
        }

        // Gli eventi dell'archivio, come tacche sotto l'asse: ciascuna porta al suo nome.
        $base = self::SU + $alt + 24;
        foreach ($this->eventi as $e) {
            if ($e['jd'] < $jd0 || $e['jd'] > $jd1) {
                continue;
            }
            $xe = $x($e['jd']);
            $h[] = sprintf('<a href="%s" aria-label="%s"><line class="ic-evento ic-%s" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f">'
                . '<title>%s (%s)</title></line></a>',
                htmlspecialchars(url('/archivio/' . $e['slug']), ENT_QUOTES), htmlspecialchars($e['nome'], ENT_QUOTES),
                $e['tipo'] === 'nazione' ? 'nazione' : 'evento',
                $xe, $base, $xe, $base + 16,
                htmlspecialchars($e['nome'], ENT_QUOTES), gmdate('Y', Mondo::unix($e['jd'])));
        }
        $h[] = Svg::testo(self::SX, $base + 30, 'Tacche in basso: eventi e fondazioni dell\'archivio — toccale o passaci sopra per il nome',
            ['class' => 'ic-legenda', 'text-anchor' => 'start']);

        $h[] = '</svg>';

        return implode('', $h);
    }
}
