<?php
/**
 * LOGIN PELAMAR - DENGAN INCLUDE NAVBAR & FOOTER
 * File: auth/login_pelamar.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'pelamar') {
    header('Location: ../users/pelamar/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        try {
            $query = "SELECT * FROM users 
                      WHERE email = :email 
                      AND user_type = 'pelamar' 
                      AND is_active = 1
                      LIMIT 1";
            $stmt = $conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($password, $user['password'])) {
                $error = "Email atau password salah!";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                if (isset($_POST['remember'])) {
                    setcookie('remember_email', $email, time() + (86400 * 30), '/');
                }
                
                session_write_close();
                session_start();
                
                $redirect = $_GET['redirect'] ?? '';
                $lowongan_id = $_GET['lowongan_id'] ?? '';
                
                if ($redirect == 'apply' && !empty($lowongan_id)) {
                    header('Location: ../users/pelamar/lamaran.php?lowongan_id=' . $lowongan_id);
                } else {
                    header('Location: ../users/pelamar/dashboard.php');
                }
                exit;
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

$page_title = 'Login - Politeknik NEST';
include '../users/partials/navbar_req.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        font-family: 'Poppins', sans-serif;
    }

    /* MAIN CONTENT */
    .main-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: calc(100vh - 80px);
    }

    .left-section {
        background-image: url('<?php echo BASE_URL; ?>users/assets/nest.jpg');
        background-size: cover;
        background-position: center;
        position: relative;
    }

    .left-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.3);
    }

    .right-section {
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
    }

    .login-form {
        width: 100%;
        max-width: 450px;
    }

    .form-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .form-logo {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: #1e3a5f;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .form-logo img {
        width: 50px;
        height: 50px;
    }

    .form-title {
        font-size: 32px;
        font-weight: 700;
        color: #1e3a5f;
        margin-bottom: 10px;
    }

    .form-subtitle {
        font-size: 14px;
        color: #666;
        line-height: 1.6;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .form-control {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: #0d47a1;
        box-shadow: 0 0 0 3px rgba(13,71,161,0.1);
    }

    .error-message {
        background: #ffebee;
        color: #c62828;
        padding: 12px;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .success-message {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 12px;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-login {
        width: 100%;
        padding: 14px;
        background: #0d47a1;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-login:hover {
        background: #0b3d91;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13,71,161,0.3);
    }

    .form-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        margin-top: 15px;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 15px;
    }

    .checkbox-group input {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .checkbox-group label {
        font-size: 14px;
        color: #0d47a1;
        cursor: pointer;
    }

    .form-footer a {
        color: #0d47a1;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
    }

    .form-footer a:hover {
        text-decoration: underline;
    }

    @media (max-width: 968px) {
        .main-container {
            grid-template-columns: 1fr;
        }
        .left-section {
            display: none;
        }
    }
</style>
</head>
<body>
    <!-- MAIN CONTENT -->
    <div class="main-container">
        <div class="left-section"></div>
        
        <div class="right-section">
            <div class="login-form">
                <div class="form-header">
                    <div class="form-logo">
                        <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo">
                    </div>
                    <h1 class="form-title">Selamat Datang</h1>
                    <p class="form-subtitle">Sistem Manajemen Sumber Daya Manusia<br>Politeknik NEST</p>
                </div>

                <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered'])): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Registrasi berhasil! Silakan login.</span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout'])): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Anda telah berhasil logout.</span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Alamat Email</label>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               placeholder="Masukkan email Anda"
                               value="<?= isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '' ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               placeholder="Masukkan password Anda"
                               required>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Ingat Saya</label>
                    </div>

                    <button type="submit" class="btn-login">Masuk Akun</button>

                    <div class="form-footer">
                        <span style="color: #666;">Belum Punya Akun?</span>
                        <a href="register_pelamar.php">Klik Disini</a>
                    </div>
                </form>
            </div>
        </div>
    </div>