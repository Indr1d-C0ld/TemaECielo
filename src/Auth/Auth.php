<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Csrf;
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

        // Il tentativo si conta PRIMA di verificarlo, come fallito; se va bene,
        // registraTentativo(..., true) toglie i fallimenti di questo indirizzo.
        //
        // Contarlo dopo lasciava due varchi. Il primo: fra il controllo del
        // blocco e la registrazione passava un Argon2id intero, e una raffica
        // di richieste parallele superava il tetto di parecchio. Il secondo, piu'
        // grave: se la registrazione falliva — e falliva, con un nome utente
        // costruito apposta per essere troncato a meta' di un carattere — il
        // tentativo semplicemente non esisteva, e il blocco non scattava mai.
        // Qui la registrazione non puo' fallire (vedi `registraTentativo`), e
        // se fallisse lo stesso l'eccezione ferma tutto PRIMA di verificare.
        self::registraTentativo($ip, $utente, false);

        $riga = Database::riga(
            'SELECT id, utente, password_hash, attivo FROM amministratori WHERE utente = ? LIMIT 1',
            [$utente],
        );

        // Il database confronta i nomi con una collation che ignora le
        // maiuscole, gli accenti e i caratteri invisibili: per lui «admin»
        // seguito da venti spazi a larghezza zero e' «admin». Per chi entra
        // no. Il nome deve coincidere byte per byte, altrimenti e' un nome che
        // non esiste — e segue la stessa strada, allo stesso costo.
        if (is_array($riga) && !hash_equals((string) $riga['utente'], $utente)) {
            $riga = null;
        }

        // Si calcola comunque un hash finto quando l'utente non esiste: altrimenti
        // il tempo di risposta direbbe a un estraneo quali nomi utente sono validi.
        $hash = is_array($riga) ? (string) $riga['password_hash'] : self::hashFinto();
        $ok   = password_verify($password, $hash);

        if (!is_array($riga) || (int) $riga['attivo'] !== 1 || !$ok) {
            return ['esito' => false, 'motivo' => 'credenziali', 'attesa' => 0];
        }

        if (password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPZIONI)) {
            Database::esegui(
                'UPDATE amministratori SET password_hash = ? WHERE id = ?',
                [self::hash($password), (int) $riga['id']],
            );
        }

        // Prima le scritture di registro, POI la sessione: se una scrittura
        // fallisse, l'eccezione non deve trovare un amministratore gia' dentro.
        Database::esegui(
            'UPDATE amministratori SET ultimo_accesso = NOW(), ultimo_ip = ? WHERE id = ?',
            [$ip, (int) $riga['id']],
        );
        self::registraTentativo($ip, $utente, true);

        Session::rigenera();
        Csrf::rinnova();
        Session::set(self::CHIAVE, [
            'id'     => (int) $riga['id'],
            'utente' => (string) $riga['utente'],
            'da'     => time(),
        ]);

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
        $ip = self::chiaveIp($ip);
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
        $ip = self::chiaveIp($ip);

        Database::esegui(
            'INSERT INTO accessi_admin (quando, ip, utente, riuscito) VALUES (NOW(), ?, ?, ?)',
            // `mb_scrub` e `mb_strcut`, non `substr`: tagliare a 64 BYTE un nome
            // lungo spezzava l'ultimo carattere a meta', il database in
            // modalita' stretta rifiutava la riga, e il tentativo spariva.
            [$ip, mb_strcut(mb_scrub($utente, 'UTF-8'), 0, 64, 'UTF-8'), $riuscito ? 1 : 0],
        );

        if ($riuscito) {
            Database::esegui('DELETE FROM accessi_admin WHERE ip = ? AND riuscito = 0', [$ip]);
        }
    }

    /** Vedi `Rete::chiaveCliente`: per IPv6 si conta la rete /64. */
    private static function chiaveIp(string $ip): string
    {
        return \App\Support\Rete::chiaveCliente($ip);
    }

    /**
     * Impronta di comodo con lo stesso costo della vera, per non rivelare i nomi
     * utente: a un nome inesistente si fa verificare questa, e il tempo di
     * risposta e' lo stesso di un nome esistente con la password sbagliata.
     *
     * E' scritta qui come costante, e non calcolata al momento. Prima veniva
     * generata alla prima richiesta con `password_hash` — ma sotto il web ogni
     * richiesta e' un processo nuovo, quindi la «prima volta» era ogni volta:
     * per un nome inesistente si pagavano DUE Argon2id (generare e verificare)
     * contro uno solo per quello vero. Il cronometro diceva il contrario di
     * quello che l'impronta finta doveva nascondere: 450 ms contro 180.
     *
     * Nessuno conosce la parola da cui viene, che era casuale ed e' stata
     * buttata: verificarla contro qualunque password da' sempre falso. I
     * parametri sono quelli di OPZIONI; se cambiano, va rigenerata con
     *   php -r 'echo password_hash(bin2hex(random_bytes(24)), PASSWORD_ARGON2ID,
     *           ["memory_cost"=>65536,"time_cost"=>4,"threads"=>2]);'
     */
    private const HASH_FINTO = '$argon2id$v=19$m=65536,t=4,p=2$cGdJZzdWSjJnNzhsNG13bg$UpcU03eHy6/v/fTtNJSh5CgqLAeQmmYd5rA/bO4wdbY';

    private static function hashFinto(): string
    {
        return self::HASH_FINTO;
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
