<?php
/** @var array<string,mixed> $testo @var int $usi */
$servePosto = in_array($testo['ambito'], ['aspetto_relazione', 'dignita'], true)
    && str_contains((string) $testo['corpo'], '%s');
?>
<article class="cartiglio cartiglio-stretto">
  <p class="occhiello"><?= e((string) $testo['ambito']) ?> &middot; <?= e((string) $testo['registro']) ?></p>
  <h1><?= e((string) $testo['chiave']) ?></h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    Usata <strong><?= e(number_format((float) $usi, 0, ',', '.')) ?></strong>
    volt<?= $usi === 1 ? 'a' : 'e' ?> nelle relazioni finora.
  </p>

  <?php if (in_array($testo['ambito'], ['pianeta', 'segno_modo', 'casa_campo', 'aspetto_relazione'], true)): ?>
    <p class="lampo lampo-attento">
      Questo &egrave; un <strong>frammento</strong>: non compare mai da solo, ma entra in decine
      di voci composte. Cambiarlo cambia tutte insieme le frasi che lo usano.
      <?php if ($testo['ambito'] === 'pianeta'): ?>
        Il <em>titolo</em> dev'essere un gruppo nominale minuscolo con l'articolo
        (&laquo;il centro della coscienza&raquo;): viene incollato dopo una preposizione e
        articolato in automatico.
      <?php elseif ($testo['ambito'] === 'segno_modo' || $testo['ambito'] === 'casa_campo'): ?>
        Il <em>corpo</em> deve continuare la frase &laquo;&lt;Il pianeta&gt; &hellip;&raquo;:
        comincia con un verbo, non con una maiuscola.
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if ($servePosto): ?>
    <p class="lampo lampo-attento">
      Il testo contiene <code>%s</code>, dove il portale mette il nome del corpo celeste.
      Non toglierlo: senza, ogni carta stamperebbe una frase monca.
    </p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/admin/corpus/' . (int) $testo['id'])) ?>" class="modulo">
    <?= csrf() ?>

    <div class="campo">
      <label for="titolo">Titolo</label>
      <input type="text" id="titolo" name="titolo" maxlength="160" value="<?= e((string) $testo['titolo']) ?>">
    </div>

    <div class="campo">
      <label for="corpo">Corpo</label>
      <textarea id="corpo" name="corpo" rows="10" required><?= e((string) $testo['corpo']) ?></textarea>
    </div>

    <div class="coordinate">
      <div class="campo">
        <label for="peso">Peso <span class="facoltativo">(0&ndash;10)</span></label>
        <input type="number" id="peso" name="peso" min="0" max="10" value="<?= e((string) $testo['peso']) ?>">
      </div>
      <div class="campo">
        <label for="stato">Stato</label>
        <select id="stato" name="stato">
          <option value="pubblicato" <?= $testo['stato'] === 'pubblicato' ? 'selected' : '' ?>>pubblicato</option>
          <option value="bozza"      <?= $testo['stato'] === 'bozza' ? 'selected' : '' ?>>bozza</option>
        </select>
      </div>
      <div class="campo">
        <label for="etichette">Etichette</label>
        <input type="text" id="etichette" name="etichette" maxlength="190" value="<?= e((string) $testo['etichette']) ?>">
      </div>
    </div>
    <p class="aiuto">
      Il <strong>peso</strong> e&rsquo; la rilevanza di base: il montatore la corregge poi secondo
      quello che la carta dice davvero. Le <strong>etichette</strong>, separate da spazi, servono a
      riconoscere le voci che dicono la stessa cosa e a non ripeterle.
    </p>

    <button type="submit" class="bottone bottone-primo">Salva</button>
  </form>

  <p class="nota-piccola">
    <a href="<?= e(url('/admin/corpus')) ?>">&larr; torna all&rsquo;elenco</a>
    &middot; ultima modifica <?= e(date('j/n/Y H:i', strtotime((string) $testo['aggiornato']))) ?>
  </p>
</article>
