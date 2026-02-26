<?php
//  UNTUK AUTHORIZATION  
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Koneksi Database
require_once '../../config/database.php';
require_once '../../includes/sync_user_type.php'; 

// Proses Form Submit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // ===== VALIDASI DATA PRIBADI =====

    if(empty(trim($_POST['nama_lengkap'] ?? ''))) {
        $errors[] = 'Nama lengkap wajib diisi';
    }

    if(empty($_POST['jenis_kelamin'])) {
        $errors[] = 'Jenis kelamin wajib diisi';
    }

    if(empty($_POST['tanggal_lahir'])) {
        $errors[] = 'Tanggal lahir wajib diisi';
    }

    if(empty($_POST['email'])) {
        $errors[] = 'Email wajib diisi';
    } elseif(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }

    // ===== VALIDASI DATA KEPEGAWAIAN =====

    if(empty($_POST['jenis_pegawai'])) {
        $errors[] = 'Jenis pegawai wajib diisi';
    }

    if(empty(trim($_POST['nip'] ?? ''))) {
        $errors[] = 'NIP wajib diisi';
    }

    if(empty(trim($_POST['jabatan'] ?? ''))) {
        $errors[] = 'Jabatan wajib diisi';
    }

    if(empty(trim($_POST['unit_kerja'] ?? ''))) {
        $errors[] = 'Unit kerja wajib diisi';
    }

    if(empty($_POST['jenis_kepegawaian'])) {
        $errors[] = 'Jenis kepegawaian wajib diisi';
    }

    if(empty($_POST['status_aktif'])) {
        $errors[] = 'Status wajib diisi';
    }

    if(empty($_POST['ptkp'])) {
        $errors[] = 'PTKP (status pajak) wajib diisi';
    }

    if(empty($_POST['tanggal_mulai_kerja'])) {
        $errors[] = 'Tanggal mulai kerja wajib diisi';
    }

    // Validasi khusus kontrak
    if(($_POST['jenis_kepegawaian'] ?? '') == 'kontrak') {
        if(empty($_POST['masa_kontrak_mulai'])) {
            $errors[] = 'Masa kontrak mulai wajib diisi untuk pegawai kontrak';
        }
        if(empty($_POST['masa_kontrak_selesai'])) {
            $errors[] = 'Masa kontrak selesai wajib diisi untuk pegawai kontrak';
        }
        if(!empty($_POST['masa_kontrak_mulai']) && !empty($_POST['masa_kontrak_selesai'])) {
            if($_POST['masa_kontrak_selesai'] <= $_POST['masa_kontrak_mulai']) {
                $errors[] = 'Masa kontrak selesai harus setelah masa kontrak mulai';
            }
        }
    }

    // Validasi khusus dosen
    if(($_POST['jenis_pegawai'] ?? '') == 'dosen') {
        if(empty(trim($_POST['prodi'] ?? ''))) {
            $errors[] = 'Program studi wajib diisi untuk dosen';
        }
    }

    // Cek email duplikat
    if(!empty($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $checkEmail = $conn->prepare("SELECT pegawai_id FROM pegawai WHERE email = :email");
        $checkEmail->bindParam(':email', $_POST['email']);
        $checkEmail->execute();
        if($checkEmail->rowCount() > 0) {
            $errors[] = 'Email sudah terdaftar';
        }
    }

    // Cek NIK duplikat
    if(!empty(trim($_POST['nik'] ?? ''))) {
        $checkNik = $conn->prepare("SELECT pegawai_id FROM pegawai WHERE nik = :nik");
        $checkNik->bindParam(':nik', $_POST['nik']);
        $checkNik->execute();
        if($checkNik->rowCount() > 0) {
            $errors[] = 'NIK sudah terdaftar';
        }
    }

    // Jika tidak ada error, proses insert
    if(empty($errors)) {
        function emptyToNull($value) {
            return (empty($value)) ? null : $value;
        }

        $nik              = emptyToNull(trim($_POST['nik']));
        $nidn             = emptyToNull($_POST['nidn']);
        $nip              = emptyToNull($_POST['nip']);
        $tempat_lahir     = emptyToNull(trim($_POST['tempat_lahir']));
        $no_telepon       = emptyToNull(trim($_POST['no_telepon']));
        $alamat_domisili  = emptyToNull(trim($_POST['alamat_domisili']));
        $alamat_ktp       = emptyToNull(trim($_POST['alamat_ktp']));
        $prodi            = emptyToNull($_POST['prodi']);
        $jabatan          = emptyToNull(trim($_POST['jabatan']));
        $unit_kerja       = emptyToNull(trim($_POST['unit_kerja']));
        $ptkp             = emptyToNull($_POST['ptkp']);
        $masa_kontrak_mulai   = emptyToNull($_POST['masa_kontrak_mulai']);
        $masa_kontrak_selesai = emptyToNull($_POST['masa_kontrak_selesai']);

        $conn->beginTransaction();

        try {
            // 1. Insert ke tabel users
            $default_password = password_hash('password123', PASSWORD_BCRYPT);
            $user_type = ($_POST['jenis_pegawai'] == 'dosen') ? 'dosen' : 'pegawai';

            $userStmt = $conn->prepare("INSERT INTO users (email, password, user_type, is_active) 
                                        VALUES (:email, :password, :user_type, 1)");
            $userStmt->bindParam(':email', $_POST['email']);
            $userStmt->bindParam(':password', $default_password);
            $userStmt->bindParam(':user_type', $user_type);
            $userStmt->execute();
            $user_id = $conn->lastInsertId();

            // 2. Insert ke tabel pegawai
            $is_dosen_nest = 0;
            if($_POST['jenis_pegawai'] == 'dosen' &&
               (strpos($_POST['email'], '@polnest.ac.id') !== false ||
                strpos($_POST['email'], '@nest.ac.id') !== false)) {
                $is_dosen_nest = 1;
            }

            $pegawaiStmt = $conn->prepare("INSERT INTO pegawai (
                user_id, nik, nama_lengkap, tempat_lahir, tanggal_lahir, 
                jenis_kelamin, email, no_telepon, alamat_domisili, alamat_ktp,
                nidn, prodi, nip, jenis_pegawai, is_dosen_nest
            ) VALUES (
                :user_id, :nik, :nama_lengkap, :tempat_lahir, :tanggal_lahir,
                :jenis_kelamin, :email, :no_telepon, :alamat_domisili, :alamat_ktp,
                :nidn, :prodi, :nip, :jenis_pegawai, :is_dosen_nest
            )");
            $pegawaiStmt->bindParam(':user_id', $user_id);
            $pegawaiStmt->bindValue(':nik', $nik, $nik === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindParam(':nama_lengkap', $_POST['nama_lengkap']);
            $pegawaiStmt->bindValue(':tempat_lahir', $tempat_lahir, $tempat_lahir === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindParam(':tanggal_lahir', $_POST['tanggal_lahir']);
            $pegawaiStmt->bindParam(':jenis_kelamin', $_POST['jenis_kelamin']);
            $pegawaiStmt->bindParam(':email', $_POST['email']);
            $pegawaiStmt->bindValue(':no_telepon', $no_telepon, $no_telepon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindValue(':alamat_domisili', $alamat_domisili, $alamat_domisili === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindValue(':alamat_ktp', $alamat_ktp, $alamat_ktp === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindValue(':nidn', $nidn, $nidn === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindValue(':prodi', $prodi, $prodi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindValue(':nip', $nip, $nip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $pegawaiStmt->bindParam(':jenis_pegawai', $_POST['jenis_pegawai']);
            $pegawaiStmt->bindParam(':is_dosen_nest', $is_dosen_nest);
            $pegawaiStmt->execute();
            $pegawai_id = $conn->lastInsertId();

            sinkronisasiUserType($conn, $user_id, $_POST['jenis_pegawai']);

            // 3. Insert ke tabel status_kepegawaian
            $admin_id = 1; // TEMPORARY

            $statusStmt = $conn->prepare("INSERT INTO status_kepegawaian (
                pegawai_id, jabatan, jenis_kepegawaian, status_aktif, ptkp,
                unit_kerja, tanggal_mulai_kerja, masa_kontrak_mulai,
                masa_kontrak_selesai, created_by
            ) VALUES (
                :pegawai_id, :jabatan, :jenis_kepegawaian, :status_aktif, :ptkp,
                :unit_kerja, :tanggal_mulai_kerja, :masa_kontrak_mulai,
                :masa_kontrak_selesai, :created_by
            )");
            $statusStmt->bindParam(':pegawai_id', $pegawai_id);
            $statusStmt->bindValue(':jabatan', $jabatan, $jabatan === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statusStmt->bindParam(':jenis_kepegawaian', $_POST['jenis_kepegawaian']);
            $statusStmt->bindParam(':status_aktif', $_POST['status_aktif']);
            $statusStmt->bindValue(':ptkp', $ptkp, $ptkp === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statusStmt->bindValue(':unit_kerja', $unit_kerja, $unit_kerja === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statusStmt->bindParam(':tanggal_mulai_kerja', $_POST['tanggal_mulai_kerja']);
            $statusStmt->bindValue(':masa_kontrak_mulai', $masa_kontrak_mulai,
                $masa_kontrak_mulai === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statusStmt->bindValue(':masa_kontrak_selesai', $masa_kontrak_selesai,
                $masa_kontrak_selesai === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $statusStmt->bindParam(':created_by', $admin_id);
            $statusStmt->execute();

            $conn->commit();

            header('Location: administrasiKepegawaian.php?success=1&message=' . urlencode('Data pegawai berhasil ditambahkan'));
            exit;

        } catch(Exception $e) {
            $conn->rollBack();
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Helper: cek apakah field harus tampil is-invalid setelah POST gagal
function isInvalid($field) {
    global $errors;
    if(empty($errors) || $_SERVER['REQUEST_METHOD'] !== 'POST') return '';
    return empty(trim($_POST[$field] ?? '')) ? 'is-invalid' : '';
}
function isInvalidSelect($field) {
    global $errors;
    if(empty($errors) || $_SERVER['REQUEST_METHOD'] !== 'POST') return '';
    return empty($_POST[$field] ?? '') ? 'is-invalid' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pegawai - Administrasi Kepegawaian</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }

        .main-content { max-width: 1200px; margin: 0 auto; padding: 40px; }

        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #1f2937; margin-bottom: 8px; }

        .breadcrumb { background: none; padding: 0; margin: 0; font-size: 14px; }
        .breadcrumb-item a { color: #2563eb; text-decoration: none; }

        .content-card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: 30px; }

        .form-section { margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e5e7eb; }
        .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

        .form-section-title {
            font-size: 18px; font-weight: 600; color: #1f2937;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .form-section-title i { color: #2563eb; }

        .form-label { font-weight: 500; color: #374151; margin-bottom: 8px; font-size: 14px; }
        .form-label .required { color: #ef4444; }

        .form-control, .form-select {
            border-radius: 8px; border: 1px solid #d1d5db;
            padding: 10px 15px; font-size: 14px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .alert-custom { border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; border: none; }
        .alert-danger { background: #fef2f2; color: #991b1b; }

        .btn-primary-custom {
            background: #1f2937; color: white; border: none;
            padding: 12px 24px; border-radius: 8px; font-weight: 500;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary-custom:hover { background: #374151; color: white; }

        .btn-outline-custom {
            background: white; border: 1px solid #d1d5db; color: #374151;
            padding: 12px 24px; border-radius: 8px; font-weight: 500;
        }
        .btn-outline-custom:hover { background: #f9fafb; }

        .form-actions {
            display: flex; gap: 10px; justify-content: flex-end;
            margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e7eb;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .content-card { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="administrasiKepegawaian.php?tab=data-pegawai">Kembali</a>
                    </li>
                    <li class="breadcrumb-item active">Tambah Pegawai</li>
                </ol>
            </nav>
            <h1><i class="fas fa-user-plus me-2"></i>Tambah Pegawai Baru</h1>
        </div>

        <!-- Content Card -->
        <div class="content-card">
            <?php if(!empty($errors)): ?>
            <div class="alert alert-danger alert-custom">
                <strong><i class="fas fa-exclamation-circle me-2"></i>Terjadi Kesalahan:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="formTambahPegawai" novalidate>

                <!-- ======================== DATA PRIBADI ======================== -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user"></i>Data Pribadi
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nik" class="form-label">NIK</label>
                            <input type="text"
                                class="form-control"
                                id="nik" name="nik"
                                value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>"
                                placeholder="Masukkan NIK">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="required">*</span></label>
                            <input type="text"
                                class="form-control <?= isInvalid('nama_lengkap') ?>"
                                id="nama_lengkap" name="nama_lengkap"
                                value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>"
                                placeholder="Masukkan nama lengkap" required>
                            <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                            <input type="text"
                                class="form-control"
                                id="tempat_lahir" name="tempat_lahir"
                                value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>"
                                placeholder="Masukkan tempat lahir">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date"
                                class="form-control <?= isInvalidSelect('tanggal_lahir') ?>"
                                id="tanggal_lahir" name="tanggal_lahir"
                                value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>" required>
                            <div class="invalid-feedback">Tanggal lahir wajib diisi.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin <span class="required">*</span></label>
                            <select class="form-select <?= isInvalidSelect('jenis_kelamin') ?>"
                                id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="">-- Pilih --</option>
                                <option value="L" <?= ($_POST['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                            <div class="invalid-feedback">Jenis kelamin wajib dipilih.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="no_telepon" class="form-label">No. Telepon</label>
                            <input type="text"
                                class="form-control"
                                id="no_telepon" name="no_telepon"
                                value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>"
                                placeholder="Masukkan nomor telepon">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="required">*</span></label>
                        <input type="email"
                            class="form-control <?= isInvalid('email') ?>"
                            id="email" name="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="Masukkan email" required>
                        <div class="invalid-feedback">Email wajib diisi dengan format yang valid.</div>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_ktp" class="form-label">Alamat KTP</label>
                        <textarea
                            class="form-control"
                            id="alamat_ktp" name="alamat_ktp" rows="2"
                            placeholder="Masukkan alamat sesuai KTP"><?= htmlspecialchars($_POST['alamat_ktp'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_domisili" class="form-label">Alamat Domisili</label>
                        <textarea
                            class="form-control"
                            id="alamat_domisili" name="alamat_domisili" rows="2"
                            placeholder="Masukkan alamat domisili saat ini"><?= htmlspecialchars($_POST['alamat_domisili'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- ======================== DATA KEPEGAWAIAN ======================== -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-briefcase"></i>Data Kepegawaian
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_pegawai" class="form-label">Jenis Pegawai <span class="required">*</span></label>
                            <select class="form-select <?= isInvalidSelect('jenis_pegawai') ?>"
                                id="jenis_pegawai" name="jenis_pegawai" required onchange="toggleDosenFields()">
                                <option value="">-- Pilih --</option>
                                <option value="dosen"  <?= ($_POST['jenis_pegawai'] ?? '') == 'dosen'  ? 'selected' : '' ?>>Dosen</option>
                                <option value="staff"  <?= ($_POST['jenis_pegawai'] ?? '') == 'staff'  ? 'selected' : '' ?>>Staff</option>
                                <option value="tendik" <?= ($_POST['jenis_pegawai'] ?? '') == 'tendik' ? 'selected' : '' ?>>Tendik</option>
                            </select>
                            <div class="invalid-feedback">Jenis pegawai wajib dipilih.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nip" class="form-label">NIP <span class="required">*</span></label>
                            <input type="text"
                                class="form-control <?= isInvalid('nip') ?>"
                                id="nip" name="nip"
                                value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>"
                                placeholder="Masukkan NIP" required>
                            <div class="invalid-feedback">NIP wajib diisi.</div>
                        </div>
                    </div>

                    <!-- Fields khusus Dosen -->
                    <div id="dosenFields" style="display: <?= ($_POST['jenis_pegawai'] ?? '') == 'dosen' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nidn" class="form-label">NIDN</label>
                                <input type="text" class="form-control" id="nidn" name="nidn"
                                    value="<?= htmlspecialchars($_POST['nidn'] ?? '') ?>"
                                    placeholder="Masukkan NIDN">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prodi" class="form-label">Program Studi <span class="required">*</span></label>
                                <input type="text"
                                    class="form-control <?= (!empty($errors) && ($_POST['jenis_pegawai'] ?? '') == 'dosen' && empty(trim($_POST['prodi'] ?? ''))) ? 'is-invalid' : '' ?>"
                                    id="prodi" name="prodi"
                                    value="<?= htmlspecialchars($_POST['prodi'] ?? '') ?>"
                                    placeholder="Masukkan program studi">
                                <div class="invalid-feedback">Program studi wajib diisi untuk dosen.</div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jabatan" class="form-label">Jabatan <span class="required">*</span></label>
                            <input type="text"
                                class="form-control <?= isInvalid('jabatan') ?>"
                                id="jabatan" name="jabatan"
                                value="<?= htmlspecialchars($_POST['jabatan'] ?? '') ?>"
                                placeholder="Contoh: Staff Administrasi" required>
                            <div class="invalid-feedback">Jabatan wajib diisi.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="unit_kerja" class="form-label">Unit Kerja <span class="required">*</span></label>
                            <input type="text"
                                class="form-control <?= isInvalid('unit_kerja') ?>"
                                id="unit_kerja" name="unit_kerja"
                                value="<?= htmlspecialchars($_POST['unit_kerja'] ?? '') ?>"
                                placeholder="Contoh: Bagian Keuangan" required>
                            <div class="invalid-feedback">Unit kerja wajib diisi.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_kepegawaian" class="form-label">Jenis Kepegawaian <span class="required">*</span></label>
                            <select class="form-select <?= isInvalidSelect('jenis_kepegawaian') ?>"
                                id="jenis_kepegawaian" name="jenis_kepegawaian" required onchange="toggleKontrakFields()">
                                <option value="">-- Pilih --</option>
                                <option value="tetap"   <?= ($_POST['jenis_kepegawaian'] ?? '') == 'tetap'   ? 'selected' : '' ?>>Tetap</option>
                                <option value="kontrak" <?= ($_POST['jenis_kepegawaian'] ?? '') == 'kontrak' ? 'selected' : '' ?>>Kontrak</option>
                            </select>
                            <div class="invalid-feedback">Jenis kepegawaian wajib dipilih.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status_aktif" class="form-label">Status <span class="required">*</span></label>
                            <select class="form-select" id="status_aktif" name="status_aktif" required>
                                <option value="aktif"       <?= ($_POST['status_aktif'] ?? 'aktif') == 'aktif'       ? 'selected' : '' ?>>Aktif</option>
                                <option value="tidak_aktif" <?= ($_POST['status_aktif'] ?? '')       == 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ptkp" class="form-label">PTKP (Status Pajak) <span class="required">*</span></label>
                            <select class="form-select <?= isInvalidSelect('ptkp') ?>"
                                id="ptkp" name="ptkp" required>
                                <option value="">-- Pilih PTKP --</option>
                                <option value="TK0" <?= ($_POST['ptkp'] ?? '') == 'TK0' ? 'selected' : '' ?>>TK/0 - Tidak Kawin (tanpa tanggungan)</option>
                                <option value="TK1" <?= ($_POST['ptkp'] ?? '') == 'TK1' ? 'selected' : '' ?>>TK/1 - Tidak Kawin (1 tanggungan)</option>
                                <option value="TK2" <?= ($_POST['ptkp'] ?? '') == 'TK2' ? 'selected' : '' ?>>TK/2 - Tidak Kawin (2 tanggungan)</option>
                                <option value="TK3" <?= ($_POST['ptkp'] ?? '') == 'TK3' ? 'selected' : '' ?>>TK/3 - Tidak Kawin (3 tanggungan)</option>
                                <option value="K0"  <?= ($_POST['ptkp'] ?? '') == 'K0'  ? 'selected' : '' ?>>K/0 - Kawin (tanpa tanggungan)</option>
                                <option value="K1"  <?= ($_POST['ptkp'] ?? '') == 'K1'  ? 'selected' : '' ?>>K/1 - Kawin (1 tanggungan)</option>
                                <option value="K2"  <?= ($_POST['ptkp'] ?? '') == 'K2'  ? 'selected' : '' ?>>K/2 - Kawin (2 tanggungan)</option>
                                <option value="K3"  <?= ($_POST['ptkp'] ?? '') == 'K3'  ? 'selected' : '' ?>>K/3 - Kawin (3 tanggungan)</option>
                            </select>
                            <div class="invalid-feedback">PTKP wajib dipilih.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_mulai_kerja" class="form-label">Tanggal Mulai Kerja <span class="required">*</span></label>
                            <input type="date"
                                class="form-control <?= isInvalidSelect('tanggal_mulai_kerja') ?>"
                                id="tanggal_mulai_kerja" name="tanggal_mulai_kerja"
                                value="<?= htmlspecialchars($_POST['tanggal_mulai_kerja'] ?? '') ?>" required>
                            <div class="invalid-feedback">Tanggal mulai kerja wajib diisi.</div>
                        </div>
                    </div>

                    <!-- Fields khusus Kontrak -->
                    <div id="kontrakFields" style="display: <?= ($_POST['jenis_kepegawaian'] ?? '') == 'kontrak' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="masa_kontrak_mulai" class="form-label">Masa Kontrak Mulai <span class="required">*</span></label>
                                <input type="date"
                                    class="form-control kontrak-required <?= (!empty($errors) && ($_POST['jenis_kepegawaian'] ?? '') == 'kontrak' && empty($_POST['masa_kontrak_mulai'] ?? '')) ? 'is-invalid' : '' ?>"
                                    id="masa_kontrak_mulai" name="masa_kontrak_mulai"
                                    value="<?= htmlspecialchars($_POST['masa_kontrak_mulai'] ?? '') ?>">
                                <div class="invalid-feedback">Masa kontrak mulai wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="masa_kontrak_selesai" class="form-label">Masa Kontrak Selesai <span class="required">*</span></label>
                                <input type="date"
                                    class="form-control kontrak-required <?= (!empty($errors) && ($_POST['jenis_kepegawaian'] ?? '') == 'kontrak' && empty($_POST['masa_kontrak_selesai'] ?? '')) ? 'is-invalid' : '' ?>"
                                    id="masa_kontrak_selesai" name="masa_kontrak_selesai"
                                    value="<?= htmlspecialchars($_POST['masa_kontrak_selesai'] ?? '') ?>">
                                <div class="invalid-feedback">Masa kontrak selesai wajib diisi.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="administrasiKepegawaian.php?tab=data-pegawai" class="btn btn-outline-custom">
                        <i class="fas fa-times me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary-custom">
                        <i class="fas fa-save me-1"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle field khusus Dosen
        function toggleDosenFields() {
            const jenisPegawai = document.getElementById('jenis_pegawai').value;
            const dosenFields  = document.getElementById('dosenFields');
            const prodiInput   = document.getElementById('prodi');

            if (jenisPegawai === 'dosen') {
                dosenFields.style.display = 'block';
                prodiInput.setAttribute('required', 'required');
            } else {
                dosenFields.style.display = 'none';
                prodiInput.removeAttribute('required');
                prodiInput.value = '';
                prodiInput.classList.remove('is-invalid', 'is-valid');
                document.getElementById('nidn').value = '';
            }
        }

        // Toggle field khusus Kontrak
        function toggleKontrakFields() {
            const jenisKepegawaian = document.getElementById('jenis_kepegawaian').value;
            const kontrakFields    = document.getElementById('kontrakFields');
            const kontrakInputs    = document.querySelectorAll('.kontrak-required');

            if (jenisKepegawaian === 'kontrak') {
                kontrakFields.style.display = 'block';
                kontrakInputs.forEach(input => input.setAttribute('required', 'required'));
            } else {
                kontrakFields.style.display = 'none';
                kontrakInputs.forEach(input => {
                    input.removeAttribute('required');
                    input.value = '';
                    input.classList.remove('is-invalid', 'is-valid');
                });
            }
        }

        // Validasi client-side sebelum submit
        document.getElementById('formTambahPegawai').addEventListener('submit', function(e) {
            let isValid = true;
            const fields = this.querySelectorAll('[required]');

            fields.forEach(function(field) {
                const inDosen   = field.closest('#dosenFields');
                const inKontrak = field.closest('#kontrakFields');
                if (inDosen   && inDosen.style.display   === 'none') return;
                if (inKontrak && inKontrak.style.display  === 'none') return;

                if (field.value.trim() === '') {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });

            // Validasi logika tanggal kontrak
            if (document.getElementById('jenis_kepegawaian').value === 'kontrak') {
                const mulai   = document.getElementById('masa_kontrak_mulai').value;
                const selesai = document.getElementById('masa_kontrak_selesai').value;
                if (mulai && selesai && selesai <= mulai) {
                    const elSelesai = document.getElementById('masa_kontrak_selesai');
                    elSelesai.classList.add('is-invalid');
                    elSelesai.nextElementSibling.textContent = 'Masa kontrak selesai harus setelah masa kontrak mulai.';
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });

        // Hapus is-invalid saat user mulai mengisi
        document.querySelectorAll('.form-control, .form-select').forEach(function(el) {
            ['input', 'change'].forEach(function(evt) {
                el.addEventListener(evt, function() {
                    if (this.value.trim() !== '') {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
            });
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleDosenFields();
            toggleKontrakFields();
        });
    </script>
</body>
</html>