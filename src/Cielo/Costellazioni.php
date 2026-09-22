<?php

declare(strict_types=1);

namespace App\Cielo;

/**
 * Le ottantotto costellazioni riconosciute dall'Unione Astronomica
 * Internazionale, con nome italiano, latino e genitivo.
 *
 * Scritte qui e non importate: sono ottantotto, non cambiano dal 1922, e i
 * nomi italiani nei dataset stranieri o mancano o sono approssimativi. Il
 * genitivo serve a nominare le stelle alla maniera di Bayer — «Alpha Centauri»
 * e' «l'alfa DEL Centauro».
 *
 * @return array<string,array{0:string,1:string,2:string}>
 */
final class Costellazioni
{
    /** @return array<string,array{0:string,1:string,2:string}> abbr => [italiano, latino, genitivo] */
    public static function elenco(): array
    {
        return [
            'And' => ['Andromeda', 'Andromeda', 'Andromedae'],
            'Ant' => ['Macchina Pneumatica', 'Antlia', 'Antliae'],
            'Aps' => ['Uccello del Paradiso', 'Apus', 'Apodis'],
            'Aqr' => ['Acquario', 'Aquarius', 'Aquarii'],
            'Aql' => ['Aquila', 'Aquila', 'Aquilae'],
            'Ara' => ['Altare', 'Ara', 'Arae'],
            'Ari' => ['Ariete', 'Aries', 'Arietis'],
            'Aur' => ['Auriga', 'Auriga', 'Aurigae'],
            'Boo' => ['Boote', 'Bootes', 'Bootis'],
            'Cae' => ['Bulino', 'Caelum', 'Caeli'],
            'Cam' => ['Giraffa', 'Camelopardalis', 'Camelopardalis'],
            'Cnc' => ['Cancro', 'Cancer', 'Cancri'],
            'CVn' => ['Cani da Caccia', 'Canes Venatici', 'Canum Venaticorum'],
            'CMa' => ['Cane Maggiore', 'Canis Major', 'Canis Majoris'],
            'CMi' => ['Cane Minore', 'Canis Minor', 'Canis Minoris'],
            'Cap' => ['Capricorno', 'Capricornus', 'Capricorni'],
            'Car' => ['Carena', 'Carina', 'Carinae'],
            'Cas' => ['Cassiopea', 'Cassiopeia', 'Cassiopeiae'],
            'Cen' => ['Centauro', 'Centaurus', 'Centauri'],
            'Cep' => ['Cefeo', 'Cepheus', 'Cephei'],
            'Cet' => ['Balena', 'Cetus', 'Ceti'],
            'Cha' => ['Camaleonte', 'Chamaeleon', 'Chamaeleontis'],
            'Cir' => ['Compasso', 'Circinus', 'Circini'],
            'Col' => ['Colomba', 'Columba', 'Columbae'],
            'Com' => ['Chioma di Berenice', 'Coma Berenices', 'Comae Berenices'],
            'CrA' => ['Corona Australe', 'Corona Australis', 'Coronae Australis'],
            'CrB' => ['Corona Boreale', 'Corona Borealis', 'Coronae Borealis'],
            'Crv' => ['Corvo', 'Corvus', 'Corvi'],
            'Crt' => ['Cratere', 'Crater', 'Crateris'],
            'Cru' => ['Croce del Sud', 'Crux', 'Crucis'],
            'Cyg' => ['Cigno', 'Cygnus', 'Cygni'],
            'Del' => ['Delfino', 'Delphinus', 'Delphini'],
            'Dor' => ['Dorado', 'Dorado', 'Doradus'],
            'Dra' => ['Dragone', 'Draco', 'Draconis'],
            'Equ' => ['Cavallino', 'Equuleus', 'Equulei'],
            'Eri' => ['Eridano', 'Eridanus', 'Eridani'],
            'For' => ['Fornace', 'Fornax', 'Fornacis'],
            'Gem' => ['Gemelli', 'Gemini', 'Geminorum'],
            'Gru' => ['Gru', 'Grus', 'Gruis'],
            'Her' => ['Ercole', 'Hercules', 'Herculis'],
            'Hor' => ['Orologio', 'Horologium', 'Horologii'],
            'Hya' => ['Idra', 'Hydra', 'Hydrae'],
            'Hyi' => ['Idra Maschio', 'Hydrus', 'Hydri'],
            'Ind' => ['Indiano', 'Indus', 'Indi'],
            'Lac' => ['Lucertola', 'Lacerta', 'Lacertae'],
            'Leo' => ['Leone', 'Leo', 'Leonis'],
            'LMi' => ['Leone Minore', 'Leo Minor', 'Leonis Minoris'],
            'Lep' => ['Lepre', 'Lepus', 'Leporis'],
            'Lib' => ['Bilancia', 'Libra', 'Librae'],
            'Lup' => ['Lupo', 'Lupus', 'Lupi'],
            'Lyn' => ['Lince', 'Lynx', 'Lyncis'],
            'Lyr' => ['Lira', 'Lyra', 'Lyrae'],
            'Men' => ['Mensa', 'Mensa', 'Mensae'],
            'Mic' => ['Microscopio', 'Microscopium', 'Microscopii'],
            'Mon' => ['Unicorno', 'Monoceros', 'Monocerotis'],
            'Mus' => ['Mosca', 'Musca', 'Muscae'],
            'Nor' => ['Squadra', 'Norma', 'Normae'],
            'Oct' => ['Ottante', 'Octans', 'Octantis'],
            'Oph' => ['Ofiuco', 'Ophiuchus', 'Ophiuchi'],
            'Ori' => ['Orione', 'Orion', 'Orionis'],
            'Pav' => ['Pavone', 'Pavo', 'Pavonis'],
            'Peg' => ['Pegaso', 'Pegasus', 'Pegasi'],
            'Per' => ['Perseo', 'Perseus', 'Persei'],
            'Phe' => ['Fenice', 'Phoenix', 'Phoenicis'],
            'Pic' => ['Pittore', 'Pictor', 'Pictoris'],
            'Psc' => ['Pesci', 'Pisces', 'Piscium'],
            'PsA' => ['Pesce Australe', 'Piscis Austrinus', 'Piscis Austrini'],
            'Pup' => ['Poppa', 'Puppis', 'Puppis'],
            'Pyx' => ['Bussola', 'Pyxis', 'Pyxidis'],
            'Ret' => ['Reticolo', 'Reticulum', 'Reticuli'],
            'Sge' => ['Freccia', 'Sagitta', 'Sagittae'],
            'Sgr' => ['Sagittario', 'Sagittarius', 'Sagittarii'],
            'Sco' => ['Scorpione', 'Scorpius', 'Scorpii'],
            'Scl' => ['Scultore', 'Sculptor', 'Sculptoris'],
            'Sct' => ['Scudo', 'Scutum', 'Scuti'],
            'Ser' => ['Serpente', 'Serpens', 'Serpentis'],
            'Sex' => ['Sestante', 'Sextans', 'Sextantis'],
            'Tau' => ['Toro', 'Taurus', 'Tauri'],
            'Tel' => ['Telescopio', 'Telescopium', 'Telescopii'],
            'Tri' => ['Triangolo', 'Triangulum', 'Trianguli'],
            'TrA' => ['Triangolo Australe', 'Triangulum Australe', 'Trianguli Australis'],
            'Tuc' => ['Tucano', 'Tucana', 'Tucanae'],
            'UMa' => ['Orsa Maggiore', 'Ursa Major', 'Ursae Majoris'],
            'UMi' => ['Orsa Minore', 'Ursa Minor', 'Ursae Minoris'],
            'Vel' => ['Vele', 'Vela', 'Velorum'],
            'Vir' => ['Vergine', 'Virgo', 'Virginis'],
            'Vol' => ['Pesce Volante', 'Volans', 'Volantis'],
            'Vul' => ['Volpetta', 'Vulpecula', 'Vulpeculae'],
        ];
    }

    /**
     * Nomi italiani delle stelle, dove l'italiano ne ha uno proprio.
     *
     * Sono poche: la gran parte delle stelle porta lo stesso nome arabo o
     * latino in tutte le lingue, e tradurre «Betelgeuse» non avrebbe senso.
     *
     * @return array<string,string>
     */
    public static function nomiItaliani(): array
    {
        return [
            'Polaris'   => 'Stella Polare',
            'Sirius'    => 'Sirio',
            'Arcturus'  => 'Arturo',
            'Procyon'   => 'Procione',
            'Regulus'   => 'Regolo',
            'Pollux'    => 'Polluce',
            'Castor'    => 'Castore',
            'Alcyone'   => 'Alcione',
            'Denebola'  => 'Denebola',
            'Capella'   => 'Capella',
            'Achernar'  => 'Achernar',
            'Canopus'   => 'Canopo',
        ];
    }
}
