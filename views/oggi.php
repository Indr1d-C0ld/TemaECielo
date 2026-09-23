<?php
/**
 * Le posizioni del momento: una pagina di dati, senza disegno.
 *
 * @var array<string,mixed> $luogo
 * @var array<string,mixed> $tema
 * @var DateTimeImmutable $adesso
 * @var array<string,mixed> $quando
 * @var list<string> $retrogradi
 */
$fl = $tema['fenomeni']['luna'] ?? null;
$g  = $tema['giorno']['sole'] ?? [];

/** Da giorno giuliano a ora locale leggibile. */
$oraDi = static function (?float $jd) use ($quando): string {
    if ($jd === null) { return '—'; }
    // Il giorno giuliano parte da mezzogiorno: si sposta di mezza giornata
    // prima di trattarlo come un tempo Unix.
    $unix = (int) round(($jd - 2440587.5) * 86400.0);
    // Nel fuso del LUOGO osservato, non in quello del server: da Tokyo l'alba
    // si legge sull'orologio di Tokyo.
    return (new DateTimeImmutable('@' . $unix))
        ->setTimezone(new DateTimeZone((string) $quando['zona']))->format('H:i');
};
?>
<article class="cartiglio">
  <p class="occhiello"><?= e($adesso->format('j/n/Y')) ?> &middot; <?= e($adesso->format('H:i')) ?></p>
  <h1>Il cielo di oggi</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Dove stanno i corpi celesti in questo momento, calcolato per <?= e($luogo['nome']) ?>.
    Per vederli disegnati: <a href="<?= e(url('/cielo')) ?>">la volta celeste</a>.
  </p>

  <h2>Posizioni</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th></th><th>Corpo</th><th>Posizione</th><th class="destra">Velocit&agrave;</th>
        <th class="destra">Altezza</th><th>Levata</th><th>Culmine</th><th>Tramonto</th></tr></thead>
      <tbody>
      <?php foreach (\App\Astro\Corpi::dieci() as $k):
        if (!isset($tema['corpi'][$k])) { continue; }
        $c = $tema['corpi'][$k];
        $el = \App\Astro\Corpi::segni()[(int) $c['segno']]['elemento'];
        $ev = $k === 'sole' ? $g : ($tema['giorno']['corpi'][$k] ?? []); ?>
        <tr>
          <td class="g"><?= glifo((string) \App\Astro\Corpi::elenco()[$k]['glifo'], $el) ?></td>
          <td><?= e((string) $c['nome']) ?></td>
          <td class="num"><?= e((string) $c['posizione']) ?>
            <?php if ($c['retrogrado']): ?><span class="retro"><?= glifo('retrogrado') ?></span><?php endif; ?></td>
          <td class="num destra"><?= e(number_format((float) $c['vel_lon'], 4, ',', '')) ?></td>
          <td class="num destra <?= (float) $c['altezza'] >= 0 ? '' : 'tenue' ?>">
            <?= e(number_format((float) $c['altezza'], 1, ',', '')) ?>&deg;</td>
          <td class="num tenue"><?= e($oraDi($ev['levata'] ?? null)) ?></td>
          <td class="num tenue"><?= e($oraDi($ev['culminazione'] ?? null)) ?></td>
          <td class="num tenue"><?= e($oraDi($ev['tramonto'] ?? null)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="aiuto">
    Levata, culmine e tramonto sono nell'ora locale del luogo (<?= e((string) $quando['zona']) ?>)
    e si riferiscono a quella giornata vista da <?= e($luogo['nome']) ?>. Un trattino significa che l'evento non avviene &mdash;
    capita alle alte latitudini, dove d'estate il Sole non tramonta affatto.
  </p>

  <div class="due">
    <div>
      <h2>La Luna</h2>
      <?php if ($fl !== null): ?>
        <dl class="anagrafe">
          <div><dt>Fase</dt><dd><?= e((string) $fl['fase_nome']) ?></dd></div>
          <div><dt>Illuminazione</dt><dd class="num"><?= e(number_format((float) $fl['illuminazione'] * 100, 0, ',', '')) ?>%</dd></div>
          <div><dt>Et&agrave;</dt><dd class="num">
            <?= e(number_format((float) ($fl['eta_vera_giorni'] ?? $fl['eta_media_giorni']), 1, ',', '')) ?> giorni</dd></div>
          <div><dt>Elongazione</dt><dd class="num"><?= e(number_format((float) $fl['elongazione'], 1, ',', '')) ?>&deg;</dd></div>
        </dl>
      <?php endif; ?>
      <?php if (($tema['sizigia'] ?? null) !== null): ?>
        <p class="tenue piccolo">
          Ultima sizigia: <?= e((string) $tema['sizigia']['tipo']) ?> a
          <?= e(\App\Astro\Corpi::formatta((float) $tema['sizigia']['lon'], false)) ?>.
        </p>
      <?php endif; ?>
    </div>

    <div>
      <h2>Retrogradi</h2>
      <?php if ($retrogradi === []): ?>
        <p class="condotto">Nessuno dei dieci corpi &egrave; retrogrado in questo momento.</p>
      <?php else: ?>
        <ul class="configurazioni">
          <?php foreach ($retrogradi as $k): ?>
            <li>
              <?= glifo((string) \App\Astro\Corpi::elenco()[$k]['glifo']) ?>
              <strong><?= e((string) $tema['corpi'][$k]['nome']) ?></strong>
              &mdash; <?= e((string) $tema['corpi'][$k]['posizione']) ?>
              <span class="tenue">(<?= e(number_format((float) $tema['corpi'][$k]['vel_lon'], 4, ',', '')) ?>&deg; al giorno)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <h2>Il momento</h2>
      <table class="griglia definizioni">
        <tbody>
          <tr><th>Giorno giuliano</th><td class="num"><?= e(number_format((float) $tema['tempo']['jd_ut'], 5, ',', '.')) ?></td></tr>
          <tr><th>Tempo siderale locale</th><td class="num"><?= e(number_format((float) $tema['tempo']['siderale_locale'], 4, ',', '')) ?> h</td></tr>
          <tr><th>&Delta;T</th><td class="num"><?= e(number_format((float) $tema['tempo']['delta_t_secondi'], 1, ',', '')) ?> s</td></tr>
          <tr><th>Obliquit&agrave; vera</th><td class="num"><?= e(number_format((float) $tema['tempo']['obliquita_vera'], 6, ',', '')) ?>&deg;</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</article>
