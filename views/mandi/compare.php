<?php
// Compare one crop across 2 to 5 mandis (F5). Prices come from the price service; no travel cost is modelled.
$cfText = [
    'chooseCrop' => __('mp_choose_crop'), 'loadingMandis' => __('mp_loading_mandis'), 'errMandis' => __('mp_err_mandis'),
    'needTwo' => __('cf_need_two'), 'maxFive' => __('cf_max_five'), 'loading' => __('cf_loading'), 'err' => __('cf_err'),
    'best' => __('cf_best'), 'spread' => __('cf_spread'), 'datesDiffer' => __('cf_dates_differ'), 'skipped' => __('cf_skipped'),
    'stale' => __('cp_stale'), 'servedStale' => __('cp_served_stale'), 'bestTag' => __('cf_best_tag'), 'trendNa' => __('mp_trend_na'),
    'colRank' => __('cf_col_rank'), 'colMandi' => __('mp_mandi'), 'colPrice' => __('cf_col_price'),
    'colDiff' => __('cf_col_diff'), 'useLoc' => __('cn_use_location'), 'locating' => __('cn_locating'),
    'locDenied' => __('cn_denied'), 'locUnsupported' => __('cn_unsupported'), 'locChooseCrop' => __('cn_choose_crop'),
    'locNone' => __('cn_none'), 'locDone' => __('cn_done'), 'km' => __('cn_km'), 'colDate' => __('mp_col_date'), 'colTrend' => __('cf_col_trend'),
];
?>
<div class="container py-5">
    <div class="mb-4">
        <h2 class="fw-bold" style="color: var(--primary-green);">
            <i class="fa-solid fa-code-compare"></i> <?= e(__('cf_title')) ?>
        </h2>
        <p class="text-muted"><?= e(__('cf_desc')) ?></p>
    </div>
    <?php $mandiActive = 'compare'; require __DIR__ . '/_nav.php'; ?>

    <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--primary-orange);">
        <div class="card-body p-4">
            <form id="cfForm">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold" for="cfCrop"><?= e(__('mp_crop')) ?></label>
                        <select class="form-select" id="cfCrop">
                            <option value=""><?= e(__('mp_choose_crop')) ?></option>
                            <?php foreach ($crops as $c): ?>
                                <option value="<?= e($c) ?>"><?= e(\App\Helpers\MandiText::cropLabel($c, function ($k) { return __($k); })) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="form-label fw-bold m-0"><?= e(__('cf_pick_mandis')) ?></div>
                            <button type="button" class="btn btn-outline-success btn-sm" id="cfLoc"><i class="fa-solid fa-location-crosshairs"></i> <?= e(__('cn_use_location')) ?></button>
                        </div>
                        <div class="text-muted small mb-2"><?= e(__('cn_privacy')) ?></div>
                        <div id="cfMandis" class="row row-cols-1 row-cols-sm-2 g-1"></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary-custom fw-bold mt-3" id="cfGo" disabled><?= e(__('cf_compare')) ?></button>
            </form>
        </div>
    </div>

    <div id="cfStatus" class="alert d-none" role="alert"></div>
    <div id="cfNotices"></div>
    <div id="cfResult" class="d-none">
        <p class="fw-bold mb-1" id="cfBest"></p>
        <p class="text-muted" id="cfSpread"></p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr id="cfHead"></tr></thead>
                <tbody id="cfRows"></tbody>
            </table>
        </div>
        <p class="text-muted small"><?= e(__('cf_travel')) ?></p>
    </div>
</div>

<script>
(function () {
    var BASE = '<?= BASE_URL ?>/index.php?url=mandi/';
    var T = <?= json_encode($cfText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var MAX = 5;
    function el(id) { return document.getElementById(id); }
    function fmt(text, vars) { return String(text).replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? vars[k] : m; }); }
    function money(n) { return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
    function status(msg, kind) { var s = el('cfStatus'); s.className = 'alert alert-' + kind; s.textContent = msg; }
    function hideStatus() { el('cfStatus').className = 'alert d-none'; }
    function chosen() {
        return Array.prototype.map.call(el('cfMandis').querySelectorAll('input:checked'), function (i) { return i.value; });
    }
    function syncButton() {
        var n = chosen().length;
        el('cfGo').disabled = n < 2;
        // Once 5 are ticked, the rest cannot be ticked.
        Array.prototype.forEach.call(el('cfMandis').querySelectorAll('input'), function (i) { i.disabled = !i.checked && n >= MAX; });
    }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return { ok: false }; });
    }

    var loadToken = 0;
    el('cfCrop').addEventListener('change', function () {
        var crop = this.value, mine = ++loadToken;
        el('cfMandis').textContent = '';
        el('cfResult').classList.add('d-none');
        el('cfNotices').textContent = '';
        el('cfGo').disabled = true;
        if (!crop) { hideStatus(); return; }
        status(T.loadingMandis, 'info');
        getJson(BASE + 'mandis&crop=' + encodeURIComponent(crop)).then(function (j) {
            if (mine !== loadToken) { return; }      // a newer crop choice replaced this one
            if (!j.ok) { status(j.error || T.errMandis, 'danger'); return; }
            hideStatus();
            j.mandis.forEach(function (name, i) {
                var col = document.createElement('div'); col.className = 'col';
                var wrap = document.createElement('div'); wrap.className = 'form-check';
                var box = document.createElement('input'); box.type = 'checkbox'; box.className = 'form-check-input';
                box.id = 'cfm' + i; box.value = name; box.addEventListener('change', syncButton);
                var lab = document.createElement('label'); lab.className = 'form-check-label'; lab.htmlFor = box.id; lab.textContent = name;
                wrap.appendChild(box); wrap.appendChild(lab); col.appendChild(wrap);
                el('cfMandis').appendChild(col);
            });
        });
    });

    // "Use my location" (F4): tick the nearest mandis that have a price for the chosen crop.
    el('cfLoc').addEventListener('click', function () {
        var crop = el('cfCrop').value;
        if (!crop) { status(T.locChooseCrop, 'warning'); return; }
        if (!navigator.geolocation) { status(T.locUnsupported, 'warning'); return; }
        status(T.locating, 'info');
        var mine = loadToken;
        navigator.geolocation.getCurrentPosition(function (pos) {
            var url = BASE + 'nearby&crop=' + encodeURIComponent(crop) +
                '&lat=' + encodeURIComponent(pos.coords.latitude.toFixed(2)) +
                '&lon=' + encodeURIComponent(pos.coords.longitude.toFixed(2));
            getJson(url).then(function (j) {
                if (mine !== loadToken || el('cfCrop').value !== crop) { return; }   // crop changed meanwhile
                if (!j.ok) { status(j.error || T.err, 'danger'); return; }
                var boxes = Array.prototype.slice.call(el('cfMandis').querySelectorAll('input'));
                var byName = {};
                boxes.forEach(function (b) { b.checked = false; b.parentNode.querySelector('label').textContent = b.value; byName[b.value.toLowerCase()] = b; });
                var ticked = 0;
                j.data.mandis.forEach(function (m) {
                    var b = byName[m.mandi.toLowerCase()];
                    if (!b) { return; }
                    b.checked = true; ticked++;
                    var label = b.parentNode.querySelector('label');
                    label.textContent = m.mandi + ' (' + T.km.replace('{km}', m.distance_km) + ')';
                });
                syncButton();
                if (ticked === 0) { status(T.locNone, 'warning'); return; }
                status(T.locDone, 'success');
            });
        }, function () { status(T.locDenied, 'warning'); }, { timeout: 15000, maximumAge: 300000 });
    });

    function cell(tr, text, cls) { var td = document.createElement('td'); if (cls) { td.className = cls; } td.textContent = text; tr.appendChild(td); return td; }
    function notice(text) { var d = document.createElement('div'); d.className = 'alert alert-warning'; d.textContent = text; el('cfNotices').appendChild(d); }

    function render(d) {
        el('cfNotices').textContent = '';
        if (d.served_stale) { notice(T.servedStale); }
        if (d.date_spread_days > 0) { notice(fmt(T.datesDiffer, { days: d.date_spread_days })); }
        var oldest = null;
        d.rows.forEach(function (r) { if (r.age_days !== null && (oldest === null || r.age_days > oldest.age_days)) { oldest = r; } });
        if (oldest && oldest.age_days > 7) { notice(fmt(T.stale, { date: oldest.latest_date, days: oldest.age_days })); }
        if (d.skipped.length) { notice(fmt(T.skipped, { mandis: d.skipped.join(', ') })); }

        var top = d.rows[0];
        el('cfBest').textContent = fmt(T.best, { mandi: top.mandi, price: money(top.latest_price) });
        el('cfSpread').textContent = d.rows.length > 1 ? fmt(T.spread, { spread: money(d.spread), pct: d.spread_pct }) : '';
        var head = el('cfHead'); head.textContent = '';
        [T.colRank, T.colMandi, T.colPrice, T.colDiff, T.colDate, T.colTrend].forEach(function (h) {
            var th = document.createElement('th'); th.textContent = h; head.appendChild(th);
        });
        var body = el('cfRows'); body.textContent = '';
        d.rows.forEach(function (r, i) {
            var tr = document.createElement('tr');
            if (i === 0) { tr.className = 'table-success'; }
            cell(tr, String(i + 1));
            var m = cell(tr, r.mandi, 'fw-bold');
            if (i === 0) { var b = document.createElement('span'); b.className = 'badge bg-success ms-2'; b.textContent = T.bestTag; m.appendChild(b); }
            cell(tr, '\u20B9' + money(r.latest_price));
            cell(tr, i === 0 ? '-' : '-\u20B9' + money(top.latest_price - r.latest_price));
            cell(tr, r.latest_date || '-');
            cell(tr, r.trend_label || T.trendNa);
            body.appendChild(tr);
        });
        el('cfResult').classList.remove('d-none');
    }

    el('cfForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var names = chosen(), crop = el('cfCrop').value;
        if (names.length < 2) { status(T.needTwo, 'warning'); return; }
        if (names.length > MAX) { status(T.maxFive, 'warning'); return; }
        el('cfGo').disabled = true;
        el('cfResult').classList.add('d-none');
        status(T.loading, 'info');
        getJson(BASE + 'comparison&crop=' + encodeURIComponent(crop) + '&mandis=' + encodeURIComponent(names.join(','))).then(function (j) {
            if (!j.ok) { status(j.error || T.err, 'danger'); return; }
            hideStatus();
            render(j.data);
        }).then(function () { syncButton(); });
    });
})();
</script>
