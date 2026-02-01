<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email'] ?? '');
    $input    = trim($_POST['password'] ?? ''); // bisa password / token

    if ($email === '') {
        $error = "Email harus diisi!";
    } else {

        $stmt = $conn->prepare("
            SELECT u.*, p.pegawai_id, p.is_pegawai_lama
            FROM users u
            LEFT JOIN pegawai p ON u.user_id = p.user_id
            WHERE u.email = ?
              AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Email tidak ditemukan atau akun tidak aktif!";
        }

        // ================= ADMIN =================
        elseif ($user['user_type'] === 'admin') {

            if (!password_verify($input, $user['password'])) {
                $error = "Password salah!";
            } else {

                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['user_type'] = 'admin';

                header('Location: ../admin/index.php');
                exit;
            }
        }

        // ================= PEGAWAI / DOSEN =================
        elseif (in_array($user['user_type'], ['pegawai', 'dosen'])) {

            // ===== PEGAWAI LAMA =====
            if ($user['is_pegawai_lama'] == 1 && $user['password_changed'] == 0) {
                
                if ($input === '') {
                    $error = "Token login wajib diisi!";
                }
                elseif ($input !== $user['token']) {
                    $error = "Token login salah!";
                }
                else {

                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['pegawai_id'] = $user['pegawai_id'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['first_login'] = true;

                    header('Location: ../users/pegawai/keamanan.php?first_login=1');
                    exit;
                }
            }

            // ===== PEGAWAI AKTIF =====
            else {

                if ($input === '') {
                    $error = "Password harus diisi!";
                }
                elseif (!password_verify($input, $user['password'])) {
                    $error = "Password salah!";
                }
                else {

                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['pegawai_id'] = $user['pegawai_id'];
                    $_SESSION['email']      = $user['email'];
                    $_SESSION['user_type']  = $user['user_type'];

                    header('Location: ../users/pegawai/administrasi.php');
                    exit;
                }
            }
        }

        else {
            $error = "Anda tidak memiliki akses.";
        }
    }
}

$page_title = 'Login Pegawai - Politeknik NEST';
include '../users/partials/navbar.php';
?>
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
    }

    .login-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: calc(100vh - 80px);
        background: #f5f5f5;
    }

    .login-image {
        background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.3)),
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

    .info-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
    }

    .info-box h4 {
        color: #856404;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-box p {
        color: #856404;
        font-size: 13px;
        line-height: 1.6;
        margin: 0;
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

    .divider {
        text-align: center;
        margin: 20px 0;
        color: #9e9e9e;
        font-size: 13px;
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
                    <h1 class="form-title">Portal Pegawai</h1>
                    <p class="form-subtitle">Sistem Manajemen Sumber Daya Manusia<br>Politeknik NEST</p>
                </div>

                <div class="info-box">
                    <h4>
                        <i class="bi bi-info-circle-fill"></i>
                        Informasi Login
                    </h4>
                    <p>
                        Portal ini khusus untuk <strong>pegawai</strong> Politeknik NEST. 
                        Jika Anda adalah pelamar yang sudah diterima, Anda dapat login di sini untuk aktivasi akun pegawai.
                    </p>
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
                        <span>Anda telah berhasil logout</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['activated'])): ?>
                    <div class="success-message">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Akun berhasil diaktivasi! Silakan login dengan password baru Anda.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['reset_success'])): ?>
                    <div class="success-message">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Password berhasil direset! Silakan login dengan password baru Anda.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" placeholder="Masukkan email Anda"
                            value="<?= isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '' ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Password / Token Login" required>
                    </div>

                    <div class="form-footer">
                        <div class="remember-group">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Ingat Saya</label>
                        </div>
                        <a href="lupa-password.php" class="forgot-password-link">
                            <i class="bi bi-key-fill"></i> Lupa Password?
                        </a>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="bi bi-box-arrow-in-right"></i> Masuk
                    </button>
                </form>

                <div class="divider">atau</div>

                <div class="login-links">
                    <a href="login_pelamar.php">
                        <i class="bi bi-person-fill"></i> Login sebagai Pelamar
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>