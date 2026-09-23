<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Un cliente ha superato un freno. Il front controller la trasforma in un 429
 * con l'intestazione Retry-After; chi la intercetta prima puo' dirlo meglio.
 */
final class TroppeRichieste extends \RuntimeException
{
    public function __construct(public readonly int $attesa = 60)
    {
        parent::__construct('Troppe richieste: riprova fra un minuto.');
    }
}
