<?php

declare(strict_types=1);

namespace App\Mondo;

use App\Astro\Corpi;
use App\Astro\Motore;
use App\Core\Database;
use App\Luogo\Gazetteer;
use App\Luogo\Tempo;
use DateTimeImmutable;
use DateTimeZone;

/**
 * L'astrologia mondiale: gli attrezzi comuni alle sue pagine.
 *
 * Una carta mondiale — un ingresso, una lunazione, un'eclissi — e' un istante
 * che vale per tutti, eretto per un luogo: di solito la capitale del paese che
 * si vuole guardare. L'istante lo trova il motore; il luogo lo sceglie chi
 * legge.
 */
final class Mondo
{
    /** Le effemeridi installate coprono questi anni, e nient'altro. */
    public const ANNO_MIN = 1800;
    public const ANNO_MAX = 2399;

    /** I corpi di una carta su cui si cercano i colpi (vedi puntiArchivio()). */
    private const PERSONALI = ['sole', 'luna', 'mercurio', 'venere', 'marte', 'giove', 'saturno'];

    /** Entro quanti gradi un'eclissi o una congiunzione «colpisce» una carta. */
    public const ORBE_COLPO = 2.0;

    /**
     * Le capitali proposte. Chi vuole un altro luogo lo scrive.
     *
     * @var array<string,array{0:string,1:float,2:float,3:string,4:int}> nome, lat, lon, fuso, altitudine
     */
    public const CAPITALI = [
        'roma'        => ['Roma', 41.89193, 12.51133, 'Europe/Rome', 20],
        'londra'      => ['Londra', 51.50853, -0.12574, 'Europe/London', 25],
        'parigi'      => ['Parigi', 48.85341, 2.3488, 'Europe/Paris', 42],
        'berlino'     => ['Berlino', 52.52437, 13.41053, 'Europe/Berlin', 43],
        'madrid'      => ['Madrid', 40.4165, -3.70256, 'Europe/Madrid', 667],
        'bruxelles'   => ['Bruxelles', 50.85045, 4.34878, 'Europe/Brussels', 28],
        'mosca'       => ['Mosca', 55.75222, 37.61556, 'Europe/Moscow', 144],
        'kyiv'        => ['Kyiv', 50.45466, 30.5238, 'Europe/Kyiv', 187],
        'washington'  => ['Washington', 38.89511, -77.03637, 'America/New_York', 7],
        'pechino'     => ['Pechino', 39.9075, 116.39723, 'Asia/Shanghai', 63],
        'tokyo'       => ['Tokyo', 35.6895, 139.69171, 'Asia/Tokyo', 44],
        'nuova-delhi' => ['Nuova Delhi', 28.63576, 77.22445, 'Asia/Kolkata', 216],
        'gerusalemme' => ['Gerusalemme', 31.76904, 35.21633, 'Asia/Jerusalem', 786],
        'il-cairo'    => ['Il Cairo', 30.06263, 31.24967, 'Africa/Cairo', 23],
        'brasilia'    => ['Brasilia', -15.77972, -47.92972, 'America/Sao_Paulo', 1172],
    ];

    /**
     * Il luogo chiesto: una capitale per chiave, o il primo risultato di una
     * ricerca libera. Roma se non si chiede niente o se non si trova niente.
     *
     * @return array{chiave:string,nome:string,lat:float,lon:float,fuso:string,alt:int}
     */
    public static function luogo(?string $chiave, ?string $testo = null): array
    {
        $testo = trim((string) $testo);
        if ($testo !== '' && mb_strlen($testo) >= 3) {
            $trovato = Gazetteer::cerca(mb_substr($testo, 0, 120), null, 1)[0] ?? null;
            if ($trovato !== null && Tempo::zonaValida((string) $trovato['fuso'])) {
                return [
                    'chiave' => '',
                    'nome'   => (string) $trovato['nome'],
                    'lat'    => (float) $trovato['lat'],
                    'lon'    => (float) $trovato['lon'],
                    'fuso'   => (string) $trovato['fuso'],
                    'alt'    => (int) ($trovato['altitudine'] ?? 0),
                ];
            }
        }
        $chiave = isset(self::CAPITALI[(string) $chiave]) ? (string) $chiave : 'roma';
        [$nome, $lat, $lon, $fuso, $alt] = self::CAPITALI[$chiave];

        return ['chiave' => $chiave, 'nome' => $nome, 'lat' => $lat, 'lon' => $lon, 'fuso' => $fuso, 'alt' => $alt];
    }

    /** Da giorno giuliano UT a istante Unix. */
    public static function unix(float $jd): int
    {
        return (int) round(($jd - 2440587.5) * 86400.0);
    }

    /** Da anno (1 gennaio, 0h UT) a giorno giuliano. */
    public static function jdAnno(int $anno): float
    {
        return 2440587.5 + (float) gmmktime(0, 0, 0, 1, 1, $anno) / 86400.0;
    }

    public static function jdOra(): float
    {
        return 2440587.5 + time() / 86400.0;
    }

    /** L'istante sull'orologio del luogo. */
    public static function locale(float $jd, string $fuso): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . self::unix($jd)))->setTimezone(new DateTimeZone($fuso));
    }

    /**
     * La carta di un istante qualunque per un luogo.
     *
     * @param array{lat:float,lon:float,alt:int} $luogo
     * @return array<string,mixed>
     */
    public static function tema(float $jd, array $luogo): array
    {
        return (new Motore())->tema(Tempo::componenti(self::unix($jd)) + [
            'lat' => $luogo['lat'], 'lon' => $luogo['lon'], 'alt' => $luogo['alt'],
            'sistema_case' => 'placido', 'ora_ignota' => false,
            'offset_secondi' => self::locale($jd, $luogo['fuso'])->getOffset(),
        ]);
    }

    /**
     * Dove un grado dello zodiaco cade sulle carte dell'archivio: i pianeti e
     * gli angoli entro l'orbe, in congiunzione.
     *
     * E' la domanda che la tradizione fa a ogni eclissi e a ogni grande
     * congiunzione: su chi cade? Una sola lettura del database per tutte le
     * carte pubblicate, e solo i campi che servono.
     *
     * @return list<array{nome:string,slug:string,punto:string,distanza:float}>
     */
    public static function colpi(float $lon, float $orbe = self::ORBE_COLPO): array
    {
        $fuori = [];
        foreach (self::puntiArchivio() as $carta) {
            foreach ($carta['punti'] as $punto => $l) {
                $d = Corpi::distanza($lon, $l);
                if ($d <= $orbe) {
                    $fuori[] = ['nome' => $carta['nome'], 'slug' => $carta['slug'], 'punto' => $punto, 'distanza' => $d];
                }
            }
        }
        usort($fuori, static fn (array $a, array $b): int => $a['distanza'] <=> $b['distanza']);

        return $fuori;
    }

    /**
     * I sette pianeti tradizionali e i due angoli di ogni carta pubblicata
     * nell'archivio.
     *
     * Urano, Nettuno e Plutone restano fuori: stanno anni nello stesso grado,
     * e un'eclissi sul Plutone di una carta cade sul Plutone di tutte le carte
     * di quegli anni — non dice niente di quella in particolare. Gli angoli
     * solo se l'ora e' nota e non approssimativa: un'eclissi sull'Ascendente
     * di una carta di classe C non e' un'informazione.
     *
     * @return list<array{nome:string,slug:string,punti:array<string,float>}>
     */
    public static function puntiArchivio(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $campi = [];
        foreach (self::PERSONALI as $k) {
            $campi[] = "JSON_EXTRACT(c.esito, '$.corpi.{$k}.lon') AS `{$k}`";
        }
        $righe = Database::righe(
            'SELECT a.nome, a.slug, a.rodden, ' . implode(', ', $campi) . ",
                    JSON_EXTRACT(c.esito, '$.punti.asc.lon') AS `asc`, JSON_EXTRACT(c.esito, '$.punti.mc.lon') AS `mc`
               FROM archivio a JOIN calcoli c ON c.id = a.calcolo_id
              WHERE a.pubblicata = 1",
        );

        $nomi = Corpi::elenco();
        $cache = [];
        foreach ($righe as $r) {
            $punti = [];
            foreach (self::PERSONALI as $k) {
                if ($r[$k] !== null) {
                    $punti[(string) $nomi[$k]['nome']] = (float) $r[$k];
                }
            }
            if (in_array($r['rodden'], ['AA', 'A', 'B'], true)) {
                foreach (['asc' => 'Ascendente', 'mc' => 'Medio Cielo'] as $k => $n) {
                    if ($r[$k] !== null) {
                        $punti[$n] = (float) $r[$k];
                    }
                }
            }
            $cache[] = ['nome' => (string) $r['nome'], 'slug' => (string) $r['slug'], 'punti' => $punti];
        }

        return $cache;
    }

    /** «0°29' Acquario», senza secondi: nelle tabelle bastano i primi. */
    public static function grado(float $lon): string
    {
        return Corpi::formatta($lon, false);
    }
}
