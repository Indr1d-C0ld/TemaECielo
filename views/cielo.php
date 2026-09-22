<?php
/**
 * Il cielo adesso.
 *
 * @var array<string,mixed> $luogo
 * @var array<string,mixed> $tema
 * @var DateTimeImmutable $adesso
 */
$altSole = (float) ($tema['corpi']['sole']['altezza'] ?? -90);
$desc = \App\Cielo\Volta::cielo($altSole);
$fl = $tema['fenomeni']['luna'] ?? null;
?>
<article class="cartiglio">
  <p class="occhiello"><?= e($desc['nome']) ?></p>
  <h1>Il cielo sopra <?= e($luogo['nome']) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Adesso, <?= e($adesso->format('j/n/Y')) ?> alle <?= e($adesso->format('H:i')) ?>.
    Zenit al centro, orizzonte sul bordo, est a sinistra.
  </p>

  <figure class="volta-riquadro">
    <?= (new \App\Grafica\VoltaCeleste($tema))->disegna() ?>
    <figcaption>
      <?php if ($altSole > -6.0): ?>
        Il Sole &egrave; a <?= e(number_format($altSole, 0, ',', '')) ?>&deg; sull'orizzonte:
        le stelle ci sono ma non si vedono. Sono disegnate attenuate per mostrare dove stanno.
      <?php else: ?>
        Stelle fino alla magnitudine 5,6, con la tinta che segue l'indice di colore:
        le azzurre sono calde, le arancioni fredde.
      <?php endif; ?>
      <a href="<?= e(url('/cielo/volta.svg?luogo=' . (int) ($luogo['id'] ?? 0))) ?>" download>Scarica</a>
      &middot; <a href="<?= e(url('/oggi')) ?>">le posizioni in tabella</a>
    </figcaption>
  </figure>

  <h2>Sopra l'orizzonte in questo momento</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th></th><th>Corpo</th><th class="destra">Altezza</th><th class="destra">Azimut</th>
        <th>Direzione</th><th>Posizione</th></tr></thead>
      <tbody>
      <?php
      $direzione = static function (float $az): string {
          $punti = ['nord', 'nord-est', 'est', 'sud-est', 'sud', 'sud-ovest', 'ovest', 'nord-ovest'];
          return $punti[(int) round($az / 45.0) % 8];
      };
      $sopra = 0;
      foreach (\App\Astro\Corpi::dieci() as $k):
          if (!isset($tema['corpi'][$k])) { continue; }
          $c = $tema['corpi'][$k];
          if ((float) $c['altezza'] < 0) { continue; }
          $sopra++;
          $el = \App\Astro\Corpi::segni()[(int) $c['segno']]['elemento']; ?>
        <tr>
          <td class="g"><?= glifo((string) \App\Astro\Corpi::elenco()[$k]['glifo'], $el) ?></td>
          <td><?= e((string) $c['nome']) ?><?= $c['retrogrado'] ? ' <span class="retro">' . glifo('retrogrado') . '</span>' : '' ?></td>
          <td class="num destra"><?= e(number_format((float) $c['altezza'], 1, ',', '')) ?>&deg;</td>
          <td class="num destra"><?= e(number_format((float) $c['azimut'], 0, ',', '')) ?>&deg;</td>
          <td class="tenue"><?= e($direzione((float) $c['azimut'])) ?></td>
          <td class="num"><?= e((string) $c['posizione']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($sopra === 0): ?>
        <tr><td colspan="6" class="tenue">Nessuno dei dieci corpi &egrave; sopra l'orizzonte in questo momento.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($fl !== null): ?>
    <dl class="anagrafe">
      <div><dt>Fase</dt><dd><?= e((string) $fl['fase_nome']) ?></dd></div>
      <div><dt>Illuminazione</dt><dd class="num"><?= e(number_format((float) $fl['illuminazione'] * 100, 0, ',', '')) ?>%</dd></div>
      <div><dt>Luna sopra l'orizzonte</dt><dd><?= ((float) ($tema['corpi']['luna']['altezza'] ?? -1) >= 0) ? 'si' : 'no' ?></dd></div>
      <div><dt>Sole</dt><dd class="num"><?= e(number_format($altSole, 1, ',', '')) ?>&deg;</dd></div>
    </dl>
  <?php endif; ?>

  <p class="nota-piccola">
    Il cielo &egrave; calcolato per Roma quando non indichi un luogo. Da un altro posto:
    aggiungi <code>?lat=&hellip;&amp;lon=&hellip;</code> all'indirizzo, oppure
    <a href="<?= e(url('/calcola')) ?>">calcola un tema</a>, che porta con s&eacute; il proprio cielo.
  </p>
</article>
