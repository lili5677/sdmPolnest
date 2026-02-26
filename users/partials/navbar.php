<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek status login dan role user
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']);
$user_type = $is_logged_in ? ($_SESSION['user_type'] ?? '') : '';
$is_pegawai_dosen = ($user_type == 'pegawai' || $user_type == 'dosen');
$is_dosen = ($user_type === 'dosen');
$user_email = $is_logged_in ? $_SESSION['email'] : '';
$username = $is_logged_in ? explode('@', $user_email)[0] : '';

//CEK KELENGKAPAN DATA PEGAWAI
$data_complete = true;
$completion_message = '';

if ($is_pegawai_dosen && isset($_SESSION['pegawai_id'])) {
    // Include helper untuk cek kelengkapan
    require_once __DIR__ . '/../../config/check_completion.php';
    require_once __DIR__ . '/../../config/database.php';
    
    $check_result = checkPegawaiCompletion($conn, $_SESSION['pegawai_id']);
    $data_complete = $check_result['is_complete'];
    $completion_message = addslashes($check_result['message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Politeknik NEST' ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
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
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
        }

        .navbar-custom {
            background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);
            padding: 12px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-container {
            max-width: 1400px;
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
            object-fit: contain;
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
            align-items: center;
        }

        .navbar-menu li {
            position: relative;
        }

        .navbar-menu li a {
            color: white;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
            padding: 5px 0;
            display: block;
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

        /* Dropdown Layanan */
        .dropdown-layanan {
            position: relative;
        }

        .dropdown-layanan > a {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            color: white !important;
        }

        .dropdown-layanan > a i {
            font-size: 12px;
            transition: transform 0.3s;
        }

        .dropdown-layanan:hover > a i {
            transform: rotate(180deg);
        }

        .dropdown-layanan-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);
            border-radius: 12px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            min-width: 300px;
            overflow: hidden;
            margin-top: 15px;
            z-index: 1000;
        }

        .dropdown-layanan.active .dropdown-layanan-menu {
            display: block;
            animation: dropFadeLayanan 0.25s ease-out;
        }

        @keyframes dropFadeLayanan {
            from { 
                opacity: 0; 
                transform: translateY(-12px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }

        .dropdown-layanan-item {
            padding: 13px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: white;
            font-size: 14px;
            font-weight: 500;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-layanan-item:last-child {
            border-bottom: none;
        }

        .dropdown-layanan-item:hover {
            background: rgba(255, 255, 255, 0.2);
            padding-left: 24px;
        }

        .dropdown-layanan-item i {
            font-size: 18px;
            width: 22px;
            color: white;
            transition: all 0.2s;
        }

        .dropdown-layanan-item:hover i {
            transform: scale(1.1);
        }

        /* Style untuk menu yang terkunci */
        .dropdown-layanan-item.locked {
            opacity: 0.7;
            cursor: not-allowed;
            position: relative;
        }

        .dropdown-layanan-item.locked:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 20px;
        }

        /* Login Button */
        .btn-login {
            background: white;
            color: #F19BB8 !important;
            padding: 10px 30px;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
            text-decoration: none !important;
            display: inline-block;
        }

        .btn-login:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 255, 255, 0.4);
            color: #F6C35A !important;
        }

        .btn-login:active {
            transform: translateY(0);
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

        /* Dropdown Menu Profil */
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

        /* Hide mobile login on desktop */
        .mobile-login-item {
            display: none;
        }

        /* Responsive */
        @media (max-width: 968px) {
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
                align-items: stretch;
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
            
            /* Show mobile login item */
            .mobile-login-item {
                display: block;
            }
            
            /* Style login button in mobile menu */
            .mobile-login-item .btn-login {
                width: 100%;
                text-align: center;
                margin-top: 10px;
                display: block;
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

            /* Mobile Dropdown Layanan */
            .dropdown-layanan-menu {
                position: static;
                box-shadow: none;
                margin-top: 10px;
                border-radius: 8px;
                display: none;
            }

            .dropdown-layanan.active .dropdown-layanan-menu {
                display: block;
            }

            .dropdown-layanan > a {
                padding: 12px 0;
            }
        }

        /* Custom SweetAlert2 Styling */
        .swal2-popup {
            border-radius: 15px;
            font-family: 'Poppins', sans-serif;
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

        /* Desktop only */
        .desktop-login {
            display: inline-block;
        }

        /* Mobile only */
        @media (max-width: 968px) {
            .desktop-login {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-container">
        <!-- Logo -->
        <a href="<?php echo BASE_URL; ?>index.php" class="navbar-brand">
            <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo Politeknik Nest" class="navbar-logo">
            <h1 class="navbar-title">POLITEKNIK NEST</h1>
        </a>

        <!-- Mobile Toggle -->
        <button class="mobile-toggle" onclick="toggleMobileMenu()">
            <i class="bi bi-list"></i>
        </button>

        <!-- Right Section -->
        <div class="navbar-right" id="navbarRight">
            <!-- Navigation Menu -->
            <ul class="navbar-menu" id="navbarMenu">
                <li><a href="<?php echo BASE_URL; ?>index.php">Beranda</a></li>
                <li><a href="<?php echo BASE_URL; ?>users/staff.php">Tim Kami</a></li>
                
                <?php if ($is_pegawai_dosen): ?>
                    <!-- Kalau Pegawai/Dosen: Tampilkan Dropdown Layanan -->
                    <li class="dropdown-layanan" id="dropdownLayanan">
                        <a href="javascript:void(0)" onclick="toggleLayanan(event)">
                            Layanan <i class="bi bi-chevron-down"></i>
                        </a>

                        <div class="dropdown-layanan-menu">
                            <!-- Administrasi Kepegawaian - SELALU BISA DIAKSES -->
                            <a href="<?php echo BASE_URL; ?>users/pegawai/administrasi.php" class="dropdown-layanan-item">
                                <i class="bi bi-file-earmark-text-fill"></i>
                                <span>Administrasi Kepegawaian</span>
                            </a>
                            
                            <!-- Pengembangan SDM - DIKUNCI jika data belum lengkap -->
                            <a href="<?php echo $data_complete ? BASE_URL . 'users/pegawai/pengembangan_sdm.php' : 'javascript:void(0)'; ?>" 
                               class="dropdown-layanan-item <?php echo !$data_complete ? 'locked' : ''; ?>"
                               <?php if (!$data_complete): ?>
                               onclick="showIncompleteAlert(event)"
                               <?php endif; ?>>
                                <i class="bi bi-graph-up-arrow"></i>
                                <span>Pengembangan SDM</span>
                            </a>
                            
                            <!-- Sertifikasi Dosen - KHUSUS DOSEN + DIKUNCI jika data belum lengkap -->
                            <?php if ($is_dosen): ?>
                            <a href="<?php echo $data_complete ? BASE_URL . 'users/pegawai/sertifikasi_dosen.php' : 'javascript:void(0)'; ?>" 
                               class="dropdown-layanan-item <?php echo !$data_complete ? 'locked' : ''; ?>"
                               <?php if (!$data_complete): ?>
                               onclick="showIncompleteAlert(event)"
                               <?php endif; ?>>
                                <i class="bi bi-award-fill"></i>
                                <span>Sertifikasi Dosen</span>
                            </a>
                            <?php endif; ?>
                            
                            <!-- Penilaian dan Kinerja Pegawai - DIKUNCI jika data belum lengkap -->
                            <a href="<?php echo $data_complete ? BASE_URL . 'users/pegawai/penilaian/penilaian_kinerja.php' : 'javascript:void(0)'; ?>" 
                               class="dropdown-layanan-item <?php echo !$data_complete ? 'locked' : ''; ?>"
                               <?php if (!$data_complete): ?>
                               onclick="showIncompleteAlert(event)"
                               <?php endif; ?>>
                                <i class="bi bi-clipboard-check-fill"></i>
                                <span>Penilaian dan Kinerja Pegawai</span>
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- Kalau Belum Login / Pelamar: Tampilkan Lowongan Pekerjaan -->
                    <li><a href="<?php echo BASE_URL; ?>users/pelamar/dashboard.php">Lowongan Pekerjaan</a></li>
                <?php endif; ?>
                
                <!-- Mobile Login Item -->
                <?php if (!$is_pegawai_dosen): ?>
                <li class="mobile-login-item">
                    <a href="<?php echo BASE_URL; ?>auth/login_pegawai.php" class="btn-login">
                        Login Pegawai
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right Side: Login Button or Profile Icon -->
            <?php if ($is_pegawai_dosen): ?>
                <!-- Kalau Pegawai/Dosen: Tampilkan Icon Profil -->
                <div class="user-dropdown">
                    <div class="user-icon-btn" onclick="toggleUserDropdown()">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="dropdown-menu-custom" id="userDropdown">
                        <a href="<?php echo BASE_URL; ?>users/pegawai/profil.php" class="dropdown-item-custom">
                            <i class="bi bi-person-fill"></i>
                            <span>Profil Saya</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>users/pegawai/keamanan_akun.php" class="dropdown-item-custom">
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
                <!-- Kalau Belum Login / Pelamar: Tampilkan Tombol Login - Desktop Only -->
                <a href="<?php echo BASE_URL; ?>auth/login_pegawai.php" class="btn-login desktop-login">
                    Login Pegawai
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<script>
    // Toggle Mobile Menu
    function toggleMobileMenu() {
        const navbarRight = document.getElementById('navbarRight');
        const toggle = document.querySelector('.mobile-toggle i');
        
        navbarRight.classList.toggle('show');
        
        // Change icon
        if (navbarRight.classList.contains('show')) {
            toggle.className = 'bi bi-x-lg';
        } else {
            toggle.className = 'bi bi-list';
        }
    }

    // Toggle User Dropdown
    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        dropdown.classList.toggle('show');
    }

    // Confirm Logout with SweetAlert
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
                // Tampilkan loading
                Swal.fire({
                    title: 'Logging out...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Redirect ke halaman logout
                window.location.href = '<?php echo BASE_URL; ?>auth/logout.php';
            }
        });
    }

    // Close dropdown when click outside
    document.addEventListener('click', function(event) {
        const userDropdown = document.querySelector('.user-dropdown');
        const dropdown = document.getElementById('userDropdown');
        if (userDropdown && dropdown && !userDropdown.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });

    function toggleLayanan(event) {
        event.preventDefault();
        event.stopPropagation();

        const dropdown = document.getElementById('dropdownLayanan');
        dropdown.classList.toggle('active');
    }

    // Tutup dropdown layanan kalau klik di luar
    document.addEventListener('click', function (event) {
        const layanan = document.getElementById('dropdownLayanan');
        if (layanan && !layanan.contains(event.target)) {
            layanan.classList.remove('active');
        }
    });

    //ALERT UNTUK DATA BELUM LENGKAP
    function showIncompleteAlert(event) {
        event.preventDefault();
        event.stopPropagation();
        
        Swal.fire({
            icon: 'warning',
            title: 'Data Belum Lengkap!',
            html: '<?php echo $completion_message; ?>',
            showCancelButton: false,
            confirmButtonText: 'Lengkapi Data Sekarang',
            confirmButtonColor: '#F6C35A',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?php echo BASE_URL; ?>users/pegawai/administrasi.php';
            }
        });
    }
</script>

</body>
</html>