<?php
session_start();
require_once '../config/database.php';

date_default_timezone_set('Asia/Jakarta');

$error = '';
$success = '';

/* INIT STEP */
if (isset($_GET['reset_flow']) && $_GET['reset_flow'] === 'new') {
    unset(
        $_SESSION['reset_step_pelamar'],
        $_SESSION['verified_pelamar_user_id'],
        $_SESSION['reset_pelamar_email'],
        $_SESSION['token_expired_pelamar'],
        $_SESSION['token_rejected_message_pelamar'],
        $_SESSION['auto_redirect_pelamar']
    );
    $_SESSION['reset_step_pelamar'] = 'request';
    
    header("Location: lupa-password-pelamar.php");
    exit;
}

if (!isset($_SESSION['reset_step_pelamar'])) {
    $_SESSION['reset_step_pelamar'] = 'request';
}
$step = $_SESSION['reset_step_pelamar'];

// AUTO-CHECK: Jika di step verify, cek apakah request sudah di-reject/complete oleh admin
if ($step === 'verify' && isset($_SESSION['reset_pelamar_email'])) {
    $email = $_SESSION['reset_pelamar_email'];
    
    $stmt = $conn->prepare("
        SELECT request_id, status 
        FROM password_reset_requests
        WHERE email = ?
        ORDER BY requested_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $latest_request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Jika request sudah rejected/completed, redirect ke step 1
    if ($latest_request && in_array($latest_request['status'], ['rejected', 'completed'])) {
        unset($_SESSION['reset_step_pelamar'], $_SESSION['reset_pelamar_email'], $_SESSION['verified_pelamar_user_id']);
        $_SESSION['reset_step_pelamar'] = 'request';
        
        if ($latest_request['status'] === 'rejected') {
            $_SESSION['token_expired_pelamar'] = true;
            $_SESSION['token_rejected_message_pelamar'] = "Permintaan reset password Anda telah ditolak atau dibatalkan oleh admin. Silakan ajukan permintaan baru.";
        } else {
            $_SESSION['token_expired_pelamar'] = true;
            $_SESSION['token_rejected_message_pelamar'] = "Token sudah digunakan. Silakan ajukan permintaan baru jika masih membutuhkan reset password.";
        }
        
        header("Location: lupa-password-pelamar.php");
        exit;
    }
}

// Tampilkan pesan jika token expired
$token_expired_message = '';
if (isset($_SESSION['token_expired_pelamar']) && $_SESSION['token_expired_pelamar'] === true) {
    $token_expired_message = "Token Anda sudah kadaluarsa (lebih dari 24 jam). Silakan ajukan permintaan reset password baru di bawah ini.";
    unset($_SESSION['token_expired_pelamar']);
}

/* STEP 1 — REQUEST RESET (Simpan ke Database)*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {

    // Reset flow setiap request baru
    unset(
        $_SESSION['reset_step_pelamar'],
        $_SESSION['verified_pelamar_user_id'],
        $_SESSION['reset_pelamar_email']
    );

    $_SESSION['reset_step_pelamar'] = 'request';

    $email = trim($_POST['email']);

    if ($email === '') {
        $error = "Email harus diisi!";
    } else {

        // Cek user pelamar
        $stmt = $conn->prepare("
            SELECT u.user_id, u.email, p.nama_lengkap
            FROM users u
            LEFT JOIN pelamar p ON u.user_id = p.user_id
            WHERE u.email = ?
              AND u.is_active = 1
              AND u.user_type = 'pelamar'
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Email tidak terdaftar sebagai pelamar aktif.";
        } else {

            // Cek request pending dalam 1 jam terakhir
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
                $error = "Anda sudah mengajukan permintaan reset password. Silakan hubungi admin atau tunggu hingga request sebelumnya diproses. Jika sudah mendapatkan token dari admin, refresh website";
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

                $_SESSION['reset_pelamar_email'] = $email;
                $_SESSION['reset_step_pelamar'] = 'verify';

                header("Location: lupa-password-pelamar.php");
                exit;
            }
        }
    }
}

/* STEP 2 — VERIFY TOKEN */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_token'])) {

    $token = strtoupper(trim($_POST['token']));
    $email = trim($_POST['email']);

    if ($token === '' || $email === '') {
        $error = "Email dan kode verifikasi wajib diisi!";
    } else {

        // Cek request yang approved untuk email 
        $stmt = $conn->prepare("
            SELECT request_id, status 
            FROM password_reset_requests
            WHERE email = ?
            ORDER BY requested_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $latest_request = $stmt->fetch(PDO::FETCH_ASSOC);

        // Jika request sudah rejected/completed, redirect ke step 1
        if ($latest_request && in_array($latest_request['status'], ['rejected', 'completed'])) {
            
            // Clear session
            unset($_SESSION['reset_step_pelamar'], $_SESSION['reset_pelamar_email'], $_SESSION['verified_pelamar_user_id']);
            $_SESSION['reset_step_pelamar'] = 'request';
            
            if ($latest_request['status'] === 'rejected') {
                $_SESSION['token_expired_pelamar'] = true;
                $_SESSION['token_rejected_message_pelamar'] = "Token Anda sudah dibatalkan atau ditolak oleh admin. Silakan ajukan permintaan reset password baru.";
            } else {
                $_SESSION['token_expired_pelamar'] = true;
                $_SESSION['token_rejected_message_pelamar'] = "Token sudah digunakan. Silakan ajukan permintaan baru jika masih membutuhkan reset password.";
            }
            
            header("Location: lupa-password-pelamar.php");
            exit;
        }

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
            $error = "Kode verifikasi tidak valid atau sudah dibatalkan. Pastikan Anda memasukkan kode yang benar dari admin.";
            
            $_SESSION['auto_redirect_pelamar'] = true;
            
        } else {
            // Cek apakah token sudah expired
            if (strtotime($user['reset_token_expires']) < time()) {
                
                try {
                    // Update status request menjadi rejected (karena expired)
                    $stmt = $conn->prepare("
                        UPDATE password_reset_requests
                        SET status = 'rejected'
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
                } catch (PDOException $e) {
                    error_log("Error updating expired token: " . $e->getMessage());
                }
                
                // Clear session dan kembalikan ke step 1
                unset($_SESSION['reset_step_pelamar'], $_SESSION['reset_pelamar_email'], $_SESSION['verified_pelamar_user_id']);
                $_SESSION['reset_step_pelamar'] = 'request';
                $_SESSION['token_expired_pelamar'] = true;
                
                header("Location: lupa-password-pelamar.php");
                exit;
                
            } else {
                // Token valid dan belum expired
                $_SESSION['verified_pelamar_user_id'] = $user['user_id'];
                $_SESSION['reset_step_pelamar'] = 'reset';

                header("Location: lupa-password-pelamar.php");
                exit;
            }
        }
    }
}

/* STEP 3 — RESET PASSWORD */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {

    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $user_id = $_SESSION['verified_pelamar_user_id'] ?? 0;

    if ($new === '' || $confirm === '') {
        $error = "Password harus diisi!";
    } elseif (strlen($new) < 8) {
        $error = "Password minimal 8 karakter!";
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $error = "Password harus mengandung minimal 1 huruf besar!";
    } elseif (!preg_match('/[a-z]/', $new)) {
        $error = "Password harus mengandung minimal 1 huruf kecil!";
    } elseif (!preg_match('/[0-9]/', $new)) {
        $error = "Password harus mengandung minimal 1 angka!";
    } elseif (!preg_match('/[@$!%*?&#]/', $new)) {
        $error = "Password harus mengandung minimal 1 simbol (@$!%*?&#)!";
    } elseif ($new !== $confirm) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif ($user_id === 0) {
        $error = "Sesi reset tidak valid.";
    } else {

        $hash = password_hash($new, PASSWORD_DEFAULT);

        try {
            // Update password & hapus token
            $stmt = $conn->prepare("
                UPDATE users
                SET password = ?,
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

            // Hapus session
            session_unset();
            session_destroy();

            header("Location: login_pelamar.php?reset_success=1");
            exit;
            
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan saat mereset password. Silakan coba lagi.";
            error_log("Error resetting password: " . $e->getMessage());
        }
    }
}

$page_title = 'Lupa Password Pelamar - Politeknik NEST';
include '../users/partials/navbar.php';
?>
<head>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
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

        /* Password Strength Meter */
        .password-strength-meter {
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .strength-meter-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            margin-bottom: 8px;
        }

        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 10px;
        }

        .strength-meter-fill.strength-weak {
            width: 25%;
            background: linear-gradient(90deg, #ef4444 0%, #f87171 100%);
        }

        .strength-meter-fill.strength-fair {
            width: 50%;
            background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
        }

        .strength-meter-fill.strength-good {
            width: 75%;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
        }

        .strength-meter-fill.strength-strong {
            width: 100%;
            background: linear-gradient(90deg, #10b981 0%, #34d399 100%);
        }

        .strength-meter-text {
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .strength-meter-text.strength-weak {
            color: #ef4444;
        }

        .strength-meter-text.strength-fair {
            color: #f59e0b;
        }

        .strength-meter-text.strength-good {
            color: #3b82f6;
        }

        .strength-meter-text.strength-strong {
            color: #10b981;
        }

        .strength-requirements {
            margin-top: 10px;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #e2e8f0;
        }

        .strength-requirement {
            font-size: 11px;
            color: #64748b;
            margin: 4px 0;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .strength-requirement i {
            font-size: 10px;
        }

        .strength-requirement.met {
            color: #10b981;
            font-weight: 500;
        }

        .strength-requirement.met i.bi-circle {
            display: none;
        }

        .strength-requirement.met i.bi-check-circle-fill {
            display: inline;
        }

        .strength-requirement i.bi-check-circle-fill {
            display: none;
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
                <p>Reset password akun pelamar Anda</p>
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

                <!-- Token Expired Alert -->
                <?php if ($token_expired_message): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span><?php echo htmlspecialchars($token_expired_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['token_rejected_message_pelamar'])): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-x-circle-fill"></i>
                        <span><?php echo htmlspecialchars($_SESSION['token_rejected_message_pelamar']); ?></span>
                    </div>
                    <?php unset($_SESSION['token_rejected_message_pelamar']); ?>
                <?php endif; ?>

                <!-- Error Alert -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    
                    <!-- Auto redirect notice -->
                    <?php if (isset($_SESSION['auto_redirect_pelamar'])): ?>
                        <div class="alert alert-info" style="margin-top: 10px;">
                            <i class="bi bi-arrow-clockwise"></i>
                            <span>Anda akan dialihkan ke halaman request baru dalam <strong><span id="countdown">5</span> detik</strong>...</span>
                        </div>
                        <?php unset($_SESSION['auto_redirect_pelamar']); ?>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- STEP 1: Request Reset -->
                <?php if ($step === 'request'): ?>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Email Pelamar</label>
                            <input type="email" class="form-control" name="email" 
                                   placeholder="Masukkan email pelamar Anda" required>
                        </div>

                        <button type="submit" name="request_reset" class="btn-primary">
                            <i class="bi bi-send-fill"></i> Kirim Permintaan Reset Password
                        </button>
                    </form>
                <?php endif; ?>

                <!-- STEP 2: Verify Token -->
                <?php if ($step === 'verify'): ?>
                    <div class="waiting-box">
                        <i class="bi bi-hourglass-half"></i>
                        <h4>Menunggu Persetujuan Admin</h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['reset_pelamar_email'] ?? ''); ?></p>
                        <p style="margin-top: 15px;">
                            Silakan <strong>hubungi admin</strong> untuk mendapatkan token reset password.
                            Admin akan mengirimkan token ke WhatsApp/Email Anda.
                        </p>
                        <p style="margin-top: 10px; font-size: 12px;">
                            <i class="bi bi-arrow-clockwise"></i> Halaman akan otomatis refresh setiap 30 detik untuk mengecek status request
                        </p>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>Setelah mendapat token dari admin, masukkan di form bawah ini.</span>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-clock-fill"></i>
                        <span><strong>Penting:</strong> Token hanya berlaku selama <strong>24 jam</strong>. Jika lebih dari 24 jam, Anda harus meminta token baru dari admin.</span>
                    </div>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   placeholder="Masukkan email Anda"
                                   value="<?php echo htmlspecialchars($_SESSION['reset_pelamar_email'] ?? ''); ?>"
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
                                <i class="bi bi-clock"></i> Token berlaku selama 24 jam sejak admin men-generate
                            </small>
                        </div>

                        <button type="submit" name="verify_token" class="btn-primary">
                            <i class="bi bi-check-circle-fill"></i> Verifikasi Token
                        </button>
                    </form>
                    
                    <script>
                        // Auto-refresh setiap 30 detik untuk cek status request
                        setTimeout(function() {
                            window.location.reload();
                        }, 30000); 
                    </script>
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
                          
                            <div class="password-strength-meter" id="strengthMeter" style="display: none;">
                                <div class="strength-meter-bar">
                                    <div class="strength-meter-fill" id="strengthFill"></div>
                                </div>
                                <div class="strength-meter-text" id="strengthText">
                                    <i class="bi bi-shield-fill-check"></i>
                                    <span id="strengthLabel">Kekuatan Password</span>
                                </div>
                            </div>

                            <div class="strength-requirements">
                                <div class="strength-requirement" id="req-length">
                                    <i class="bi bi-circle"></i>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Minimal 8 karakter</span>
                                </div>
                                <div class="strength-requirement" id="req-uppercase">
                                    <i class="bi bi-circle"></i>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Mengandung huruf besar (A-Z)</span>
                                </div>
                                <div class="strength-requirement" id="req-lowercase">
                                    <i class="bi bi-circle"></i>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Mengandung huruf kecil (a-z)</span>
                                </div>
                                <div class="strength-requirement" id="req-number">
                                    <i class="bi bi-circle"></i>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Mengandung angka (0-9)</span>
                                </div>
                                <div class="strength-requirement" id="req-symbol">
                                    <i class="bi bi-circle"></i>
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Mengandung simbol (@$!%*?&#)</span>
                                </div>
                            </div>
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
                    <a href="login_pelamar.php">
                        <i class="bi bi-arrow-left"></i> Kembali ke Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // PASSWORD FUNCTIONS
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

        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                symbol: /[@$!%*?&#]/.test(password)
            };

            Object.values(requirements).forEach(met => {
                if (met) strength++;
            });

            return { strength, requirements };
        }

        function updateStrengthMeter(password) {
            const strengthMeter = document.getElementById('strengthMeter');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            const strengthLabel = document.getElementById('strengthLabel');

            if (password.length === 0) {
                strengthMeter.style.display = 'none';
                return;
            }

            strengthMeter.style.display = 'block';

            const { strength, requirements } = checkPasswordStrength(password);

            document.getElementById('req-length').classList.toggle('met', requirements.length);
            document.getElementById('req-uppercase').classList.toggle('met', requirements.uppercase);
            document.getElementById('req-lowercase').classList.toggle('met', requirements.lowercase);
            document.getElementById('req-number').classList.toggle('met', requirements.number);
            document.getElementById('req-symbol').classList.toggle('met', requirements.symbol);

            strengthFill.classList.remove('strength-weak', 'strength-fair', 'strength-good', 'strength-strong');
            strengthText.classList.remove('strength-weak', 'strength-fair', 'strength-good', 'strength-strong');

            if (strength <= 2) {
                strengthFill.classList.add('strength-weak');
                strengthText.classList.add('strength-weak');
                strengthLabel.textContent = 'Lemah';
            } else if (strength === 3) {
                strengthFill.classList.add('strength-fair');
                strengthText.classList.add('strength-fair');
                strengthLabel.textContent = 'Cukup';
            } else if (strength === 4) {
                strengthFill.classList.add('strength-good');
                strengthText.classList.add('strength-good');
                strengthLabel.textContent = 'Baik';
            } else {
                strengthFill.classList.add('strength-strong');
                strengthText.classList.add('strength-strong');
                strengthLabel.textContent = 'Kuat';
            }
        }

        // FORM VALIDATION
        const resetForm = document.getElementById('resetForm');
        if (resetForm) {
            const newPasswordInput = document.getElementById('newPassword');
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    updateStrengthMeter(this.value);
                });
            }

            resetForm.addEventListener('submit', function(e) {
                const newPass = document.getElementById('newPassword').value;
                const confirmPass = document.getElementById('confirmPassword').value;
                
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('Password dan konfirmasi password tidak cocok!');
                    return false;
                }
                
                const { requirements } = checkPasswordStrength(newPass);
                
                if (!requirements.length || !requirements.uppercase || !requirements.lowercase || 
                    !requirements.number || !requirements.symbol) {
                    e.preventDefault();
                    alert('Password tidak memenuhi syarat yang ditentukan!');
                    return false;
                }
            });
        }

        // AUTO-REDIRECT COUNTDOWN
        <?php if (isset($error) && isset($_SESSION['auto_redirect_pelamar'])): ?>
            let countdown = 5;
            const countdownElement = document.getElementById('countdown');
            
            const interval = setInterval(() => {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 0) {
                    clearInterval(interval);
                    window.location.href = 'lupa-password-pelamar.php?reset_flow=new';
                }
            }, 1000);
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>