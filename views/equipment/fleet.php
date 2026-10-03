<div class="container py-5 animate-fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold" style="color: var(--secondary-orange);"><i class="fa-solid fa-truck-pickup"></i> Fleet Manager</h2>
            <p class="text-muted">Manage your equipment and incoming farmer requests.</p>
        </div>
    </div>

    <div class="row">
        <!-- Add Equipment Form -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm border-0" style="border-top: 3px solid var(--secondary-orange);">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-plus"></i> Add New Machinery
                </div>
                <div class="card-body">
                    <form id="addEqForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Equipment Name / Model</label>
                            <input type="text" class="form-control" name="name" id="eq_name" placeholder="e.g. Mahindra 575 DI" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" id="eq_type" required>
                                <option value="Tractor">Tractor</option>
                                <option value="Harvester">Harvester</option>
                                <option value="Drone">Agricultural Drone</option>
                                <option value="Implement">Implement (Plough, Cultivator)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rental Rate (₹ per hour)</label>
                            <input type="number" step="0.01" class="form-control" name="rate_per_hour" id="rate_per_hour" placeholder="e.g. 500" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Equipment Photo</label>
                            <input type="file" class="form-control" name="image" accept="image/*" required>
                            <small class="text-muted">A clear photo helps farmers trust your machinery.</small>
                        </div>
                        <button type="submit" class="btn btn-secondary-custom w-100" id="btnAddEq">Add to Fleet</button>
                    </form>
                </div>
            </div>

            <!-- My Fleet List -->
            <h5 class="fw-bold mb-3 mt-4">My Available Fleet</h5>
            <div class="row g-3">
                <?php if(empty($equipments)): ?>
                    <div class="col-12"><p class="text-muted">No equipment added yet.</p></div>
                <?php else: ?>
                    <?php foreach($equipments as $eq): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--secondary-orange);">
                                <?php if(!empty($eq['image_path'])): ?>
                                    <img src="/smartharvest/public/<?= htmlspecialchars($eq['image_path']) ?>" class="card-img-top" alt="Equipment" style="height: 120px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($eq['name']) ?></h6>
                                            <small class="text-muted"><?= $eq['type'] ?> • ₹<?= $eq['rate_per_hour'] ?>/hr</small>
                                        </div>
                                        <span class="badge <?= $eq['status'] == 'active' ? 'bg-success' : 'bg-danger' ?>"><?= ucfirst($eq['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Booking Requests Table -->
        <div class="col-lg-8">
            <h4 class="fw-bold mb-3">Incoming Booking Requests</h4>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Equipment</th>
                                    <th>Farmer Contact</th>
                                    <th>Location</th>
                                    <th>Booking Date</th>
                                    <th>Duration & Cost</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($bookings)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No bookings received yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach($bookings as $b): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($b['equipment_name']) ?></td>
                                            <td><?= htmlspecialchars($b['farmer_phone']) ?></td>
                                            <td><?= htmlspecialchars($b['location_address']) ?><br><small><?= $b['area'] ?> Acres</small></td>
                                            <td><?= date('d M Y', strtotime($b['booking_date'])) ?></td>
                                            <td><?= $b['duration_hours'] ?> Hrs<br><strong class="text-success">₹<?= $b['total_cost'] ?></strong></td>
                                            <td>
                                                <?php 
                                                    $bg = 'bg-secondary';
                                                    if($b['status'] == 'pending') $bg = 'bg-warning text-dark';
                                                    if($b['status'] == 'confirmed') $bg = 'bg-info text-dark';
                                                    if($b['status'] == 'completed') $bg = 'bg-success';
                                                ?>
                                                <span class="badge <?= $bg ?>"><?= ucfirst($b['status']) ?></span>
                                            </td>
                                            <td>
                                                <?php if($b['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success update-status-btn" data-id="<?= $b['id'] ?>" data-status="confirmed">Confirm</button>
                                                    <button class="btn btn-sm btn-danger update-status-btn" data-id="<?= $b['id'] ?>" data-status="cancelled">Reject</button>
                                                <?php elseif($b['status'] == 'confirmed'): ?>
                                                    <button class="btn btn-sm btn-primary update-status-btn" data-id="<?= $b['id'] ?>" data-status="completed">Mark Completed</button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
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
    </div>
</div>

<script>
document.getElementById('addEqForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnAddEq');
    btn.disabled = true;
    btn.innerHTML = 'Adding...';
    
    const formData = new FormData(this);

    try {
        const res = await fetch('/smartharvest/public/index.php?url=equipment/addEquipment', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if(data.success) {
            showToast('success', 'Equipment added successfully!');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('error', data.message);
            btn.disabled = false;
            btn.innerHTML = 'Add to Fleet';
        }
    } catch(err) {
        showToast('error', 'Error connecting to server.');
        btn.disabled = false;
        btn.innerHTML = 'Add to Fleet';
    }
});

document.querySelectorAll('.update-status-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if(!confirm(`Are you sure you want to change the status to ${this.dataset.status}?`)) return;
        
        this.disabled = true;
        const payload = {
            booking_id: this.dataset.id,
            status: this.dataset.status
        };

        try {
            const res = await fetch('/smartharvest/public/index.php?url=equipment/updateBookingStatus', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if(data.success) {
                showToast('success', 'Status updated successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message);
                this.disabled = false;
            }
        } catch(err) {
            showToast('error', 'Error connecting to server.');
            this.disabled = false;
        }
    });
});
</script>
