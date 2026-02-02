<?php
require_once '../../includes/check_login.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login_pelamar.php');
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
    
    if (password_verify($old_password, $user['password'])) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password = :password WHERE user_id = :user_id";
        $update_stmt = $conn->prepare($update);
        if ($update_stmt->execute(['password' => $hashed, 'user_id' => $user_id])) {
            $success = "Password berhasil diubah!";
        }
    } else {
        $error = "Password lama tidak sesuai!";
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
        }

        .form-control:focus {
            outline: none;
            border-color: #0d47a1;
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

        /* Danger Zone */
        .danger-zone {
            border: 2px solid #d32f2f;
            border-radius: 12px;
            padding: 25px;
            background: #ffebee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .danger-info h3 {
            color: #d32f2f;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .danger-info p {
            color: #c62828;
            font-size: 13px;
            margin: 0;
        }

        .btn-danger {
            padding: 6px 20px;
            background: #d32f2f;
            color: white;
            border: 2px solid #d32f2f;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            background: #c62828;
            border-color: #c62828;
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

        @media (max-width: 768px) {
            .danger-zone {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
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

                <form method="POST" class="password-form">
                    <div class="form-group">
                        <label class="form-label">Kata Sandi Lama</label>
                        <input type="password" name="old_password" class="form-control" placeholder="Masukkan kata sandi lama" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kata Sandi Baru</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Masukkan kata sandi baru" required>
                    </div>

                    <button type="submit" name="change_password" class="btn-update">Update Kata Sandi</button>
                </form>
            </div>

            <!-- Privacy Settings -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="bi bi-shield-check"></i>
                    Privasi dan Keamanan
                </h2>

                <div class="settings-item">
                    <div class="settings-info">
                        <div class="settings-icon">
                            <i class="bi bi-shield-lock-fill" style="color: #0d47a1;"></i>
                        </div>
                        <div class="settings-text">
                            <h4>Autentikasi 2 Faktor</h4>
                            <p>Amankan akun Anda dengan kode SMS/App</p>
                        </div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="settings-section">
                <div class="danger-zone">
                    <div class="danger-info">
                        <h3>
                            <i class="bi bi-trash-fill"></i>
                            Hapus Akun
                        </h3>
                        <p>Semua data lamaran dan profil akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button class="btn-danger" onclick="confirmDeleteAccount()">
                        Tutup Akun
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        function confirmDeleteAccount() {
            Swal.fire({
                title: 'Hapus Akun?',
                text: 'Semua data lamaran dan profil akan dihapus permanen. Tindakan ini tidak dapat dibatalkan!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus Akun',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus akun...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // TODO: Tambahkan proses hapus akun
                    // window.location.href = '../../auth/delete_account.php';
                }
            });
        }
    </script>

<?php include '../partials/footer.php'; ?>