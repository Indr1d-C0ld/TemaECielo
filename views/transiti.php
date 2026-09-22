<?php
/**
 * @var array<string,mixed> $primo @var array<string,mixed> $temaA @var array<string,mixed> $cielo
 * @var string $quando @var list<array<string,mixed>> $aspetti @var list<array<string,mixed>>|null $inCase
 */
$rom = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$oggi = date('Y-m-d');
?>
<article class="cartiglio">
  <p class="occhiello">Il cielo sopra la carta</p>
  <h1>Transiti</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <p class="condotto">
    I pianeti di <strong><?= e(date('j/n/Y', strtotime($quando))) ?></strong> sopra la carta natale di
    <?= e($primo['nome'] !== '' ? $primo['nome'] : 'questa carta') ?>.
    <?php if ($quando === $oggi): ?>Oggi.<?php endif; ?>
  </p>

  <form method="get" action="<?= e(url('/carta/' . $gettone . '/transiti')) ?>" class="filtri">
    <label for="data" class="tenue piccolo">Un altro giorno:</label>
    <input type="date" id="data" name="data" value="<?= e($quando) ?>" min="1800-01-01" max="2099-12-31">
    <button type="submit" class="bottone">Guarda</button>
    <?php if ($quando !== $oggi): ?>
      <a class="bottone" href="<?= e(url('/carta/' . $gettone . '/transiti')) ?>">Torna a oggi</a>
    <?php endif; ?>
  </form>

  <figure class="ruota-riquadro">
    <?= (new \App\Grafica\RuotaTema($temaA, false, null, $cielo, $aspetti,
          mb_strtoupper(date('j/n/Y', strtotime($quando)), 'UTF-8')))->disegna() ?>
    <figcaption>
      Al centro la carta natale; nella corona esterna il cielo di quel giorno. Le posizioni
      dei transiti sono calcolate a mezzogiorno: la Luna si sposta di mezzo grado all'ora,
      quindi per lei l'orario conta.
    </figcaption>
  </figure>

  <h2>Aspetti in corso <span class="conteggio"><?= e((string) count($aspetti)) ?></span></h2>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th>Transito</th><th>Aspetto</th><th>Punto natale</th>
        <th class="destra">Orbe</th><th class="destra">Forza</th></tr></thead>
      <tbody>
      <?php foreach ($aspetti as $a): ?>
        <tr>
          <td><?= e((string) $a['nome_b']) ?> <span class="tenue piccolo">in transito</span></td>
          <td class="aspetto-<?= e((string) $a['natura']) ?>">
            <?= glifo((string) $a['glifo']) ?> <?= e((string) $a['aspetto_nome']) ?></td>
          <td><?= e((string) $a['nome_a']) ?> <span class="tenue piccolo">natale</span></td>
          <td class="num destra"><?= e(number_format((float) $a['orbe'], 2, ',', '')) ?>&deg;</td>
          <td class="num destra"><?= e(number_format((float) $a['forza'] * 100, 0, ',', '')) ?>%</td>
        </tr>
      <?php endforeach; ?>
      <?php if ($aspetti === []): ?>
        <tr><td colspan="5" class="tenue">Nessun aspetto entro gli orbi stretti dei transiti.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <h2>In quali case cadono</h2>
  <?php if ($inCase === null): ?>
    <p class="condotto">
      L'ora di nascita non &egrave; nota: le cuspidi non esistono, e dire in quale casa cade un
      transito sarebbe inventare. Gli aspetti qui sopra, invece, restano validi.
    </p>
  <?php else: ?>
    <div class="tabella-scorre">
      <table class="griglia fitta">
        <thead><tr><th>Pianeta</th><th>Posizione oggi</th><th class="destra">Casa natale</th></tr></thead>
        <tbody>
        <?php foreach ($inCase as $o): ?>
          <tr>
            <td><?= e((string) $o['nome']) ?></td>
            <td class="num"><?= e((string) $o['posizione']) ?></td>
            <td class="num destra"><?= e($rom[(int) $o['casa']]) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="nota-piccola">
    <a href="<?= e(url('/carta/' . $gettone)) ?>">&larr; torna alla carta</a>
  </p>
</article>
