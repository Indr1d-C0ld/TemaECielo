<?php /** @var list<array<string,mixed>> $righe */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Registro azioni</h1>
  <?= vista('admin/_nav') ?>
  <p class="condotto">Ogni azione compiuta dalla regia, con chi l'ha compiuta. È la ragione
    per cui questo portale ha credenziali proprie e non un file di password condiviso.</p>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th>Quando</th><th>Chi</th><th>Azione</th><th>Oggetto</th><th>Dettaglio</th></tr></thead>
      <tbody>
      <?php foreach ($righe as $r): ?>
        <tr>
          <td class="num"><?= e(date('d/m/y H:i:s', strtotime((string) $r['quando']))) ?></td>
          <td><?= e((string) $r['admin_utente']) ?></td>
          <td class="num"><?= e((string) $r['azione']) ?></td>
          <td class="tenue"><?= e((string) $r['oggetto']) ?: '—' ?></td>
          <td class="tenue"><?= e((string) $r['dettaglio']) ?: '—' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($righe === []): ?><tr><td colspan="5" class="tenue">Registro vuoto.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
