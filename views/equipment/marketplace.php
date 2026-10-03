<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold" style="color: var(--secondary-orange);"><i class="fa-solid fa-tractor"></i> Farm Services Marketplace</h2>
            <p class="text-muted">Rent tractors, drones, and harvesters directly from local providers.</p>
        </div>
    </div>

    <div class="row">
        <!-- Marketplace Listings -->
        <div class="col-lg-7">
            <h4 class="fw-bold mb-3">Available Machinery Nearby</h4>
            <div class="row g-4">
                <?php if(empty($equipments)): ?>
                    <div class="col-12"><div class="alert alert-info">No equipment listed nearby at the moment.</div></div>
                <?php else: ?>
                    <?php foreach($equipments as $eq): ?>
                        <div class="col-md-6">
                            <div class="card shadow-sm border-0 h-100" style="border-top: 3px solid var(--primary-green); overflow: hidden;">
                                <?php if(!empty($eq['image_path'])): ?>
                                    <img src="/smartharvest/public/<?= htmlspecialchars($eq['image_path']) ?>" class="card-img-top" alt="Equipment" style="height: 140px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($eq['name']) ?></h5>
                                    <p class="text-muted small mb-2"><i class="fa-solid fa-tag"></i> <?= $eq['type'] ?></p>
                                    
                                    <div class="mb-3 bg-light p-2 rounded">
                                        <p class="mb-0 small"><i class="fa-solid fa-building"></i> <?= htmlspecialchars($eq['business_name']) ?></p>
                                        <p class="mb-0 small"><i class="fa-solid fa-location-dot"></i> Base: <?= htmlspecialchars($eq['base_location']) ?></p>
                                        <p class="mb-0 small"><i class="fa-solid fa-map-pin"></i> Covers <?= $eq['service_radius_km'] ?> km</p>
                                    </div>
                                    
                                    <h4 class="fw-bold text-success mb-3">₹<?= $eq['rate_per_hour'] ?> <small class="text-muted fs-6">/ hour</small></h4>
                                    
                                    <button class="btn btn-outline-success w-100 book-btn" data-id="<?= $eq['id'] ?>" data-name="<?= htmlspecialchars($eq['name']) ?>" data-rate="<?= $eq['rate_per_hour'] ?>">
                                        Book Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Booking Form / History -->
        <div class="col-lg-5">
            <!-- Hidden by default, shown when user clicks Book Now -->
            <div class="card shadow border-0 mb-5 d-none" id="bookingCard" style="border-top: 4px solid var(--secondary-orange);">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-regular fa-calendar-check"></i> Book <span id="displayEqName"></span>
                </div>
                <div class="card-body p-4">
                    <form id="bookingForm">
                        <input type="hidden" id="book_eq_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Your Land</label>
                            <select class="form-select" id="book_land_id" required>
                                <option value="">-- Choose Land --</option>
                                <?php foreach($lands as $land): ?>
                                    <option value="<?= $land['id'] ?>">Land #<?= $land['id'] ?> - <?= $land['area'] ?> Acres</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Date Needed</label>
                            <input type="date" class="form-control" id="book_date" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Estimated Duration (Hours)</label>
                            <input type="number" class="form-control" id="book_duration" min="1" placeholder="e.g. 5" required>
                            <small class="text-muted d-block mt-1">Rate: ₹<span id="displayRate">0</span>/hr. Total Estimated Cost: <strong class="text-success">₹<span id="displayTotal">0</span></strong></small>
                        </div>
                        
                        <button type="submit" class="btn btn-secondary-custom w-100" id="btnSubmitBooking">Confirm Booking Request</button>
                        <button type="button" class="btn btn-light w-100 mt-2" onclick="document.getElementById('bookingCard').classList.add('d-none')">Cancel</button>
                    </form>
                </div>
            </div>

            <!-- Booking History -->
            <h4 class="fw-bold mb-3">My Booking Requests</h4>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Machine</th>
                                    <th>Provider</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($myBookings)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">You have no booking history.</td></tr>
                                <?php else: ?>
                                    <?php foreach($myBookings as $b): ?>
                                        <tr>
                                            <td class="fw-bold">
                                                <?= htmlspecialchars($b['equipment_name']) ?><br>
                                                <small class="text-muted">₹<?= $b['total_cost'] ?> (<?= $b['duration_hours'] ?>h)</small>
                                            </td>
                                            <td><?= htmlspecialchars($b['business_name']) ?></td>
                                            <td><?= date('d M', strtotime($b['booking_date'])) ?></td>
                                            <td>
                                                <?php 
                                                    $bg = 'bg-secondary';
                                                    if($b['status'] == 'pending') $bg = 'bg-warning text-dark';
                                                    if($b['status'] == 'confirmed') $bg = 'bg-info text-dark';
                                                    if($b['status'] == 'completed') $bg = 'bg-success';
                                                    if($b['status'] == 'cancelled') $bg = 'bg-danger';
                                                ?>
                                                <span class="badge <?= $bg ?>"><?= ucfirst($b['status']) ?></span>
                                            </td>
                                            <td>
                                                <?php if($b['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-outline-danger cancel-booking-btn" data-id="<?= $b['id'] ?>">Cancel</button>
                                                <?php else: ?>
                                                    -
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
// Booking Form Logic
let currentRate = 0;

document.querySelectorAll('.book-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        // Populate and show the booking form
        document.getElementById('book_eq_id').value = this.dataset.id;
        document.getElementById('displayEqName').innerText = this.dataset.name;
        currentRate = parseFloat(this.dataset.rate);
        document.getElementById('displayRate').innerText = currentRate;
        
        // Reset form values
        document.getElementById('book_duration').value = '';
        document.getElementById('displayTotal').innerText = '0';
        
        // Scroll to form
        const card = document.getElementById('bookingCard');
        card.classList.remove('d-none');
        card.scrollIntoView({ behavior: 'smooth' });
    });
});

document.getElementById('book_duration').addEventListener('input', function() {
    const hours = parseFloat(this.value) || 0;
    document.getElementById('displayTotal').innerText = (hours * currentRate).toFixed(2);
});

document.getElementById('bookingForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmitBooking');
    btn.disabled = true;
    btn.innerHTML = 'Submitting...';
    
    const payload = {
        equipment_id: document.getElementById('book_eq_id').value,
        land_id: document.getElementById('book_land_id').value,
        booking_date: document.getElementById('book_date').value,
        duration_hours: document.getElementById('book_duration').value
    };

    try {
        const res = await fetch('/smartharvest/public/index.php?url=equipment/bookEquipment', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if(data.success) {
            showToast('success', 'Booking submitted successfully!');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', data.message);
            btn.disabled = false;
            btn.innerHTML = 'Confirm Booking Request';
        }
    } catch(err) {
        showToast('error', 'Error connecting to server.');
        btn.disabled = false;
        btn.innerHTML = 'Confirm Booking Request';
    }
});

document.querySelectorAll('.cancel-booking-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if(!confirm('Are you sure you want to cancel this booking request?')) return;
        
        this.disabled = true;
        const payload = { booking_id: this.dataset.id };

        try {
            const res = await fetch('/smartharvest/public/index.php?url=equipment/cancelBooking', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if(data.success) {
                showToast('success', 'Booking cancelled successfully.');
                setTimeout(() => location.reload(), 1500);
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
