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

    public function calcola(Request $r): Response
    {
        return $this->inLavorazione('calcola', 'Calcola il tuo tema', 'F2 — il luogo e il tempo',
            'Il modulo di nascita: generalita\', data, ora, e il luogo scelto sulla mappa Esri con '
            . 'ricerca delle localita\' e coordinate. Qui si risolve anche il fuso orario storico, '
            . 'che e\' la prima causa di temi natali sbagliati.');
    }

    public function cielo(Request $r): Response
    {
        return $this->inLavorazione('cielo', 'Il cielo', 'F4 — il cielo',
            'La volta celeste reale dell\'istante e del luogo di nascita: stelle fino alla sesta '
            . 'magnitudine, costellazioni, eclittica, pianeti, Luna nella fase vera, e il colore '
            . 'del cielo secondo l\'altezza del Sole.');
    }

    public function sinastria(Request $r): Response
    {
        return $this->inLavorazione('sinastria', 'Sinastria', 'F6 — relazioni e derivati',
            'Il rapporto fra due carte: aspetti incrociati, sovrapposizione delle case, composita '
            . 'di punti medi e carta di Davison. Con la versione rapida segno contro segno, che '
            . 'non chiede nessuna data.');
    }

    public function oggi(Request $r): Response
    {
        return $this->inLavorazione('oggi', 'Il cielo di oggi', 'F4 — il cielo',
            'Posizioni correnti, fase lunare, pianeti retrogradi in questo momento, prossimi '
            . 'ingressi di segno e prossime lunazioni.');
    }

    public function statistiche(Request $r): Response
    {
        return $this->inLavorazione('statistiche', 'Statistiche', 'F7 — comunita\' e regia',
            'Cosa ha calcolato il portale finora: distribuzione dei segni solari, lunari e degli '
            . 'ascendenti, bilanci di elementi e modalita\', mappa dei luoghi di nascita, voti di '
            . 'gradimento e attinenza.');
    }

    public function guestbook(Request $r): Response
    {
        return $this->inLavorazione('guestbook', 'Guestbook', 'F7 — comunita\' e regia',
            'Firma, saluto e commento sul calcolo ricevuto, con i due voti distinti di gradimento '
            . 'e attinenza.');
    }

    private function inLavorazione(string $sezione, string $titolo, string $fase, string $cosa): Response
    {
        return Response::html(Vista::pagina('in-lavorazione', [
            'titolo'  => $titolo,
            'sezione' => $sezione,
            'fase'    => $fase,
            'cosa'    => $cosa,
        ]));
    }
}
