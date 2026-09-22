<?php

declare(strict_types=1);

namespace App\Grafica;

use App\Astro\Corpi;

/**
 * La griglia triangolare degli aspetti: la scaletta delle carte a stampa.
 *
 * I corpi stanno sulla diagonale; ogni cella incrocia la riga con la colonna e
 * mostra l'aspetto fra i due, se c'e'. E' una tavola densa — in una sola
 * occhiata si vede quali pianeti sono in relazione e quali sono isolati — e
 * nessuna tabella per righe la sostituisce.
 *
 * Esce in HTML e non in SVG: e' una tabella, e una tabella deve restare
 * leggibile da una riga di comando, selezionabile e navigabile con la tastiera.
 */
final class GrigliaAspetti
{
    /** @param array<string,mixed> $tema */
    public function __construct(private array $tema)
    {
    }

    public function disegna(): string
    {
        $corpi = $this->ordine();
        $n = count($corpi);
        if ($n < 2) {
            return '';
        }

        $mappa = $this->mappa();
        $elenco = Corpi::elenco();
        $segni  = Corpi::segni();

        $h = ['<table class="scaletta" aria-label="Griglia degli aspetti">'];
        $h[] = '<tbody>';

        for ($i = 0; $i < $n; $i++) {
            $riga = $corpi[$i];
            $h[] = '<tr>';

            // Le celle degli incroci con i corpi che precedono.
            for ($j = 0; $j < $i; $j++) {
                $colonna = $corpi[$j];
                $a = $mappa[$riga][$colonna] ?? null;

                if ($a === null) {
                    $h[] = '<td class="scaletta-vuota"></td>';
                    continue;
                }

                $titolo = sprintf(
                    '%s %s %s — orbe %s°, %s',
                    $this->nome($riga), mb_strtolower((string) $a['aspetto_nome']), $this->nome($colonna),
                    number_format((float) $a['orbe'], 2, ',', ''),
                    $a['applicativo'] === true ? 'applicativo'
                        : ($a['applicativo'] === false ? 'separativo' : 'moto nullo'),
                );

                $h[] = sprintf(
                    '<td class="scaletta-cella asp-%s" data-corpi="%s %s" title="%s">'
                    . '<svg class="glifo" aria-hidden="true"><use href="#gl-%s"></use></svg>'
                    . '<span class="scaletta-orbe">%s</span></td>',
                    htmlspecialchars((string) $a['natura'], ENT_QUOTES),
                    htmlspecialchars($riga, ENT_QUOTES),
                    htmlspecialchars($colonna, ENT_QUOTES),
                    htmlspecialchars($titolo, ENT_QUOTES),
                    htmlspecialchars((string) $a['glifo'], ENT_QUOTES),
                    htmlspecialchars(number_format((float) $a['orbe'], 1, ',', ''), ENT_QUOTES),
                );
            }

            // La cella sulla diagonale: il corpo stesso.
            $glifo = $riga === 'asc' || $riga === 'mc'
                ? 'stella'
                : ($elenco[$riga]['glifo'] ?? 'stella');

            $elemento = isset($this->tema['corpi'][$riga])
                ? $segni[(int) $this->tema['corpi'][$riga]['segno']]['elemento']
                : 'aria';

            $h[] = sprintf(
                '<th class="scaletta-corpo el-%s" scope="row" data-corpo="%s" title="%s">'
                . '<svg class="glifo" aria-hidden="true"><use href="#gl-%s"></use></svg>'
                . '<span class="scaletta-nome">%s</span></th>',
                htmlspecialchars($elemento, ENT_QUOTES),
                htmlspecialchars($riga, ENT_QUOTES),
                htmlspecialchars($this->nome($riga), ENT_QUOTES),
                htmlspecialchars($glifo, ENT_QUOTES),
                htmlspecialchars($this->nome($riga), ENT_QUOTES),
            );

            $h[] = '</tr>';
        }

        $h[] = '</tbody></table>';

        return implode("\n", $h);
    }

    /**
     * Quali corpi entrano nella griglia, e in che ordine.
     *
     * I dieci nell'ordine tradizionale — dal Sole a Plutone, cioe' dal piu'
     * veloce al piu' lento — poi gli assi. I corpi minori restano fuori: con
     * diciassette righe la scaletta diventa una parete di celle vuote.
     *
     * @return list<string>
     */
    private function ordine(): array
    {
        $fuori = [];
        foreach (Corpi::dieci() as $c) {
            if (isset($this->tema['corpi'][$c])) {
                $fuori[] = $c;
            }
        }
        foreach (['asc', 'mc'] as $a) {
            if (isset($this->tema['punti'][$a])) {
                $fuori[] = $a;
            }
        }

        return $fuori;
    }

    /** @return array<string,array<string,array<string,mixed>>> */
    private function mappa(): array
    {
        $m = [];
        foreach ($this->tema['aspetti']['elenco'] as $a) {
            $m[$a['a']][$a['b']] = $a;
            $m[$a['b']][$a['a']] = $a;
        }

        return $m;
    }

    private function nome(string $chiave): string
    {
        return (string) ($this->tema['corpi'][$chiave]['nome']
            ?? $this->tema['punti'][$chiave]['nome']
            ?? ucfirst($chiave));
    }
}
