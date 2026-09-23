<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $avviata = false;

    public static function avvia(): void
    {
        if (self::$avviata || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$avviata = true;
            return;
        }

        $sicuro = (($_SERVER['HTTPS'] ?? '') !== '')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        // Modalita' stretta: un identificativo di sessione che il server non ha
        // emesso viene rifiutato e sostituito, invece di essere adottato. Senza,
        // chi riesce a piazzare un cookie nel browser di qualcun altro sceglie
        // lui l'identificativo — e lo conosce.
        ini_set('session.use_strict_mode', '1');
        session_name((string) Config::get('sicurezza.nome_sessione', 'temaecielo_sess'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => (string) Config::get('app.base_path', '/') ?: '/',
            'secure'   => $sicuro,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $durata = (int) Config::get('sicurezza.durata_sessione', 7200);
        $ultima = (int) ($_SESSION['__ultima'] ?? 0);
        if ($ultima > 0 && (time() - $ultima) > $durata) {
            self::distruggi();
            session_start();
        }
        $_SESSION['__ultima'] = time();

        self::$avviata = true;
    }

    public static function get(string $chiave, mixed $predefinito = null): mixed
    {
        return $_SESSION[$chiave] ?? $predefinito;
    }

    public static function set(string $chiave, mixed $valore): void
    {
        $_SESSION[$chiave] = $valore;
    }

    public static function togli(string $chiave): void
    {
        unset($_SESSION[$chiave]);
    }

    /** Messaggio da mostrare una volta sola alla pagina successiva. */
    public static function lampo(string $tipo, string $testo): void
    {
        $_SESSION['__lampi'][] = ['tipo' => $tipo, 'testo' => $testo];
    }

    /** @return list<array{tipo:string,testo:string}> */
    public static function lampi(): array
    {
        $l = $_SESSION['__lampi'] ?? [];
        unset($_SESSION['__lampi']);

        return is_array($l) ? $l : [];
    }

    public static function rigenera(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function distruggi(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }
}
