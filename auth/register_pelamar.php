<?php
/**
 * REGISTER PELAMAR - DENGAN INCLUDE NAVBAR & FOOTER
 * File: auth/register_pelamar.php
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($password) || empty($confirm)) {
        $error = "Semua field harus diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter!";
    } elseif ($password !== $confirm) {
        $error = "Password tidak cocok!";
    } else {
        try {
            $check = "SELECT user_id FROM users WHERE email = :email";
            $stmt = $conn->prepare($check);
            $stmt->execute(['email' => $email]);
            
            if ($stmt->fetch()) {
                $error = "Email sudah terdaftar!";
            } else {
                $conn->beginTransaction();
                
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insert_user = "INSERT INTO users 
                               (email, password, user_type, is_active, created_at) 
                               VALUES 
                               (:email, :password, 'pelamar', 1, NOW())";
                $stmt = $conn->prepare($insert_user);
                $stmt->execute(['email' => $email, 'password' => $hashed]);
                $user_id = $conn->lastInsertId();
                
                $insert_pelamar = "INSERT INTO pelamar 
                                  (user_id, email_aktif, is_complete, created_at) 
                                  VALUES 
                                  (:user_id, :email, 0, NOW())";
                $stmt = $conn->prepare($insert_pelamar);
                $stmt->execute(['user_id' => $user_id, 'email' => $email]);
                
                $conn->commit();
                
                header('Location: login_pelamar.php?registered=1');
                exit;
            }
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

$page_title = 'Registrasi - Politeknik NEST';
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

    .register-form {
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
        position: relative;
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
        padding: 14px 45px 14px 16px;
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

    .password-toggle {
        position: absolute;
        right: 15px;
        top: 42px;
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        font-size: 18px;
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

    .btn-register {
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
        margin-top: 10px;
    }

    .btn-register:hover {
        background: #0b3d91;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13,71,161,0.3);
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
            <div class="register-form">
                <div class="form-header">
                    <div class="form-logo">
                        <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo">
                    </div>
                    <h1 class="form-title">Selamat Datang</h1>
                    <p class="form-subtitle">Silahkan Daftarkan Akun Anda untuk Pengalaman<br>Menarik Bersama Politeknik NEST</p>
                </div>

                <?php if ($error): ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="regForm">
                    <div class="form-group">
                        <label class="form-label">Alamat Email</label>
                        <input type="email" 
                               class="form-control" 
                               name="email" 
                               placeholder="Masukkan email Anda"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Minimal 6 karakter"
                               required>
                        <button type="button" class="password-toggle" onclick="toggle('password', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="confirm" 
                               name="confirm_password" 
                               placeholder="Ulangi password Anda"
                               required>
                        <button type="button" class="password-toggle" onclick="toggle('confirm', this)">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn-register">Daftar Akun</button>
                </form>
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

        document.getElementById('regForm').addEventListener('submit', function(e) {
            const pass = document.getElementById('password').value;
            const conf = document.getElementById('confirm').value;
            if (pass !== conf) {
                e.preventDefault();
                alert('Password tidak cocok!');
            }
        });
    </script>
