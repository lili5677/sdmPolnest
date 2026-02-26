<?php
// Check session untuk navbar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'pelamar';
$user_email = $is_logged_in ? $_SESSION['email'] : '';
$username = $is_logged_in ? explode('@', $user_email)[0] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Politeknik NEST' ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        /* Navbar */
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

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .navbar-logo {
            width: 52px;
            height: 52px;
            padding: 5px;
        }

        .navbar-title {
            color: white;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 35px;
        }

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

        /* Login Button */
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
            text-decoration: none;
            display: inline-block;
        }

        .btn-login:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 255, 255, 0.4);
            color: #F6C35A;
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .user-icon-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            border: 2px solid rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-icon-btn:hover {
            transform: scale(1.08);
            border-color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .user-icon-btn i {
            font-size: 24px;
            color: #F19BB8;
        }

        /* Dropdown Menu */
        .dropdown-menu-custom {
            display: none;
            position: absolute;
            top: 58px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            min-width: 260px;
            overflow: hidden;
            z-index: 1000;
        }

        .dropdown-menu-custom.show {
            display: block;
            animation: dropFade 0.25s ease-out;
        }

        @keyframes dropFade {
            from { 
                opacity: 0; 
                transform: translateY(-12px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        /* Dropdown Items */
        .dropdown-item-custom {
            padding: 13px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #37474f;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid #f5f5f5;
        }

        .dropdown-item-custom:last-child {
            border-bottom: none;
        }

        .dropdown-item-custom:hover {
            background: #f8f9fa;
            padding-left: 24px;
        }

        .dropdown-item-custom i {
            font-size: 19px;
            width: 22px;
            color: #607d8b;
            transition: all 0.2s;
        }

        .dropdown-item-custom:hover i {
            color: #0d47a1;
        }

        /* Logout Item */
        .dropdown-item-custom.logout {
            color: #d32f2f;
            border-top: 1px solid #f0f0f0;
            margin-top: 4px;
        }

        .dropdown-item-custom.logout:hover {
            background: #ffebee;
        }

        .dropdown-item-custom.logout i {
            color: #d32f2f;
        }

        .dropdown-item-custom.logout:hover i {
            color: #c62828;
        }

        /* Mobile Toggle */
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
            .navbar-right {
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

            .navbar-right.show {
                display: flex;
            }

            .navbar-menu {
                flex-direction: column;
                gap: 0;
                width: 100%;
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
            
            .btn-login {
                width: 100%;
                text-align: center;
                margin-top: 10px;
            }

            .user-dropdown {
                width: 100%;
            }

            .user-icon-btn {
                width: 100%;
                border-radius: 8px;
                justify-content: flex-start;
                gap: 12px;
                padding: 12px 20px;
            }

            .dropdown-menu-custom {
                position: static;
                box-shadow: none;
                margin-top: 10px;
                border-radius: 8px;
            }
        }

        /* Custom SweetAlert */
        .swal2-popup {
            border-radius: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 30px !important;
            font-weight: 600 !important;
        }

        .swal2-cancel {
            background: #6c757d !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 30px !important;
            font-weight: 600 !important;
        }
    </style>
</head>
<body>
    <nav class="navbar-custom">
        <div class="navbar-container">
            <a href="<?php echo BASE_URL; ?>index.php" class="navbar-brand">
                <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo" class="navbar-logo">
                <h1 class="navbar-title">POLITEKNIK NEST</h1>
            </a>

            <button class="mobile-toggle" onclick="toggleMobileMenu()">
                <i class="bi bi-list"></i>
            </button>

            <div class="navbar-right" id="navbarRight">
                <ul class="navbar-menu">
                    <li><a href="<?php echo BASE_URL; ?>users/pelamar/dashboard.php">Lowongan Pekerjaan</a></li>
                    <li><a href="<?php echo BASE_URL; ?>users/pelamar/tracking_lamaran.php">Tracking Lamaran</a></li>
                </ul>

                <?php if ($is_logged_in): ?>
                    <div class="user-dropdown">
                        <div class="user-icon-btn" onclick="toggleUserDropdown()">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div class="dropdown-menu-custom" id="userDropdown">
                            <a href="<?php echo BASE_URL; ?>users/pelamar/profil.php" class="dropdown-item-custom">
                                <i class="bi bi-person-fill"></i>
                                <span>Profil Saya</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>users/pelamar/keamanan_akun.php" class="dropdown-item-custom">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Keamanan Akun</span>
                            </a>
                            <a href="javascript:void(0)" class="dropdown-item-custom logout" onclick="confirmLogout()">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>auth/login_pelamar.php?redirect=lowongan" class="btn-login">
                        Daftar/Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <script>
        function toggleMobileMenu() {
            const navbarRight = document.getElementById('navbarRight');
            const toggle = document.querySelector('.mobile-toggle i');
            navbarRight.classList.toggle('show');
            toggle.className = navbarRight.classList.contains('show') ? 'bi bi-x-lg' : 'bi bi-list';
        }

        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        function confirmLogout() {
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Logging out...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    window.location.href = '<?php echo BASE_URL; ?>auth/logout.php';
                }
            });
        }

        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const dropdown = document.getElementById('userDropdown');
            if (userDropdown && !userDropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>