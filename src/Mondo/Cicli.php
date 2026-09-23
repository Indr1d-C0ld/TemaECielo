<?php

declare(strict_types=1);

namespace App\Mondo;

use App\Astro\Corpi;

/**
 * I cicli dei pianeti lenti, come li ha letti l'astrologia mondiale del
 * Novecento: Andre' Barbault soprattutto («Les astres et l'histoire», 1967),
 * e prima di lui Henri Gouchon e Reinhold Ebertin.
 *
 * Un ciclo comincia a ogni congiunzione di due lenti e dura fino alla
 * successiva. La tradizione lo legge come il seme di un'epoca: cio' che nasce
 * sotto una congiunzione si sviluppa per tutto il ciclo.
 */
final class Cicli
{
    /**
     * Le coppie che si mostrano, dalla piu' rapida alla piu' lenta.
     *
     * @var array<string,array{nome:string,anni:string,tema:string}>
     */
    public const COPPIE = [
        'giove-saturno' => [
            'nome' => 'Giove e Saturno', 'anni' => '20 anni',
            'tema' => 'Il ciclo della vita sociale e politica: l\'espansione e il limite, chi cresce e chi governa. '
                . 'Le sue congiunzioni restano per circa due secoli nello stesso elemento, poi «mutano»: '
                . 'la tradizione medievale ci datava il cambio delle epoche.',
        ],
        'saturno-urano' => [
            'nome' => 'Saturno e Urano', 'anni' => '45 anni',
            'tema' => 'L\'ordine e la rottura: le istituzioni messe alla prova dalle riforme, la conservazione '
                . 'contro il cambiamento.',
        ],
        'saturno-nettuno' => [
            'nome' => 'Saturno e Nettuno', 'anni' => '36 anni',
            'tema' => 'Le strutture e gli ideali: Barbault lo legava alla storia del socialismo, dal 1917 '
                . 'della rivoluzione russa al 1989 del Muro.',
        ],
        'saturno-plutone' => [
            'nome' => 'Saturno e Plutone', 'anni' => '33 anni',
            'tema' => 'Il ciclo delle crisi del potere, il piu\' studiato: le congiunzioni del 1914, del 1947, '
                . 'del 1982 e del 2020 cadono su guerre, divisioni del mondo e fratture globali.',
        ],
        'urano-nettuno' => [
            'nome' => 'Urano e Nettuno', 'anni' => '171 anni',
            'tema' => 'Il ciclo lungo delle idee collettive e delle tecniche che le rendono possibili.',
        ],
        'urano-plutone' => [
            'nome' => 'Urano e Plutone', 'anni' => '127 anni',
            'tema' => 'Il ciclo delle rivoluzioni: la congiunzione del 1850 segue il 1848 europeo, quella del '
                . '1965-66 accompagna gli anni della contestazione.',
        ],
        'nettuno-plutone' => [
            'nome' => 'Nettuno e Plutone', 'anni' => '492 anni',
            'tema' => 'Il ciclo di civilta\'. L\'ultima congiunzione, nel 1891-92 in Gemelli, apre per molti '
                . 'autori il mondo moderno; la prossima cade nel 2385.',
        ],
    ];

    /** Due passaggi della stessa coppia piu' vicini di cosi' sono la stessa congiunzione, tripla. */
    private const GIORNI_TRIPLA = 500.0;

    /** @var array<string,string> */
    public const ELEMENTI = ['fuoco' => 'fuoco', 'terra' => 'terra', 'aria' => 'aria', 'acqua' => 'acqua'];

    /**
     * Le congiunzioni della coppia, con i passaggi tripli raccolti in uno.
     *
     * @param list<array{a:string,b:string,jd:float,lon:float,segno:int}> $congiunzioni
     * @return list<array{jd:float,lon:float,segno:int,elemento:string,passaggi:list<float>,mutazione:bool}>
     */
    public static function passaggi(array $congiunzioni, string $coppia): array
    {
        [$a, $b] = explode('-', $coppia);
        $segni = Corpi::segni();
        $fuori = [];
        foreach ($congiunzioni as $c) {
            if ($c['a'] !== $a || $c['b'] !== $b) {
                continue;
            }
            $ultimo = array_key_last($fuori);
            if ($ultimo !== null && $c['jd'] - end($fuori[$ultimo]['passaggi']) < self::GIORNI_TRIPLA) {
                $fuori[$ultimo]['passaggi'][] = (float) $c['jd'];
                continue;
            }
            $fuori[] = [
                'jd' => (float) $c['jd'], 'lon' => (float) $c['lon'], 'segno' => (int) $c['segno'],
                'elemento' => (string) $segni[(int) $c['segno']]['elemento'],
                'passaggi' => [(float) $c['jd']], 'mutazione' => false,
            ];
        }
        // Una mutazione e' il primo passaggio in un elemento nuovo.
        foreach ($fuori as $i => $p) {
            $fuori[$i]['mutazione'] = $i > 0 && $p['elemento'] !== $fuori[$i - 1]['elemento'];
        }

        return $fuori;
    }

    /**
     * I minimi dell'indice: il punto piu' basso in una finestra di dieci anni
     * da una parte e dall'altra. Sono gli anni che Barbault indicava come
     * critici.
     *
     * @param list<array{0:float,1:float}> $indice
     * @return list<array{0:float,1:float}>
     */
    public static function minimi(array $indice, float $anniFinestra = 10.0): array
    {
        $finestra = (int) round($anniFinestra * 365.25 / max(1.0, ($indice[1][0] ?? 30.0) - ($indice[0][0] ?? 0.0)));
        $fuori = [];
        $n = count($indice);
        for ($i = 0; $i < $n; $i++) {
            $v = $indice[$i][1];
            $minimo = true;
            for ($j = max(0, $i - $finestra); $j <= min($n - 1, $i + $finestra); $j++) {
                if ($indice[$j][1] < $v || ($indice[$j][1] === $v && $j < $i)) {
                    $minimo = false;
                    break;
                }
            }
            if ($minimo && $i > 0 && $i < $n - 1) {
                $fuori[] = $indice[$i];
            }
        }

        return $fuori;
    }

    /** @param list<array{0:float,1:float}> $indice */
    public static function media(array $indice): float
    {
        return $indice === [] ? 0.0 : array_sum(array_column($indice, 1)) / count($indice);
    }

    /**
     * Il valore dell'indice a un istante, per interpolazione fra i due campioni vicini.
     *
     * @param list<array{0:float,1:float}> $indice
     */
    public static function valore(array $indice, float $jd): ?float
    {
        foreach ($indice as $i => [$t, $v]) {
            if ($t >= $jd) {
                if ($i === 0) {
                    return $v;
                }
                [$t0, $v0] = $indice[$i - 1];
                return $v0 + ($v - $v0) * ($jd - $t0) / max(1e-9, $t - $t0);
            }
        }

        return null;
    }
}
