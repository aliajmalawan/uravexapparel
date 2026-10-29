<?php
/**
 * Single-series SVG bar chart with hover/focus tooltips and a table view.
 * @var list<array{label:string, value:int, display:string}> $points
 * @var string $title
 */
$w = 720; $h = 240; $padL = 8; $padB = 28; $padT = 12;
$max = max(1, ...array_map(static fn ($p) => $p['value'], $points ?: [['value' => 1]]));
$n = max(1, count($points));
$slot = ($w - $padL) / $n;
$barW = max(2, min(28, $slot - 2));
$plotH = $h - $padB - $padT;
$ticks = 4;
$labelEvery = (int) max(1, ceil($n / 8));
$id = 'chart-' . substr(md5($title), 0, 6);
?>
<figure class="chart-wrap" data-chart>
  <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h ?>" role="img" aria-labelledby="<?= $id ?>-t">
    <title id="<?= $id ?>-t"><?= e($title) ?></title>
    <?php for ($i = 0; $i <= $ticks; $i++): $y = $padT + $plotH - $plotH * $i / $ticks; ?>
      <line class="grid" x1="<?= $padL ?>" x2="<?= $w ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>"/>
    <?php endfor; ?>
    <?php foreach ($points as $i => $p):
        $bh = $p['value'] > 0 ? max(2, $plotH * $p['value'] / $max) : 0;
        $x = $padL + $i * $slot + ($slot - $barW) / 2;
        $y = $padT + $plotH - $bh; ?>
      <?php if ($bh > 0): ?>
        <path class="bar" tabindex="0" data-tip="<?= e($p['label'] . ': ' . $p['display']) ?>"
              d="M<?= round($x, 1) ?>,<?= round($padT + $plotH, 1) ?> V<?= round($y + min(4, $bh), 1) ?> q0,-<?= min(4, $bh) ?> <?= min(4, $barW / 2) ?>,-<?= min(4, $bh) ?> H<?= round($x + $barW - min(4, $barW / 2), 1) ?> q<?= min(4, $barW / 2) ?>,0 <?= min(4, $barW / 2) ?>,<?= min(4, $bh) ?> V<?= round($padT + $plotH, 1) ?> Z"><title><?= e($p['label'] . ': ' . $p['display']) ?></title></path>
      <?php endif; ?>
      <?php if ($i % $labelEvery === 0): ?>
        <text class="axis" x="<?= round($x + $barW / 2, 1) ?>" y="<?= $h - 8 ?>" text-anchor="middle"><?= e($p['short'] ?? $p['label']) ?></text>
      <?php endif; ?>
    <?php endforeach; ?>
  </svg>
  <figcaption class="definition">Peak: <?= e($peak ?? '') ?></figcaption>
  <details class="mt-2"><summary class="small">View as table</summary>
    <div class="table-scroll mt-2"><table class="admin-table"><thead><tr><th scope="col">Date</th><th scope="col" class="num">Value</th></tr></thead><tbody>
      <?php foreach ($points as $p): ?><tr><td><?= e($p['label']) ?></td><td class="num"><?= e($p['display']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </details>
</figure>
