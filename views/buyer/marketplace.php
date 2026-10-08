<div class="container py-5 animate-fade-in">
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold" style="color: var(--primary-green);">
                    <i class="fa-solid fa-cart-flatbed"></i> Procurement Market
                </h2>
                <p class="text-muted">Source premium crops directly from farmers.</p>
            </div>
            <div>
                <?php $cartCount = isset($_SESSION['buyer_cart']) ? count($_SESSION['buyer_cart']) : 0; ?>
                <a href="<?= BASE_URL ?>/index.php?url=buyer/viewCart" class="btn btn-outline-success position-relative me-2">
                    <i class="fa-solid fa-cart-shopping"></i> View Cart
                    <?php if($cartCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $cartCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?= BASE_URL ?>/index.php?url=dashboard" class="btn btn-light border"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if(empty($listings)): ?>
            <div class="col-12"><div class="alert alert-info">No active listings available at the moment.</div></div>
        <?php else: ?>
            <?php foreach($listings as $list): ?>
                <?php 
                    $langKey = \App\Helpers\Translator::getCurrentLang() === 'pa' ? 'name_pa' : (\App\Helpers\Translator::getCurrentLang() === 'hi' ? 'name_hi' : 'crop_name');
                    $qtyTons = number_format($list['quantity_kg'] / 1000, 2);
                    $priceQtl = number_format($list['expected_price_per_kg'] * 100, 2);
                ?>
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm border-0" style="border-top: 4px solid var(--primary-green);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <h5 class="fw-bold mb-1"><?= $list[$langKey] ?? $list['crop_name'] ?></h5>
                                <span class="badge bg-success">Verified</span>
                            </div>
                            <p class="text-muted small mb-3"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($list['location_address']) ?></p>
                            
                            <div class="bg-light p-3 rounded mb-3">
                                <div class="row text-center">
                                    <div class="col-6 border-end">
                                        <small class="text-muted d-block">Quantity</small>
                                        <strong class="text-dark fs-5"><?= $qtyTons ?> Tons</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Expected Price</small>
                                        <strong class="text-success fs-5">₹<?= $priceQtl ?>/qtl</strong>
                                    </div>
                                </div>
                            </div>
                            
                            <p class="mb-1 small text-muted"><i class="fa-solid fa-calendar"></i> Harvest: <?= date('d M Y', strtotime($list['harvest_date'])) ?></p>
                            <p class="mb-3 small text-muted"><i class="fa-solid fa-user"></i> Listed by: <?= htmlspecialchars($list['first_name']) ?></p>
                            
                            <button class="btn btn-secondary-custom w-100 place-bid-btn" 
                                data-id="<?= $list['id'] ?>" 
                                data-name="<?= $list[$langKey] ?? $list['crop_name'] ?>"
                                data-qty="<?= $qtyTons ?>"
                                data-price="<?= $list['expected_price_per_kg'] * 100 ?>">
                                Place Bid
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bid Modal -->
<div class="modal fade" id="bidModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-light border-0">
        <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar" style="color: var(--primary-orange);"></i> Place Bid on <span id="modalCropName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="bidForm">
            <input type="hidden" id="bid_listing_id">
            
            <div class="mb-3">
                <label class="form-label text-muted small mb-1">Required Quantity (Tons)</label>
                <input type="number" step="0.1" class="form-control form-control-lg" id="bid_quantity" required>
                <small class="text-success">Available: <span id="modalMaxQty"></span> Tons</small>
            </div>
            
            <div class="mb-4">
                <label class="form-label text-muted small mb-1">Your Offer (₹ per Quintal)</label>
                <input type="number" step="1" class="form-control form-control-lg" id="bid_price" required>
                <small class="text-muted">Farmer's Expected: ₹<span id="modalExpectedPrice"></span></small>
            </div>
            
            <button type="submit" class="btn btn-primary-custom w-100 py-2 fs-5" id="btnSubmitBid">Submit Offer</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
let bidModal;

document.addEventListener('DOMContentLoaded', () => {
    if(typeof bootstrap !== 'undefined') {
        bidModal = new bootstrap.Modal(document.getElementById('bidModal'));
    }
});

document.querySelectorAll('.place-bid-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('bid_listing_id').value = this.dataset.id;
        document.getElementById('modalCropName').innerText = this.dataset.name;
        document.getElementById('modalMaxQty').innerText = this.dataset.qty;
        document.getElementById('bid_quantity').max = this.dataset.qty;
        document.getElementById('bid_quantity').value = this.dataset.qty;
        document.getElementById('modalExpectedPrice').innerText = this.dataset.price;
        document.getElementById('bid_price').value = this.dataset.price;
        
        if(bidModal) bidModal.show();
    });
});

document.getElementById('bidForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitBid');
    btn.disabled = true;
    btn.innerHTML = 'Submitting...';
    
    const payload = {
        listing_id: document.getElementById('bid_listing_id').value,
        quantity: document.getElementById('bid_quantity').value,
        price: document.getElementById('bid_price').value
    };

    try {
        const res = await fetch('<?= BASE_URL ?>/index.php?url=buyer/addToCart', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success) {
            if(bidModal) bidModal.hide();
            showToast('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', data.message || 'Error adding to cart');
        }
    } catch(err) {
        showToast('error', 'Error connecting to server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Submit Offer';
    }
});
</script>
