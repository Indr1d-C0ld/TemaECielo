<?php
/**
 * Motivo decorativo: un astrolabio inciso. Puro ornamento, nessun dato.
 * Le tacche sono generate qui invece che scritte a mano per non avere
 * trecento coordinate in un file di vista.
 */
$cx = 200; $cy = 200;
$punto = static function (float $gradi, float $r) use ($cx, $cy): array {
    $a = ($gradi - 90) * M_PI / 180;
    return [round($cx + $r * cos($a), 2), round($cy + $r * sin($a), 2)];
};
?>
<svg viewBox="0 0 400 400" class="astrolabio" role="presentation">
  <defs>
    <radialGradient id="ast-fondo">
      <stop offset="0%" stop-color="var(--pannello)"/>
      <stop offset="100%" stop-color="var(--notte)"/>
    </radialGradient>
  </defs>

  <circle cx="200" cy="200" r="192" fill="url(#ast-fondo)"/>
  <?php foreach ([192, 186, 156, 124, 92, 60] as $r): ?>
    <circle cx="200" cy="200" r="<?= $r ?>" fill="none" stroke="var(--filo)" stroke-width="1"/>
  <?php endforeach; ?>

  <?php
  // Una tacca ogni cinque gradi. Col grado singolo sarebbero 360 linee e
  // quarantottomila byte su ogni pagina: sovradettaglio per un ornamento che
  // non porta dati. A questa misura la differenza non si vede.
  for ($g = 0; $g < 360; $g += 5):
      if ($g % 30 === 0)      { $r1 = 156; $w = 1.2; $o = '.55'; }
      elseif ($g % 10 === 0)  { $r1 = 168; $w = .9;  $o = '.4';  }
      else                    { $r1 = 174; $w = .7;  $o = '.28'; }
      [$x1, $y1] = $punto((float) $g, (float) $r1);
      [$x2, $y2] = $punto((float) $g, 186.0);
  ?>
    <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>"
          stroke="var(--oro)" stroke-width="<?= $w ?>" opacity="<?= $o ?>"/>
  <?php endfor; ?>

  <?php
  // Dodici settori, colorati per elemento nell'ordine fuoco-terra-aria-acqua.
  $elementi = ['fuoco', 'terra', 'aria', 'acqua'];
  for ($s = 0; $s < 12; $s++):
      $el = $elementi[$s % 4];
      [$ax, $ay] = $punto($s * 30.0, 124.0);
      [$bx, $by] = $punto($s * 30.0 + 30.0, 124.0);
      [$cxx, $cyy] = $punto($s * 30.0 + 30.0, 156.0);
      [$dx, $dy] = $punto($s * 30.0, 156.0);
  ?>
    <path d="M <?= $ax ?> <?= $ay ?> A 124 124 0 0 1 <?= $bx ?> <?= $by ?> L <?= $cxx ?> <?= $cyy ?> A 156 156 0 0 0 <?= $dx ?> <?= $dy ?> Z"
          fill="var(--el-<?= $el ?>)" opacity=".16" stroke="var(--filo)" stroke-width=".7"/>
  <?php endfor; ?>

  <?php
  // Le corde interne: un motivo d'aspetti, non una carta vera.
  $corde = [[15, 195], [75, 195], [135, 255], [15, 135], [255, 15], [75, 315]];
  foreach ($corde as $i => [$a, $b]):
      [$x1, $y1] = $punto((float) $a, 60.0);
      [$x2, $y2] = $punto((float) $b, 60.0);
      $col = $i % 3 === 0 ? 'var(--tensione)' : ($i % 3 === 1 ? 'var(--armonico)' : 'var(--oro)');
  ?>
    <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>"
          stroke="<?= $col ?>" stroke-width="1.1" opacity=".5"/>
  <?php endforeach; ?>

  <circle cx="200" cy="200" r="60" fill="none" stroke="var(--oro)" stroke-width="1.2" opacity=".6"/>
  <circle cx="200" cy="200" r="4" fill="var(--oro-chiaro)"/>

  <!-- Gli assi, marcati come su una carta a stampa -->
  <line x1="8" y1="200" x2="392" y2="200" stroke="var(--oro-chiaro)" stroke-width="1.4" opacity=".55"/>
  <line x1="200" y1="8" x2="200" y2="392" stroke="var(--oro-chiaro)" stroke-width="1" opacity=".35"/>
</svg>
