<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Registro accessi ed eventi.
 *
 * Scrive a fine richiesta, quando stato, byte e durata sono gia' noti. Non
 * deve MAI far fallire una pagina: qualunque errore qui viene inghiottito e
 * finisce nel log applicativo, perche' un portale che si rompe perche' non
 * riesce a contare le visite e' un portale mal fatto.
 */
final class Telemetria
{
    private static ?string $sessione = null;

    /** Identificativo di sessione per la telemetria, staccato da quello di PHP. */
    public static function sessione(): string
    {
        if (self::$sessione !== null) {
            return self::$sessione;
        }

        $s = Session::get('__tel');
        if (!is_string($s) || strlen($s) !== 32) {
            $s = bin2hex(random_bytes(16));
            Session::set('__tel', $s);
        }

        return self::$sessione = $s;
    }

    public static function registra(Request $richiesta, Response $risposta, float $inizio): void
    {
        try {
            $ip  = $richiesta->ip();
            $ua  = self::taglia($richiesta->userAgent(), 500);
            $sc  = Agente::leggi($ua);
            $ses = self::sessione();
            $ms  = (int) round((microtime(true) - $inizio) * 1000);

            if ((bool) Config::get('privacy.anonimizza_ip', false)) {
                $ip = self::anonimizza($ip);
            }

            // Le API del modulo ricevono nella richiesta proprio i dati di
            // nascita — /api/fuso?data=…&ora=…, /api/luoghi?q=<il paese
            // natale> — e il registro accessi li teneva accanto all'IP, anche
            // dopo che la carta era stata cancellata. Delle API resta il
            // percorso, non la domanda.
            $parametri = str_contains($richiesta->percorso(), '/api/') ? '' : (string) ($_SERVER['QUERY_STRING'] ?? '');
            $dove = self::dove($ip);

            Database::esegui(
                'INSERT INTO accessi
                   (quando, sessione, ip, ip_binario, paese, regione, citta, lat, lon,
                    asn, operatore, metodo, percorso, parametri, stato,
                    byte_inviati, durata_ms, referente, lingua, ua, ua_famiglia, ua_so,
                    dispositivo, bot)
                 VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $ses,
                    $ip,
                    Rete::binario($ip),
                    $dove['paese'],
                    $dove['regione'],
                    $dove['citta'],
                    $dove['lat'],
                    $dove['lon'],
                    $dove['asn'],
                    $dove['operatore'],
                    $richiesta->metodo(),
                    self::taglia($richiesta->percorso(), 255),
                    self::taglia($parametri, 255),
                    $risposta->stato(),
                    $risposta->lunghezza(),
                    $ms,
                    self::taglia($richiesta->referente(), 255),
                    self::taglia($richiesta->lingua(), 100),
                    $ua,
                    $sc['famiglia'],
                    $sc['so'],
                    $sc['dispositivo'],
                    $sc['bot'] ? 1 : 0,
                ],
            );

            self::aggiornaSessione($ses, $ip, $richiesta, $sc, $dove['paese']);
            Manutenzione::ogniTanto();
        } catch (\Throwable $e) {
            registro('telemetria: ' . $e->getMessage(), 'warn');
        }
    }

    /** @param array{famiglia:string,so:string,dispositivo:string,bot:bool} $sc */
    private static function aggiornaSessione(string $ses, string $ip, Request $r, array $sc, ?string $paese): void
    {
        Database::esegui(
            'INSERT INTO sessioni
               (id, prima_vista, ultima_vista, pagine, ip, paese, ua_famiglia, ua_so, dispositivo, bot,
                ingresso, referente)
             VALUES (?, NOW(), NOW(), 1, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ultima_vista = NOW(), pagine = pagine + 1',
            [
                $ses,
                $ip,
                $paese,
                $sc['famiglia'],
                $sc['so'],
                $sc['dispositivo'],
                $sc['bot'] ? 1 : 0,
                self::taglia($r->percorso(), 255),
                self::taglia($r->referente(), 255),
            ],
        );
    }

    /**
     * Da dove viene l'indirizzo, con la risposta tenuta da parte per la sessione.
     *
     * Un visitatore che sfoglia dieci pagine ha lo stesso indirizzo dieci
     * volte: cercarlo ogni volta in una tabella da otto milioni di righe e'
     * lavoro sprecato.
     *
     * @return array{paese:?string,regione:?string,citta:?string,lat:?float,lon:?float,asn:?int,operatore:?string}
     */
    private static function dove(string $ip): array
    {
        $vuoto = ['paese' => null, 'regione' => null, 'citta' => null,
                  'lat' => null, 'lon' => null, 'asn' => null, 'operatore' => null];

        $cache = Session::get('__dove');
        if (is_array($cache) && ($cache['ip'] ?? null) === $ip) {
            return $cache['dati'];
        }

        $g = Rete::geolocalizza($ip);
        $o = Rete::operatore($ip);

        $dati = [
            'paese'     => $g['paese'] ?? null,
            'regione'   => $g['regione'] ?? null,
            'citta'     => $g['citta'] ?? null,
            'lat'       => $g['lat'] ?? null,
            'lon'       => $g['lon'] ?? null,
            'asn'       => $o['asn'] ?? null,
            'operatore' => $o['organizzazione'] ?? null,
        ];

        Session::set('__dove', ['ip' => $ip, 'dati' => $dati]);

        return $dati === $vuoto ? $vuoto : $dati;
    }

    /** Evento applicativo: imbuto del modulo, sezioni usate, errori del motore. */
    public static function evento(string $tipo, string $oggetto = '', string $valore = '', ?int $durataMs = null): void
    {
        try {
            Database::esegui(
                'INSERT INTO eventi (quando, sessione, tipo, oggetto, valore, durata_ms)
                 VALUES (NOW(), ?, ?, ?, ?, ?)',
                [self::sessione(), self::taglia($tipo, 48), self::taglia($oggetto, 128), self::taglia($valore, 255), $durataMs],
            );
        } catch (\Throwable $e) {
            registro('evento: ' . $e->getMessage(), 'warn');
        }
    }

    /**
     * Taglia una stringa per una colonna del database, senza romperla.
     *
     * `substr` taglia a BYTE: su un testo in UTF-8 puo' spezzare un carattere a
     * meta', e un User-Agent o un Referer con byte non validi basta da solo.
     * Col database in modalita' stretta l'inserimento viene rifiutato, l'errore
     * finisce solo nel diario, e la richiesta sparisce dal registro degli
     * accessi — cioe' chi vuole passare inosservato ha un modo semplice per
     * farlo. Qui prima si ripuliscono i byte non validi, poi si taglia sul
     * confine di un carattere.
     */
    private static function taglia(string $s, int $byte): string
    {
        return mb_strcut(mb_scrub($s, 'UTF-8'), 0, $byte, 'UTF-8');
    }

    /** IPv4 al /24, IPv6 al /48. Disattivata per impostazione. */
    public static function anonimizza(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $p = explode('.', $ip);
            return $p[0] . '.' . $p[1] . '.' . $p[2] . '.0';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $bin = (string) @inet_pton($ip);
            return (string) @inet_ntop(substr($bin, 0, 6) . str_repeat("\0", 10));
        }

        return $ip;
    }
}
