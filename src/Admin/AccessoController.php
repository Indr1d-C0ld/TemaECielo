<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Vista;
use App\Support\Telemetria;

final class AccessoController
{
    public function modulo(Request $r): Response
    {
        if (Auth::amministratore()) {
            return Response::redirect(url('/admin'));
        }

        return Response::html(Vista::pagina('accesso', [
            'titolo'  => 'Accesso',
            'sezione' => 'accesso',
            'da'      => (string) ($r->query('da') ?? '/admin'),
            'errore'  => null,
        ]));
    }

    public function entra(Request $r): Response
    {
        if (!Csrf::verifica($r->post('_csrf'))) {
            return $this->rifiuta($r, 'La sessione e\' scaduta. Riprova.');
        }

        $utente   = trim((string) $r->post('utente', ''));
        $password = (string) $r->post('password', '');

        if ($utente === '' || $password === '') {
            return $this->rifiuta($r, 'Servono nome utente e password.');
        }

        $esito = Auth::entra($utente, $password, $r->ip());

        if (!$esito['esito']) {
            Telemetria::evento('accesso_fallito', $utente, $esito['motivo']);

            if ($esito['motivo'] === 'bloccato') {
                $min = (int) ceil($esito['attesa'] / 60);
                return $this->rifiuta(
                    $r,
                    "Troppi tentativi falliti da questo indirizzo. Riprova fra {$min} minut" . ($min === 1 ? 'o' : 'i') . '.',
                    429,
                );
            }

            return $this->rifiuta($r, 'Nome utente o password non validi.', 401);
        }

        Telemetria::evento('accesso_riuscito', $utente);
        Session::lampo('bene', 'Accesso effettuato.');

        return Response::redirect(url(self::destinazione((string) $r->post('da', '/admin'))));
    }

    public function esci(Request $r): Response
    {
        if (Csrf::verifica($r->post('_csrf'))) {
            Auth::traccia('uscita');
            Auth::esci();
            Session::lampo('bene', 'Sessione chiusa.');
        }

        return Response::redirect(url('/'));
    }

    /**
     * Dove andare dopo l'accesso: solo un percorso di questo portale.
     *
     * Il controllo di prima — comincia con «/» e non con «//» — lasciava
     * passare «/\\altrove.com», che i browser trattano come «//altrove.com»,
     * cioe' un altro sito. Oggi non era sfruttabile solo perche' `url()`
     * antepone /temaecielo; installato nella radice del dominio, lo sarebbe
     * diventato. Si ammettono quindi solo caratteri da percorso, e niente che
     * un browser possa leggere come l'inizio di un indirizzo esterno.
     */
    private static function destinazione(string $da): string
    {
        return preg_match('#^/(?![/\\\\])[A-Za-z0-9/_\-.~%?=&]*$#', $da) === 1
            && parse_url('http://x' . $da, PHP_URL_HOST) === 'x'
            ? $da
            : '/admin';
    }

    private function rifiuta(Request $r, string $errore, int $stato = 400): Response
    {
        return Response::html(Vista::pagina('accesso', [
            'titolo'  => 'Accesso',
            'sezione' => 'accesso',
            'da'      => (string) $r->post('da', '/admin'),
            'errore'  => $errore,
        ]), $stato);
    }
}
