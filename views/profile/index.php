<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold" style="color: var(--primary-green);"><i class="fa-solid fa-user-circle"></i> <?= __('nav_profile') ?></h2>
    </div>

    <?php if($userRole === 'farmer'): ?>
    <div class="row">
        <!-- Left Column: Add Forms -->
        <div class="col-lg-4 mb-4">
            
            <!-- Add Land Form -->
            <div class="card shadow-sm border-0 mb-4" style="border-top: 3px solid var(--secondary-green);">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-map"></i> <?= __('add_land_detail') ?>
                </div>
                <div class="card-body">
                    <form id="addLandForm">
                        <div class="mb-3">
                            <label class="form-label"><?= __('total_area') ?></label>
                            <input type="number" step="0.01" class="form-control" id="land_area" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('soil_type') ?></label>
                            <select class="form-select" id="soil_type" required>
                                <option value="Alluvial"><?= __('soil_alluvial') ?></option>
                                <option value="Black"><?= __('soil_black') ?></option>
                                <option value="Red"><?= __('soil_red') ?></option>
                                <option value="Laterite"><?= __('soil_laterite') ?></option>
                                <option value="Sandy"><?= __('soil_sandy') ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('irrigation_type') ?></label>
                            <select class="form-select" id="irrigation_type">
                                <option value="rainfed"><?= __('irrigation_rainfed') ?></option>
                                <option value="tubewell"><?= __('irrigation_tubewell') ?></option>
                                <option value="canal"><?= __('irrigation_canal') ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('location') ?></label>
                            <input type="text" class="form-control" id="location">
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100" id="btnLand"><?= __('save_land') ?></button>
                    </form>
                </div>
            </div>

            <!-- Add Crop Form -->
            <div class="card shadow-sm border-0" style="border-top: 3px solid var(--primary-orange);">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-seedling"></i> <?= __('add_current_crop') ?>
                </div>
                <div class="card-body">
                    <form id="addCropForm">
                        <div class="mb-3">
                            <label class="form-label"><?= __('select_land') ?></label>
                            <select class="form-select" id="land_id" required>
                                <option value=""><?= __('choose_land') ?></option>
                                <?php foreach($lands as $land): ?>
                                    <option value="<?= $land['id'] ?>">Land #<?= $land['id'] ?> - <?= $land['area'] ?> Acres (<?= $land['soil_type'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('select_crop') ?></label>
                            <select class="form-select" id="crop_id" required>
                                <option value=""><?= __('choose_crop') ?></option>
                                <?php 
                                    $langKey = \App\Helpers\Translator::getCurrentLang() === 'pa' ? 'name_pa' : (\App\Helpers\Translator::getCurrentLang() === 'hi' ? 'name_hi' : 'name');
                                ?>
                                <?php foreach($masterCrops as $mcrop): ?>
                                    <option value="<?= $mcrop['id'] ?>"><?= $mcrop[$langKey] ?? $mcrop['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('sowing_date') ?></label>
                            <input type="date" class="form-control" id="sowing_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('area_allocated') ?></label>
                            <input type="number" step="0.01" class="form-control" id="area_allocated" required>
                        </div>
                        <button type="submit" class="btn btn-secondary-custom w-100" id="btnCrop"><?= __('save_crop') ?></button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Lists -->
        <div class="col-lg-8">
            <h4 class="fw-bold mb-3">My Lands</h4>
            <div class="row g-3 mb-5">
                <?php if(empty($lands)): ?>
                    <div class="col-12"><p class="text-muted">No land details added yet.</p></div>
                <?php else: ?>
                    <?php foreach($lands as $land): ?>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm" style="border-left: 4px solid var(--primary-green);">
                                <div class="card-body">
                                    <h5 class="fw-bold">Land #<?= $land['id'] ?></h5>
                                    <p class="mb-1"><i class="fa-solid fa-ruler-combined text-muted"></i> <?= $land['area'] ?> Acres</p>
                                    <p class="mb-1"><i class="fa-solid fa-layer-group text-muted"></i> <?= $land['soil_type'] ?> Soil</p>
                                    <p class="mb-0"><i class="fa-solid fa-location-dot text-muted"></i> <?= htmlspecialchars($land['location_address'] ?: 'N/A') ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h4 class="fw-bold mb-3">My Crop History</h4>
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Crop</th>
                                    <th>Land</th>
                                    <th>Sown On</th>
                                    <th>Area (Acres)</th>
                                    <th>Expected Harvest</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($myCrops)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No crops added yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach($myCrops as $crop): ?>
                                        <tr>
                                            <td class="fw-bold"><?= $crop['crop_name'] ?></td>
                                            <td>Land #<?= $crop['land_id'] ?></td>
                                            <td><?= date('d M Y', strtotime($crop['sowing_date'])) ?></td>
                                            <td><?= $crop['area_allocated'] ?></td>
                                            <td><?= date('d M Y', strtotime($crop['expected_harvest_date'])) ?></td>
                                            <td>
                                                <?php if($crop['status'] === 'growing'): ?>
                                                    <span class="badge bg-success">Growing</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= ucfirst($crop['status']) ?></span>
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
    
    <?php elseif($userRole === 'buyer'): ?>
    <!-- Buyer Profile -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0" style="border-top: 3px solid var(--primary-green);">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-building"></i> Company Profile
                </div>
                <div class="card-body">
                    <form id="buyerProfileForm">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" value="<?= htmlspecialchars($buyerProfile['company_name'] ?? '') ?>" placeholder="Enter company name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">GSTIN / Tax ID</label>
                            <input type="text" class="form-control" id="gstin" value="<?= htmlspecialchars($buyerProfile['gstin'] ?? '') ?>" placeholder="Enter GSTIN">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Procurement Capacity (Tons/Month)</label>
                            <input type="number" class="form-control" id="procurement_capacity" value="<?= htmlspecialchars($buyerProfile['procurement_capacity'] ?? '') ?>" placeholder="e.g. 500">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery Address</label>
                            <textarea class="form-control" id="delivery_address" rows="2"><?= htmlspecialchars($buyerProfile['delivery_address'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100" id="btnSaveBuyer">Save Company Profile</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="bg-light p-5 rounded text-center h-100 border d-flex flex-column justify-content-center align-items-center">
                <i class="fa-solid fa-box-open fa-5x mb-4 text-muted"></i>
                <h3 class="fw-bold">Buyer Profile</h3>
                <p class="text-muted">Complete your profile to start procuring crops directly from farmers at scale.</p>
                <?php if(!empty($buyerProfile)): ?>
                    <a href="/smartharvest/public/index.php?url=buyer/marketplace" class="btn btn-secondary-custom mt-3">Procurement Marketplace</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php elseif($userRole === 'equipment_owner'): ?>
    <!-- Equipment Owner Profile -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm border-0" style="border-top: 3px solid var(--secondary-orange);">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-tractor"></i> Provider Profile
                </div>
                <div class="card-body">
                    <form id="ownerProfileForm">
                        <div class="mb-3">
                            <label class="form-label">Business Name</label>
                            <input type="text" class="form-control" id="business_name" value="<?= htmlspecialchars($ownerProfile['business_name'] ?? '') ?>" placeholder="Enter business name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Area (Radius in KM)</label>
                            <input type="number" class="form-control" id="service_radius_km" value="<?= htmlspecialchars($ownerProfile['service_radius_km'] ?? '50') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Location (Village/District)</label>
                            <input type="text" class="form-control" id="base_location" value="<?= htmlspecialchars($ownerProfile['base_location'] ?? '') ?>" placeholder="e.g. Ludhiana" required>
                        </div>
                        <button type="submit" class="btn btn-secondary-custom w-100" id="btnSaveOwner">Save Profile</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="bg-light p-5 rounded text-center h-100 border d-flex flex-column justify-content-center align-items-center">
                <i class="fa-solid fa-truck-pickup fa-5x mb-4 text-muted"></i>
                <h3 class="fw-bold">Fleet Manager</h3>
                <p class="text-muted">Set up your profile so farmers in your radius can easily find and rent your machinery.</p>
                <?php if(!empty($ownerProfile)): ?>
                    <a href="/smartharvest/public/index.php?url=equipment/fleet" class="btn btn-primary mt-3">Manage My Fleet</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<script>
// Buyer specific JS
if (document.getElementById('buyerProfileForm')) {
    document.getElementById('buyerProfileForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSaveBuyer');
        btn.disabled = true;
        btn.innerHTML = 'Saving...';
        
        const payload = {
            company_name: document.getElementById('company_name').value,
            gstin: document.getElementById('gstin').value,
            procurement_capacity: document.getElementById('procurement_capacity').value,
            delivery_address: document.getElementById('delivery_address').value
        };

        try {
            const res = await fetch('/smartharvest/public/index.php?url=profile/saveBuyerProfile', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if(data.success) {
                showToast('success', 'Profile saved successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message);
                btn.disabled = false;
                btn.innerHTML = 'Save Company Profile';
            }
        } catch(err) {
            showToast('error', 'Error connecting to server.');
            btn.disabled = false;
            btn.innerHTML = 'Save Company Profile';
        }
    });
}
// Equipment Owner specific JS
if (document.getElementById('ownerProfileForm')) {
    document.getElementById('ownerProfileForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSaveOwner');
        btn.disabled = true;
        btn.innerHTML = 'Saving...';
        
        const payload = {
            business_name: document.getElementById('business_name').value,
            service_radius_km: document.getElementById('service_radius_km').value,
            base_location: document.getElementById('base_location').value
        };

        try {
            const res = await fetch('/smartharvest/public/index.php?url=profile/saveOwnerProfile', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if(data.success) {
                showToast('success', 'Profile saved successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message);
                btn.disabled = false;
                btn.innerHTML = 'Save Profile';
            }
        } catch(err) {
            showToast('error', 'Error connecting to server.');
            btn.disabled = false;
            btn.innerHTML = 'Save Profile';
        }
    });
}
// Handle Add Land
if(document.getElementById('addLandForm')) {
    document.getElementById('addLandForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnLand');
        btn.disabled = true;
        btn.innerHTML = 'Saving...';
        
        const payload = {
            area: document.getElementById('land_area').value,
            soil_type: document.getElementById('soil_type').value,
            irrigation_type: document.getElementById('irrigation_type').value,
            location: document.getElementById('location').value
        };

        try {
            const res = await fetch('/smartharvest/public/index.php?url=profile/addLand', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if(data.success) {
                showToast('success', 'Land added successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message);
                btn.disabled = false;
                btn.innerHTML = 'Save Land';
            }
        } catch(err) {
            showToast('error', 'Error connecting to server.');
            btn.disabled = false;
            btn.innerHTML = 'Save Land';
        }
    });
}

// Handle Add Crop
if(document.getElementById('addCropForm')) {
    document.getElementById('addCropForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btnCrop');
        btn.disabled = true;
        btn.innerHTML = 'Saving...';
        
        const payload = {
            land_id: document.getElementById('land_id').value,
            crop_id: document.getElementById('crop_id').value,
            sowing_date: document.getElementById('sowing_date').value,
            area_allocated: document.getElementById('area_allocated').value
        };

        try {
            const res = await fetch('/smartharvest/public/index.php?url=profile/addCrop', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if(data.success) {
                showToast('success', 'Crop added successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('error', data.message);
                btn.disabled = false;
                btn.innerHTML = 'Save Crop';
            }
        } catch(err) {
            showToast('error', 'Error connecting to server.');
            btn.disabled = false;
            btn.innerHTML = 'Save Crop';
        }
    });
}
</script>
