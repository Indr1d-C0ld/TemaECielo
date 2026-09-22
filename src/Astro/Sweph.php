<?php

declare(strict_types=1);

namespace App\Astro;

use FFI;
use RuntimeException;

/**
 * Legame con la libreria C della Swiss Ephemeris, via FFI.
 *
 * ATTENZIONE: questa classe si usa SOLO da riga di comando.
 *
 * PHP sotto Apache ha `ffi.enable=preload`, che blocca l'FFI a meno di
 * modificare php.ini da root; in PHP CLI l'FFI e' invece sempre attivo, senza
 * configurazione. Percio' il livello web non tocca mai questa classe: invoca
 * bin/effemeridi.php con proc_open e gli parla in JSON. Vedi Astro\Motore.
 */
final class Sweph
{
    /** Bandiere della libreria. */
    public const SWIEPH     = 2;        // effemeridi compresse .se1
    public const MOSEPH     = 4;        // effemeride analitica Moshier, senza file
    public const SPEED      = 256;      // calcola anche le velocita'
    public const EQUATORIAL = 2048;     // coordinate equatoriali invece che eclittiche
    public const TOPOCTR    = 32768;    // topocentrico invece che geocentrico
    public const SIDEREAL   = 65536;

    public const GREGORIANO = 1;

    // swe_rise_trans
    public const LEVATA      = 1;
    public const TRAMONTO    = 2;
    public const CULMINAZIONE = 4;
    public const ANTICULMINE = 8;
    public const CENTRO_DISCO = 256;

    // swe_azalt
    public const ECL_A_ORIZZONTE = 0;

    private FFI $ffi;
    private bool $conFile;
    private string $versione;

    private const DICHIARAZIONI = <<<'C'
        char *swe_version(char *svers);
        void swe_set_ephe_path(char *path);
        void swe_close(void);
        void swe_set_topo(double geolon, double geolat, double geoalt);
        double swe_julday(int year, int month, int day, double hour, int gregflag);
        void swe_revjul(double tjd, int gregflag, int *jyear, int *jmon, int *jday, double *jut);
        double swe_deltat(double tjd);
        double swe_sidtime(double tjd_ut);
        double swe_get_ayanamsa_ut(double tjd_ut);
        int swe_calc_ut(double tjd_ut, int ipl, int iflag, double *xx, char *serr);
        int swe_houses_ex(double tjd_ut, int iflag, double geolat, double geolon, int hsys,
                          double *cusps, double *ascmc);
        int swe_fixstar2_ut(char *star, double tjd_ut, int iflag, double *xx, char *serr);
        int swe_pheno_ut(double tjd_ut, int ipl, int iflag, double *attr, char *serr);
        int swe_rise_trans(double tjd_ut, int ipl, char *starname, int epheflag, int rsmi,
                           double *geopos, double atpress, double attemp, double *tret, char *serr);
        void swe_azalt(double tjd_ut, int calc_flag, double *geopos, double atpress, double attemp,
                       double *xin, double *xaz);
        void swe_cotrans(double *xpo, double *xpn, double eps);
        C;

    public function __construct(string $percorsoLibreria, string $percorsoEffemeridi)
    {
        if (!extension_loaded('FFI')) {
            throw new RuntimeException('Estensione FFI non disponibile.');
        }
        if (!is_file($percorsoLibreria)) {
            throw new RuntimeException("Libreria Swiss Ephemeris non trovata: {$percorsoLibreria}");
        }

        try {
            $this->ffi = FFI::cdef(self::DICHIARAZIONI, $percorsoLibreria);
        } catch (\Throwable $e) {
            throw new RuntimeException('Impossibile legare libswe: ' . $e->getMessage(), 0, $e);
        }

        // Senza i file .se1 la libreria ripiega sull'effemeride analitica di
        // Moshier: nessun file, precisione di qualche decimo di secondo d'arco.
        // Per l'astrologia e' piu' che sufficiente, ma va DETTO, non subito.
        $this->conFile = is_dir($percorsoEffemeridi) && (glob($percorsoEffemeridi . '/*.se1') ?: []) !== [];

        if ($this->conFile) {
            $this->ffi->swe_set_ephe_path($percorsoEffemeridi);
        }

        $buf = FFI::new('char[256]');
        $this->ffi->swe_version(FFI::cast('char *', $buf));
        $this->versione = FFI::string($buf);
    }

    public function __destruct()
    {
        try {
            $this->ffi->swe_close();
        } catch (\Throwable) {
            // in chiusura non c'e' nessuno a cui dirlo
        }
    }

    public function versione(): string
    {
        return $this->versione;
    }

    public function conFileEffemeridi(): bool
    {
        return $this->conFile;
    }

    /** La bandiera dell'effemeride da usare: file se ci sono, Moshier altrimenti. */
    public function bandieraBase(): int
    {
        return ($this->conFile ? self::SWIEPH : self::MOSEPH) | self::SPEED;
    }

    public function giornoGiuliano(int $anno, int $mese, int $giorno, float $oraDecimale): float
    {
        return $this->ffi->swe_julday($anno, $mese, $giorno, $oraDecimale, self::GREGORIANO);
    }

    /** @return array{anno:int,mese:int,giorno:int,ora:float} */
    public function daGiornoGiuliano(float $jd): array
    {
        $a = FFI::new('int'); $m = FFI::new('int'); $g = FFI::new('int'); $o = FFI::new('double');
        $this->ffi->swe_revjul($jd, self::GREGORIANO, FFI::addr($a), FFI::addr($m), FFI::addr($g), FFI::addr($o));

        return ['anno' => $a->cdata, 'mese' => $m->cdata, 'giorno' => $g->cdata, 'ora' => $o->cdata];
    }

    public function deltaT(float $jd): float
    {
        return $this->ffi->swe_deltat($jd);
    }

    /** Tempo siderale di Greenwich, in ore. */
    public function tempoSiderale(float $jdUt): float
    {
        return $this->ffi->swe_sidtime($jdUt);
    }

    public function impostaTopocentrico(float $lon, float $lat, float $alt): void
    {
        $this->ffi->swe_set_topo($lon, $lat, $alt);
    }

    /**
     * Posizione di un corpo.
     *
     * @return array{lon:float,lat:float,dist:float,vel_lon:float,vel_lat:float,vel_dist:float}
     * @throws RuntimeException se la libreria non sa calcolare quel corpo
     */
    public function posizione(float $jdUt, int $ipl, ?int $bandiere = null): array
    {
        $xx   = FFI::new('double[6]');
        $serr = FFI::new('char[256]');

        $r = $this->ffi->swe_calc_ut(
            $jdUt,
            $ipl,
            $bandiere ?? $this->bandieraBase(),
            $xx,
            FFI::cast('char *', $serr),
        );

        if ($r < 0) {
            throw new RuntimeException('swe_calc_ut(' . $ipl . '): ' . FFI::string($serr));
        }

        return [
            'lon'      => $xx[0],
            'lat'      => $xx[1],
            'dist'     => $xx[2],
            'vel_lon'  => $xx[3],
            'vel_lat'  => $xx[4],
            'vel_dist' => $xx[5],
        ];
    }

    /**
     * Ascensione retta e declinazione dello stesso corpo.
     *
     * @return array{ar:float,decl:float,dist:float}
     */
    public function posizioneEquatoriale(float $jdUt, int $ipl): array
    {
        $p = $this->posizione($jdUt, $ipl, $this->bandieraBase() | self::EQUATORIAL);

        return ['ar' => $p['lon'], 'decl' => $p['lat'], 'dist' => $p['dist']];
    }

    /**
     * Cuspidi delle case e punti d'angolo.
     *
     * Restituisce anche `degenere`: alle alte latitudini Placido e Koch non
     * hanno soluzione — oltre il circolo polare certe cuspidi semplicemente non
     * esistono — e la libreria ripiega su Porfirio senza dirlo. Qui lo diciamo.
     *
     * @return array{cuspidi:list<float>,asc:float,mc:float,armc:float,vertex:float,
     *               equatoriale:float,coasc_koch:float,polascensione:float,degenere:bool}
     */
    public function case(float $jdUt, float $lat, float $lon, string $codice): array
    {
        $cusps  = FFI::new('double[13]');
        $ascmc  = FFI::new('double[10]');

        $r = $this->ffi->swe_houses_ex(
            $jdUt,
            $this->conFile ? self::SWIEPH : self::MOSEPH,
            $lat,
            $lon,
            ord($codice),
            $cusps,
            $ascmc,
        );

        $cuspidi = [];
        for ($i = 1; $i <= 12; $i++) {
            $cuspidi[] = $cusps[$i];
        }

        return [
            'cuspidi'       => $cuspidi,
            'asc'           => $ascmc[0],
            'mc'            => $ascmc[1],
            'armc'          => $ascmc[2],
            'vertex'        => $ascmc[3],
            'equatoriale'   => $ascmc[4],
            'coasc_koch'    => $ascmc[5],
            'polascensione' => $ascmc[7],
            // swe_houses_ex torna ERR quando il sistema chiesto non e' calcolabile
            // a quella latitudine e ha dovuto ripiegare.
            'degenere'      => $r < 0,
        ];
    }

    /** @return array{lon:float,lat:float,dist:float,nome:string}|null */
    public function stellaFissa(string $nome, float $jdUt): ?array
    {
        $buf = FFI::new('char[64]');
        FFI::memcpy($buf, $nome, min(strlen($nome), 63));

        $xx   = FFI::new('double[6]');
        $serr = FFI::new('char[256]');

        $r = $this->ffi->swe_fixstar2_ut(
            FFI::cast('char *', $buf),
            $jdUt,
            $this->bandieraBase(),
            $xx,
            FFI::cast('char *', $serr),
        );

        if ($r < 0) {
            return null;
        }

        return ['lon' => $xx[0], 'lat' => $xx[1], 'dist' => $xx[2], 'nome' => FFI::string($buf)];
    }

    /**
     * Fenomeni: fase, illuminazione, diametro apparente, elongazione, magnitudine.
     *
     * @return array{angolo_fase:float,fase:float,elongazione:float,diametro:float,magnitudine:float}
     */
    public function fenomeni(float $jdUt, int $ipl): array
    {
        $attr = FFI::new('double[20]');
        $serr = FFI::new('char[256]');

        $this->ffi->swe_pheno_ut($jdUt, $ipl, $this->bandieraBase(), $attr, FFI::cast('char *', $serr));

        return [
            'angolo_fase'  => $attr[0],
            'fase'         => $attr[1],
            'elongazione'  => $attr[2],
            'diametro'     => $attr[3],
            'magnitudine'  => $attr[4],
        ];
    }

    /**
     * Istante di levata, tramonto o culminazione, in giorno giuliano UT.
     * Null quando l'evento non avviene (sole di mezzanotte, notte polare).
     */
    public function levataTramonto(float $jdUt, int $ipl, int $quale, float $lat, float $lon, float $alt): ?float
    {
        $geopos = FFI::new('double[3]');
        $geopos[0] = $lon; $geopos[1] = $lat; $geopos[2] = $alt;

        $tret = FFI::new('double[10]');
        $serr = FFI::new('char[256]');
        $nome = FFI::new('char[1]');

        $r = $this->ffi->swe_rise_trans(
            $jdUt,
            $ipl,
            FFI::cast('char *', $nome),
            $this->conFile ? self::SWIEPH : self::MOSEPH,
            $quale,
            $geopos,
            1013.25,
            15.0,
            $tret,
            FFI::cast('char *', $serr),
        );

        return $r < 0 || $tret[0] <= 0.0 ? null : $tret[0];
    }

    /**
     * Da coordinate eclittiche a azimut e altezza sull'orizzonte del luogo.
     *
     * L'azimut e' quello della convenzione astronomica riportato a nord = 0,
     * che e' quella che si usa guardando il cielo.
     *
     * @return array{azimut:float,altezza_vera:float,altezza_apparente:float}
     */
    public function orizzonte(float $jdUt, float $lonEcl, float $latEcl, float $dist, float $lat, float $lon, float $alt): array
    {
        $geopos = FFI::new('double[3]');
        $geopos[0] = $lon; $geopos[1] = $lat; $geopos[2] = $alt;

        $xin = FFI::new('double[3]');
        $xin[0] = $lonEcl; $xin[1] = $latEcl; $xin[2] = $dist;

        $xaz = FFI::new('double[3]');

        $this->ffi->swe_azalt($jdUt, self::ECL_A_ORIZZONTE, $geopos, 1013.25, 15.0, $xin, $xaz);

        return [
            'azimut'            => Corpi::norma($xaz[0] + 180.0),
            'altezza_vera'      => $xaz[1],
            'altezza_apparente' => $xaz[2],
        ];
    }
}
