<?php
/**
 * L'anno mondiale: ingressi e lunazioni.
 *
 * @var int $anno @var array<string,mixed> $luogo
 * @var list<array<string,mixed>> $ingressi @var list<array<string,mixed>> $lunazioni
 */
use App\Mondo\Eclissi;
use App\Mondo\Mondo;

$stagioni = ['primavera', 'estate', 'autunno', 'inverno'];
$segniCardinali = ['Ariete', 'Cancro', 'Bilancia', 'Capricorno'];
$dove = $luogo['chiave'] !== '' ? ['luogo' => $luogo['chiave']] : ['altrove' => $luogo['nome']];
$carta = static fn (array $q): string => url('/mondo/carta?' . http_build_query($q + $dove));
$locale = static fn (float $jd): string => Mondo::locale($jd, $luogo['fuso'])->format('j/n H:i');
$ut = static fn (float $jd): string => gmdate('j/n H:i', Mondo::unix($jd));
?>
<article class="cartiglio">
  <p class="occhiello"><a href="<?= e(url('/mondo')) ?>">Astrologia mondiale</a></p>
  <h1>L'anno <?= e((string) $anno) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <nav class="mondo-scorri" aria-label="Anni">
    <?php if ($anno > Mondo::ANNO_MIN): ?><a href="<?= e(url('/mondo/anno?' . http_build_query(['anno' => $anno - 1] + $dove))) ?>">&larr; <?= $anno - 1 ?></a><?php endif; ?>
    <?php if ($anno < Mondo::ANNO_MAX): ?><a href="<?= e(url('/mondo/anno?' . http_build_query(['anno' => $anno + 1] + $dove))) ?>"><?= $anno + 1 ?> &rarr;</a><?php endif; ?>
  </nav>

  <?= vista('partials/mondo-luogo', ['luogo' => $luogo, 'azione' => '/mondo/anno', 'nascosti' => ['anno' => $anno]]) ?>

  <h2>Gli ingressi</h2>
  <p class="condotto">
    L'istante in cui il Sole entra in un segno cardinale apre una stagione. La carta di quell'istante,
    eretta per <?= e($luogo['nome']) ?>, &egrave; la carta della stagione per il paese; quella
    dell'ingresso in Ariete vale, per tradizione, per l'anno intero. Gli orari sono locali
    (<?= e($luogo['fuso']) ?>), con il Tempo Universale accanto.
  </p>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th>Stagione</th><th>Il Sole entra in</th><th>Ora locale</th><th>UT</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($ingressi as $i): $k = intdiv((int) $i['segno'], 3); ?>
        <tr>
          <td><?= e($stagioni[$k] ?? '') ?></td>
          <td><?= glifo(strtolower($segniCardinali[$k]), '') ?> <?= e($segniCardinali[$k]) ?></td>
          <td class="num"><?= e($locale((float) $i['jd'])) ?></td>
          <td class="num tenue"><?= e($ut((float) $i['jd'])) ?></td>
          <td><a href="<?= e($carta(['jd' => $i['jd'], 'tipo' => 'ingresso'])) ?>">la carta</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>Le lunazioni</h2>
  <p class="condotto">
    Il novilunio apre il mese lunare, il plenilunio ne &egrave; il culmine. Quando una lunazione cade
    vicino ai nodi della Luna diventa un'eclissi: qui &egrave; segnata.
  </p>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th></th><th>Lunazione</th><th>Grado</th><th>Ora locale</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($lunazioni as $l): $nuova = $l['tipo'] === 'novilunio'; ?>
        <tr<?= isset($l['eclissi']) ? ' class="riga-eclissi"' : '' ?>>
          <td aria-hidden="true"><?= $nuova ? '&#9679;' : '&#9675;' ?></td>
          <td><?= e($nuova ? 'Novilunio' : 'Plenilunio') ?>
            <?php if (isset($l['eclissi'])): ?><span class="bollino">
              <?= e(Eclissi::genere((int) $l['eclissi']['tipo'], (string) $l['eclissi']['corpo'])) ?></span><?php endif; ?></td>
          <td class="num"><?= e(Mondo::grado((float) $l['lon'])) ?></td>
          <td class="num"><?= e($locale((float) $l['jd'])) ?></td>
          <td><a href="<?= e($carta(isset($l['eclissi'])
              ? ['jd' => $l['jd'], 'tipo' => 'eclissi', 'corpo' => $nuova ? 'sole' : 'luna',
                 'genere' => Eclissi::genere((int) $l['eclissi']['tipo'], (string) $l['eclissi']['corpo'])]
              : ['jd' => $l['jd'], 'tipo' => $l['tipo']])) ?>">la carta</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
