<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
    /* Navbar Styling */
    .navbar-custom {
        background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);
        padding: 12px 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .navbar-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Logo Section */
    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }

    .navbar-logo {
        width: 45px;
        height: 45px;
        background: white;
        border-radius: 50%;
        padding: 5px;
        object-fit: contain;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .navbar-title {
        color: white;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 0;
    }

    /* Navigation Menu */
    .navbar-menu {
        display: flex;
        gap: 35px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .navbar-menu li a {
        color: white;
        text-decoration: none;
        font-size: 15px;
        font-weight: 500;
        transition: all 0.3s;
        position: relative;
        padding: 5px 0;
    }

    .navbar-menu li a::after {
        content: '';
        position: absolute;
        bottom: -3px;
        left: 0;
        width: 0;
        height: 2px;
        background: white;
        transition: width 0.3s;
    }

    .navbar-menu li a:hover::after {
        width: 100%;
    }

    .navbar-menu li a:hover {
        opacity: 0.9;
    }

    /* Login Button & Dropdown */
    .login-wrapper {
        position: relative;
    }

    .btn-login {
        background: white;
        color: #F19BB8;
        padding: 10px 30px;
        border: none;
        border-radius: 25px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
    }

    .btn-login:hover {
        background: #fff;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(255, 255, 255, 0.4);
        color: #F6C35A;
    }

    .btn-login:active {
        transform: translateY(0);
    }

    /* Dropdown Login */
    .login-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        min-width: 280px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .login-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    /* Triangle Arrow */
    .login-dropdown::before {
        content: '';
        position: absolute;
        top: -8px;
        right: 20px;
        width: 0;
        height: 0;
        border-left: 10px solid transparent;
        border-right: 10px solid transparent;
        border-bottom: 10px solid white;
    }

    .dropdown-item-custom {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 16px 20px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s;
        border-bottom: 1px solid #f0f0f0;
    }

    .dropdown-item-custom:last-child {
        border-bottom: none;
        border-radius: 0 0 12px 12px;
    }

    .dropdown-item-custom:first-child {
        border-radius: 12px 12px 0 0;
    }

    .dropdown-item-custom:hover {
        background: #f8f9fa;
    }

    .dropdown-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        flex-shrink: 0;
    }

    .dropdown-content {
        flex: 1;
    }

    .dropdown-title {
        font-size: 15px;
        font-weight: 600;
        color: #333;
        margin-bottom: 3px;
    }

    .dropdown-subtitle {
        font-size: 12px;
        color: #666;
        margin: 0;
    }

    /* Mobile Menu Toggle */
    .mobile-toggle {
        display: none;
        background: white;
        border: none;
        color: #F19BB8;
        font-size: 24px;
        cursor: pointer;
        padding: 8px;
        border-radius: 8px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .navbar-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);
            flex-direction: column;
            gap: 0;
            padding: 15px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .navbar-menu.show {
            display: flex;
        }

        .navbar-menu li {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-menu li:last-child {
            border-bottom: none;
        }

        .navbar-menu li a {
            display: block;
            padding: 12px 0;
        }

        .mobile-toggle {
            display: block;
        }

        .navbar-container {
            position: relative;
        }

        .login-dropdown {
            min-width: 260px;
        }
    }
</style>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <!-- Logo -->
        <a href="index.php" class="navbar-brand">
            <img src="assets/logo.png" alt="Logo Politeknik Nest" class="navbar-logo">
            <h1 class="navbar-title">POLITEKNIK NEST</h1>
        </a>

        <!-- Mobile Toggle -->
        <button class="mobile-toggle" onclick="toggleMobileMenu()">
            <i class="bi bi-list"></i>
        </button>

        <!-- Navigation Menu -->
        <ul class="navbar-menu" id="navbarMenu">
            <li><a href="index.php">Beranda</a></li>
            <li><a href="tim-kami.php">Tim Kami</a></li>
            <li><a href="lowongan.php">Lowongan Pekerjaan</a></li>
        </ul>

        <!-- Login Button -->
        <div class="login-wrapper">
            <button class="btn-login" onclick="toggleLoginDropdown()">
                Login
            </button>

            <!-- Login Dropdown -->
            <div class="login-dropdown" id="loginDropdown">
                <a href="login-dosen.php" class="dropdown-item-custom">
                    <div class="dropdown-icon">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <div class="dropdown-content">
                        <div class="dropdown-title">Login sebagai Dosen</div>
                        <p class="dropdown-subtitle">Akses Portal Dosen</p>
                    </div>
                </a>

                <a href="login-pegawai.php" class="dropdown-item-custom">
                    <div class="dropdown-icon">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <div class="dropdown-content">
                        <div class="dropdown-title">Login sebagai Pegawai</div>
                        <p class="dropdown-subtitle">Akses Portal Pegawai</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>

<script>
    // Toggle Login Dropdown
    function toggleLoginDropdown() {
        const dropdown = document.getElementById('loginDropdown');
        dropdown.classList.toggle('show');
    }

    // Toggle Mobile Menu
    function toggleMobileMenu() {
        const menu = document.getElementById('navbarMenu');
        const toggle = document.querySelector('.mobile-toggle i');
        
        menu.classList.toggle('show');
        
        // Change icon
        if (menu.classList.contains('show')) {
            toggle.className = 'bi bi-x-lg';
        } else {
            toggle.className = 'bi bi-list';
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const loginWrapper = document.querySelector('.login-wrapper');
        const dropdown = document.getElementById('loginDropdown');
        
        if (loginWrapper && !loginWrapper.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Prevent dropdown close when clicking inside
    const loginDropdown = document.getElementById('loginDropdown');
    if (loginDropdown) {
        loginDropdown.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
</script>
