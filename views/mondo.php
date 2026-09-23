<?php
/**
 * L'ingresso dell'astrologia mondiale.
 *
 * @var array<string,mixed> $luogo @var int $anno
 * @var array<string,mixed>|null $ingresso @var array<string,mixed>|null $lunazione @var array<string,mixed>|null $eclissi
 * @var float|null $indice @var float $media @var float $tendenza
 */
use App\Astro\Corpi;
use App\Mondo\Eclissi;
use App\Mondo\Mondo;

$quando = static fn (float $jd): string => Mondo::locale($jd, $luogo['fuso'])->format('j/n/Y \a\l\l\e H:i');
$segno = static fn (float $lon): string => Corpi::segni()[(int) floor(Corpi::norma($lon + 1e-6) / 30) % 12]['nome'];
?>
<article class="cartiglio">
  <p class="occhiello">Astrologia mondiale</p>
  <h1>Il mondo</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Non la carta di una persona, ma il cielo che vale per tutti. L'astrologia mondiale &egrave; la pi&ugrave;
    antica: prima di chiedere alle stelle di un re, i Babilonesi le interrogavano sul regno, sui raccolti,
    sulle guerre. Qui trovi le carte che la tradizione usa per leggere un anno, un paese, un'epoca,
    calcolate con le stesse effemeridi delle carte natali.
  </p>

  <div class="mondo-ingressi">
    <section class="mondo-riquadro">
      <h2><a href="<?= e(url('/mondo/anno')) ?>">L'anno</a></h2>
      <p>I quattro ingressi del Sole nei segni cardinali, e i noviluni e pleniluni che scandiscono i mesi.
         L'ingresso in Ariete &egrave; la carta dell'anno.</p>
      <?php if ($ingresso !== null): ?>
        <p class="mondo-prossimo"><span class="tenue">Prossimo ingresso</span>
          <a href="<?= e(url('/mondo/carta?' . http_build_query(['jd' => $ingresso['jd'], 'tipo' => 'ingresso', 'luogo' => 'roma']))) ?>">
          Sole in <?= e($segno((float) $ingresso['lon'])) ?></a>, <?= e($quando((float) $ingresso['jd'])) ?></p>
      <?php endif; ?>
      <?php if ($lunazione !== null): ?>
        <p class="mondo-prossimo"><span class="tenue">Prossima lunazione</span>
          <a href="<?= e(url('/mondo/carta?' . http_build_query(['jd' => $lunazione['jd'], 'tipo' => $lunazione['tipo'], 'luogo' => 'roma']))) ?>">
          <?= e(ucfirst((string) $lunazione['tipo'])) ?> in <?= e($segno((float) $lunazione['lon'])) ?></a>, <?= e($quando((float) $lunazione['jd'])) ?></p>
      <?php endif; ?>
    </section>

    <section class="mondo-riquadro">
      <h2><a href="<?= e(url('/mondo/eclissi')) ?>">Le eclissi</a></h2>
      <p>Tutte le eclissi di Sole e di Luna, dove sono massime, se si vedono dalla tua capitale, e su quali
         carte dell'archivio cade il loro grado.</p>
      <?php if ($eclissi !== null): ?>
        <p class="mondo-prossimo"><span class="tenue">Prossima</span>
          <a href="<?= e(url('/mondo/carta?' . http_build_query(['jd' => $eclissi['jd'], 'tipo' => 'eclissi', 'corpo' => $eclissi['corpo'], 'genere' => Eclissi::genere((int) $eclissi['tipo'], (string) $eclissi['corpo']), 'luogo' => 'roma']))) ?>">
          <?= e(Eclissi::titolo($eclissi)) ?></a> a <?= e(Mondo::grado((float) $eclissi['lon_eclittica'])) ?>,
          <?= e($quando((float) $eclissi['jd'])) ?></p>
      <?php endif; ?>
    </section>

    <section class="mondo-riquadro">
      <h2><a href="<?= e(url('/mondo/cicli')) ?>">I cicli</a></h2>
      <p>Le congiunzioni dei pianeti lenti dal <?= Mondo::ANNO_MIN ?> al <?= Mondo::ANNO_MAX ?>, le mutazioni di
         Giove e Saturno, e l'indice ciclico di Barbault messo a confronto con gli eventi dell'archivio.</p>
      <?php if ($indice !== null): ?>
        <p class="mondo-prossimo"><span class="tenue">Indice ciclico oggi</span>
          <?= e((string) (int) round($indice)) ?>&deg;
          <span class="tenue">(media <?= e((string) (int) round($media)) ?>&deg;;
          <?= $tendenza < -5 ? 'in discesa' : ($tendenza > 5 ? 'in salita' : 'stabile') ?> nel prossimo anno)</span></p>
      <?php endif; ?>
    </section>

    <section class="mondo-riquadro">
      <h2><a href="<?= e(url('/archivio?tipo=nazione')) ?>">Nazioni ed eventi</a></h2>
      <p>Le carte di fondazione degli Stati e quelle degli eventi che hanno fatto la storia, nell'archivio,
         lette con le chiavi mondiali: il Sole &egrave; chi governa, la Luna il popolo.</p>
      <p class="mondo-prossimo"><a href="<?= e(url('/archivio?tipo=nazione')) ?>">Nazioni e istituzioni</a>
         &middot; <a href="<?= e(url('/archivio?tipo=evento')) ?>">Eventi</a></p>
    </section>
  </div>
</article>
