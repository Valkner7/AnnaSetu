<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow border-0" style="border-top: 4px solid var(--primary-green);">
                <div class="card-body p-4 p-md-5 text-center">
                    <img src="/smartharvest/public/assets/images/logo.jpg" alt="Logo" style="height: 60px; margin-bottom: 20px;">
                    <h3 class="fw-bold text-dark mb-4">Welcome Back</h3>
                    
                    <div id="loginAlert" class="alert d-none" role="alert"></div>

                    <form id="loginForm">
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-phone"></i></span>
                                <input type="tel" class="form-control" id="phone" required placeholder="Enter mobile number">
                            </div>
                        </div>
                        <div class="mb-4 text-start">
                            <label class="form-label fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" required placeholder="Enter password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-custom w-100 py-2 fw-bold" id="loginBtn">Login to Dashboard</button>
                    </form>
                    
                    <p class="mt-4 mb-0">Don't have an account? <a href="/smartharvest/public/index.php?url=auth/registerView" style="color: var(--primary-orange); font-weight: bold;">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('loginBtn');
    const alertBox = document.getElementById('loginAlert');
    
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...';
    btn.disabled = true;
    
    const payload = {
        phone: document.getElementById('phone').value,
        password: document.getElementById('password').value
    };

    try {
        // Fetch API - Same endpoint the mobile app would use
        const response = await fetch('/smartharvest/public/index.php?url=auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', result.message);
            // Save token (Simulation for web)
            localStorage.setItem('auth_token', result.token);
            setTimeout(() => {
                window.location.href = '/smartharvest/public/index.php?url=dashboard'; 
            }, 1000);
        } else {
            showToast('error', result.message);
            btn.innerHTML = 'Login to Dashboard';
            btn.disabled = false;
        }
        
    } catch (error) {
        showToast('error', 'Network error occurred.');
        btn.innerHTML = 'Login to Dashboard';
        btn.disabled = false;
    }
});
</script>
