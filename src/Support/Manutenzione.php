<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use App\Core\Database;
use DateTimeImmutable;

/**
 * La manutenzione che il portale fa da solo: la purga del registro accessi e
 * le partizioni mensili di `accessi`.
 *
 * Tutte e due esistevano solo a meta'. La purga era un'impostazione mostrata
 * nel pannello e promessa dal README, ma nessun codice cancellava niente. Le
 * partizioni si aggiungevano solo lanciando a mano un comando che chi installa
 * non sa di dover lanciare, e dal gennaio 2028 ogni riga sarebbe finita nella
 * partizione di riserva. Ora le fa la telemetria, una richiesta su
 * duecento, come la cache del motore si sfoltisce da sola.
 */
final class Manutenzione
{
    /** Ogni quante richieste si controlla. */
    public const UNA_SU = 200;

    /** Quante righe al massimo per giro, per non tenere ferma una richiesta. */
    private const RIGHE_PER_GIRO = 5000;

    /** Chiamata dalla telemetria: di rado, e mai due processi insieme. */
    public static function ogniTanto(): void
    {
        if (random_int(1, self::UNA_SU) !== 1) {
            return;
        }
        if ((int) Database::valore("SELECT GET_LOCK('tec_manutenzione', 0)") !== 1) {
            return;
        }
        try {
            $giorni = (int) Config::get('privacy.purga_accessi_giorni', 0);
            if ($giorni > 0) {
                self::purga($giorni, self::RIGHE_PER_GIRO);
            }
            if (self::mesiCoperti() < 3) {
                self::partizioni();
            }
        } catch (\Throwable $e) {
            registro('manutenzione: ' . $e->getMessage(), 'warn');
        } finally {
            Database::valore("SELECT RELEASE_LOCK('tec_manutenzione')");
        }
    }

    /**
     * Cancella accessi, eventi e sessioni piu' vecchi di N giorni.
     *
     * @return array{accessi:int,eventi:int,sessioni:int}
     */
    public static function purga(int $giorni, ?int $limite = null): array
    {
        $giorni = max(1, $giorni);
        $coda = $limite !== null ? ' LIMIT ' . max(1, $limite) : '';
        $fuori = [];
        foreach (['accessi' => 'quando', 'eventi' => 'quando', 'sessioni' => 'ultima_vista'] as $tabella => $colonna) {
            $fuori[$tabella] = Database::esegui(
                "DELETE FROM {$tabella} WHERE {$colonna} < NOW() - INTERVAL {$giorni} DAY{$coda}",
            )->rowCount();
        }

        return $fuori;
    }

    /** Quanti mesi futuri hanno gia' la loro partizione, a partire dal prossimo. */
    public static function mesiCoperti(): int
    {
        $presenti = self::partizioniPresenti();
        $mese = new DateTimeImmutable('first day of this month 00:00:00');
        for ($i = 0; $i < 24; $i++) {
            $mese = $mese->modify('+1 month');
            if (!in_array('p' . $mese->format('Y_m'), $presenti, true)) {
                return $i;
            }
        }

        return 24;
    }

    /**
     * Aggiunge le partizioni mensili mancanti, fino a dodici mesi avanti.
     *
     * pMAX esiste per non perdere mai una riga, ma una partizione MAXVALUE non
     * si puo' «estendere»: si riorganizza, staccando da lei i mesi nuovi.
     *
     * @return list<string> le partizioni aggiunte
     */
    public static function partizioni(): array
    {
        $presenti = self::partizioniPresenti();
        if ($presenti === []) {
            throw new \RuntimeException('La tabella `accessi` non risulta partizionata.');
        }

        $nuove = [];
        $mese  = new DateTimeImmutable('first day of this month 00:00:00');
        for ($i = 0; $i < 12; $i++) {
            $mese = $mese->modify('+1 month');
            $nome = 'p' . $mese->format('Y_m');
            if (!in_array($nome, $presenti, true)) {
                $nuove[$nome] = $mese->modify('+1 month')->format('Y-m-01');
            }
        }
        if ($nuove === []) {
            return [];
        }

        $pezzi = [];
        foreach ($nuove as $nome => $limite) {
            $pezzi[] = sprintf("PARTITION %s VALUES LESS THAN ('%s')", $nome, $limite);
        }
        $pezzi[] = 'PARTITION pMAX VALUES LESS THAN (MAXVALUE)';
        Database::pdo()->exec('ALTER TABLE accessi REORGANIZE PARTITION pMAX INTO (' . implode(', ', $pezzi) . ')');

        return array_keys($nuove);
    }

    /** @return list<string> */
    private static function partizioniPresenti(): array
    {
        return array_map(
            static fn (array $r): string => (string) $r['PARTITION_NAME'],
            Database::righe(
                'SELECT PARTITION_NAME FROM information_schema.PARTITIONS
                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL',
                [(string) Config::get('db.name'), 'accessi'],
            ),
        );
    }
}
