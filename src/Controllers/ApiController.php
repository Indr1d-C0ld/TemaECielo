<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Luogo\Gazetteer;
use App\Luogo\Tempo;
use App\Support\Telemetria;

/**
 * Le poche chiamate che il modulo di nascita fa mentre lo si compila.
 *
 * Tutte in sola lettura e tutte servite da questo server: cercare un luogo di
 * nascita non deve far uscire un carattere verso terzi.
 */
final class ApiController
{
    /** Quanto puo' chiedere una sessione al minuto, prima di essere rallentata. */
    private const TETTO_MINUTO = 120;

    /** GET /api/luoghi?q=milano&paese=IT */
    public function luoghi(Request $r): Response
    {
        if (!$this->consentito()) {
            return Response::json(['errore' => 'Troppe richieste.'], 429);
        }

        $q     = trim((string) ($r->query('q') ?? ''));
        $paese = $r->query('paese');
        $paese = (is_string($paese) && preg_match('/^[A-Za-z]{2}$/', $paese) === 1) ? $paese : null;

        if (mb_strlen($q) > 120) {
            return Response::json(['errore' => 'Richiesta troppo lunga.'], 400);
        }

        $t0 = microtime(true);
        $risultati = Gazetteer::cerca($q, $paese);
        $ms = (int) round((microtime(true) - $t0) * 1000);

        Telemetria::evento('ricerca_luogo', mb_substr($q, 0, 60), (string) count($risultati), $ms);

        return Response::json([
            'query'      => $q,
            'risultati'  => $risultati,
            'durata_ms'  => $ms,
        ]);
    }

    /** GET /api/luogo-vicino?lat=45.46&lon=9.19 — per il click sulla mappa */
    public function vicino(Request $r): Response
    {
        if (!$this->consentito()) {
            return Response::json(['errore' => 'Troppe richieste.'], 429);
        }

        $lat = $this->coordinata($r->query('lat'), 90.0);
        $lon = $this->coordinata($r->query('lon'), 180.0);

        if ($lat === null || $lon === null) {
            return Response::json(['errore' => 'Coordinate non valide.'], 400);
        }

        $luogo = Gazetteer::piuVicino($lat, $lon);
        $fuso  = Gazetteer::fusoDi($lat, $lon);

        Telemetria::evento('click_mappa', sprintf('%.3f,%.3f', $lat, $lon), $luogo['nome'] ?? '—');

        return Response::json([
            'lat'   => $lat,
            'lon'   => $lon,
            'luogo' => $luogo,
            'fuso'  => $fuso,
        ]);
    }

    /**
     * GET /api/fuso?data=1978-06-12&ora=23:14&zona=Europe/Rome
     *
     * Serve a mostrare in tempo reale, sotto il campo dell'ora, quale scarto
     * dal Tempo Universale verra' applicato — e ad avvisare subito quando
     * l'ora inserita e' ambigua o non e' mai esistita.
     */
    public function fuso(Request $r): Response
    {
        if (!$this->consentito()) {
            return Response::json(['errore' => 'Troppe richieste.'], 429);
        }

        $data = (string) ($r->query('data') ?? '');
        $ora  = (string) ($r->query('ora') ?? '');
        $zona = (string) ($r->query('zona') ?? '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) !== 1 || preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $ora) !== 1) {
            return Response::json(['errore' => 'Data od ora non valide.'], 400);
        }

        return Response::json(Tempo::risolvi($data, $ora, $zona));
    }

    private function coordinata(?string $v, float $massimo): ?float
    {
        if ($v === null || !is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return abs($f) <= $massimo ? $f : null;
    }

    /**
     * Limite di frequenza per sessione.
     *
     * Il completamento automatico chiama a ogni tasto premuto: il tetto e'
     * largo apposta. Serve contro chi volesse scaricarsi il gazetteer una
     * riga per volta, non contro chi sta scrivendo il proprio luogo di nascita.
     */
    private function consentito(): bool
    {
        $ora     = time();
        $finestra = (int) floor($ora / 60);
        $chiave  = '__api_' . $finestra;

        $n = (int) \App\Core\Session::get($chiave, 0);
        if ($n >= self::TETTO_MINUTO) {
            return false;
        }

        \App\Core\Session::set($chiave, $n + 1);

        // Le finestre vecchie non servono piu' e non devono gonfiare la sessione.
        foreach (array_keys($_SESSION) as $k) {
            if (is_string($k) && str_starts_with($k, '__api_') && $k !== $chiave) {
                \App\Core\Session::togli($k);
            }
        }

        return true;
    }
}
