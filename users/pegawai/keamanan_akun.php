<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login-pegawai.php");
    exit();
}

if (!in_array($_SESSION['user_type'], ['pegawai', 'dosen'])) {
    header("Location: ../auth/login-pegawai.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
   
// CEK FIRST LOGIN
$query_check = "SELECT password, password_changed FROM users WHERE user_id = ?";
$stmt_check = $conn->prepare($query_check);
$stmt_check->execute([$user_id]);
$user_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

$is_first_login = ($user_data['password_changed'] == 0);


//    HANDLE FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = "Semua field harus diisi.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak cocok.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password minimal 8 karakter.";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error_message = "Password harus mengandung minimal 1 huruf besar (A-Z).";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error_message = "Password harus mengandung minimal 1 huruf kecil (a-z).";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error_message = "Password harus mengandung minimal 1 angka (0-9).";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>_+=\[\]\/\\\-]/', $new_password)) {
        $error_message = "Password harus mengandung minimal 1 simbol (!@#$%^&* dll).";
    } else {

        try {
            if (!$is_first_login) {
                // VALIDASI PASSWORD LAMA
                if (!password_verify($current_password, $user_data['password'])) {
                    $error_message = "Password lama salah.";
                } else {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("
                        UPDATE users SET 
                            password = ?, 
                            password_changed = 1,
                            token = NULL,
                            updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $update->execute([$hash, $user_id]);
                    $success_message = "Password berhasil diubah.";
                }
            } else {
                // FIRST LOGIN
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("
                    UPDATE users SET 
                        password = ?, 
                        password_changed = 1,
                        token = NULL,
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $update->execute([$hash, $user_id]);
                $success_message = "first_login_success"; // Special flag untuk redirect
            }
        } catch (PDOException $e) {
            $error_message = "Terjadi kesalahan sistem.";
        }
    }
}
include '../../users/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Keamanan Akun - POLNEST</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

<!-- SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
* { font-family: 'Poppins', sans-serif; }
body { background:#f5f5f5; }

.profile-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
}

.profile-card {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 6px 18px rgba(0,0,0,.08);
}

.profile-header {
    border-bottom: 1px solid #eee;
    padding-bottom: 25px;
    margin-bottom: 30px;
}

.profile-header h1 {
    font-size: 26px;
    font-weight: 700;
    color: #1e3a5f;
}

.profile-header p {
    color: #607d8b;
    margin: 0;
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e3a5f;
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.password-form {
    max-width: 480px;
}

.form-group {
    margin-bottom: 18px;
}

.form-label {
    font-weight: 600;
    font-size: 14px;
}

.password-input-wrapper {
    position: relative;
}

.form-control {
    padding: 12px 14px;
    padding-right: 45px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
}

.form-control:focus {
    border-color: #0d47a1;
    box-shadow: none;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #607d8b;
    cursor: pointer;
    padding: 5px;
    font-size: 16px;
    transition: color 0.3s;
}

.toggle-password:hover {
    color: #0d47a1;
}

.btn-update {
    background: #0d47a1;
    color: #fff;
    padding: 12px 30px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
}

.btn-update:hover {
    background: #0b3d91;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #f59e0b;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
}

.password-requirements {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.password-requirements h6 {
    font-size: 13px;
    font-weight: 600;
    color: #1e3a5f;
    margin-bottom: 10px;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #607d8b;
    margin-bottom: 5px;
}

.requirement-item i {
    width: 16px;
    font-size: 12px;
}

.requirement-item.valid {
    color: #2e7d32;
}

.requirement-item.valid i {
    color: #2e7d32;
}

.requirement-item.invalid {
    color: #c62828;
}

.requirement-item.invalid i {
    color: #c62828;
}

/* SweetAlert2 */
.swal2-popup {
    font-family: 'Poppins', sans-serif !important;
}
</style>
</head>

<body>

<div class="profile-container">
<div class="profile-card">

    <!-- HEADER -->
    <div class="profile-header">
        <h1><?= $is_first_login ? 'Buat Password Baru' : 'Keamanan Akun' ?></h1>
        <p><?= $is_first_login ? 'Silakan buat password baru untuk keamanan akun Anda' : 'Kelola dan perbarui keamanan akun Anda' ?></p>
    </div>

    <?php if ($is_first_login): ?>
    <div class="warning-box">
        <strong><i class="fas fa-exclamation-triangle"></i> Penting!</strong>
        <ul class="mb-0 mt-2">
            <li>Ini adalah login pertama Anda</li>
            <li>Anda wajib membuat password baru yang kuat</li>
            <li>Token login tidak berlaku setelah ini</li>
        </ul>
    </div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="settings-section">
        <h2 class="section-title">
            <i class="fas fa-lock"></i>
            <?= $is_first_login ? 'Buat Password' : 'Ubah Password' ?>
        </h2>

        <form method="POST" class="password-form" id="passwordForm">

            <?php if (!$is_first_login): ?>
            <div class="form-group">
                <label class="form-label">Password Lama</label>
                <div class="password-input-wrapper">
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                        <i class="far fa-eye" id="current_password_icon"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Password Baru</label>
                <div class="password-input-wrapper">
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                        <i class="far fa-eye" id="new_password_icon"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <div class="password-input-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="far fa-eye" id="confirm_password_icon"></i>
                    </button>
                </div>
            </div>

            <!-- Password Requirements -->
            <div class="password-requirements">
                <h6><i class="fas fa-shield-alt me-2"></i>Syarat Password Kuat:</h6>
                <div class="requirement-item" id="req-length">
                    <i class="fas fa-circle"></i>
                    <span>Minimal 8 karakter</span>
                </div>
                <div class="requirement-item" id="req-uppercase">
                    <i class="fas fa-circle"></i>
                    <span>Minimal 1 huruf besar (A-Z)</span>
                </div>
                <div class="requirement-item" id="req-lowercase">
                    <i class="fas fa-circle"></i>
                    <span>Minimal 1 huruf kecil (a-z)</span>
                </div>
                <div class="requirement-item" id="req-number">
                    <i class="fas fa-circle"></i>
                    <span>Minimal 1 angka (0-9)</span>
                </div>
                <div class="requirement-item" id="req-symbol">
                    <i class="fas fa-circle"></i>
                    <span>Minimal 1 simbol (!@#$%^&*)</span>
                </div>
            </div>

            <button type="submit" class="btn-update mt-3">
                <i class="fas fa-save me-2"></i>
                <?= $is_first_login ? 'Buat Password' : 'Simpan Perubahan' ?>
            </button>

        </form>
    </div>

</div>
</div>

<script>

// SHOW SUCCESS/ERROR MESSAGE ON LOAD
<?php if ($success_message === 'first_login_success'): ?>
Swal.fire({
    icon: 'success',
    title: 'Password Berhasil Dibuat!',
    html: 'Password Anda telah berhasil dibuat.<br>Mengalihkan ke dashboard...',
    timer: 3000,
    timerProgressBar: true,
    showConfirmButton: false,
    allowOutsideClick: false,
    willClose: () => {
        window.location.href = '../../index.php';
    }
});
<?php elseif ($success_message && $success_message !== 'first_login_success'): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= addslashes($success_message) ?>',
    confirmButtonColor: '#0d47a1',
    confirmButtonText: 'OK'
});
<?php endif; ?>

<?php if ($error_message): ?>
Swal.fire({
    icon: 'error',
    title: 'Oops...',
    text: '<?= addslashes($error_message) ?>',
    confirmButtonColor: '#0d47a1',
    confirmButtonText: 'OK'
});
<?php endif; ?>

// TOGGLE PASSWORD VISIBILITY
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// REAL-TIME PASSWORD VALIDATION
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    
    // Check length
    const lengthReq = document.getElementById('req-length');
    if (password.length >= 8) {
        lengthReq.classList.add('valid');
        lengthReq.classList.remove('invalid');
        lengthReq.querySelector('i').classList.remove('fa-circle', 'fa-times-circle');
        lengthReq.querySelector('i').classList.add('fa-check-circle');
    } else {
        lengthReq.classList.remove('valid');
        lengthReq.classList.add('invalid');
        lengthReq.querySelector('i').classList.remove('fa-circle', 'fa-check-circle');
        lengthReq.querySelector('i').classList.add('fa-times-circle');
    }
    
    // Check uppercase
    const uppercaseReq = document.getElementById('req-uppercase');
    if (/[A-Z]/.test(password)) {
        uppercaseReq.classList.add('valid');
        uppercaseReq.classList.remove('invalid');
        uppercaseReq.querySelector('i').classList.remove('fa-circle', 'fa-times-circle');
        uppercaseReq.querySelector('i').classList.add('fa-check-circle');
    } else {
        uppercaseReq.classList.remove('valid');
        uppercaseReq.classList.add('invalid');
        uppercaseReq.querySelector('i').classList.remove('fa-circle', 'fa-check-circle');
        uppercaseReq.querySelector('i').classList.add('fa-times-circle');
    }
    
    // Check lowercase
    const lowercaseReq = document.getElementById('req-lowercase');
    if (/[a-z]/.test(password)) {
        lowercaseReq.classList.add('valid');
        lowercaseReq.classList.remove('invalid');
        lowercaseReq.querySelector('i').classList.remove('fa-circle', 'fa-times-circle');
        lowercaseReq.querySelector('i').classList.add('fa-check-circle');
    } else {
        lowercaseReq.classList.remove('valid');
        lowercaseReq.classList.add('invalid');
        lowercaseReq.querySelector('i').classList.remove('fa-circle', 'fa-check-circle');
        lowercaseReq.querySelector('i').classList.add('fa-times-circle');
    }
    
    // Check number
    const numberReq = document.getElementById('req-number');
    if (/[0-9]/.test(password)) {
        numberReq.classList.add('valid');
        numberReq.classList.remove('invalid');
        numberReq.querySelector('i').classList.remove('fa-circle', 'fa-times-circle');
        numberReq.querySelector('i').classList.add('fa-check-circle');
    } else {
        numberReq.classList.remove('valid');
        numberReq.classList.add('invalid');
        numberReq.querySelector('i').classList.remove('fa-circle', 'fa-check-circle');
        numberReq.querySelector('i').classList.add('fa-times-circle');
    }
    
    // Check symbol - DIPERBAIKI
    const symbolReq = document.getElementById('req-symbol');
    if (/[!@#$%^&*(),.?":{}|<>_+=\[\]\/\\\-]/.test(password)) {
        symbolReq.classList.add('valid');
        symbolReq.classList.remove('invalid');
        symbolReq.querySelector('i').classList.remove('fa-circle', 'fa-times-circle');
        symbolReq.querySelector('i').classList.add('fa-check-circle');
    } else {
        symbolReq.classList.remove('valid');
        symbolReq.classList.add('invalid');
        symbolReq.querySelector('i').classList.remove('fa-circle', 'fa-check-circle');
        symbolReq.querySelector('i').classList.add('fa-times-circle');
    }
});

// FORM VALIDATION BEFORE SUBMIT
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Check if passwords match
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Password Tidak Cocok',
            text: 'Konfirmasi password tidak cocok dengan password baru!',
            confirmButtonColor: '#0d47a1',
            confirmButtonText: 'OK'
        });
        return false;
    }
    
    // Check all requirements
    const requirements = [
        newPassword.length >= 8,
        /[A-Z]/.test(newPassword),
        /[a-z]/.test(newPassword),
        /[0-9]/.test(newPassword),
        /[!@#$%^&*(),.?":{}|<>_+=\[\]\/\\\-]/.test(newPassword)
    ];
    
    if (!requirements.every(req => req)) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Password Tidak Memenuhi Syarat',
            html: 'Password belum memenuhi semua syarat keamanan!<br><br>' +
                  '<small>Pastikan password Anda memiliki:</small>' +
                  '<ul style="text-align: left; font-size: 13px; margin-top: 10px;">' +
                  '<li>Minimal 8 karakter</li>' +
                  '<li>Minimal 1 huruf besar (A-Z)</li>' +
                  '<li>Minimal 1 huruf kecil (a-z)</li>' +
                  '<li>Minimal 1 angka (0-9)</li>' +
                  '<li>Minimal 1 simbol (!@#$%^&*)</li>' +
                  '</ul>',
            confirmButtonColor: '#0d47a1',
            confirmButtonText: 'OK'
        });
        return false;
    }
});
</script>

</body>
</html>