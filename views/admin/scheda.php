<?php
/**
 * La scheda d'archivio di una carta.
 *
 * @var array<string,mixed> $carta @var array<string,mixed> $scheda @var bool $esiste @var ?string $errore
 */
use App\Archivio\Archivio;
?>
<article class="cartiglio">
  <p class="occhiello">Regia &middot; archivio</p>
  <h1><?= e((string) ($scheda['nome'] ?: $carta['nome'])) ?></h1>
  <?= vista('admin/_nav') ?>

  <p class="condotto">
    La carta del <?= e(date('j/n/Y', strtotime((string) $carta['data_nascita']))) ?>
    <?= $carta['ora_nascita'] !== null ? 'alle ' . e(substr((string) $carta['ora_nascita'], 0, 5)) : '(ora ignota)' ?>,
    <?= e((string) $carta['luogo_nome']) ?>.
    <a href="<?= e(url('/carta/' . $carta['gettone'])) ?>">Apri la carta</a>
    <?php if ($esiste && (int) ($scheda['pubblicata'] ?? 0) === 1 && isset($scheda['slug'])): ?>
      &middot; <a href="<?= e(url('/archivio/' . $scheda['slug'])) ?>">vedila nell'archivio</a>
    <?php endif; ?>
  </p>

  <?php if ($errore !== null): ?><p class="lampo lampo-male" role="alert"><?= e((string) $errore) ?></p><?php endif; ?>

  <form method="post" action="<?= e(url('/admin/carte/' . $carta['gettone'])) ?>" class="modulo">
    <?= csrf() ?>
    <div class="quadro-griglia">
      <div class="campo campo-largo">
        <label for="nome">Nome pubblico</label>
        <input type="text" id="nome" name="nome" maxlength="160" required value="<?= e((string) $scheda['nome']) ?>">
      </div>
      <div class="campo">
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo">
          <?php foreach (Archivio::TIPI as $k => $n): ?>
            <option value="<?= e($k) ?>" <?= $scheda['tipo'] === $k ? 'selected' : '' ?>><?= e($n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
          <?php foreach (Archivio::CATEGORIE as $tipo => $cats): ?>
            <optgroup label="<?= e(Archivio::TIPI[$tipo]) ?>">
              <?php foreach ($cats as $c): ?>
                <option value="<?= e($c) ?>" <?= $scheda['categoria'] === $c && $scheda['tipo'] === $tipo ? 'selected' : '' ?>><?= e(\App\Archivio\Archivio::etichetta($c)) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <p class="aiuto">Se non corrisponde al tipo, si usa la prima categoria di quel tipo.</p>
      </div>
      <div class="campo">
        <label for="rodden">Affidabilit&agrave; dell'ora (Rodden)</label>
        <select id="rodden" name="rodden">
          <?php foreach (Archivio::RODDEN as $k => [$n, $d]): ?>
            <option value="<?= e($k) ?>" <?= $scheda['rodden'] === $k ? 'selected' : '' ?>><?= e($k . ' — ' . $n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="url_fonte">Collegamento alla fonte</label>
        <input type="url" id="url_fonte" name="url_fonte" maxlength="500" value="<?= e((string) $scheda['url_fonte']) ?>" placeholder="https://&hellip;">
      </div>
      <div class="campo campo-largo">
        <label for="fonte">Fonte dei dati</label>
        <input type="text" id="fonte" name="fonte" maxlength="500" value="<?= e((string) $scheda['fonte']) ?>"
               placeholder="Atto di nascita, biografia, verbale&hellip;">
      </div>
      <div class="campo campo-largo">
        <label for="nota">Nota <span class="facoltativo">(Markdown)</span></label>
        <textarea id="nota" name="nota" rows="5" maxlength="4000"><?= e((string) $scheda['nota']) ?></textarea>
        <p class="aiuto">Chi era, che cosa accadde. Due righe bastano: la carta dice il resto.</p>
      </div>
    </div>
    <label class="scelta">
      <input type="checkbox" name="pubblicata" value="1" <?= (int) $scheda['pubblicata'] === 1 ? 'checked' : '' ?>>
      <span class="scelta-corpo"><span class="scelta-titolo">Pubblicata nell'archivio</span>
        <span class="scelta-spiega">Senza la spunta la scheda resta visibile solo alla regia.</span></span>
    </label>
    <div class="quadro-azioni">
      <button type="submit" class="bottone bottone-primo" name="azione" value="salva">Salva la scheda</button>
      <?php if ($esiste): ?>
        <button type="submit" class="bottone bottone-male" name="azione" value="ritira">Togli dall'archivio</button>
      <?php endif; ?>
    </div>
  </form>
</article>
