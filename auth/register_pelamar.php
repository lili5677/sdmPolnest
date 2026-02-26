<?php

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database
require_once '../config/database.php';

// sudah login, redirect
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'pelamar') {
    header('Location: ../users/pelamar/dashboard.php');
    exit;
}

// Handle register
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($password) || empty($confirm)) {
        $error = "Semua field harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter!";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password harus mengandung minimal 1 huruf besar!";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password harus mengandung minimal 1 huruf kecil!";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password harus mengandung minimal 1 angka!";
    } elseif (!preg_match('/[!@#$%^&*()]/', $password)) {
        $error = "Password harus mengandung minimal 1 simbol (!@#$%^&*)!";
    } elseif ($password !== $confirm) {
        $error = "Password tidak cocok!";
    } else {
        try {
            // Cek email sudah ada
            $check = "SELECT user_id FROM users WHERE email = :email";
            $stmt = $conn->prepare($check);
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $error = "Email sudah terdaftar!";
            } else {
                // Insert user
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insert_user = "INSERT INTO users (email, password, user_type, is_active, created_at) 
                               VALUES (:email, :password, 'pelamar', 1, NOW())";
                $stmt = $conn->prepare($insert_user);
                $stmt->execute(['email' => $email, 'password' => $hashed]);
                $user_id = $conn->lastInsertId();
                
                // Insert pelamar
                $insert_pelamar = "INSERT INTO pelamar (user_id, email_aktif, is_complete, created_at) 
                                  VALUES (:user_id, :email, 0, NOW())";
                $stmt = $conn->prepare($insert_pelamar);
                $stmt->execute(['user_id' => $user_id, 'email' => $email]);
                
                // AUTO LOGIN - SET SESSION
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['user_type'] = 'pelamar';
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Force save
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
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Registrasi - Politeknik NEST';
include '../users/partials/navbar_req.php';
?>
<head>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; }
        .register-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: calc(100vh - 80px);
            background: #f5f5f5;
        }
        .register-image {
            background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), 
                        url('<?php echo BASE_URL; ?>users/assets/nest.jpg') center/cover;
        }
        .register-form-container {
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
        }
        .register-form-wrapper {
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
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c62828;
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
        }
        .btn-submit:hover {
            background: #0b3d91;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #546e7a;
            font-size: 14px;
        }
        .login-link a {
            color: #0d47a1;
            font-weight: 600;
            text-decoration: none;
        }

        /* PASSWORD REQUIREMENTS */
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
        /* END PASSWORD REQUIREMENTS */

        @media (max-width: 968px) {
            .register-container { grid-template-columns: 1fr; }
            .register-image { display: none; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-image"></div>
        <div class="register-form-container">
            <div class="register-form-wrapper">
                <div class="form-logo">
                    <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo">
                    <h1 class="form-title">Selamat Datang</h1>
                    <p class="form-subtitle">Silahkan Daftarkan Akun Anda untuk Pengalaman<br>Menarik Bersama Politeknik NEST</p>
                </div>

                <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="regForm">
                    <div class="form-group">
                        <label class="form-label">Alamat Email</label>
                        <input type="email" class="form-control" name="email" 
                               placeholder="Masukkan email Anda" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Masukkan password Anda" required>
                        <button type="button" class="password-toggle" onclick="toggle('password', this)">
                            <i class="bi bi-eye"></i>
                        </button>

                        <!-- PASSWORD REQUIREMENTS -->
                        <div class="password-requirements">
                            <div class="req-title">Syarat password:</div>
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
                        <!-- END PASSWORD REQUIREMENTS -->
                    </div>

                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password</label>
                        <input type="password" class="form-control" id="confirm" name="confirm_password" 
                               placeholder="Konfirmasi password Anda" required>
                        <button type="button" class="password-toggle" onclick="toggle('confirm', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn-submit">Daftar Akun</button>
                </form>

                <div class="login-link">
                    Sudah Punya Akun? <a href="login_pelamar.php">Masuk Disini</a>
                </div>
            </div>
        </div>
    </div>

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

        // PASSWORD REQUIREMENTS LOGIC
        const passwordInput = document.getElementById('password');

        const checks = {
            len:   { el: document.getElementById('chk-len'),   test: p => p.length >= 8 },
            upper: { el: document.getElementById('chk-upper'), test: p => /[A-Z]/.test(p) },
            lower: { el: document.getElementById('chk-lower'), test: p => /[a-z]/.test(p) },
            num:   { el: document.getElementById('chk-num'),   test: p => /[0-9]/.test(p) },
            sym:   { el: document.getElementById('chk-sym'),   test: p => /[!@#$%^&*()]/.test(p) },
        };

        passwordInput.addEventListener('input', function () {
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
        // END PASSWORD REQUIREMENTS LOGIC

        document.getElementById('regForm').addEventListener('submit', function(e) {
            const pass = document.getElementById('password').value;
            const conf = document.getElementById('confirm').value;
            
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
                alert('Password harus mengandung minimal 1 simbol unik!');
                return;
            }
            if (pass !== conf) {
                e.preventDefault();
                alert('Password tidak cocok!');
                return;
            }
        });
    </script>
</body>
</html>