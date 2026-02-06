<?php
require_once '../../includes/check_login.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login_pelamar.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ======================================
// HANDLE DELETE ACCOUNT
// ======================================
if (isset($_GET['action']) && $_GET['action'] === 'delete_account') {
    try {
        // STEP 1: Get pelamar_id
        $query = "SELECT pelamar_id FROM pelamar WHERE user_id = :user_id";
        $stmt = $conn->prepare($query);
        $stmt->execute(['user_id' => $user_id]);
        $pelamar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pelamar) {
            throw new Exception('Data pelamar tidak ditemukan');
        }
        
        $pelamar_id = $pelamar['pelamar_id'];
        
        // STEP 2: Hitung jumlah lamaran
        $count_lamaran = $conn->prepare("SELECT COUNT(*) as total FROM lamaran WHERE pelamar_id = ?");
        $count_lamaran->execute([$pelamar_id]);
        $total_lamaran = $count_lamaran->fetch(PDO::FETCH_ASSOC)['total'];
        
        // STEP 3: Begin transaction
        $conn->beginTransaction();
        
        // STEP 4: Hapus semua lamaran
        if ($total_lamaran > 0) {
            $delete_lamaran = "DELETE FROM lamaran WHERE pelamar_id = ?";
            $stmt_lamaran = $conn->prepare($delete_lamaran);
            $stmt_lamaran->execute([$pelamar_id]);
        }
        
        // STEP 5: Hapus data pelamar
        $delete_pelamar = "DELETE FROM pelamar WHERE pelamar_id = ?";
        $stmt_pelamar = $conn->prepare($delete_pelamar);
        $stmt_pelamar->execute([$pelamar_id]);
        
        // STEP 6: Hapus data user
        $delete_user = "DELETE FROM users WHERE user_id = ?";
        $stmt_user = $conn->prepare($delete_user);
        $stmt_user->execute([$user_id]);
        
        // STEP 7: Commit
        $conn->commit();
        
        // STEP 8: Destroy session
        session_destroy();
        
        // STEP 9: Redirect dengan pesan sukses
        header('Location: ../../index.php?account_deleted=1&lamaran=' . $total_lamaran);
        exit;
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $delete_error = $e->getMessage();
    }
}

// ======================================
// HANDLE PASSWORD CHANGE
// ======================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    
    // Validasi
    if (strlen($new_password) < 6) {
        $error = "Password baru minimal 6 karakter!";
    } else {
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
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #0d47a1;
            box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-update:hover {
            background: #0b3d91;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
        }

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
            padding: 10px 24px;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .alert i {
            font-size: 20px;
        }

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: #e0e0e0;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s;
            width: 0;
        }

        .strength-weak { width: 33%; background: #d32f2f; }
        .strength-medium { width: 66%; background: #f57c00; }
        .strength-strong { width: 100%; background: #2e7d32; }

        @media (max-width: 768px) {
            .danger-zone {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .profile-card {
                padding: 25px;
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
                    <h1><i class="bi bi-shield-lock-fill"></i> Keamanan Akun</h1>
                    <div class="profile-meta">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['nama_lengkap'] ?? 'Nama Lengkap') ?>
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
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= $success ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $error ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($delete_error)): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Gagal menghapus akun: <?= $delete_error ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" class="password-form">
                    <div class="form-group">
                        <label class="form-label">Kata Sandi Lama</label>
                        <input type="password" name="old_password" class="form-control" placeholder="Masukkan kata sandi lama" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kata Sandi Baru</label>
                        <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Masukkan kata sandi baru" required minlength="6">
                    </div>

                    <button type="submit" name="change_password" class="btn-update">
                        Update Kata Sandi
                    </button>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="settings-section">
                <h2 class="section-title" style="color: #d32f2f;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Zona Berbahaya
                </h2>
                <div class="danger-zone">
                    <div class="danger-info">
                        <h3>
                            <i class="bi bi-trash-fill"></i>
                            Hapus Akun Permanen
                        </h3>
                        <p>Semua data lamaran dan profil akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button class="btn-danger" onclick="confirmDeleteAccount()">
                        <i class="bi bi-trash-fill"></i>
                        Tutup Akun
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        // Confirm Delete Account
        function confirmDeleteAccount() {
            Swal.fire({
                title: 'Hapus Akun Permanen?',
                html: `
                    <p style="color: #666; margin-bottom: 15px;">Semua data berikut akan dihapus:</p>
                    <ul style="text-align: left; color: #666; margin-left: 30px;">
                        <li>Profil dan data pribadi</li>
                        <li>Semua riwayat lamaran</li>
                        <li>Dokumen CV dan berkas</li>
                    </ul>
                    <p style="color: #d32f2f; font-weight: 600; margin-top: 15px;">⚠️ Tindakan ini tidak dapat dibatalkan!</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus Akun Saya',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                confirmButtonColor: '#d32f2f',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Konfirmasi kedua
                    Swal.fire({
                        title: 'Konfirmasi Terakhir',
                        text: 'Ketik "HAPUS" untuk melanjutkan',
                        input: 'text',
                        inputPlaceholder: 'Ketik HAPUS',
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonText: 'Hapus Akun',
                        cancelButtonText: 'Batalkan',
                        confirmButtonColor: '#d32f2f',
                        cancelButtonColor: '#6c757d',
                        inputValidator: (value) => {
                            if (value !== 'HAPUS') {
                                return 'Anda harus mengetik "HAPUS" untuk melanjutkan!';
                            }
                        }
                    }).then((result2) => {
                        if (result2.isConfirmed) {
                            Swal.fire({
                                title: 'Menghapus akun...',
                                text: 'Mohon tunggu sebentar',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            
                            // Redirect dengan action delete
                            window.location.href = 'keamanan.php?action=delete_account';
                        }
                    });
                }
            });
        }
    </script>

<?php include '../partials/footer.php'; ?>