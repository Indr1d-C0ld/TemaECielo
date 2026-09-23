<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string,string> $intestazioni */
    private function __construct(
        private string $corpo,
        private int $stato = 200,
        private array $intestazioni = [],
    ) {
    }

    public static function html(string $corpo, int $stato = 200): self
    {
        return new self($corpo, $stato, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param array<string,mixed>|list<mixed> $dati */
    public static function json(array $dati, int $stato = 200): self
    {
        return new self(
            // Un testo non UTF-8 valido (?q=%ff) faceva fallire json_encode, e la
            // risposta partiva vuota con stato 200.
            (string) json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            $stato,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function svg(string $corpo, int $stato = 200, bool $immutabile = false): self
    {
        $h = ['Content-Type' => 'image/svg+xml; charset=utf-8'];
        if ($immutabile) {
            $h['Cache-Control'] = 'public, max-age=31536000, immutable';
        }

        return new self($corpo, $stato, $h);
    }

    public static function testo(string $corpo, int $stato = 200): self
    {
        return new self($corpo, $stato, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function redirect(string $verso, int $stato = 302): self
    {
        return new self('', $stato, ['Location' => $verso]);
    }

    public function conIntestazione(string $nome, string $valore): self
    {
        $this->intestazioni[$nome] = $valore;

        return $this;
    }

    /** Il valore di un'intestazione gia' impostata, senza badare alle maiuscole. */
    public function intestazione(string $nome): ?string
    {
        foreach ($this->intestazioni as $k => $v) {
            if (strcasecmp($k, $nome) === 0) {
                return (string) $v;
            }
        }

        return null;
    }

    public function stato(): int
    {
        return $this->stato;
    }

    public function lunghezza(): int
    {
        return strlen($this->corpo);
    }

    public function invia(): void
    {
        if (!headers_sent()) {
            http_response_code($this->stato);
            foreach ($this->intestazioni as $nome => $valore) {
                header("{$nome}: {$valore}");
            }
        }
        echo $this->corpo;
    }
}
