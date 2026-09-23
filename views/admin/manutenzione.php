<?php /** @var array<string,string> $stato @var list<array<string,mixed>> $partizioni @var array<string,mixed> $cache */ ?>
<article class="cartiglio">
  <p class="occhiello">Regia</p>
  <h1>Manutenzione</h1>
  <?= vista('admin/_nav') ?>

  <h2>Stato del sistema</h2>
  <table class="griglia definizioni">
    <tbody>
    <?php foreach ($stato as $voce => $valore): ?>
      <tr><th><?= e($voce) ?></th><td class="num"><?= e($valore) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>Cache del motore</h2>
  <p class="aiuto">
    La tabella <code>calcoli</code> tiene due cose diverse. I <strong>permalink</strong> sono
    l'unica copia di una carta e non si buttano mai: chi ne perde l'indirizzo perde la carta.
    Le righe di <strong>cache</strong> sono calcoli gi&agrave; fatti, tenuti per non rifarli, e si
    possono buttare in qualunque momento &mdash; si rifanno in quaranta millisecondi.
  </p>
  <dl class="anagrafe">
    <div><dt>Permalink</dt><dd class="num"><?= e(number_format((float) ($cache['permalink'] ?? 0), 0, ',', '.')) ?></dd></div>
    <div><dt>Righe di cache</dt><dd class="num"><?= e(number_format((float) ($cache['cache'] ?? 0), 0, ',', '.')) ?></dd></div>
    <div><dt>Peso complessivo</dt><dd class="num"><?= e(number_format((float) ($cache['mb'] ?? 0), 1, ',', '.')) ?> MB</dd></div>
    <div><dt>Cache pi&ugrave; vecchia</dt><dd><?= e((string) ($cache['piu_vecchia'] ?? '&mdash;')) ?></dd></div>
  </dl>
  <p class="nota-piccola">
    Il portale sfoltisce da s&eacute; ci&ograve; che nessuno richiede da oltre trenta giorni, una
    scrittura su duecento. Per farlo subito, o per svuotare tutto dopo aver cambiato la
    struttura delle carte:
    <code>php bin/console.php cache:purga</code> &mdash; con <code>0</code> butta tutta la cache.
    I permalink restano in ogni caso.
  </p>

  <h2>Partizioni di <code>accessi</code></h2>
  <p class="condotto">Le nuove si aggiungono con <code>php bin/console.php partizioni</code>.</p>
  <div class="tabella-scorre">
    <table class="griglia fitta">
      <thead><tr><th>Partizione</th><th class="destra">Righe (stima)</th></tr></thead>
      <tbody>
      <?php foreach ($partizioni as $p): ?>
        <tr><td class="num"><?= e((string) $p['nome']) ?></td>
            <td class="num destra"><?= e(number_format((float) $p['righe'], 0, ',', '.')) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
