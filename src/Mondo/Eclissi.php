<?php

declare(strict_types=1);

namespace App\Mondo;

use App\Astro\Sweph;

/**
 * Le parole delle eclissi.
 */
final class Eclissi
{
    /** «totale», «anulare», «ibrida», «parziale», «di penombra», dal tipo che dà la libreria. */
    public static function genere(int $tipo, string $corpo): string
    {
        return match (true) {
            ($tipo & Sweph::ECL_IBRIDA) !== 0      => 'ibrida',
            ($tipo & Sweph::ECL_TOTALE) !== 0      => 'totale',
            ($tipo & Sweph::ECL_ANULARE) !== 0     => 'anulare',
            ($tipo & Sweph::ECL_PENOMBRALE) !== 0  => 'di penombra',
            default                                => 'parziale',
        };
    }

    /** Il titolo: «Eclissi totale di Sole». */
    public static function titolo(array $e): string
    {
        return 'Eclissi ' . self::genere((int) $e['tipo'], (string) $e['corpo']) . ' di '
            . ($e['corpo'] === 'sole' ? 'Sole' : 'Luna');
    }

    /**
     * La magnitudine di un'eclissi di Luna: d'ombra se l'ombra la tocca, di
     * penombra altrimenti.
     */
    public static function magnitudine(float $ombra, float $penombra): string
    {
        return $ombra > 0
            ? 'magnitudine ' . number_format($ombra, 3, ',', '')
            : 'magnitudine di penombra ' . number_format($penombra, 3, ',', '');
    }

    /**
     * Come si vede da un luogo, in una riga.
     *
     * @param array{magnitudine:float,oscuramento:float,penombrale?:float,altezza:float}|null $locale
     */
    public static function visibilita(?array $locale, string $corpo): string
    {
        if ($locale === null) {
            return 'non visibile';
        }
        if ($corpo === 'sole') {
            $parte = min(1.0, max(0.0, (float) $locale['oscuramento']));
            $quanto = $parte < 0.01 ? 'appena sfiorato il disco' : 'coperto il ' . number_format($parte * 100, 0, ',', '') . '% del disco';
        } else {
            $quanto = self::magnitudine((float) $locale['magnitudine'], (float) ($locale['penombrale'] ?? 0));
        }

        return ((float) $locale['altezza'] < 0 ? 'in parte, il massimo sotto l\'orizzonte' : 'visibile') . ' · ' . $quanto;
    }
}
