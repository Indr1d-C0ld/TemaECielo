<?php
/**
 * Il cielo, da un luogo e in un istante scelti.
 *
 * @var array<string,mixed> $luogo
 * @var array<string,mixed> $quando
 * @var array<string,mixed> $tema
 * @var int $anno_min
 * @var int $anno_max
 */
$altSole = (float) ($tema['corpi']['sole']['altezza'] ?? -90);
$desc = \App\Cielo\Volta::cielo($altSole);
$fl = $tema['fenomeni']['luna'] ?? null;

/**
 * L'indirizzo di questo stesso cielo, con qualche parametro cambiato.
 *
 * I pulsanti di scorrimento del tempo sono collegamenti e non pulsanti di
 * modulo: si possono aprire in una scheda nuova, si possono salvare, e
 * funzionano identici con JavaScript spento.
 */
$parametri = static function (array $cambia = []) use ($luogo, $quando): string {
    $p = array_merge([
        'lat' => number_format((float) $luogo['lat'], 6, '.', ''),
        'lon' => number_format((float) $luogo['lon'], 6, '.', ''),
    ], $quando['adesso'] ? [] : ['data' => $quando['data'], 'ora' => $quando['ora']], $cambia);

    return http_build_query(array_filter($p, static fn ($v): bool => $v !== null && $v !== ''));
};

/** Questo stesso cielo, su una pagina qualunque. */
$indirizzo = static fn (string $pagina, array $cambia = []): string
    => url($pagina . '?' . $parametri($cambia));

/** Lo stesso istante, spostato di tanti secondi, nel fuso del luogo. */
$sposta = static function (int $secondi) use ($quando, $indirizzo): string {
    $t = (new DateTimeImmutable('@' . ((int) $quando['istante'] + $secondi)))
        ->setTimezone(new DateTimeZone((string) $quando['zona']));

    return $indirizzo('/cielo', ['data' => $t->format('Y-m-d'), 'ora' => $t->format('H:i')]);
};
?>
<article class="cartiglio">
  <p class="occhiello"><?= e($desc['nome']) ?></p>
  <h1>Il cielo sopra <?= e((string) $luogo['nome']) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    <?php if ($quando['adesso']): ?>
      Adesso, <?= e((new DateTimeImmutable('@' . (int) $quando['istante']))
          ->setTimezone(new DateTimeZone((string) $quando['zona']))->format('j/n/Y')) ?>
      alle <?= e((string) $quando['ora']) ?> ora locale del luogo.
    <?php else: ?>
      <?= e((new DateTimeImmutable('@' . (int) $quando['istante']))
          ->setTimezone(new DateTimeZone((string) $quando['zona']))->format('j/n/Y')) ?>
      alle <?= e((string) $quando['ora']) ?>, ora locale di <?= e(explode(',', (string) $luogo['nome'])[0]) ?>.
    <?php endif; ?>
    Zenit al centro, orizzonte sul bordo, est a sinistra.
  </p>

  <?php if (($quando['avviso'] ?? null) !== null): ?>
    <p class="lampo lampo-attento" role="status"><?= e((string) $quando['avviso']) ?></p>
  <?php endif; ?>

  <figure class="volta-riquadro" id="volta-riquadro" data-volta>
    <div class="volta-tela" id="volta-tela">
      <?= (new \App\Grafica\VoltaCeleste($tema))->disegna() ?>
      <div class="volta-attrezzi" role="group" aria-label="Ingrandimento della volta">
        <button type="button" class="volta-tasto" data-zoom="in"    aria-label="Ingrandisci" title="Ingrandisci (+)">+</button>
        <button type="button" class="volta-tasto" data-zoom="out"   aria-label="Rimpicciolisci" title="Rimpicciolisci (&minus;)">&minus;</button>
        <button type="button" class="volta-tasto" data-zoom="reset" aria-label="Torna alla vista intera" title="Vista intera (0)">&#8634;</button>
      </div>
    </div>
    <p class="volta-aiuto" id="volta-aiuto" hidden>
      Trascina per spostarti; per ingrandire, due dita, doppio clic, o la rotella con <kbd>Ctrl</kbd> premuto
      &mdash; una volta ingrandita basta la rotella.
      Con la tastiera: frecce per spostare, <kbd>+</kbd> e <kbd>&minus;</kbd> per la scala, <kbd>0</kbd> per tornare indietro.
    </p>
    <figcaption>
      <?php if ($altSole > -6.0): ?>
        Il Sole &egrave; a <?= e(number_format($altSole, 0, ',', '')) ?>&deg; sull'orizzonte:
        le stelle ci sono ma non si vedono. Sono disegnate attenuate per mostrare dove stanno.
      <?php else: ?>
        Stelle fino alla magnitudine 5,6, con la tinta che segue l'indice di colore:
        le azzurre sono calde, le arancioni fredde.
      <?php endif; ?>
      <a href="<?= e($indirizzo('/cielo/volta.svg')) ?>" download>Scarica</a>
      &middot; <a href="<?= e($indirizzo('/oggi')) ?>">le posizioni in tabella</a>
    </figcaption>
  </figure>

  <!-- ── da dove e quando si guarda ──────────────────────────────── -->
  <form method="get" action="<?= e(url('/cielo')) ?>" class="quadro-cielo" id="quadro-cielo">
    <h2 class="quadro-titolo">Da dove, e quando</h2>

    <div class="quadro-griglia">
      <div class="campo campo-largo">
        <label for="cerca-cielo">Luogo di osservazione</label>
        <div class="cerca">
          <input type="text" id="cerca-cielo" name="luogo_testo" autocomplete="off" role="combobox"
                 aria-expanded="false" aria-controls="risultati-cielo" aria-autocomplete="list"
                 placeholder="Comune, citt&agrave;, paese&hellip;"
                 value="<?= e((string) $luogo['nome']) ?>">
          <ul class="risultati" id="risultati-cielo" role="listbox" hidden></ul>
        </div>
        <p class="aiuto">Cercato su questo server: nessun carattere di quello che scrivi esce da qui.</p>
      </div>

      <div class="campo">
        <label for="cielo-data">Data</label>
        <input type="date" id="cielo-data" name="data" value="<?= e((string) $quando['data']) ?>"
               min="<?= e($anno_min . '-01-01') ?>" max="<?= e($anno_max . '-12-31') ?>">
      </div>

      <div class="campo">
        <label for="cielo-ora">Ora locale</label>
        <input type="time" id="cielo-ora" name="ora" value="<?= e((string) $quando['ora']) ?>">
        <p class="aiuto">
          <?= e((string) $quando['zona']) ?>, <?= e((string) $quando['offset_testo']) ?>
          <?= $quando['abbreviazione'] !== '' ? '(' . e((string) $quando['abbreviazione']) . ')' : '' ?>
        </p>
      </div>

      <div class="campo">
        <label for="cielo-lat">Latitudine</label>
        <input type="number" id="cielo-lat" name="lat" step="0.000001" min="-90" max="90"
               value="<?= e(number_format((float) $luogo['lat'], 6, '.', '')) ?>">
      </div>

      <div class="campo">
        <label for="cielo-lon">Longitudine</label>
        <input type="number" id="cielo-lon" name="lon" step="0.000001" min="-180" max="180"
               value="<?= e(number_format((float) $luogo['lon'], 6, '.', '')) ?>">
      </div>
    </div>

    <details class="mappa-piega" id="mappa-piega">
      <summary>Scegli il punto sulla mappa</summary>
      <div class="mappa-riquadro">
        <div class="mappa-barra">
          <div class="mappa-vesti" role="group" aria-label="Tipo di mappa">
            <button type="button" class="mappa-vest attiva" data-strato="cartina">Cartina</button>
            <button type="button" class="mappa-vest" data-strato="satellite">Satellite</button>
          </div>
          <p class="mappa-istruzione">Clicca sulla mappa o trascina il segnaposto.</p>
        </div>
        <div class="mappa" id="mappa-cielo" role="application"
             aria-label="Mappa per scegliere il luogo di osservazione"></div>
        <p class="mappa-crediti">Tessere: Esri, DeLorme, NAVTEQ &middot; Ricerca: GeoNames (CC BY 4.0)</p>
      </div>
    </details>

    <div class="quadro-azioni">
      <button type="submit" class="bottone">Mostra questo cielo</button>
      <a class="bottone-tenue" href="<?= e($indirizzo('/cielo', ['data' => null, 'ora' => null])) ?>">Adesso</a>
    </div>

    <nav class="scorri-tempo" aria-label="Sposta l'istante">
      <a href="<?= e($sposta(-86400)) ?>" rel="nofollow">&minus;1 giorno</a>
      <a href="<?= e($sposta(-3600)) ?>"  rel="nofollow">&minus;1 ora</a>
      <a href="<?= e($sposta(-600)) ?>"   rel="nofollow">&minus;10 min</a>
      <a href="<?= e($sposta(600)) ?>"    rel="nofollow">+10 min</a>
      <a href="<?= e($sposta(3600)) ?>"   rel="nofollow">+1 ora</a>
      <a href="<?= e($sposta(86400)) ?>"  rel="nofollow">+1 giorno</a>
    </nav>
  </form>

  <h2>Sopra l'orizzonte in questo momento</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th></th><th>Corpo</th><th class="destra">Altezza</th><th class="destra">Azimut</th>
        <th>Direzione</th><th>Posizione</th></tr></thead>
      <tbody>
      <?php
      $direzione = static function (float $az): string {
          $punti = ['nord', 'nord-est', 'est', 'sud-est', 'sud', 'sud-ovest', 'ovest', 'nord-ovest'];
          return $punti[(int) round($az / 45.0) % 8];
      };
      $sopra = 0;
      foreach (\App\Astro\Corpi::dieci() as $k):
          if (!isset($tema['corpi'][$k])) { continue; }
          $c = $tema['corpi'][$k];
          if ((float) $c['altezza'] < 0) { continue; }
          $sopra++;
          $el = \App\Astro\Corpi::segni()[(int) $c['segno']]['elemento']; ?>
        <tr>
          <td class="g"><?= glifo((string) \App\Astro\Corpi::elenco()[$k]['glifo'], $el) ?></td>
          <td><?= e((string) $c['nome']) ?><?= $c['retrogrado'] ? ' <span class="retro">' . glifo('retrogrado') . '</span>' : '' ?></td>
          <td class="num destra"><?= e(number_format((float) $c['altezza'], 1, ',', '')) ?>&deg;</td>
          <td class="num destra"><?= e(number_format((float) $c['azimut'], 0, ',', '')) ?>&deg;</td>
          <td class="tenue"><?= e($direzione((float) $c['azimut'])) ?></td>
          <td class="num"><?= e((string) $c['posizione']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($sopra === 0): ?>
        <tr><td colspan="6" class="tenue">Nessuno dei dieci corpi &egrave; sopra l'orizzonte in questo istante.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($fl !== null): ?>
    <dl class="anagrafe">
      <div><dt>Fase</dt><dd><?= e((string) $fl['fase_nome']) ?></dd></div>
      <div><dt>Illuminazione</dt><dd class="num"><?= e(number_format((float) $fl['illuminazione'] * 100, 0, ',', '')) ?>%</dd></div>
      <div><dt>Luna sopra l'orizzonte</dt><dd><?= ((float) ($tema['corpi']['luna']['altezza'] ?? -1) >= 0) ? 's&igrave;' : 'no' ?></dd></div>
      <div><dt>Sole</dt><dd class="num"><?= e(number_format($altSole, 1, ',', '')) ?>&deg;</dd></div>
    </dl>
  <?php endif; ?>

  <p class="nota-piccola">
    Le effemeridi installate coprono dal <?= e((string) $anno_min) ?> al <?= e((string) $anno_max) ?>.
    L'ora si legge nel fuso del luogo osservato: le 22:30 a Tokyo sono le 22:30 a Tokyo
    anche se stai guardando da Milano. Per la carta di una nascita, invece,
    <a href="<?= e(url('/calcola')) ?>">calcola un tema</a>: porta con s&eacute; il proprio cielo.
  </p>
</article>
