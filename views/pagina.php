<?php
/** @var string $titolo @var string $sottotitolo @var string $corpo @var string $aggiornata */
?>
<article class="cartiglio">
  <h1><?= e($titolo) ?></h1>
  <?php if (($sottotitolo ?? '') !== ''): ?>
    <p class="condotto"><?= e($sottotitolo) ?></p>
  <?php endif; ?>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <div class="prosa"><?= $corpo ?></div>
  <p class="nota-piccola">Ultimo aggiornamento: <?= e(date('j/n/Y', strtotime($aggiornata))) ?></p>
</article>
