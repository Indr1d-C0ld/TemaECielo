<?php
/**
 * La carta di un istante mondiale, eretta per un luogo.
 *
 * @var string $nome @var string $tipo @var float $jd @var array<string,mixed> $luogo
 * @var array<string,mixed> $tema @var array<string,mixed> $lettura
 * @var list<array{nome:string,slug:string,punto:string,distanza:float}> $colpi
 * @var array<string,string> $parametri i parametri dell'istante, per rifare la carta altrove
 */
use App\Astro\Corpi;
use App\Grafica\RuotaTema;
use App\Mondo\Mondo;

$locale = Mondo::locale($jd, $luogo['fuso']);
$occhiello = [
    'ingresso' => 'Carta d\'ingresso', 'novilunio' => 'Carta di lunazione', 'plenilunio' => 'Carta di lunazione',
    'eclissi' => 'Carta d\'eclissi', 'congiunzione' => 'Carta di congiunzione', 'istante' => 'Carta di un istante',
][$tipo] ?? 'Carta mondiale';
$rom = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$altrove = $luogo['chiave'] !== '' ? ['luogo' => $luogo['chiave']] : ['altrove' => $luogo['nome']];
?>
<article class="cartiglio">
  <p class="occhiello"><a href="<?= e(url('/mondo')) ?>">Astrologia mondiale</a> &middot; <?= e($occhiello) ?></p>
  <h1><?= e($nome) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <dl class="anagrafe">
    <div><dt>Istante</dt><dd><?= e($locale->format('j/n/Y H:i')) ?> <span class="tenue"><?= e($locale->format('P')) ?></span></dd></div>
    <div><dt>Tempo universale</dt><dd><?= e(gmdate('j/n/Y H:i', Mondo::unix($jd))) ?></dd></div>
    <div><dt>Eretta per</dt><dd><?= e($luogo['nome']) ?></dd></div>
    <div><dt>Case</dt><dd>Placido</dd></div>
  </dl>

  <?= vista('partials/mondo-luogo', ['luogo' => $luogo, 'azione' => '/mondo/carta',
        'nascosti' => $parametri]) ?>

  <?php if ($colpi !== []): ?>
    <p class="scheda-archivio">
      <span class="tenue">Il grado di questa carta cade su</span>
      <?php foreach (array_slice($colpi, 0, 8) as $i => $c): ?><?= $i > 0 ? ', ' : '' ?>
        <?= e($c['punto']) ?> di <a href="<?= e(url('/archivio/' . $c['slug'])) ?>"><?= e($c['nome']) ?></a>
        <span class="tenue">(<?= e(number_format($c['distanza'], 1, ',', '')) ?>&deg;)</span><?php endforeach; ?>
    </p>
  <?php endif; ?>

  <figure class="ruota-riquadro">
    <?= (new RuotaTema($tema))->disegna() ?>
    <figcaption>Ascendente a sinistra, case in senso antiorario, per <?= e($luogo['nome']) ?>.</figcaption>
  </figure>

  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th>Corpo</th><th>Posizione</th><th>Casa</th></tr></thead>
      <tbody>
      <?php foreach (Corpi::dieci() as $k): if (!isset($tema['corpi'][$k])) { continue; } $c = $tema['corpi'][$k]; ?>
        <tr>
          <td><?= glifo($k) ?> <?= e((string) $c['nome']) ?><?= !empty($c['retrogrado']) ? ' <span class="tenue">R</span>' : '' ?></td>
          <td class="num"><?= e((string) $c['posizione']) ?></td>
          <td class="num"><?= e($rom[(int) ($c['casa'] ?? 0)] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
        <tr><td>Ascendente</td><td class="num"><?= e((string) ($tema['punti']['asc']['posizione'] ?? '')) ?></td><td></td></tr>
        <tr><td>Medio Cielo</td><td class="num"><?= e((string) ($tema['punti']['mc']['posizione'] ?? '')) ?></td><td></td></tr>
      </tbody>
    </table>
  </div>

  <?= vista('partials/lettura-mondiale', ['lettura' => $lettura]) ?>
</article>
