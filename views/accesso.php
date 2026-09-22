<?php
/** @var string $da @var string|null $errore */
?>
<article class="cartiglio cartiglio-stretto">
  <p class="occhiello">Area riservata</p>
  <h1>Accesso</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <?php if ($errore !== null): ?>
    <p class="lampo lampo-male" role="alert"><?= e($errore) ?></p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/accesso')) ?>" class="modulo" autocomplete="off">
    <?= csrf() ?>
    <input type="hidden" name="da" value="<?= e($da) ?>">

    <label for="utente">Nome utente</label>
    <input type="text" id="utente" name="utente" required autofocus
           autocapitalize="none" autocorrect="off" spellcheck="false">

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="bottone bottone-primo">Entra</button>
  </form>

  <p class="nota-piccola">
    Dopo cinque tentativi falliti l'indirizzo resta bloccato per un quarto d'ora.
    Ogni tentativo, riuscito o no, finisce nel registro.
  </p>
</article>
