<?php
// Crop price comparison (F2). Real past prices only: no invented yield or revenue.
$firstCrop = $crops[0] ?? 'Potato';
$cpText = [
    'loading'      => __('cp_loading'),
    'err'          => __('cp_err'),
    'loadingMandis'=> __('mp_loading_mandis'),
    'errMandis'    => __('mp_err_mandis'),
    'noData'       => __('cp_no_data'),
    'unavailable'  => __('cp_unavailable'),
    'lastPrice'    => __('cp_last_price'),
    'avg30'        => __('cp_avg30'),
    'above'        => __('cp_above'),
    'below'        => __('cp_below'),
    'up'           => __('cp_change_up'),
    'down'         => __('cp_change_down'),
    'swing'        => __('cp_swing'),
    'swing_low'    => __('cp_swing_low'),
    'swing_medium' => __('cp_swing_medium'),
    'swing_high'   => __('cp_swing_high'),
    'stale'        => __('cp_stale'),
    'servedStale'  => __('cp_served_stale'),
    'viewPrices'   => __('cp_view_prices'),
    'chooseMandi'  => __('mp_choose_mandi'),
];
?>
<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold" style="color: var(--primary-green);">
                <i class="fa-solid fa-scale-balanced"></i> <?= e(__('cp_title')) ?>
            </h2>
            <p class="text-muted"><?= e(__('cp_desc')) ?></p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--primary-orange);">
        <div class="card-body p-4">
            <form id="cpForm" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold" for="cpMandi"><?= e(__('mp_mandi')) ?></label>
                    <select class="form-select" id="cpMandi" required disabled>
                        <option value=""><?= e(__('mp_loading_mandis')) ?></option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary-custom w-100 fw-bold" id="cpGo" disabled><?= e(__('cp_compare')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="cpStatus" class="alert d-none" role="alert"></div>
    <div id="cpNotices"></div>
    <div class="row g-3" id="cpCards"></div>
    <p class="text-muted small mt-4 d-none" id="cpDisclaimer"><?= e(__('cp_disclaimer')) ?></p>
</div>

<script>
(function () {
    var BASE = '<?= BASE_URL ?>/index.php?url=';
    var FIRST_CROP = <?= json_encode($firstCrop) ?>;
    var T = <?= json_encode($cpText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    function el(id) { return document.getElementById(id); }
    function fmt(text, vars) {
        return String(text).replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? vars[k] : m; });
    }
    function rupee(n) { return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
    function showStatus(msg, kind) {
        var s = el('cpStatus');
        s.className = 'alert alert-' + kind;
        s.textContent = msg;
    }
    function hideStatus() { el('cpStatus').className = 'alert d-none'; }
    function line(parent, text, cls) {
        var d = document.createElement('div');
        if (cls) { d.className = cls; }
        d.textContent = text;
        parent.appendChild(d);
    }

    function card(c) {
        var col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4';
        var box = document.createElement('div');
        box.className = 'card border-0 shadow-sm h-100';
        var body = document.createElement('div');
        body.className = 'card-body';
        var h = document.createElement('h4');
        h.className = 'fw-bold';
        h.textContent = c.label || c.crop;
        body.appendChild(h);
        if (c.status === 'ok') {
            line(body, fmt(T.lastPrice, { price: rupee(c.latest_price), date: c.latest_date }), 'mb-2');
            line(body, fmt(T.avg30, { price: rupee(c.avg_30) }), 'mb-2');
            var p = Math.abs(c.vs_period_pct);
            line(body, fmt(c.vs_period_pct >= 0 ? T.above : T.below, { pct: p, days: c.days_covered }), 'mb-2');
            if (c.change_90_pct !== null && c.change_90_pct !== undefined) {
                line(body, fmt(c.change_90_pct >= 0 ? T.up : T.down, { pct: Math.abs(c.change_90_pct) }), 'mb-2');
            }
            if (c.swing) {
                var colour = c.swing === 'low' ? 'success' : (c.swing === 'medium' ? 'warning' : 'danger');
                var b = document.createElement('span');
                b.className = 'badge bg-' + colour;
                b.textContent = fmt(T.swing, { level: T['swing_' + c.swing] || c.swing });
                body.appendChild(b);
            }
        } else {
            line(body, c.status === 'unavailable' ? T.unavailable : T.noData, 'text-muted');
        }
        box.appendChild(body);
        col.appendChild(box);
        return col;
    }

    function render(d) {
        var notices = el('cpNotices');
        notices.textContent = '';
        if (d.served_stale) {
            var w = document.createElement('div'); w.className = 'alert alert-warning'; w.textContent = T.servedStale; notices.appendChild(w);
        }
        // One data-age notice for the oldest price shown.
        var oldest = null;
        d.crops.forEach(function (c) { if (c.status === 'ok' && (oldest === null || c.age_days > oldest.age_days)) { oldest = c; } });
        if (oldest && oldest.age_days > 7) {
            var a = document.createElement('div'); a.className = 'alert alert-warning';
            a.textContent = fmt(T.stale, { date: oldest.latest_date, days: oldest.age_days });
            notices.appendChild(a);
        }
        var cards = el('cpCards');
        cards.textContent = '';
        d.crops.forEach(function (c) { cards.appendChild(card(c)); });
        var link = document.createElement('a');
        link.className = 'btn btn-outline-success mt-3';
        link.href = BASE + 'mandi';
        link.textContent = T.viewPrices;
        var wrap = document.createElement('div'); wrap.className = 'col-12'; wrap.appendChild(link);
        cards.appendChild(wrap);
        el('cpDisclaimer').classList.remove('d-none');
    }

    // Mandi list comes from the same endpoint as the Market Prices page.
    showStatus(T.loadingMandis, 'info');
    fetch(BASE + 'mandi/mandis&crop=' + encodeURIComponent(FIRST_CROP)).then(function (r) { return r.json(); }).then(function (j) {
        hideStatus();
        if (!j.ok) { showStatus(j.error || T.errMandis, 'danger'); return; }
        var m = el('cpMandi');
        m.textContent = '';
        m.appendChild(new Option(T.chooseMandi, ''));
        j.mandis.forEach(function (n) { m.appendChild(new Option(n, n)); });
        m.disabled = false;
        el('cpGo').disabled = false;
    }).catch(function () { showStatus(T.errMandis, 'danger'); });

    el('cpForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var mandi = el('cpMandi').value;
        if (!mandi) { return; }
        el('cpGo').disabled = true;
        el('cpCards').textContent = '';
        el('cpNotices').textContent = '';
        showStatus(T.loading, 'info');
        fetch(BASE + 'farmer/getAiRecommendation', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mandi: mandi })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.success) { showStatus(T.err, 'danger'); return; }
            hideStatus();
            render(d);
        }).catch(function () { showStatus(T.err, 'danger'); })
          .then(function () { el('cpGo').disabled = false; });
    });
})();
</script>
