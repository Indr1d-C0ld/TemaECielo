<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Wrapper sottile su PDO/MariaDB. Connessione pigra e condivisa.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('db.host', '127.0.0.1'),
            (int) Config::get('db.port', 3306),
            (string) Config::get('db.name', ''),
            (string) Config::get('db.charset', 'utf8mb4'),
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('db.user', ''),
                (string) Config::get('db.pass', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ],
            );
        } catch (PDOException $e) {
            throw new RuntimeException('Connessione al database non riuscita: ' . $e->getMessage(), 0, $e);
        }

        // Il database e l'applicazione devono avere lo stesso «adesso»: altrimenti
        // NOW() e PHP divergono e ogni conto sul tempo sbaglia in silenzio.
        self::$pdo->exec("SET time_zone = '" . self::scartoUtc() . "'");

        return self::$pdo;
    }

    private static function scartoUtc(): string
    {
        $tz  = new \DateTimeZone((string) Config::get('app.timezone', 'UTC'));
        $sec = $tz->getOffset(new \DateTimeImmutable('now', $tz));
        $seg = $sec < 0 ? '-' : '+';
        $sec = abs($sec);

        return sprintf('%s%02d:%02d', $seg, intdiv($sec, 3600), intdiv($sec % 3600, 60));
    }

    /** @param array<string|int,mixed> $parametri */
    public static function esegui(string $sql, array $parametri = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($parametri);

        return $stmt;
    }

    /**
     * @param array<string|int,mixed> $parametri
     * @return array<string,mixed>|null
     */
    public static function riga(string $sql, array $parametri = []): ?array
    {
        $riga = self::esegui($sql, $parametri)->fetch();

        return $riga === false ? null : $riga;
    }

    /**
     * @param array<string|int,mixed> $parametri
     * @return list<array<string,mixed>>
     */
    public static function righe(string $sql, array $parametri = []): array
    {
        return self::esegui($sql, $parametri)->fetchAll();
    }

    /** @param array<string|int,mixed> $parametri */
    public static function valore(string $sql, array $parametri = []): mixed
    {
        $v = self::esegui($sql, $parametri)->fetchColumn();

        return $v === false ? null : $v;
    }

    public static function ultimoId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function disponibile(): bool
    {
        try {
            self::pdo()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
