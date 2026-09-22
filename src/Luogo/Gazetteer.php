<?php

declare(strict_types=1);

namespace App\Luogo;

use App\Core\Database;

/**
 * Ricerca dei luoghi, tutta in casa.
 *
 * Nessuna interrogazione a servizi esterni: mentre qualcuno digita il proprio
 * luogo di nascita, non si spedisce fuori un carattere. In piu' non ci sono
 * quote da rispettare ne' un servizio di terzi che un giorno chiude.
 */
final class Gazetteer
{
    // Tre caratteri, non due. Con due il prefisso pesca decine di migliaia di
    // alias — mezzo secondo di attesa — e per giunta le corrispondenze esatte
    // su traslitterazioni oscure scavalcano le citta' vere: cercando «mi»
    // usciva Istanbul, che fra i suoi nomi antichi ha «Micklagard».
    private const MINIMO = 3;
    private const LIMITE = 12;

    /**
     * Cerca un luogo per nome, con corrispondenza da inizio parola.
     *
     * L'ordinamento mette prima le corrispondenze esatte, poi la popolazione:
     * chi scrive «roma» vuole Roma, non Romano di Lombardia, anche se
     * entrambe cominciano per «roma».
     *
     * @return list<array<string,mixed>>
     */
    public static function cerca(string $query, ?string $paese = null, int $limite = self::LIMITE): array
    {
        $norm = Normalizza::nome($query);
        if (mb_strlen($norm) < self::MINIMO) {
            return [];
        }

        $limite = max(1, min(50, $limite));

        // Si restringe PRIMA sugli alias e solo dopo si unisce ai luoghi.
        // Facendo il contrario, con un prefisso corto come «mi» il database
        // unirebbe decine di migliaia di righe per poi buttarne via quasi
        // tutte.
        $sql = 'SELECT l.id, l.nome, l.nome_geonames, l.paese, l.paese_nome,
                       l.admin1_nome, l.admin2_nome, l.lat, l.lon, l.altitudine,
                       l.popolazione, l.fuso, l.codice,
                       t.trovato, t.esatto
                  FROM (
                        SELECT a.luogo_id,
                               MIN(a.nome) AS trovato,
                               MAX(a.nome_norm = ?) AS esatto,
                               MAX(a.preferito) AS preferito,
                               MAX(a.popolazione) AS pop
                          FROM luoghi_alias a
                         WHERE a.nome_norm LIKE ?
                         GROUP BY a.luogo_id
                         ORDER BY MAX(a.nome_norm = ?) DESC, MAX(a.popolazione) DESC
                         LIMIT 60
                       ) AS t
                  JOIN luoghi l ON l.id = t.luogo_id';

        $parametri = [$norm, $norm . '%', $norm];

        if ($paese !== null && preg_match('/^[A-Za-z]{2}$/', $paese) === 1) {
            $sql .= ' WHERE l.paese = ?';
            $parametri[] = strtoupper($paese);
        }

        // Una corrispondenza esatta conta, ma solo se il luogo esiste davvero:
        // senza il vincolo sulla popolazione un villaggio disabitato che si
        // chiama per caso come la query scavalca una capitale.
        $sql .= ' ORDER BY (t.esatto = 1 AND l.popolazione > 0) DESC,
                           t.esatto DESC,
                           l.popolazione DESC,
                           l.nome ASC
                  LIMIT ' . $limite;

        return array_map(
            static fn (array $r): array => self::vesti($r),
            Database::righe($sql, $parametri),
        );
    }

    /** @return array<string,mixed>|null */
    public static function perId(int $id): ?array
    {
        $r = Database::riga(
            'SELECT id, nome, nome_geonames, paese, paese_nome, admin1_nome, admin2_nome,
                    lat, lon, altitudine, popolazione, fuso, codice
               FROM luoghi WHERE id = ? LIMIT 1',
            [$id],
        );

        return $r === null ? null : self::vesti($r);
    }

    /**
     * Il luogo piu' vicino a un punto, per chi clicca sulla mappa.
     *
     * Si restringe prima a un riquadro di coordinate, che usa gli indici, e
     * solo dentro quel riquadro si calcola la distanza vera. Calcolare la
     * distanza su tutte le righe vorrebbe dire una scansione completa a ogni
     * click.
     *
     * Il riquadro si allarga a tentativi finche' qualcosa non salta fuori: in
     * mezzo all'oceano o nel Sahara il paese abitato piu' vicino puo' essere a
     * centinaia di chilometri.
     *
     * @return array<string,mixed>|null
     */
    public static function piuVicino(float $lat, float $lon): ?array
    {
        // Ci si ferma a cinque gradi, circa cinquecento chilometri. Oltre, il
        // «luogo piu' vicino» non e' piu' una risposta: dire che il punto in
        // mezzo all'Atlantico sta alle Azzorre, novecento chilometri piu' in
        // la', e' peggio che non dire niente — e costava anche piu' di un
        // secondo. Chi chiama sa gia' cosa fare con un null.
        foreach ([0.15, 0.5, 1.5, 5.0] as $raggio) {
            // Un grado di longitudine vale meno di un grado di latitudine, e
            // sempre meno man mano che ci si allontana dall'equatore.
            $fattore = max(0.15, cos(deg2rad($lat)));
            $dLon = $raggio / $fattore;

            // Quale luogo «e'» il punto su cui si e' cliccato?
            //
            // Il piu' vicino in linea d'aria e' la risposta sbagliata: nel
            // centro di Roma il piu' vicino e' sempre un rione — Trevi,
            // Esquilino, Monti — e alla stazione di Tokyo e' un quartiere.
            // Nessuno, indicando quel punto, intende quello.
            //
            // Ne' funziona penalizzare i rioni di un fattore fisso: Roma dista
            // dal centro geometrico 1,4 km e Trevi 0,4, e nessuna costante
            // rimette le cose a posto in ogni caso.
            //
            // Si soppesano invece importanza e distanza:
            //
            //      punteggio = radice(abitanti) / (1 + (km/3)^2)
            //
            // La radice comprime la popolazione, cosi' una metropoli non
            // schiaccia tutto quello che ha intorno; il denominatore cresce col
            // quadrato della distanza, cosi' l'importanza conta solo da vicino.
            // Il risultato: sul centro di Roma esce Roma, ma cliccando su Monza
            // esce Monza e non Milano, che pure e' dodici volte piu' grande e a
            // soli dodici chilometri.
            //
            // La scala di tre chilometri e' stata trovata per prova: con due,
            // alla stazione di Tokyo vinceva il quartiere Chūō per tre punti
            // su duecentoquarantacinque. Un margine cosi' sottile vuol dire che
            // li' la regola e' al limite, e conviene dare piu' peso alla
            // dimensione.
            //
            // Oltre un grado e mezzo di raggio la formula si spegne e decide la
            // sola distanza: a quelle distanze — in mezzo all'oceano, nel
            // deserto — «importanza» non vuol piu' dire niente, e preferire una
            // citta' a millesettecento chilometri invece di un paese a
            // novecento sarebbe solo arbitrario.
            $r = Database::riga(
                'SELECT id, nome, nome_geonames, paese, paese_nome, admin1_nome, admin2_nome,
                        lat, lon, altitudine, popolazione, fuso, codice,
                        (POW(lat - ?, 2) + POW((lon - ?) * ?, 2)) AS scarto
                   FROM luoghi
                  WHERE lat BETWEEN ? AND ? AND lon BETWEEN ? AND ?
                  ORDER BY ' . ($raggio > 1.5
                        ? '(POW(lat - ?, 2) + POW((lon - ?) * ?, 2)) ASC'
                        : 'SQRT(popolazione + 1)
                           / (1 + POW(SQRT(POW(lat - ?, 2) + POW((lon - ?) * ?, 2)) * 37.07, 2))
                           DESC') . '
                  LIMIT 1',
                [
                    $lat, $lon, $fattore,
                    $lat - $raggio, $lat + $raggio, $lon - $dLon, $lon + $dLon,
                    $lat, $lon, $fattore,
                ],
            );

            if ($r !== null) {
                $v = self::vesti($r);
                $v['distanza_km'] = round(self::distanzaKm($lat, $lon, (float) $r['lat'], (float) $r['lon']), 1);

                return $v;
            }
        }

        return null;
    }

    /**
     * Fuso orario di un punto qualsiasi.
     *
     * Si prende quello del luogo abitato piu' vicino. E' un'approssimazione, e
     * va detto: vicino a un confine di fuso puo' sbagliare. Ma per un luogo di
     * NASCITA e' quasi sempre giusto — si nasce dove c'e' gente — ed evita di
     * caricare duecento megabyte di poligoni per un guadagno che riguarda
     * pochi casi.
     *
     * @return array{fuso:string,fonte:string,luogo:?string,distanza_km:?float}
     */
    public static function fusoDi(float $lat, float $lon): array
    {
        $vicino = self::piuVicino($lat, $lon);

        if ($vicino !== null && $vicino['fuso'] !== '') {
            return [
                'fuso'        => $vicino['fuso'],
                'fonte'       => 'luogo_vicino',
                'luogo'       => $vicino['nome'],
                'distanza_km' => $vicino['distanza_km'] ?? null,
            ];
        }

        // Nessun luogo abitato nel raggio cercato: si ripiega sul fuso
        // nautico, quello dei quindici gradi di longitudine per ora.
        $ore = (int) round($lon / 15.0);

        return [
            'fuso'        => sprintf('Etc/GMT%s%d', $ore <= 0 ? '+' : '-', abs($ore)),
            'fonte'       => 'longitudine',
            'luogo'       => null,
            'distanza_km' => null,
        ];
    }

    private static function distanzaKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private static function vesti(array $r): array
    {
        $parti = array_values(array_filter([
            (string) ($r['admin2_nome'] ?? ''),
            (string) ($r['admin1_nome'] ?? ''),
            (string) ($r['paese_nome'] ?? ''),
        ], static fn (string $s): bool => $s !== ''));

        // Non ripetere «Toscana, Toscana» quando provincia e regione coincidono.
        $parti = array_values(array_unique($parti));

        $trovato = (string) ($r['trovato'] ?? '');
        $nome    = (string) $r['nome'];

        return [
            'id'          => (int) $r['id'],
            'nome'        => $nome,
            // Se l'utente ha trovato il luogo digitando un altro nome — cerca
            // «Londra», il luogo si chiama «London» — glielo si mostra, cosi'
            // capisce perche' quella riga e' comparsa.
            'trovato_come' => ($trovato !== '' && Normalizza::nome($trovato) !== Normalizza::nome($nome))
                ? $trovato : null,
            'contesto'    => implode(', ', $parti),
            'paese'       => (string) $r['paese'],
            'lat'         => round((float) $r['lat'], 6),
            'lon'         => round((float) $r['lon'], 6),
            'altitudine'  => (int) $r['altitudine'],
            'popolazione' => (int) $r['popolazione'],
            'fuso'        => (string) $r['fuso'],
            'capoluogo'   => in_array((string) $r['codice'], ['PPLC', 'PPLA'], true),
        ];
    }
}
