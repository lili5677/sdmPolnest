<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Politeknik NEST' ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }

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

        /* Right Side Menu & Login */
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 35px;
        }

        /* Logo Section */
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

        .btn-login:active {
            transform: translateY(0);
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
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar-custom">
        <div class="navbar-container">
            <!-- Logo -->
            <a href="<?php echo BASE_URL; ?>users/dashboard.php" class="navbar-brand">
                <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo Politeknik Nest" class="navbar-logo">
                <h1 class="navbar-title">POLITEKNIK NEST</h1>
            </a>

            <!-- Mobile Toggle -->
            <button class="mobile-toggle" onclick="toggleMobileMenu()">
                <i class="bi bi-list"></i>
            </button>

            <!-- Right Side: Menu + Login Button -->
            <div class="navbar-right" id="navbarRight">
                <!-- Navigation Menu -->
                <ul class="navbar-menu" id="navbarMenu">
                    <li><a href="../pelamar/lowongan.php">Lowongan Pekerjaan</a></li>
                    <li><a href="../pelamar/tracking_lamaran.php">Tracking Lamaran</a></li>
                </ul>

                <!-- Login Button -->
                <a href="../../auth/login_pegawai.php" class="btn-login">
                    Daftar/Login
                </a>
            </div>
        </div>
    </nav>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>

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
    </script>