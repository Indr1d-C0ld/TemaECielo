<?php
/**
 * Sinastria rapida: due segni e basta.
 *
 * @var int|null $a @var int|null $b @var array<string,mixed>|null $esito
 */
$segni = \App\Astro\Corpi::segni();
?>
<article class="cartiglio">
  <p class="occhiello">Rapporti</p>
  <h1>Sinastria</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Due segni, nessuna data: il ritratto di un rapporto a grandi linee. Per l'analisi vera
    servono due carte intere &mdash; <a href="<?= e(url('/calcola')) ?>">calcola un tema</a> e
    da l&igrave; potrai confrontarlo con chiunque.
  </p>

  <form method="get" action="<?= e(url('/sinastria')) ?>" class="coppia-segni">
    <div class="campo">
      <label for="a">Primo segno</label>
      <select id="a" name="a">
        <?php foreach ($segni as $i => $s): ?>
          <option value="<?= e((string) $i) ?>" <?= $a === $i ? 'selected' : '' ?>><?= e($s['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <span class="coppia-piu">&amp;</span>
    <div class="campo">
      <label for="b">Secondo segno</label>
      <select id="b" name="b">
        <?php foreach ($segni as $i => $s): ?>
          <option value="<?= e((string) $i) ?>" <?= $b === $i ? 'selected' : '' ?>><?= e($s['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="bottone bottone-primo">Guarda</button>
  </form>

  <?php if ($esito !== null): ?>
    <section class="esito-coppia">
      <div class="coppia-titolo">
        <?= glifo($segni[$esito['segni'][0]]['glifo'], 'el-' . $esito['elementi'][0]) ?>
        <h2><?= e($esito['titolo']) ?></h2>
        <?= glifo($segni[$esito['segni'][1]]['glifo'], 'el-' . $esito['elementi'][1]) ?>
      </div>
      <p class="coppia-sottotitolo">
        <?= e(ucfirst($esito['elementi'][0])) ?> <?= e($esito['modalita'][0]) ?>
        &middot; <?= e($esito['aspetto']) ?> &middot;
        <?= e(ucfirst($esito['elementi'][1])) ?> <?= e($esito['modalita'][1]) ?>
      </p>

      <?php foreach ($esito['paragrafi'] as $p): ?>
        <article class="voce voce-composto">
          <h4><?= e($p['titolo']) ?></h4>
          <p><?= e($p['corpo']) ?></p>
        </article>
      <?php endforeach; ?>

      <p class="nota-piccola">
        Questo &egrave; il rapporto fra due <em>segni</em>, non fra due persone: il segno solare
        &egrave; una fetta di carta su dodici. Due Ariete possono somigliarsi pochissimo se
        tutto il resto diverge. Per il rapporto vero servono le due carte intere.
      </p>
    </section>
  <?php endif; ?>
</article>
