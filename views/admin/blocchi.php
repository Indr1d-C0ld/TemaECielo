<?php /** @var list<array<string,mixed>> $blocchi */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Blocchi</h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    Indirizzi e reti che non possono firmare il guestbook. Si blocca un indirizzo singolo
    (<code>203.0.113.9</code>) o un&rsquo;intera rete (<code>203.0.113.0/24</code>) &mdash; ma
    dietro un solo indirizzo pubblico ci pu&ograve; stare un condominio o un ateneo, quindi
    bloccare una rete per un messaggio &egrave; sproporzionato.
  </p>

  <form method="post" action="<?= e(url('/admin/blocchi')) ?>" class="filtri">
    <?= csrf() ?>
    <input type="text" name="cidr" placeholder="203.0.113.9 oppure 203.0.113.0/24" required>
    <input type="text" name="motivo" placeholder="Motivo" maxlength="255">
    <button type="submit" class="bottone bottone-primo">Blocca</button>
  </form>

  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th>Indirizzo o rete</th><th>Motivo</th><th>Da</th><th>Quando</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($blocchi as $b): ?>
        <tr>
          <td class="num"><?= e((string) $b['cidr']) ?></td>
          <td class="tenue"><?= e((string) $b['motivo']) ?: '—' ?></td>
          <td class="tenue"><?= e((string) $b['creato_da']) ?></td>
          <td class="num tenue"><?= e(date('j/n/y H:i', strtotime((string) $b['creato']))) ?></td>
          <td>
            <form method="post" action="<?= e(url('/admin/blocchi')) ?>" class="in-riga">
              <?= csrf() ?>
              <input type="hidden" name="azione" value="togli">
              <input type="hidden" name="id" value="<?= e((string) $b['id']) ?>">
              <button type="submit" class="collegamento">togli</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($blocchi === []): ?><tr><td colspan="5" class="tenue">Nessun blocco.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</article>
