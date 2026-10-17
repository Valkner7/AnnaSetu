<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold" style="color: var(--primary-orange);">
                <i class="fa-solid fa-store"></i> Sell Produce (फसल बेचें)
            </h2>
            <p class="text-muted">List your harvested or upcoming crops on the Smart Marketplace.</p>
        </div>
    </div>

    <div class="row">
        <!-- Input Form -->
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm border-0 h-100" style="border-top: 3px solid var(--primary-green);">
                <div class="card-body p-4">
                    <form id="listProduceForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Crop to Sell (बेचने के लिए फसल चुनें)</label>
                            <select class="form-select" id="farmer_crop_id" required>
                                <option value="">-- Choose from your crops --</option>
                                <?php foreach($myCrops as $crop): ?>
                                    <option value="<?= e($crop['id']) ?>"><?= e($crop['crop_name']) ?> (<?= e($crop['crop_name_hi']) ?>) - Land #<?= e($crop['land_id']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Quantity in Kg (मात्रा किलो में)</label>
                            <input type="number" class="form-control" id="quantity_kg" placeholder="e.g. 5000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Expected Price per Kg in ₹ (अपेक्षित मूल्य)</label>
                            <input type="number" step="0.01" class="form-control" id="expected_price" placeholder="e.g. 25" required>
                        </div>

                        <!-- Mandi price hint (F3). Optional: shown only for crops the price service covers. -->
                        <div class="mb-3 d-none" id="priceHintBox">
                            <label class="form-label fw-bold" for="hintMandi">Compare with a mandi price (optional)</label>
                            <select class="form-select mb-2" id="hintMandi" disabled>
                                <option value="">-- Choose mandi --</option>
                            </select>
                            <div class="alert alert-secondary py-2 mb-0 d-none" id="priceHint" role="status" aria-live="polite">
                                <div id="priceHintRef"></div>
                                <div class="small text-muted" id="priceHintAge"></div>
                                <div class="fw-bold mt-1" id="priceHintVerdict"></div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Upload Photos (तस्वीरें अपलोड करें)</label>
                            <input type="file" class="form-control" accept="image/*" multiple>
                            <small class="text-muted">Clear photos help the AI grade your crop better.</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fa-solid fa-robot"></i> **Note:** Once listed, SmartHarvest AI will analyze your crop images and history to assign a Quality Grade (A/B/C) to attract bulk buyers.
                        </div>

                        <button type="submit" class="btn btn-secondary-custom w-100 py-2 fw-bold" id="btnList">
                            <i class="fa-solid fa-bullhorn"></i> Publish to Marketplace (बाज़ार में प्रकाशित करें)
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Info Graphic -->
        <div class="col-lg-7">
            <div class="bg-light p-5 rounded text-center h-100 border d-flex flex-column justify-content-center align-items-center">
                <i class="fa-solid fa-handshake fa-5x mb-4" style="color: var(--primary-green);"></i>
                <h3 class="fw-bold">Reach Direct Buyers</h3>
                <p class="text-muted fs-5">By listing here, your produce is automatically matched with bulk buyers nearby. Small quantities can be <strong>Pooled</strong> with neighboring farmers to fulfill large 10-Ton orders!</p>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/price-hint.js"></script>
<script>
// Mandi price hint (F3). Everything shown is set with textContent, never innerHTML.
(function () {
    var BASE = <?= json_encode(BASE_URL . '/index.php?url=mandi/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var cropSel = document.getElementById('farmer_crop_id');
    var priceIn = document.getElementById('expected_price');
    var box = document.getElementById('priceHintBox');
    var mandiSel = document.getElementById('hintMandi');
    var panel = document.getElementById('priceHint');
    var info = null;      // latest mandi data for the chosen crop + mandi
    var token = 0;        // ignores answers that arrive after the selection changed

    function getJson(url) {
        return fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { return { ok: false, error: 'Could not read the response.' }; });
    }
    function resetMandis() {
        mandiSel.innerHTML = '';
        mandiSel.appendChild(new Option('-- Choose mandi --', ''));
        mandiSel.disabled = true;
    }
    function clearPanel() {
        info = null;
        panel.className = 'alert alert-secondary py-2 mb-0 d-none';
    }
    function say(text, level) {
        document.getElementById('priceHintRef').textContent = text;
        document.getElementById('priceHintAge').textContent = '';
        document.getElementById('priceHintVerdict').textContent = '';
        panel.className = 'alert alert-' + (level || 'secondary') + ' py-2 mb-0';
    }
    function render() {
        if (!info) { return; }
        var d = PriceHint.describe(info, PriceHint.evaluate(priceIn.value, info.latestPrice));
        document.getElementById('priceHintRef').textContent = d.reference;
        document.getElementById('priceHintAge').textContent = d.age;
        document.getElementById('priceHintVerdict').textContent = d.verdict;
        panel.className = 'alert alert-' + d.level + ' py-2 mb-0';
    }

    cropSel.addEventListener('change', function () {
        var mine = ++token;
        var label = cropSel.options[cropSel.selectedIndex] ? cropSel.options[cropSel.selectedIndex].text : '';
        var crop = label.split(' (')[0].trim();    // option text starts with the English crop name
        clearPanel();
        resetMandis();
        box.classList.add('d-none');
        if (!cropSel.value || !crop) { return; }
        getJson(BASE + 'mandis&crop=' + encodeURIComponent(crop)).then(function (j) {
            if (mine !== token) { return; }
            // Crops the price service does not cover (or any error) simply get no hint.
            if (!j.ok || !j.mandis || !j.mandis.length) { return; }
            j.mandis.forEach(function (n) { mandiSel.appendChild(new Option(n, n)); });
            mandiSel.disabled = false;
            box.classList.remove('d-none');
        });
    });

    mandiSel.addEventListener('change', function () {
        var mine = ++token;
        var label = cropSel.options[cropSel.selectedIndex].text;
        var crop = label.split(' (')[0].trim();
        var mandi = mandiSel.value;
        clearPanel();
        if (!mandi) { return; }
        say('Loading the mandi price... the price service can take up to a minute to wake up.', 'secondary');
        getJson(BASE + 'forecast&crop=' + encodeURIComponent(crop) + '&mandi=' + encodeURIComponent(mandi)).then(function (j) {
            if (mine !== token) { return; }
            if (!j.ok || !j.data || !(Number(j.data.latest_price) > 0)) {
                say('No mandi price is available for this crop and mandi right now. You can still list your produce.', 'secondary');
                return;
            }
            var days = (typeof j.data.data_age_days === 'number') ? j.data.data_age_days : PriceHint.ageDays(j.data.latest_date);
            info = {
                mandi: j.data.mandi || mandi,
                latestPrice: Number(j.data.latest_price),
                latestDate: j.data.latest_date,
                ageDays: days,
                servedStale: !!j.data.served_stale
            };
            render();
        });
    });

    priceIn.addEventListener('input', render);
})();
</script>
<script>
document.getElementById('listProduceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnList');
    btn.disabled = true;
    btn.innerHTML = 'Publishing...';
    
    const payload = {
        farmer_crop_id: document.getElementById('farmer_crop_id').value,
        quantity_kg: document.getElementById('quantity_kg').value,
        expected_price: document.getElementById('expected_price').value
    };

    try {
        const res = await fetch('<?= BASE_URL ?>/index.php?url=farmer/saveListing', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success) {
            showToast('success', 'Produce listed successfully on the marketplace!');
            setTimeout(() => window.location.href = '<?= BASE_URL ?>/index.php?url=dashboard', 1500);
        } else {
            showToast('error', data.message || 'Validation error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-bullhorn"></i> Publish to Marketplace';
        }
    } catch(err) {
        showToast('error', 'Error connecting to server.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bullhorn"></i> Publish to Marketplace';
    }
});
</script>
