<?php
// Sub-navigation shared by the Market Prices pages. Set $mandiActive to prices|compare|ask|alerts before including.
$mandiActive = $mandiActive ?? 'prices';
$mandiTabs = [
    'prices'  => ['mandi',         'fa-chart-line',     'mp_title'],
    'compare' => ['mandi/compare', 'fa-code-compare',   'cf_title'],
    'ask'     => ['advisory',      'fa-comments',       'mn_ask'],
    'alerts'  => ['alerts',        'fa-bell',           'mn_alerts'],
];
?>
<ul class="nav nav-pills mb-4 flex-wrap gap-1">
    <?php foreach ($mandiTabs as $key => $tab): ?>
        <li class="nav-item">
            <a class="nav-link <?= e($key === $mandiActive ? 'active' : '') ?>" aria-current="<?= e($key === $mandiActive ? 'page' : 'false') ?>"
               href="<?= e(BASE_URL) ?>/index.php?url=<?= e($tab[0]) ?>"><i class="fa-solid <?= e($tab[1]) ?>"></i> <?= e(__($tab[2])) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
