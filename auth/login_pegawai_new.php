<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if (in_array($_SESSION['user_type'], ['pegawai', 'dosen'])) {
        header('Location: ../users/pegawai/administrasi.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'admin') {
        header('Location: ../users/admin/dashboard.php');
        exit();
    }
}

$error = '';
$auto_email = '';
$auto_token = '';
$nama_pegawai = '';
$role_pegawai = '';
$data_loaded = false;

// AUTO-LOAD EMAIL & TOKEN FROM URL
$url_email = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($url_email) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                at.token,
                at.role,
                at.expired_at,
                p.pelamar_id,
                p.nama_lengkap,
                p.email_aktif,
                u.user_id
            FROM activation_tokens at
            INNER JOIN pelamar p ON at.pelamar_id = p.pelamar_id
            INNER JOIN users u ON p.user_id = u.user_id
            WHERE p.email_aktif = :email
              AND at.is_used = 0
              AND at.expired_at > NOW()
            ORDER BY at.created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['email' => $url_email]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            $data_loaded = true;
            $auto_email = $tokenData['email_aktif'];
            $auto_token = $tokenData['token'];
            $nama_pegawai = $tokenData['nama_lengkap'];
            $role_pegawai = $tokenData['role'];
        } else {
            $error = "Token tidak ditemukan, sudah digunakan, atau sudah kadaluarsa.";
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}


// PROSES AKTIVASI 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $data_loaded) {
    $input_email = trim($_POST['email'] ?? '');
    $input_token = trim($_POST['token'] ?? '');

    try {
        $stmtToken = $conn->prepare("
            SELECT 
                at.*,
                p.pelamar_id,
                p.nama_lengkap,
                p.tempat_lahir,
                p.tanggal_lahir,
                p.email_aktif,
                p.no_wa,
                p.alamat_domisili,
                p.alamat_ktp,
                u.user_id,
                u.email as email_login
            FROM activation_tokens at
            INNER JOIN pelamar p ON at.pelamar_id = p.pelamar_id
            INNER JOIN users u ON p.user_id = u.user_id
            WHERE p.email_aktif = :email
              AND at.token = :token
              AND at.is_used = 0
              AND at.expired_at > NOW()
            LIMIT 1
        ");
        $stmtToken->execute([
            'email' => $input_email,
            'token' => $input_token
        ]);
        $tokenData = $stmtToken->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            $error = "Token tidak valid, sudah digunakan, atau sudah kadaluarsa!";
        } else {
            $conn->beginTransaction();
            
            try {
                // 1. UPDATE TOKEN → HANGUS
                $stmtUpdateToken = $conn->prepare("
                    UPDATE activation_tokens 
                    SET is_used = 1, 
                        created_at = NOW()
                    WHERE token = :token
                ");
                $stmtUpdateToken->execute(['token' => $input_token]);
                // 2. AMBIL JENIS_POSISI DARI LOWONGAN YANG DILAMAR
                $stmtLowongan = $conn->prepare("
                    SELECT 
                        lp.jenis_posisi, 
                        lp.posisi,
                        lp.lowongan_id
                    FROM lamaran l
                    INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
                    WHERE l.pelamar_id = :pelamar_id
                      AND l.status_lamaran = 'diterima'
                    ORDER BY l.tanggal_update DESC
                    LIMIT 1
                ");
                $stmtLowongan->execute(['pelamar_id' => $tokenData['pelamar_id']]);
                $lowonganData = $stmtLowongan->fetch(PDO::FETCH_ASSOC);
                // 3. TENTUKAN JENIS_PEGAWAI DAN USER_TYPE
                // PRIORITAS:
                // 1. jenis_posisi dari lowongan (PRIORITAS UTAMA)
                // 2. NIDN + Prodi (fallback)
                // 3. Deteksi dari nama posisi (fallback terakhir)

                $jenis_pegawai = 'staff'; 
                $user_type = 'pegawai';  
                $is_dosen_nest = 0;
                
                if ($lowonganData && !empty($lowonganData['jenis_posisi'])) {
                    // GUNAKAN JENIS_POSISI DARI LOWONGAN
                    $jenis_pegawai = strtolower($lowonganData['jenis_posisi']);
                    $user_type = ($jenis_pegawai === 'dosen') ? 'dosen' : 'pegawai';
                    
                } elseif ($tokenData['nidn'] && $tokenData['prodi']) {
                    // FALLBACK: Jika ada NIDN & Prodi
                    $jenis_pegawai = 'dosen';
                    $user_type = 'dosen';
                    
                } elseif ($lowonganData && stripos($lowonganData['posisi'], 'dosen') !== false) {
                    // FALLBACK: Deteksi dari nama posisi
                    $jenis_pegawai = 'dosen';
                    $user_type = 'dosen';
                }
                
                // Cek apakah email institusi (untuk is_dosen_nest)
                $email_check = $tokenData['email_aktif'];
                if (
                    $jenis_pegawai === 'dosen' && 
                    (strpos($email_check, '@polnest.ac.id') !== false || 
                     strpos($email_check, '@nest.ac.id') !== false)
                ) {
                    $is_dosen_nest = 1;
                }

                // 4. UPDATE USER → SET USER_TYPE
                $stmtUpdateUser = $conn->prepare("
                    UPDATE users 
                    SET user_type = :user_type,
                        token = NULL,
                        password_changed = 0,
                        updated_at = NOW()
                    WHERE user_id = :user_id
                ");
                $stmtUpdateUser->execute([
                    'user_type' => $user_type,
                    'user_id' => $tokenData['user_id']
                ]);

                // 5. UPDATE PELAMAR → IS_PEGAWAI = 1
                $stmtUpdatePelamar = $conn->prepare("
                    UPDATE pelamar 
                    SET is_pegawai = 1
                    WHERE pelamar_id = :pelamar_id
                ");
                $stmtUpdatePelamar->execute(['pelamar_id' => $tokenData['pelamar_id']]);

                // 6. INSERT KE TABEL PEGAWAI
                // Cek dulu apakah sudah ada di tabel pegawai
                $stmtCheckPegawai = $conn->prepare("
                    SELECT pegawai_id FROM pegawai WHERE user_id = :user_id
                ");
                $stmtCheckPegawai->execute(['user_id' => $tokenData['user_id']]);
                
                // Jika belum ada, INSERT data baru
                if ($stmtCheckPegawai->rowCount() === 0) {
                    $stmtInsertPegawai = $conn->prepare("
                        INSERT INTO pegawai (
                            user_id,
                            nama_lengkap,
                            tempat_lahir,
                            tanggal_lahir,
                            email,
                            no_telepon,
                            alamat_domisili,
                            alamat_ktp,
                            nidn,
                            prodi,
                            nip,
                            jenis_pegawai,
                            is_dosen_nest
                        ) VALUES (
                            :user_id,
                            :nama_lengkap,
                            :tempat_lahir,
                            :tanggal_lahir,
                            :email,
                            :no_telepon,
                            :alamat_domisili,
                            :alamat_ktp,
                            :nidn,
                            :prodi,
                            :nip,
                            :jenis_pegawai,
                            :is_dosen_nest
                        )
                    ");
                    
                    $stmtInsertPegawai->execute([
                        'user_id' => $tokenData['user_id'],
                        'nama_lengkap' => $tokenData['nama_lengkap'],
                        'tempat_lahir' => $tokenData['tempat_lahir'],
                        'tanggal_lahir' => $tokenData['tanggal_lahir'],
                        'email' => $tokenData['email_aktif'],
                        'no_telepon' => $tokenData['no_wa'],
                        'alamat_domisili' => $tokenData['alamat_domisili'],
                        'alamat_ktp' => $tokenData['alamat_ktp'],
                        'nidn' => $tokenData['nidn'],
                        'prodi' => $tokenData['prodi'],
                        'nip' => $tokenData['nip'],
                        'jenis_pegawai' => $jenis_pegawai,
                        'is_dosen_nest' => $is_dosen_nest
                    ]);
                    
                    // Ambil pegawai_id yang baru dibuat
                    $pegawai_id = $conn->lastInsertId();
                } else {
                    // Jika sudah ada, ambil pegawai_id yang existing
                    $existingPegawai = $stmtCheckPegawai->fetch(PDO::FETCH_ASSOC);
                    $pegawai_id = $existingPegawai['pegawai_id'];
                }

                // 7. COMMIT TRANSACTION
                $conn->commit();
                
                // 8. SET SESSION
                $_SESSION['user_id']      = $tokenData['user_id'];
                $_SESSION['pegawai_id']   = $pegawai_id;
                $_SESSION['email']        = $tokenData['email_login'];
                $_SESSION['user_type']    = $user_type;
                $_SESSION['nama_lengkap'] = $tokenData['nama_lengkap'];
                $_SESSION['first_login']  = true;

                // 9. REDIRECT KE KEAMANAN AKUN
                header('Location: ../users/pegawai/keamanan_akun.php?first_login=1');
                exit;
                
            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Gagal aktivasi akun: " . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

$page_title = 'Aktivasi Akun Pegawai';
include '../users/partials/navbar.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Poppins', sans-serif;
    }

    body {
        font-family: 'Poppins', sans-serif;
        overflow-x: hidden;
    }

    .login-wrapper {
        display: grid;
        grid-template-columns: 1fr 1fr;
        min-height: calc(100vh - 80px);
    }

    .left-side {
        background: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.2)),
            url('<?php echo BASE_URL; ?>users/assets/nest.jpg') center/cover;
        position: relative;
        display: flex;
        align-items: flex-end;
        padding: 60px;
    }

    .left-side::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 300px;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
    }

    .right-side {
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 40px;
    }

    .form-container {
        width: 100%;
        max-width: 480px;
    }

    .form-header {
        text-align: center;
        margin-bottom: 40px;
    }

    .logo-circle {
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30px;
    }

    .logo-circle img {
        width: 60px;
        height: 60px;
    }

    .form-header h1 {
        font-size: 36px;
        font-weight: 800;
        color: #1e3a5f;
        margin-bottom: 15px;
    }

    .form-header p {
        font-size: 15px;
        color: #546e7a;
        line-height: 1.6;
    }

    /* FORM GROUPS */
    .form-group {
        margin-bottom: 24px;
    }

    .form-label {
        display: block;
        font-size: 15px;
        font-weight: 600;
        color: #1e3a5f;
        margin-bottom: 10px;
    }

    .input-wrapper {
        position: relative;
    }

    .form-control {
        width: 100%;
        padding: 16px 18px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 15px;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s;
        background: white;
    }

    .form-control:focus {
        outline: none;
        border-color: #1e3a5f;
        box-shadow: 0 0 0 4px rgba(30, 58, 95, 0.1);
    }

    .form-control:read-only {
        background: #f0fdf4;
        border-color: #86efac;
        color: #065f46;
        cursor: default;
    }

    .form-control::placeholder {
        color: #bdbdbd;
    }

    .token-field {
        font-family: 'Courier New', monospace;
        font-size: 16px;
        font-weight: 600;
        letter-spacing: 1px;
        text-align: center;
    }

    .input-icon {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #9e9e9e;
        font-size: 18px;
        cursor: pointer;
    }

    /* BUTTON */
    .btn-submit {
        width: 100%;
        padding: 18px;
        background: #0056d2;
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 17px;
        font-weight: 700;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s;
        margin-top: 10px;
        box-shadow: 0 4px 15px rgba(0, 86, 210, 0.3);
    }

    .btn-submit:hover {
        background: #003da6;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 86, 210, 0.4);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    /* ERROR MESSAGE */
    .error-alert {
        background: #ffebee;
        border-left: 4px solid #f44336;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 24px;
    }

    .error-alert p {
        color: #c62828;
        font-size: 14px;
        margin: 0;
    }

    /* SUCCESS INFO */
    .success-info {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 24px;
    }

    .success-info p {
        color: #2e7d32;
        font-size: 14px;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* FOOTER */
    .footer-section {
        background: #0891b2;
        padding: 50px 40px 30px;
        color: white;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 40px;
        margin-bottom: 40px;
    }

    .footer-logo {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }

    .footer-logo img {
        width: 50px;
        height: 50px;
        background: white;
        padding: 8px;
        border-radius: 50%;
    }

    .footer-logo h3 {
        font-size: 20px;
        font-weight: 700;
    }

    .footer-column h4 {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
    }

    .footer-column p {
        font-size: 14px;
        line-height: 1.8;
        margin: 8px 0;
    }

    .social-icons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .social-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-decoration: none;
        transition: all 0.3s;
    }

    .social-icon:hover {
        background: white;
        color: #0891b2;
    }

    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.2);
        padding-top: 20px;
        text-align: center;
        font-size: 13px;
    }

    @media (max-width: 968px) {
        .login-wrapper {
            grid-template-columns: 1fr;
        }

        .left-side {
            display: none;
        }

        .footer-content {
            grid-template-columns: 1fr;
            gap: 30px;
        }
    }
</style>
</head>

<body>
    <div class="login-wrapper">
        <!-- LEFT SIDE - IMAGE -->
        <div class="left-side">
        </div>

        <!-- RIGHT SIDE - FORM -->
        <div class="right-side">
            <div class="form-container">
                <div class="form-header">
                    <div class="logo-circle">
                        <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo">
                    </div>
                    <h1>Selamat Datang</h1>
                    <p>Silakan Aktivasi Akun Anda untuk Bergabung<br>Bersama Politeknik NEST</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-alert">
                        <p><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($data_loaded): ?>
                    <div class="success-info">
                        <p>
                            <i class="bi bi-check-circle-fill"></i>
                            <strong>Token valid!</strong> Klik "Aktivasi Akun" untuk melanjutkan.
                        </p>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Alamat Email</label>
                            <div class="input-wrapper">
                                <input type="email" 
                                       class="form-control" 
                                       name="email" 
                                       value="<?= htmlspecialchars($auto_email) ?>"
                                       placeholder="Email terisi otomatis"
                                       readonly
                                       required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Token Aktivasi</label>
                            <div class="input-wrapper">
                                <input type="text" 
                                       class="form-control token-field" 
                                       name="token" 
                                       value="<?= htmlspecialchars($auto_token) ?>"
                                       readonly
                                       required>
                                <i class="bi bi-eye input-icon" id="toggleToken"></i>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="bi bi-check-circle-fill"></i> Aktivasi Akun
                        </button>
                    </form>
                <?php else: ?>
                    <div class="error-alert">
                        <p><i class="bi bi-exclamation-triangle-fill"></i> Link aktivasi tidak valid. Silakan gunakan link yang diberikan oleh admin.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-section">
        <div class="footer-content">
            <div class="footer-column">
                <div class="footer-logo">
                    <img src="<?php echo BASE_URL; ?>users/assets/logo.png" alt="Logo">
                    <h3>POLITEKNIK<br>NEST</h3>
                </div>
            </div>
            <div class="footer-column">
                <h4>Hubungi Kami</h4>
                <p><i class="bi bi-telephone-fill"></i> +628112951003</p>
                <p><i class="bi bi-whatsapp"></i> +628112951003</p>
                <p><i class="bi bi-envelope-fill"></i> info@politekniknest.ac.id</p>
            </div>
            <div class="footer-column">
                <h4>Alamat Kantor</h4>
                <p>Jl. Telukan - Cuplik, RT 03 RW 10,<br>Parangtoro, Kec.Grogol,Kab.Sukoharjo,<br>Jawa Tengah</p>
            </div>
        </div>

        <div class="social-icons">
            <a href="#" class="social-icon"><i class="bi bi-instagram"></i></a>
            <a href="#" class="social-icon"><i class="bi bi-facebook"></i></a>
            <a href="#" class="social-icon"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="social-icon"><i class="bi bi-tiktok"></i></a>
            <a href="#" class="social-icon"><i class="bi bi-linkedin"></i></a>
            <a href="#" class="social-icon"><i class="bi bi-youtube"></i></a>
        </div>

        <div class="footer-bottom">
            <p>Copyright © 2026 Politeknik Nest</p>
        </div>
    </div>

    <script>
        // Toggle token visibility
        const toggleToken = document.getElementById('toggleToken');
        const tokenInput = document.querySelector('.token-field');
        
        if (toggleToken && tokenInput) {
            toggleToken.addEventListener('click', function() {
                if (tokenInput.type === 'text') {
                    tokenInput.type = 'password';
                    this.classList.remove('bi-eye');
                    this.classList.add('bi-eye-slash');
                } else {
                    tokenInput.type = 'text';
                    this.classList.remove('bi-eye-slash');
                    this.classList.add('bi-eye');
                }
            });
        }
    </script>
</body>
</html>