<?php
/**
 * La soglia del portale.
 *
 * In F0 il modulo di nascita non c'e' ancora: al suo posto c'e' il cantiere,
 * dichiarato. Meglio una pagina onesta di un modulo che non calcola.
 */
$fasi = [
    ['F0', 'Fondamenta',        'fatta',    'Impianto, configurazione, schema, veste grafica, regia.'],
    ['F1', 'Il motore',         'fatta',    'Swiss Ephemeris: pianeti, case, aspetti, dignita\'.'],
    ['F2', 'Il luogo e il tempo','fatta',    'Mappa Esri, gazetteer, fuso orario storico.'],
    ['F3', 'La carta',          'fatta',    'La ruota disegnata e le tabelle.'],
    ['F4', 'Il cielo',          'fatta',    'La volta celeste reale dell\'istante.'],
    ['F5', 'Le parole',         'fatta',    'Il corpus a doppio registro.'],
    ['F6', 'Relazioni',         'fatta',    'Sinastria, doppia ruota, transiti.'],
    ['F7', 'Comunita\'',        'fatta',    'Guestbook, statistiche, registro accessi, regia.'],
];
?>
<section class="soglia">
  <div class="soglia-testo">
    <p class="occhiello">Carta del cielo &amp; volta celeste</p>
    <h1>Dove eri, quando il cielo era cosi'</h1>
    <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
    <p class="condotto">
      Data, ora e luogo di nascita. Da questi tre dati il portale ricava il tema natale
      disegnato, la volta celeste reale di quell'istante da quel punto della Terra, e tutto
      cio' che se ne puo' calcolare — posizioni, case, aspetti, dignita', bilanci, effemeridi
      del giorno.
    </p>
    <p class="condotto">
      Le posizioni vengono dalla <strong>Swiss Ephemeris</strong>, che poggia sulle effemeridi
      JPL della NASA. L'interpretazione arriva in <strong>due registri affiancati</strong>,
      quello tradizionale e quello moderno: sta a te scegliere con quale voce leggere.
    </p>
    <p class="azioni">
      <a class="bottone bottone-primo" href="<?= e(url('/calcola')) ?>">Calcola il tuo tema</a>
      <a class="bottone" href="<?= e(url('/sinastria')) ?>">Sinastria rapida</a>
    </p>
  </div>

  <div class="soglia-figura" aria-hidden="true">
    <?= vista('partials/astrolabio') ?>
  </div>
</section>

<section class="tre">
  <article class="riquadro">
    <h2>La carta</h2>
    <p>Dieci pianeti piu' Chirone, Lilith, i Nodi, il Vertex, la Parte di Fortuna e i quattro
       asteroidi maggiori. Undici sistemi di case. Aspetti maggiori e minori, applicativi o
       separativi. Dignita' essenziali e accidentali col punteggio.</p>
  </article>
  <article class="riquadro">
    <h2>Il cielo</h2>
    <p>Non lo zodiaco: il cielo vero. Novemila stelle fino alla sesta magnitudine,
       costellazioni, eclittica, pianeti, la Luna nella fase reale, e il colore del cielo
       secondo l'altezza del Sole sotto l'orizzonte.</p>
  </article>
  <article class="riquadro">
    <h2>Senza conto</h2>
    <p>Nessuna registrazione, nessuna e-mail. Il risultato vive a un indirizzo con un gettone
       segreto: chi lo conserva ritrova la propria carta, e da li' puo' anche cancellarla.
       La ricerca dei luoghi non esce da questo server.</p>
  </article>
</section>

<section class="cantiere">
  <h2>Il cantiere</h2>
  <p class="condotto">Il portale si e' costruito a fasi. Sono tutte in opera.</p>
  <ol class="fasi">
    <?php foreach ($fasi as [$sigla, $nome, $stato, $cosa]): ?>
      <li class="fase-<?= e($stato === 'in corso' ? 'corso' : $stato) ?>">
        <span class="sigla"><?= e($sigla) ?></span>
        <span class="fase-nome"><?= e($nome) ?></span>
        <span class="fase-cosa"><?= e($cosa) ?></span>
        <span class="fase-stato"><?= e($stato) ?></span>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
