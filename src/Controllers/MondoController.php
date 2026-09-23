<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Astro\Corpi;
use App\Astro\Motore;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;
use App\Corpus\Mondana;
use App\Mondo\Cicli;
use App\Mondo\Mondo;
use App\Support\Telemetria;
use App\Support\TroppeRichieste;

/**
 * L'astrologia mondiale: il cielo che vale per tutti.
 *
 * Quattro pagine e una carta. L'anno, con gli ingressi del Sole nei segni
 * cardinali e le lunazioni; le eclissi; i cicli dei pianeti lenti con
 * l'indice di Barbault; e la carta di un istante qualunque di queste, eretta
 * per una capitale.
 *
 * I calcoli stanno in cache su file per sempre: il cielo di un anno non
 * cambia. Il motore lavora solo la prima volta che qualcuno chiede un anno o
 * un intervallo mai chiesto prima.
 */
final class MondoController
{
    /** Quanti anni al massimo in una pagina di eclissi. */
    private const ANNI_ECLISSI = 30;

    /** Le carte mondiali che la pagina della carta sa leggere. */
    private const TIPI_CARTA = ['ingresso', 'novilunio', 'plenilunio', 'eclissi', 'congiunzione'];

    /** GET /mondo */
    public function indice(Request $r): Response
    {
        $anno = (int) gmdate('Y');
        $ora = Mondo::jdOra();
        $luogo = Mondo::luogo('roma');

        try {
            $motore = new Motore();
            $annata = $motore->mondo('anno', ['anno' => $anno]);
            $eclissi = $motore->mondo('eclissi', ['jd_da' => Mondo::jdAnno($anno), 'jd_a' => Mondo::jdAnno($anno + 3)]);
            $cicli = $this->datiCicli($motore);
        } catch (TroppeRichieste) {
            return $this->occupato();
        } catch (\Throwable $e) {
            return $this->guasto($e);
        }

        $prossima = static fn (array $elenco): ?array => array_values(array_filter($elenco, static fn (array $x): bool => $x['jd'] > $ora))[0] ?? null;

        Telemetria::evento('mondo');

        return Response::html(Vista::pagina('mondo', [
            'titolo'   => 'Il mondo',
            'sezione'  => 'mondo',
            'luogo'    => $luogo,
            'anno'     => $anno,
            'ingresso' => $prossima($annata['ingressi']) ?? null,
            'lunazione'=> $prossima($annata['lunazioni']) ?? null,
            'eclissi'  => $prossima($eclissi['eclissi']) ?? null,
            'indice'   => Cicli::valore($cicli['indice'], $ora),
            'media'    => Cicli::media($cicli['indice']),
            'tendenza' => (Cicli::valore($cicli['indice'], $ora + 365.25) ?? 0) - (Cicli::valore($cicli['indice'], $ora) ?? 0),
        ]));
    }

    /** GET /mondo/anno */
    public function anno(Request $r): Response
    {
        $anno = $this->annoChiesto($r->query('anno'));
        $luogo = Mondo::luogo($r->query('luogo'), $r->query('altrove'));

        try {
            $motore = new Motore();
            $annata = $motore->mondo('anno', ['anno' => $anno]);
            $eclissi = $motore->mondo('eclissi', ['jd_da' => Mondo::jdAnno($anno), 'jd_a' => Mondo::jdAnno($anno + 1)]);
        } catch (TroppeRichieste) {
            return $this->occupato();
        } catch (\Throwable $e) {
            return $this->guasto($e);
        }

        // Una lunazione che e' anche un'eclissi lo dice: e' la stessa sizigia,
        // col massimo entro poche ore.
        $lunazioni = $annata['lunazioni'];
        foreach ($lunazioni as $i => $l) {
            foreach ($eclissi['eclissi'] as $e) {
                if (abs($e['jd'] - $l['jd']) < 0.3) {
                    $lunazioni[$i]['eclissi'] = $e;
                }
            }
        }

        Telemetria::evento('mondo_anno', (string) $anno, $luogo['nome']);

        return Response::html(Vista::pagina('mondo-anno', [
            'titolo'    => 'L\'anno ' . $anno,
            'sezione'   => 'mondo',
            'anno'      => $anno,
            'luogo'     => $luogo,
            'ingressi'  => $annata['ingressi'],
            'lunazioni' => $lunazioni,
        ]));
    }

    /** GET /mondo/eclissi */
    public function eclissi(Request $r): Response
    {
        $da = $this->annoChiesto($r->query('da') ?? (string) (intdiv((int) gmdate('Y'), 10) * 10));
        $a  = $this->annoChiesto($r->query('a') ?? (string) ($da + 9));
        if ($a < $da) {
            [$da, $a] = [$a, $da];
        }
        $a = min($a, $da + self::ANNI_ECLISSI - 1);
        $luogo = Mondo::luogo($r->query('luogo'), $r->query('altrove'));

        try {
            $elenco = (new Motore())->mondo('eclissi', [
                'jd_da' => Mondo::jdAnno($da), 'jd_a' => Mondo::jdAnno($a + 1),
                'lat' => $luogo['lat'], 'lon' => $luogo['lon'], 'alt' => $luogo['alt'],
            ])['eclissi'];
        } catch (TroppeRichieste) {
            return $this->occupato();
        } catch (\Throwable $e) {
            return $this->guasto($e);
        }

        foreach ($elenco as $i => $e) {
            $elenco[$i]['colpi'] = Mondo::colpi((float) $e['lon_eclittica']);
        }

        Telemetria::evento('mondo_eclissi', "{$da}-{$a}", $luogo['nome']);

        return Response::html(Vista::pagina('mondo-eclissi', [
            'titolo'  => 'Eclissi dal ' . $da . ' al ' . $a,
            'sezione' => 'mondo',
            'da'      => $da,
            'a'       => $a,
            'luogo'   => $luogo,
            'elenco'  => $elenco,
        ]));
    }

    /** GET /mondo/cicli */
    public function cicli(Request $r): Response
    {
        try {
            $cicli = $this->datiCicli(new Motore());
        } catch (TroppeRichieste) {
            return $this->occupato();
        } catch (\Throwable $e) {
            return $this->guasto($e);
        }

        $passaggi = [];
        foreach (array_keys(Cicli::COPPIE) as $coppia) {
            $passaggi[$coppia] = Cicli::passaggi($cicli['congiunzioni'], $coppia);
            foreach ($passaggi[$coppia] as $i => $p) {
                $passaggi[$coppia][$i]['colpi'] = Mondo::colpi($p['lon']);
            }
        }

        Telemetria::evento('mondo_cicli');

        return Response::html(Vista::pagina('mondo-cicli', [
            'titolo'   => 'I cicli dei pianeti lenti',
            'sezione'  => 'mondo',
            'indice'   => $cicli['indice'],
            'passaggi' => $passaggi,
            'eventi'   => $this->eventiArchivio(),
            'oggi'     => Mondo::jdOra(),
            'luogo'    => Mondo::luogo('roma'),
        ]));
    }

    /**
     * GET /mondo/carta — la carta di un istante mondiale, per un luogo.
     *
     * L'istante arriva come giorno giuliano: lo ha calcolato il motore, e le
     * pagine lo passano cosi' com'e'. Si accetta solo dentro le effemeridi.
     */
    public function carta(Request $r): Response
    {
        $jd = (float) ($r->query('jd') ?? 0);
        $tipo = in_array($r->query('tipo'), self::TIPI_CARTA, true) ? (string) $r->query('tipo') : 'ingresso';
        if (!is_finite($jd) || $jd < Mondo::jdAnno(Mondo::ANNO_MIN) || $jd >= Mondo::jdAnno(Mondo::ANNO_MAX + 1)) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Istante fuori scala', 'stato' => 404,
                'messaggio' => 'Le effemeridi del portale vanno dal ' . Mondo::ANNO_MIN . ' al ' . Mondo::ANNO_MAX . '.',
            ]), 404);
        }
        $luogo = Mondo::luogo($r->query('luogo'), $r->query('altrove'));

        try {
            $tema = Mondo::tema($jd, $luogo);
        } catch (TroppeRichieste) {
            return $this->occupato();
        } catch (\Throwable $e) {
            registro('carta mondiale fallita: ' . $e->getMessage(), 'error');
            return Response::html(Vista::pagina('errors/generico', [
                'titolo' => 'Il motore non ha risposto', 'stato' => 503,
                'messaggio' => 'Riprova fra poco.',
            ]), 503);
        }

        // Il titolo si compone da valori in elenco chiuso, mai da testo libero:
        // un indirizzo del portale non deve poter portare un titolo inventato.
        $segni = Corpi::segni();
        $corpo = $r->query('corpo') === 'luna' ? 'luna' : 'sole';
        $genere = in_array($r->query('genere'), ['totale', 'anulare', 'ibrida', 'parziale', 'di penombra'], true)
            ? (string) $r->query('genere') : '';
        $coppia = isset(Cicli::COPPIE[(string) $r->query('coppia')]) ? (string) $r->query('coppia') : '';
        $titolo = match ($tipo) {
            'ingresso'     => 'Ingresso del Sole in ' . $segni[(int) floor(((float) $tema['corpi']['sole']['lon'] + 0.5) / 30) % 12]['nome'],
            'novilunio'    => 'Novilunio in ' . $segni[(int) $tema['corpi']['luna']['segno']]['nome'],
            'plenilunio'   => 'Plenilunio in ' . $segni[(int) $tema['corpi']['luna']['segno']]['nome'],
            'eclissi'      => trim('Eclissi ' . $genere) . ' di ' . ($corpo === 'luna' ? 'Luna' : 'Sole'),
            default        => $coppia !== '' ? 'Congiunzione di ' . Cicli::COPPIE[$coppia]['nome'] : 'Congiunzione',
        };

        return Response::html(Vista::pagina('mondo-carta', [
            'titolo'  => $titolo . ' — ' . $luogo['nome'],
            'nome'    => $titolo,
            'sezione' => 'mondo',
            'tipo'    => $tipo,
            'jd'      => $jd,
            'luogo'   => $luogo,
            'tema'    => $tema,
            'lettura' => Mondana::monta($tema, $tipo),
            'parametri' => array_filter(['jd' => (string) $jd, 'tipo' => $tipo, 'corpo' => $tipo === 'eclissi' ? $corpo : '',
                                         'genere' => $genere, 'coppia' => $coppia], static fn (string $v): bool => $v !== ''),
            'colpi'   => Mondo::colpi($this->gradoChiave($tema, $tipo, $tipo === 'congiunzione' ? explode('-', $coppia)[0] : $corpo)),
        ]))->conIntestazione('X-Robots-Tag', 'noindex');
    }

    /**
     * Il grado che «colpisce» le carte dell'archivio: la Luna di un plenilunio
     * o di un'eclissi di Luna, il pianeta di una congiunzione, il Sole altrimenti.
     *
     * @param array<string,mixed> $tema
     */
    private function gradoChiave(array $tema, string $tipo, string $corpo): float
    {
        $chiave = match (true) {
            $tipo === 'plenilunio'                        => 'luna',
            $tipo === 'eclissi' && $corpo === 'luna'      => 'luna',
            $tipo === 'congiunzione' && isset($tema['corpi'][$corpo]) => $corpo,
            default                                       => 'sole',
        };

        return (float) $tema['corpi'][$chiave]['lon'];
    }

    /** @return array{indice:list<array{0:float,1:float}>,congiunzioni:list<array<string,mixed>>} */
    private function datiCicli(Motore $motore): array
    {
        return $motore->mondo('cicli', [
            'jd_da' => Mondo::jdAnno(Mondo::ANNO_MIN), 'jd_a' => Mondo::jdAnno(Mondo::ANNO_MAX + 1), 'passo' => 10,
        ]);
    }

    /** @return list<array{nome:string,slug:string,jd:float,tipo:string}> */
    private function eventiArchivio(): array
    {
        return array_map(static fn (array $r): array => [
            'nome' => (string) $r['nome'], 'slug' => (string) $r['slug'], 'tipo' => (string) $r['tipo'],
            'jd' => (float) $r['jd'],
        ], Database::righe(
            "SELECT a.nome, a.slug, a.tipo, JSON_EXTRACT(c.esito, '$.tempo.jd_ut') AS jd
               FROM archivio a JOIN calcoli c ON c.id = a.calcolo_id
              WHERE a.pubblicata = 1 AND a.tipo IN ('evento', 'nazione')
              ORDER BY jd",
        ));
    }

    private function annoChiesto(mixed $valore): int
    {
        $anno = (int) $valore;

        return $anno < Mondo::ANNO_MIN || $anno > Mondo::ANNO_MAX ? (int) gmdate('Y') : $anno;
    }

    private function guasto(\Throwable $e): Response
    {
        registro('calcolo mondiale fallito: ' . $e->getMessage(), 'error');

        return Response::html(Vista::pagina('errors/generico', [
            'titolo' => 'Il motore non ha risposto', 'stato' => 503,
            'messaggio' => 'Il motore di calcolo non ha risposto. Riprova fra poco.',
        ]), 503);
    }

    private function occupato(): Response
    {
        return Response::html(Vista::pagina('errors/generico', [
            'titolo' => 'Troppe richieste', 'stato' => 429,
            'messaggio' => 'Troppe richieste di calcolo da questo indirizzo. Riprova fra un minuto.',
        ]), 429);
    }
}
