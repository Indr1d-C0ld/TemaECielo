<?php
/** @var list<array<string,mixed>> $perRegistro @var list<array<string,mixed>> $daScrivere */
$griglia = [];
foreach ($perRegistro as $r) {
    $griglia[$r['ambito']][$r['registro']] = (int) $r['n'];
}
?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Copertura del corpus</h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    Scrivere a mano tutte le voci &egrave; il lavoro di anni. Farlo in ordine alfabetico sarebbe
    uno spreco: questa pagina dice che cosa il portale ha dovuto <em>comporre</em> pi&ugrave;
    spesso, e quindi che cosa conviene scrivere per primo.
  </p>

  <div class="numeri">
    <div class="numero">
      <span class="numero-val"><?= e(number_format((float) $letture, 0, ',', '.')) ?></span>
      <span class="numero-eti">passaggi montati</span>
    </div>
    <div class="numero">
      <span class="numero-val"><?= e(number_format((float) $usate, 0, ',', '.')) ?></span>
      <span class="numero-eti">voci distinte usate</span>
    </div>
  </div>

  <h2>Voci per ambito e registro</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th>Ambito</th><th class="destra">Tradizionale</th><th class="destra">Moderno</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($griglia as $ambito => $conte):
        $t = $conte['tradizionale'] ?? 0; $m = $conte['moderno'] ?? 0; ?>
        <tr>
          <td class="num"><?= e((string) $ambito) ?></td>
          <td class="num destra"><?= e((string) $t) ?></td>
          <td class="num destra"><?= e((string) $m) ?></td>
          <td><?= $t !== $m ? '<span class="bollino bollino-attento">registri sbilanciati</span>' : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>Da scrivere, in ordine di urgenza</h2>
  <?php if ($daScrivere === []): ?>
    <p class="condotto">
      Nessuna voce composta risulta ancora usata. L&rsquo;elenco si riempie man mano che le
      carte vengono lette: e&rsquo; il portale stesso a dire che cosa gli serve.
    </p>
  <?php else: ?>
    <p class="condotto">
      Queste combinazioni sono state incontrate davvero e risolte coi frammenti. Le pi&ugrave;
      frequenti in cima.
    </p>
    <div class="tabella-scorre">
      <table class="griglia fitta">
        <thead><tr><th>Ambito</th><th>Chiave</th><th class="destra">Volte</th>
          <th>Tradizionale</th><th>Moderno</th><th>Ultima</th></tr></thead>
        <tbody>
        <?php foreach ($daScrivere as $d): ?>
          <tr>
            <td class="num tenue"><?= e((string) $d['ambito']) ?></td>
            <td class="num"><?= e((string) $d['chiave']) ?></td>
            <td class="num destra"><?= e(number_format((float) $d['usi'], 0, ',', '.')) ?></td>
            <td><?= ((int) $d['ha_trad'] > 0) ? '<span class="bollino bollino-bene">scritta</span>' : '<span class="bollino">composta</span>' ?></td>
            <td><?= ((int) $d['ha_mod'] > 0) ? '<span class="bollino bollino-bene">scritta</span>' : '<span class="bollino">composta</span>' ?></td>
            <td class="num tenue"><?= e(date('j/n/y', strtotime((string) $d['ultimo']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</article>
