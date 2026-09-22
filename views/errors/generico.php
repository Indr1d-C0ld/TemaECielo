<?php
/** @var string $titolo @var int $stato @var string $messaggio */
?>
<article class="cartiglio cartiglio-solo">
  <p class="occhiello">Errore <?= e((string) $stato) ?></p>
  <h1><?= e($titolo) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto"><?= e($messaggio) ?></p>
  <p><a class="bottone" href="<?= e(url('/')) ?>">Torna alla soglia</a></p>
</article>
