<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use App\Core\Session;

/**
 * Accesso dell'amministratore.
 *
 * Credenziali proprie in tabella, non .htpasswd: il pannello ha un registro
 * delle azioni, e un registro ha senso solo se sa CHI ha agito e da dove.
 *
 * La password non esiste in chiaro da nessuna parte — ne' nei file, ne' nei
 * repository, ne' in questo codice. Si sceglie una volta con
 *   php bin/console.php admin:password
 * e di lei resta solo un hash Argon2id.
 */
final class Auth
{
    private const CHIAVE   = '__admin';
    private const TENTATIVI = 5;
    private const BLOCCO    = 900; // 15 minuti

    /** Parametri Argon2id: generosi ma sostenibili su questo server. */
    private const OPZIONI = [
        'memory_cost' => 65536,
        'time_cost'   => 4,
        'threads'     => 2,
    ];

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPZIONI);
    }

    /**
     * @return array{esito:bool,motivo:string,attesa:int}
     */
    public static function entra(string $utente, string $password, string $ip): array
    {
        $attesa = self::bloccoResiduo($ip);
        if ($attesa > 0) {
            return ['esito' => false, 'motivo' => 'bloccato', 'attesa' => $attesa];
        }

        $riga = Database::riga(
            'SELECT id, utente, password_hash, attivo FROM amministratori WHERE utente = ? LIMIT 1',
            [$utente],
        );

        // Si calcola comunque un hash finto quando l'utente non esiste: altrimenti
        // il tempo di risposta direbbe a un estraneo quali nomi utente sono validi.
        $hash = is_array($riga) ? (string) $riga['password_hash'] : self::hashFinto();
        $ok   = password_verify($password, $hash);

        if (!is_array($riga) || (int) $riga['attivo'] !== 1 || !$ok) {
            self::registraTentativo($ip, $utente, false);
            return ['esito' => false, 'motivo' => 'credenziali', 'attesa' => 0];
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPZIONI)) {
            Database::esegui(
                'UPDATE amministratori SET password_hash = ? WHERE id = ?',
                [self::hash($password), (int) $riga['id']],
            );
        }

        Session::rigenera();
        Session::set(self::CHIAVE, [
            'id'     => (int) $riga['id'],
            'utente' => (string) $riga['utente'],
            'da'     => time(),
        ]);

        Database::esegui(
            'UPDATE amministratori SET ultimo_accesso = NOW(), ultimo_ip = ? WHERE id = ?',
            [$ip, (int) $riga['id']],
        );
        self::registraTentativo($ip, $utente, true);

        return ['esito' => true, 'motivo' => '', 'attesa' => 0];
    }

    public static function esci(): void
    {
        Session::togli(self::CHIAVE);
        Session::rigenera();
    }

    public static function amministratore(): bool
    {
        return is_array(Session::get(self::CHIAVE));
    }

    /** @return array{id:int,utente:string,da:int}|null */
    public static function corrente(): ?array
    {
        $a = Session::get(self::CHIAVE);

        return is_array($a) ? $a : null;
    }

    public static function nome(): string
    {
        return (string) (self::corrente()['utente'] ?? '');
    }

    /** Secondi mancanti alla fine del blocco per questo IP, 0 se libero. */
    public static function bloccoResiduo(string $ip): int
    {
        $falliti = (int) Database::valore(
            'SELECT COUNT(*) FROM accessi_admin
              WHERE ip = ? AND riuscito = 0 AND quando > (NOW() - INTERVAL ? SECOND)',
            [$ip, self::BLOCCO],
        );

        if ($falliti < self::TENTATIVI) {
            return 0;
        }

        $ultimo = Database::valore(
            'SELECT UNIX_TIMESTAMP(MAX(quando)) FROM accessi_admin WHERE ip = ? AND riuscito = 0',
            [$ip],
        );

        return max(0, self::BLOCCO - (time() - (int) $ultimo));
    }

    private static function registraTentativo(string $ip, string $utente, bool $riuscito): void
    {
        Database::esegui(
            'INSERT INTO accessi_admin (quando, ip, utente, riuscito) VALUES (NOW(), ?, ?, ?)',
            [$ip, substr($utente, 0, 64), $riuscito ? 1 : 0],
        );

        if ($riuscito) {
            Database::esegui('DELETE FROM accessi_admin WHERE ip = ? AND riuscito = 0', [$ip]);
        }
    }

    /** Hash di comodo con lo stesso costo del vero, per non rivelare i nomi utente. */
    private static function hashFinto(): string
    {
        static $finto = null;

        return $finto ??= self::hash(bin2hex(random_bytes(16)));
    }

    /** Scrive una riga nel registro delle azioni dell'admin. */
    public static function traccia(string $azione, string $oggetto = '', string $dettaglio = ''): void
    {
        $a = self::corrente();
        Database::esegui(
            'INSERT INTO admin_registro (quando, admin_id, admin_utente, azione, oggetto, dettaglio)
             VALUES (NOW(), ?, ?, ?, ?, ?)',
            [
                $a['id'] ?? null,
                (string) ($a['utente'] ?? 'sistema'),
                substr($azione, 0, 64),
                substr($oggetto, 0, 128),
                substr($dettaglio, 0, 1000),
            ],
        );
    }
}
