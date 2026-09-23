<?php
/** @var int $pagina @var int $totale @var int $per @var list<array<string,mixed>> $righe */
$pagine = max(1, (int) ceil($totale / $per));
?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Registro accessi</h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    <?= e(number_format((float) $totale, 0, ',', '.')) ?> righe in tutto.
    Indirizzi completi, conservazione illimitata: la tabella è partizionata per mese
    perché regga la crescita. La geolocalizzazione è <strong>offline</strong> &mdash;
    nessun indirizzo di nessun visitatore esce da questo server.
  </p>

  <?php if (($paesi ?? []) !== [] || ($reti ?? []) !== []): ?>
    <div class="due">
      <?php if (($paesi ?? []) !== []): ?>
        <div>
          <h2>Da quali paesi</h2>
          <table class="griglia fitta"><tbody>
          <?php foreach ($paesi as $x): ?>
            <tr><td><?= e((string) $x['paese']) ?></td>
                <td class="num destra"><?= e(number_format((float) $x['n'], 0, ',', '.')) ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
      <?php if (($reti ?? []) !== []): ?>
        <div>
          <h2>Da quali reti</h2>
          <table class="griglia fitta"><tbody>
          <?php foreach ($reti as $x): ?>
            <tr><td class="troncato" title="<?= e((string) $x['operatore']) ?>"><?= e((string) $x['operatore']) ?></td>
                <td class="num tenue">AS<?= e((string) $x['asn']) ?></td>
                <td class="num destra"><?= e(number_format((float) $x['n'], 0, ',', '.')) ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr>
        <th>Quando</th><th>Indirizzo</th><th>Luogo</th><th>Operatore</th><th>Met.</th><th>Percorso</th>
        <th class="destra">St.</th><th class="destra">Byte</th><th class="destra">ms</th>
        <th>Agente</th><th>Referente</th>
      </tr></thead>
      <tbody>
      <?php foreach ($righe as $r): ?>
        <tr<?= (int) $r['bot'] === 1 ? ' class="robot"' : '' ?>>
          <td class="num"><?= e(date('d/m/y H:i:s', strtotime((string) $r['quando']))) ?></td>
          <td class="num"><?= e((string) $r['ip']) ?></td>
          <td class="tenue"><?= e(trim(((string) ($r['citta'] ?? '')) . ' ' . ((string) ($r['regione'] ?? '')) . ' ' . ((string) ($r['paese'] ?? '')))) ?: '—' ?></td>
          <td class="tenue troncato" title="<?= e((string) ($r['operatore'] ?? '')) ?>">
            <?= e((string) ($r['operatore'] ?? '')) ?: '—' ?></td>
          <td class="tenue"><?= e((string) $r['metodo']) ?></td>
          <td><?= e((string) $r['percorso']) ?></td>
          <td class="num destra"><?= e((string) $r['stato']) ?></td>
          <td class="num destra"><?= e(number_format((float) $r['byte_inviati'], 0, ',', '.')) ?></td>
          <td class="num destra"><?= e((string) $r['durata_ms']) ?></td>
          <td class="tenue"><?= e((string) $r['ua_famiglia'] . ' / ' . (string) $r['ua_so'] . ' / ' . (string) $r['dispositivo']) ?></td>
          <td class="tenue troncato"><?= e((string) $r['referente']) ?: '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= e(url('/admin/accessi?p=' . ($pagina - 1))) ?>">&larr; precedenti</a><?php endif; ?>
      <span><?= e((string) $pagina) ?> di <?= e((string) $pagine) ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= e(url('/admin/accessi?p=' . ($pagina + 1))) ?>">successivi &rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</article>
