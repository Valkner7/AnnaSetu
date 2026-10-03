<div class="container py-5 animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold" style="color: var(--primary-green);"><i class="fa-solid fa-list-check"></i> My Procurement Bids</h2>
            <p class="text-muted">Track the status of your offers to farmers.</p>
        </div>
        <a href="/smartharvest/public/index.php?url=buyer/marketplace" class="btn btn-primary-custom"><i class="fa-solid fa-store"></i> Back to Market</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Crop</th>
                            <th>Farmer Contact</th>
                            <th>Quantity (Tons)</th>
                            <th>Offered Price</th>
                            <th>Date Placed</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($orders)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">You haven't placed any bids yet.</td></tr>
                        <?php else: ?>
                            <?php foreach($orders as $o): ?>
                                <tr>
                                    <td class="fw-bold">
                                        <?= htmlspecialchars($o['crop_name']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($o['crop_name_hi']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($o['first_name']) ?><br>
                                        <span class="text-muted small"><i class="fa-solid fa-phone"></i> <?= $o['farmer_phone'] ?></span>
                                    </td>
                                    <td>
                                        <?= $o['quantity_tons'] ?>
                                        <small class="d-block text-muted">of <?= number_format($o['quantity_kg'] / 1000, 2) ?> Available</small>
                                    </td>
                                    <td class="text-success fw-bold">₹<?= $o['offered_price_per_quintal'] ?>/qtl</td>
                                    <td><?= date('d M Y, h:i A', strtotime($o['created_at'])) ?></td>
                                    <td>
                                        <?php 
                                            $bg = 'bg-secondary';
                                            if($o['status'] == 'pending') $bg = 'bg-warning text-dark';
                                            if($o['status'] == 'accepted') $bg = 'bg-success';
                                            if($o['status'] == 'rejected') $bg = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $bg ?>"><?= ucfirst($o['status']) ?></span>
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
