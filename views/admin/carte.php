<?php
/**
 * La regia delle carte: tutte quelle salvate.
 *
 * @var list<array<string,mixed>> $righe @var string $q @var string $quali
 * @var int $pagina @var int $per @var int $totale @var array<string,int> $numeri
 */
$pagine = max(1, (int) ceil($totale / $per));
$link = static function (array $sopra) use ($q, $quali, $pagina): string {
    $p = array_merge(['q' => $q, 'quali' => $quali === 'tutte' ? '' : $quali, 'p' => $pagina], $sopra);
    return url('/admin/carte?' . http_build_query(array_filter($p, static fn ($v): bool => $v !== '' && $v !== 1)));
};
?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Carte salvate</h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    Tutte le carte calcolate sul portale, dei visitatori e tue. Da qui una carta pu&ograve; ricevere una
    <em>scheda</em> e finire nell'archivio pubblico, fra le persone, gli eventi e le nazioni.
    L'informativa dice ai visitatori che la regia pu&ograve; consultarle.
  </p>

  <div class="numeri">
    <div class="numero"><span class="numero-val"><?= e(number_format((float) $numeri['carte'], 0, ',', '.')) ?></span><span class="numero-eti">carte salvate</span></div>
    <div class="numero"><span class="numero-val"><?= e((string) $numeri['archivio']) ?></span><span class="numero-eti">con una scheda</span></div>
    <div class="numero"><span class="numero-val"><?= e((string) $numeri['pubblicate']) ?></span><span class="numero-eti">nell'archivio pubblico</span></div>
  </div>

  <form method="get" action="<?= e(url('/admin/carte')) ?>" class="filtri">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nome o luogo&hellip;" aria-label="Cerca per nome o luogo">
    <select name="quali" aria-label="Quali carte">
      <option value="">tutte</option>
      <option value="visitatori" <?= $quali === 'visitatori' ? 'selected' : '' ?>>senza scheda</option>
      <option value="archivio"   <?= $quali === 'archivio' ? 'selected' : '' ?>>con una scheda</option>
    </select>
    <button type="submit" class="bottone">Filtra</button>
    <a class="bottone-tenue" href="<?= e(url('/calcola')) ?>">Calcola una carta nuova</a>
  </form>

  <p class="tenue piccolo"><?= e(number_format((float) $totale, 0, ',', '.')) ?> carte</p>

  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr>
        <th>Nome</th><th>Nascita</th><th>Luogo</th><th>Salvata</th><th class="destra">Viste</th>
        <th>Archivio</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($righe as $c): ?>
        <tr>
          <td><a href="<?= e(url('/carta/' . $c['gettone'])) ?>"><?= e((string) $c['nome'] !== '' ? (string) $c['nome'] : 'senza nome') ?></a></td>
          <td class="num"><?= e(date('j/n/Y', strtotime((string) $c['data_nascita']))) ?>
            <?= $c['ora_nascita'] !== null ? e(substr((string) $c['ora_nascita'], 0, 5)) : '<span class="tenue">ora ignota</span>' ?></td>
          <td class="tenue"><?= e(mb_strimwidth((string) $c['luogo_nome'], 0, 38, '…')) ?></td>
          <td class="num tenue"><?= e(date('j/n/Y H:i', strtotime((string) $c['creato']))) ?></td>
          <td class="num destra"><?= e((string) $c['richieste']) ?></td>
          <td>
            <?php if ($c['slug'] !== null): ?>
              <span class="bollino"><?= e((string) $c['tipo']) ?> &middot; <?= e((string) $c['rodden']) ?></span>
              <?= (int) $c['pubblicata'] === 1 ? '' : '<span class="tenue">non pubblicata</span>' ?>
            <?php endif; ?>
          </td>
          <td><a href="<?= e(url('/admin/carte/' . $c['gettone'])) ?>"><?= $c['slug'] !== null ? 'scheda' : 'metti in archivio' ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($righe === []): ?><tr><td colspan="7" class="tenue">Nessuna carta con questi filtri.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= e($link(['p' => $pagina - 1])) ?>">&larr; pi&ugrave; recenti</a><?php endif; ?>
      <span><?= e((string) $pagina) ?> di <?= e((string) $pagine) ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= e($link(['p' => $pagina + 1])) ?>">pi&ugrave; vecchie &rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</article>
