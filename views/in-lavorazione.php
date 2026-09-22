<?php
/** @var string $titolo @var string $fase @var string $cosa */
?>
<article class="cartiglio cartiglio-solo">
  <p class="occhiello">In costruzione</p>
  <h1><?= e($titolo) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto"><?= e($cosa) ?></p>
  <p class="fase">Arriva con la fase <strong><?= e($fase) ?></strong>.</p>
  <p><a class="bottone" href="<?= e(url('/')) ?>">Torna alla soglia</a></p>
</article>
