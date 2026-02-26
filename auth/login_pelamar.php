<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once '../config/database.php';


if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'pelamar') {
    header('Location: ../users/pelamar/dashboard.php');
    exit;
}

// Handle login form
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        try {
            // Query cari user
            $query = "SELECT * FROM users WHERE email = :email AND user_type = 'pelamar' AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Cek password
            if ($user && password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Remember me
                if (isset($_POST['remember'])) {
                    setcookie('remember_email', $email, time() + (86400 * 30), '/');
                }
                
                // Force session save
                session_write_close();
                session_start();
                
                // Redirect
                $redirect = $_GET['redirect'] ?? '';
                $lowongan_id = $_GET['lowongan_id'] ?? '';
                
                if ($redirect == 'apply' && !empty($lowongan_id)) {
                    header('Location: ../users/pelamar/lamaran.php?lowongan_id=' . $lowongan_id);
                } else {
                    header('Location: ../users/pelamar/dashboard.php');
                }
                exit;
            } else {
                $error = "Email atau password salah!";
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

$page_title = 'Login Pelamar - Politeknik NEST';
include '../users/partials/navbar_req.php';
?>
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
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
        
        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: calc(100vh - 80px);
            background: #f5f5f5;
        }
        
        .login-image {
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), 
                        url('<?php echo BASE_URL; ?>users/assets/nest.jpg') center/cover;
        }
        
        .login-form-container {
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
        }
        
        .login-form-wrapper {
            width: 100%;
            max-width: 450px;
        }
        
        .form-logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .form-logo img {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
        }
        
        .form-title {
            font-size: 32px;
            color: #1e3a5f;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .form-subtitle {
            color: #546e7a;
            font-size: 14px;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            color: #1e3a5f;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #0d47a1;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c62828;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        
        .error-message i {
            margin-top: 2px;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #2e7d32;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .remember-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .remember-group label {
            color: #0d47a1;
            font-size: 14px;
            cursor: pointer;
            user-select: none;
        }
        
        .forgot-password-link {
            color: #0d47a1;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .forgot-password-link:hover {
            color: #1976d2;
            text-decoration: underline;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #0d47a1;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            background: #0b3d91;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #546e7a;
            font-size: 14px;
        }
        
        .register-link a {
            color: #0d47a1;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .register-link a:hover {
            color: #1976d2;
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #9e9e9e;
            font-size: 13px;
        }
        
        .login-links {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-links a {
            color: #0d47a1;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            display: inline-block;
            margin: 5px 0;
        }
        
        .login-links a:hover {
            color: #1976d2;
            text-decoration: underline;
        }
        
        @media (max-width: 968px) {
            .login-container { 
                grid-template-columns: 1fr; 
            }
            
            .login-image { 
                display: none; 
            }
            
            .form-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image"></div>
        <div class="login-form-container">
            <div class="login-form-wrapper">
                <div class="form-logo">
                    <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo">
                    <h1 class="form-title">Selamat Datang</h1>
                    <p class="form-subtitle">Sistem Manajemen Sumber Daya Manusia<br>Politeknik NEST</p>
                </div>

                <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout'])): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i> 
                    <span>Anda telah berhasil logout.</span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['reset_success'])): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Password berhasil direset! Silakan login dengan password baru Anda.</span>
                </div>
                <?php endif; ?>

                <?php if (isset($_GET['registered'])): ?>
                <div class="success-message">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>Registrasi berhasil! Silakan login untuk melanjutkan.</span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Alamat Email</label>
                        <input type="email" class="form-control" name="email" 
                               placeholder="Masukkan email Anda" 
                               value="<?= isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '' ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" 
                               placeholder="Masukkan password Anda" required>
                    </div>

                    <div class="form-footer">
                        <div class="remember-group">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Ingat Saya</label>
                        </div>
                        <a href="lupa-password-pelamar.php" class="forgot-password-link">
                            <i class="bi bi-key-fill"></i> Lupa Password?
                        </a>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk Akun
                    </button>
                </form>

                <div class="divider">atau</div>

                <div class="register-link">
                    Belum Punya Akun? <a href="register_pelamar.php">Klik Disini</a>
                </div>

                <div class="login-links">
                    <a href="login_pegawai.php">
                        <i class="bi bi-person-badge-fill"></i> Login sebagai Pegawai
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>