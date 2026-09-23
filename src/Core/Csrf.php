<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const CHIAVE = '__csrf';

    public static function gettone(): string
    {
        $g = Session::get(self::CHIAVE);
        if (!is_string($g) || $g === '') {
            $g = bin2hex(random_bytes(32));
            Session::set(self::CHIAVE, $g);
        }

        return $g;
    }

    public static function verifica(?string $inviato): bool
    {
        $atteso = Session::get(self::CHIAVE);

        return is_string($atteso)
            && is_string($inviato)
            && $inviato !== ''
            && hash_equals($atteso, $inviato);
    }

    /**
     * Un gettone nuovo, buttando il vecchio.
     *
     * Si chiama quando cambia chi c'e' dietro la sessione — all'accesso
     * dell'amministratore. Un gettone letto prima dell'accesso, da una pagina
     * pubblica, non deve valere per le azioni della regia.
     */
    public static function rinnova(): void
    {
        Session::togli(self::CHIAVE);
    }

    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::gettone(), ENT_QUOTES) . '">';
    }
}
