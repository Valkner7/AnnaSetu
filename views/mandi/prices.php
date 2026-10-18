<div class="container py-5">
    <div class="mb-4">
        <h2 class="fw-bold" style="color: var(--primary-green);">
            <i class="fa-solid fa-chart-line"></i> <?= e(__('mp_title')) ?>
        </h2>
        <p class="text-muted"><?= e(__('mp_subtitle')) ?></p>
    </div>

    <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--primary-orange);">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="crop"><?= e(__('mp_crop')) ?></label>
                    <select class="form-select" id="crop">
                        <option value=""><?= e(__('mp_choose_crop')) ?></option>
                        <?php foreach ($crops as $c): ?>
                            <option value="<?= e($c) ?>"><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold" for="mandi"><?= e(__('mp_mandi')) ?></label>
                    <select class="form-select" id="mandi" disabled>
                        <option value=""><?= e(__('mp_choose_crop_first')) ?></option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary-custom w-100 fw-bold" id="go" disabled><?= e(__('mp_show_prices')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <div id="status" class="alert d-none" role="alert"></div>

    <div id="result" class="d-none">
        <div id="warnings"></div>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small"><?= e(__('mp_last_price')) ?></div>
                        <div class="display-5 fw-bold" id="price" style="color: var(--primary-green);"></div>
                        <div class="text-muted" id="priceMeta"></div>
                        <div class="mt-3"><span class="badge bg-secondary" id="trend"></span></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1"><?= e(__('mp_projection')) ?></h5>
                        <p class="text-muted small mb-3" id="projNote"></p>
                        <table class="table table-sm mb-0">
                            <thead><tr><th><?= e(__('mp_col_date')) ?></th><th class="text-end"><?= e(__('mp_col_projected')) ?></th></tr></thead>
                            <tbody id="rows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <p class="text-muted small mt-3 mb-0" id="confidence"></p>
    </div>
</div>
<script>
(function () {
    var BASE = <?= json_encode(BASE_URL . '/index.php?url=mandi/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var T = <?= json_encode([
        'choose_crop_first' => __('mp_choose_crop_first'),
        'choose_mandi'      => __('mp_choose_mandi'),
        'per_quintal'       => __('mp_per_quintal'),
        'price_meta'        => __('mp_price_meta'),
        'trend'             => __('mp_trend'),
        'trend_na'          => __('mp_trend_na'),
        'stale_hidden'      => __('mp_stale_hidden'),
        'no_projection'     => __('mp_no_projection'),
        'loading_mandis'    => __('mp_loading_mandis'),
        'loading_prices'    => __('mp_loading_prices'),
        'err_read'          => __('mp_err_read'),
        'err_mandis'        => __('mp_err_mandis'),
        'err_forecast'      => __('mp_err_forecast'),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
    var token = 0;
    // Fill {name} placeholders in a translated string.
    function fmt(text, vars) {
        return String(text).replace(/\{(\w+)\}/g, function (m, k) { return vars[k] === undefined ? m : vars[k]; });
    }
    function el(id) { return document.getElementById(id); }
    function showStatus(msg, type) {
        var s = el('status');
        s.className = 'alert alert-' + type;
        s.textContent = msg;
    }
    function hideStatus() { el('status').className = 'alert d-none'; }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: T.err_read }; });
    }
    function money(n) {
        return '\u20B9' + Number(n).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }
    function addWarning(box, text) {
        var d = document.createElement('div');
        d.className = 'alert alert-warning';
        d.textContent = text;
        box.appendChild(d);
    }
    function resetMandi() {
        var m = el('mandi');
        m.innerHTML = '';
        m.appendChild(new Option(T.choose_crop_first, ''));
        m.disabled = true;
        el('go').disabled = true;
        el('result').classList.add('d-none');
    }
    function todayIso() {
        var t = new Date();
        var m = t.getMonth() + 1, day = t.getDate();
        return t.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
    }
    // Keep only projected rows dated today or later (ISO dates compare correctly as strings).
    function futureRows(rows, today) {
        return (rows || []).filter(function (f) { return f && f.date && f.date >= today; });
    }
    // One notice only: prefer the service's age warning, then its data note, else a generic stale message.
    function pickNotice(d, hiddenCount) {
        if (d.data_age_warning) { return d.data_age_warning; }
        if (d.data_note) { return d.data_note; }
        if (hiddenCount > 0) { return fmt(T.stale_hidden, { date: d.latest_date }); }
        return '';
    }
    function render(d) {
        var all = d.forecast || [];
        var rows = futureRows(all, todayIso());
        var hidden = all.length - rows.length;
        var w = el('warnings');
        w.innerHTML = '';
        var notice = pickNotice(d, hidden);
        if (notice) { addWarning(w, notice); }
        el('price').textContent = money(d.latest_price) + ' ' + T.per_quintal;
        el('priceMeta').textContent = fmt(T.price_meta, { crop: d.crop, mandi: d.mandi, date: d.latest_date });
        el('trend').textContent = fmt(T.trend, { trend: d.trend || T.trend_na });
        el('projNote').textContent = '';
        var tb = el('rows');
        tb.innerHTML = '';
        if (rows.length === 0) {
            var er = document.createElement('tr');
            var ec = document.createElement('td');
            ec.colSpan = 2;
            ec.className = 'text-muted';
            ec.textContent = T.no_projection;
            er.appendChild(ec);
            tb.appendChild(er);
        }
        rows.forEach(function (f) {
            var tr = document.createElement('tr');
            var a = document.createElement('td');
            a.textContent = f.date;
            var b = document.createElement('td');
            b.className = 'text-end';
            b.textContent = money(f.price);
            tr.appendChild(a);
            tr.appendChild(b);
            tb.appendChild(tr);
        });
        el('confidence').textContent = d.confidence_note || '';
        el('result').classList.remove('d-none');
    }
    el('crop').addEventListener('change', function () {
        var crop = this.value;
        var mine = ++token;
        resetMandi();
        if (!crop) { hideStatus(); return; }
        showStatus(T.loading_mandis, 'info');
        getJson(BASE + 'mandis&crop=' + encodeURIComponent(crop)).then(function (j) {
            if (mine !== token) { return; }
            if (!j.ok) { showStatus(j.error || T.err_mandis, 'danger'); return; }
            hideStatus();
            var m = el('mandi');
            m.innerHTML = '';
            m.appendChild(new Option(T.choose_mandi, ''));
            j.mandis.forEach(function (n) { m.appendChild(new Option(n, n)); });
            m.disabled = false;
        });
    });
    el('mandi').addEventListener('change', function () {
        el('go').disabled = !this.value;
    });
    el('go').addEventListener('click', function () {
        showStatus(T.loading_prices, 'info');
        el('result').classList.add('d-none');
        var url = BASE + 'forecast&crop=' + encodeURIComponent(el('crop').value) +
                  '&mandi=' + encodeURIComponent(el('mandi').value);
        getJson(url).then(function (j) {
            if (!j.ok) { showStatus(j.error || T.err_forecast, 'warning'); return; }
            hideStatus();
            render(j.data);
        });
    });
})();
</script>
