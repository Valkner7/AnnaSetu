<div class="container py-5">
    <div class="mb-4">
        <h2 class="fw-bold" style="color: var(--primary-green);">
            <i class="fa-solid fa-chart-line"></i> Market Prices (&#2350;&#2306;&#2337;&#2368; &#2349;&#2366;&#2357;)
        </h2>
        <p class="text-muted">Last reported mandi prices and a short projection for Punjab mandis.</p>
    </div>

    <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--primary-orange);">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="crop">Crop (&#2347;&#2360;&#2354;)</label>
                    <select class="form-select" id="crop">
                        <option value="">-- Choose crop --</option>
                        <?php foreach ($crops as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold" for="mandi">Mandi (&#2350;&#2306;&#2337;&#2368;)</label>
                    <select class="form-select" id="mandi" disabled>
                        <option value="">-- Choose crop first --</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn btn-primary-custom w-100 fw-bold" id="go" disabled>Show prices</button>
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
                        <div class="text-muted small">Last reported price</div>
                        <div class="display-5 fw-bold" id="price" style="color: var(--primary-green);"></div>
                        <div class="text-muted" id="priceMeta"></div>
                        <div class="mt-3"><span class="badge bg-secondary" id="trend"></span></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-1">Projection</h5>
                        <p class="text-muted small mb-3" id="projNote"></p>
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Date</th><th class="text-end">Projected price (&#8377;/qtl)</th></tr></thead>
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
    var BASE = '/smartharvest/public/index.php?url=mandi/';
    var token = 0;
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
            .catch(function () { return { ok: false, error: 'Could not read the response. Please try again.' }; });
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
        m.appendChild(new Option('-- Choose crop first --', ''));
        m.disabled = true;
        el('go').disabled = true;
        el('result').classList.add('d-none');
    }
    function render(d) {
        var w = el('warnings');
        w.innerHTML = '';
        if (d.data_age_warning) { addWarning(w, d.data_age_warning); }
        if (d.data_note) { addWarning(w, d.data_note); }
        el('price').textContent = money(d.latest_price) + ' / quintal';
        el('priceMeta').textContent = d.crop + ' at ' + d.mandi + ' - reported on ' + d.latest_date;
        el('trend').textContent = 'Trend: ' + (d.trend || 'n/a');
        el('projNote').textContent = 'Projected forward from ' + d.latest_date + ' (not from today).';
        var tb = el('rows');
        tb.innerHTML = '';
        (d.forecast || []).forEach(function (f) {
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
        showStatus('Loading mandis... the price service can take up to a minute to wake up the first time.', 'info');
        getJson(BASE + 'mandis&crop=' + encodeURIComponent(crop)).then(function (j) {
            if (mine !== token) { return; }
            if (!j.ok) { showStatus(j.error || 'Could not load mandis.', 'danger'); return; }
            hideStatus();
            var m = el('mandi');
            m.innerHTML = '';
            m.appendChild(new Option('-- Choose mandi --', ''));
            j.mandis.forEach(function (n) { m.appendChild(new Option(n, n)); });
            m.disabled = false;
        });
    });
    el('mandi').addEventListener('change', function () {
        el('go').disabled = !this.value;
    });
    el('go').addEventListener('click', function () {
        showStatus('Loading prices...', 'info');
        el('result').classList.add('d-none');
        var url = BASE + 'forecast&crop=' + encodeURIComponent(el('crop').value) +
                  '&mandi=' + encodeURIComponent(el('mandi').value);
        getJson(url).then(function (j) {
            if (!j.ok) { showStatus(j.error || 'No forecast available for this selection.', 'warning'); return; }
            hideStatus();
            render(j.data);
        });
    });
})();
</script>
