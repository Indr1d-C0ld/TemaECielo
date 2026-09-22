<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Content-Security-Policy con nonce.
 *
 * La CSP di questo vhost vieta lo stile inline. Qui si parte direttamente senza
 * attributi style="..." e senza onclick=: quel poco di stile calcolato che serve
 * (larghezze delle barre, dimensioni dei grafici) passa da un blocco <style> che
 * porta il nonce, e il JavaScript sta tutto in file esterni.
 */
final class Csp
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return self::$nonce ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    /** @return array<string,string> */
    public static function intestazioni(): array
    {
        $n = self::nonce();

        $direttive = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$n}'",
            "style-src 'self' 'nonce-{$n}'",
            // I tile Esri sono immagini remote: e' l'unica origine esterna ammessa,
            // e serve solo al riquadro mappa del modulo di nascita.
            "img-src 'self' data: blob: https://server.arcgisonline.com https://services.arcgisonline.com",
            "connect-src 'self'",
            "font-src 'self'",
            "manifest-src 'self'",
        ];

        return [
            'Content-Security-Policy' => implode('; ', $direttive),
            'X-Content-Type-Options'  => 'nosniff',
            'Referrer-Policy'         => 'same-origin',
            'X-Frame-Options'         => 'DENY',
        ];
    }
}
