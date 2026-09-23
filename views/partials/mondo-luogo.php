<?php
/**
 * Il luogo per cui si ergono le carte mondiali: una capitale, o un luogo
 * scritto a mano. Gli altri parametri della pagina passano come campi nascosti.
 *
 * @var array{chiave:string,nome:string} $luogo
 * @var string $azione
 * @var array<string,string|int> $nascosti
 */
use App\Mondo\Mondo;
?>
<form method="get" action="<?= e(url($azione)) ?>" class="filtri">
  <?php foreach ($nascosti as $k => $v): ?>
    <input type="hidden" name="<?= e((string) $k) ?>" value="<?= e((string) $v) ?>">
  <?php endforeach; ?>
  <label class="tenue piccolo" for="mondo-luogo">Carte erette per</label>
  <select id="mondo-luogo" name="luogo">
    <?php foreach (Mondo::CAPITALI as $k => $c): ?>
      <option value="<?= e($k) ?>" <?= $luogo['chiave'] === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="search" name="altrove" value="<?= $luogo['chiave'] === '' ? e($luogo['nome']) : '' ?>"
         placeholder="oppure un altro luogo&hellip;" aria-label="Un altro luogo">
  <button type="submit" class="bottone">Mostra</button>
</form>
