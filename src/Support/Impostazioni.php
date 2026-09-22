<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Le impostazioni modificabili dal pannello.
 *
 * Distinte dalla configurazione in `config.php`: quella descrive come il
 * portale e' installato — database, percorsi, chiavi — e si cambia solo
 * rimettendo le mani sul server. Queste invece sono decisioni redazionali, e
 * l'admin le prende da solo.
 */
final class Impostazioni
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /** @return array<string,string> */
    private static function tutte(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];

        try {
            foreach (Database::righe('SELECT chiave, valore FROM impostazioni') as $r) {
                self::$cache[(string) $r['chiave']] = (string) $r['valore'];
            }
        } catch (\Throwable) {
            // Senza tabella si va avanti coi valori predefiniti: il portale
            // non deve cadere perche' manca un'impostazione.
        }

        return self::$cache;
    }

    public static function testo(string $chiave, string $predefinito = ''): string
    {
        return self::tutte()[$chiave] ?? $predefinito;
    }

    public static function attiva(string $chiave, bool $predefinito = true): bool
    {
        $v = self::tutte()[$chiave] ?? null;

        return $v === null ? $predefinito : $v === '1';
    }

    public static function intero(string $chiave, int $predefinito = 0): int
    {
        $v = self::tutte()[$chiave] ?? null;

        return $v === null ? $predefinito : (int) $v;
    }

    public static function imposta(string $chiave, string $valore): void
    {
        Database::esegui(
            'INSERT INTO impostazioni (chiave, valore, tipo, aggiornata) VALUES (?,?,?,NOW())
             ON DUPLICATE KEY UPDATE valore = VALUES(valore), aggiornata = NOW()',
            [$chiave, $valore, 'testo'],
        );
        self::$cache = null;
    }

    /** @return list<array<string,mixed>> */
    public static function elenco(): array
    {
        return Database::righe('SELECT chiave, valore, tipo, descrizione, aggiornata FROM impostazioni ORDER BY chiave');
    }
}
