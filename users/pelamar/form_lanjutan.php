<?php

require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pelamar.php');
    exit();
}

if (!isset($_GET['lamaran_id'])) {
    header('Location: ' . BASE_URL . 'users/pelamar/tracking_lamaran.php');
    exit();
}

$lamaran_id = $_GET['lamaran_id'];
$user_id = $_SESSION['user_id'];

// Verify lamaran belongs to this user and status is lolos_administrasi
$query = "SELECT l.*, p.pelamar_id, p.nama_lengkap, lp.posisi 
          FROM lamaran l 
          JOIN pelamar p ON l.pelamar_id = p.pelamar_id 
          JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
          WHERE l.lamaran_id = ? AND p.user_id = ? AND l.status_lamaran = 'lolos_administrasi'";
$stmt = $conn->prepare($query);
$stmt->execute([$lamaran_id, $user_id]);
$lamaran = $stmt->fetch();

if (!$lamaran) {
    die('Lamaran tidak ditemukan atau Anda tidak memiliki akses untuk mengisi form ini.');
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_submit'])) {
    header('Content-Type: application/json');
    
    try {
        $conn->beginTransaction();
        
        // 1. Susunan Keluarga
        $stmt = $conn->prepare("
            INSERT INTO susunan_keluarga (lamaran_id, nama_ayah, pekerjaan_ayah, nama_ibu, pekerjaan_ibu)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nama_ayah = VALUES(nama_ayah),
                pekerjaan_ayah = VALUES(pekerjaan_ayah),
                nama_ibu = VALUES(nama_ibu),
                pekerjaan_ibu = VALUES(pekerjaan_ibu)
        ");
        $stmt->execute([
            $lamaran_id,
            $_POST['nama_ayah'],
            $_POST['pekerjaan_ayah'],
            $_POST['nama_ibu'],
            $_POST['pekerjaan_ibu']
        ]);
        
        $keluarga_id = $conn->lastInsertId();
        if (!$keluarga_id) {
            $stmt = $conn->prepare("SELECT keluarga_id FROM susunan_keluarga WHERE lamaran_id = ?");
            $stmt->execute([$lamaran_id]);
            $keluarga_id = $stmt->fetchColumn();
        }
        
        // 2. Saudara Kandung 
        $stmt = $conn->prepare("DELETE FROM saudara_kandung WHERE keluarga_id = ?");
        $stmt->execute([$keluarga_id]);
        
            // Insert new saudara kandung
        if (!empty($_POST['nama_saudara']) && !empty($_POST['nama_saudara'][0])) {
            $stmt = $conn->prepare("INSERT INTO saudara_kandung (keluarga_id, nama_saudara, pekerjaan_saudara) VALUES (?, ?, ?)");
            foreach ($_POST['nama_saudara'] as $i => $nama) {
                if (!empty($nama)) {
                    $pekerjaan = isset($_POST['pekerjaan_saudara'][$i]) ? $_POST['pekerjaan_saudara'][$i] : '';
                    $stmt->execute([$keluarga_id, $nama, $pekerjaan]);
                }
            }
        }
        
        // 3. Kontak Darurat
        $stmt = $conn->prepare("
            INSERT INTO kontak_darurat (lamaran_id, nama, hubungan, nomor_telepon)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                nama = VALUES(nama),
                hubungan = VALUES(hubungan),
                nomor_telepon = VALUES(nomor_telepon)
        ");
        $stmt->execute([
            $lamaran_id,
            $_POST['nama_kontak_darurat'],
            $_POST['hubungan_kontak_darurat'],
            $_POST['nomor_kontak_darurat']
        ]);
        
        // 4. Kondisi Kesehatan
        $stmt = $conn->prepare("
            INSERT INTO kondisi_kesehatan (lamaran_id, riwayat_sakit_berat, detail_penyakit)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                riwayat_sakit_berat = VALUES(riwayat_sakit_berat),
                detail_penyakit = VALUES(detail_penyakit)
        ");
        $stmt->execute([
            $lamaran_id,
            $_POST['riwayat_sakit_berat'],
            !empty($_POST['detail_penyakit']) ? $_POST['detail_penyakit'] : null
        ]);
        
        // 5. Riwayat Pekerjaan
        $stmt = $conn->prepare("
            INSERT INTO riwayat_pekerjaan_sebelumnya (lamaran_id, alasan_berhenti, gaji_terakhir, surat_keterangan_kerja)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                alasan_berhenti = VALUES(alasan_berhenti),
                gaji_terakhir = VALUES(gaji_terakhir),
                surat_keterangan_kerja = VALUES(surat_keterangan_kerja)
        ");
        $stmt->execute([
            $lamaran_id,
            !empty($_POST['alasan_berhenti']) ? $_POST['alasan_berhenti'] : null,
            !empty($_POST['gaji_terakhir']) ? $_POST['gaji_terakhir'] : null,
            $_POST['surat_keterangan_kerja']
        ]);
        
            // Upload Surat Keterangan Kerja ke tabel pengalaman_pelamar (jika ada)
        if ($_POST['surat_keterangan_kerja'] == 'ya' && isset($_FILES['file_skk']) && $_FILES['file_skk']['error'] == 0) {
            $upload_dir = '../../uploads/surat_keterangan_kerja/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['file_skk'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception('Format file SKK tidak didukung. Gunakan PDF, JPG, atau PNG');
            }
            
            $file_size = $file['size'] / 1024 / 1024; // MB
            if ($file_size > 5) {
                throw new Exception('Ukuran file SKK maksimal 5 MB');
            }
            
            $new_filename = 'skk_' . $lamaran_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Gagal upload surat keterangan kerja');
            }
            
                // Get pelamar_id from lamaran
            $stmt_pelamar = $conn->prepare("SELECT pelamar_id FROM lamaran WHERE lamaran_id = ?");
            $stmt_pelamar->execute([$lamaran_id]);
            $pelamar_id = $stmt_pelamar->fetchColumn();
            
                // Update pengalaman_pelamar dengan file SKK
            $stmt = $conn->prepare("
                UPDATE pengalaman_pelamar 
                SET skk_path = ?, 
                    skk_nama = ?, 
                    skk_size = ?,
                    updated_at = NOW()
                WHERE pelamar_id = ?
            ");
            $stmt->execute([
                $file_path,
                $file['name'],
                round($file_size, 2),
                $pelamar_id
            ]);
        }
        
        // 6. Surat SKCK (UPDATED - dengan upload file PDF)
        $skck_path = null;
        $skck_nama = null;
        $skck_size = null;
        
            // Upload file SKCK jika dipilih "ya"
        if ($_POST['punya_skck'] == 'ya' && isset($_FILES['file_skck']) && $_FILES['file_skck']['error'] == 0) {
            $upload_dir = '../../uploads/skck/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['file_skck'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Validasi: hanya PDF yang diterima
            if ($file_ext !== 'pdf') {
                throw new Exception('File SKCK harus dalam format PDF');
            }
            
            $file_size = $file['size'] / 1024 / 1024; // MB
            
            // Validasi: maksimal 5MB
            if ($file_size > 5) {
                throw new Exception('Ukuran file SKCK maksimal 5 MB. File Anda: ' . round($file_size, 2) . ' MB');
            }
            
            $new_filename = 'skck_' . $lamaran_id . '_' . time() . '.pdf';
            $file_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Gagal upload file SKCK');
            }
            
            $skck_path = $file_path;
            $skck_nama = $file['name'];
            $skck_size = round($file_size, 2);
        }
        
        // Insert/Update data SKCK
        $stmt = $conn->prepare("
            INSERT INTO surat_skck (lamaran_id, punya_skck, keterangan, path_file, nama_file, ukuran_file)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                punya_skck = VALUES(punya_skck),
                keterangan = VALUES(keterangan),
                path_file = VALUES(path_file),
                nama_file = VALUES(nama_file),
                ukuran_file = VALUES(ukuran_file),
                tanggal_upload = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $lamaran_id,
            $_POST['punya_skck'],
            $_POST['punya_skck'] == 'tidak' && !empty($_POST['keterangan_skck']) ? $_POST['keterangan_skck'] : null,
            $skck_path,
            $skck_nama,
            $skck_size
        ]);
        
        // 7. Kesediaan Komitmen
        $stmt = $conn->prepare("
            INSERT INTO kesediaan_komitmen (lamaran_id, kesediaan_tunduk_peraturan, waktu_mulai_kerja)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                kesediaan_tunduk_peraturan = VALUES(kesediaan_tunduk_peraturan),
                waktu_mulai_kerja = VALUES(waktu_mulai_kerja)
        ");
        $stmt->execute([
            $lamaran_id,
            $_POST['kesediaan_tunduk'],
            $_POST['waktu_mulai_kerja']
        ]);
        
        // 8. Upload Surat Pernyataan
        if (isset($_FILES['surat_pernyataan']) && $_FILES['surat_pernyataan']['error'] == 0) {
            $upload_dir = '../../uploads/surat_pernyataan/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file = $_FILES['surat_pernyataan'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($file_ext, $allowed_ext)) {
                throw new Exception('Format file tidak didukung. Gunakan PDF, JPG, atau PNG');
            }
            
            $file_size = $file['size'] / 1024 / 1024; // MB
            if ($file_size > 5) {
                throw new Exception('Ukuran file maksimal 5 MB');
            }
            
            $new_filename = 'surat_pernyataan_' . $lamaran_id . '_' . time() . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Gagal upload surat pernyataan');
            }
            
            // Save to database
            $stmt = $conn->prepare("
                INSERT INTO surat_pernyataan_pelamar (lamaran_id, path_file, nama_file, ukuran_file)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    path_file = VALUES(path_file),
                    nama_file = VALUES(nama_file),
                    ukuran_file = VALUES(ukuran_file)
            ");
            $stmt->execute([
                $lamaran_id,
                $file_path,
                $file['name'],
                round($file_size, 2)
            ]);
        }
        
        // 9. Update lamaran status to form_lanjutan
        $stmt = $conn->prepare("
            UPDATE lamaran 
            SET status_lamaran = 'form_lanjutan', 
                tanggal_update = NOW() 
            WHERE lamaran_id = ?
        ");
        $stmt->execute([$lamaran_id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Form lanjutan berhasil disimpan!'
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menyimpan data: ' . $e->getMessage()
        ]);
    }
    
    exit();
}

// Check if form already completed
$check_query = "SELECT * FROM susunan_keluarga WHERE lamaran_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->execute([$lamaran_id]);
$form_completed = $stmt->fetch();

$page_title = 'Formulir Lanjutan - Politeknik Nest';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: white;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-title {
            text-align: center;
            color: #2c3e50;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 16px;
            margin-bottom: 40px;
        }
        
        /* Progress Stepper */
        .progress-stepper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 25px;
            left: 60%;
            width: 80%;
            height: 3px;
            background: #e0e0e0;
            z-index: 0;
        }
        
        .step.active:not(:last-child)::after {
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f0f0f0;
            color: #999;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            transition: all 0.3s;
        }
        
        .step.active .step-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .step-label {
            font-size: 14px;
            font-weight: 600;
            color: #999;
        }
        
        .step.active .step-label {
            color: #2c3e50;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .form-step {
            display: none;
        }
        
        .form-step.active {
            display: block;
        }
        
        .step-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #667eea;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        
        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }
        
        .radio-group label {
            font-weight: normal;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        
        .radio-group input[type="radio"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .section-divider {
            border-top: 1px dashed #ddd;
            margin: 30px 0;
        }
        
        /* Button */
        .button-group {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 40px;
        }
        
        .btn-prev, .btn-next, .btn-submit {
            padding: 15px 40px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-prev {
            background: #e0e0e0;
            color: #666;
        }
        
        .btn-prev:hover {
            background: #d0d0d0;
        }
        
        .btn-next {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            margin-left: auto;
        }
        
        .btn-next:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            margin-left: auto;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(76, 175, 80, 0.4);
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .alert-warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
        }
        
        .required-mark {
            color: #f44336;
            font-weight: bold;
        }
        
        #addSaudaraBtn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        
        #addSaudaraBtn:hover {
            background: #5568d3;
        }
        
        .saudara-item {
            position: relative;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .remove-saudara {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #f44336;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
        }
        
        .remove-saudara:hover {
            background: #d32f2f;
        }
        
        .file-upload-box {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-box:hover {
            border-color: #667eea !important;
            background: #f8f9fa;
        }
        
        .pdf-icon {
            color: #f44336;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include '../partials/navbar_req.php'; ?>
    
    <div class="container">
        <h1 class="page-title">Formulir Lanjutan</h1>
        <p class="page-subtitle">Lengkapi data berikut untuk melanjutkan proses rekrutmen posisi <strong><?= htmlspecialchars($lamaran['posisi']) ?></strong></p>
        
        <!-- Progress Stepper -->
        <div class="progress-stepper">
            <div class="step active" id="stepIndicator1">
                <div class="step-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="step-label">Keluarga</div>
            </div>
            <div class="step" id="stepIndicator2">
                <div class="step-icon">
                    <i class="fas fa-phone"></i>
                </div>
                <div class="step-label">Kontak</div>
            </div>
            <div class="step" id="stepIndicator3">
                <div class="step-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="step-label">Pekerjaan</div>
            </div>
            <div class="step" id="stepIndicator4">
                <div class="step-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="step-label">Konfirmasi</div>
            </div>
        </div>
        
        <!-- Form Card -->
        <div class="form-card">
            <form id="formLanjutan" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="ajax_submit" value="1">
                <input type="hidden" name="lamaran_id" value="<?= $lamaran_id ?>">
                
                <!-- STEP 1: Susunan Keluarga & Kontak Darurat -->
                <div class="form-step active" id="step1">
                    <h2 class="step-title">
                        <i class="fas fa-users me-2"></i>
                        Susunan Keluarga & Kontak Darurat
                    </h2>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Ayah <span class="required-mark">*</span></label>
                            <input type="text" name="nama_ayah" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pekerjaan Ayah <span class="required-mark">*</span></label>
                            <input type="text" name="pekerjaan_ayah" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nama Ibu <span class="required-mark">*</span></label>
                            <input type="text" name="nama_ibu" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pekerjaan Ibu <span class="required-mark">*</span></label>
                            <input type="text" name="pekerjaan_ibu" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <div class="mb-3">
                        <label class="form-label">Saudara Kandung</label>
                        <p class="text-muted small mb-3">Tambahkan minimal 1 saudara kandung (jika ada)</p>
                        
                        <div id="saudaraContainer">
                            <div class="saudara-item">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Nama Saudara (Kakak/Adik)</label>
                                        <input type="text" name="nama_saudara[]" class="form-control">
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Pekerjaan/Sekolah</label>
                                        <input type="text" name="pekerjaan_saudara[]" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" id="addSaudaraBtn">
                            <i class="fas fa-plus me-2"></i>Tambah Saudara
                        </button>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <h5 class="mb-3">Kontak Darurat</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nama <span class="required-mark">*</span></label>
                            <input type="text" name="nama_kontak_darurat" class="form-control" placeholder="Nama kontak darurat" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hubungan <span class="required-mark">*</span></label>
                            <select name="hubungan_kontak_darurat" class="form-select" required>
                                <option value="">Pilih hubungan</option>
                                <option value="Orang Tua">Orang Tua</option>
                                <option value="Saudara Kandung">Saudara Kandung</option>
                                <option value="Pasangan">Pasangan</option>
                                <option value="Kerabat">Kerabat</option>
                                <option value="Teman">Teman</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Nomor Telepon <span class="required-mark">*</span></label>
                            <input type="tel" name="nomor_kontak_darurat" class="form-control" placeholder="08xx xxxx xxxx" required>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn-next" onclick="nextStep(2)">
                            Selanjutnya
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- STEP 2: Kesehatan -->
                <div class="form-step" id="step2">
                    <h2 class="step-title">
                        <i class="fas fa-heartbeat me-2"></i>
                        Kondisi Kesehatan
                    </h2>
                    
                    <div class="mb-4">
                        <label class="form-label">Apakah Anda punya riwayat penyakit? <span class="required-mark">*</span></label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="riwayat_sakit_berat" value="tidak" onclick="toggleDetail('detailPenyakit', false)" required>
                                Tidak
                            </label>
                            <label>
                                <input type="radio" name="riwayat_sakit_berat" value="ya" onclick="toggleDetail('detailPenyakit', true)">
                                Ya, ada riwayat
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-4" id="detailPenyakit" style="display: none;">
                        <label class="form-label">Sebutkan penyakit yang pernah diderita <span class="required-mark">*</span></label>
                        <textarea name="detail_penyakit" id="textDetailPenyakit" class="form-control" rows="3" placeholder="Contoh: Asma kronis sejak tahun 2018, Diabetes Tipe 2, dll"></textarea>
                        <small class="text-muted">Jelaskan secara detail riwayat sakit berat yang pernah diderita</small>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Catatan:</strong> Informasi kesehatan Anda bersifat rahasia dan hanya digunakan untuk keperluan administrasi kepegawaian.
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn-prev" onclick="prevStep(1)">
                            <i class="fas fa-arrow-left"></i>
                            Sebelumnya
                        </button>
                        <button type="button" class="btn-next" onclick="nextStep(3)">
                            Selanjutnya
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- STEP 3: Riwayat Pekerjaan & SKCK -->
                <div class="form-step" id="step3">
                    <h2 class="step-title">
                        <i class="fas fa-briefcase me-2"></i>
                        Riwayat Pekerjaan & Surat SKCK
                    </h2>
                    
                    <h5 class="mb-3">Riwayat Pekerjaan Sebelumnya</h5>
                    
                    <div class="mb-3">
                        <label class="form-label">Jelaskan alasan berhenti di tempat kerja sebelumnya</label>
                        <textarea name="alasan_berhenti" class="form-control" rows="3" placeholder="Contoh: Kontrak habis, Mencari peluang lebih baik, dll"></textarea>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gaji terakhir (Rp/bulan)</label>
                            <input type="number" name="gaji_terakhir" class="form-control" step="100000" placeholder="Contoh: 5000000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Surat keterangan kerja <span class="required-mark">*</span></label>
                            <select name="surat_keterangan_kerja" id="selectSKK" class="form-select" required onchange="toggleSKKUpload(this.value)">
                                <option value="">Pilih</option>
                                <option value="ya">Ya, saya punya</option>
                                <option value="tidak">Tidak punya</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Upload SKK Field (Hidden by default) -->
                    <div id="uploadSKKSection" style="display: none;" class="mb-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Upload Surat Keterangan Kerja</strong><br>
                            Silakan upload surat keterangan kerja dari perusahaan terakhir Anda.
                        </div>
                        <label class="form-label">Upload Surat Keterangan Kerja <span class="required-mark">*</span></label>
                        <div class="file-upload-box" onclick="document.getElementById('fileSKK').click()">
                            <input type="file" id="fileSKK" name="file_skk" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" onchange="updateFileLabel(this, 'skkLabel')">
                            <div id="skkLabel">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #17a2b8; margin-bottom: 10px;"></i>
                                <p style="margin: 0; color: #666; font-size: 14px;">
                                    Klik untuk upload Surat Keterangan Kerja<br>
                                    <small>Format: PDF, JPG, PNG (maksimal 5MB)</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <h5 class="mb-3">Dokumen SKCK (Surat Keterangan Catatan Kepolisian)</h5>
                    
                    <div class="alert-warning-box">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Perhatian:</strong> Jika Anda memiliki SKCK, wajib upload file dalam format <strong>PDF</strong> dengan ukuran maksimal <strong>5 MB</strong>.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Apakah Anda memiliki dokumen SKCK? <span class="required-mark">*</span></label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="punya_skck" value="ya" onclick="toggleSKCKUpload(true)" required>
                                Ya, punya SKCK
                            </label>
                            <label>
                                <input type="radio" name="punya_skck" value="tidak" onclick="toggleSKCKUpload(false)">
                                Tidak punya SKCK
                            </label>
                        </div>
                    </div>
                    
                    <!-- Upload SKCK Field (Hidden by default) -->
                    <div id="uploadSKCKSection" style="display: none;" class="mb-4">
                        <label class="form-label">Upload File SKCK (PDF) <span class="required-mark">*</span></label>
                        <div class="file-upload-box" onclick="document.getElementById('fileSKCK').click()">
                            <input type="file" id="fileSKCK" name="file_skck" accept=".pdf" style="display: none;" onchange="validateSKCKFile(this)">
                            <div id="skckLabel">
                                <i class="fas fa-file-pdf pdf-icon" style="font-size: 40px; margin-bottom: 10px;"></i>
                                <p style="margin: 0; color: #666; font-size: 14px;">
                                    Klik untuk upload file SKCK<br>
                                    <small><strong>HANYA format PDF</strong> | Maksimal 5 MB</small>
                                </p>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="fas fa-info-circle"></i> File harus dalam format PDF dan ukuran tidak lebih dari 5 MB
                        </small>
                    </div>
                    
                    <!-- Keterangan jika tidak punya SKCK -->
                    <div id="keteranganSKCKSection" style="display: none;" class="mb-4">
                        <label class="form-label">Keterangan <span class="text-muted">(Opsional)</span></label>
                        <textarea name="keterangan_skck" class="form-control" rows="2" placeholder="Contoh: Sedang dalam proses pembuatan SKCK, dll"></textarea>
                    </div>
                    
                    <div class="button-group">
                        <button type="button" class="btn-prev" onclick="prevStep(2)">
                            <i class="fas fa-arrow-left"></i>
                            Sebelumnya
                        </button>
                        <button type="button" class="btn-next" onclick="nextStep(4)">
                            Selanjutnya
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- STEP 4: Konfirmasi & Submit -->
                <div class="form-step" id="step4">
                    <h2 class="step-title">
                        <i class="fas fa-file-signature me-2"></i>
                        Surat Pernyataan & Konfirmasi
                    </h2>
                    
                    <!-- Surat Pernyataan Section -->
                    <div class="mb-4">
                        <h5 class="mb-3">Surat Pernyataan Kebenaran Dokumen</h5>
                        
                        <div class="info-box">
                            <strong><i class="fas fa-info-circle me-2"></i>Petunjuk Pengisian:</strong>
                            <ol style="margin: 10px 0 0 0; padding-left: 20px; font-size: 13px;">
                                <li>Download template surat pernyataan dengan klik tombol di bawah</li>
                                <li>Isi data template dengan lengkap</li>
                                <li>Bubuhi tanda tangan di atas materai Rp 10.000</li>
                                <li>Scan atau foto surat yang sudah ditandatangani</li>
                                <li>Upload file hasil scan/foto (Format: PDF, JPG, PNG, maksimal 5MB)</li>
                            </ol>
                        </div>
                        
                        <div class="text-center mb-4">
                            <button type="button" onclick="downloadTemplate()" 
                               class="btn btn-primary" 
                               style="padding: 12px 30px; border-radius: 10px; font-weight: 600; background: linear-gradient(135deg, #667eea, #764ba2); border: none; cursor: pointer;">
                                <i class="fas fa-download me-2"></i> Download Template Surat Pernyataan
                            </button>
                            <p class="text-muted small mt-2 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Klik tombol di atas untuk download template
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Upload Surat Pernyataan <span class="required-mark">*</span></label>
                            <div class="file-upload-box" onclick="document.getElementById('suratPernyataan').click()">
                                <input type="file" id="suratPernyataan" name="surat_pernyataan" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required onchange="updateFileLabel(this, 'suratLabel')">
                                <div id="suratLabel">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #667eea; margin-bottom: 10px;"></i>
                                    <p style="margin: 0; color: #666; font-size: 14px;">
                                        Klik untuk upload file<br>
                                        <small>atau drag and drop file di sini<br>
                                        Format: PDF, JPG, PNG (maksimal 5MB)</small>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-divider"></div>
                    
                    <!-- Pernyataan & Kesediaan -->
                    <div class="info-box">
                        <strong><i class="fas fa-exclamation-triangle me-2"></i>Pernyataan Penting!</strong><br>
                        Dengan melanjutkan, Anda menyatakan bahwa:
                        <ul style="margin: 10px 0 0 20px; padding: 0;">
                            <li>Semua data yang diisi adalah benar dan dapat dipertanggungjawabkan</li>
                            <li>Saya bersedia untuk memberikan dokumen pendukung jika diminta</li>
                            <li>Saya memahami bahwa pemalsuan data dapat berakibat dibatalkannya lamaran</li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Jika diterima, apakah Anda bersedia selalu tunduk pada peraturan perusahaan yang berlaku? <span class="required-mark">*</span></label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="kesediaan_tunduk" value="ya" required>
                                Ya, bersedia
                            </label>
                            <label>
                                <input type="radio" name="kesediaan_tunduk" value="tidak">
                                Tidak bersedia
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Berapa lama setelah kesepakatan, Anda bisa mulai bekerja? <span class="required-mark">*</span></label>
                        <select name="waktu_mulai_kerja" class="form-select" required>
                            <option value="">Pilih waktu</option>
                            <option value="segera">Segera (1-3 hari)</option>
                            <option value="1_minggu">1 minggu</option>
                            <option value="2_minggu">2 minggu</option>
                            <option value="1_bulan">1 bulan</option>
                        </select>
                    </div>
                                        
                    <div class="button-group">
                        <button type="button" class="btn-prev" onclick="prevStep(3)">
                            <i class="fas fa-arrow-left"></i>
                            Sebelumnya
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i>
                            Kirim Formulir
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
    <script>
        let currentStep = 1;
        
        // Download Template Function
        function downloadTemplate() {
            const link = document.createElement('a');
            link.href = '../../assets/templates/surat_pernyataan_template.pdf';
            link.download = 'Template_Surat_Pernyataan.pdf';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Show notification
            Swal.fire({
                icon: 'success',
                title: 'Download Dimulai',
                text: 'Template sedang didownload. Jika tidak muncul, periksa pengaturan download browser Anda.',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
        
        // Next Step
        function nextStep(step) {
            // Validate current step
            const currentStepEl = document.getElementById('step' + currentStep);
            const inputs = currentStepEl.querySelectorAll('[required]');
            let valid = true;
            
            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    valid = false;
                    return false;
                }
            });
            
            if (!valid) return;
            
            // Hide current step
            currentStepEl.classList.remove('active');
            document.getElementById('stepIndicator' + currentStep).classList.remove('active');
            
            // Show next step
            currentStep = step;
            document.getElementById('step' + step).classList.add('active');
            document.getElementById('stepIndicator' + step).classList.add('active');
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Previous Step
        function prevStep(step) {
            document.getElementById('step' + currentStep).classList.remove('active');
            document.getElementById('stepIndicator' + currentStep).classList.remove('active');
            
            currentStep = step;
            document.getElementById('step' + step).classList.add('active');
            document.getElementById('stepIndicator' + step).classList.add('active');
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        // Toggle detail fields
        function toggleDetail(fieldId, show) {
            const field = document.getElementById(fieldId);
            const textarea = field.querySelector('textarea');
            
            if (show) {
                field.style.display = 'block';
                // Make textarea required when shown
                if (textarea) {
                    textarea.setAttribute('required', 'required');
                }
            } else {
                field.style.display = 'none';
                // Remove required when hidden and clear value
                if (textarea) {
                    textarea.removeAttribute('required');
                    textarea.value = '';
                }
            }
        }
        
        // Toggle SKK Upload field
        function toggleSKKUpload(value) {
            const uploadSection = document.getElementById('uploadSKKSection');
            const fileInput = document.getElementById('fileSKK');
            
            if (value === 'ya') {
                uploadSection.style.display = 'block';
                fileInput.setAttribute('required', 'required');
            } else {
                uploadSection.style.display = 'none';
                fileInput.removeAttribute('required');
                fileInput.value = '';
                // Reset label
                document.getElementById('skkLabel').innerHTML = `
                    <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #17a2b8; margin-bottom: 10px;"></i>
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        Klik untuk upload Surat Keterangan Kerja<br>
                        <small>Format: PDF, JPG, PNG (maksimal 5MB)</small>
                    </p>
                `;
            }
        }
        
        // Toggle SKCK Upload field (NEW FUNCTION)
        function toggleSKCKUpload(show) {
            const uploadSection = document.getElementById('uploadSKCKSection');
            const keteranganSection = document.getElementById('keteranganSKCKSection');
            const fileInput = document.getElementById('fileSKCK');
            
            if (show) {
                // Show upload section
                uploadSection.style.display = 'block';
                keteranganSection.style.display = 'none';
                fileInput.setAttribute('required', 'required');
            } else {
                // Show keterangan section
                uploadSection.style.display = 'none';
                keteranganSection.style.display = 'block';
                fileInput.removeAttribute('required');
                fileInput.value = '';
                // Reset label
                document.getElementById('skckLabel').innerHTML = `
                    <i class="fas fa-file-pdf pdf-icon" style="font-size: 40px; margin-bottom: 10px;"></i>
                    <p style="margin: 0; color: #666; font-size: 14px;">
                        Klik untuk upload file SKCK<br>
                        <small><strong>HANYA format PDF</strong> | Maksimal 5 MB</small>
                    </p>
                `;
            }
        }
        
        // Validate SKCK File
        function validateSKCKFile(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = file.size / 1024 / 1024; 
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                // Check if PDF
                if (fileExt !== 'pdf') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Salah!',
                        html: 'File SKCK harus dalam format <strong>PDF</strong>.<br>File yang Anda pilih: <strong>' + fileExt.toUpperCase() + '</strong>',
                        confirmButtonColor: '#f44336'
                    });
                    input.value = '';
                    return;
                }
                
                // Check max 5MB
                if (fileSize > 5) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar!',
                        html: 'Ukuran file maksimal <strong>5 MB</strong>.<br>File yang Anda pilih: <strong>' + fileSize.toFixed(2) + ' MB</strong>',
                        confirmButtonColor: '#f44336'
                    });
                    input.value = '';
                    return;
                }
                
                // Update label if valid
                document.getElementById('skckLabel').innerHTML = `
                    <i class="fas fa-check-circle" style="font-size: 40px; color: #4CAF50; margin-bottom: 10px;"></i>
                    <p style="margin: 0; color: #2c3e50; font-size: 14px;">
                        <strong>${file.name}</strong><br>
                        <small style="color: #666;">${fileSize.toFixed(2)} MB</small>
                    </p>
                `;
                
                // Show success toast
                Swal.fire({
                    icon: 'success',
                    title: 'File Valid!',
                    text: 'File SKCK berhasil dipilih',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        }
        
        // Add Saudara
        document.getElementById('addSaudaraBtn').addEventListener('click', function() {
            const container = document.getElementById('saudaraContainer');
            const count = container.children.length;
            
            if (count >= 5) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Maksimal 5 Saudara',
                    text: 'Anda hanya dapat menambahkan maksimal 5 saudara kandung',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            const newSaudara = document.createElement('div');
            newSaudara.className = 'saudara-item';
            newSaudara.innerHTML = `
                <button type="button" class="remove-saudara" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <input type="text" name="nama_saudara[]" class="form-control" placeholder="Nama Saudara (Kakak/Adik)">
                    </div>
                    <div class="col-md-6 mb-2">
                        <input type="text" name="pekerjaan_saudara[]" class="form-control" placeholder="Pekerjaan/Sekolah">
                    </div>
                </div>
            `;
            
            container.appendChild(newSaudara);
        });
        
        // Update file label when file selected
        function updateFileLabel(input, labelId) {
            const label = document.getElementById(labelId);
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                // Check max 5MB
                if (fileSize > 5) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar!',
                        text: 'Ukuran file maksimal 5 MB. File yang Anda pilih: ' + fileSize + ' MB',
                        confirmButtonColor: '#f44336'
                    });
                    input.value = '';
                    return;
                }
                
                label.innerHTML = `
                    <i class="fas fa-check-circle" style="font-size: 40px; color: #4CAF50; margin-bottom: 10px;"></i>
                    <p style="margin: 0; color: #2c3e50; font-size: 14px;">
                        <strong>${file.name}</strong><br>
                        <small style="color: #666;">${fileSize} MB</small>
                    </p>
                `;
            }
        }
        
        // Form Submit
        document.getElementById('formLanjutan').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading
            Swal.fire({
                title: 'Mengirim formulir...',
                html: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Prepare form data
            const formData = new FormData(this);
            
            // Send AJAX request
            fetch('form_lanjutan.php?lamaran_id=<?= $lamaran_id ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#4CAF50',
                        confirmButtonText: '<i class="fas fa-check me-2"></i>Lihat Status'
                    }).then(() => {
                        window.location.href = '<?= BASE_URL ?>users/pelamar/tracking_lamaran.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message,
                        confirmButtonColor: '#f44336'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan: ' + error,
                    confirmButtonColor: '#f44336'
                });
            });
        });
        
        <?php if ($form_completed): ?>
        // Show warning if form already completed
        Swal.fire({
            icon: 'info',
            title: 'Form Sudah Pernah Diisi',
            text: 'Anda sudah mengisi form ini sebelumnya. Jika Anda submit lagi, data lama akan ditimpa.',
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Mengerti'
        });
        <?php endif; ?>
    </script>
</body>
</html>