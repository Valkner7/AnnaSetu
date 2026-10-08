<div class="container py-5 animate-fade-in">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-dark"><i class="fa-solid fa-cart-flatbed"></i> Procurement Cart</h2>
                <p class="text-muted">Review your selected crop listings before placing bulk bids.</p>
            </div>
            <a href="<?= BASE_URL ?>/index.php?url=buyer/marketplace" class="btn btn-outline-secondary">Back to Market</a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border-0" style="border-top: 4px solid var(--primary-green);">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Crop Name</th>
                                    <th>Quantity Bid</th>
                                    <th>Offered Price</th>
                                    <th>Total Est. Value</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($cart)): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">Your cart is empty. Explore the marketplace to add listings.</td></tr>
                                <?php else: ?>
                                    <?php 
                                        $grandTotal = 0;
                                        foreach($cart as $id => $item): 
                                        $total = $item['quantity'] * 10 * $item['price']; // tons * 10 = quintals. quintals * price/quintal
                                        $grandTotal += $total;
                                    ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($item['name']) ?></td>
                                            <td><?= $item['quantity'] ?> Tons</td>
                                            <td class="text-success">₹<?= $item['price'] ?> / qtl</td>
                                            <td class="fw-bold">₹<?= number_format($total, 2) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger remove-btn" data-id="<?= $id ?>"><i class="fa-solid fa-trash"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 bg-light">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Total Bids:</span>
                        <span class="fw-bold"><?= count($cart) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Estimated Total Value:</span>
                        <h4 class="text-dark fw-bold mb-0">₹<?= number_format($grandTotal ?? 0, 2) ?></h4>
                    </div>
                    <hr>
                    <p class="small text-muted"><i class="fa-solid fa-circle-info"></i> Clicking checkout will send a notification to the respective farmers. The order is finalized only when the farmer accepts.</p>
                    
                    <button id="btnCheckout" class="btn btn-success w-100 py-3 fw-bold" <?= empty($cart) ? 'disabled' : '' ?>>
                        Submit Procurement Bids
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const payload = { listing_id: this.dataset.id };
        try {
            const res = await fetch('<?= BASE_URL ?>/index.php?url=buyer/removeFromCart', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if(data.success) {
                location.reload();
            }
        } catch(e) {
            showToast('error', 'Network error.');
        }
    });
});

document.getElementById('btnCheckout').addEventListener('click', async function() {
    this.disabled = true;
    this.innerHTML = 'Processing...';

    try {
        const res = await fetch('<?= BASE_URL ?>/index.php?url=buyer/checkout', {
            method: 'POST'
        });
        const data = await res.json();
        if(data.success) {
            showToast('success', data.message);
            setTimeout(() => window.location.href = '<?= BASE_URL ?>/index.php?url=buyer/myOrders', 1500);
        } else {
            showToast('error', data.message);
            this.disabled = false;
            this.innerHTML = 'Submit Procurement Bids';
        }
    } catch(e) {
        showToast('error', 'Network error.');
        this.disabled = false;
        this.innerHTML = 'Submit Procurement Bids';
    }
});
</script>
