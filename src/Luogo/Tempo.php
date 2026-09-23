<?php

declare(strict_types=1);

namespace App\Luogo;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Dalla data e ora locali di nascita al Tempo Universale.
 *
 * E' il punto piu' delicato di tutto il portale, e la prima causa di temi
 * natali sbagliati al mondo.
 *
 * Una nascita del 15 giugno 1943 a Milano non e' «UTC+1». Fra il 1916 e il
 * 1920 e fra il 1940 e il 1948 l'ora legale italiana cambiava regole quasi
 * ogni anno; prima del 1° novembre 1893 l'Italia andava a ora locale media,
 * legata alla longitudine, non a un fuso. Chi applica l'offset di oggi a una
 * nascita di ottant'anni fa sbaglia l'Ascendente di un intero segno.
 *
 * Qui NON si calcola niente a mano: si interroga il tzdata di sistema, che
 * conosce tutte le transizioni storiche. Il lavoro di questa classe e'
 * chiederglielo nel modo giusto e riconoscere i due casi in cui l'ora scritta
 * su un certificato di nascita non identifica un istante univoco.
 */
final class Tempo
{
    public const UNICO       = 'unico';
    public const AMBIGUO     = 'ambiguo';
    public const INESISTENTE = 'inesistente';

    /**
     * Risolve un'ora locale in Tempo Universale.
     *
     * @param string $data  AAAA-MM-GG
     * @param string $ora   HH:MM oppure HH:MM:SS
     * @return array<string,mixed>
     */
    public static function risolvi(string $data, string $ora, string $zona): array
    {
        if (!self::zonaValida($zona)) {
            return self::errore("Fuso orario sconosciuto: {$zona}");
        }

        [$anno, $mese, $giorno] = array_map('intval', explode('-', $data));
        $pezzi = array_map('intval', array_pad(explode(':', $ora), 3, 0));
        [$h, $m, $s] = $pezzi;

        if (!checkdate($mese, $giorno, $anno)) {
            return self::errore("Data inesistente: {$data}");
        }
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59 || $s < 0 || $s > 59) {
            return self::errore("Ora fuori scala: {$ora}");
        }

        $tz = new DateTimeZone($zona);

        // L'orologio a muro, letto come se fosse UTC. Non e' un istante vero:
        // e' il numero da cui si sottrarra' l'offset per ottenerne uno.
        $muro = (int) (new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $anno, $mese, $giorno, $h, $m, $s),
            new DateTimeZone('UTC'),
        ))->getTimestamp();

        $valide = self::interpretazioni($muro, $tz);

        if ($valide === []) {
            // L'ora non esiste: e' stata saltata all'ingresso dell'ora legale.
            return self::inesistente($data, $ora, $zona, $muro, $tz);
        }

        $stato = count($valide) > 1 ? self::AMBIGUO : self::UNICO;
        $scelta = $valide[0];

        return [
            'ok'             => true,
            'stato'          => $stato,
            'data_locale'    => $data,
            'ora_locale'     => sprintf('%02d:%02d:%02d', $h, $m, $s),
            'zona'           => $zona,
            'offset_secondi' => $scelta['offset'],
            'offset_testo'   => self::offsetTesto($scelta['offset']),
            'abbreviazione'  => $scelta['abbr'],
            'ora_legale'     => $scelta['dst'],
            'istante'        => $scelta['istante'],
            'utc'            => gmdate('Y-m-d\TH:i:s\Z', $scelta['istante']),
            'componenti_ut'  => self::componenti($scelta['istante']),
            // Quando l'ora e' ambigua servono entrambe, per poterle proporre.
            'alternative'    => array_map(
                static fn (array $v): array => [
                    'offset_secondi' => $v['offset'],
                    'offset_testo'   => self::offsetTesto($v['offset']),
                    'abbreviazione'  => $v['abbr'],
                    'ora_legale'     => $v['dst'],
                    'utc'            => gmdate('Y-m-d\TH:i:s\Z', $v['istante']),
                    'componenti_ut'  => self::componenti($v['istante']),
                ],
                $valide,
            ),
            'avviso'         => $stato === self::AMBIGUO
                ? 'Quella notte gli orologi sono tornati indietro: questa ora e\' esistita due volte. '
                  . 'Sono state calcolate entrambe; e\' stata scelta la prima, quella ancora in ora legale.'
                : null,
        ];
    }

    /**
     * Le interpretazioni valide di un'ora a muro.
     *
     * Un'ora locale identifica un istante solo se esiste un offset o tale che,
     * togliendolo dall'orologio a muro, si ottenga un istante in cui quel fuso
     * usa davvero l'offset o. Nessuna soluzione: l'ora non esiste. Due: e'
     * ambigua. E' il modo corretto di porre la domanda al tzdata, e non
     * richiede di conoscere nessuna regola di ora legale.
     *
     * @return list<array{offset:int,abbr:string,dst:bool,istante:int}>
     */
    private static function interpretazioni(int $muro, DateTimeZone $tz): array
    {
        $candidati = [];
        foreach (self::offsetVicini($muro, $tz) as $offset) {
            $istante = $muro - $offset;

            if ($tz->getOffset(new DateTimeImmutable('@' . $istante)) !== $offset) {
                continue; // offset non in vigore in quell'istante: interpretazione non valida
            }

            $t = new DateTimeImmutable('@' . $istante);
            $t = $t->setTimezone($tz);

            $candidati[$offset] = [
                'offset'  => $offset,
                'abbr'    => $t->format('T'),
                'dst'     => $t->format('I') === '1',
                'istante' => $istante,
            ];
        }

        // Dalla piu' avanti alla piu' indietro: in caso di ambiguita' la prima
        // e' quella ancora in ora legale, che e' la lettura piu' naturale di
        // un'ora scritta su un documento.
        krsort($candidati);

        return array_values($candidati);
    }

    /**
     * Gli offset in uso nei dintorni di un istante.
     *
     * Si guarda una finestra di due giorni: piu' che sufficiente a contenere
     * qualunque transizione, e stretta abbastanza da non raccogliere offset di
     * epoche diverse.
     *
     * @return list<int>
     */
    private static function offsetVicini(int $muro, DateTimeZone $tz): array
    {
        $da = $muro - 2 * 86400;
        $a  = $muro + 2 * 86400;

        $transizioni = $tz->getTransitions($da, $a);
        $offset = [];

        if ($transizioni !== false) {
            foreach ($transizioni as $t) {
                $offset[] = (int) $t['offset'];
            }
        }

        // Rete di sicurezza: se il fuso non ha transizioni in quella finestra
        // (o getTransitions fallisce, cosa che capita con date remotissime),
        // resta valido almeno l'offset in vigore attorno a quell'istante.
        $offset[] = $tz->getOffset(new DateTimeImmutable('@' . $muro));

        return array_values(array_unique($offset));
    }

    /**
     * L'ora saltata all'ingresso dell'ora legale.
     *
     * Non si rifiuta il calcolo: si dice cosa e' successo e si propone l'ora
     * vera piu' vicina, che e' l'istante in cui gli orologi sono stati spostati.
     * Rifiutare e basta lascerebbe l'utente senza sapere che fare.
     *
     * @return array<string,mixed>
     */
    private static function inesistente(string $data, string $ora, string $zona, int $muro, DateTimeZone $tz): array
    {
        $salto = null;
        $transizioni = $tz->getTransitions($muro - 2 * 86400, $muro + 2 * 86400);

        if ($transizioni !== false) {
            foreach ($transizioni as $t) {
                if ($t['ts'] > $muro - 2 * 86400 && $t['ts'] < $muro + 2 * 86400) {
                    $salto = (int) $t['ts'];
                }
            }
        }

        $proposta = $salto ?? $muro - $tz->getOffset(new DateTimeImmutable('@' . $muro));
        $locale = (new DateTimeImmutable('@' . $proposta))->setTimezone($tz);

        return [
            'ok'            => false,
            'stato'         => self::INESISTENTE,
            'data_locale'   => $data,
            'ora_locale'    => $ora,
            'zona'          => $zona,
            'errore'        => 'Quell\'ora non e\' mai esistita in quel luogo.',
            'avviso'        => sprintf(
                'Quella notte gli orologi sono stati spostati avanti e l\'ora %s e\' stata saltata. '
                . 'Il primo istante esistente dopo il salto e\' %s.',
                substr($ora, 0, 5),
                $locale->format('H:i'),
            ),
            'proposta_ora'  => $locale->format('H:i'),
            // L'istante c'e' anche qui, benche' `ok` sia falso: chi non sta
            // ricostruendo una nascita — la carta del cielo, per esempio — ha
            // comunque bisogno di un istante su cui calcolare, e questo e' il
            // primo esistente dopo il salto. Chi invece deve fermarsi guarda
            // `ok`, come ha sempre fatto.
            'istante'       => $proposta,
            'componenti_ut' => self::componenti($proposta),
        ];
    }

    /**
     * Come `risolvi`, ma sapendo DOVE: prima dell'adozione dei fusi l'ora di un
     * atto di nascita e' l'ora locale media del luogo, non quella della citta'
     * che da' il nome alla zona.
     *
     * Il tzdata, per le date anteriori ai fusi, risponde con l'ora media della
     * citta' di riferimento: «LMT» di Chicago per tutto l'Illinois, «RMT» —
     * l'ora di Roma — per tutta l'Italia dal 1866 al 1893. Ma l'ora di Roma era
     * quella delle ferrovie e dei telegrafi; nei comuni si segnava l'ora del
     * proprio meridiano. Per una nascita a Milano nel 1880 l'errore era di
     * tredici minuti, a Torino di diciannove: qualche grado di Ascendente.
     *
     * La regola non si allarga a ogni «ora media di una capitale»: in Francia
     * l'ora di Parigi divento' davvero ora civile nazionale nel 1891, ed e'
     * giusto che resti tale. Per questo «RMT» e' trattata come locale solo per
     * le zone italiane.
     *
     * @return array<string,mixed>
     */
    public static function risolviNelLuogo(string $data, string $ora, string $zona, float $lon): array
    {
        $r = self::risolvi($data, $ora, $zona);

        $abbr = (string) ($r['abbreviazione'] ?? '');
        $locale = $abbr === 'LMT'
            || ($abbr === 'RMT' && in_array($zona, self::ZONE_ITALIANE, true));

        if (($r['ok'] ?? false) !== true || !$locale) {
            return $r;
        }

        return ['zona' => $zona] + self::oraLocaleMedia($data, $ora, $lon);
    }

    /** Zone che nel tzdata seguono l'Italia (Roma, e i due stati che ci stanno dentro). */
    private const ZONE_ITALIANE = ['Europe/Rome', 'Europe/Vatican', 'Europe/San_Marino'];

    /**
     * Ora locale media, ricavata dalla sola longitudine.
     *
     * Serve per le nascite anteriori all'adozione dei fusi — in Italia il 1°
     * novembre 1893 — e per i luoghi di cui non si conosce la storia oraria.
     * E' quello che facevano gli astrologi prima che i fusi esistessero: il
     * mezzogiorno e' quando il Sole passa al meridiano del luogo.
     *
     * @return array<string,mixed>
     */
    public static function oraLocaleMedia(string $data, string $ora, float $lon): array
    {
        [$anno, $mese, $giorno] = array_map('intval', explode('-', $data));
        $pezzi = array_map('intval', array_pad(explode(':', $ora), 3, 0));

        $muro = (int) (new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $anno, $mese, $giorno, ...$pezzi),
            new DateTimeZone('UTC'),
        ))->getTimestamp();

        $offset  = (int) round($lon / 15.0 * 3600.0);
        $istante = $muro - $offset;

        return [
            'ok'             => true,
            'stato'          => self::UNICO,
            'data_locale'    => $data,
            'ora_locale'     => sprintf('%02d:%02d:%02d', ...$pezzi),
            'zona'           => 'LMT',
            'offset_secondi' => $offset,
            'offset_testo'   => self::offsetTesto($offset),
            'abbreviazione'  => 'LMT',
            'ora_legale'     => false,
            'istante'        => $istante,
            'utc'            => gmdate('Y-m-d\TH:i:s\Z', $istante),
            'componenti_ut'  => self::componenti($istante),
            'alternative'    => [],
            'avviso'         => 'Ora locale media ricavata dalla longitudine: a quella data i fusi orari non erano in uso.',
        ];
    }

    /**
     * Scompone un istante nei valori che vuole il motore delle effemeridi.
     *
     * @return array{anno:int,mese:int,giorno:int,ora_ut:float}
     */
    public static function componenti(int $istante): array
    {
        return [
            'anno'   => (int) gmdate('Y', $istante),
            'mese'   => (int) gmdate('n', $istante),
            'giorno' => (int) gmdate('j', $istante),
            'ora_ut' => (int) gmdate('G', $istante)
                        + (int) gmdate('i', $istante) / 60.0
                        + (int) gmdate('s', $istante) / 3600.0,
        ];
    }

    public static function offsetTesto(int $secondi): string
    {
        $segno = $secondi < 0 ? '-' : '+';
        $s = abs($secondi);

        return $s % 60 === 0
            ? sprintf('%s%02d:%02d', $segno, intdiv($s, 3600), intdiv($s % 3600, 60))
            : sprintf('%s%02d:%02d:%02d', $segno, intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }

    /**
     * Le zone `Etc/GMT+N` — i fusi nautici, quelli che si usano in mezzo
     * all'oceano dove non c'e' nessun paese di cui prendere l'ora — non
     * compaiono in `listIdentifiers()`, che di suo restituisce solo le zone
     * geografiche. Stanno nel gruppo ALL_WITH_BC, e PHP le accetta senza
     * problemi: e' l'elenco a essere parziale, non la zona a essere invalida.
     */
    public static function zonaValida(string $zona): bool
    {
        static $tutte = null;
        $tutte ??= array_flip(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC));

        return isset($tutte[$zona]);
    }

    /** @return array<string,mixed> */
    private static function errore(string $messaggio): array
    {
        return ['ok' => false, 'stato' => 'errore', 'errore' => $messaggio, 'avviso' => null];
    }
}
