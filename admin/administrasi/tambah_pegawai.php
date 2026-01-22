<?php
/**
 * Halaman: Tambah Pegawai
 * File: admin/tambah_pegawai.php
 * Deskripsi: Form dan proses tambah pegawai baru
 */

// ===== UNTUK AUTHORIZATION (DICOMMENT DULU) =====
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
//     header('Location: ../login.php');
//     exit();
// }
// ================================================

// Koneksi Database
require_once '../../config/database.php';

// Proses Form Submit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi input
    $errors = [];
    
    if(empty($_POST['nama_lengkap'])) {
        $errors[] = 'Nama lengkap wajib diisi';
    }
    
    if(empty($_POST['email'])) {
        $errors[] = 'Email wajib diisi';
    } elseif(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid';
    }
    
    if(empty($_POST['jenis_pegawai'])) {
        $errors[] = 'Jenis pegawai wajib diisi';
    }
    
    // Cek email duplikat
    if(empty($errors)) {
        $checkEmail = $conn->prepare("SELECT pegawai_id FROM pegawai WHERE email = :email");
        $checkEmail->bindParam(':email', $_POST['email']);
        $checkEmail->execute();
        
        if($checkEmail->rowCount() > 0) {
            $errors[] = 'Email sudah terdaftar';
        }
    }
    
    // Cek NIK duplikat jika diisi
    if(!empty($_POST['nik'])) {
        $checkNik = $conn->prepare("SELECT pegawai_id FROM pegawai WHERE nik = :nik");
        $checkNik->bindParam(':nik', $_POST['nik']);
        $checkNik->execute();
        
        if($checkNik->rowCount() > 0) {
            $errors[] = 'NIK sudah terdaftar';
        }
    }
    
    // Jika tidak ada error, proses insert
    if(empty($errors)) {
        $nidn = $_POST['nidn'] ?? null;
        if ($nidn === '') {
            $nidn = null;
        }

        $masa_kontrak_mulai   = $_POST['masa_kontrak_mulai'] ?? null;
        $masa_kontrak_selesai = $_POST['masa_kontrak_selesai'] ?? null;

        $nip = $_POST['nip'] ?? null;
        if ($nip === '') {
            $nip = null;
        }

        if ($masa_kontrak_mulai === '') {
            $masa_kontrak_mulai = null;
        }
        if ($masa_kontrak_selesai === '') {
            $masa_kontrak_selesai = null;
        }


        $conn->beginTransaction();
        
        try {
            // 1. Insert ke tabel users
            $default_password = password_hash('password123', PASSWORD_BCRYPT);
            $user_type = ($_POST['jenis_pegawai'] == 'dosen') ? 'dosen' : 'pegawai';
            
            $userQuery = "INSERT INTO users (email, password, user_type, is_active) 
                         VALUES (:email, :password, :user_type, 1)";
            
            $userStmt = $conn->prepare($userQuery);
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
            
            $pegawaiQuery = "INSERT INTO pegawai (
                user_id, nik, nama_lengkap, tempat_lahir, tanggal_lahir, 
                jenis_kelamin, email, no_telepon, alamat_domisili, alamat_ktp,
                nidn, prodi, nip, jenis_pegawai, is_dosen_nest
            ) VALUES (
                :user_id, :nik, :nama_lengkap, :tempat_lahir, :tanggal_lahir,
                :jenis_kelamin, :email, :no_telepon, :alamat_domisili, :alamat_ktp,
                :nidn, :prodi, :nip, :jenis_pegawai, :is_dosen_nest
            )";
            
            $pegawaiStmt = $conn->prepare($pegawaiQuery);
            $pegawaiStmt->bindParam(':user_id', $user_id);
            $pegawaiStmt->bindParam(':nik', $_POST['nik']);
            $pegawaiStmt->bindParam(':nama_lengkap', $_POST['nama_lengkap']);
            $pegawaiStmt->bindParam(':tempat_lahir', $_POST['tempat_lahir']);
            $pegawaiStmt->bindParam(':tanggal_lahir', $_POST['tanggal_lahir']);
            $pegawaiStmt->bindParam(':jenis_kelamin', $_POST['jenis_kelamin']);
            $pegawaiStmt->bindParam(':email', $_POST['email']);
            $pegawaiStmt->bindParam(':no_telepon', $_POST['no_telepon']);
            $pegawaiStmt->bindParam(':alamat_domisili', $_POST['alamat_domisili']);
            $pegawaiStmt->bindParam(':alamat_ktp', $_POST['alamat_ktp']);
            $pegawaiStmt->bindValue(
                ':nidn',
                $nidn,
                $nidn === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );

            $pegawaiStmt->bindParam(':prodi', $_POST['prodi']);
            $pegawaiStmt->bindValue(
                ':nip',
                $nip,
                $nip === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );

            $pegawaiStmt->bindParam(':jenis_pegawai', $_POST['jenis_pegawai']);
            $pegawaiStmt->bindParam(':is_dosen_nest', $is_dosen_nest);
            
            $pegawaiStmt->execute();
            $pegawai_id = $conn->lastInsertId();
            
            // 3. Insert ke tabel status_kepegawaian
            $admin_id = 1; // TEMPORARY
            
            $statusQuery = "INSERT INTO status_kepegawaian (
                pegawai_id, jabatan, jenis_kepegawaian, status_aktif, 
                unit_kerja, tanggal_mulai_kerja, masa_kontrak_mulai, 
                masa_kontrak_selesai, created_by
            ) VALUES (
                :pegawai_id, :jabatan, :jenis_kepegawaian, :status_aktif,
                :unit_kerja, :tanggal_mulai_kerja, :masa_kontrak_mulai,
                :masa_kontrak_selesai, :created_by
            )";
            
            $statusStmt = $conn->prepare($statusQuery);
            $statusStmt->bindParam(':pegawai_id', $pegawai_id);
            $statusStmt->bindParam(':jabatan', $_POST['jabatan']);
            $statusStmt->bindParam(':jenis_kepegawaian', $_POST['jenis_kepegawaian']);
            $statusStmt->bindParam(':status_aktif', $_POST['status_aktif']);
            $statusStmt->bindParam(':unit_kerja', $_POST['unit_kerja']);
            $statusStmt->bindParam(':tanggal_mulai_kerja', $_POST['tanggal_mulai_kerja']);
            $statusStmt->bindValue(
                ':masa_kontrak_mulai',
                $masa_kontrak_mulai,
                $masa_kontrak_mulai === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );

            $statusStmt->bindValue(
                ':masa_kontrak_selesai',
                $masa_kontrak_selesai,
                $masa_kontrak_selesai === null ? PDO::PARAM_NULL : PDO::PARAM_STR
            );

            $statusStmt->bindParam(':created_by', $admin_id);
            
            $statusStmt->execute();
            
            $conn->commit();
            
            // Redirect dengan pesan sukses
            header('Location: administrasiKepegawaian.php?success=1&message=' . urlencode('Data pegawai berhasil ditambahkan'));
            exit;
            
        } catch(Exception $e) {
            $conn->rollBack();
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pegawai - Administrasi Kepegawaian</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        .main-content {
        max-width: 1200px;
        margin: 0 auto;          
        padding: 40px;
    }


        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .breadcrumb {
            background: none;
            padding: 0;
            margin: 0;
            font-size: 14px;
        }

        .breadcrumb-item a {
            color: #2563eb;
            text-decoration: none;
        }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title i {
            color: #2563eb;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-label .required {
            color: #ef4444;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 10px 15px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Alert */
        .alert-custom {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        /* Buttons */
        .btn-primary-custom {
            background: #1f2937;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-custom:hover {
            background: #374151;
            color: white;
        }

        .btn-outline-custom {
            background: white;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-outline-custom:hover {
            background: #f9fafb;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .content-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php 
        // include '../sidebar/sidebar.php';  // UNCOMMENT saat sidebar sudah siap
    ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="administrasiKepegawaian.php?tab=data-pegawai" class="">
                        <i class=""></i> Kembali
                    </a>
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

            <form method="POST" action="">
                <!-- Data Pribadi -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user"></i>
                        Data Pribadi
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nik" class="form-label">NIK</label>
                            <input type="text" class="form-control" id="nik" name="nik" value="<?= $_POST['nik'] ?? '' ?>" placeholder="Masukkan NIK">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nama_lengkap" class="form-label">
                                Nama Lengkap <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= $_POST['nama_lengkap'] ?? '' ?>" placeholder="Masukkan nama lengkap" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" value="<?= $_POST['tempat_lahir'] ?? '' ?>" placeholder="Masukkan tempat lahir">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                            <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?= $_POST['tanggal_lahir'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                            <select class="form-select" id="jenis_kelamin" name="jenis_kelamin">
                                <option value="">-- Pilih --</option>
                                <option value="L" <?= ($_POST['jenis_kelamin'] ?? '') == 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($_POST['jenis_kelamin'] ?? '') == 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="no_telepon" class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" id="no_telepon" name="no_telepon" value="<?= $_POST['no_telepon'] ?? '' ?>" placeholder="Masukkan nomor telepon">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">
                            Email <span class="required">*</span>
                        </label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= $_POST['email'] ?? '' ?>" placeholder="Masukkan email" required>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_ktp" class="form-label">Alamat KTP</label>
                        <textarea class="form-control" id="alamat_ktp" name="alamat_ktp" rows="2" placeholder="Masukkan alamat sesuai KTP"><?= $_POST['alamat_ktp'] ?? '' ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_domisili" class="form-label">Alamat Domisili</label>
                        <textarea class="form-control" id="alamat_domisili" name="alamat_domisili" rows="2" placeholder="Masukkan alamat domisili saat ini"><?= $_POST['alamat_domisili'] ?? '' ?></textarea>
                    </div>
                </div>

                <!-- Data Kepegawaian -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-briefcase"></i>
                        Data Kepegawaian
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_pegawai" class="form-label">
                                Jenis Pegawai <span class="required">*</span>
                            </label>
                            <select class="form-select" id="jenis_pegawai" name="jenis_pegawai" required onchange="toggleDosenFields()">
                                <option value="">-- Pilih --</option>
                                <option value="dosen" <?= ($_POST['jenis_pegawai'] ?? '') == 'dosen' ? 'selected' : '' ?>>Dosen</option>
                                <option value="staff" <?= ($_POST['jenis_pegawai'] ?? '') == 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="tendik" <?= ($_POST['jenis_pegawai'] ?? '') == 'tendik' ? 'selected' : '' ?>>Tendik</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nip" class="form-label">NIP</label>
                            <input type="text" class="form-control" id="nip" name="nip" value="<?= $_POST['nip'] ?? '' ?>" placeholder="Masukkan NIP">
                        </div>
                    </div>

                    <!-- Fields khusus Dosen -->
                    <div id="dosenFields" style="display: <?= ($_POST['jenis_pegawai'] ?? '') == 'dosen' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nidn" class="form-label">NIDN</label>
                                <input type="text" class="form-control" id="nidn" name="nidn" value="<?= $_POST['nidn'] ?? '' ?>" placeholder="Masukkan NIDN">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="prodi" class="form-label">Program Studi</label>
                                <input type="text" class="form-control" id="prodi" name="prodi" value="<?= $_POST['prodi'] ?? '' ?>" placeholder="Masukkan program studi">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jabatan" class="form-label">Jabatan</label>
                            <input type="text" class="form-control" id="jabatan" name="jabatan" value="<?= $_POST['jabatan'] ?? '' ?>" placeholder="Masukkan jabatan">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="unit_kerja" class="form-label">Unit Kerja</label>
                            <input type="text" class="form-control" id="unit_kerja" name="unit_kerja" value="<?= $_POST['unit_kerja'] ?? '' ?>" placeholder="Masukkan unit kerja">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_kepegawaian" class="form-label">Jenis Kepegawaian</label>
                            <select class="form-select" id="jenis_kepegawaian" name="jenis_kepegawaian" onchange="toggleKontrakFields()">
                                <option value="">-- Pilih --</option>
                                <option value="tetap" <?= ($_POST['jenis_kepegawaian'] ?? '') == 'tetap' ? 'selected' : '' ?>>Tetap</option>
                                <option value="kontrak" <?= ($_POST['jenis_kepegawaian'] ?? '') == 'kontrak' ? 'selected' : '' ?>>Kontrak</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status_aktif" class="form-label">Status</label>
                            <select class="form-select" id="status_aktif" name="status_aktif">
                                <option value="aktif" <?= ($_POST['status_aktif'] ?? 'aktif') == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="tidak_aktif" <?= ($_POST['status_aktif'] ?? '') == 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="tanggal_mulai_kerja" class="form-label">Tanggal Mulai Kerja</label>
                        <input type="date" class="form-control" id="tanggal_mulai_kerja" name="tanggal_mulai_kerja" value="<?= $_POST['tanggal_mulai_kerja'] ?? '' ?>">
                    </div>

                    <!-- Fields khusus Kontrak -->
                    <div id="kontrakFields" style="display: <?= ($_POST['jenis_kepegawaian'] ?? '') == 'kontrak' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="masa_kontrak_mulai" class="form-label">Masa Kontrak Mulai</label>
                                <input type="date" class="form-control" id="masa_kontrak_mulai" name="masa_kontrak_mulai" value="<?= $_POST['masa_kontrak_mulai'] ?? '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="masa_kontrak_selesai" class="form-label">Masa Kontrak Selesai</label>
                                <input type="date" class="form-control" id="masa_kontrak_selesai" name="masa_kontrak_selesai" value="<?= $_POST['masa_kontrak_selesai'] ?? '' ?>">
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleDosenFields() {
            const jenisPegawai = document.getElementById('jenis_pegawai').value;
            const dosenFields = document.getElementById('dosenFields');
            
            if(jenisPegawai === 'dosen') {
                dosenFields.style.display = 'block';
            } else {
                dosenFields.style.display = 'none';
                document.getElementById('nidn').value = '';
                document.getElementById('prodi').value = '';
            }
        }

        function toggleKontrakFields() {
            const jenisKepegawaian = document.getElementById('jenis_kepegawaian').value;
            const kontrakFields = document.getElementById('kontrakFields');
            
            if(jenisKepegawaian === 'kontrak') {
                kontrakFields.style.display = 'block';
            } else {
                kontrakFields.style.display = 'none';
                document.getElementById('masa_kontrak_mulai').value = '';
                document.getElementById('masa_kontrak_selesai').value = '';
            }
        }
    </script>
</body>
</html>