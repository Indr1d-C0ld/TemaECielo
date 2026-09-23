<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Quante volte un cliente puo' fare una cosa costosa in un minuto.
 *
 * Il conteggio e' per indirizzo, non per sessione: una sessione la sceglie il
 * cliente — basta non mandare il cookie per averne una nuova — un indirizzo
 * no. Per IPv6 conta la rete /64, che e' quello che riceve di solito una
 * singola connessione domestica (vedi `Rete::chiaveCliente`).
 *
 * L'incremento e' atomico: un solo INSERT ... ON DUPLICATE KEY UPDATE, che il
 * database esegue per intero prima di servire la richiesta successiva. Il
 * valore si rilegge subito dopo; in una raffica parallela puo' risultare piu'
 * alto di uno o due, e quindi il freno, se sbaglia, sbaglia per eccesso di
 * prudenza — non lascia mai passare piu' del tetto.
 *
 * A riga di comando non frena nulla: le prove, la console e gli importatori
 * sono dell'amministratore.
 */
final class Freno
{
    /**
     * @return bool vero se si puo' procedere
     */
    public static function consenti(string $ambito, int $massimo, ?string $ip = null): bool
    {
        if (PHP_SAPI === 'cli' && $ip === null) {
            return true;
        }

        $ip ??= (string) ($GLOBALS['__ip_cliente'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '' || !Database::disponibile()) {
            return true;   // senza indirizzo o senza archivio non si frena: meglio servire
        }

        $chiave   = substr($ambito, 0, 30) . ':' . Rete::chiaveCliente($ip);
        $finestra = intdiv(time(), 60);

        try {
            // L'incremento e' atomico; il valore si rilegge subito dopo.
            //
            // Prima si usava LAST_INSERT_ID(n + 1) per avere il contatore dalla
            // stessa istruzione. Funziona sul duplicato, ma sulla PRIMA richiesta
            // del minuto — riga nuova, nessuna chiave automatica — LAST_INSERT_ID
            // non si azzera: restituisce l'ultimo identificativo inserito da
            // questa connessione, cioe' quello della cache del motore o del
            // registro. Una pagina che scriveva in cache e poi lanciava un altro
            // calcolo si sentiva dire «troppe richieste» al primo tentativo.
            // L'ha trovato una prova, prima che lo trovasse un visitatore.
            Database::esegui(
                'INSERT INTO freni (chiave, finestra, n) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE n = n + 1',
                [$chiave, $finestra],
            );
            $n = (int) Database::valore(
                'SELECT n FROM freni WHERE chiave = ? AND finestra = ?',
                [$chiave, $finestra],
            );

            if (random_int(1, 200) === 1) {
                Database::esegui('DELETE FROM freni WHERE finestra < ?', [$finestra - 5]);
            }
        } catch (\Throwable $e) {
            registro('freno: ' . $e->getMessage(), 'warn');
            return true;
        }

        return $n <= $massimo;
    }

    /** Come `consenti`, ma interrompe con TroppeRichieste. */
    public static function esigi(string $ambito, int $massimo): void
    {
        if (!self::consenti($ambito, $massimo)) {
            throw new TroppeRichieste(60 - time() % 60);
        }
    }
}
