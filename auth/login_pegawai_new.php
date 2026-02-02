<?php
/**
 * LOGIN PEGAWAI - AUTO FILL TOKEN
 * File: auth/login_pegawai.php
 * 
 * FITUR:
 * - Pegawai input email saja
 * - System auto-ambil token dari database
 * - Token otomatis terisi di form (readonly)
 * - Setelah ganti password, token hilang
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if (in_array($_SESSION['user_type'], ['pegawai', 'dosen'])) {
        header('Location: ../users/pegawai/administrasi.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'admin') {
        header('Location: ../users/admin/dashboard.php');
        exit();
    }
}

$error = '';
$token_pegawai_baru = '';
$is_pegawai_baru = false;
$email_check = '';

// CEK APAKAH USER INPUT EMAIL (AJAX REQUEST)
if (isset($_POST['check_email'])) {
    header('Content-Type: application/json');
    
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT u.token, u.password_changed, u.user_type
            FROM users u
            WHERE u.email = :email
              AND u.is_active = 1
              AND u.user_type IN ('pegawai', 'dosen')
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['password_changed'] == 0 && !empty($user['token'])) {
            // Pegawai baru - ada token
            echo json_encode([
                'success' => true,
                'is_pegawai_baru' => true,
                'token' => $user['token']
            ]);
        } else {
            // Pegawai aktif - pakai password
            echo json_encode([
                'success' => true,
                'is_pegawai_baru' => false
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false]);
    }
    exit;
}

// PROSES LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['check_email'])) {
    $input_email = trim($_POST['email'] ?? '');
    $input = trim($_POST['password'] ?? ''); // bisa password / token

    if ($input_email === '') {
        $error = "Email harus diisi!";
    } else {
        $stmt = $conn->prepare("
            SELECT u.*, p.pegawai_id, p.nama_lengkap
            FROM users u
            LEFT JOIN pegawai p ON u.user_id = p.user_id
            WHERE u.email = ?
              AND u.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$input_email]);
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

                header('Location: ../users/admin/dashboard.php');
                exit;
            }
        }
        // ================= PEGAWAI / DOSEN =================
        elseif (in_array($user['user_type'], ['pegawai', 'dosen'])) {
            
            // ===== PEGAWAI BARU (belum ganti password, ada token) =====
            if ($user['password_changed'] == 0 && !empty($user['token'])) {
                
                if ($input === '') {
                    $error = "Token login wajib diisi!";
                }
                elseif ($input !== $user['token']) {
                    $error = "Token login salah! Token otomatis terisi dari database.";
                }
                else {
                    // Token valid - redirect to change password page
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['pegawai_id'] = $user['pegawai_id'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['first_login'] = true;

                    header('Location: ../users/pegawai/keamanan.php?first_login=1');
                    exit;
                }
            }
            
            // ===== PEGAWAI AKTIF (sudah pernah login & ganti password) =====
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
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];

                    // Set remember me cookie if checked
                    if (isset($_POST['remember'])) {
                        setcookie('remember_email', $input_email, time() + (86400 * 30), '/');
                    } else {
                        setcookie('remember_email', '', time() - 3600, '/');
                    }

                    header('Location: ../users/pegawai/administrasi.php');
                    exit;
                }
            }
        }
        else {
            $error = "Anda tidak memiliki akses ke portal pegawai.";
        }
    }
}

$page_title = 'Login Pegawai - Politeknik NEST';
include '../users/partials/navbar.php';
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
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
    }

    .info-box h4 {
        color: #1565c0;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-box p {
        color: #1565c0;
        font-size: 13px;
        line-height: 1.6;
        margin: 0;
    }

    .info-box.success {
        background: #e8f5e9;
        border-left-color: #10b981;
    }

    .info-box.success h4,
    .info-box.success p {
        color: #2e7d32;
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

    .form-control:read-only {
        background: #f0fdf4;
        border-color: #86efac;
        color: #065f46;
        cursor: default;
    }

    .token-input {
        font-family: 'Courier New', monospace;
        font-size: 18px;
        font-weight: 600;
        letter-spacing: 2px;
        text-align: center;
        text-transform: uppercase;
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-submit:hover {
        background: #0b3d91;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
    }

    .btn-submit.activation {
        background: linear-gradient(135deg, #10b981, #34d399);
    }

    .btn-submit.activation:hover {
        background: linear-gradient(135deg, #059669, #10b981);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
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
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
        position: relative;
    }

    .divider::before,
    .divider::after {
        content: '';
        position: absolute;
        top: 50%;
        width: 45%;
        height: 1px;
        background: #e0e0e0;
    }

    .divider::before {
        left: 0;
    }

    .divider::after {
        right: 0;
    }

    /* HIDDEN STATE */
    .hidden {
        display: none !important;
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

                <!-- INFO BOX - DYNAMIC -->
                <div class="info-box" id="infoNormal">
                    <h4>
                        <i class="bi bi-info-circle-fill"></i>
                        Informasi Login
                    </h4>
                    <p>
                        Masukkan email Anda untuk melanjutkan. System akan otomatis mendeteksi apakah Anda pegawai baru atau pegawai aktif.
                    </p>
                </div>

                <div class="info-box success hidden" id="infoPegawaiBaru">
                    <h4>
                        <i class="bi bi-shield-check"></i>
                        Pegawai Baru Terdeteksi
                    </h4>
                    <p>
                        Selamat! Anda adalah pegawai baru. Token aktivasi telah otomatis terisi. Klik tombol <strong>"Aktivasi Akun"</strong> untuk melanjutkan ke pengaturan password.
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

                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="bi bi-envelope-fill"></i> Email
                        </label>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               id="emailInput"
                               placeholder="Masukkan email Anda"
                               value="<?= isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : '' ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="bi bi-shield-lock-fill"></i> 
                            <span id="passwordLabel">Password</span>
                        </label>
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               id="passwordInput"
                               placeholder="Masukkan password Anda" 
                               required>
                        <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;" id="passwordHint">
                            <i class="bi bi-info-circle"></i> Masukkan password Anda
                        </small>
                    </div>

                    <!-- REMEMBER & FORGOT PASSWORD - HIDE FOR PEGAWAI BARU -->
                    <div class="form-footer" id="formFooter">
                        <div class="remember-group">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Ingat Saya</label>
                        </div>
                        <a href="lupa-password.php" class="forgot-password-link">
                            <i class="bi bi-key-fill"></i> Lupa Password?
                        </a>
                    </div>

                    <button type="submit" class="btn-submit" id="btnSubmit">
                        <i class="bi bi-box-arrow-in-right"></i> 
                        <span id="btnText">Masuk</span>
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

    <script>
        const emailInput = document.getElementById('emailInput');
        const passwordInput = document.getElementById('passwordInput');
        const passwordLabel = document.getElementById('passwordLabel');
        const passwordHint = document.getElementById('passwordHint');
        const formFooter = document.getElementById('formFooter');
        const btnSubmit = document.getElementById('btnSubmit');
        const btnText = document.getElementById('btnText');
        const infoNormal = document.getElementById('infoNormal');
        const infoPegawaiBaru = document.getElementById('infoPegawaiBaru');
        
        let isPegawaiBaru = false;
        let currentToken = '';

        // AUTO CHECK EMAIL SAAT BLUR
        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            
            if (email && email.includes('@')) {
                checkEmail(email);
            }
        });

        function checkEmail(email) {
            // AJAX request ke server
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'check_email=1&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.is_pegawai_baru) {
                        // PEGAWAI BARU - SHOW TOKEN
                        showPegawaiBaru(data.token);
                    } else {
                        // PEGAWAI AKTIF - NORMAL
                        showPegawaiAktif();
                    }
                } else {
                    showPegawaiAktif();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showPegawaiAktif();
            });
        }

        function showPegawaiBaru(token) {
            isPegawaiBaru = true;
            currentToken = token;
            
            // Update UI
            infoNormal.classList.add('hidden');
            infoPegawaiBaru.classList.remove('hidden');
            
            passwordLabel.textContent = 'Token Aktivasi';
            passwordInput.type = 'text';
            passwordInput.value = token;
            passwordInput.readOnly = true;
            passwordInput.classList.add('token-input');
            
            passwordHint.innerHTML = '<i class="bi bi-check-circle-fill" style="color: #10b981;"></i> Token otomatis terisi dari database';
            passwordHint.style.color = '#10b981';
            
            formFooter.classList.add('hidden');
            
            btnSubmit.classList.add('activation');
            btnSubmit.querySelector('i').className = 'bi bi-unlock-fill';
            btnText.textContent = 'Aktivasi Akun';
        }

        function showPegawaiAktif() {
            isPegawaiBaru = false;
            currentToken = '';
            
            // Reset UI
            infoNormal.classList.remove('hidden');
            infoPegawaiBaru.classList.add('hidden');
            
            passwordLabel.textContent = 'Password';
            passwordInput.type = 'password';
            passwordInput.value = '';
            passwordInput.readOnly = false;
            passwordInput.classList.remove('token-input');
            
            passwordHint.innerHTML = '<i class="bi bi-info-circle"></i> Masukkan password Anda';
            passwordHint.style.color = '#666';
            
            formFooter.classList.remove('hidden');
            
            btnSubmit.classList.remove('activation');
            btnSubmit.querySelector('i').className = 'bi bi-box-arrow-in-right';
            btnText.textContent = 'Masuk';
        }
    </script>
</body>
</html>