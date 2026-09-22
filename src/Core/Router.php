<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;

final class Router
{
    /** @var list<array{metodo:string,regex:string,parametri:list<string>,gestore:mixed,filtri:list<string>}> */
    private array $rotte = [];

    /** @param callable|array{0:class-string,1:string} $gestore */
    public function get(string $percorso, callable|array $gestore, array $filtri = []): void
    {
        $this->aggiungi('GET', $percorso, $gestore, $filtri);
    }

    /** @param callable|array{0:class-string,1:string} $gestore */
    public function post(string $percorso, callable|array $gestore, array $filtri = []): void
    {
        $this->aggiungi('POST', $percorso, $gestore, $filtri);
    }

    /** @param callable|array{0:class-string,1:string} $gestore */
    public function aggiungi(string $metodo, string $percorso, callable|array $gestore, array $filtri = []): void
    {
        $parametri = [];
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static function (array $m) use (&$parametri): string {
                $parametri[] = $m[1];
                return '([^/]+)';
            },
            $percorso,
        );

        $this->rotte[] = [
            'metodo'    => $metodo,
            'regex'     => '#^' . $regex . '$#',
            'parametri' => $parametri,
            'gestore'   => $gestore,
            'filtri'    => $filtri,
        ];
    }

    public function smista(Request $richiesta): Response
    {
        $percorso = $richiesta->percorso();
        $percorsoTrovato = false;

        foreach ($this->rotte as $rotta) {
            if (preg_match($rotta['regex'], $percorso, $m) !== 1) {
                continue;
            }
            $percorsoTrovato = true;
            if ($rotta['metodo'] !== $richiesta->metodo()) {
                continue;
            }

            foreach ($rotta['filtri'] as $filtro) {
                $esito = $this->filtro($filtro, $richiesta);
                if ($esito instanceof Response) {
                    return $esito;
                }
            }

            array_shift($m);
            $argomenti = [];
            foreach ($rotta['parametri'] as $i => $nome) {
                $argomenti[$nome] = urldecode((string) ($m[$i] ?? ''));
            }

            $gestore = $rotta['gestore'];
            if (is_array($gestore)) {
                [$classe, $metodo] = $gestore;
                $gestore = [new $classe(), $metodo];
            }

            /** @var Response $risposta */
            $risposta = $gestore($richiesta, $argomenti);

            return $risposta;
        }

        if ($percorsoTrovato) {
            return Response::html(Vista::pagina('errors/generico', [
                'titolo'   => 'Metodo non ammesso',
                'stato'    => 405,
                'messaggio'=> 'Questa pagina non risponde a richieste di tipo ' . $richiesta->metodo() . '.',
            ]), 405);
        }

        return Response::html(Vista::pagina('errors/generico', [
            'titolo'   => 'Pagina non trovata',
            'stato'    => 404,
            'messaggio'=> 'Non c\'e\' niente a questo indirizzo. Forse il collegamento e\' vecchio.',
        ]), 404);
    }

    private function filtro(string $nome, Request $richiesta): ?Response
    {
        return match ($nome) {
            'admin' => Auth::amministratore()
                ? null
                : Response::redirect(url('/accesso?da=' . rawurlencode($richiesta->percorso()))),
            default => null,
        };
    }
}
