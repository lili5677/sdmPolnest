<?php
/**
 * LOGIN PEGAWAI - SMART VERSION
 * File: auth/login_pegawai.php
 * 
 * LOGIC:
 * 1. Pelamar biasa → TOLAK dengan pesan "Bukan pegawai"
 * 2. Pelamar DITERIMA → BOLEH login → Auto redirect ke aktivasi
 * 3. Pegawai aktif → Login normal
 */

// STEP 1: Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// STEP 2: Database connection
require_once '../config/database.php';

// STEP 3: Kalau sudah login sebagai pegawai/dosen, langsung ke dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] == 'pegawai' || $_SESSION['user_type'] == 'dosen') {
        header('Location: ../users/pegawai/dashboard.php');
        exit;
    }
}

// STEP 4: Handle login form
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Email dan password harus diisi!";
    } else {
        try {
            // Query cek user + status pelamar
            $query = "SELECT u.*, 
                             p.pelamar_id, 
                             p.nama_lengkap,
                             l.lamaran_id,
                             l.status_lamaran,
                             at.token,
                             at.is_used,
                             at.expired_at,
                             at.role,
                             at.nidn,
                             at.prodi,
                             at.nip
                      FROM users u
                      LEFT JOIN pelamar p ON u.user_id = p.user_id
                      LEFT JOIN lamaran l ON p.pelamar_id = l.pelamar_id AND l.status_lamaran = 'diterima'
                      LEFT JOIN activation_tokens at ON p.pelamar_id = at.pelamar_id
                      WHERE u.email = :email 
                      AND u.is_active = 1";
            
            $stmt = $conn->prepare($query);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Validasi user exists
            if (!$user) {
                $error = "Email tidak ditemukan atau akun tidak aktif!";
            } else {
                // CASE 1: User adalah PEGAWAI atau DOSEN (sudah aktif)
                if ($user['user_type'] == 'pegawai' || $user['user_type'] == 'dosen') {
                    // Cek password
                    if (password_verify($password, $user['password'])) {
                        // LOGIN BERHASIL sebagai pegawai/dosen
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['logged_in'] = true;
                        $_SESSION['login_time'] = time();

                        // Remember me
                        if (isset($_POST['remember'])) {
                            setcookie('remember_email', $email, time() + (86400 * 30), '/');
                        }

                        session_write_close();
                        session_start();

                        header('Location: ../users/pegawai/dashboard.php');
                        exit;
                    } else {
                        $error = "Password salah!";
                    }
                }
                // CASE 2: User adalah PELAMAR
                elseif ($user['user_type'] == 'pelamar') {
                    // Cek apakah pelamar SUDAH DITERIMA
                    if ($user['status_lamaran'] == 'diterima' && !empty($user['token'])) {
                        // PELAMAR DITERIMA - BOLEH LOGIN!
                        
                        // Cek password (untuk pelamar yang sudah pernah login)
                        if (password_verify($password, $user['password'])) {
                            // Password match - set session
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['user_type'] = 'pelamar';
                            $_SESSION['pelamar_id'] = $user['pelamar_id'];
                            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                            $_SESSION['logged_in'] = true;
                            $_SESSION['login_time'] = time();
                            $_SESSION['is_first_login'] = true;

                            // Remember me
                            if (isset($_POST['remember'])) {
                                setcookie('remember_email', $email, time() + (86400 * 30), '/');
                            }

                            session_write_close();
                            session_start();

                            // Redirect ke halaman aktivasi pegawai
                            header('Location: ../users/pelamar/aktivasi_pegawai.php');
                            exit;
                        } else {
                            $error = "Password salah!";
                        }
                    } else {
                        // PELAMAR BIASA (belum diterima) - TOLAK!
                        $error = "Anda bukan pegawai di Politeknik NEST. Akun Anda masih berstatus pelamar.";
                    }
                }
                // CASE 3: User type lain (admin, dll)
                else {
                    $error = "Anda tidak memiliki akses ke halaman pegawai. Silakan login di halaman yang sesuai.";
                }
            }
        } catch (Exception $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
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

    .remember-group {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
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
                        Portal ini khusus untuk <strong>pegawai dan dosen</strong> Politeknik NEST. 
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
                        <span>Anda telah berhasil logout.</span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['activated'])): ?>
                    <div class="success-message">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Akun berhasil diaktivasi! Silakan login dengan password baru Anda.</span>
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
                        <input type="password" class="form-control" name="password" placeholder="Masukkan password Anda"
                            required>
                    </div>

                    <div class="remember-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Ingat Saya</label>
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