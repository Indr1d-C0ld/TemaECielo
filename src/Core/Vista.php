<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Motore di viste a PHP puro. Nessun templating: la vista e' un file PHP che
 * riceve variabili gia' preparate dal controller e ha il solo compito di
 * stamparle, sempre passando da e().
 */
final class Vista
{
    private static string $percorso = '';

    public static function percorso(string $percorso): void
    {
        self::$percorso = rtrim($percorso, '/');
    }

    /** @param array<string,mixed> $dati */
    public static function rendi(string $nome, array $dati = []): string
    {
        $file = self::$percorso . '/' . str_replace('..', '', $nome) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vista non trovata: {$nome}");
        }

        extract($dati, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /**
     * Rende una vista dentro il layout generale.
     *
     * @param array<string,mixed> $dati
     */
    public static function pagina(string $nome, array $dati = []): string
    {
        $dati['contenuto'] = self::rendi($nome, $dati);

        return self::rendi('layout', $dati);
    }
}
