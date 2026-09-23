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

        // La vista si rende in un ambito tutto suo, dove le sole variabili locali
        // hanno nomi che nessuna vista userebbe.
        //
        // Prima `extract` girava qui, accanto a `$nome`, `$dati` e `$file`, con
        // EXTR_SKIP: le chiavi con quei nomi venivano scartate in silenzio, e la
        // vista trovava al loro posto le variabili di questo metodo. Il modulo
        // dell'ora ambigua riceveva cosi' in `$dati` l'intero pacchetto della
        // pagina invece dei dati di nascita, scriveva i campi nascosti sbagliati,
        // e la scelta fra le due ore non arrivava mai a una carta. Per lo stesso
        // motivo i moduli non si ricompilavano dopo un errore.
        return (static function (string $__vista, array $__dati): string {
            extract($__dati, EXTR_SKIP);
            ob_start();
            try {
                require $__vista;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        })($file, $dati);
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
