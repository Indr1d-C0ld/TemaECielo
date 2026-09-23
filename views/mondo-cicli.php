<?php
/**
 * I cicli dei pianeti lenti e l'indice di Barbault.
 *
 * @var list<array{0:float,1:float}> $indice
 * @var array<string,list<array<string,mixed>>> $passaggi
 * @var list<array<string,mixed>> $eventi @var float $oggi @var array<string,mixed> $luogo
 */
use App\Grafica\IndiceCiclico;
use App\Mondo\Cicli;
use App\Mondo\Mondo;

$data = static fn (float $jd): string => gmdate('j/n/Y', Mondo::unix($jd));
$anno = static fn (float $jd): string => gmdate('Y', Mondo::unix($jd));
$minimi = Cicli::minimi($indice, 12.0);
?>
<article class="cartiglio">
  <p class="occhiello"><a href="<?= e(url('/mondo')) ?>">Astrologia mondiale</a></p>
  <h1>I cicli dei pianeti lenti</h1>
  <div class="filetto"><i></i><span>&#10022;</span><i></i></div>
  <p class="condotto">
    Giove, Saturno, Urano, Nettuno e Plutone si muovono lenti, e si incontrano di rado: ogni loro
    congiunzione apre un ciclo di anni o di secoli. L'astrologia mondiale del Novecento &mdash; Andr&eacute;
    Barbault soprattutto, con <em>Les astres et l'histoire</em> (1967) &mdash; ha letto la storia su questi
    ritmi. Qui sono calcolati tutti, dal <?= Mondo::ANNO_MIN ?> al <?= Mondo::ANNO_MAX ?>.
  </p>

  <h2>L'indice ciclico</h2>
  <figure class="indice-riquadro">
    <div class="indice-scorre"><?= (new IndiceCiclico($indice, $eventi, $oggi))->disegna() ?></div>
    <figcaption>
      La somma delle dieci distanze angolari fra i cinque pianeti lenti. Quando si raccolgono in una
      parte dello zodiaco l'indice scende, e Barbault vi leggeva i periodi di tensione e di crisi del
      mondo; quando si sparpagliano sale. La linea tratteggiata &egrave; la media dei sei secoli.
      Le tacche sotto l'asse sono gli eventi e le fondazioni dell'archivio: ciascuna porta alla sua carta.
      <?php if ($minimi !== []): ?>
        I minimi: <?= e(implode(', ', array_map(static fn (array $m): string => gmdate('Y', Mondo::unix($m[0])), $minimi))) ?>.
      <?php endif; ?>
    </figcaption>
  </figure>

  <?php foreach (Cicli::COPPIE as $chiave => $c): $elenco = $passaggi[$chiave] ?? []; ?>
    <details class="ciclo" <?= in_array($chiave, ['giove-saturno', 'saturno-plutone'], true) ? 'open' : '' ?>>
      <summary><h2><?= e($c['nome']) ?> <span class="tenue piccolo">un ciclo ogni <?= e($c['anni']) ?> &middot;
        <?= count($elenco) ?> congiunzioni</span></h2></summary>
      <p class="condotto"><?= e($c['tema']) ?></p>
      <?php if ($chiave === 'giove-saturno'): ?>
        <p class="condotto tenue piccolo">
          Una <em>mutazione</em> apre una serie di almeno due congiunzioni in un elemento nuovo. Una congiunzione
          <em>isolata</em> cade fuori dalla serie in corso: l'anticipo di quella che verr&agrave; &mdash; il 1980
          in Bilancia prima dell'aria del 2020 &mdash; o un ritorno a quella che finisce.
        </p>
      <?php endif; ?>
      <div class="tabella-scorre">
        <table class="griglia fitta">
          <thead><tr><th>Anno</th><th>Grado</th><?php if ($chiave === 'giove-saturno'): ?><th>Elemento</th><?php endif; ?><th>Cade su</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($elenco as $p): ?>
            <tr<?= $chiave === 'giove-saturno' && !empty($p['mutazione']) ? ' class="riga-mutazione"' : '' ?>>
              <td class="num"><?= e($anno((float) $p['jd'])) ?>
                <?php if (count($p['passaggi']) > 1): ?>
                  <span class="tenue piccolo" title="<?= e(implode(', ', array_map($data, $p['passaggi']))) ?>">tripla</span>
                <?php endif; ?></td>
              <td class="num"><?= e(Mondo::grado((float) $p['lon'])) ?></td>
              <?php if ($chiave === 'giove-saturno'): ?>
                <td><?= e((string) $p['elemento']) ?><?= !empty($p['mutazione']) ? ' <span class="bollino">mutazione</span>' : '' ?><?=
                  !empty($p['fuori_serie']) ? ' <span class="bollino" title="Una congiunzione isolata in un altro elemento: l\'anticipo della serie che verrà, o un ritorno a quella che finisce">isolata</span>' : '' ?></td>
              <?php endif; ?>
              <td class="piccolo">
                <?php foreach (array_slice($p['colpi'], 0, 3) as $i => $x): ?><?= $i > 0 ? ', ' : '' ?><?= e($x['punto']) ?> di
                  <a href="<?= e(url('/archivio/' . $x['slug'])) ?>"><?= e($x['nome']) ?></a><?php endforeach; ?>
              </td>
              <td><a href="<?= e(url('/mondo/carta?' . http_build_query(['jd' => $p['jd'], 'tipo' => 'congiunzione', 'coppia' => $chiave, 'luogo' => $luogo['chiave']]))) ?>">la carta</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endforeach; ?>
</article>
