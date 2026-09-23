<?php
/**
 * La lettura mondiale di una carta: fondazione, evento, ingresso, lunazione,
 * eclissi, congiunzione. La compone Corpus\Mondana.
 *
 * @var array{introduzione:string,sezioni:list<array<string,mixed>>} $lettura
 */
?>
<section class="lettura lettura-mondiale">
  <h2>La lettura mondiale</h2>
  <p class="condotto registro-spiega"><?= e($lettura['introduzione']) ?></p>
  <?php foreach ($lettura['sezioni'] as $sezione): ?>
    <h3 class="lettura-sezione"><?= e($sezione['titolo']) ?></h3>
    <?php foreach ($sezione['voci'] as $v): ?>
      <article class="voce<?= $v['perche'] === 'angolare' ? ' voce-angolare' : '' ?>"
               data-corpi="<?= e(implode(' ', $v['corpi'])) ?>">
        <h4><?= e($v['titolo']) ?> <span class="voce-perche"><?= e($v['perche']) ?></span></h4>
        <p><?= e($v['corpo']) ?></p>
      </article>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <p class="nota-piccola">
    Le corrispondenze sono quelle dell'astrologia mondiale classica, raccolte da Baigent, Campion
    e Harvey in <em>Mundane Astrology</em> (1984). Sono chiavi di lettura, non previsioni.
  </p>
</section>
