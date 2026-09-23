<?php
/**
 * @var array<string,mixed> $primo @var array<string,mixed> $natale @var int $anno
 * @var float $eta @var int $anniCompiuti @var array<string,mixed>|null $progresso
 * @var list<array<string,mixed>> $contatti @var array<string,mixed>|null $direzioni
 * @var array<string,mixed>|null $rivoluzione @var array<string,mixed>|null $profezione
 */
$rom = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$chi = $primo['nome'] !== '' ? $primo['nome'] : 'questa carta';
?>
<article class="cartiglio">
  <p class="occhiello">Come la carta si muove nel tempo</p>
  <h1>Le carte del tempo</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <p class="condotto">
    Tre modi diversi di far avanzare la carta <?= e(\App\Corpus\Lingua::inserisci('di %s', $chi)) ?>, e non si sostituiscono l'uno
    all'altro. Riferiti al compleanno del <strong><?= e((string) $anno) ?></strong>, quando
    <?= e($chi) ?> compie <strong><?= e((string) $anniCompiuti) ?> anni</strong>.
  </p>

  <form method="get" action="<?= e(url('/carta/' . $gettone . '/derivate')) ?>" class="filtri">
    <label for="anno" class="tenue piccolo">Un altro anno:</label>
    <input type="number" id="anno" name="anno" value="<?= e((string) $anno) ?>" min="1800" max="2199">
    <button type="submit" class="bottone">Guarda</button>
    <?php if ($anno !== (int) date('Y')): ?>
      <a class="bottone" href="<?= e(url('/carta/' . $gettone . '/derivate')) ?>">Torna a quest'anno</a>
    <?php endif; ?>
  </form>

  <?php foreach ($errori as $dove => $msg): ?>
    <p class="lampo lampo-male">Non e&rsquo; stato possibile calcolare <?= e($dove) ?>: <?= e($msg) ?></p>
  <?php endforeach; ?>

  <!-- ─────────────── PROFEZIONE ─────────────── -->
  <h2>Profezione annuale</h2>
  <?php if ($profezione === null): ?>
    <p class="condotto tenue">
      L'ora di nascita non &egrave; nota: senza Ascendente non c'&egrave; niente da far avanzare.
    </p>
  <?php else: ?>
    <p class="condotto">
      La tecnica pi&ugrave; antica delle tre, e la pi&ugrave; semplice: l'Ascendente avanza di un
      segno intero per ogni anno compiuto, e torna al punto di partenza ogni dodici.
    </p>
    <div class="profezione">
      <div class="profezione-segno">
        <?= glifo((string) $profezione['segno_glifo'], 'el-' . $profezione['elemento']) ?>
        <span class="profezione-nome"><?= e((string) $profezione['segno_nome']) ?></span>
        <span class="tenue piccolo">casa <?= e($rom[(int) $profezione['casa']]) ?> natale</span>
      </div>
      <div>
        <p>
          Signore dell'anno: <strong><?= e((string) $profezione['signore_nome']) ?></strong>
          <?php if ($profezione['signore_posizione'] !== null): ?>
            &mdash; natale a <?= e((string) $profezione['signore_posizione']) ?>
            <?php if ($profezione['signore_casa'] !== null): ?>
              in casa <?= e($rom[(int) $profezione['signore_casa']]) ?>
            <?php endif; ?>
          <?php endif; ?>
        </p>
        <p class="tenue piccolo">
          &Egrave; il pianeta che d&agrave; il tono all'anno, e la casa in cui si trova indica il
          settore di vita che l'anno mette in primo piano.
          <?php if ($profezione['signore_moderno'] !== $profezione['signore']): ?>
            Per la lettura moderna il signore sarebbe
            <?= e((string) \App\Astro\Corpi::elenco()[$profezione['signore_moderno']]['nome']) ?>.
          <?php endif; ?>
        </p>
      </div>
    </div>
  <?php endif; ?>

  <!-- ─────────────── PROGRESSIONI ─────────────── -->
  <h2>Progressioni secondarie</h2>
  <?php if ($progresso === null): ?>
    <p class="condotto tenue">Non calcolate.</p>
  <?php else: ?>
    <p class="condotto">
      Un giorno di effemeridi per un anno di vita: si guarda il cielo del
      <?= e((string) $anniCompiuti) ?>&ordm; giorno dopo la nascita. La Luna progredita percorre
      un segno in due anni e mezzo ed &egrave; il corpo che dice di pi&ugrave;; il Sole avanza di
      circa un grado all'anno.
    </p>
    <div class="tabella-scorre">
      <table class="griglia fitta">
        <thead><tr><th>Corpo</th><th>Natale</th><th>Progredito</th><th class="destra">Arco percorso</th></tr></thead>
        <tbody>
        <?php
        $giorni = (float) $progresso['tempo']['jd_ut'] - (float) $natale['tempo']['jd_ut'];
        foreach (\App\Astro\Corpi::dieci() as $k):
          if (!isset($progresso['corpi'][$k], $natale['corpi'][$k])) { continue; }
          // L'arco VERO, giri compresi: la Luna progredita in cinquant'anni
          // fa quasi due rivoluzioni, e la differenza ridotta al giro
          // direbbe che e' andata all'indietro.
          $d = \App\Astro\Derivate::arcoVero(
                 $k,
                 (float) $natale['corpi'][$k]['lon'],
                 (float) $progresso['corpi'][$k]['lon'],
                 $giorni,
               ); ?>
          <tr>
            <td><?= e((string) $natale['corpi'][$k]['nome']) ?></td>
            <td class="num tenue"><?= e((string) $natale['corpi'][$k]['posizione']) ?></td>
            <td class="num"><?= e((string) $progresso['corpi'][$k]['posizione']) ?></td>
            <td class="num destra"><?= e(number_format($d, 1, ',', '.')) ?>&deg;</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <h3>Contatti col cielo natale <span class="conteggio"><?= e((string) count($contatti)) ?></span></h3>
    <p class="condotto">
      Gli orbi sono di un grado soltanto: nelle progressioni un grado vale un anno di vita, e
      con gli orbi di una carta natale un aspetto resterebbe &laquo;attivo&raquo; per sedici anni
      senza dire pi&ugrave; niente.
    </p>
    <?php if ($contatti === []): ?>
      <p class="condotto tenue">Nessun contatto entro un grado in questo momento.</p>
    <?php else: ?>
      <div class="tabella-scorre">
        <table class="griglia fitta">
          <thead><tr><th>Progredito</th><th>Aspetto</th><th>Natale</th><th class="destra">Orbe</th></tr></thead>
          <tbody>
          <?php foreach ($contatti as $c): ?>
            <tr>
              <td><?= e((string) $c['nome_a']) ?> <span class="tenue piccolo">prog.</span></td>
              <td class="aspetto-<?= e((string) $c['natura']) ?>">
                <?= glifo((string) $c['glifo']) ?> <?= e((string) $c['aspetto_nome']) ?></td>
              <td><?= e((string) $c['nome_b']) ?></td>
              <td class="num destra"><?= e(number_format((float) $c['orbe'], 2, ',', '')) ?>&deg;</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- ─────────────── ARCO SOLARE ─────────────── -->
  <?php if ($direzioni !== null): ?>
    <h2>Direzioni di arco solare</h2>
    <p class="condotto">
      Tutto avanza dello stesso arco percorso dal Sole progredito:
      <strong><?= e(number_format((float) $direzioni['arco'], 2, ',', '')) ?>&deg;</strong>.
      &Egrave; una tecnica pi&ugrave; rozza &mdash; in cielo i pianeti non vanno tutti alla stessa
      velocit&agrave; &mdash; ma ha il pregio di mettere in moto anche Saturno e i tre lenti, che
      nelle progressioni in ottant'anni si spostano di pochi gradi.
    </p>
    <div class="tabella-scorre">
      <table class="griglia fitta">
        <thead><tr><th>Corpo</th><th>Natale</th><th>Diretto</th></tr></thead>
        <tbody>
        <?php foreach (\App\Astro\Corpi::dieci() as $k):
          if (!isset($direzioni['corpi'][$k])) { continue; } ?>
          <tr>
            <td><?= e((string) $direzioni['corpi'][$k]['nome']) ?></td>
            <td class="num tenue"><?= e((string) $direzioni['corpi'][$k]['natale']) ?></td>
            <td class="num"><?= e((string) $direzioni['corpi'][$k]['posizione']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach (['asc', 'mc'] as $a):
          if (!isset($direzioni['punti'][$a]) || ($natale['carta']['ora_ignota'] ?? false) === true) { continue; } ?>
          <tr>
            <td><?= e((string) $direzioni['punti'][$a]['nome']) ?></td>
            <td class="num tenue"><?= e((string) $direzioni['punti'][$a]['natale']) ?></td>
            <td class="num"><?= e((string) $direzioni['punti'][$a]['posizione']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- ─────────────── RIVOLUZIONE SOLARE ─────────────── -->
  <h2>Rivoluzione solare <?= e((string) $anno) ?></h2>
  <?php if ($rivoluzione === null): ?>
    <p class="condotto tenue">Non calcolata.</p>
  <?php else: ?>
    <?php $i = $rivoluzione['istante']; ?>
    <p class="condotto">
      Il momento in cui il Sole ripassa esattamente sul grado che occupava alla nascita:
      <strong><?= e(sprintf('%d/%d/%d', $i['giorno'], $i['mese'], $i['anno'])) ?></strong>
      <?php $minuti = (int) floor((float) $i['ora_ut'] * 60.0); /* troncati: «13:60» non esiste */ ?>
      alle <strong><?= e(sprintf('%02d:%02d UT', intdiv($minuti, 60), $minuti % 60)) ?></strong>.
      Non &egrave; il compleanno: il Sole impiega 365 giorni e un quarto, quindi l'istante cade
      ogni anno quasi sei ore pi&ugrave; tardi e ogni tanto scivola al giorno prima.
    </p>

    <figure class="ruota-riquadro">
      <?= (new \App\Grafica\RuotaTema($natale, false, null, $rivoluzione['tema'], $incrociati,
            'RIVOLUZIONE ' . $anno))->disegna() ?>
      <figcaption>
        Al centro la carta natale, nella corona esterna la rivoluzione solare, calcolata sul
        luogo di nascita.
      </figcaption>
    </figure>

    <div class="tabella-scorre">
      <table class="griglia fitta">
        <thead><tr><th>Corpo</th><th>Nella rivoluzione</th><th class="destra">Casa</th></tr></thead>
        <tbody>
        <?php foreach (\App\Astro\Corpi::dieci() as $k):
          if (!isset($rivoluzione['tema']['corpi'][$k])) { continue; }
          $c = $rivoluzione['tema']['corpi'][$k]; ?>
          <tr>
            <td><?= e((string) $c['nome']) ?></td>
            <td class="num"><?= e((string) $c['posizione']) ?><?= $c['retrogrado'] ? ' ℞' : '' ?></td>
            <td class="num destra"><?= e($rom[(int) $c['casa']]) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td>Ascendente</td>
          <td class="num"><?= e((string) $rivoluzione['tema']['punti']['asc']['posizione']) ?></td>
          <td></td>
        </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p class="nota-piccola"><a href="<?= e(url('/carta/' . $gettone)) ?>">&larr; torna alla carta</a></p>
</article>
