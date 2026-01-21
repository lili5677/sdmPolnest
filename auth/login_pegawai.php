<?php
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users_pegawai WHERE email = ? AND role = 'pegawai'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = 'pegawai';
                $_SESSION['nama'] = $user['nama'] ?? 'Pegawai';
                
                header('Location: ' . BASE_URL . 'users/dashboard.php');
                exit();
            } else {
                $error = 'Email atau password salah!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem.';
        }
    } else {
        $error = 'Mohon isi semua field!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pegawai - Politeknik Nest</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f5f5;
        }
        
        .login-container {
            display: flex;
            min-height: calc(100vh - 400px);
        }
        
        .left-side {
            flex: 1;
            background: url('../users/assets/dashboard.png') center/cover;
            position: relative;
            display: flex;
            align-items: flex-end;
            padding: 60px;
            min-height: 600px;
        }
        
        .left-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.3), rgba(0,0,0,0.5));
        }
        
        .building-text {
            position: relative;
            z-index: 2;
            color: white;
            font-size: 80px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
            line-height: 0.9;
            text-shadow: 2px 4px 10px rgba(0,0,0,0.5);
        }
        
        .right-side {
            flex: 1;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
        }
        
        .login-form-wrapper {
            width: 100%;
            max-width: 450px;
        }
        
        .logo-circle {
            width: 120px;
            height: 120px;
            background: #2c3e50;
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .logo-circle img {
            width: 80px;
            filter: brightness(0) invert(1);
        }
        
        .welcome-title {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .welcome-subtitle {
            font-size: 15px;
            color: #6c757d;
            text-align: center;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .form-control {
            height: 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #0D5E9D;
            box-shadow: 0 0 0 0.2rem rgba(13, 94, 157, 0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 18px;
        }
        
        .btn-login {
            width: 100%;
            height: 55px;
            background: #0D5E9D;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 30px;
            box-shadow: 0 8px 20px rgba(13, 94, 157, 0.3);
        }
        
        .btn-login:hover {
            background: #0a4d7f;
            transform: translateY(-2px);
        }
        
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
            }
            
            .left-side {
                min-height: 300px;
            }
            
            .building-text {
                font-size: 50px;
            }
        }
    </style>
</head>
<body>
    <?php include '../partials/navbar.php'; ?>
    
    <div class="login-container">
        <div class="left-side">
            <div class="building-text">NEST<br>POLITEKNIK</div>
        </div>
        
        <div class="right-side">
            <div class="login-form-wrapper">
                <div class="logo-circle">
                    <img src="../users/assets/logo.png" alt="Logo">
                </div>
                
                <h2 class="welcome-title">Selamat Datang</h2>
                <p class="welcome-subtitle">
                    Silahkan Daftarkan Akun Anda untuk Pengalaman<br>
                    Menarik Bersama Politeknik NEST
                </p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Alamat Email</label>
                        <input type="email" name="email" class="form-control" placeholder="Email terisi otomatis" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Token</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan token" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="far fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">Masuk Akun</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>