<?php
/** @var array<string,string> $filtri @var list<array<string,mixed>> $testi @var list<array<string,mixed>> $ambiti */
$pagine = max(1, (int) ceil($totale / $per));
$q = static function (array $sopra) use ($filtri, $pagina): string {
    $p = array_merge(['ambito' => $filtri['ambito'], 'registro' => $filtri['registro'],
                      'cerca' => $filtri['cerca'], 'p' => $pagina], $sopra);
    return url('/admin/corpus?' . http_build_query(array_filter($p, static fn ($v): bool => $v !== '' && $v !== 0)));
};
?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Corpus</h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    I testi interpretativi, nei due registri. Si correggono da qui: nessun file da toccare,
    nessun rilascio da fare. Per sapere <em>che cosa</em> scrivere per primo,
    <a href="<?= e(url('/admin/corpus/copertura')) ?>">la copertura</a>.
  </p>

  <form method="get" action="<?= e(url('/admin/corpus')) ?>" class="filtri">
    <input type="search" name="cerca" value="<?= e($filtri['cerca']) ?>" placeholder="Cerca nel testo o nella chiave&hellip;">
    <select name="ambito">
      <option value="">tutti gli ambiti</option>
      <?php foreach ($ambiti as $a): ?>
        <option value="<?= e((string) $a['ambito']) ?>" <?= $filtri['ambito'] === $a['ambito'] ? 'selected' : '' ?>>
          <?= e((string) $a['ambito']) ?> (<?= e((string) $a['n']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <select name="registro">
      <option value="">entrambi i registri</option>
      <option value="tradizionale" <?= $filtri['registro'] === 'tradizionale' ? 'selected' : '' ?>>tradizionale</option>
      <option value="moderno"      <?= $filtri['registro'] === 'moderno' ? 'selected' : '' ?>>moderno</option>
    </select>
    <button type="submit" class="bottone">Filtra</button>
  </form>

  <p class="tenue piccolo"><?= e(number_format((float) $totale, 0, ',', '.')) ?> voci</p>

  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr>
        <th>Ambito</th><th>Chiave</th><th>Reg.</th><th>Titolo</th>
        <th class="destra">Peso</th><th class="destra">Usi</th><th>Stato</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($testi as $t): ?>
        <tr>
          <td class="num tenue"><?= e((string) $t['ambito']) ?></td>
          <td class="num"><?= e((string) $t['chiave']) ?></td>
          <td class="tenue"><?= e(mb_substr((string) $t['registro'], 0, 4)) ?></td>
          <td><?= e(mb_strimwidth((string) $t['titolo'], 0, 46, '&hellip;')) ?></td>
          <td class="num destra"><?= e((string) $t['peso']) ?></td>
          <td class="num destra"><?= e(number_format((float) $t['usi'], 0, ',', '.')) ?></td>
          <td><?= $t['stato'] === 'pubblicato' ? '' : '<span class="bollino">bozza</span>' ?></td>
          <td><a href="<?= e(url('/admin/corpus/' . (int) $t['id'])) ?>">modifica</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($testi === []): ?><tr><td colspan="8" class="tenue">Nessuna voce con questi filtri.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= e($q(['p' => $pagina - 1])) ?>">&larr; precedenti</a><?php endif; ?>
      <span><?= e((string) $pagina) ?> di <?= e((string) $pagine) ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= e($q(['p' => $pagina + 1])) ?>">successive &rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</article>
