<?php
session_start();
require_once '../../config/database.php';

/* ===============================
   AUTH CHECK
================================ */
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

/* ===============================
   CEK FIRST LOGIN
================================ */
$query_check = "SELECT password, password_changed FROM users WHERE user_id = ?";
$stmt_check = $conn->prepare($query_check);
$stmt_check->execute([$user_id]);
$user_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

$is_first_login = ($user_data['password_changed'] == 0);

/* ===============================
   HANDLE FORM
================================ */
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
                $success_message = "Password berhasil dibuat. Mengalihkan ke dashboard...";
                echo "<script>
                        setTimeout(() => {
                            window.location.href = '../../index.php';
                        }, 3000);
                    </script>";
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

.form-control {
    padding: 12px 14px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
}

.form-control:focus {
    border-color: #0d47a1;
    box-shadow: none;
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

.alert-success {
    background: #e8f5e9;
    border-left: 4px solid #2e7d32;
}

.alert-danger {
    background: #ffebee;
    border-left: 4px solid #c62828;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #f59e0b;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
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
        <strong>Penting!</strong>
        <ul class="mb-0 mt-2">
            <li>Ini adalah login pertama Anda</li>
            <li>Anda wajib membuat password baru</li>
            <li>Token login tidak berlaku setelah ini</li>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="alert alert-success mb-3"><?= $success_message ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger mb-3"><?= $error_message ?></div>
    <?php endif; ?>

    <!-- FORM -->
    <div class="settings-section">
        <h2 class="section-title">
            <i class="fas fa-lock"></i>
            <?= $is_first_login ? 'Buat Password' : 'Ubah Password' ?>
        </h2>

        <form method="POST" class="password-form">

            <?php if (!$is_first_login): ?>
            <div class="form-group">
                <label class="form-label">Password Lama</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Password Baru</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn-update">
                <?= $is_first_login ? 'Buat Password' : 'Simpan Perubahan' ?>
            </button>

        </form>
    </div>

</div>
</div>

</body>
</html>