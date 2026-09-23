<?php
/**
 * L'archivio pubblico.
 *
 * @var array{tipo:string,categoria:string,q:string,secolo:int} $filtri
 * @var list<array<string,mixed>> $righe @var int $totale @var int $pagina @var int $per
 * @var array<string,array<string,int>> $conteggi
 */
use App\Archivio\Archivio;
use App\Astro\Corpi;

$pagine = max(1, (int) ceil($totale / $per));
$link = static function (array $sopra) use ($filtri): string {
    $p = array_merge($filtri, $sopra);
    // Si tolgono i filtri vuoti, e la pagina 1 che e' quella predefinita.
    if (($p['p'] ?? 1) === 1) { unset($p['p']); }
    return url('/archivio?' . http_build_query(array_filter($p, static fn ($v): bool => $v !== '' && $v !== 0)));
};
$segno = static function (?string $i, string $etichetta): string {
    if ($i === null || $i === '' || !is_numeric($i)) { return ''; }
    $s = Corpi::segni()[(int) $i];
    return '<span class="voce-segno" title="' . e($etichetta . ' in ' . $s['nome']) . '">'
        . '<span class="tenue">' . e($etichetta) . '</span> ' . glifo((string) $s['glifo'], 'el-' . $s['elemento']) . '</span>';
};
$totTipo = static fn (string $t): int => array_sum($conteggi[$t] ?? []);
// Le categorie dell'elenco a tendina: quelle del tipo scelto o, senza tipo, di
// tutti i tipi con i conteggi sommati — «politica» esiste per persone ed eventi,
// e comparire due volte con due numeri diversi non aiuta nessuno.
$categorie = [];
foreach ($filtri['tipo'] !== '' ? [$filtri['tipo'] => $conteggi[$filtri['tipo']] ?? []] : $conteggi as $perTipo) {
    foreach ($perTipo as $c => $n) {
        $categorie[$c] = ($categorie[$c] ?? 0) + $n;
    }
}
ksort($categorie);
?>
<article class="cartiglio">
  <p class="occhiello">Persone, eventi, nazioni</p>
  <h1>L'archivio</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Carte calcolate e documentate dalla regia: persone che hanno lasciato un segno, eventi che hanno
    cambiato la storia, fondazioni di Stati e di istituzioni. Ogni carta dichiara da dove vengono
    i dati e quanto ci si pu&ograve; fidare dell'ora, con la classe di affidabilit&agrave; di Lois Rodden.
    Si aprono come ogni altra carta, e si possono confrontare con la tua.
  </p>

  <nav class="linguette" aria-label="Tipo">
    <a class="linguetta" href="<?= e($link(['tipo' => '', 'categoria' => '', 'p' => 1])) ?>" <?= $filtri['tipo'] === '' ? 'aria-current="page"' : '' ?>>Tutto</a>
    <?php foreach (Archivio::TIPI as $k => $n): ?>
      <a class="linguetta" href="<?= e($link(['tipo' => $k, 'categoria' => '', 'p' => 1])) ?>" <?= $filtri['tipo'] === $k ? 'aria-current="page"' : '' ?>>
        <?= e($n) ?> <span class="conteggio tenue"><?= e((string) $totTipo($k)) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form method="get" action="<?= e(url('/archivio')) ?>" class="filtri">
    <?php if ($filtri['tipo'] !== ''): ?><input type="hidden" name="tipo" value="<?= e($filtri['tipo']) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($filtri['q']) ?>" placeholder="Nome o luogo&hellip;" aria-label="Cerca nell'archivio">
    <select name="categoria" aria-label="Categoria">
      <option value="">ogni categoria</option>
      <?php foreach ($categorie as $c => $n): ?>
        <option value="<?= e($c) ?>" <?= $filtri['categoria'] === $c ? 'selected' : '' ?>><?= e($c) ?> (<?= e((string) $n) ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="secolo" aria-label="Secolo">
      <option value="">ogni secolo</option>
      <?php foreach ([19 => 'Ottocento', 20 => 'Novecento', 21 => 'Duemila'] as $sec => $n): ?>
        <option value="<?= e((string) $sec) ?>" <?= $filtri['secolo'] === $sec ? 'selected' : '' ?>><?= e($n) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="bottone">Cerca</button>
  </form>

  <?php if ($righe === []): ?>
    <p class="condotto tenue">Nessuna voce con questi criteri.</p>
  <?php else: ?>
    <p class="tenue piccolo"><?= e((string) $totale) ?> <?= $totale === 1 ? 'voce' : 'voci' ?>, in ordine di data</p>
    <ul class="archivio-voci">
      <?php foreach ($righe as $v): ?>
        <li class="voce-archivio">
          <a class="voce-nome" href="<?= e(url('/archivio/' . $v['slug'])) ?>"><?= e((string) $v['nome']) ?></a>
          <span class="voce-quando num">
            <?= e(date('j/n/Y', strtotime((string) $v['data_nascita']))) ?>
            <?= $v['ora_nascita'] !== null ? e(substr((string) $v['ora_nascita'], 0, 5)) : '' ?>
          </span>
          <span class="voce-dove tenue"><?= e(explode(',', (string) $v['luogo_nome'])[0]) ?></span>
          <span class="voce-segni">
            <?= $segno($v['segno_sole'], 'Sole') ?><?= $segno($v['segno_luna'], 'Luna') ?>
            <?php // L'Ascendente solo con un'ora affidabile, come nei «colpi» del mondo. ?>
            <?= $v['ora_nascita'] !== null && in_array($v['rodden'], ['AA', 'A', 'B'], true) ? $segno($v['segno_asc'], 'Asc') : '' ?>
          </span>
          <span class="voce-meta">
            <span class="bollino"><?= e((string) $v['categoria']) ?></span>
            <span class="bollino rodden-<?= e(strtolower((string) $v['rodden'])) ?>" title="<?= e(Archivio::RODDEN[$v['rodden']][0] ?? '') ?>"><?= e((string) $v['rodden']) ?></span>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($pagine > 1): ?>
    <nav class="paginazione">
      <?php if ($pagina > 1): ?><a href="<?= e($link(['p' => $pagina - 1])) ?>">&larr; precedenti</a><?php endif; ?>
      <span><?= e((string) $pagina) ?> di <?= e((string) $pagine) ?></span>
      <?php if ($pagina < $pagine): ?><a href="<?= e($link(['p' => $pagina + 1])) ?>">successive &rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>

  <details class="avanzate">
    <summary>Le classi di Rodden</summary>
    <dl class="definizioni-rodden">
      <?php foreach (Archivio::RODDEN as $k => [$n, $d]): ?>
        <div><dt><span class="bollino rodden-<?= e(strtolower($k)) ?>"><?= e($k) ?></span> <?= e($n) ?></dt><dd><?= e($d) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </details>
</article>
