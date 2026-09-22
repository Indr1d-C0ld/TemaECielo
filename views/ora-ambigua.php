<?php
/**
 * Due volte all'anno un'ora locale non identifica un istante.
 *
 * @var array<string,mixed> $tempo
 * @var array<string,mixed> $dati
 */
?>
<article class="cartiglio cartiglio-stretto">
  <p class="occhiello">Serve una scelta</p>
  <h1>Quale delle due?</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <p class="condotto">
    La notte fra il <?= e(date('j/n/Y', strtotime((string) $tempo['data_locale']))) ?> gli orologi
    sono tornati indietro di un'ora, e le <strong><?= e(substr((string) $tempo['ora_locale'], 0, 5)) ?></strong>
    sono esistite <strong>due volte</strong>: una prima del cambio, ancora in ora legale, e una dopo.
  </p>
  <p class="condotto">
    Sono due istanti diversi, distanti un'ora l'uno dall'altro, e danno due carte diverse.
    Il portale non sceglie al posto tuo.
  </p>

  <form method="post" action="<?= e(url('/calcola')) ?>" class="modulo">
    <?= csrf() ?>
    <?php foreach ($dati as $chiave => $valore): ?>
      <?php if ($chiave !== 'ambigua' && !is_array($valore)): ?>
        <input type="hidden" name="<?= e($chiave) ?>" value="<?= e((string) $valore) ?>">
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="scelte">
      <?php foreach ($tempo['alternative'] as $i => $a): ?>
        <label class="scelta">
          <input type="radio" name="ambigua" value="<?= e((string) $i) ?>" <?= $i === 0 ? 'checked' : '' ?>>
          <span class="scelta-corpo">
            <span class="scelta-titolo">
              <?= $a['ora_legale'] ? 'Prima del cambio' : 'Dopo il cambio' ?>
              &mdash; scarto <?= e($a['offset_testo']) ?>
              <?= $a['abbreviazione'] !== '' ? '(' . e($a['abbreviazione']) . ')' : '' ?>
            </span>
            <span class="scelta-spiega">
              <?= $a['ora_legale']
                  ? 'Gli orologi non erano ancora stati spostati: era ancora in vigore l\'ora legale.'
                  : 'Gli orologi erano gia\' stati riportati indietro all\'ora solare.' ?>
              &mdash; Tempo Universale <?= e(str_replace(['T', 'Z'], [' ', ''], (string) $a['utc'])) ?>
            </span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>

    <button type="submit" class="bottone bottone-primo">Calcola con questa</button>
  </form>

  <p class="nota-piccola">
    Se non sai quale delle due, prova a ricordare se quella notte gli orologi erano gi&agrave; stati
    cambiati. In mancanza d'altro, la prima &egrave; la lettura pi&ugrave; comune di un'ora
    trascritta su un documento.
  </p>
</article>
