<?php

declare(strict_types=1);

namespace App\Core;

/**
 * La richiesta HTTP in arrivo, ridotta a cio' che serve.
 */
final class Request
{
    private string $metodo;
    private string $percorso;
    private string $basePath;

    public function __construct(?string $basePathForzato = null)
    {
        $this->metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $uri = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');

        $this->basePath = rtrim($basePathForzato ?? $this->deduciBasePath(), '/');

        $percorso = $uri;
        if ($this->basePath !== '' && str_starts_with($percorso, $this->basePath)) {
            $percorso = substr($percorso, strlen($this->basePath));
        }

        $this->percorso = '/' . trim($percorso, '/');
    }

    private function deduciBasePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');

        return rtrim(str_replace('\\', '/', dirname($script)), '/');
    }

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function percorso(): string
    {
        return $this->percorso;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function query(string $chiave, ?string $predefinito = null): ?string
    {
        $v = $_GET[$chiave] ?? null;

        return is_string($v) ? $v : $predefinito;
    }

    public function post(string $chiave, ?string $predefinito = null): ?string
    {
        $v = $_POST[$chiave] ?? null;

        return is_string($v) ? $v : $predefinito;
    }

    /** @return array<string,mixed> */
    public function tuttoPost(): array
    {
        return $_POST;
    }

    public function haPost(string $chiave): bool
    {
        return array_key_exists($chiave, $_POST);
    }

    /**
     * L'indirizzo del visitatore.
     *
     * Dietro il reverse proxy di questo server la sorgente giusta e'
     * X-Forwarded-For, ma ci si puo' fidare SOLO se la connessione diretta
     * arriva da un proxy noto: altrimenti chiunque potrebbe dichiarare
     * l'indirizzo che vuole e falsare il registro accessi.
     */
    public function ip(): string
    {
        $diretto = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $fidati = (array) Config::get('sicurezza.proxy_fidati', ['127.0.0.1', '::1']);
        if (in_array($diretto, $fidati, true)) {
            // Si legge la catena da DESTRA: l'ultimo elemento l'ha scritto
            // l'intermediario fidato, e ogni elemento prima di lui l'ha scritto
            // chi gli stava davanti. Il primo a sinistra lo sceglie il client,
            // e prenderlo per buono vorrebbe dire lasciargli decidere il proprio
            // indirizzo — e con esso freni, blocchi e tentativi di accesso.
            $catena = array_reverse(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))));
            foreach ($catena as $voce) {
                if (filter_var($voce, FILTER_VALIDATE_IP) === false) {
                    break;
                }
                if (!in_array($voce, $fidati, true)) {
                    return $voce;
                }
            }
        }

        return $diretto;
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function referente(): string
    {
        return substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    }

    public function lingua(): string
    {
        return substr((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 0, 100);
    }

    public function eAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
}
