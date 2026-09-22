<?php
/** @var array<string,int> $numeri @var list<array<string,mixed>> $ultimi */
$etichette = [
    'accessi_oggi'   => 'Accessi oggi',
    'sessioni_oggi'  => 'Sessioni oggi',
    'bot_oggi'       => 'Di cui robot',
    'accessi_totale' => 'Accessi in tutto',
    'eventi_oggi'    => 'Eventi oggi',
    'pagine'         => 'Pagine',
];
?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Cruscotto</h1>
  <?= vista('admin/_nav') ?>

  <div class="numeri">
    <?php foreach ($etichette as $chiave => $etichetta): ?>
      <div class="numero">
        <span class="numero-val"><?= e(number_format((float) ($numeri[$chiave] ?? 0), 0, ',', '.')) ?></span>
        <span class="numero-eti"><?= e($etichetta) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>Ultimi venticinque accessi</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr>
        <th>Quando</th><th>Indirizzo</th><th>Percorso</th>
        <th class="destra">Stato</th><th class="destra">ms</th><th>Agente</th>
      </tr></thead>
      <tbody>
      <?php foreach ($ultimi as $r): ?>
        <tr<?= (int) $r['bot'] === 1 ? ' class="robot"' : '' ?>>
          <td class="num"><?= e(date('d/m H:i:s', strtotime((string) $r['quando']))) ?></td>
          <td class="num"><?= e((string) $r['ip']) ?></td>
          <td><?= e((string) $r['percorso']) ?></td>
          <td class="num destra stato-<?= e((string) ((int) $r['stato'] / 100 | 0)) ?>"><?= e((string) $r['stato']) ?></td>
          <td class="num destra"><?= e((string) $r['durata_ms']) ?></td>
          <td class="tenue"><?= e(trim((string) $r['ua_famiglia'] . ' / ' . (string) $r['ua_so'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($ultimi === []): ?>
        <tr><td colspan="6" class="tenue">Nessun accesso registrato.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
