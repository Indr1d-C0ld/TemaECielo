<?php /** @var array<string,mixed> $p @var string $anteprima */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1><?= (int) $p['id'] > 0 ? e((string) $p['titolo']) : 'Pagina nuova' ?></h1>
  <?= vista('admin/_nav') ?>

  <form method="post" action="<?= e(url('/admin/pagine/' . (int) $p['id'])) ?>" class="modulo">
    <?= csrf() ?>
    <div class="coordinate">
      <div class="campo">
        <label for="slug">Slug <span class="facoltativo">(l&rsquo;indirizzo)</span></label>
        <input type="text" id="slug" name="slug" value="<?= e((string) $p['slug']) ?>" required
               pattern="[a-z0-9-]+" maxlength="80">
      </div>
      <div class="campo">
        <label for="stato">Stato</label>
        <select id="stato" name="stato">
          <option value="bozza"      <?= $p['stato'] === 'bozza' ? 'selected' : '' ?>>bozza</option>
          <option value="pubblicata" <?= $p['stato'] === 'pubblicata' ? 'selected' : '' ?>>pubblicata</option>
        </select>
      </div>
      <div class="campo">
        <label for="ordine">Ordine nel menu</label>
        <input type="number" id="ordine" name="ordine" value="<?= e((string) $p['ordine']) ?>">
      </div>
    </div>

    <div class="campo">
      <label class="scelta">
        <input type="checkbox" name="in_menu" value="1" <?= (int) $p['in_menu'] === 1 ? 'checked' : '' ?>>
        <span class="scelta-corpo"><span class="scelta-titolo">Mostra nel men&ugrave; del pi&egrave; di pagina</span></span>
      </label>
    </div>

    <div class="campo">
      <label for="titolo">Titolo</label>
      <input type="text" id="titolo" name="titolo" value="<?= e((string) $p['titolo']) ?>" required maxlength="160">
    </div>
    <div class="campo">
      <label for="sottotitolo">Sottotitolo</label>
      <input type="text" id="sottotitolo" name="sottotitolo" value="<?= e((string) $p['sottotitolo']) ?>" maxlength="255">
    </div>
    <div class="campo">
      <label for="corpo">Corpo <span class="facoltativo">(Markdown)</span></label>
      <textarea id="corpo" name="corpo" rows="18"><?= e((string) $p['corpo']) ?></textarea>
      <p class="aiuto">
        Titoli con <code>#</code>, grassetto con <code>**</code>, elenchi con <code>-</code>,
        collegamenti con <code>[testo](indirizzo)</code>. L&rsquo;HTML non passa: il testo viene
        scappato prima di qualunque trasformazione.
      </p>
    </div>

    <div class="azioni">
      <button type="submit" class="bottone bottone-primo">Salva</button>
      <?php if ((int) $p['id'] > 0): ?>
        <a class="bottone" href="<?= e(url('/pagina/' . (string) $p['slug'])) ?>">Guardala</a>
        <button type="submit" name="azione" value="elimina" class="bottone bottone-male">Elimina</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($anteprima !== ''): ?>
    <h2>Anteprima</h2>
    <div class="prosa anteprima"><?= $anteprima ?></div>
  <?php endif; ?>
</article>
