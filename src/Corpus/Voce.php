<?php

declare(strict_types=1);

namespace App\Corpus;

/**
 * Una voce della relazione: un paragrafo con la sua provenienza.
 *
 * La provenienza si mostra al lettore. Un testo scritto a mano per «Sole in
 * Ariete» e uno composto da due frammenti non valgono lo stesso, e far finta
 * di si' sarebbe disonesto verso chi legge.
 */
final class Voce
{
    /** @param list<string> $etichette @param list<string> $soggetti */
    public function __construct(
        public readonly string $ambito,
        public readonly string $chiave,
        public readonly string $titolo,
        public readonly string $corpo,
        public readonly float $rilevanza,
        public readonly string $fonte,        // 'scritto' | 'composto'
        public readonly array $etichette = [],
        public readonly array $soggetti = [],  // i corpi di cui parla
        public readonly string $perche = '',   // perche' e' in cima: si mostra in pagina
    ) {
    }

    public function con(float $rilevanza, string $perche = ''): self
    {
        return new self(
            $this->ambito, $this->chiave, $this->titolo, $this->corpo,
            $rilevanza, $this->fonte, $this->etichette, $this->soggetti,
            $perche !== '' ? $perche : $this->perche,
        );
    }
}
