<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Vista;

final class HomeController
{
    public function home(Request $r): Response
    {
        return Response::html(Vista::pagina('home', [
            'titolo'  => 'Tema e Cielo',
            'sezione' => 'home',
        ]));
    }
}
