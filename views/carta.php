<?php
/**
 * Il risultato.
 *
 * In F2 la ruota disegnata non c'e' ancora — arriva con F3 — ma i dati ci sono
 * tutti, e si leggono in tabella. Meglio una pagina completa senza disegno che
 * un disegno senza dati.
 *
 * @var array<string,mixed> $soggetto
 * @var array<string,mixed> $tema
 * @var string $gettone
 */
$rom = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$ignota = ($tema['carta']['ora_ignota'] ?? false) === true;
$inatt  = static fn (string $k): bool => in_array($k, $tema['carta']['inattendibili'] ?? [], true);
// Catene di dispositori e manico arrivano come chiavi interne («mercurio»): in
// pagina va il nome, «Mercurio».
$nomeDi = static fn (string $k): string => (string) (\App\Astro\Corpi::elenco()[$k]['nome'] ?? ucfirst($k));

$offsetTesto = sprintf('%s%02d:%02d',
    (int) $soggetto['offset_minuti'] < 0 ? '-' : '+',
    intdiv(abs((int) $soggetto['offset_minuti']), 60),
    abs((int) $soggetto['offset_minuti']) % 60);
?>
<?php
$scheda = $scheda ?? null;
$archivio = $scheda !== null && (int) $scheda['pubblicata'] === 1;
// Una carta d'archivio si rilegge al suo indirizzo pubblico, non al gettone.
$indirizzo = $archivio ? '/archivio/' . $scheda['slug'] : '/carta/' . $gettone;
// Un evento o una fondazione non «nascono»: le frasi sull'ora lo rispettano.
$diCosa = match ($scheda['tipo'] ?? '') {
    'evento'  => 'dell\'evento',
    'nazione' => 'della fondazione',
    default   => 'di nascita',
};
$occhiello = match ($scheda['tipo'] ?? '') {
    'evento'  => 'Carta di evento',
    'nazione' => 'Carta di fondazione',
    default   => 'Tema natale',
};
?>
<article class="cartiglio">
  <p class="occhiello"><?= e($occhiello) ?><?= $archivio ? ' &middot; <a href="' . e(url('/archivio')) . '">archivio</a>' : '' ?></p>
  <h1><?= e($scheda !== null ? (string) $scheda['nome'] : ($soggetto['nome'] !== '' ? $soggetto['nome'] : 'Carta anonima')) ?></h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <?php if ($scheda !== null): ?>
    <div class="scheda-archivio">
      <?php if (trim((string) $scheda['nota']) !== ''): ?>
        <div class="prosa"><?= \App\Support\Markdown::rendi((string) $scheda['nota']) ?></div>
      <?php endif; ?>
      <p class="scheda-fonte">
        <span class="bollino rodden-<?= e(strtolower((string) $scheda['rodden'])) ?>"
              title="<?= e(\App\Archivio\Archivio::RODDEN[$scheda['rodden']][1] ?? '') ?>">Rodden <?= e((string) $scheda['rodden']) ?></span>
        <?= e(\App\Archivio\Archivio::RODDEN[$scheda['rodden']][0] ?? '') ?>
        <?php if ((string) $scheda['fonte'] !== ''): ?>&middot; <?= e((string) $scheda['fonte']) ?><?php endif; ?>
        <?php if ((string) $scheda['url_fonte'] !== ''): ?>
          &middot; <a href="<?= e((string) $scheda['url_fonte']) ?>" rel="noopener noreferrer nofollow">fonte</a>
        <?php endif; ?>
      </p>
      <?php if (in_array($scheda['rodden'], ['C', 'DD'], true)): ?>
        <p class="tenue piccolo">Con un'ora incerta, Ascendente, Medio Cielo e case vanno presi con cautela; i pianeti nei segni no.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (\App\Auth\Auth::amministratore()): ?>
    <p class="nota-piccola"><a href="<?= e(url('/admin/carte/' . $gettone)) ?>"><?= $scheda !== null ? 'Modifica la scheda d\'archivio' : 'Metti questa carta nell\'archivio' ?></a></p>
  <?php endif; ?>

  <dl class="anagrafe">
    <div><dt>Data</dt><dd><?= e(date('j/n/Y', strtotime((string) $soggetto['data_nascita']))) ?></dd></div>
    <div><dt>Ora locale</dt><dd>
      <?= $soggetto['ora_nascita'] !== null ? e(substr((string) $soggetto['ora_nascita'], 0, 5)) : 'ignota' ?>
      <?php if (($soggetto['precisione_ora'] ?? '') === 'approssimativa'): ?><span class="tenue">circa</span><?php endif; ?>
      <?php if ($soggetto['ora_nascita'] !== null): ?>
        <span class="tenue"><?= e($offsetTesto) ?></span>
      <?php endif; ?>
    </dd></div>
    <div><dt>Luogo</dt><dd><?= e($soggetto['luogo_nome']) ?></dd></div>
    <div><dt>Coordinate</dt><dd class="num"><?= e(number_format((float) $soggetto['lat'], 4, ',', '')) ?>&deg;
      <?= (float) $soggetto['lat'] >= 0 ? 'N' : 'S' ?>
      <?= e(number_format(abs((float) $soggetto['lon']), 4, ',', '')) ?>&deg;
      <?= (float) $soggetto['lon'] >= 0 ? 'E' : 'O' ?></dd></div>
    <div><dt>Fuso</dt><dd><?= e($soggetto['fuso']) ?></dd></div>
    <div><dt>Case</dt><dd><?= e((string) $tema['carta']['sistema_nome']) ?></dd></div>
  </dl>

  <?php if ($ignota): ?>
    <p class="lampo lampo-attento">
      <strong>L'ora <?= e($diCosa) ?> non &egrave; nota.</strong>
      Questa &egrave; una <em>carta solare</em>: il Sole in cuspide di prima casa, case per segni
      interi. Ascendente, Medio Cielo, cuspidi, Parte di Fortuna e Vertex
      <strong>non sono attendibili</strong> e sono segnati come tali. Le posizioni dei corpi,
      invece, valgono: sotto trovi di quanto si sono mossi nelle ventiquattro ore.
    </p>
  <?php endif; ?>

  <?php
    $ripiego = (array) ($tema['carta']['effemeride_ripiego'] ?? []);
    $mancanti = array_map(
        static fn (string $k): string => (string) (\App\Astro\Corpi::elenco()[$k]['nome'] ?? $k),
        array_keys((array) ($tema['errori_corpi'] ?? [])),
    );
  ?>
  <?php if ($ripiego !== [] || $mancanti !== []): ?>
    <p class="lampo lampo-attento">
      Questa data sta al margine dell'arco coperto dai file delle effemeridi.
      <?php if ($ripiego !== []): ?>
        Per <?= e(implode(', ', $ripiego)) ?> il calcolo &egrave; passato al modello analitico di
        Moshier: preciso a meno di un secondo d'arco per i pianeti, ma non &egrave; quello delle altre posizioni.
      <?php endif; ?>
      <?php if ($mancanti !== []): ?>
        <?= e(implode(', ', $mancanti)) ?> non <?= count($mancanti) === 1 ? '&egrave; calcolabile' : 'sono calcolabili' ?>
        per questa data, e <?= count($mancanti) === 1 ? 'manca' : 'mancano' ?> dalla carta.
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if (($soggetto['precisione_ora'] ?? '') === 'approssimativa'): ?>
    <p class="lampo lampo-attento">
      <strong>L'ora <?= e($diCosa) ?> &egrave; approssimativa.</strong>
      L'Ascendente si sposta di circa un grado ogni quattro minuti, e le cuspidi con lui:
      un quarto d'ora di incertezza basta a cambiare il segno che sorge o la casa di un pianeta
      vicino a una cuspide. Le posizioni dei corpi restano valide; case e angoli vanno presi con cautela.
    </p>
  <?php endif; ?>

  <?php if (($tema['carta']['case_degeneri'] ?? false) === true): ?>
    <p class="lampo lampo-attento">
      A questa latitudine il sistema di case scelto non ha soluzione &mdash; oltre i circoli
      polari certe cuspidi non esistono. La libreria ha ripiegato su un sistema alternativo:
      per una carta coerente, rifai il calcolo scegliendo <em>Segni Interi</em> o <em>Porfirio</em>.
    </p>
  <?php endif; ?>

  <?php if (($lettura ?? null) !== null && $lettura['sezioni'] !== []): ?>
    <section class="lettura">
      <h2>La lettura</h2>
      <div class="linguette" role="tablist" aria-label="Registro interpretativo">
        <?php foreach (['tradizionale' => 'Tradizionale', 'moderno' => 'Moderna'] as $reg => $eti): ?>
          <a class="linguetta" role="tab" href="<?= e(url($indirizzo . '?registro=' . $reg)) ?>"
             aria-selected="<?= $registro === $reg ? 'true' : 'false' ?>"><?= e($eti) ?></a>
        <?php endforeach; ?>
      </div>

      <p class="condotto registro-spiega">
        <?php if ($registro === 'tradizionale'): ?>
          Lettura nel registro <strong>tradizionale</strong>: dignit&agrave;, signorie, qualit&agrave;
          elementari, i sette pianeti visibili in primo piano. Il linguaggio &egrave; quello di Tolomeo
          e di Lilly, e i tre pianeti moderni compaiono come chiose.
        <?php else: ?>
          Lettura nel registro <strong>moderno</strong>: archetipi, processo psicologico, funzioni
          della persona. Il linguaggio &egrave; quello del Novecento, da Rudhyar in avanti.
        <?php endif; ?>
      </p>

      <?php foreach ($lettura['sezioni'] as $sezione): ?>
        <h3 class="lettura-sezione"><?= $sezione['titolo'] ?></h3>
        <?php foreach ($sezione['voci'] as $v): ?>
          <article class="voce voce-<?= e($v->fonte) ?>"
                   <?= $v->soggetti !== [] ? 'data-corpi="' . e(implode(' ', $v->soggetti)) . '"' : '' ?>>
            <h4>
              <?= e($v->titolo) ?>
              <?php if ($v->perche !== ''): ?>
                <span class="voce-perche"><?= e($v->perche) ?></span>
              <?php endif; ?>
            </h4>
            <p><?= e($v->corpo) ?></p>
          </article>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <p class="nota-piccola">
        <?php
        $c = $lettura['conteggio'];
        $tot = $c['scritte'] + $c['composte'];
        ?>
        Di <?= e((string) $tot) ?> passaggi, <?= e((string) $c['scritte']) ?>
        <?= $c['scritte'] === 1 ? '&egrave;' : 'sono' ?> scritt<?= $c['scritte'] === 1 ? 'o' : 'i' ?> a mano
        e <?= e((string) $c['composte']) ?> compost<?= $c['composte'] === 1 ? 'o' : 'i' ?> dai frammenti
        del corpus &mdash; li riconosci dal filetto pi&ugrave; tenue a sinistra.
        Il corpus cresce: le voci pi&ugrave; frequenti vengono scritte per prime.
      </p>
    </section>
  <?php endif; ?>

  <?php if (($mondana ?? null) !== null): ?>
    <?= vista('partials/lettura-mondiale', ['lettura' => $mondana]) ?>
  <?php endif; ?>

  <figure class="ruota-riquadro">
    <?= (new \App\Grafica\RuotaTema($tema))->disegna() ?>
    <figcaption>
      Ascendente a sinistra, case in senso antiorario. Il glifo di un pianeta pu&ograve; essere
      spostato per far posto ai vicini: la linea sottile che lo unisce alla tacca dice il grado vero.
      <a href="<?= e(url('/carta/' . $gettone . '/ruota.svg')) ?>" download>Scarica la ruota</a>
      come file vettoriale.
    </figcaption>
  </figure>

  <h2>Il cielo di quell'istante</h2>
  <?php
  $altSole = (float) ($tema['corpi']['sole']['altezza'] ?? -90);
  $descCielo = \App\Cielo\Volta::cielo($altSole);
  ?>
  <figure class="volta-riquadro">
    <?= (new \App\Grafica\VoltaCeleste($tema))->disegna() ?>
    <figcaption>
      Non lo zodiaco: il cielo vero, visto da <?= e($soggetto['luogo_nome']) ?> in quell'istante.
      Zenit al centro, orizzonte sul bordo, <strong>est a sinistra</strong> &mdash; una carta
      del cielo si guarda tenendola sopra la testa, e rispetto a una mappa del terreno i punti
      cardinali sono specchiati.
      <?php if ($altSole > -6.0): ?>
        <br><strong>Il Sole era a <?= e(number_format($altSole, 0, ',', '')) ?>&deg; sull'orizzonte
        (<?= e($descCielo['nome']) ?>): in cielo non si sarebbe visto quasi nulla di tutto questo.</strong>
        Le stelle sono disegnate ugualmente, molto attenuate, per mostrare dove stavano.
      <?php endif; ?>
      <a href="<?= e(url('/carta/' . $gettone . '/cielo.svg')) ?>" download>Scarica la volta celeste</a>.
    </figcaption>
  </figure>

  <?php if (($tema['fenomeni']['luna'] ?? null) !== null): ?>
    <?php $fl = $tema['fenomeni']['luna']; ?>
    <dl class="anagrafe">
      <div><dt>Fase lunare</dt><dd><?= e((string) $fl['fase_nome']) ?></dd></div>
      <div><dt>Illuminazione</dt><dd class="num"><?= e(number_format((float) $fl['illuminazione'] * 100, 0, ',', '')) ?>%</dd></div>
      <div><dt>Et&agrave; della Luna</dt><dd class="num">
        <?= $fl['eta_vera_giorni'] !== null
            ? e(number_format((float) $fl['eta_vera_giorni'], 1, ',', '')) . ' giorni'
            : e(number_format((float) $fl['eta_media_giorni'], 1, ',', '')) . ' giorni circa' ?>
      </dd></div>
      <?php if (($tema['giorno']['durata_giorno_ore'] ?? null) !== null): ?>
        <div><dt>Durata del giorno</dt><dd class="num">
          <?= e(number_format((float) $tema['giorno']['durata_giorno_ore'], 2, ',', '')) ?> ore</dd></div>
      <?php endif; ?>
      <?php if (($tema['sizigia'] ?? null) !== null): ?>
        <div><dt>Sizigia prenatale</dt><dd><?= e((string) $tema['sizigia']['tipo']) ?>
          a <?= e(\App\Astro\Corpi::formatta((float) $tema['sizigia']['lon'], false)) ?></dd></div>
      <?php endif; ?>
      <div><dt>Cielo</dt><dd><?= e((string) $descCielo['nome']) ?></dd></div>
    </dl>
  <?php endif; ?>

  <h2>Griglia degli aspetti</h2>
  <div class="tabella-scorre">
    <?= (new \App\Grafica\GrigliaAspetti($tema))->disegna() ?>
  </div>

  <h2>Posizioni</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr>
        <th></th><th>Corpo</th><th>Posizione</th><th class="destra">Casa</th>
        <th class="destra">Decl.</th><th class="destra">Vel.</th><th>Dignit&agrave;</th>
      </tr></thead>
      <tbody>
      <?php foreach ($tema['corpi'] as $chiave => $c):
        $iSeg = (int) $c['segno'];
        $el = \App\Astro\Corpi::segni()[$iSeg]['elemento']; ?>
        <tr data-corpo="<?= e($chiave) ?>">
          <td class="g"><?= glifo((string) (\App\Astro\Corpi::elenco()[$chiave]['glifo'] ?? 'stella'), $el) ?></td>
          <td><?= e((string) $c['nome']) ?></td>
          <td class="num"><?= e((string) $c['posizione']) ?>
            <?php if ($c['retrogrado']): ?><span class="retro" title="retrogrado"><?= glifo('retrogrado') ?></span><?php endif; ?>
          </td>
          <td class="num destra<?= $inatt('case') ? ' incerto' : '' ?>"><?= e($rom[(int) $c['casa']]) ?></td>
          <td class="num destra"><?= e(number_format((float) $c['decl'], 2, ',', '')) ?>&deg;</td>
          <td class="num destra"><?= e(number_format((float) $c['vel_lon'], 4, ',', '')) ?></td>
          <td class="tenue piccolo">
            <?php if (isset($c['dignita'])): ?>
              <?= e(implode(', ', array_map(static fn (array $v): string => $v['nome'], $c['dignita']['voci']))) ?>
              <?php if ($c['dignita']['voci'] !== []): ?>
                <span class="punteggio"><?= e(sprintf('%+d', (int) $c['punteggio_totale'])) ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($inatt('setta')): ?>
    <p class="nota-piccola">
      Senza l'ora <?= e($diCosa) ?> il punteggio delle dignit&agrave; non conta la casa, e usa i signori di
      triplicit&agrave; del giorno: non si sa se l'istante sia caduto di giorno o di notte.
    </p>
  <?php endif; ?>

  <h2>Punti</h2>
  <div class="tabella-scorre">
    <table class="griglia">
      <thead><tr><th>Punto</th><th>Posizione</th><th class="destra">Casa</th></tr></thead>
      <tbody>
      <?php foreach ($tema['punti'] as $chiave => $p): ?>
        <tr class="<?= $inatt($chiave) ? 'incerto' : '' ?>">
          <td><?= e((string) $p['nome']) ?>
            <?php if ($inatt($chiave)): ?><span class="bollino">non attendibile</span><?php endif; ?>
          </td>
          <td class="num"><?= e((string) $p['posizione']) ?></td>
          <td class="num destra"><?= e($rom[(int) $p['casa']]) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($ignota && isset($tema['arco_giornaliero'])): ?>
    <h2>Quanto si sono mossi quel giorno</h2>
    <p class="condotto">
      Senza l'ora esatta, questo &egrave; il dato onesto: l'arco percorso da ogni corpo nelle
      ventiquattro ore. Dove il corpo non cambia segno, la sua posizione &egrave; certa comunque.
    </p>
    <div class="tabella-scorre">
      <table class="griglia">
        <thead><tr><th>Corpo</th><th>A mezzanotte</th><th>A fine giornata</th><th class="destra">Arco</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tema['arco_giornaliero'] as $chiave => $a):
          if ($chiave === 'asc') { continue; } ?>
          <tr>
            <td><?= e(ucfirst($chiave)) ?></td>
            <td class="num"><?= e((string) $a['da_testo']) ?></td>
            <td class="num"><?= e((string) $a['a_testo']) ?></td>
            <td class="num destra"><?= e(number_format((float) $a['arco'], 2, ',', '')) ?>&deg;</td>
            <td><?= $a['cambia_segno']
                ? '<span class="bollino bollino-attento">cambia segno</span>'
                : '<span class="bollino bollino-bene">resta nel segno</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="incerto">
          <td>Ascendente</td><td colspan="3" class="tenue">percorre l'intero zodiaco in ventiquattro ore</td>
          <td><span class="bollino">indeterminabile</span></td>
        </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h2>Aspetti <span class="conteggio"><?= e((string) $tema['aspetti']['conteggio']['totale']) ?></span></h2>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr>
        <th></th><th>Aspetto</th><th></th><th class="destra">Orbe</th>
        <th class="destra">Forza</th><th>Moto</th>
      </tr></thead>
      <tbody>
      <?php foreach ($tema['aspetti']['elenco'] as $a): ?>
        <tr data-corpi="<?= e($a['a'] . ' ' . $a['b']) ?>">
          <td><?= e((string) $a['nome_a']) ?></td>
          <td class="aspetto-<?= e((string) $a['natura']) ?>">
            <?= glifo((string) $a['glifo']) ?> <?= e((string) $a['aspetto_nome']) ?>
          </td>
          <td><?= e((string) $a['nome_b']) ?></td>
          <td class="num destra"><?= e(number_format((float) $a['orbe'], 2, ',', '')) ?>&deg;</td>
          <td class="num destra"><?= e(number_format((float) $a['forza'] * 100, 0, ',', '')) ?>%</td>
          <td class="tenue piccolo"><?= $a['applicativo'] === true ? 'applicativo'
              : ($a['applicativo'] === false ? 'separativo' : '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="due">
    <div>
      <h2>Bilanci</h2>
      <?php
      $b = $tema['bilanci']['segni'];
      $massimo = max(max($b['elementi']), max($b['modalita']));
      foreach (['elementi' => $b['elementi'], 'modalit&agrave;' => $b['modalita']] as $titolo => $gruppo): ?>
        <h3><?= $titolo ?></h3>
        <?php foreach ($gruppo as $nome => $valore): ?>
          <div class="barra" data-quota="<?= e((string) (int) round($valore / max(0.001, $massimo) * 100)) ?>">
            <span class="barra-eti el-<?= e($nome) ?>"><?= e(ucfirst($nome)) ?></span>
            <span class="traccia"><i class="riemp riemp-<?= e($nome) ?>"></i></span>
            <span class="barra-val"><?= e(number_format((float) $valore, 1, ',', '')) ?></span>
          </div>
        <?php endforeach;
      endforeach; ?>

      <h3>Emisferi</h3>
      <?php $em = $tema['bilanci']['emisferi'] ?? null; ?>
      <?php if ($em === null): ?>
        <p class="tenue">Non determinabili: senza l'ora <?= e($diCosa) ?> non si sa dove stesse l'orizzonte,
          e quindi quali pianeti fossero sopra o sotto, a oriente o a occidente.</p>
      <?php else: ?>
      <table class="griglia definizioni">
        <tbody>
          <tr><th>Sopra l'orizzonte</th><td class="num"><?= e((string) $em['sopra_orizzonte']) ?> su 10</td></tr>
          <tr><th>A oriente</th><td class="num"><?= e((string) $em['est']) ?> su 10</td></tr>
          <tr><th>Quadranti</th><td class="num">
            <?= e(implode(' · ', array_map(
                static fn (string $r, int $n): string => $r . ' ' . $n,
                ['I', 'II', 'III', 'IV'], array_values($em['quadranti'])))) ?>
          </td></tr>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div>
      <h2>Figura e signorie</h2>
      <?php $f = $tema['bilanci']['figura']; ?>
      <p class="figura-nome"><?= e((string) $f['nome']) ?></p>
      <p class="condotto"><?= e((string) $f['descrizione']) ?></p>
      <p class="tenue piccolo">
        Ampiezza <?= e(number_format((float) $f['ampiezza'], 1, ',', '')) ?>&deg;,
        vuoto massimo <?= e(number_format((float) $f['vuoto_massimo'], 1, ',', '')) ?>&deg;<?php
        if ($f['manico'] !== null): ?>, manico: <?= e($nomeDi((string) $f['manico'])) ?><?php endif; ?>.
      </p>

      <?php
        $d = $tema['bilanci']['dispositori'];
      ?>
      <h3>Dispositori</h3>
      <?php if ($d['dispositori_finali'] !== []): ?>
        <p>Dispositore finale: <strong><?= e(implode(', ', array_map($nomeDi, $d['dispositori_finali']))) ?></strong>
           &mdash; governa l'intera catena della carta.</p>
      <?php elseif ($d['anelli'] !== []): ?>
        <p>Nessun dispositore finale. La catena si chiude in un anello:
           <strong><?= e(implode(' ↔ ', array_map($nomeDi, $d['anelli'][0]))) ?></strong>,
           pianeti che si governano a vicenda senza che nessuno comandi.</p>
      <?php else: ?>
        <p class="tenue">Catena non determinabile.</p>
      <?php endif; ?>

      <?php if ($tema['configurazioni'] !== []): ?>
        <h3>Configurazioni</h3>
        <ul class="configurazioni">
          <?php foreach ($tema['configurazioni'] as $c): ?>
            <li><strong><?= e((string) $c['nome']) ?></strong>
                &mdash; <?= e(implode(', ', $c['nomi'])) ?>.
                <span class="tenue"><?= e((string) $c['dettaglio']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <?php if (($tema['stelle'] ?? []) !== []): ?>
    <h2>Stelle fisse in congiunzione</h2>
    <p class="condotto">Entro un grado, con le posizioni precessate alla data della carta.</p>
    <div class="tabella-scorre">
      <table class="griglia fitta">
        <thead><tr><th>Stella</th><th>Corpo</th><th class="destra">Orbe</th></tr></thead>
        <tbody>
        <?php foreach ($tema['stelle'] as $s): ?>
          <tr><td><?= e((string) $s['stella']) ?></td>
              <td><?= e(match ((string) $s['corpo']) { 'asc' => 'Ascendente', 'mc' => 'Medio Cielo', default => $nomeDi((string) $s['corpo']) }) ?></td>
              <td class="num destra"><?= e(number_format((float) $s['orbe'], 2, ',', '')) ?>&deg;</td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <nav class="derivati">
    <h2>Da questa carta</h2>
    <div class="azioni">
      <a class="bottone" href="<?= e(url('/carta/' . $gettone . '/transiti')) ?>">Il cielo di oggi sopra questa carta</a>
      <a class="bottone" href="<?= e(url('/carta/' . $gettone . '/sinastria')) ?>">Confronta con un'altra carta</a>
      <a class="bottone" href="<?= e(url('/carta/' . $gettone . '/derivate')) ?>">Le carte del tempo</a>
    </div>
  </nav>

  <?php if (!$archivio): ?>
  <div class="permalink">
    <h2>Il tuo indirizzo</h2>
    <p class="condotto">
      Questa carta vive qui. Conserva l'indirizzo: &egrave; l'unica chiave, non c'&egrave;
      nessun account da cui recuperarla.
    </p>
    <p class="permalink-url"><code><?= e(rtrim((string) \App\Core\Config::get('app.url_pubblico'), '/') . '/carta/' . $gettone) ?></code></p>
  </div>
  <?php endif; ?>

  <p class="tenue piccolo">
      Calcolata con Swiss Ephemeris <?= e((string) ($tema['meta']['versione_swe'] ?? '')) ?>
      in <?= e((string) ($tema['meta']['durata_ms'] ?? '?')) ?> ms
      &middot; &Delta;T <?= e(number_format((float) $tema['tempo']['delta_t_secondi'], 1, ',', '')) ?> s
      &middot; giorno giuliano <?= e(number_format((float) $tema['tempo']['jd_ut'], 5, ',', '')) ?>
  </p>

  <?php if (!($protetta ?? false) || \App\Auth\Auth::amministratore()): ?>
  <details class="avanzate cancella-carta">
    <summary>Cancella questa carta</summary>
    <form method="post" action="<?= e(url('/carta/' . $gettone . '/elimina')) ?>" class="modulo">
      <?= csrf() ?>
      <p class="aiuto">
        Si cancellano la carta e i dati di nascita. L'indirizzo smetter&agrave; di funzionare per
        chiunque l'abbia ricevuto, e non c'&egrave; modo di recuperarla.
      </p>
      <label class="scelta">
        <input type="checkbox" name="conferma" value="si" required>
        <span class="scelta-corpo"><span class="scelta-titolo">S&igrave;, cancellala per sempre</span></span>
      </label>
      <button type="submit" class="bottone bottone-male">Cancella</button>
    </form>
  </details>
  <?php endif; ?>
</article>
