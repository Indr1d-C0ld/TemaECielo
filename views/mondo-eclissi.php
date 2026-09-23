<?php
/**
 * Il catalogo delle eclissi.
 *
 * @var int $da @var int $a @var array<string,mixed> $luogo @var list<array<string,mixed>> $elenco
 */
use App\Mondo\Eclissi;
use App\Mondo\Mondo;

$dove = $luogo['chiave'] !== '' ? ['luogo' => $luogo['chiave']] : ['altrove' => $luogo['nome']];
$span = $a - $da + 1;
$coord = static function (float $lat, float $lon): string {
    // Si arrotonda ai primi PRIMA di separare i gradi: 41,9999° e' 42°00′.
    $gp = static fn (float $x): array => [intdiv((int) round(abs($x) * 60), 60), (int) round(abs($x) * 60) % 60];
    [$la, $lap] = $gp($lat);
    [$lo, $lop] = $gp($lon);
    return sprintf('%d°%02d′ %s, %d°%02d′ %s', $la, $lap, $lat >= 0 ? 'N' : 'S', $lo, $lop, $lon >= 0 ? 'E' : 'O');
};
?>
<article class="cartiglio">
  <p class="occhiello"><a href="<?= e(url('/mondo')) ?>">Astrologia mondiale</a></p>
  <h1>Le eclissi</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Dal <?= e((string) $da) ?> al <?= e((string) $a) ?>: <?= count($elenco) ?> eclissi. Per ciascuna il grado
    dello zodiaco, la serie di Saros &mdash; la famiglia di eclissi che si ripete ogni 18 anni e 11 giorni
    &mdash;, quanto si vede da <?= e($luogo['nome']) ?>, e le carte dell'archivio su cui il grado cade
    entro <?= e((string) (int) Mondo::ORBE_COLPO) ?>&deg;.
  </p>

  <nav class="mondo-scorri" aria-label="Periodi">
    <?php if ($da > Mondo::ANNO_MIN): $p0 = max(Mondo::ANNO_MIN, $da - $span); ?><a href="<?= e(url('/mondo/eclissi?' . http_build_query(['da' => $p0, 'a' => $da - 1] + $dove))) ?>">&larr; <?= $p0 ?>&ndash;<?= $da - 1 ?></a><?php else: ?><span></span><?php endif; ?>
    <?php if ($a < Mondo::ANNO_MAX): $p1 = min(Mondo::ANNO_MAX, $a + $span); ?><a href="<?= e(url('/mondo/eclissi?' . http_build_query(['da' => $a + 1, 'a' => $p1] + $dove))) ?>"><?= $a + 1 ?>&ndash;<?= $p1 ?> &rarr;</a><?php endif; ?>
  </nav>

  <?= vista('partials/mondo-luogo', ['luogo' => $luogo, 'azione' => '/mondo/eclissi', 'nascosti' => ['da' => $da, 'a' => $a]]) ?>

  <ul class="eclissi-elenco">
  <?php foreach ($elenco as $e): $sole = $e['corpo'] === 'sole'; ?>
    <li class="eclissi eclissi-<?= $sole ? 'sole' : 'luna' ?>">
      <h3>
        <a href="<?= e(url('/mondo/carta?' . http_build_query(['jd' => $e['jd'], 'tipo' => 'eclissi', 'corpo' => $e['corpo'], 'genere' => Eclissi::genere((int) $e['tipo'], (string) $e['corpo'])] + $dove))) ?>">
          <?= e(Eclissi::titolo($e)) ?></a>
        <span class="tenue"><?= e(gmdate('j/n/Y', Mondo::unix((float) $e['jd']))) ?></span>
      </h3>
      <p class="eclissi-dati">
        <span><?= e(Mondo::grado((float) $e['lon_eclittica'])) ?></span>
        <span class="tenue">massimo <?= e(gmdate('H:i', Mondo::unix((float) $e['jd']))) ?> UT</span>
        <?php if ((int) $e['saros'] > 0): ?><span class="tenue">Saros <?= e((string) $e['saros']) ?> (<?= e((string) $e['membro']) ?>&ordf;)</span><?php endif; ?>
        <span class="tenue"><?= e($sole ? 'magnitudine ' . number_format((float) $e['magnitudine'], 3, ',', '')
            : Eclissi::magnitudine((float) $e['magnitudine'], (float) ($e['penombrale'] ?? 0))) ?></span>
        <?php if ($sole && isset($e['lat'])): ?><span class="tenue">massima a <?= e($coord((float) $e['lat'], (float) $e['lon'])) ?></span><?php endif; ?>
      </p>
      <p class="eclissi-locale">
        <span class="tenue">Da <?= e($luogo['nome']) ?>:</span>
        <?= e(Eclissi::visibilita($e['locale'] ?? null, (string) $e['corpo'])) ?>
        <?php if (isset($e['locale']) && $e['locale']['altezza'] >= 0): ?>
          <span class="tenue">&middot; massimo alle <?= e(Mondo::locale((float) $e['locale']['jd'], $luogo['fuso'])->format('H:i')) ?></span>
        <?php endif; ?>
      </p>
      <?php if ($e['colpi'] !== []): ?>
        <p class="eclissi-colpi"><span class="tenue">Cade su</span>
          <?php foreach (array_slice($e['colpi'], 0, 6) as $i => $c): ?><?= $i > 0 ? ', ' : '' ?>
            <?= e($c['punto']) ?> di <a href="<?= e(url('/archivio/' . $c['slug'])) ?>"><?= e($c['nome']) ?></a>
            <span class="tenue">(<?= e(number_format($c['distanza'], 1, ',', '')) ?>&deg;)</span><?php endforeach; ?>
          <?= count($e['colpi']) > 6 ? '<span class="tenue">e altre ' . (count($e['colpi']) - 6) . '</span>' : '' ?>
        </p>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  </ul>
</article>
