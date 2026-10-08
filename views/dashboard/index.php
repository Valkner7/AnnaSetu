<div class="container py-5 animate-fade-in">
    <!-- Common Header Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-bold text-dark mb-0">Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <span class="fw-bold text-success"><?= htmlspecialchars($displayName) ?></span></p>
        </div>
        <div>
            <span class="badge bg-light text-dark border px-3 py-2 fs-6 shadow-sm">
                <i class="fa-solid fa-user-tag text-primary"></i> 
                <?= ucfirst(str_replace('_', ' ', $userRole)) ?>
            </span>
        </div>
    </div>

    <?php if($userRole === 'farmer'): ?>
        <!-- ============================== -->
        <!-- FARMER PANEL WITH NAVIGATION -->
        <!-- ============================== -->
        
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 100px; overflow: hidden;">
                    <div class="bg-dark text-white p-4 text-center">
                        <div class="mb-3">
                            <i class="fa-solid fa-user-circle fa-4x text-light"></i>
                        </div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($displayName) ?></h5>
                        <small class="text-success"><i class="fa-solid fa-circle" style="font-size: 8px;"></i> Verified Farmer</small>
                    </div>
                    <div class="list-group list-group-flush" id="farmer-nav-tab" role="tablist">
                        <a class="list-group-item list-group-item-action active fw-bold py-3" id="tab-overview" data-bs-toggle="list" href="#pane-overview" role="tab">
                            <i class="fa-solid fa-border-all text-primary me-2"></i> Overview
                        </a>
                        <a class="list-group-item list-group-item-action fw-bold py-3" id="tab-inventory" data-bs-toggle="list" href="#pane-inventory" role="tab">
                            <i class="fa-solid fa-boxes-stacked text-warning me-2"></i> My Inventory
                        </a>
                        <a class="list-group-item list-group-item-action fw-bold py-3 d-flex justify-content-between align-items-center" id="tab-orders" data-bs-toggle="list" href="#pane-orders" role="tab">
                            <span><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Sales & Orders</span>
                            <?php 
                                $pendingCount = 0; 
                                foreach($myOrders as $o) if($o['status'] === 'pending') $pendingCount++; 
                            ?>
                            <?php if($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="list-group-item list-group-item-action fw-bold py-3" href="<?= BASE_URL ?>/index.php?url=farmer/listProduce">
                            <i class="fa-solid fa-plus-circle text-secondary me-2"></i> Sell New Produce
                        </a>
                        <a class="list-group-item list-group-item-action fw-bold py-3" href="<?= BASE_URL ?>/index.php?url=equipment/marketplace">
                            <i class="fa-solid fa-tractor text-danger me-2"></i> Rent Equipment
                        </a>
                        <a class="list-group-item list-group-item-action fw-bold py-3" href="<?= BASE_URL ?>/index.php?url=profile">
                            <i class="fa-solid fa-map-location-dot text-info me-2"></i> Manage Lands
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Tab Content -->
            <div class="col-lg-9">
                <div class="tab-content" id="nav-tabContent">
                    
                    <!-- OVERVIEW PANE -->
                    <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card border-0 text-white overflow-hidden" style="background: linear-gradient(135deg, var(--primary-green) 0%, #2980b9 100%); border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(27, 94, 32, 0.4);">
                                    <div class="card-body p-5 position-relative">
                                        <i class="fa-solid fa-wheat-awn position-absolute" style="font-size: 15rem; right: -2rem; bottom: -3rem; opacity: 0.1;"></i>
                                        <h3 class="fw-bold mb-3">Optimize Your Harvest with AI</h3>
                                        <p class="fs-5 mb-4" style="max-width: 600px; opacity: 0.9;">Plan your next sowing season, rent smart machinery, and sell directly to bulk buyers without middlemen.</p>
                                        <a href="<?= BASE_URL ?>/index.php?url=farmer/cropPlanning" class="btn btn-light btn-lg fw-bold px-4" style="color: var(--primary-green);">
                                            <i class="fa-solid fa-robot text-primary"></i> Ask AI for Crop Plan
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="card h-100 border-0 p-2" style="background-color: #fff8e1; border-radius: 20px; box-shadow: var(--box-shadow);">
                                    <div class="card-body d-flex flex-column justify-content-center">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="bg-white p-3 rounded-circle shadow-sm me-3">
                                                <i class="fa-solid fa-store fa-2x" style="color: var(--primary-orange);"></i>
                                            </div>
                                            <div>
                                                <h4 class="fw-bold mb-0">Global Marketplace</h4>
                                                <span class="text-muted small">Sell your produce at your price</span>
                                            </div>
                                        </div>
                                        <p class="text-muted mb-4">List your upcoming or harvested crops on the platform. Let our AI grade it and match you directly with wholesale buyers.</p>
                                        <div class="mt-auto">
                                            <a href="<?= BASE_URL ?>/index.php?url=farmer/listProduce" class="btn btn-secondary-custom w-100 py-3 fw-bold shadow-sm">
                                                <i class="fa-solid fa-bullhorn"></i> Sell Produce Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <div class="row g-4 h-100">
                                    <div class="col-12">
                                        <div class="card h-100 border-0 feature-card p-4" style="border-radius: 20px; background-color: #f1f5f9;">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="fw-bold mb-0 text-dark">Farm Services</h5>
                                                <i class="fa-solid fa-tractor fa-2x text-secondary"></i>
                                            </div>
                                            <p class="text-muted small mb-3">Rent modern drones, harvesters, and tractors nearby.</p>
                                            <a href="<?= BASE_URL ?>/index.php?url=equipment/marketplace" class="btn btn-outline-dark fw-bold w-100">Find Equipment</a>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="card h-100 border-0 feature-card p-4" style="border-radius: 20px; background-color: #e0f2f1;">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h5 class="fw-bold mb-0 text-dark">My Lands</h5>
                                                <i class="fa-solid fa-map-location-dot fa-2x" style="color: var(--secondary-green);"></i>
                                            </div>
                                            <p class="text-muted small mb-3">Update your soil info and current crops.</p>
                                            <a href="<?= BASE_URL ?>/index.php?url=profile" class="btn btn-primary-custom fw-bold w-100">Manage Profile</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- INVENTORY PANE -->
                    <div class="tab-pane fade" id="pane-inventory" role="tabpanel">
                        <h3 class="fw-bold text-dark mb-4">My Crop Inventory</h3>
                        <div class="card shadow-sm border-0" style="border-top: 3px solid var(--primary-green);">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Produce</th>
                                                <th>Quantity Available</th>
                                                <th>Expected Price</th>
                                                <th>Status</th>
                                                <th>Date Listed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($myInventory)): ?>
                                                <tr><td colspan="5" class="text-center text-muted py-4">You have no active listings. <a href="<?= BASE_URL ?>/index.php?url=farmer/listProduce">Sell produce here.</a></td></tr>
                                            <?php else: ?>
                                                <?php foreach($myInventory as $inv): ?>
                                                    <tr>
                                                        <td class="fw-bold"><?= htmlspecialchars($inv['crop_name']) ?></td>
                                                        <td><?= number_format($inv['quantity_kg']/1000, 2) ?> Tons</td>
                                                        <td class="text-success fw-bold">₹<?= number_format($inv['expected_price_per_kg']*100, 2) ?>/qtl</td>
                                                        <td><span class="badge bg-success"><?= ucfirst($inv['status']) ?></span></td>
                                                        <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ORDERS PANE -->
                    <div class="tab-pane fade" id="pane-orders" role="tabpanel">
                        <h3 class="fw-bold text-dark mb-4">Sales & Incoming Orders</h3>
                        <div class="card shadow-sm border-0" style="border-top: 3px solid var(--primary-orange);">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Crop Listing</th>
                                                <th>Buyer Details</th>
                                                <th>Requested Qty</th>
                                                <th>Offered Price</th>
                                                <th>Total Value</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(empty($myOrders)): ?>
                                                <tr><td colspan="7" class="text-center text-muted py-4">No incoming orders yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach($myOrders as $bid): ?>
                                                    <tr>
                                                        <td class="fw-bold"><?= htmlspecialchars($bid['crop_name']) ?></td>
                                                        <td>
                                                            <strong><?= htmlspecialchars($bid['company_name']) ?></strong><br>
                                                            <small class="text-muted"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($bid['delivery_address']) ?></small>
                                                        </td>
                                                        <td><?= $bid['quantity_tons'] ?> Tons</td>
                                                        <td class="text-success fw-bold">₹<?= $bid['offered_price_per_quintal'] ?>/qtl</td>
                                                        <td class="fw-bold text-dark">₹<?= number_format($bid['quantity_tons'] * 10 * $bid['offered_price_per_quintal'], 2) ?></td>
                                                        <td>
                                                            <?php 
                                                                $bg = 'bg-secondary';
                                                                if($bid['status'] == 'pending') $bg = 'bg-warning text-dark';
                                                                if($bid['status'] == 'accepted') $bg = 'bg-success';
                                                                if($bid['status'] == 'rejected') $bg = 'bg-danger';
                                                            ?>
                                                            <span class="badge <?= $bg ?>"><?= ucfirst($bid['status']) ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if($bid['status'] == 'pending'): ?>
                                                                <button class="btn btn-sm btn-success update-bid-btn" data-id="<?= $bid['id'] ?>" data-status="accepted">Accept</button>
                                                                <button class="btn btn-sm btn-outline-danger update-bid-btn" data-id="<?= $bid['id'] ?>" data-status="rejected">Reject</button>
                                                            <?php elseif($bid['status'] == 'accepted'): ?>
                                                                <button class="btn btn-sm btn-primary" onclick="alert('Contact Buyer at <?= $bid['buyer_phone'] ?> to arrange dispatch.')">Contact</button>
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
        </div>
        
        <script>
        document.querySelectorAll('.update-bid-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if(!confirm(`Are you sure you want to mark this order as ${this.dataset.status}?`)) return;
                
                this.disabled = true;
                const payload = {
                    order_id: this.dataset.id,
                    status: this.dataset.status
                };

                try {
                    const res = await fetch('<?= BASE_URL ?>/index.php?url=farmer/updateOrderStatus', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    
                    if(data.success) {
                        showToast('success', 'Order status updated!');
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

    <?php elseif($userRole === 'buyer'): ?>
        <!-- ============================== -->
        <!-- CORPORATE BUYER DASHBOARD -->
        <!-- ============================== -->
        
        <!-- KPI Row -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm" style="border-radius: 16px; border-left: 5px solid var(--primary-green) !important;">
                    <div class="card-body p-4">
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Active Market Size</h6>
                        <h2 class="fw-bold text-dark mb-0"><i class="fa-solid fa-leaf text-success fs-4"></i> Live</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm" style="border-radius: 16px; border-left: 5px solid var(--primary-orange) !important;">
                    <div class="card-body p-4">
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Pending Orders</h6>
                        <h2 class="fw-bold text-dark mb-0"><i class="fa-solid fa-clock-rotate-left text-warning fs-4"></i> Track</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm" style="border-radius: 16px; border-left: 5px solid #3b82f6 !important;">
                    <div class="card-body p-4">
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Logistics</h6>
                        <h2 class="fw-bold text-dark mb-0"><i class="fa-solid fa-truck-fast text-primary fs-4"></i> Coming Soon</h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Procurement Action Center -->
        <div class="row g-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                    <div class="card-body p-5 text-center d-flex flex-column justify-content-center align-items-center" style="background: linear-gradient(to right, #f8fafc, #e2e8f0); border-radius: 20px;">
                        <i class="fa-solid fa-cart-flatbed fa-4x mb-4 text-primary"></i>
                        <h3 class="fw-bold text-dark">Procurement Marketplace</h3>
                        <p class="text-muted fs-5 mb-4" style="max-width: 500px;">Source high-quality, AI-graded agricultural produce directly from verified farmers across the region.</p>
                        <a href="<?= BASE_URL ?>/index.php?url=buyer/marketplace" class="btn btn-primary btn-lg fw-bold px-5 py-3 shadow">
                            Enter the Market
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 feature-card p-0" style="border-radius: 20px;">
                    <div class="card-body p-5 d-flex flex-column justify-content-center align-items-center text-center">
                        <i class="fa-solid fa-list-check fa-4x mb-4 text-secondary"></i>
                        <h4 class="fw-bold">My Procurement Orders</h4>
                        <p class="text-muted small mb-4">View accepted bids and contact farmers for dispatch.</p>
                        <a href="<?= BASE_URL ?>/index.php?url=buyer/myOrders" class="btn btn-outline-dark fw-bold w-100 py-2">View Orders</a>
                        
                        <hr class="w-100 my-4 text-muted">
                        
                        <a href="<?= BASE_URL ?>/index.php?url=profile" class="btn btn-light w-100 text-muted border fw-bold"><i class="fa-solid fa-building"></i> Edit Company Profile</a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif($userRole === 'equipment_owner'): ?>
        <!-- ============================== -->
        <!-- EQUIPMENT OWNER DASHBOARD -->
        <!-- ============================== -->
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 text-white overflow-hidden" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(217, 119, 6, 0.4);">
                    <div class="card-body p-5 position-relative">
                        <i class="fa-solid fa-gears position-absolute" style="font-size: 15rem; right: -1rem; bottom: -4rem; opacity: 0.15;"></i>
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h3 class="fw-bold mb-3">Operations & Fleet Manager</h3>
                                <p class="fs-5 mb-0" style="opacity: 0.9;">Turn your idle machinery into passive income. List your tractors and modern tech for local farmers to rent.</p>
                            </div>
                            <div class="col-md-4 text-md-end mt-4 mt-md-0">
                                <a href="<?= BASE_URL ?>/index.php?url=equipment/fleet" class="btn btn-light btn-lg fw-bold px-4 text-dark shadow-sm">
                                    <i class="fa-solid fa-plus text-warning"></i> Add Machinery
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 20px; transition: transform 0.2s;">
                    <div class="card-body p-5 text-center">
                        <div class="bg-light d-inline-block p-4 rounded-circle mb-4 shadow-sm">
                            <i class="fa-solid fa-calendar-check fa-3x text-success"></i>
                        </div>
                        <h4 class="fw-bold">Incoming Bookings</h4>
                        <p class="text-muted mb-4">Review requests from farmers, accept bookings, and manage your service schedule.</p>
                        <a href="<?= BASE_URL ?>/index.php?url=equipment/fleet" class="btn btn-primary-custom fw-bold px-5 py-2">Manage Bookings</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 20px;">
                    <div class="card-body p-5 text-center">
                        <div class="bg-light d-inline-block p-4 rounded-circle mb-4 shadow-sm">
                            <i class="fa-solid fa-location-crosshairs fa-3x" style="color: var(--primary-orange);"></i>
                        </div>
                        <h4 class="fw-bold">Service Area Configuration</h4>
                        <p class="text-muted mb-4">Set your base location and maximum travel radius to match with the right farmers.</p>
                        <a href="<?= BASE_URL ?>/index.php?url=profile" class="btn btn-outline-dark fw-bold px-5 py-2">Update Profile</a>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Generic / Unknown Role -->
        <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 text-center">
            <i class="fa-solid fa-circle-info fa-2x mb-3"></i>
            <h4>Role not completely configured</h4>
            <p>Please update your profile to access specialized features.</p>
            <a href="<?= BASE_URL ?>/index.php?url=profile" class="btn btn-primary mt-2">Go to Profile</a>
        </div>
    <?php endif; ?>
</div>
