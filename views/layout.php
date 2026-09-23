<?php
/**
 * Layout generale.
 *
 * @var string $titolo
 * @var string $contenuto
 * @var string $sezione
 */
use App\Auth\Auth;
use App\Core\Config;
use App\Core\Session;

$lampi = Session::lampi();
$voci = [
    ['/',            'Calcola',      'home',        false],
    ['/cielo',       'Il cielo',     'cielo',       false],
    ['/sinastria',   'Sinastria',    'sinastria',   false],
    ['/oggi',        'Oggi',         'oggi',        false],
    ['/statistiche', 'Statistiche',  'statistiche', false],
    ['/guestbook',   'Guestbook',    'guestbook',   false],
];

// L'accesso alla regia sta in fondo al menu, non solo nel pie' di pagina:
// e' una porta, e una porta si mette dove si cerca. Il quarto elemento la
// stacca dalle altre voci, che sono sezioni del portale e non una soglia.
$voci[] = Auth::amministratore()
    ? ['/admin',   'Regia',   'admin',   true]
    : ['/accesso', 'Accesso', 'accesso', true];
?>
<!doctype html>
<html lang="it" data-veste="notte">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($titolo ?? '') === 'Tema e Cielo' || ($titolo ?? '') === '' ? 'Tema e Cielo' : $titolo . ' — Tema e Cielo') ?></title>
<meta name="description" content="Il tema natale calcolato con la Swiss Ephemeris e la volta celeste reale dell'istante di nascita.">
<link rel="stylesheet" href="<?= e(risorsa('css/temaecielo.css')) ?>">
<?php if (($mappa ?? false) === true): ?>
  <link rel="stylesheet" href="<?= e(risorsa('leaflet/leaflet.css')) ?>">
<?php endif; ?>
<link rel="icon" href="<?= e(risorsa('img/stella.svg')) ?>" type="image/svg+xml">
</head>
<body data-base="<?= e(rtrim((string) ($GLOBALS['__base_path'] ?? ''), '/')) ?>">
<?= vista('partials/glifi') ?>
<a class="salta" href="#principale">Vai al contenuto</a>

<header class="testata">
  <div class="dentro">
    <a class="marchio" href="<?= e(url('/')) ?>">
      <span class="marchio-nome">Tema e Cielo</span>
      <span class="marchio-motto">carta del cielo &amp; volta celeste</span>
    </a>

    <!-- Il pulsante nasce nascosto e lo scopre il JavaScript: senza, il menu
         resta aperto e la testata si limita a mandare le voci a capo, che e'
         esattamente quello che faceva prima. Nessuno resta chiuso fuori. -->
    <button type="button" class="menu-tasto" id="menu-tasto" hidden
            aria-expanded="true" aria-controls="navigazione">
      <span class="menu-barre" aria-hidden="true"><i></i><i></i><i></i></span>
      <span class="menu-parola">Menu</span>
    </button>

    <nav class="navigazione" id="navigazione" aria-label="Sezioni del portale">
      <ul>
        <?php foreach ($voci as [$percorso, $etichetta, $chiave, $soglia]): ?>
          <li<?= $soglia ? ' class="voce-soglia"' : '' ?>>
            <a href="<?= e(url($percorso)) ?>"<?= ($sezione ?? '') === $chiave ? ' aria-current="page"' : '' ?>><?= e($etichetta) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <button type="button" class="veste" id="cambia-veste"
            aria-label="Cambia fra veste notte e veste pergamena" title="Notte / Pergamena">
      <?= glifo('luna') ?>
    </button>
  </div>
</header>

<?php if ($lampi !== []): ?>
  <div class="lampi" role="status">
    <?php foreach ($lampi as $l): ?>
      <p class="lampo lampo-<?= e($l['tipo']) ?>"><?= e($l['testo']) ?></p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<main id="principale" class="foglio">
<?= $contenuto ?>
</main>

<footer class="piede">
  <div class="dentro">
    <p class="avvertenza">
      L'astrologia e' una tradizione simbolica e culturale, non una scienza predittiva.
      Questo portale calcola posizioni astronomiche reali — verificabili al secondo d'arco —
      e vi applica un linguaggio interpretativo storico.
    </p>
    <p class="crediti">
      Posizioni calcolate con la <strong>Swiss Ephemeris</strong> (effemeridi JPL DE431).
      Questo portale e' software libero: il
      <a href="https://github.com/Indr1d-C0ld/TemaECielo" rel="noopener noreferrer">codice sorgente</a>
      e' disponibile sotto licenza AGPL-3.0.
    </p>
    <p class="riga-fondo">
      <?php if (Auth::amministratore()): ?>
        <a href="<?= e(url('/admin')) ?>">Regia</a>
        <span class="sep">&middot;</span>
        <form method="post" action="<?= e(url('/uscita')) ?>" class="in-riga">
          <?= csrf() ?><button type="submit" class="collegamento">Esci (<?= e(Auth::nome()) ?>)</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('/accesso')) ?>">Accesso</a>
      <?php endif; ?>
      <span class="sep">&middot;</span>
      <a href="<?= e(url('/pagina/informativa')) ?>">Informativa</a>
      <span class="sep">&middot;</span>
      <span><?= e((string) Config::get('app.nome', 'Tema e Cielo')) ?></span>
    </p>
  </div>
</footer>

<script src="<?= e(risorsa('js/portale.js')) ?>" nonce="<?= e(nonce()) ?>" defer></script>
<?php if (($mappa ?? false) === true): ?>
  <script src="<?= e(risorsa('leaflet/leaflet.js')) ?>" nonce="<?= e(nonce()) ?>"></script>
  <script src="<?= e(risorsa('js/luoghi.js')) ?>" nonce="<?= e(nonce()) ?>" defer></script>
  <script src="<?= e(risorsa('js/mappa.js')) ?>" nonce="<?= e(nonce()) ?>" defer></script>
<?php endif; ?>
<?php if (($cielo ?? false) === true): ?>
  <script src="<?= e(risorsa('js/cielo.js')) ?>" nonce="<?= e(nonce()) ?>" defer></script>
<?php endif; ?>
</body>
</html>
