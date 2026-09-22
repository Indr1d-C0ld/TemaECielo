<?php
/**
 * @var array<string,int> $generale @var array<string,mixed> $segni
 * @var array<string,mixed> $elementi @var array<string,mixed> $nascite
 * @var array<string,mixed> $voti @var list<array<string,mixed>> $aspetti
 * @var list<array<string,mixed>> $paesi
 */
$barra = static function (string $eti, int $quota, string $valore, string $classe = ''): string {
    return '<div class="barra" data-quota="' . (int) $quota . '">'
        . '<span class="barra-eti ' . e($classe) . '">' . $eti . '</span>'
        . '<span class="traccia"><i class="riemp ' . ($classe !== '' ? 'riemp-' . e(str_replace('el-', '', $classe)) : '') . '"></i></span>'
        . '<span class="barra-val">' . e($valore) . '</span></div>';
};
$nomiMesi = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
?>
<article class="cartiglio">
  <p class="occhiello">L&rsquo;archivio che si racconta</p>
  <h1>Statistiche</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Tutto aggregato e senza dati personali: nessun nome, nessuna data di nascita singola,
    nessun indirizzo. Sono i numeri di quello che il portale ha calcolato finora.
  </p>

  <div class="numeri">
    <?php foreach ([
      'carte'     => 'carte distinte',
      'richieste' => 'richieste in tutto',
      'luoghi'    => 'luoghi di nascita',
      'senza_ora' => 'senza ora nota',
      'messaggi'  => 'messaggi firmati',
    ] as $k => $eti): ?>
      <div class="numero">
        <span class="numero-val"><?= e(number_format((float) $generale[$k], 0, ',', '.')) ?></span>
        <span class="numero-eti"><?= e($eti) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>Dove cadono i luminari</h2>
  <div class="tre-grafici">
    <?php foreach (['sole' => 'Sole', 'luna' => 'Luna', 'asc' => 'Ascendente'] as $k => $titolo): ?>
      <div>
        <h3><?= e($titolo) ?> <span class="conteggio"><?= e((string) $segni[$k]['totale']) ?></span></h3>
        <?php foreach ($segni[$k]['righe'] as $s): ?>
          <?= $barra(
                glifo($s['glifo'], 'el-' . $s['elemento']) . ' ' . e($s['segno']),
                (int) $s['quota'],
                (string) $s['quanti'],
                'el-' . $s['elemento'],
              ) ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="aiuto">
    <strong>Attenzione a leggere il grafico degli ascendenti.</strong> La sua distribuzione non
    &egrave; uniforme nemmeno in teoria: alle nostre latitudini certi segni sorgono in
    un&rsquo;ora e mezza e altri in venti minuti. Se qui compaiono pi&ugrave; Bilance che
    Arieti, il dato non dice niente sulle persone &mdash; dice qualcosa sulla geometria della
    sfera celeste. Sole e Luna, invece, sono distribuiti quasi uniformemente per davvero.
  </p>

  <div class="due">
    <div>
      <h2>Elementi</h2>
      <p class="condotto">Conteggio pesato su <?= e((string) $elementi['carte']) ?> carte.</p>
      <?php foreach ($elementi['elementi'] as $x): ?>
        <?= $barra(e(ucfirst($x['nome'])), (int) $x['quota'],
              number_format((float) $x['valore'], 1, ',', '') . '%', 'el-' . $x['nome']) ?>
      <?php endforeach; ?>
      <h3>Modalit&agrave;</h3>
      <?php foreach ($elementi['modalita'] as $x): ?>
        <?= $barra(e(ucfirst($x['nome'])), (int) $x['quota'],
              number_format((float) $x['valore'], 1, ',', '') . '%') ?>
      <?php endforeach; ?>
    </div>

    <div>
      <h2>Aspetti pi&ugrave; frequenti</h2>
      <?php if ($aspetti === []): ?>
        <p class="tenue">Nessun dato ancora.</p>
      <?php else: ?>
        <?php foreach ($aspetti as $a): ?>
          <?= $barra(e($a['nome']), (int) $a['quota'], number_format((float) $a['quanti'], 0, ',', '.')) ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($paesi !== []): ?>
        <h2>Da dove si collegano</h2>
        <?php foreach ($paesi as $p): ?>
          <?= $barra(e($p['nome']), (int) $p['quota'], number_format((float) $p['quanti'], 0, ',', '.')) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <h2>Quando sono nati</h2>
  <div class="tre-grafici">
    <div>
      <h3>Per decennio</h3>
      <?php foreach ($nascite['decenni'] as $x): ?>
        <?= $barra(e($x['etichetta']) . '&ndash;' . e((string) ((int) $x['etichetta'] + 9)),
              (int) $x['quota'], (string) $x['quanti']) ?>
      <?php endforeach; ?>
      <?php if ($nascite['decenni'] === []): ?><p class="tenue">Nessun dato.</p><?php endif; ?>
    </div>
    <div>
      <h3>Per mese</h3>
      <?php foreach ($nascite['mesi'] as $x): ?>
        <?= $barra(e($nomiMesi[(int) $x['etichetta']] ?? $x['etichetta']), (int) $x['quota'], (string) $x['quanti']) ?>
      <?php endforeach; ?>
      <?php if ($nascite['mesi'] === []): ?><p class="tenue">Nessun dato.</p><?php endif; ?>
    </div>
    <div>
      <h3>Per ora del giorno</h3>
      <?php foreach ($nascite['ore'] as $x): ?>
        <?= $barra(sprintf('%02d', (int) $x['etichetta']) . ':00', (int) $x['quota'], (string) $x['quanti']) ?>
      <?php endforeach; ?>
      <?php if ($nascite['ore'] === []): ?><p class="tenue">Nessun dato.</p><?php endif; ?>
      <p class="aiuto">Solo chi ha dichiarato di conoscere l&rsquo;ora con precisione.</p>
    </div>
  </div>

  <h2>I voti del guestbook</h2>
  <?php if ($voti['n_gradimento'] === 0 && $voti['n_attinenza'] === 0): ?>
    <p class="condotto tenue">Nessun voto ancora.</p>
  <?php else: ?>
    <div class="due">
      <?php foreach ([
        ['voto_gradimento', 'Gradimento', $voti['gradimento'], $voti['n_gradimento'],
         'Quanto &egrave; piaciuto il portale.'],
        ['voto_attinenza', 'Attinenza', $voti['attinenza'], $voti['n_attinenza'],
         'Quanto il risultato &egrave; parso corrispondente. &Egrave; il dato pi&ugrave; interessante che questo portale possa raccogliere.'],
      ] as [$campo, $titolo, $media, $quanti, $cosa]): ?>
        <div>
          <h3><?= e($titolo) ?>
            <span class="conteggio"><?= $media !== null ? e(number_format((float) $media, 2, ',', '')) : '&mdash;' ?>
              su 5 &middot; <?= e((string) $quanti) ?> voti</span></h3>
          <p class="condotto"><?= $cosa ?></p>
          <?php foreach ($voti['dettaglio'][$campo] as $d): ?>
            <?= $barra(str_repeat('&#9733;', (int) $d['valore']), (int) $d['quota'], (string) $d['quanti']) ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</article>
