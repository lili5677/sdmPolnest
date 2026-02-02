<?php
session_start();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';

/* ==========================================
   INIT STEP
   ========================================== */
if (!isset($_SESSION['reset_step'])) {
    $_SESSION['reset_step'] = 'request';
}
$step = $_SESSION['reset_step'];

/* ======================================================
   STEP 1 — REQUEST RESET (Simpan ke Database)
   ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {

    // Reset flow setiap request baru
    unset(
        $_SESSION['reset_step'],
        $_SESSION['verified_user_id'],
        $_SESSION['reset_email']
    );

    $_SESSION['reset_step'] = 'request';

    $email = trim($_POST['email']);

    if ($email === '') {
        $error = "Email harus diisi!";
    } else {

        // Cek user
        $stmt = $conn->prepare("
            SELECT u.user_id, u.email, p.nama_lengkap
            FROM users u
            LEFT JOIN pegawai p ON u.user_id = p.user_id
            WHERE u.email = ?
              AND u.is_active = 1
              AND u.user_type IN ('pegawai','dosen')
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Email tidak terdaftar sebagai pegawai aktif.";
        } else {

            // Cek apakah sudah ada request pending dalam 1 jam terakhir
            $stmt = $conn->prepare("
                SELECT request_id 
                FROM password_reset_requests
                WHERE user_id = ? 
                  AND status = 'pending'
                  AND requested_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                LIMIT 1
            ");
            $stmt->execute([$user['user_id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $error = "Anda sudah mengajukan permintaan reset password. Silakan hubungi admin atau tunggu hingga request sebelumnya diproses. Jika sudah mendapatkan token dari admin, resfresh website";
            } else {

                // Insert request baru ke database
                $stmt = $conn->prepare("
                    INSERT INTO password_reset_requests 
                    (user_id, email, token, status, ip_address)
                    VALUES (?, ?, '', 'pending', ?)
                ");
                
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                $stmt->execute([
                    $user['user_id'], 
                    $user['email'],
                    $ip
                ]);

                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_step'] = 'verify';

                header("Location: lupa-password.php");
                exit;
            }
        }
    }
}

/* ======================================================
   STEP 2 — VERIFY TOKEN
   ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_token'])) {

    $token = strtoupper(trim($_POST['token']));
    $email = trim($_POST['email']);

    if ($token === '' || $email === '') {
        $error = "Email dan kode verifikasi wajib diisi!";
    } else {

        // Cek apakah token valid dan belum expired
        $stmt = $conn->prepare("
            SELECT user_id, reset_token_expires
            FROM users
            WHERE email = ?
              AND reset_token = ?
              AND reset_token_expires IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([$email, $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Kode verifikasi tidak valid. Pastikan Anda memasukkan kode yang benar.";
        } else {
            // Cek apakah token sudah expired
            if (strtotime($user['reset_token_expires']) < time()) {
                
                // Update status request menjadi expired
                $stmt = $conn->prepare("
                    UPDATE password_reset_requests
                    SET status = 'expired'
                    WHERE user_id = ? AND status = 'approved'
                ");
                $stmt->execute([$user['user_id']]);
                
                // Hapus token dari users table
                $stmt = $conn->prepare("
                    UPDATE users
                    SET reset_token = NULL,
                        reset_token_expires = NULL
                    WHERE user_id = ?
                ");
                $stmt->execute([$user['user_id']]);
                
                $error = "Token sudah kadaluarsa (lebih dari 30 menit). Silakan hubungi admin untuk mendapatkan token baru.";
                
            } else {
                // Token valid dan belum expired
                $_SESSION['verified_user_id'] = $user['user_id'];
                $_SESSION['reset_step']       = 'reset';

                header("Location: lupa-password.php");
                exit;
            }
        }
    }
}

/* ======================================================
   STEP 3 — RESET PASSWORD
   ====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {

    $new      = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['verified_user_id'] ?? 0;

    if ($new === '' || $confirm === '') {
        $error = "Password harus diisi!";
    } elseif (strlen($new) < 8) {
        $error = "Password minimal 8 karakter!";
    } elseif ($new !== $confirm) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif ($user_id === 0) {
        $error = "Sesi reset tidak valid.";
    } else {

        $hash = password_hash($new, PASSWORD_DEFAULT);

        // Update password & hapus token
        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?,
                password_changed = 1,
                reset_token = NULL,
                reset_token_expires = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([$hash, $user_id]);

        // Update status request menjadi 'completed'
        $stmt = $conn->prepare("
            UPDATE password_reset_requests
            SET status = 'completed'
            WHERE user_id = ? AND status = 'approved'
        ");
        $stmt->execute([$user_id]);

        // Bersihkan session
        session_unset();
        session_destroy();

        header("Location: login_pegawai.php?reset_success=1");
        exit;
    }
}

$page_title = 'Lupa Password - Politeknik NEST';
include '../users/partials/navbar.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f0f2f5;
        min-height: 100vh;
    }

    .reset-wrapper {
        min-height: calc(100vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        background: #f0f2f5;
        position: relative;
    }

    .reset-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        max-width: 500px;
        width: 100%;
        position: relative;
        z-index: 1;
        animation: slideUp 0.5s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .reset-header {
        background: white;
        padding: 40px 30px 20px;
        text-align: center;
        color: #1e3a5f;
    }

    .reset-header img {
        width: 100px;
        height: 100px;
        margin-bottom: 20px;
    }

    .reset-header h2 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 10px;
        letter-spacing: -0.5px;
        color: #1e3a5f;
    }

    .reset-header p {
        font-size: 15px;
        font-weight: 400;
        line-height: 1.5;
        margin: 0;
        color: #64748b;
    }

    .reset-body {
        padding: 35px 30px 30px;
    }

    /* Progress Steps */
    .progress-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        position: relative;
    }

    .progress-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 50px;
        right: 50px;
        height: 2px;
        background: #e2e8f0;
        z-index: 0;
    }

    .progress-step {
        flex: 1;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .step-circle {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #94a3b8;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-bottom: 10px;
        transition: all 0.3s;
        font-size: 18px;
    }

    .progress-step.active .step-circle {
        background: #1e3a5f;
        color: white;
        box-shadow: 0 4px 12px rgba(30, 58, 95, 0.3);
        transform: scale(1.05);
    }

    .progress-step.completed .step-circle {
        background: #1e3a5f;
        color: white;
    }

    .step-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }

    .progress-step.active .step-label {
        color: #1e3a5f;
        font-weight: 700;
    }

    /* Alert Messages */
    .alert {
        padding: 14px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .alert-error {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-left: 4px solid #dc2626;
        color: #991b1b;
    }

    .alert-success {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        border-left: 4px solid #059669;
        color: #065f46;
    }

    .alert-info {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-left: 4px solid #3b82f6;
        color: #1e40af;
    }

    .alert-warning {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-left: 4px solid #f59e0b;
        color: #92400e;
    }

    .alert i {
        font-size: 16px;
        margin-top: 1px;
    }

    /* Info Box */
    .info-box {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-left: 4px solid #f59e0b;
        padding: 16px 18px;
        border-radius: 10px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15);
    }

    .info-box h4 {
        color: #92400e;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-box p {
        color: #92400e;
        font-size: 13px;
        line-height: 1.6;
        margin: 0;
    }

    .info-box strong {
        font-weight: 600;
        color: #78350f;
    }

    /* Waiting Admin Box */
    .waiting-box {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 2px solid #f59e0b;
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        margin-bottom: 20px;
    }

    .waiting-box i {
        font-size: 48px;
        color: #f59e0b;
        margin-bottom: 15px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    .waiting-box h4 {
        color: #92400e;
        margin-bottom: 12px;
        font-size: 18px;
        font-weight: 700;
    }

    .waiting-box p {
        color: #92400e;
        font-size: 14px;
        margin: 8px 0;
        line-height: 1.6;
    }

    .waiting-box strong {
        color: #78350f;
        font-weight: 600;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        color: #1e293b;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        letter-spacing: -0.2px;
    }

    .form-control {
        width: 100%;
        padding: 13px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        font-family: 'Inter', sans-serif;
        transition: all 0.3s ease;
        background: #f8fafc;
    }

    .form-control:focus {
        outline: none;
        border-color: #0d47a1;
        background: white;
        box-shadow: 0 0 0 4px rgba(13, 71, 161, 0.1);
    }

    .form-control::placeholder {
        color: #94a3b8;
    }

    .form-hint {
        display: block;
        color: #64748b;
        font-size: 12px;
        margin-top: 6px;
        line-height: 1.4;
    }

    .form-hint i {
        margin-right: 4px;
    }

    /* Password Field */
    .password-wrapper {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #94a3b8;
        font-size: 16px;
        transition: color 0.3s;
        padding: 5px;
    }

    .toggle-password:hover {
        color: #0d47a1;
    }

    /* Buttons */
    .btn-primary {
        width: 100%;
        padding: 14px;
        background: #0d47a1;
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        letter-spacing: 0.3px;
    }

    .btn-primary:hover {
        background: #0b3d91;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(13, 71, 161, 0.4);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .btn-primary i {
        margin-right: 6px;
    }

    /* Back Link */
    .back-link {
        text-align: center;
        margin-top: 20px;
    }

    .back-link a {
        color: #0d47a1;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .back-link a:hover {
        color: #0b3d91;
        text-decoration: underline;
    }

    /* Token Expired Notice */
    .expired-notice {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-left: 4px solid #dc2626;
        padding: 16px 18px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .expired-notice h4 {
        color: #991b1b;
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .expired-notice p {
        color: #991b1b;
        font-size: 13px;
        line-height: 1.6;
        margin: 0;
    }

    .expired-notice ul {
        margin: 10px 0 0 20px;
        padding: 0;
    }

    .expired-notice li {
        color: #991b1b;
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 5px;
    }

    /* Responsive */
    @media (max-width: 576px) {
        .reset-wrapper {
            padding: 20px 15px;
        }

        .reset-card {
            border-radius: 15px;
        }

        .reset-header {
            padding: 30px 25px;
        }

        .reset-header h2 {
            font-size: 22px;
        }

        .reset-body {
            padding: 25px 20px 20px;
        }

        .progress-steps::before {
            left: 40px;
            right: 40px;
        }
    }
</style>

</head>
<body>
    <div class="reset-wrapper">
        <div class="reset-card">
            <div class="reset-header">
                <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo Politeknik NEST">
                <h2>Lupa Password</h2>
                <p>Reset password akun pegawai Anda</p>
            </div>

            <div class="reset-body">
                <!-- Progress Steps -->
                <div class="progress-steps">
                    <div class="progress-step <?php echo $step === 'request' ? 'active' : 'completed'; ?>">
                        <div class="step-circle">1</div>
                        <div class="step-label">Email</div>
                    </div>
                    <div class="progress-step <?php echo $step === 'verify' ? 'active' : ($step === 'reset' ? 'completed' : ''); ?>">
                        <div class="step-circle">2</div>
                        <div class="step-label">Verifikasi</div>
                    </div>
                    <div class="progress-step <?php echo $step === 'reset' ? 'active' : ''; ?>">
                        <div class="step-circle">3</div>
                        <div class="step-label">Reset</div>
                    </div>
                </div>

                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <!-- STEP 1: Request Reset -->
                <?php if ($step === 'request'): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Email Pegawai</label>
                            <input type="email" class="form-control" name="email" 
                                   placeholder="Masukkan email pegawai Anda" required>
                        </div>

                        <button type="submit" name="request_reset" class="btn-primary">
                            <i class="bi bi-send-fill"></i> Kirim Permintaan Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <!-- STEP 2: Verify Token -->
                <?php if ($step === 'verify'): ?>
                    <div class="waiting-box">
                        <i class=""></i>
                        <h4>Menunggu Persetujuan Admin</h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?></p>
                        <p style="margin-top: 15px;">
                            Silakan <strong>hubungi admin</strong> untuk mendapatkan token reset password.
                            Admin akan mengirimkan token ke WhatsApp Anda.
                        </p>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Setelah mendapat token dari admin, masukkan di form bawah ini.</span>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-clock-fill"></i>
                        <span><strong>Penting:</strong> Token hanya berlaku selama <strong>30 menit</strong>. Jika lebih dari 30 menit, Anda harus meminta token baru dari admin.</span>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="Masukkan email Anda"
                                   value="<?php echo htmlspecialchars($_SESSION['reset_email'] ?? ''); ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kode Token dari Admin</label>
                            <input type="text" name="token" class="form-control"
                                   placeholder="Contoh: A1B2C3" 
                                   style="text-transform: uppercase;"
                                   maxlength="6"
                                   required>
                            <small class="form-hint">
                                <i class="bi bi-clock"></i> Token berlaku selama 30 menit sejak admin men-generate
                            </small>
                        </div>

                        <button type="submit" name="verify_token" class="btn-primary">
                            <i class="bi bi-check-circle-fill"></i> Verifikasi Token
                        </button>
                    </form>
                <?php endif; ?>

                <!-- STEP 3: Reset Password -->
                <?php if ($step === 'reset'): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Token valid! Silakan buat password baru Anda.</span>
                    </div>

                    <form method="POST" action="" id="resetForm">
                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" 
                                       id="newPassword"
                                       name="new_password" 
                                       placeholder="Masukkan password baru" 
                                       minlength="8"
                                       required>
                                <span class="toggle-password" onclick="togglePassword('newPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                            <small class="form-hint">
                                <i class="bi bi-info-circle"></i> Minimal 8 karakter
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password</label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" 
                                       id="confirmPassword"
                                       name="confirm_password" 
                                       placeholder="Ketik ulang password baru" 
                                       minlength="8"
                                       required>
                                <span class="toggle-password" onclick="togglePassword('confirmPassword', this)">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" name="reset_password" class="btn-primary">
                            <i class="bi bi-key-fill"></i> Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <div class="back-link">
                    <a href="login_pegawai.php">
                        <i class="bi bi-arrow-left"></i> Kembali ke Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const iconElement = icon.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.classList.remove('bi-eye');
                iconElement.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                iconElement.classList.remove('bi-eye-slash');
                iconElement.classList.add('bi-eye');
            }
        }

        // Validate password match on form submit
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                const newPass = document.getElementById('newPassword').value;
                const confirmPass = document.getElementById('confirmPassword').value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('Password dan konfirmasi password tidak cocok!');
                }
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>