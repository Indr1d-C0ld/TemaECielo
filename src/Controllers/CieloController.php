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
use App\Support\Telemetria;

/**
 * Il cielo adesso, e le posizioni del momento.
 *
 * Due pagine distinte perche' rispondono a due domande diverse: «che cosa si
 * vede stanotte guardando in alto» e «dove stanno i pianeti in questo momento».
 */
final class CieloController
{
    /** Roma, quando non si sa da dove si guarda. */
    private const PREDEFINITO = ['nome' => 'Roma', 'lat' => 41.89193, 'lon' => 12.51133, 'alt' => 20, 'id' => 3169070];

    /** GET /cielo */
    public function cielo(Request $r): Response
    {
        $luogo = $this->luogoDa($r);
        $tema  = $this->cieloAdesso($luogo);

        Telemetria::evento('cielo_visto', $luogo['nome']);

        return Response::html(Vista::pagina('cielo', [
            'titolo'  => 'Il cielo',
            'sezione' => 'cielo',
            'mappa'   => false,
            'luogo'   => $luogo,
            'tema'    => $tema,
            'adesso'  => new \DateTimeImmutable('now'),
        ]));
    }

    /** GET /oggi */
    public function oggi(Request $r): Response
    {
        $luogo = $this->luogoDa($r);
        $tema  = $this->cieloAdesso($luogo);

        Telemetria::evento('oggi_visto', $luogo['nome']);

        return Response::html(Vista::pagina('oggi', [
            'titolo'      => 'Il cielo di oggi',
            'sezione'     => 'oggi',
            'luogo'       => $luogo,
            'tema'        => $tema,
            'adesso'      => new \DateTimeImmutable('now'),
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
        $luogo = $this->luogoDa($r);
        $tema  = $this->cieloAdesso($luogo);

        return Response::svg((new VoltaCeleste($tema, true))->disegna())
            ->conIntestazione('Content-Disposition', 'attachment; filename="cielo.svg"')
            // Il cielo cambia di minuto in minuto: questa non e' immutabile
            // come una carta natale, e conservarla a lungo sarebbe sbagliato.
            ->conIntestazione('Cache-Control', 'public, max-age=300');
    }

    // ------------------------------------------------------------------------

    /**
     * Il cielo di adesso da un luogo.
     *
     * Si riusa l'operazione «tema» del motore invece di inventarne una nuova:
     * serve esattamente la stessa roba — posizioni, altezze, azimut, fase
     * lunare — e il calcolo delle case, che qui non interessa, costa meno di un
     * millisecondo.
     *
     * L'istante si arrotonda al minuto: cosi' la cache per impronta funziona
     * anche qui, e sessanta visitatori nello stesso minuto ottengono un solo
     * calcolo invece di sessanta.
     *
     * @param array<string,mixed> $luogo
     * @return array<string,mixed>
     */
    private function cieloAdesso(array $luogo): array
    {
        $ora = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return (new Motore())->tema([
            'anno'         => (int) $ora->format('Y'),
            'mese'         => (int) $ora->format('n'),
            'giorno'       => (int) $ora->format('j'),
            'ora_ut'       => (int) $ora->format('G') + (int) $ora->format('i') / 60.0,
            'lat'          => (float) $luogo['lat'],
            'lon'          => (float) $luogo['lon'],
            'alt'          => (int) $luogo['alt'],
            'sistema_case' => 'placido',
        ]);
    }

    /**
     * Da dove si guarda il cielo.
     *
     * @return array<string,mixed>
     */
    private function luogoDa(Request $r): array
    {
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

        return self::PREDEFINITO;
    }
}
