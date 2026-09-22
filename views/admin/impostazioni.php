<?php /** @var list<array<string,mixed>> $impostazioni */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Impostazioni</h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    Decisioni redazionali, distinte dalla configurazione del server. Quella descrive
    <em>come</em> il portale &egrave; installato &mdash; database, percorsi, chiavi &mdash; e si
    cambia solo rimettendo le mani sulla macchina. Queste le prendi tu.
  </p>

  <form method="post" action="<?= e(url('/admin/impostazioni')) ?>" class="modulo">
    <?= csrf() ?>
    <?php foreach ($impostazioni as $i):
      $k = (string) $i['chiave']; ?>
      <div class="campo">
        <?php if ($i['tipo'] === 'booleano'): ?>
          <label class="scelta">
            <input type="checkbox" name="i_<?= e($k) ?>" value="1" <?= $i['valore'] === '1' ? 'checked' : '' ?>>
            <span class="scelta-corpo">
              <span class="scelta-titolo"><?= e((string) $i['descrizione']) ?></span>
              <span class="scelta-spiega"><code><?= e($k) ?></code></span>
            </span>
          </label>
        <?php elseif ($i['tipo'] === 'intero'): ?>
          <label for="i_<?= e($k) ?>"><?= e((string) $i['descrizione']) ?></label>
          <input type="number" id="i_<?= e($k) ?>" name="i_<?= e($k) ?>" min="0"
                 value="<?= e((string) $i['valore']) ?>">
          <p class="aiuto"><code><?= e($k) ?></code></p>
        <?php else: ?>
          <label for="i_<?= e($k) ?>"><?= e((string) $i['descrizione']) ?></label>
          <input type="text" id="i_<?= e($k) ?>" name="i_<?= e($k) ?>" maxlength="2000"
                 value="<?= e((string) $i['valore']) ?>">
          <p class="aiuto"><code><?= e($k) ?></code></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="bottone bottone-primo">Salva</button>
  </form>
</article>
