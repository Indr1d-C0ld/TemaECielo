<?php
/** @var array<string,mixed> $primo @var array<string,mixed> $dati @var array<string,string> $errori */
$v = static fn (string $k, string $pre = ''): string => (string) ($dati[$k] ?? $pre);
$err = static fn (string $k): string => isset($errori[$k])
    ? '<p class="errore-campo">' . e($errori[$k]) . '</p>' : '';
?>
<article class="cartiglio">
  <p class="occhiello">Sinastria completa</p>
  <h1>Chi confrontare con <?= e($primo['nome'] !== '' ? $primo['nome'] : 'questa carta') ?>?</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <dl class="anagrafe">
    <div><dt>Prima carta</dt><dd><?= e($primo['nome'] !== '' ? $primo['nome'] : 'anonima') ?></dd></div>
    <div><dt>Nata il</dt><dd><?= e(date('j/n/Y', strtotime((string) $primo['data_nascita']))) ?></dd></div>
    <div><dt>A</dt><dd><?= e((string) $primo['luogo_nome']) ?></dd></div>
  </dl>

  <p class="condotto">
    Servono i dati di nascita della seconda persona. Valgono le stesse regole della prima:
    l'ora si pu&ograve; dichiarare ignota, e in quel caso non si calcoler&agrave; la
    sovrapposizione delle case.
  </p>

  <?php if ($errori !== []): ?>
    <p class="lampo lampo-male" role="alert">Manca qualcosa. I campi da correggere sono segnati qui sotto.</p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/carta/' . $gettone . '/sinastria')) ?>" class="modulo-nascita" id="modulo-nascita">
    <?= csrf() ?>
    <fieldset>
      <legend>La seconda persona</legend>
      <div class="campo">
        <label for="nome">Nome <span class="facoltativo">(facoltativo)</span></label>
        <input type="text" id="nome" name="nome" maxlength="120" value="<?= e($v('nome')) ?>" autocomplete="off">
      </div>
      <div class="campo">
        <label for="data">Data di nascita</label>
        <input type="date" id="data" name="data" required min="1800-01-01" max="<?= e(date('Y-m-d')) ?>" value="<?= e($v('data')) ?>">
        <?= $err('data') ?>
      </div>
      <div class="campo">
        <label>Ora di nascita</label>
        <div class="scelte" role="radiogroup">
          <?php $scelta = $v('precisione', 'esatta');
          foreach (['esatta' => 'La so con precisione', 'approssimativa' => 'La so all\'incirca',
                    'ignota' => 'Non la so'] as $k => $eti): ?>
            <label class="scelta">
              <input type="radio" name="precisione" value="<?= e($k) ?>" <?= $scelta === $k ? 'checked' : '' ?>>
              <span class="scelta-corpo"><span class="scelta-titolo"><?= e($eti) ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="campo" id="campo-ora">
        <label for="ora">Ora locale</label>
        <input type="time" id="ora" name="ora" value="<?= e($v('ora')) ?>">
        <?= $err('ora') ?>
        <p class="esito-fuso" id="esito-fuso" hidden></p>
      </div>
    </fieldset>

    <fieldset>
      <legend>Dove</legend>
      <div class="campo">
        <label for="cerca-luogo">Luogo di nascita</label>
        <div class="cerca">
          <input type="text" id="cerca-luogo" name="luogo_testo" autocomplete="off" role="combobox" aria-expanded="false"
                 aria-controls="risultati-luogo" placeholder="Comune, citt&agrave;, paese&hellip;"
                 value="<?= e($v('luogo_nome')) ?>">
          <input type="hidden" id="luogo_era" name="luogo_era" value="<?= e($v('luogo_nome')) ?>">
          <ul class="risultati" id="risultati-luogo" role="listbox" hidden></ul>
        </div>
        <?= $err('luogo') ?>
      </div>
      <div class="mappa-riquadro">
        <div class="mappa-barra">
          <div class="mappa-vesti" role="group">
            <button type="button" class="mappa-vest attiva" data-strato="cartina">Cartina</button>
            <button type="button" class="mappa-vest" data-strato="satellite">Satellite</button>
          </div>
          <p class="mappa-istruzione">Clicca sulla mappa per scegliere il punto.</p>
        </div>
        <div class="mappa" id="mappa" role="application"></div>
      </div>
      <div class="coordinate">
        <div class="campo"><label for="lat">Latitudine</label>
          <input type="number" id="lat" name="lat" step="0.000001" min="-90" max="90" value="<?= e($v('lat')) ?>"></div>
        <div class="campo"><label for="lon">Longitudine</label>
          <input type="number" id="lon" name="lon" step="0.000001" min="-180" max="180" value="<?= e($v('lon')) ?>"></div>
        <div class="campo"><label for="altitudine">Altitudine</label>
          <input type="number" id="altitudine" name="altitudine" step="1" value="<?= e($v('altitudine', '0')) ?>"></div>
      </div>
      <div class="campo">
        <label for="fuso">Fuso orario</label>
        <input type="text" id="fuso" name="fuso" value="<?= e($v('fuso')) ?>" readonly required>
        <?= $err('fuso') ?>
      </div>
      <input type="hidden" name="luogo_id" id="luogo_id" value="<?= e($v('luogo_id')) ?>">
      <input type="hidden" name="luogo_nome" id="luogo_nome" value="<?= e($v('luogo_nome')) ?>">
      <input type="hidden" name="sistema" value="placido">
    </fieldset>

    <?php if (isset($errori['motore'])): ?>
      <p class="lampo lampo-male"><?= e($errori['motore']) ?></p>
    <?php endif; ?>

    <button type="submit" class="bottone bottone-primo">Confronta le due carte</button>
  </form>
</article>
