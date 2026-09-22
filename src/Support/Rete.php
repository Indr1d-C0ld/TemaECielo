<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/**
 * Indirizzi IP: forma binaria, appartenenza a una rete, geolocalizzazione.
 */
final class Rete
{
    /**
     * Un indirizzo in forma binaria a SEDICI byte, sempre.
     *
     * inet_pton restituisce quattro byte per un IPv4 e sedici per un IPv6.
     * Mescolare le due lunghezze nella stessa colonna rompe ogni confronto
     * d'intervallo — «192.0.2.1» risulterebbe minore di qualunque IPv6 — e il
     * guaio non si vede: le ricerche tornano semplicemente vuote.
     *
     * Gli IPv4 si portano quindi nella forma mappata ::ffff:a.b.c.d, che e' il
     * modo standard di rappresentarli nello spazio IPv6.
     */
    public static function binario(string $ip): ?string
    {
        $b = @inet_pton($ip);
        if ($b === false) {
            return null;
        }

        return strlen($b) === 4
            ? str_repeat("\x00", 10) . "\xff\xff" . $b
            : $b;
    }

    /**
     * Da dove viene un indirizzo.
     *
     * Gli intervalli di DB-IP non si accavallano: basta prendere quello che
     * comincia piu' vicino da sotto e controllare che l'indirizzo ci stia
     * dentro. Senza quel controllo finale, un indirizzo non coperto
     * ricadrebbe nell'intervallo precedente, che puo' essere dall'altra parte
     * del mondo.
     *
     * @return array<string,mixed>|null
     */
    public static function geolocalizza(string $ip): ?array
    {
        $b = self::binario($ip);
        if ($b === null) {
            return null;
        }

        try {
            $r = Database::riga(
                'SELECT paese, regione, citta, lat, lon FROM geoip_reti
                  WHERE ip_da <= ? ORDER BY ip_da DESC LIMIT 1',
                [$b],
            );
            if ($r === null) {
                return null;
            }

            $dentro = Database::valore(
                'SELECT 1 FROM geoip_reti WHERE ip_da <= ? AND ip_a >= ? ORDER BY ip_da DESC LIMIT 1',
                [$b, $b],
            );
            if ($dentro === null) {
                return null;
            }
        } catch (\Throwable) {
            return null;   // il database geografico puo' non essere importato
        }

        $paese = (string) $r['paese'];

        return [
            'paese'      => $paese,
            'paese_nome' => $paese !== '' && class_exists(\Locale::class)
                ? (\Locale::getDisplayRegion('-' . $paese, 'it') ?: $paese)
                : $paese,
            'regione'    => (string) $r['regione'],
            'citta'      => (string) $r['citta'],
            'lat'        => $r['lat'] === null ? null : (float) $r['lat'],
            'lon'        => $r['lon'] === null ? null : (float) $r['lon'],
        ];
    }

    /** @return array{asn:int,organizzazione:string}|null */
    public static function operatore(string $ip): ?array
    {
        $b = self::binario($ip);
        if ($b === null) {
            return null;
        }

        try {
            $r = Database::riga(
                'SELECT asn, organizzazione FROM geoip_asn
                  WHERE ip_da <= ? AND ip_a >= ? ORDER BY ip_da DESC LIMIT 1',
                [$b, $b],
            );
        } catch (\Throwable) {
            return null;
        }

        return $r === null
            ? null
            : ['asn' => (int) $r['asn'], 'organizzazione' => (string) $r['organizzazione']];
    }

    /**
     * Se un indirizzo appartiene a una rete in notazione CIDR.
     *
     * Serve ai blocchi: l'admin blocca «203.0.113.0/24» e tutti gli indirizzi
     * di quella rete restano fuori.
     */
    public static function dentro(string $ip, string $cidr): bool
    {
        $pezzi = explode('/', $cidr, 2);
        $reteB = self::binario($pezzi[0]);
        $ipB   = self::binario($ip);

        if ($reteB === null || $ipB === null) {
            return false;
        }

        // Senza barra e' un indirizzo singolo.
        if (!isset($pezzi[1])) {
            return $ipB === $reteB;
        }

        $bit = (int) $pezzi[1];

        // Una maschera scritta per IPv4 va traslata: nella forma mappata i
        // primi 96 bit sono il prefisso ::ffff:, e un /24 diventa un /120.
        if (str_contains($pezzi[0], '.')) {
            $bit += 96;
        }
        if ($bit < 0 || $bit > 128) {
            return false;
        }

        $interi = intdiv($bit, 8);
        $resto  = $bit % 8;

        if ($interi > 0 && substr($ipB, 0, $interi) !== substr($reteB, 0, $interi)) {
            return false;
        }
        if ($resto === 0) {
            return true;
        }

        $maschera = 0xFF << (8 - $resto) & 0xFF;

        return (ord($ipB[$interi]) & $maschera) === (ord($reteB[$interi]) & $maschera);
    }

    /** Se l'indirizzo e' in un blocco attivo. */
    public static function bloccato(string $ip): bool
    {
        try {
            $righe = Database::righe(
                'SELECT cidr FROM blocchi WHERE scade IS NULL OR scade > NOW()'
            );
        } catch (\Throwable) {
            return false;
        }

        foreach ($righe as $r) {
            if (self::dentro($ip, (string) $r['cidr'])) {
                return true;
            }
        }

        return false;
    }
}
