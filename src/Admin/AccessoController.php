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

        $da = (string) $r->post('da', '/admin');
        if (!str_starts_with($da, '/') || str_starts_with($da, '//')) {
            $da = '/admin';
        }

        return Response::redirect(url($da));
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
