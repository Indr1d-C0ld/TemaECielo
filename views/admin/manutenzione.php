<?php /** @var array<string,string> $stato @var list<array<string,mixed>> $partizioni */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Manutenzione</h1>
  <?= vista('admin/_nav') ?>

  <h2>Stato del sistema</h2>
  <table class="griglia definizioni">
    <tbody>
    <?php foreach ($stato as $voce => $valore): ?>
      <tr><th><?= e($voce) ?></th><td class="num"><?= e($valore) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Partizioni di <code>accessi</code></h2>
  <p class="condotto">Le nuove si aggiungono con <code>php bin/console.php partizioni</code>.</p>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th>Partizione</th><th class="destra">Righe (stima)</th></tr></thead>
      <tbody>
      <?php foreach ($partizioni as $p): ?>
        <tr><td class="num"><?= e((string) $p['nome']) ?></td>
            <td class="num destra"><?= e(number_format((float) $p['righe'], 0, ',', '.')) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
