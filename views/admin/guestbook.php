<?php
/** @var string $stato @var array<string,int> $conteggi @var list<array<string,mixed>> $messaggi */
$pagine = max(1, (int) ceil($totale / $per));
$etichette = ['coda' => 'In coda', 'approvato' => 'Approvati', 'rifiutato' => 'Rifiutati', 'cestino' => 'Cestino'];
?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Guestbook</h1>
  <?= vista('admin/_nav') ?>

  <nav class="linguette">
    <?php foreach ($etichette as $k => $eti): ?>
      <a class="linguetta" href="<?= e(url('/admin/guestbook?stato=' . $k)) ?>"
         aria-selected="<?= $stato === $k ? 'true' : 'false' ?>">
        <?= e($eti) ?> <span class="conteggio"><?= e((string) ($conteggi[$k] ?? 0)) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($messaggi === []): ?>
    <p class="condotto tenue">Niente in questa coda.</p>
  <?php endif; ?>

  <?php foreach ($messaggi as $m): ?>
    <article class="moderazione">
      <header>
        <strong><?= e((string) $m['nome']) ?></strong>
        <span class="tenue piccolo"><?= e(date('j/n/Y H:i', strtotime((string) $m['creato']))) ?></span>
        <span class="tenue piccolo num"><?= e((string) $m['ip']) ?></span>
        <?php if ($m['paese'] !== null): ?><span class="bollino"><?= e((string) $m['paese']) ?></span><?php endif; ?>
        <?php if ($m['gettone'] !== null): ?>
          <a class="piccolo" href="<?= e(url('/carta/' . (string) $m['gettone'])) ?>">la carta votata</a>
        <?php endif; ?>
        <span class="tenue piccolo">
          gradimento <?= $m['voto_gradimento'] !== null ? e((string) $m['voto_gradimento']) : '—' ?>
          &middot; attinenza <?= $m['voto_attinenza'] !== null ? e((string) $m['voto_attinenza']) : '—' ?>
        </span>
      </header>

      <?php if ($m['moderato_da'] !== ''): ?>
        <p class="tenue piccolo">
          Moderato da <?= e((string) $m['moderato_da']) ?>
          il <?= e(date('j/n/Y H:i', strtotime((string) $m['moderato_il']))) ?>
          <?= $m['nota_admin'] !== '' ? '&mdash; ' . e((string) $m['nota_admin']) : '' ?>
        </p>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/admin/guestbook/' . (int) $m['id'])) ?>" class="modulo-moderazione">
        <?= csrf() ?>
        <input type="hidden" name="stato_attuale" value="<?= e($stato) ?>">

        <textarea name="messaggio" rows="4"><?= e((string) $m['messaggio']) ?></textarea>
        <input type="text" name="nota" placeholder="Nota interna (non pubblica)" maxlength="500"
               value="<?= e((string) $m['nota_admin']) ?>">

        <div class="azioni-moderazione">
          <?php foreach ([
            'approva'  => ['Approva', 'bene'],
            'rifiuta'  => ['Rifiuta', ''],
            'modifica' => ['Salva il testo', ''],
            'cestina'  => ['Cestina', ''],
            'blocca'   => ['Blocca l&rsquo;indirizzo', 'male'],
          ] as $azione => [$eti, $tinta]): ?>
            <button type="submit" name="azione" value="<?= e($azione) ?>"
                    class="bottone <?= $tinta === 'bene' ? 'bottone-primo' : '' ?> <?= $tinta === 'male' ? 'bottone-male' : '' ?>">
              <?= $eti ?>
            </button>
          <?php endforeach; ?>
        </div>

        <details class="avanzate">
          <summary>Rispondi pubblicamente</summary>
          <div class="campo">
            <textarea name="risposta" rows="3" placeholder="La risposta comparir&agrave; sotto il messaggio."></textarea>
          </div>
          <button type="submit" name="azione" value="rispondi" class="bottone">Pubblica la risposta</button>
        </details>
      </form>
    </article>
  <?php endforeach; ?>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= e(url('/admin/guestbook?stato=' . $stato . '&p=' . ($pagina - 1))) ?>">&larr;</a><?php endif; ?>
      <span><?= e((string) $pagina) ?> di <?= e((string) $pagine) ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= e(url('/admin/guestbook?stato=' . $stato . '&p=' . ($pagina + 1))) ?>">&rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</article>
