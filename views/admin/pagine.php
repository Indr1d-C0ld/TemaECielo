<?php /** @var list<array<string,mixed>> $pagine */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Pagine redazionali</h1>
  <?= vista('admin/_nav') ?>
  <p class="condotto">
    Le pagine redazionali del portale: chi siamo, come si legge una carta, l'informativa.
    Si scrivono in Markdown e l'HTML non passa.
  </p>
  <p class="azioni"><a class="bottone bottone-primo" href="<?= e(url('/admin/pagine/0')) ?>">Pagina nuova</a></p>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th>Slug</th><th>Titolo</th><th>Stato</th><th>In menu</th><th class="destra">Ordine</th><th>Aggiornata</th></tr></thead>
      <tbody>
      <?php foreach ($pagine as $p): ?>
        <tr>
          <td class="num"><?= e((string) $p['slug']) ?></td>
          <td><a href="<?= e(url('/admin/pagine/' . (int) $p['id'])) ?>"><?= e((string) $p['titolo']) ?></a></td>
          <td><?= e((string) $p['stato']) ?></td>
          <td><?= ((int) $p['in_menu'] === 1) ? 's&igrave;' : '—' ?></td>
          <td class="num destra"><?= e((string) $p['ordine']) ?></td>
          <td class="num tenue"><?= e(date('d/m/y H:i', strtotime((string) $p['aggiornata']))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($pagine === []): ?><tr><td colspan="6" class="tenue">Nessuna pagina.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
