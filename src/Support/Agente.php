<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lettura elementare dello user agent.
 *
 * Deliberatamente minima: serve a separare gli umani dai robot nelle
 * statistiche e a sapere se si sta guardando da telefono. Non e' una libreria
 * di fingerprinting e non deve diventarlo.
 */
final class Agente
{
    private const ROBOT = [
        'bot', 'crawler', 'spider', 'slurp', 'curl', 'wget', 'python-requests',
        'scrapy', 'headless', 'facebookexternalhit', 'preview', 'monitor',
        'uptime', 'validator', 'lighthouse', 'pingdom', 'go-http-client',
        'libwww', 'httpclient', 'java/', 'okhttp', 'axios', 'postman',
    ];

    /** @return array{famiglia:string,so:string,dispositivo:string,bot:bool} */
    public static function leggi(string $ua): array
    {
        $b = strtolower($ua);

        if ($ua === '') {
            return ['famiglia' => 'ignoto', 'so' => 'ignoto', 'dispositivo' => 'ignoto', 'bot' => true];
        }

        foreach (self::ROBOT as $spia) {
            if (str_contains($b, $spia)) {
                return ['famiglia' => 'robot', 'so' => 'ignoto', 'dispositivo' => 'bot', 'bot' => true];
            }
        }

        // L'ordine conta: Edge e Opera si dichiarano anche Chrome, Chrome anche Safari.
        $famiglia = match (true) {
            str_contains($b, 'edg/')                        => 'Edge',
            str_contains($b, 'opr/') || str_contains($b, 'opera') => 'Opera',
            str_contains($b, 'samsungbrowser')              => 'Samsung',
            str_contains($b, 'firefox') || str_contains($b, 'fxios') => 'Firefox',
            str_contains($b, 'chrome') || str_contains($b, 'crios')  => 'Chrome',
            str_contains($b, 'safari')                      => 'Safari',
            default                                         => 'altro',
        };

        $so = match (true) {
            str_contains($b, 'android')                     => 'Android',
            str_contains($b, 'iphone') || str_contains($b, 'ipad') || str_contains($b, 'ios') => 'iOS',
            str_contains($b, 'windows')                     => 'Windows',
            str_contains($b, 'mac os') || str_contains($b, 'macintosh') => 'macOS',
            str_contains($b, 'cros')                        => 'ChromeOS',
            str_contains($b, 'linux') || str_contains($b, 'x11') => 'Linux',
            default                                         => 'altro',
        };

        $dispositivo = match (true) {
            str_contains($b, 'ipad') || (str_contains($b, 'android') && !str_contains($b, 'mobile')) => 'tablet',
            str_contains($b, 'mobile') || str_contains($b, 'iphone') => 'mobile',
            default => 'desktop',
        };

        return ['famiglia' => $famiglia, 'so' => $so, 'dispositivo' => $dispositivo, 'bot' => false];
    }
}
