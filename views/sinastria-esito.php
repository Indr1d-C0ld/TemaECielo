<?php
/**
 * @var array<string,mixed> $primo @var array<string,mixed> $temaA @var array<string,mixed> $temaB
 * @var array<string,mixed> $sinastria @var string $nomeB
 */
$rom = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$nomeA = $sinastria['nomi']['a'];
?>
<article class="cartiglio">
  <p class="occhiello">Sinastria completa</p>
  <h1><?= e($nomeA) ?> e <?= e($nomeB) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <div class="numeri">
    <?php foreach ($sinastria['punteggi'] as $p): ?>
      <div class="numero">
        <span class="numero-val"><?= e((string) $p['valore']) ?>%</span>
        <span class="numero-eti"><?= e($p['nome']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="aiuto">
    Quattro numeri e non uno solo: &laquo;compatibilit&agrave; 73%&raquo; non significa niente
    e non si pu&ograve; verificare. Sotto ogni punteggio c'&egrave; da che cosa viene.
  </p>

  <figure class="ruota-riquadro">
    <?= (new \App\Grafica\RuotaTema($temaA, false, null, $temaB, $sinastria['aspetti'],
          mb_strtoupper($nomeB, 'UTF-8'))) ->disegna() ?>
    <figcaption>
      Al centro la carta di <?= e($nomeA) ?>; nella corona esterna, pi&ugrave; leggera,
      i pianeti di <?= e($nomeB) ?>. Le corde che attraversano sono gli aspetti fra le due.
    </figcaption>
  </figure>

  <h2>Da che cosa vengono i punteggi</h2>
  <div class="due">
    <?php foreach ($sinastria['punteggi'] as $chiave => $p): ?>
      <div class="area-punteggio">
        <h3><?= e($p['nome']) ?> <span class="conteggio"><?= e((string) $p['valore']) ?>%</span></h3>
        <p class="condotto"><?= e($p['cosa']) ?></p>
        <?php if ($p['contributi'] === []): ?>
          <p class="tenue piccolo">Nessun contatto significativo in quest'area.</p>
        <?php else: ?>
          <ul class="contributi">
            <?php foreach ($p['contributi'] as $c): ?>
              <li class="aspetto-<?= e($c['natura']) ?>"><?= e($c['testo']) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php if ($p['quanti'] > count($p['contributi'])): ?>
            <p class="tenue piccolo">e altri <?= e((string) ($p['quanti'] - count($p['contributi']))) ?>.</p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>Aspetti incrociati <span class="conteggio"><?= e((string) $sinastria['conteggio']['totale']) ?></span></h2>
  <p class="condotto">
    Gli orbi sono pi&ugrave; stretti che in una carta singola: fra due carte le coppie
    possibili sono cento invece di quarantacinque, e con gli orbi normali uscirebbero
    ottanta aspetti che non direbbero pi&ugrave; niente.
  </p>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th><?= e($nomeA) ?></th><th>Aspetto</th><th><?= e($nomeB) ?></th>
        <th class="destra">Orbe</th><th class="destra">Forza</th></tr></thead>
      <tbody>
      <?php foreach ($sinastria['aspetti'] as $a): ?>
        <tr>
          <td><?= e((string) $a['nome_a']) ?></td>
          <td class="aspetto-<?= e((string) $a['natura']) ?>">
            <?= glifo((string) $a['glifo']) ?> <?= e((string) $a['aspetto_nome']) ?></td>
          <td><?= e((string) $a['nome_b']) ?></td>
          <td class="num destra"><?= e(number_format((float) $a['orbe'], 2, ',', '')) ?>&deg;</td>
          <td class="num destra"><?= e(number_format((float) $a['forza'] * 100, 0, ',', '')) ?>%</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <h2>Sovrapposizione delle case</h2>
  <p class="condotto">
    Dove cadono i pianeti dell'uno nelle case dell'altro. Nella pratica dice pi&ugrave; degli
    aspetti: indica in quali settori di vita l'altro entra davvero.
  </p>
  <div class="due">
    <?php foreach ([['a_in_b', $nomeA, $nomeB], ['b_in_a', $nomeB, $nomeA]] as [$k, $chi, $dove]): ?>
      <div>
        <h3>I pianeti di <?= e($chi) ?> nelle case di <?= e($dove) ?></h3>
        <?php if ($sinastria['sovrapposizioni'][$k] === null): ?>
          <p class="tenue">
            L'ora di nascita di <?= e($dove) ?> non &egrave; nota: le cuspidi non esistono, e
            dire in quale casa cade un pianeta sarebbe inventare.
          </p>
        <?php else: ?>
          <table class="griglia fitta">
            <tbody>
            <?php foreach ($sinastria['sovrapposizioni'][$k] as $o): ?>
              <tr>
                <td><?= e((string) $o['nome']) ?></td>
                <td class="num tenue"><?= e((string) $o['posizione']) ?></td>
                <td class="num destra">casa <?= e($rom[(int) $o['casa']]) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>La carta composita</h2>
  <p class="condotto">
    I punti medi fra le due carte: il rapporto guardato come se fosse esso stesso una persona.
    Non &egrave; un cielo che c'&egrave; stato &mdash; &egrave; una costruzione, e va letta come tale.
  </p>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th>Corpo</th><th>Posizione composita</th></tr></thead>
      <tbody>
      <?php foreach ($sinastria['composita']['corpi'] as $k => $c):
        if (!in_array($k, \App\Astro\Corpi::dieci(), true)) { continue; } ?>
        <tr>
          <td><?= e((string) $c['nome']) ?></td>
          <td class="num"><?= e((string) $c['posizione']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="tenue piccolo">
    <?= e((string) count($sinastria['composita']['aspetti'])) ?> aspetti interni alla composita:
    il rapporto ha tensioni proprie, che non sono la somma di quelle dei due.
  </p>

  <p class="nota-piccola">
    Questa pagina non viene conservata: per rivederla, rifai il confronto da
    <a href="<?= e(url('/carta/' . $gettone)) ?>">la carta di <?= e($nomeA) ?></a>.
    I dati della seconda persona non sono stati archiviati.
  </p>
</article>
