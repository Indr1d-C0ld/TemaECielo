<?php
/**
 * Il modulo di nascita.
 *
 * @var array<string,mixed> $dati
 * @var array<string,string> $errori
 * @var array<string,array{codice:string,nome:string,polare:bool}> $sistemi
 */
$v = static fn (string $k, string $pre = ''): string => (string) ($dati[$k] ?? $pre);
$err = static fn (string $k): string => isset($errori[$k])
    ? '<p class="errore-campo">' . e($errori[$k]) . '</p>' : '';
?>
<article class="cartiglio">
  <p class="occhiello">Il tuo cielo</p>
  <h1>Calcola il tema natale</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Tre dati: quando, a che ora, e dove. Da questi il portale ricava le posizioni reali
    dei corpi celesti in quell'istante, calcolate con la Swiss Ephemeris.
  </p>

  <?php if ($errori !== []): ?>
    <p class="lampo lampo-male" role="alert">
      Manca qualcosa, o qualcosa non torna. I campi da correggere sono segnati qui sotto.
    </p>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/calcola')) ?>" class="modulo-nascita" id="modulo-nascita">
    <?= csrf() ?>

    <!-- ── generalità ─────────────────────────────────────────── -->
    <fieldset>
      <legend>Generalit&agrave;</legend>
      <div class="campo">
        <label for="nome">Nome <span class="facoltativo">(facoltativo)</span></label>
        <input type="text" id="nome" name="nome" maxlength="120" value="<?= e($v('nome')) ?>"
               autocomplete="off" placeholder="Come chiamare questa carta">
        <p class="aiuto">Serve solo a dare un titolo alla carta. Puoi lasciarlo vuoto.</p>
      </div>
    </fieldset>

    <!-- ── quando ─────────────────────────────────────────────── -->
    <fieldset>
      <legend>Quando</legend>

      <div class="campo">
        <label for="data">Data di nascita</label>
        <input type="date" id="data" name="data" required min="1800-01-01"
               max="<?= e(date('Y-m-d')) ?>" value="<?= e($v('data')) ?>">
        <?= $err('data') ?>
      </div>

      <div class="campo">
        <label>Ora di nascita</label>
        <div class="scelte" role="radiogroup" aria-label="Quanto conosci l'ora di nascita">
          <?php
          $precisioni = [
              'esatta'         => ['La so con precisione', 'Dall\'atto di nascita o dal ricordo di chi c\'era.'],
              'approssimativa' => ['La so all\'incirca',   'Verso sera, di mattina presto: si calcola sul centro dell\'intervallo.'],
              'ignota'         => ['Non la so',            'Si calcola la carta solare. Case e Ascendente non saranno attendibili, e verra\' detto.'],
          ];
          $scelta = $v('precisione', 'esatta');
          foreach ($precisioni as $chiave => [$etichetta, $spiega]): ?>
            <label class="scelta">
              <input type="radio" name="precisione" value="<?= e($chiave) ?>"
                     <?= $scelta === $chiave ? 'checked' : '' ?>>
              <span class="scelta-corpo">
                <span class="scelta-titolo"><?= e($etichetta) ?></span>
                <span class="scelta-spiega"><?= e($spiega) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="campo" id="campo-ora">
        <label for="ora">Ora locale di nascita</label>
        <input type="time" id="ora" name="ora" value="<?= e($v('ora')) ?>">
        <p class="aiuto">
          L'ora segnata sul documento, cos&igrave; com'&egrave;: l'ora legale la applica il portale,
          seguendo le regole in vigore quel giorno in quel luogo.
        </p>
        <?= $err('ora') ?>
        <p class="esito-fuso" id="esito-fuso" hidden></p>
      </div>
    </fieldset>

    <!-- ── dove ───────────────────────────────────────────────── -->
    <fieldset>
      <legend>Dove</legend>

      <div class="campo">
        <label for="cerca-luogo">Luogo di nascita</label>
        <div class="cerca">
          <input type="text" id="cerca-luogo" autocomplete="off" role="combobox"
                 aria-expanded="false" aria-controls="risultati-luogo" aria-autocomplete="list"
                 placeholder="Comune, citt&agrave;, paese&hellip;" value="<?= e($v('luogo_nome')) ?>">
          <ul class="risultati" id="risultati-luogo" role="listbox" hidden></ul>
        </div>
        <p class="aiuto">
          La ricerca avviene su questo server: nessun carattere di quello che scrivi esce da qui.
          Vanno bene anche gli esonimi &mdash; <em>Londra</em>, <em>Parigi</em>, <em>Monaco di Baviera</em>.
        </p>
        <?= $err('luogo') ?>
      </div>

      <div class="mappa-riquadro">
        <div class="mappa-barra">
          <div class="mappa-vesti" role="group" aria-label="Tipo di mappa">
            <button type="button" class="mappa-vest attiva" data-strato="cartina">Cartina</button>
            <button type="button" class="mappa-vest" data-strato="satellite">Satellite</button>
          </div>
          <p class="mappa-istruzione">Clicca sulla mappa o trascina il segnaposto per scegliere il punto esatto.</p>
        </div>
        <div class="mappa" id="mappa" role="application" aria-label="Mappa per scegliere il luogo di nascita"></div>
        <p class="mappa-crediti">Tessere: Esri, DeLorme, NAVTEQ &middot; Ricerca: GeoNames (CC BY 4.0)</p>
      </div>

      <div class="coordinate">
        <div class="campo">
          <label for="lat">Latitudine</label>
          <input type="number" id="lat" name="lat" step="0.000001" min="-90" max="90"
                 value="<?= e($v('lat')) ?>" required>
        </div>
        <div class="campo">
          <label for="lon">Longitudine</label>
          <input type="number" id="lon" name="lon" step="0.000001" min="-180" max="180"
                 value="<?= e($v('lon')) ?>" required>
        </div>
        <div class="campo">
          <label for="altitudine">Altitudine <span class="facoltativo">(m)</span></label>
          <input type="number" id="altitudine" name="altitudine" step="1" min="-500" max="9000"
                 value="<?= e($v('altitudine', '0')) ?>">
        </div>
      </div>
      <p class="aiuto">
        In gradi decimali, positivi a nord e a est. L'altitudine serve alla parallasse della Luna
        e agli orari di alba e tramonto.
      </p>

      <div class="campo">
        <label for="fuso">Fuso orario</label>
        <input type="text" id="fuso" name="fuso" value="<?= e($v('fuso')) ?>" readonly required
               aria-describedby="aiuto-fuso">
        <p class="aiuto" id="aiuto-fuso">
          Dedotto dal luogo. Le regole storiche &mdash; l'ora legale di quell'anno, l'ora locale
          media prima del 1893 &mdash; le applica il portale.
        </p>
        <?= $err('fuso') ?>
      </div>

      <input type="hidden" name="luogo_id"   id="luogo_id"   value="<?= e($v('luogo_id')) ?>">
      <input type="hidden" name="luogo_nome" id="luogo_nome" value="<?= e($v('luogo_nome')) ?>">
    </fieldset>

    <!-- ── opzioni ────────────────────────────────────────────── -->
    <details class="avanzate">
      <summary>Opzioni di calcolo</summary>
      <div class="campo">
        <label for="sistema">Sistema di case</label>
        <select id="sistema" name="sistema">
          <?php foreach ($sistemi as $chiave => $s): ?>
            <option value="<?= e($chiave) ?>" <?= $v('sistema', 'placido') === $chiave ? 'selected' : '' ?>>
              <?= e($s['nome']) ?><?= $s['polare'] ? '' : ' — non calcolabile oltre i circoli polari' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="aiuto">
          Placido &egrave; il pi&ugrave; diffuso. Alle latitudini estreme degenera, e in quel caso
          il portale te lo dice e propone un'alternativa.
        </p>
      </div>
    </details>

    <?php if (isset($errori['motore'])): ?>
      <p class="lampo lampo-male" role="alert"><?= e($errori['motore']) ?></p>
    <?php endif; ?>

    <button type="submit" class="bottone bottone-primo">Calcola il tema</button>

    <p class="nota-piccola">
      Il risultato vive a un indirizzo con un gettone segreto: conservalo per ritrovarlo, o per
      cancellarlo. Non serve nessuna registrazione e non viene chiesta nessuna e-mail.
      Leggi l'<a href="<?= e(url('/pagina/informativa')) ?>">informativa</a>.
    </p>
  </form>
</article>
