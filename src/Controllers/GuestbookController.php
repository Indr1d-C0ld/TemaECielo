<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Vista;
use App\Support\Impostazioni;
use App\Support\Rete;
use App\Support\Telemetria;

/**
 * Il guestbook: firma, commento e due voti.
 *
 * I voti sono DUE e restano distinti. «Mi e' piaciuto» e «mi ci sono
 * riconosciuto» sono due giudizi diversi: mescolarli in un numero solo li
 * renderebbe inutili entrambi, e l'attinenza — aggregata per segno solare e
 * per ascendente — e' il dato piu' interessante che questo portale possa
 * raccogliere.
 *
 * Contro gli abusi non si usa nessun captcha di terzi: sarebbe un'altra fuga
 * di dati verso l'esterno, proprio in un portale costruito per non farne. Ci
 * sono invece quattro difese messe insieme, e nessuna chiede niente a nessuno.
 */
final class GuestbookController
{
    /** Quanto deve durare come minimo la compilazione, in secondi. */
    private const ATTESA_MINIMA = 4;

    /** GET /guestbook */
    public function elenco(Request $r): Response
    {
        $pagina = max(1, (int) ($r->query('p') ?? '1'));
        $per = 20;

        $totale = (int) Database::valore('SELECT COUNT(*) FROM guestbook WHERE stato = ?', ['approvato']);

        $messaggi = Database::righe(
            'SELECT g.id, g.nome, g.messaggio, g.voto_gradimento, g.voto_attinenza, g.creato, g.paese
               FROM guestbook g WHERE g.stato = ?
              ORDER BY g.creato DESC LIMIT ' . $per . ' OFFSET ' . (($pagina - 1) * $per),
            ['approvato'],
        );

        $risposte = [];
        if ($messaggi !== []) {
            $id = array_map(static fn (array $m): int => (int) $m['id'], $messaggi);
            $segna = implode(',', array_fill(0, count($id), '?'));
            foreach (Database::righe(
                "SELECT messaggio_id, corpo, autore, creato FROM guestbook_risposte
                  WHERE messaggio_id IN ({$segna}) ORDER BY creato", $id) as $x) {
                $risposte[(int) $x['messaggio_id']][] = $x;
            }
        }

        return Response::html(Vista::pagina('guestbook', [
            'titolo'    => 'Guestbook',
            'sezione'   => 'guestbook',
            'messaggi'  => $messaggi,
            'risposte'  => $risposte,
            'totale'    => $totale,
            'pagina'    => $pagina,
            'per'       => $per,
            'aperto'    => Impostazioni::attiva('guestbook_attivo'),
            'moderato'  => Impostazioni::attiva('guestbook_moderazione'),
            'medie'     => $this->medie(),
            'dati'      => Session::get('__gb', []),
            'errori'    => Session::get('__gb_errori', []),
            // Il gettone dell'ultima carta vista, per agganciare il voto al
            // calcolo che l'ha generato: senza riferimento l'attinenza non e'
            // verificabile.
            'carta'     => (string) Session::get('__ultima_carta', ''),
        ]));
    }

    /** POST /guestbook */
    public function firma(Request $r): Response
    {
        if (!Impostazioni::attiva('guestbook_attivo')) {
            Session::lampo('male', 'Il guestbook e\' chiuso in questo momento.');

            return Response::redirect(url('/guestbook'));
        }
        if (!Csrf::verifica($r->post('_csrf'))) {
            Session::lampo('male', 'La sessione e\' scaduta. Riprova.');

            return Response::redirect(url('/guestbook'));
        }

        $ip = $r->ip();
        $dati = [
            'nome'       => mb_substr(trim((string) $r->post('nome', '')), 0, 80),
            'messaggio'  => mb_substr(trim((string) $r->post('messaggio', '')), 0, 4000),
            'gradimento' => $this->voto($r->post('gradimento')),
            'attinenza'  => $this->voto($r->post('attinenza')),
            'carta'      => preg_replace('/[^a-f0-9]/', '', (string) $r->post('carta', '')),
        ];

        $errori = $this->controlla($r, $dati, $ip);

        if ($errori !== []) {
            Session::set('__gb', $dati);
            Session::set('__gb_errori', $errori);
            Telemetria::evento('guestbook_rifiutato', implode(',', array_keys($errori)));

            return Response::redirect(url('/guestbook#firma'));
        }

        $calcoloId = null;
        if ($dati['carta'] !== '') {
            $calcoloId = Database::valore('SELECT id FROM calcoli WHERE gettone = ? LIMIT 1', [$dati['carta']]);
            $calcoloId = $calcoloId === null ? null : (int) $calcoloId;
        }

        $g = Rete::geolocalizza($ip);
        $stato = Impostazioni::attiva('guestbook_moderazione') ? 'coda' : 'approvato';

        Database::esegui(
            'INSERT INTO guestbook
               (calcolo_id, nome, messaggio, voto_gradimento, voto_attinenza, stato,
                ip, sessione, ua, paese, creato)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $calcoloId,
                $dati['nome'] !== '' ? $dati['nome'] : 'Anonimo',
                $dati['messaggio'],
                $dati['gradimento'],
                $dati['attinenza'],
                $stato,
                $ip,
                Telemetria::sessione(),
                $r->userAgent(),
                $g['paese'] ?? null,
            ],
        );

        Session::togli('__gb');
        Session::togli('__gb_errori');
        Telemetria::evento('guestbook_firmato', $stato);

        Session::lampo('bene', $stato === 'coda'
            ? 'Grazie. Il messaggio comparira\' appena approvato.'
            : 'Grazie, il messaggio e\' pubblicato.');

        return Response::redirect(url('/guestbook'));
    }

    // ------------------------------------------------------------------------

    /**
     * Le quattro difese.
     *
     * @param array<string,mixed> $d
     * @return array<string,string>
     */
    private function controlla(Request $r, array $d, string $ip): array
    {
        $e = [];

        // 1. Il campo esca. E' nascosto via CSS e nessun essere umano lo vede:
        //    se e' pieno, a compilarlo e' stato un programma.
        if (trim((string) $r->post('sito', '')) !== '') {
            $e['esca'] = 'Messaggio non accettato.';

            return $e;   // non si spiega altro: chi lo ha compilato non e' un lettore
        }

        // 2. Il tempo. Un modulo compilato in meno di quattro secondi non e'
        //    stato letto. Il momento di apertura sta in sessione, non in un
        //    campo nascosto, altrimenti basterebbe falsificarlo.
        $aperto = (int) Session::get('__gb_aperto', 0);
        if ($aperto > 0 && (time() - $aperto) < self::ATTESA_MINIMA) {
            $e['fretta'] = 'Hai compilato troppo in fretta. Riprova fra qualche secondo.';
        }

        // 3. Il tetto per indirizzo.
        $tetto = Impostazioni::intero('guestbook_tetto_ora', 3);
        $recenti = (int) Database::valore(
            'SELECT COUNT(*) FROM guestbook WHERE ip = ? AND creato > (NOW() - INTERVAL 1 HOUR)',
            [$ip],
        );
        if ($recenti >= $tetto) {
            $e['tetto'] = 'Hai gia\' lasciato ' . $recenti . ' messaggi nell\'ultima ora. Riprova piu\' tardi.';
        }

        // 4. Le parole vietate e i blocchi.
        $parole = array_filter(array_map('trim', explode(',', Impostazioni::testo('guestbook_parole'))));
        $corpo = mb_strtolower($d['nome'] . ' ' . $d['messaggio'], 'UTF-8');
        foreach ($parole as $parola) {
            if ($parola !== '' && str_contains($corpo, mb_strtolower($parola, 'UTF-8'))) {
                $e['parole'] = 'Il messaggio contiene qualcosa che non possiamo pubblicare.';
                break;
            }
        }
        if (Rete::bloccato($ip)) {
            $e['bloccato'] = 'Messaggio non accettato.';
        }

        // --- e poi i controlli ordinari ---
        if (mb_strlen($d['messaggio']) < 10) {
            $e['messaggio'] = 'Scrivi almeno una frase: dieci caratteri sono pochi.';
        }
        if ($d['gradimento'] === null && $d['attinenza'] === null && mb_strlen($d['messaggio']) < 20) {
            $e['voti'] = 'Se non lasci un voto, scrivi almeno qualcosa di piu\'.';
        }

        return $e;
    }

    private function voto(?string $v): ?int
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $n = (int) $v;

        return ($n >= 1 && $n <= 5) ? $n : null;
    }

    /** @return array<string,mixed> */
    private function medie(): array
    {
        $r = Database::riga(
            'SELECT COUNT(*) AS n,
                    AVG(voto_gradimento) AS gradimento, COUNT(voto_gradimento) AS n_gradimento,
                    AVG(voto_attinenza)  AS attinenza,  COUNT(voto_attinenza)  AS n_attinenza
               FROM guestbook WHERE stato = ?',
            ['approvato'],
        );

        return [
            'messaggi'     => (int) ($r['n'] ?? 0),
            'gradimento'   => $r['gradimento'] === null ? null : round((float) $r['gradimento'], 2),
            'n_gradimento' => (int) ($r['n_gradimento'] ?? 0),
            'attinenza'    => $r['attinenza'] === null ? null : round((float) $r['attinenza'], 2),
            'n_attinenza'  => (int) ($r['n_attinenza'] ?? 0),
        ];
    }
}
