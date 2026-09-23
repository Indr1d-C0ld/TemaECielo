<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Astro\Corpi;
use App\Astro\Motore;
use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;
use App\Grafica\VoltaCeleste;
use App\Luogo\Gazetteer;
use App\Luogo\Tempo;
use App\Support\Telemetria;

/**
 * Il cielo, da un luogo e in un istante qualunque.
 *
 * Due pagine distinte perche' rispondono a due domande diverse: «che cosa si
 * vede guardando in alto» e «dove stanno i pianeti in questo momento».
 *
 * Luogo e istante arrivano dalla stringa di ricerca, e non dalla sessione: cosi'
 * ogni cielo ha un indirizzo proprio, che si puo' salvare e mandare a qualcuno,
 * e i pulsanti di scorrimento del tempo sono semplici collegamenti che
 * funzionano anche senza JavaScript.
 */
final class CieloController
{
    /** Roma, quando non si sa da dove si guarda. */
    private const PREDEFINITO = ['nome' => 'Roma', 'lat' => 41.89193, 'lon' => 12.51133, 'alt' => 20, 'id' => 3169070];

    /**
     * L'arco di tempo navigabile.
     *
     * I file `swe-basic-data` coprono dal 1800 al 2399: chiedere fuori da li'
     * non da' un errore utile, da' un cielo sbagliato o vuoto. Meglio dirlo.
     */
    private const ANNO_MIN = 1800;
    private const ANNO_MAX = 2399;

    /** GET /cielo */
    public function cielo(Request $r): Response
    {
        $luogo  = $this->luogoDa($r);
        $quando = $this->istanteDa($r, $luogo);
        $tema   = $this->cieloA($luogo, $quando['istante']);

        Telemetria::evento('cielo_visto', $luogo['nome'], $quando['adesso'] ? 'adesso' : 'scelto');

        return Response::html(Vista::pagina('cielo', [
            'titolo'  => 'Il cielo',
            'sezione' => 'cielo',
            'mappa'   => true,
            'cielo'   => true,
            'luogo'   => $luogo,
            'quando'  => $quando,
            'tema'    => $tema,
            'anno_min' => self::ANNO_MIN,
            'anno_max' => self::ANNO_MAX,
        ]));
    }

    /** GET /oggi */
    public function oggi(Request $r): Response
    {
        $luogo  = $this->luogoDa($r);
        $quando = $this->istanteDa($r, $luogo);
        $tema   = $this->cieloA($luogo, $quando['istante']);

        Telemetria::evento('oggi_visto', $luogo['nome']);

        return Response::html(Vista::pagina('oggi', [
            'titolo'      => 'Il cielo di oggi',
            'sezione'     => 'oggi',
            'luogo'       => $luogo,
            'quando'      => $quando,
            'tema'        => $tema,
            'adesso'      => (new \DateTimeImmutable('@' . $quando['istante']))
                                ->setTimezone(new \DateTimeZone($quando['zona'])),
            'retrogradi'  => array_values(array_filter(
                array_keys($tema['corpi']),
                static fn (string $k): bool => ($tema['corpi'][$k]['retrogrado'] ?? false) === true
                    && in_array($k, Corpi::dieci(), true),
            )),
        ]));
    }

    /** GET /cielo/volta.svg — la volta come file autonomo */
    public function volta(Request $r): Response
    {
        $luogo  = $this->luogoDa($r);
        $quando = $this->istanteDa($r, $luogo);
        $tema   = $this->cieloA($luogo, $quando['istante']);

        $nome = sprintf(
            'cielo-%s-%s.svg',
            preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(explode(',', (string) $luogo['nome'])[0])) ?: 'luogo',
            gmdate('Ymd-Hi', $quando['istante']),
        );

        return Response::svg((new VoltaCeleste($tema, true))->disegna())
            ->conIntestazione('Content-Disposition', 'attachment; filename="' . $nome . '"')
            // Un cielo a istante scelto e' immutabile; quello di «adesso» cambia
            // di minuto in minuto e non va conservato a lungo.
            ->conIntestazione('Cache-Control', $quando['adesso']
                ? 'public, max-age=300'
                : 'public, max-age=604800');
    }

    // ------------------------------------------------------------------------

    /**
     * Il cielo da un luogo, in un istante.
     *
     * Si riusa l'operazione «tema» del motore invece di inventarne una nuova:
     * serve esattamente la stessa roba — posizioni, altezze, azimut, fase
     * lunare — e il calcolo delle case, che qui non interessa, costa meno di un
     * millisecondo.
     *
     * @param array<string,mixed> $luogo
     * @return array<string,mixed>
     */
    private function cieloA(array $luogo, int $istante): array
    {
        $c = Tempo::componenti($istante);

        return (new Motore())->tema([
            'anno'         => $c['anno'],
            'mese'         => $c['mese'],
            'giorno'       => $c['giorno'],
            'ora_ut'       => $c['ora_ut'],
            'lat'          => (float) $luogo['lat'],
            'lon'          => (float) $luogo['lon'],
            'alt'          => (int) $luogo['alt'],
            'sistema_case' => 'placido',
        ]);
    }

    /**
     * Quando si guarda.
     *
     * Senza `data` e `ora` e' adesso, arrotondato al minuto — cosi' la cache per
     * impronta funziona anche qui, e sessanta visitatori nello stesso minuto
     * ottengono un solo calcolo invece di sessanta.
     *
     * L'ora si legge nel fuso del LUOGO OSSERVATO, non in quello del visitatore:
     * «le 22:30 a Tokyo» significa le 22:30 a Tokyo anche se chi guarda sta a
     * Milano. E' l'unica lettura sensata per una carta del cielo.
     *
     * @param array<string,mixed> $luogo
     * @return array{istante:int,zona:string,data:string,ora:string,adesso:bool,
     *               offset_testo:string,abbreviazione:string,avviso:?string}
     */
    private function istanteDa(Request $r, array $luogo): array
    {
        $zona = Gazetteer::fusoDi((float) $luogo['lat'], (float) $luogo['lon'])['fuso'];
        if (!Tempo::zonaValida($zona)) {
            $zona = 'UTC';
        }
        $tz = new \DateTimeZone($zona);

        $data = trim((string) ($r->query('data') ?? ''));
        $ora  = trim((string) ($r->query('ora') ?? ''));

        // Nessuna richiesta esplicita: adesso, al minuto.
        if ($data === '' || $ora === '') {
            $now = (new \DateTimeImmutable('now'))->setTimezone($tz);
            $now = $now->setTime((int) $now->format('G'), (int) $now->format('i'), 0);

            return $this->quando($now->getTimestamp(), $tz, true, null);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1
            || preg_match('/^\d{1,2}:\d{2}$/', $ora) !== 1) {
            return $this->quando(time(), $tz, true, 'Data od ora non valide: mostro il cielo di adesso.');
        }

        $anno = (int) substr($data, 0, 4);
        if ($anno < self::ANNO_MIN || $anno > self::ANNO_MAX) {
            return $this->quando(time(), $tz, true, sprintf(
                'Le effemeridi installate coprono dal %d al %d: mostro il cielo di adesso.',
                self::ANNO_MIN,
                self::ANNO_MAX,
            ));
        }

        $esito = Tempo::risolvi($data, $ora . ':00', $zona);

        // Un'ora ambigua o inesistente non e' un errore da fermare: qui non si
        // sta ricostruendo una nascita, si sta guardando in alto. Si sceglie
        // un'interpretazione e la si dichiara — a differenza del modulo di
        // nascita, che si ferma e chiede.
        $avviso = match ($esito['stato'] ?? '') {
            Tempo::AMBIGUO     => 'Quell\'ora e\' esistita due volte quella notte, alla fine dell\'ora legale: mostro la prima.',
            Tempo::INESISTENTE => (string) ($esito['avviso'] ?? 'Quell\'ora non e\' mai esistita li\'.'),
            default            => null,
        };

        if (!isset($esito['istante'])) {
            return $this->quando(time(), $tz, true, (string) ($esito['errore'] ?? 'Istante non risolvibile.'));
        }

        return $this->quando((int) $esito['istante'], $tz, false, $avviso);
    }

    /**
     * @return array{istante:int,zona:string,data:string,ora:string,adesso:bool,
     *               offset_testo:string,abbreviazione:string,avviso:?string}
     */
    private function quando(int $istante, \DateTimeZone $tz, bool $adesso, ?string $avviso): array
    {
        $locale = (new \DateTimeImmutable('@' . $istante))->setTimezone($tz);

        return [
            'istante'       => $istante,
            'zona'          => $tz->getName(),
            'data'          => $locale->format('Y-m-d'),
            'ora'           => $locale->format('H:i'),
            'adesso'        => $adesso,
            'offset_testo'  => Tempo::offsetTesto((int) $locale->format('Z')),
            'abbreviazione' => $locale->format('T'),
            'avviso'        => $avviso,
        ];
    }

    /**
     * Da dove si guarda il cielo.
     *
     * @return array<string,mixed>
     */
    private function luogoDa(Request $r): array
    {
        // Le coordinate esplicite vincono sull'identificativo: se il visitatore
        // ha trascinato il segnaposto, vuole QUEL punto, non il paese vicino
        // che era stato scelto prima.
        $lat = $r->query('lat');
        $lon = $r->query('lon');
        if (is_numeric($lat) && is_numeric($lon) && abs((float) $lat) <= 90 && abs((float) $lon) <= 180) {
            $vicino = Gazetteer::piuVicino((float) $lat, (float) $lon);

            return [
                'nome' => $vicino['nome'] ?? sprintf('%.3f, %.3f', (float) $lat, (float) $lon),
                'lat'  => round((float) $lat, 6), 'lon' => round((float) $lon, 6),
                'alt'  => $vicino['altitudine'] ?? 0, 'id' => $vicino['id'] ?? 0,
            ];
        }

        $id = (int) ($r->query('luogo') ?? 0);
        if ($id > 0) {
            $l = Gazetteer::perId($id);
            if ($l !== null) {
                return [
                    'nome' => $l['nome'] . ($l['contesto'] !== '' ? ', ' . $l['contesto'] : ''),
                    'lat'  => $l['lat'], 'lon' => $l['lon'], 'alt' => $l['altitudine'], 'id' => $l['id'],
                ];
            }
        }

        return self::PREDEFINITO;
    }
}
