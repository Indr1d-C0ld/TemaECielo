<?php
/**
 * @var list<array<string,mixed>> $messaggi @var array<int,list<array<string,mixed>>> $risposte
 * @var array<string,mixed> $medie @var array<string,string> $errori @var string $carta
 */
use App\Core\Session;

// Il momento di apertura del modulo sta in SESSIONE e non in un campo
// nascosto: in un campo sarebbe falsificabile con un colpo di forbici.
//
// E si segna solo la PRIMA volta. Azzerarlo a ogni ricaricamento sembrava
// ovvio e rendeva il portale inutilizzabile: chi sbagliava un campo tornava
// sulla pagina — che riazzerava il cronometro — correggeva in due secondi e
// si sentiva dire di nuovo che aveva fatto troppo in fretta. Un ciclo senza
// uscita. Il cronometro parte dalla prima visita e vale due ore.
// NB: il nome NON dev'essere $aperto — quella variabile arriva dal controller
// e dice se il guestbook accetta messaggi. Chiamando cosi' il cronometro lo si
// oscura, e alla prima visita (cronometro a zero) il modulo scompare
// dichiarando il guestbook chiuso.
$cronometro = (int) Session::get('__gb_aperto', 0);
if ($cronometro === 0 || (time() - $cronometro) > 7200) {
    Session::set('__gb_aperto', time());
}

$v = static fn (string $k, string $pre = ''): string => (string) ($dati[$k] ?? $pre);
$pagine = max(1, (int) ceil($totale / $per));

$stelle = static function (?int $n): string {
    if ($n === null) { return '<span class="tenue">&mdash;</span>'; }
    return '<span class="stelle" title="' . $n . ' su 5">'
        . str_repeat('&#9733;', $n) . '<span class="stelle-vuote">' . str_repeat('&#9733;', 5 - $n) . '</span></span>';
};
?>
<article class="cartiglio">
  <p class="occhiello">Chi e&rsquo; passato</p>
  <h1>Guestbook</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>

  <?php if ($medie['messaggi'] > 0): ?>
    <div class="numeri">
      <div class="numero">
        <span class="numero-val"><?= e((string) $medie['messaggi']) ?></span>
        <span class="numero-eti">messaggi</span>
      </div>
      <div class="numero">
        <span class="numero-val"><?= $medie['gradimento'] !== null
            ? e(number_format((float) $medie['gradimento'], 2, ',', '')) : '&mdash;' ?></span>
        <span class="numero-eti">gradimento medio<?= $medie['n_gradimento'] > 0
            ? ' · ' . e((string) $medie['n_gradimento']) . ' voti' : '' ?></span>
      </div>
      <div class="numero">
        <span class="numero-val"><?= $medie['attinenza'] !== null
            ? e(number_format((float) $medie['attinenza'], 2, ',', '')) : '&mdash;' ?></span>
        <span class="numero-eti">attinenza media<?= $medie['n_attinenza'] > 0
            ? ' · ' . e((string) $medie['n_attinenza']) . ' voti' : '' ?></span>
      </div>
    </div>
  <?php endif; ?>

  <p class="condotto">
    Due voti distinti, e tenerli separati &egrave; la scelta giusta: <em>gradimento</em>
    &egrave; quanto ti &egrave; piaciuto il portale, <em>attinenza</em> quanto il risultato ti
    &egrave; parso corrispondente. Sono due giudizi diversi, e mescolarli li renderebbe
    inutili entrambi.
  </p>

  <?php if ($messaggi === []): ?>
    <?php if ($totale > 0): ?>
      <p class="condotto tenue">
        Questa pagina non c'&egrave;: i messaggi finiscono a pagina <?= e((string) $pagine) ?>.
        <a href="<?= e(url('/guestbook?p=' . $pagine)) ?>">Vai all'ultima</a>
        o <a href="<?= e(url('/guestbook')) ?>">torna ai pi&ugrave; recenti</a>.
      </p>
    <?php else: ?>
      <p class="condotto tenue">Nessun messaggio ancora. Puoi essere il primo.</p>
    <?php endif; ?>
  <?php else: ?>
    <div class="messaggi">
      <?php foreach ($messaggi as $m): ?>
        <article class="messaggio">
          <header>
            <span class="messaggio-nome"><?= e((string) $m['nome']) ?></span>
            <?php if ($m['paese'] !== null): ?>
              <span class="tenue piccolo"><?= e((string) $m['paese']) ?></span>
            <?php endif; ?>
            <time class="tenue piccolo" datetime="<?= e((string) $m['creato']) ?>">
              <?= e(date('j/n/Y', strtotime((string) $m['creato']))) ?>
            </time>
          </header>
          <p><?= nl2br(e((string) $m['messaggio'])) ?></p>
          <footer class="messaggio-voti">
            <span>gradimento <?= $stelle($m['voto_gradimento'] === null ? null : (int) $m['voto_gradimento']) ?></span>
            <span>attinenza <?= $stelle($m['voto_attinenza'] === null ? null : (int) $m['voto_attinenza']) ?></span>
          </footer>
          <?php foreach ($risposte[(int) $m['id']] ?? [] as $ris): ?>
            <aside class="risposta">
              <span class="risposta-chi">Risposta<?= $ris['autore'] !== '' ? ' di ' . e((string) $ris['autore']) : '' ?></span>
              <p><?= nl2br(e((string) $ris['corpo'])) ?></p>
            </aside>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pagine > 1): ?>
      <nav class="paginazione">
        <?php if ($pagina > 1): ?><a href="<?= e(url('/guestbook?p=' . ($pagina - 1))) ?>">&larr; pi&ugrave; recenti</a><?php endif; ?>
        <span><?= e((string) $pagina) ?> di <?= e((string) $pagine) ?></span>
        <?php if ($pagina < $pagine): ?><a href="<?= e(url('/guestbook?p=' . ($pagina + 1))) ?>">pi&ugrave; vecchi &rarr;</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

  <section id="firma" class="firma">
    <h2>Lascia un saluto</h2>

    <?php if (!$aperto): ?>
      <p class="lampo lampo-attento">Il guestbook &egrave; chiuso in questo momento.</p>
    <?php else: ?>
      <?php if ($errori !== []): ?>
        <p class="lampo lampo-male" role="alert">
          <?= e(implode(' ', array_values($errori))) ?>
        </p>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/guestbook')) ?>" class="modulo">
        <?= csrf() ?>
        <input type="hidden" name="carta" value="<?= e($carta) ?>">

        <!-- Campo esca: nessun essere umano lo vede, i programmi lo riempiono. -->
        <div class="esca" aria-hidden="true">
          <label for="sito">Lascia vuoto questo campo</label>
          <input type="text" id="sito" name="sito" tabindex="-1" autocomplete="off">
        </div>

        <div class="campo">
          <label for="nome">Come ti chiami <span class="facoltativo">(o come vuoi firmarti)</span></label>
          <input type="text" id="nome" name="nome" maxlength="80" value="<?= e($v('nome')) ?>" autocomplete="off">
        </div>

        <div class="campo">
          <label for="messaggio">Il tuo messaggio</label>
          <textarea id="messaggio" name="messaggio" rows="5" required maxlength="4000"
                    placeholder="Che cosa ti ha colpito?"><?= e($v('messaggio')) ?></textarea>
        </div>

        <div class="voti">
          <?php foreach ([
            'gradimento' => ['Ti &egrave; piaciuto il portale?', 'per niente', 'moltissimo'],
            'attinenza'  => ['Il risultato ti &egrave; parso corrispondente?', 'per niente', 'in pieno'],
          ] as $campo => [$domanda, $basso, $alto]): ?>
            <fieldset class="voto">
              <legend><?= $domanda ?></legend>
              <div class="voto-scala">
                <span class="tenue piccolo"><?= $basso ?></span>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label class="voto-stella">
                    <input type="radio" name="<?= e($campo) ?>" value="<?= e((string) $i) ?>"
                           <?= $v($campo) === (string) $i ? 'checked' : '' ?>>
                    <span><?= e((string) $i) ?></span>
                  </label>
                <?php endfor; ?>
                <span class="tenue piccolo"><?= $alto ?></span>
              </div>
            </fieldset>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="bottone bottone-primo">Firma</button>

        <p class="nota-piccola">
          <?php if ($moderato): ?>
            I messaggi passano da una coda prima di comparire: non &egrave; censura,
            &egrave; per tenere fuori i programmi.
          <?php endif; ?>
          Del tuo messaggio si conservano l&rsquo;indirizzo di rete e il browser, come per ogni
          altra pagina &mdash; vedi l&rsquo;<a href="<?= e(url('/pagina/informativa')) ?>">informativa</a>.
          Non ci sono captcha di terzi: sarebbe un&rsquo;altra fuga di dati, proprio in un portale
          costruito per non farne.
        </p>
      </form>
    <?php endif; ?>
  </section>
</article>
