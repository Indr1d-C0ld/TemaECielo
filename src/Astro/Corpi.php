<?php

declare(strict_types=1);

namespace App\Astro;

/**
 * Il catalogo astrologico: corpi, segni, dignita', aspetti, sistemi di case.
 *
 * Dati puri, nessun calcolo. Sta tutto qui e non sparso nel codice perche'
 * sono tabelle che si consultano di continuo e che ogni tanto si discutono:
 * gli orbi si cambiano, i termini egizi no.
 */
final class Corpi
{
    // Indici della Swiss Ephemeris.
    public const SOLE = 0;
    public const LUNA = 1;
    public const MERCURIO = 2;
    public const VENERE = 3;
    public const MARTE = 4;
    public const GIOVE = 5;
    public const SATURNO = 6;
    public const URANO = 7;
    public const NETTUNO = 8;
    public const PLUTONE = 9;
    public const NODO_MEDIO = 10;
    public const NODO_VERO = 11;
    public const LILITH_MEDIA = 12;
    public const LILITH_VERA = 13;
    public const CHIRONE = 15;
    public const CERERE = 17;
    public const PALLADE = 18;
    public const GIUNONE = 19;
    public const VESTA = 20;

    /**
     * I corpi calcolabili.
     *
     * `base` = entra in ogni carta e nei bilanci. Gli altri si mostrano ma non
     * pesano sui conteggi, altrimenti dodici corpi «minori» sommergerebbero i
     * sette della tradizione.
     *
     * @return array<string,array{ipl:int,nome:string,glifo:string,tipo:string,base:bool}>
     */
    public static function elenco(): array
    {
        return [
            'sole'      => ['ipl' => self::SOLE,      'nome' => 'Sole',      'glifo' => 'sole',      'tipo' => 'luminare',  'base' => true],
            'luna'      => ['ipl' => self::LUNA,      'nome' => 'Luna',      'glifo' => 'luna',      'tipo' => 'luminare',  'base' => true],
            'mercurio'  => ['ipl' => self::MERCURIO,  'nome' => 'Mercurio',  'glifo' => 'mercurio',  'tipo' => 'pianeta',   'base' => true],
            'venere'    => ['ipl' => self::VENERE,    'nome' => 'Venere',    'glifo' => 'venere',    'tipo' => 'pianeta',   'base' => true],
            'marte'     => ['ipl' => self::MARTE,     'nome' => 'Marte',     'glifo' => 'marte',     'tipo' => 'pianeta',   'base' => true],
            'giove'     => ['ipl' => self::GIOVE,     'nome' => 'Giove',     'glifo' => 'giove',     'tipo' => 'pianeta',   'base' => true],
            'saturno'   => ['ipl' => self::SATURNO,   'nome' => 'Saturno',   'glifo' => 'saturno',   'tipo' => 'pianeta',   'base' => true],
            'urano'     => ['ipl' => self::URANO,     'nome' => 'Urano',     'glifo' => 'urano',     'tipo' => 'pianeta',   'base' => true],
            'nettuno'   => ['ipl' => self::NETTUNO,   'nome' => 'Nettuno',   'glifo' => 'nettuno',   'tipo' => 'pianeta',   'base' => true],
            'plutone'   => ['ipl' => self::PLUTONE,   'nome' => 'Plutone',   'glifo' => 'plutone',   'tipo' => 'pianeta',   'base' => true],
            'nodo'      => ['ipl' => self::NODO_VERO, 'nome' => 'Nodo Nord', 'glifo' => 'nodo-nord', 'tipo' => 'punto',     'base' => false],
            'lilith'    => ['ipl' => self::LILITH_MEDIA, 'nome' => 'Lilith', 'glifo' => 'lilith',    'tipo' => 'punto',     'base' => false],
            'chirone'   => ['ipl' => self::CHIRONE,   'nome' => 'Chirone',   'glifo' => 'chirone',   'tipo' => 'asteroide', 'base' => false],
            'cerere'    => ['ipl' => self::CERERE,    'nome' => 'Cerere',    'glifo' => 'fortuna',   'tipo' => 'asteroide', 'base' => false],
            'pallade'   => ['ipl' => self::PALLADE,   'nome' => 'Pallade',   'glifo' => 'fortuna',   'tipo' => 'asteroide', 'base' => false],
            'giunone'   => ['ipl' => self::GIUNONE,   'nome' => 'Giunone',   'glifo' => 'fortuna',   'tipo' => 'asteroide', 'base' => false],
            'vesta'     => ['ipl' => self::VESTA,     'nome' => 'Vesta',     'glifo' => 'fortuna',   'tipo' => 'asteroide', 'base' => false],
        ];
    }

    /** I dieci che entrano nei bilanci e nella figura planetaria. @return list<string> */
    public static function dieci(): array
    {
        return ['sole', 'luna', 'mercurio', 'venere', 'marte', 'giove', 'saturno', 'urano', 'nettuno', 'plutone'];
    }

    /** I sette visibili a occhio nudo: la carta della tradizione. @return list<string> */
    public static function sette(): array
    {
        return ['sole', 'luna', 'mercurio', 'venere', 'marte', 'giove', 'saturno'];
    }

    /**
     * I dodici segni, con tutto cio' che li qualifica.
     *
     * @return list<array{nome:string,glifo:string,elemento:string,modalita:string,polarita:string,
     *                    domicilio:string,domicilio_moderno:string,esilio:string,
     *                    esaltazione:?array{corpo:string,grado:float},caduta:?string}>
     */
    public static function segni(): array
    {
        return [
            ['nome' => 'Ariete',     'glifo' => 'ariete',     'elemento' => 'fuoco', 'modalita' => 'cardinale', 'polarita' => 'diurna',
             'domicilio' => 'marte',    'domicilio_moderno' => 'marte',    'esilio' => 'venere',
             'esaltazione' => ['corpo' => 'sole', 'grado' => 19.0],  'caduta' => 'saturno'],
            ['nome' => 'Toro',       'glifo' => 'toro',       'elemento' => 'terra', 'modalita' => 'fisso',     'polarita' => 'notturna',
             'domicilio' => 'venere',   'domicilio_moderno' => 'venere',   'esilio' => 'marte',
             'esaltazione' => ['corpo' => 'luna', 'grado' => 3.0],   'caduta' => null],
            ['nome' => 'Gemelli',    'glifo' => 'gemelli',    'elemento' => 'aria',  'modalita' => 'mobile',    'polarita' => 'diurna',
             'domicilio' => 'mercurio', 'domicilio_moderno' => 'mercurio', 'esilio' => 'giove',
             'esaltazione' => null, 'caduta' => null],
            ['nome' => 'Cancro',     'glifo' => 'cancro',     'elemento' => 'acqua', 'modalita' => 'cardinale', 'polarita' => 'notturna',
             'domicilio' => 'luna',     'domicilio_moderno' => 'luna',     'esilio' => 'saturno',
             'esaltazione' => ['corpo' => 'giove', 'grado' => 15.0], 'caduta' => 'marte'],
            ['nome' => 'Leone',      'glifo' => 'leone',      'elemento' => 'fuoco', 'modalita' => 'fisso',     'polarita' => 'diurna',
             'domicilio' => 'sole',     'domicilio_moderno' => 'sole',     'esilio' => 'saturno',
             'esaltazione' => null, 'caduta' => null],
            ['nome' => 'Vergine',    'glifo' => 'vergine',    'elemento' => 'terra', 'modalita' => 'mobile',    'polarita' => 'notturna',
             'domicilio' => 'mercurio', 'domicilio_moderno' => 'mercurio', 'esilio' => 'giove',
             'esaltazione' => ['corpo' => 'mercurio', 'grado' => 15.0], 'caduta' => 'venere'],
            ['nome' => 'Bilancia',   'glifo' => 'bilancia',   'elemento' => 'aria',  'modalita' => 'cardinale', 'polarita' => 'diurna',
             'domicilio' => 'venere',   'domicilio_moderno' => 'venere',   'esilio' => 'marte',
             'esaltazione' => ['corpo' => 'saturno', 'grado' => 21.0], 'caduta' => 'sole'],
            ['nome' => 'Scorpione',  'glifo' => 'scorpione',  'elemento' => 'acqua', 'modalita' => 'fisso',     'polarita' => 'notturna',
             'domicilio' => 'marte',    'domicilio_moderno' => 'plutone',  'esilio' => 'venere',
             'esaltazione' => null, 'caduta' => 'luna'],
            ['nome' => 'Sagittario', 'glifo' => 'sagittario', 'elemento' => 'fuoco', 'modalita' => 'mobile',    'polarita' => 'diurna',
             'domicilio' => 'giove',    'domicilio_moderno' => 'giove',    'esilio' => 'mercurio',
             'esaltazione' => null, 'caduta' => null],
            ['nome' => 'Capricorno', 'glifo' => 'capricorno', 'elemento' => 'terra', 'modalita' => 'cardinale', 'polarita' => 'notturna',
             'domicilio' => 'saturno',  'domicilio_moderno' => 'saturno',  'esilio' => 'luna',
             'esaltazione' => ['corpo' => 'marte', 'grado' => 28.0], 'caduta' => 'giove'],
            ['nome' => 'Acquario',   'glifo' => 'acquario',   'elemento' => 'aria',  'modalita' => 'fisso',     'polarita' => 'diurna',
             'domicilio' => 'saturno',  'domicilio_moderno' => 'urano',    'esilio' => 'sole',
             'esaltazione' => null, 'caduta' => null],
            ['nome' => 'Pesci',      'glifo' => 'pesci',      'elemento' => 'acqua', 'modalita' => 'mobile',    'polarita' => 'notturna',
             'domicilio' => 'giove',    'domicilio_moderno' => 'nettuno',  'esilio' => 'mercurio',
             'esaltazione' => ['corpo' => 'venere', 'grado' => 27.0], 'caduta' => 'mercurio'],
        ];
    }

    /**
     * Triplicita' secondo Doroteo: signore diurno, notturno e partecipante.
     *
     * @return array<string,array{giorno:string,notte:string,partecipante:string}>
     */
    public static function triplicita(): array
    {
        return [
            'fuoco' => ['giorno' => 'sole',    'notte' => 'giove',    'partecipante' => 'saturno'],
            'terra' => ['giorno' => 'venere',  'notte' => 'luna',     'partecipante' => 'marte'],
            'aria'  => ['giorno' => 'saturno', 'notte' => 'mercurio', 'partecipante' => 'giove'],
            'acqua' => ['giorno' => 'venere',  'notte' => 'marte',    'partecipante' => 'luna'],
        ];
    }

    /**
     * Termini egizi: per ogni segno, cinque fasce [signore, grado finale].
     *
     * @return list<list<array{0:string,1:float}>>
     */
    public static function termini(): array
    {
        return [
            [['giove', 6], ['venere', 12], ['mercurio', 20], ['marte', 25], ['saturno', 30]],      // Ariete
            [['venere', 8], ['mercurio', 14], ['giove', 22], ['saturno', 27], ['marte', 30]],      // Toro
            [['mercurio', 6], ['giove', 12], ['venere', 17], ['marte', 24], ['saturno', 30]],      // Gemelli
            [['marte', 7], ['venere', 13], ['mercurio', 19], ['giove', 26], ['saturno', 30]],      // Cancro
            [['giove', 6], ['venere', 11], ['saturno', 18], ['mercurio', 24], ['marte', 30]],      // Leone
            [['mercurio', 7], ['venere', 17], ['giove', 21], ['marte', 28], ['saturno', 30]],      // Vergine
            [['saturno', 6], ['mercurio', 14], ['giove', 21], ['venere', 28], ['marte', 30]],      // Bilancia
            [['marte', 7], ['venere', 11], ['mercurio', 19], ['giove', 24], ['saturno', 30]],      // Scorpione
            [['giove', 12], ['venere', 17], ['mercurio', 21], ['saturno', 26], ['marte', 30]],     // Sagittario
            [['mercurio', 7], ['giove', 14], ['venere', 22], ['saturno', 26], ['marte', 30]],      // Capricorno
            [['mercurio', 7], ['venere', 13], ['giove', 20], ['marte', 25], ['saturno', 30]],      // Acquario
            [['venere', 12], ['giove', 16], ['mercurio', 19], ['marte', 28], ['saturno', 30]],     // Pesci
        ];
    }

    /**
     * I trentasei decani (o facce) in ordine caldeo, dall'Ariete.
     *
     * @return list<string>
     */
    public static function decani(): array
    {
        $caldeo = ['marte', 'sole', 'venere', 'mercurio', 'luna', 'saturno', 'giove'];
        $out = [];
        for ($i = 0; $i < 36; $i++) {
            $out[] = $caldeo[$i % 7];
        }

        return $out;
    }

    /**
     * Aspetti riconosciuti, con angolo, orbe predefinito e natura.
     *
     * L'orbe e' quello «di base»: viene poi allargato per Sole e Luna secondo
     * self::fattoreOrbe(). Tenere un orbe unico per tutti i corpi e' la scelta
     * che fa comparire aspetti inesistenti fra due pianeti lenti.
     *
     * @return array<string,array{angolo:float,orbe:float,natura:string,grado:string,glifo:string,nome:string}>
     */
    public static function aspetti(): array
    {
        return [
            'congiunzione'  => ['angolo' => 0.0,   'orbe' => 8.0, 'natura' => 'neutro',   'grado' => 'maggiore', 'glifo' => 'congiunzione', 'nome' => 'Congiunzione'],
            'opposizione'   => ['angolo' => 180.0, 'orbe' => 8.0, 'natura' => 'tensione', 'grado' => 'maggiore', 'glifo' => 'opposizione',  'nome' => 'Opposizione'],
            'trigono'       => ['angolo' => 120.0, 'orbe' => 7.0, 'natura' => 'armonico', 'grado' => 'maggiore', 'glifo' => 'trigono',      'nome' => 'Trigono'],
            'quadrato'      => ['angolo' => 90.0,  'orbe' => 7.0, 'natura' => 'tensione', 'grado' => 'maggiore', 'glifo' => 'quadrato',     'nome' => 'Quadrato'],
            'sestile'       => ['angolo' => 60.0,  'orbe' => 5.0, 'natura' => 'armonico', 'grado' => 'maggiore', 'glifo' => 'sestile',      'nome' => 'Sestile'],
            'quinconce'     => ['angolo' => 150.0, 'orbe' => 3.0, 'natura' => 'tensione', 'grado' => 'minore',   'glifo' => 'quinconce',    'nome' => 'Quinconce'],
            'semisestile'   => ['angolo' => 30.0,  'orbe' => 2.0, 'natura' => 'armonico', 'grado' => 'minore',   'glifo' => 'quinconce',    'nome' => 'Semisestile'],
            'semiquadrato'  => ['angolo' => 45.0,  'orbe' => 2.0, 'natura' => 'tensione', 'grado' => 'minore',   'glifo' => 'quadrato',     'nome' => 'Semiquadrato'],
            'sesquiquadrato'=> ['angolo' => 135.0, 'orbe' => 2.0, 'natura' => 'tensione', 'grado' => 'minore',   'glifo' => 'quadrato',     'nome' => 'Sesquiquadrato'],
            'quintile'      => ['angolo' => 72.0,  'orbe' => 1.5, 'natura' => 'armonico', 'grado' => 'minore',   'glifo' => 'sestile',      'nome' => 'Quintile'],
            'biquintile'    => ['angolo' => 144.0, 'orbe' => 1.5, 'natura' => 'armonico', 'grado' => 'minore',   'glifo' => 'sestile',      'nome' => 'Biquintile'],
        ];
    }

    /** Quanto si allarga l'orbe secondo i due corpi coinvolti. */
    public static function fattoreOrbe(string $a, string $b): float
    {
        $peso = static fn (string $c): float => match ($c) {
            'sole', 'luna' => 1.0,
            'mercurio', 'venere', 'marte', 'giove', 'saturno' => 0.75,
            'urano', 'nettuno', 'plutone' => 0.65,
            'asc', 'mc' => 1.0,
            default => 0.45,
        };

        return max($peso($a), $peso($b));
    }

    /**
     * Sistemi di case, con la lettera che vuole la Swiss Ephemeris.
     *
     * @return array<string,array{codice:string,nome:string,polare:bool}>
     */
    public static function sistemiCase(): array
    {
        return [
            'placido'      => ['codice' => 'P', 'nome' => 'Placido',           'polare' => false],
            'koch'         => ['codice' => 'K', 'nome' => 'Koch',              'polare' => false],
            'regiomontano' => ['codice' => 'R', 'nome' => 'Regiomontano',      'polare' => false],
            'campano'      => ['codice' => 'C', 'nome' => 'Campano',           'polare' => false],
            'porfirio'     => ['codice' => 'O', 'nome' => 'Porfirio',          'polare' => true],
            'equale'       => ['codice' => 'A', 'nome' => 'Equale',            'polare' => true],
            'equale_mc'    => ['codice' => 'X', 'nome' => 'Equale dal MC',     'polare' => true],
            'segni_interi' => ['codice' => 'W', 'nome' => 'Segni Interi',      'polare' => true],
            'alcabizio'    => ['codice' => 'B', 'nome' => 'Alcabizio',         'polare' => false],
            'topocentrico' => ['codice' => 'T', 'nome' => 'Topocentrico',      'polare' => false],
            'morinus'      => ['codice' => 'M', 'nome' => 'Morinus',           'polare' => true],
        ];
    }

    /** Stelle fisse del canone, col nome che la Swiss Ephemeris riconosce. @return array<string,string> */
    public static function stelleFisse(): array
    {
        return [
            'Aldebaran' => 'Aldebaran', 'Algol' => 'Algol', 'Alcyone' => 'Alcyone',
            'Antares' => 'Antares', 'Arcturus' => 'Arturo', 'Betelgeuse' => 'Betelgeuse',
            'Capella' => 'Capella', 'Castor' => 'Castore', 'Deneb Algedi' => 'Deneb Algedi',
            'Fomalhaut' => 'Fomalhaut', 'Pollux' => 'Polluce', 'Procyon' => 'Procione',
            'Regulus' => 'Regolo', 'Rigel' => 'Rigel', 'Sirius' => 'Sirio',
            'Spica' => 'Spica', 'Vega' => 'Vega', 'Altair' => 'Altair',
            'Bellatrix' => 'Bellatrix', 'Zubenelgenubi' => 'Zubenelgenubi',
        ];
    }

    // -- comodita' -----------------------------------------------------------

    /** Indice di segno (0-11) da una longitudine eclittica. */
    public static function segnoDi(float $lon): int
    {
        return (int) floor(self::norma($lon) / 30.0);
    }

    /** Longitudine riportata in [0, 360). */
    public static function norma(float $g): float
    {
        $g = fmod($g, 360.0);

        return $g < 0 ? $g + 360.0 : $g;
    }

    /** Differenza angolare minima fra due longitudini, in [0, 180]. */
    public static function distanza(float $a, float $b): float
    {
        $d = abs(self::norma($a) - self::norma($b));

        return $d > 180.0 ? 360.0 - $d : $d;
    }

    /** @return array{segno:int,grado:int,primo:int,secondo:int} */
    public static function scomponi(float $lon): array
    {
        $lon   = self::norma($lon);
        $segno = (int) floor($lon / 30.0);
        $resto = $lon - $segno * 30.0;
        $grado = (int) floor($resto);
        $m     = ($resto - $grado) * 60.0;
        $primo = (int) floor($m);
        $secondo = (int) round(($m - $primo) * 60.0);

        if ($secondo === 60) { $secondo = 0; $primo++; }
        if ($primo === 60)   { $primo = 0; $grado++; }

        return ['segno' => $segno, 'grado' => $grado, 'primo' => $primo, 'secondo' => $secondo];
    }

    public static function formatta(float $lon, bool $conSecondi = true): string
    {
        $p = self::scomponi($lon);
        $s = self::segni()[$p['segno']];

        return $conSecondi
            ? sprintf('%d°%02d\'%02d" %s', $p['grado'], $p['primo'], $p['secondo'], $s['nome'])
            : sprintf('%d°%02d\' %s', $p['grado'], $p['primo'], $s['nome']);
    }
}
