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
                                    <option value="<?= $crop['id'] ?>"><?= $crop['crop_name'] ?> (<?= $crop['crop_name_hi'] ?>) - Land #<?= $crop['land_id'] ?></option>
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
