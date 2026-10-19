<?php
// "Market Prices" card on the dashboard. Variable from DashboardController: $marketCard (see MarketCard::summary()).
$mcLine = \App\Helpers\MarketCard::alertLine($marketCard, function ($k) { return __($k); });
$mcLinks = [
    ['mandi',         'fa-chart-line',   'mp_title'],
    ['mandi/compare', 'fa-code-compare', 'cf_title'],
    ['advisory',      'fa-comments',     'mn_ask'],
    ['alerts',        'fa-bell',         'mn_alerts'],
];
?>
<div class="card border-0 shadow-sm rounded-4 mb-4" id="market-card" style="border-left: 5px solid var(--primary-orange) !important;">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div>
                <h5 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-success"></i> <?= e(__('dm_title')) ?></h5>
                <p class="text-muted small mb-0"><?= e(__('dm_desc')) ?></p>
            </div>
            <span id="market-health" class="badge bg-secondary-subtle text-secondary-emphasis border d-none" role="status"></span>
        </div>
        <?php if ($mcLine !== ''): ?>
            <p class="mb-3 mt-2 <?= e($marketCard['unseen'] > 0 ? 'fw-bold text-success' : 'text-muted') ?>">
                <i class="fa-solid fa-bell"></i> <?= e($mcLine) ?>
                <?php if ($marketCard['unseen'] > 0): ?>
                    <a href="<?= e(BASE_URL) ?>/index.php?url=alerts" class="ms-1"><?= e(__('dm_see_alerts')) ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <?php foreach ($mcLinks as $mcLink): ?>
                <a class="btn btn-outline-success btn-sm fw-bold" href="<?= e(BASE_URL) ?>/index.php?url=<?= e($mcLink[0]) ?>"><i class="fa-solid <?= e($mcLink[1]) ?>"></i> <?= e(__($mcLink[2])) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var URL_ = <?= json_encode(BASE_URL . '/index.php?url=mandi/health', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var T = <?= json_encode([
        'ok' => __('mh_ok'), 'stale' => __('mh_stale'), 'degraded' => __('mh_degraded'), 'down' => __('mh_down'),
        'local' => __('mh_local'), 'hosted' => __('mh_hosted'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var badge = document.getElementById('market-health');
    if (!badge || !window.fetch) { return; }
    function fmt(text, vars) { return String(text).replace(/\{(\w+)\}/g, function (m, k) { return vars[k] === undefined || vars[k] === null ? m : vars[k]; }); }
    function show(level, text, cls) {
        badge.className = 'badge border ' + cls;
        badge.textContent = text;
        badge.title = text;
        badge.classList.remove('d-none');
    }
    fetch(URL_, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var d = (j && j.data) || { level: 'down' };
            var level = T[d.level] ? d.level : 'down';
            var text = fmt(T[level], { date: d.latest_date, days: d.days_stale });
            if (level !== 'down' && T[d.source]) { text += ' (' + T[d.source] + ')'; }
            show(level, text, level === 'ok' ? 'bg-success-subtle text-success-emphasis' : (level === 'down' ? 'bg-danger-subtle text-danger-emphasis' : 'bg-warning-subtle text-warning-emphasis'));
        })
        .catch(function () { show('down', T.down, 'bg-danger-subtle text-danger-emphasis'); });
})();
</script>
