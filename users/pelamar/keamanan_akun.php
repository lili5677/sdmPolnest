<?php
require_once '../../includes/check_login.php';
require_once '../../config/database.php';
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login_pegawai.php');
    exit;
}
$user_id = $_SESSION['user_id'];
// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Get current password
    $query = "SELECT password FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($old_password, $user['password'])) {
        $error = "Password lama tidak sesuai!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password minimal 8 karakter!";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = "Password harus mengandung minimal 1 huruf besar!";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = "Password harus mengandung minimal 1 huruf kecil!";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = "Password harus mengandung minimal 1 angka!";
    } elseif (!preg_match('/[!@#$%^&*()]/', $new_password)) {
        $error = "Password harus mengandung minimal 1 simbol (!@#$%^&*)!";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password = :password WHERE user_id = :user_id";
        $update_stmt = $conn->prepare($update);
        if ($update_stmt->execute(['password' => $hashed, 'user_id' => $user_id])) {
            $success = "Password berhasil diubah!";
        }
    }
}

// Get user data
$query = "SELECT u.*, p.* FROM users u LEFT JOIN pelamar p ON u.user_id = p.user_id WHERE u.user_id = :user_id";
$stmt = $conn->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$page_title = 'Keamanan Akun - Politeknik NEST';
include '../partials/navbar_req.php';
?>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f5f5;
        }
        .profile-container {
            max-width: 900px;
            margin: 30px auto 30px;
            padding: 0 20px;
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .profile-header {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .profile-info h1 {
            font-size: 28px;
            color: #1e3a5f;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .profile-meta {
            color: #546e7a;
            font-size: 14px;
        }
        .settings-section {
            margin-bottom: 40px;
        }
        .section-title {
            font-size: 20px;
            color: #1e3a5f;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #0d47a1;
        }
        /* Password Form */
        .password-form {
            max-width: 500px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
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
            padding: 12px 45px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #0d47a1;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 38px;
            background: none;
            border: none;
            color: #546e7a;
            cursor: pointer;
            font-size: 18px;
        }
        .btn-update {
            padding: 12px 30px;
            background: #0d47a1;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-update:hover {
            background: #0b3d91;
        }
        /* Settings Items */
        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        .settings-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .settings-icon {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .settings-icon i {
            font-size: 24px;
        }
        .settings-text h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 3px;
        }
        .settings-text p {
            font-size: 12px;
            color: #9e9e9e;
            margin: 0;
        }
        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #0d47a1;
        }
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        /* Custom SweetAlert2 Styling */
        .swal2-popup {
            border-radius: 15px;
            font-family: 'Poppins', sans-serif;
        }
        .swal2-confirm {
            background: #d32f2f !important;
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
        /* Password Requirements */
        .password-requirements {
            margin-top: 10px;
            background: #f8f9fc;
            border: 1px solid #e3e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        .req-title {
            font-size: 12px;
            font-weight: 600;
            color: #546e7a;
            margin-bottom: 8px;
        }
        .req-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .req-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: #78909c;
            transition: color 0.25s;
        }
        .req-item.pass {
            color: #2e7d32;
        }
        .req-item .req-icon {
            font-size: 13px;
            width: 15px;
            flex-shrink: 0;
            transition: all 0.25s;
        }
    </style>

</head>
<body>
    <div class="profile-container">
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['nama_lengkap'] ?? 'Nama Lengkap') ?></h1>
                    <div class="profile-meta">
                        <span><?= htmlspecialchars($user['gelar'] ?? $user['pendidikan_terakhir'] ?? 'Pelamar') ?></span>
                    </div>
                </div>
            </div>
            <!-- Password Change Section -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="bi bi-lock-fill"></i>
                    Ubah Kata Sandi
                </h2>
                <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
                </div>
                <?php endif; ?>
                <form method="POST" class="password-form" id="passwordForm">
                    <div class="form-group">
                        <label class="form-label">Kata Sandi Lama</label>
                        <input type="password" name="old_password" id="old_password" class="form-control" placeholder="Masukkan kata sandi lama" required>
                        <button type="button" class="password-toggle" onclick="toggle('old_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kata Sandi Baru</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Masukkan kata sandi baru" required>
                        <button type="button" class="password-toggle" onclick="toggle('new_password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                        <!-- Password Requirements (selalu tampil) -->
                        <div class="password-requirements">
                            <div class="req-title">Syarat kata sandi baru:</div>
                            <div class="req-list">
                                <div class="req-item" id="chk-len">
                                    <i class="req-icon bi bi-circle"></i>
                                    Minimal 8 karakter
                                </div>
                                <div class="req-item" id="chk-upper">
                                    <i class="req-icon bi bi-circle"></i>
                                    Minimal 1 huruf besar (A-Z)
                                </div>
                                <div class="req-item" id="chk-lower">
                                    <i class="req-icon bi bi-circle"></i>
                                    Minimal 1 huruf kecil (a-z)
                                </div>
                                <div class="req-item" id="chk-num">
                                    <i class="req-icon bi bi-circle"></i>
                                    Minimal 1 angka (0-9)
                                </div>
                                <div class="req-item" id="chk-sym">
                                    <i class="req-icon bi bi-circle"></i>
                                    Minimal 1 simbol (!@#$%^&amp;*)
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn-update">Update Kata Sandi</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        function toggle(id, btn) {
            const field = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Password Requirements Logic
        const newPasswordInput = document.getElementById('new_password');

        const checks = {
            len:   { el: document.getElementById('chk-len'),   test: p => p.length >= 8 },
            upper: { el: document.getElementById('chk-upper'), test: p => /[A-Z]/.test(p) },
            lower: { el: document.getElementById('chk-lower'), test: p => /[a-z]/.test(p) },
            num:   { el: document.getElementById('chk-num'),   test: p => /[0-9]/.test(p) },
            sym:   { el: document.getElementById('chk-sym'),   test: p => /[!@#$%^&*()]/.test(p) },
        };

        newPasswordInput.addEventListener('input', function () {
            const val = this.value;
            for (const key in checks) {
                const ok = checks[key].test(val);
                checks[key].el.classList.toggle('pass', ok);
                const icon = checks[key].el.querySelector('.req-icon');
                icon.className = ok
                    ? 'req-icon bi bi-check-circle-fill'
                    : 'req-icon bi bi-circle';
            }
        });

        // Form Submit Validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const pass = document.getElementById('new_password').value;

            if (pass.length < 8) {
                e.preventDefault();
                alert('Password minimal 8 karakter!');
                return;
            }
            if (!/[A-Z]/.test(pass)) {
                e.preventDefault();
                alert('Password harus mengandung minimal 1 huruf besar!');
                return;
            }
            if (!/[a-z]/.test(pass)) {
                e.preventDefault();
                alert('Password harus mengandung minimal 1 huruf kecil!');
                return;
            }
            if (!/[0-9]/.test(pass)) {
                e.preventDefault();
                alert('Password harus mengandung minimal 1 angka!');
                return;
            }
            if (!/[!@#$%^&*()]/.test(pass)) {
                e.preventDefault();
                alert('Password harus mengandung minimal 1 simbol (!@#$%^&*)!');
                return;
            }
        });
    </script>
<?php include '../partials/footer.php'; ?>