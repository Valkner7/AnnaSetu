<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'SmartHarvest AI - Annasetu' ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 for Modern Toasts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/smartharvest/public/assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<script>
    // Global Toast Notification System
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    // Helper wrapper for native alerts fallback in older code
    window.showToast = function(type, message) {
        Toast.fire({
            icon: type, // 'success', 'error', 'warning', 'info'
            title: message
        });
    };
</script>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="/smartharvest/public/">
                <img src="/smartharvest/public/assets/images/logo.jpg" alt="Annasetu Logo">
                Annasetu
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/smartharvest/public/index.php?url=mandi"><?= __('nav_market_prices') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><?= __('nav_services') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><?= __('nav_marketplace') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><?= __('nav_smart_storage') ?></a>
                    </li>
                    
                    <!-- Language Switcher -->
                    <li class="nav-item dropdown ms-lg-3">
                        <a class="nav-link dropdown-toggle btn btn-sm btn-outline-secondary" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-language"></i> <?= strtoupper(\App\Helpers\Translator::getCurrentLang()) ?>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="langDropdown">
                            <li><a class="dropdown-item" href="?url=<?= $_GET['url'] ?? 'home' ?>&lang=en">English</a></li>
                            <li><a class="dropdown-item" href="?url=<?= $_GET['url'] ?? 'home' ?>&lang=hi">हिंदी (Hindi)</a></li>
                            <li><a class="dropdown-item" href="?url=<?= $_GET['url'] ?? 'home' ?>&lang=pa">ਪੰਜਾਬੀ (Punjabi)</a></li>
                        </ul>
                    </li>

                    <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-outline-success px-4" href="/smartharvest/public/index.php?url=dashboard"><?= __('nav_dashboard') ?></a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-secondary px-4" href="/smartharvest/public/index.php?url=profile"><?= __('nav_profile') ?></a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-danger px-4" href="/smartharvest/public/index.php?url=auth/logout"><?= __('nav_logout') ?></a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-secondary-custom px-4" href="/smartharvest/public/index.php?url=auth/loginView"><?= __('nav_login') ?></a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-primary-custom px-4" href="/smartharvest/public/index.php?url=auth/registerView"><?= __('nav_register') ?></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main>
        <?php require_once $contentView; ?>
    </main>

    <!-- Footer -->
    <footer class="footer-custom">
        <div class="container text-center">
            <p class="mb-2">किसान से बाजार तक • डिजिटल से समृद्धि तक</p>
            <p class="mb-0">&copy; <?= date('Y') ?> SmartHarvest AI. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
