<!-- Hero Section with Carousel Background -->
<header class="hero-section position-relative p-0" style="min-height: 500px; display: flex; align-items: center; overflow: hidden;">
    <!-- Bootstrap Carousel -->
    <div id="heroCarousel" class="carousel slide carousel-fade position-absolute w-100 h-100" data-bs-ride="carousel" data-bs-interval="4000" style="top: 0; left: 0; z-index: 0;">
        <div class="carousel-inner w-100 h-100">
            <!-- Slide 1: Farmer & Tech -->
            <div class="carousel-item active w-100 h-100">
                <img src="/smartharvest/public/assets/images/banner1.jpg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Smart Farming">
                <div class="carousel-overlay position-absolute w-100 h-100" style="top:0; left:0; background: linear-gradient(135deg, rgba(27,94,32,0.85) 0%, rgba(46,160,67,0.7) 100%);"></div>
            </div>
            <!-- Slide 2: Smart Storage & Market -->
            <div class="carousel-item w-100 h-100">
                <img src="/smartharvest/public/assets/images/banner2.jpg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Wholesale Market">
                <div class="carousel-overlay position-absolute w-100 h-100" style="top:0; left:0; background: linear-gradient(135deg, rgba(27,94,32,0.85) 0%, rgba(46,160,67,0.7) 100%);"></div>
            </div>
            <!-- Slide 3: Drones & Machinery -->
            <div class="carousel-item w-100 h-100">
                <img src="/smartharvest/public/assets/images/banner3.jpg" class="d-block w-100 h-100" style="object-fit: cover;" alt="Agri Services">
                <div class="carousel-overlay position-absolute w-100 h-100" style="top:0; left:0; background: linear-gradient(135deg, rgba(27,94,32,0.85) 0%, rgba(46,160,67,0.7) 100%);"></div>
            </div>
        </div>
        
        <!-- Controls -->
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" style="z-index: 2;">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" style="z-index: 2;">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <!-- Hero Content overlaying the carousel -->
    <div class="container position-relative text-center text-white" style="z-index: 1;">
        <h1 class="hero-title fw-bold" style="font-size: 4rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Annasetu (अन्नसेतु)</h1>
        <p class="hero-subtitle fs-3 fw-light mb-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">SmartHarvest AI Ecosystem</p>
        <p class="lead mb-4 mx-auto" style="max-width: 700px; text-shadow: 1px 1px 2px rgba(0,0,0,0.8);">A complete agricultural ecosystem connecting farmers with AI intelligence, smart storage, and direct buyers.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="/smartharvest/public/index.php?url=auth/registerView" class="btn btn-primary-custom btn-lg px-4 shadow">Join as Farmer</a>
            <a href="#features" class="btn btn-secondary-custom btn-lg px-4 shadow">Explore Platform</a>
        </div>
    </div>
</header>

<!-- Features Section -->
<section id="features" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold" style="color: var(--primary-green);">Everything you need from seed to sale.</h2>
            <p class="text-muted">A comprehensive platform to support every stage of farming.</p>
        </div>
        
        <div class="row g-4">
            <!-- Feature 1 -->
            <div class="col-md-4 col-sm-6">
                <div class="feature-card">
                    <i class="fa-solid fa-tractor feature-icon"></i>
                    <h4 class="fw-bold">Agri Services</h4>
                    <p>Rent tractors, harvesters, and drones instantly from local providers.</p>
                </div>
            </div>
            
            <!-- Feature 2 -->
            <div class="col-md-4 col-sm-6">
                <div class="feature-card">
                    <i class="fa-solid fa-warehouse feature-icon"></i>
                    <h4 class="fw-bold">Smart Storage</h4>
                    <p>IoT-enabled cold storage monitoring to reduce post-harvest losses.</p>
                </div>
            </div>
            
            <!-- Feature 3 -->
            <div class="col-md-4 col-sm-6">
                <div class="feature-card">
                    <i class="fa-solid fa-microchip feature-icon"></i>
                    <h4 class="fw-bold">Quality AI</h4>
                    <p>Automated crop grading using computer vision for fair pricing.</p>
                </div>
            </div>
            
            <!-- Feature 4 -->
            <div class="col-md-4 col-sm-6">
                <div class="feature-card">
                    <i class="fa-solid fa-handshake feature-icon"></i>
                    <h4 class="fw-bold">Direct Buyer</h4>
                    <p>B2B marketplace connecting farmers directly to bulk buyers.</p>
                </div>
            </div>
            
            <!-- Feature 5 -->
            <div class="col-md-4 col-sm-6">
                <div class="feature-card">
                    <i class="fa-solid fa-truck-fast feature-icon"></i>
                    <h4 class="fw-bold">Logistics</h4>
                    <p>Seamless transport booking from farm to warehouse or buyer.</p>
                </div>
            </div>
            
            <!-- Feature 6 -->
            <div class="col-md-4 col-sm-6">
                <div class="feature-card">
                    <i class="fa-solid fa-chart-line feature-icon"></i>
                    <h4 class="fw-bold">More Profit</h4>
                    <p>Predictive analytics to maximize yield and market revenue.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Fallback to ensure carousel slides automatically
document.addEventListener('DOMContentLoaded', function() {
    setInterval(function() {
        const nextBtn = document.querySelector('.carousel-control-next');
        if (nextBtn) {
            nextBtn.click();
        }
    }, 4000);
});
</script>
