<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow border-0" style="border-top: 4px solid var(--primary-orange);">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="Logo" style="height: 50px; margin-bottom: 10px;">
                        <h3 class="fw-bold text-dark">Join Annasetu</h3>
                        <p class="text-muted">Register to access the complete agricultural ecosystem</p>
                    </div>
                    
                    <div id="registerAlert" class="alert d-none" role="alert"></div>

                    <form id="registerForm">
                        <div class="row mb-3">
                            <div class="col-md-6 text-start">
                                <label class="form-label fw-bold">First Name</label>
                                <input type="text" class="form-control" id="first_name" required>
                            </div>
                            <div class="col-md-6 text-start mt-3 mt-md-0">
                                <label class="form-label fw-bold">Last Name</label>
                                <input type="text" class="form-control" id="last_name" required>
                            </div>
                        </div>

                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">+91</span>
                                <input type="tel" class="form-control" id="phone" required placeholder="10-digit mobile number">
                            </div>
                        </div>
                        
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold">I am a...</label>
                            <select class="form-select" id="role" required>
                                <option value="farmer">Farmer (किसान / ਕਿਸਾਨ)</option>
                                <option value="buyer">Bulk Buyer (थोक खरीदार)</option>
                                <option value="equipment_owner">Equipment Owner (मशीनरी मालिक)</option>
                                <option value="storage_provider">Storage Provider (स्टोरेज प्रदाता)</option>
                                <option value="transporter">Transporter (ट्रांसपोर्टर)</option>
                            </select>
                        </div>

                        <div class="mb-4 text-start">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" class="form-control" id="password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-secondary-custom w-100 py-2 fw-bold" id="registerBtn">Create Account</button>
                    </form>
                    
                    <p class="mt-4 mb-0 text-center">Already have an account? <a href="<?= BASE_URL ?>/index.php?url=auth/loginView" style="color: var(--primary-green); font-weight: bold;">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('registerForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('registerBtn');
    
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating Account...';
    btn.disabled = true;
    
    const payload = {
        first_name: document.getElementById('first_name').value,
        last_name: document.getElementById('last_name').value,
        phone: document.getElementById('phone').value,
        role: document.getElementById('role').value,
        password: document.getElementById('password').value,
        language: 'en'
    };

    try {
        const response = await fetch('<?= BASE_URL ?>/index.php?url=auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', result.message + ' Redirecting...');
            setTimeout(() => {
                window.location.href = '<?= BASE_URL ?>/index.php?url=auth/loginView'; 
            }, 1500);
        } else {
            showToast('error', result.message);
            btn.innerHTML = 'Create Account';
            btn.disabled = false;
        }
        
    } catch (error) {
        showToast('error', 'Network error occurred.');
        btn.innerHTML = 'Create Account';
        btn.disabled = false;
    }
});
</script>
