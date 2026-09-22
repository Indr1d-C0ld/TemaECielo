<?php

declare(strict_types=1);

namespace App\Luogo;

use Transliterator;

/**
 * Riduce un nome di luogo alla forma con cui lo si cerca.
 *
 * Minuscolo, senza accenti, senza punteggiatura, con gli spazi normalizzati.
 * Serve perche' nessuno digita «Città di Castello» con l'accento giusto e con
 * le maiuscole giuste, e perche' «Sant'Agata» e «Sant Agata» devono trovare la
 * stessa riga.
 */
final class Normalizza
{
    private static ?Transliterator $translit = null;

    public static function nome(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        // Con intl si traslittera per davvero: «Кёльн» diventa «keln», non
        // sparisce. Senza, si ripiega su iconv, che sui caratteri non latini
        // getta la spugna ma almeno non rompe niente.
        if (class_exists(Transliterator::class)) {
            self::$translit ??= Transliterator::create('Any-Latin; Latin-ASCII; Lower');
            if (self::$translit !== null) {
                $t = self::$translit->transliterate($s);
                if ($t !== false) {
                    $s = $t;
                }
            }
        } else {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
            $s = $t === false ? $s : strtolower($t);
        }

        $s = mb_strtolower($s, 'UTF-8');
        $s = (string) preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }
}
