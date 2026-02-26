<?php
//Start session
session_start();

//Cek login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../auth/login_pegawai.php");
    exit;
}

//Include helper untuk cek kelengkapan
require_once '../../config/check_completion.php';
require_once '../../config/database.php';

//Cek kelengkapan data pegawai
$check_result = checkPegawaiCompletion($conn, $_SESSION['pegawai_id']);

//Jika data belum lengkap, redirect ke administrasi
if (!$check_result['is_complete']) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'message' => 'Anda harus melengkapi data administrasi kepegawaian terlebih dahulu sebelum mengakses halaman ini.'
    ];
    header("Location: ../../users/pegawai/administrasi.php");
    exit;
}

//Ambil pegawai_id dari session
$pegawai_id = $_SESSION['pegawai_id'];

//Ambil data pegawai dari database (KODE ASLI LANJUT DI SINI)
$stmt = $conn->prepare("
    SELECT p.*, sk.jabatan, sk.tanggal_mulai_kerja 
    FROM pegawai p 
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id 
    WHERE p.pegawai_id = ?
");
$stmt->execute([$pegawai_id]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    die("Data pegawai tidak ditemukan. Pegawai ID: " . $pegawai_id);
}

$nama_lengkap = $pegawai['nama_lengkap'];
$tanggal_mulai_bekerja = $pegawai['tanggal_mulai_kerja'];

// Ambil template surat izin belajar
$stmt_template = $conn->prepare("
    SELECT * 
    FROM template_surat 
    WHERE jenis_template = ?
    LIMIT 1
");
$stmt_template->execute(['izin_belajar']);
$template = $stmt_template->fetch(PDO::FETCH_ASSOC);

// Cek apakah sudah pernah mengajukan
$stmt_check = $conn->prepare("
    SELECT * 
    FROM pengajuan_studi 
    WHERE pegawai_id = ?
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt_check->execute([$pegawai_id]);
$pengajuan_terakhir = $stmt_check->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_pengajuan'])) {
    $nama_institusi = trim($_POST['nama_institusi']);
    $program_studi = trim($_POST['program_studi']);
    $jenjang_pendidikan = $_POST['jenjang_pendidikan'];
    $tanggal_mulai_studi = $_POST['tanggal_mulai_studi'];
    
    // Validasi input
    $errors = [];
    
    if (empty($nama_institusi)) {
        $errors[] = "Nama Institusi wajib diisi";
    }
    
    if (empty($program_studi)) {
        $errors[] = "Program Studi wajib diisi";
    }
    
    if (empty($jenjang_pendidikan)) {
        $errors[] = "Jenjang Pendidikan wajib dipilih";
    }
    
    if (empty($tanggal_mulai_studi)) {
        $errors[] = "Tanggal Mulai Studi wajib diisi";
    }
    
    // Validasi file upload
    $surat_permohonan_path = null;
    $surat_permohonan_size = null;
    $surat_penerimaan_path = null;
    $surat_penerimaan_size = null;
    
    // Proses upload surat permohonan (wajib)
    if (!isset($_FILES['surat_permohonan']) || $_FILES['surat_permohonan']['error'] == UPLOAD_ERR_NO_FILE) {
        $errors[] = "Surat Permohonan wajib diupload";
    } elseif ($_FILES['surat_permohonan']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['surat_permohonan'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi tipe file
        if ($file_ext != 'pdf') {
            $errors[] = "Surat Permohonan harus berformat PDF";
        }
        
        // Validasi ukuran file
        $file_size_kb = $file['size'] / 1024;
        if ($file_size_kb > 5120) {
            $errors[] = "Ukuran Surat Permohonan maksimal 5 MB";
        }
        
        if (empty($errors)) {
            // Buat direktori jika belum ada
            $upload_dir = "../../uploads/pengajuan_studi/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate nama file unik
            $new_filename = $pegawai_id . "_permohonan_" . time() . ".pdf";
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $surat_permohonan_path = $upload_path;
                $surat_permohonan_size = round($file_size_kb);
            } else {
                $errors[] = "Gagal mengupload Surat Permohonan";
            }
        }
    }
    
    // Proses upload surat penerimaan (opsional)
    if (isset($_FILES['surat_penerimaan']) && $_FILES['surat_penerimaan']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['surat_penerimaan'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validasi tipe file
        if ($file_ext != 'pdf') {
            $errors[] = "Surat Penerimaan harus berformat PDF";
        }
        
        // Validasi ukuran file 
        $file_size_kb = $file['size'] / 1024;
        if ($file_size_kb > 5120) {
            $errors[] = "Ukuran Surat Penerimaan maksimal 5 MB";
        }
        
        if (count($errors) == 0 || (count($errors) > 0 && !in_array("Surat Penerimaan harus berformat PDF", $errors) && !in_array("Ukuran Surat Penerimaan maksimal 5 MB", $errors))) {
            // Buat direktori jika belum ada
            $upload_dir = "../../uploads/pengajuan_studi/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate nama file unik
            $new_filename = $pegawai_id . "_penerimaan_" . time() . ".pdf";
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $surat_penerimaan_path = $upload_path;
                $surat_penerimaan_size = round($file_size_kb);
            }
        }
    }
    
    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $stmt_insert = $conn->prepare("
            INSERT INTO pengajuan_studi 
            (pegawai_id, nama_lengkap, jenjang_pendidikan, tanggal_mulai_bekerja, 
            nama_institusi, program_studi, tanggal_mulai_studi, 
            surat_permohonan_path, surat_permohonan_size, 
            surat_penerimaan_path, surat_penerimaan_size, status_pengajuan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'diajukan')
        ");
        
        if ($stmt_insert->execute([
            $pegawai_id,
            $nama_lengkap,
            $jenjang_pendidikan,
            $tanggal_mulai_bekerja,
            $nama_institusi,
            $program_studi,
            $tanggal_mulai_studi,
            $surat_permohonan_path,
            $surat_permohonan_size,
            $surat_penerimaan_path,
            $surat_penerimaan_size
        ])) {
            // REDIRECT dengan session message
            $_SESSION['success_message'] = "Pengajuan studi lanjut berhasil dikirim!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $errors[] = "Gagal menyimpan pengajuan";
        }
    }
}

// Ambil success message dari session (jika ada)
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembangan Sumber Daya Manusia - Politeknik NEST</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fef3e2;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .main-content {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 20px;
            margin-bottom: 40px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 16px;
            color: #666;
        }
        
        .section-title {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f5a3b4;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        /* Flow Diagram */
        .flow-diagram {
            background: linear-gradient(to right, #ffe0e9, #fff);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
        }
        
        .flow-title {
            text-align: center;
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }
        
        .flow-subtitle {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .flow-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .flow-steps::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 15%;
            right: 15%;
            height: 2px;
            background: linear-gradient(to right, #f5a3b4, #a3c4f5);
            z-index: 0;
        }
        
        .flow-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .flow-step-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 3px solid #f5a3b4;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .flow-step-text {
            font-size: 13px;
            font-weight: 500;
            color: #333;
            line-height: 1.4;
        }
        
        .template-section {
            background: #f8f3ff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .template-note {
            font-size: 13px;
            color: #666;
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .btn-download {
            background: #f5a3b4;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-download:hover {
            background: #e68fa3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 163, 180, 0.3);
            color: white;
        }
        
        .btn-download::before {
            content: '⬇';
            font-size: 18px;
        }
        
        /* Form Styles */
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input,
        .form-select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #f5a3b4;
            box-shadow: 0 0 0 3px rgba(245, 163, 180, 0.1);
        }
        
        .form-input[type="date"] {
            cursor: pointer;
        }
        
        .file-upload-group {
            margin-bottom: 20px;
        }
        
        .file-upload-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }
        
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-input:hover {
            border-color: #f5a3b4;
            background: #fff5f7;
        }
        
        .file-note {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        html {
            scroll-behavior: smooth;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-start;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 35px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary {
            background: #2c5f7d;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1f4459;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 95, 125, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Status Section */
        .status-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .status-timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .status-item {
            position: relative;
            padding: 15px 0;
            margin-bottom: 10px;
        }
        
        .status-item::before {
            content: '';
            position: absolute;
            left: -32px;
            top: 20px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid #e0e0e0;
            background: white;
        }
        
        .status-item.active::before {
            border-color: #f5a3b4;
            background: #f5a3b4;
        }
        
        .status-item.completed::before {
            border-color: #4caf50;
            background: #4caf50;
        }
        
        /* Styling khusus untuk status ditolak */
        .status-item.rejected::before {
            border-color: #dc3545 !important;
            background: #dc3545 !important;
        }
        
        .status-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: -25px;
            top: 36px;
            width: 2px;
            height: 100%;
            background: #e0e0e0;
        }
        
        /* Hilangkan garis penghubung untuk status ditolak */
        .status-item.rejected::after {
            display: none !important;
        }
        
        .status-title {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .status-date {
            font-size: 12px;
            color: #999;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .flow-steps {
                flex-direction: column;
                gap: 20px;
            }
            
            .flow-steps::before {
                display: none;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../partials/navbar.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="page-header">
                <h1>Pengembangan Sumber Daya Manusia</h1>
                <p>Kelola pengembangan karir dan studi lanjut</p>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Terjadi kesalahan:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <h2 class="section-title">Studi Lanjut / Izin Belajar</h2>
            
            <!-- Alur Pengajuan -->
            <div class="flow-diagram">
                <div class="flow-title">Alur Pengajuan Permohonan Izin Belajar</div>
                <div class="flow-subtitle">Isi formulir dan tanda tangan</div>
                
                <div class="flow-steps">
                    <div class="flow-step">
                        <div class="flow-step-circle">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="flow-step-text">
                            Unduh Template<br>
                            Unduh formulir izin<br>
                            belajar yang disediakan
                        </div>
                    </div>

                    <div class="flow-step">
                        <div class="flow-step-circle">
                            <i class="bi bi-cloud-upload"></i>
                        </div>
                        <div class="flow-step-text">
                            Unggah Dokumen<br>
                            Unggah formulir yang<br>
                            telah diisi dan diteken
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Template Download -->
            <div class="template-section">
                <h3 style="font-size: 18px; margin-bottom: 15px; color: #333;">Template Surat Permohonan Izin Belajar/Studi Lanjut</h3>
                <p class="template-note">Silakan download isi dengan lengkap dan upload kembali setelah ditandatangani.</p>
                <?php if ($template): ?>
                    <a href="../../<?php echo htmlspecialchars($template['path_file']); ?>" class="btn-download" download>
                        Unduh Template
                    </a>
                <?php else: ?>
                    <p style="color: #dc3545; font-size: 14px;">Template belum tersedia. Silakan hubungi admin.</p>
                <?php endif; ?>
            </div>
            
            <!-- Status Pengajuan (jika sudah pernah mengajukan) -->
            <?php if ($pengajuan_terakhir): ?>
                <div class="status-section">
                    <h3 class="section-title">Status Pengajuan Terakhir</h3>
                    
                    <!-- Info Pengajuan -->
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Institusi</div>
                                <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($pengajuan_terakhir['nama_institusi']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Program Studi</div>
                                <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($pengajuan_terakhir['program_studi']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Jenjang</div>
                                <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo strtoupper($pengajuan_terakhir['jenjang_pendidikan']); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Tanggal Pengajuan</div>
                                <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo date('d F Y', strtotime($pengajuan_terakhir['created_at'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline Status (Hanya 2 Langkah) -->
                    <div class="status-timeline">
                        <!-- Step 1: Dokumen Diajukan -->
                        <div class="status-item <?php echo in_array($pengajuan_terakhir['status_pengajuan'], ['diajukan', 'ditinjau']) ? 'active' : 'completed'; ?>">
                            <div class="status-title">
                                Dokumen Diajukan
                                <?php if (in_array($pengajuan_terakhir['status_pengajuan'], ['diajukan', 'ditinjau'])): ?>
                                    <span class="status-badge badge-warning">Menunggu Persetujuan HRD</span>
                                <?php endif; ?>
                            </div>
                            <div class="status-date">
                                Diajukan pada <?php echo date('d F Y, H:i', strtotime($pengajuan_terakhir['created_at'])); ?> WIB
                            </div>
                        </div>
                        
                        <!-- Step 2: Hasil Keputusan (Disetujui/Ditolak) -->
                        <?php if ($pengajuan_terakhir['status_pengajuan'] == 'disetujui'): ?>
                            <!-- DISETUJUI -->
                            <div class="status-item completed">
                                <div class="status-title">
                                    <i class="bi bi-check-circle-fill" style="color: #4caf50; margin-right: 8px;"></i>
                                    Pengajuan Disetujui
                                    <span class="status-badge badge-success">Selesai</span>
                                </div>
                                <div class="status-date">
                                    Disetujui pada <?php echo date('d F Y, H:i', strtotime($pengajuan_terakhir['updated_at'])); ?> WIB
                                </div>
                            </div>
                        
                        <?php elseif ($pengajuan_terakhir['status_pengajuan'] == 'ditolak'): ?>
                            <!-- DITOLAK -->
                            <div class="status-item rejected">
                                <div class="status-title">
                                    <i class="bi bi-x-circle-fill" style="color: #dc3545; margin-right: 8px;"></i>
                                    Pengajuan Ditolak
                                    <span class="status-badge badge-danger">Ditolak</span>
                                </div>
                                <div class="status-date">
                                    Ditolak pada <?php echo date('d F Y, H:i', strtotime($pengajuan_terakhir['updated_at'])); ?> WIB
                                </div>
                            </div>
                        
                        <?php else: ?>
                            <!-- MENUNGGU KEPUTUSAN -->
                            <div class="status-item">
                                <div class="status-title">
                                    <i class="bi bi-hourglass-split" style="color: #999; margin-right: 8px;"></i>
                                    Menunggu Keputusan HRD
                                </div>
                                <div class="status-date">
                                    Dokumen sedang ditinjau oleh tim HRD
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Catatan Admin (Alasan Penolakan) -->
                    <?php if (!empty($pengajuan_terakhir['catatan_admin'])): ?>
                        <div style="margin-top: 25px; padding: 20px; background: <?php echo $pengajuan_terakhir['status_pengajuan'] == 'ditolak' ? '#fff5f5' : '#f8f9fa'; ?>; border-radius: 10px; border-left: 4px solid <?php echo $pengajuan_terakhir['status_pengajuan'] == 'ditolak' ? '#dc3545' : '#f5a3b4'; ?>;">
                            <div style="display: flex; align-items: start; gap: 10px;">
                                <i class="bi bi-<?php echo $pengajuan_terakhir['status_pengajuan'] == 'ditolak' ? 'exclamation-triangle-fill' : 'info-circle-fill'; ?>" style="font-size: 20px; color: <?php echo $pengajuan_terakhir['status_pengajuan'] == 'ditolak' ? '#dc3545' : '#f5a3b4'; ?>; margin-top: 2px;"></i>
                                <div>
                                    <strong style="font-size: 14px; color: #333; display: block; margin-bottom: 8px;">
                                        <?php echo $pengajuan_terakhir['status_pengajuan'] == 'ditolak' ? 'Alasan Penolakan:' : 'Catatan HRD:'; ?>
                                    </strong>
                                    <p style="margin: 0; font-size: 13px; color: #666; line-height: 1.7;">
                                        <?php echo nl2br(htmlspecialchars($pengajuan_terakhir['catatan_admin'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Tombol Aksi -->
                    <div style="margin-top: 25px; display: flex; gap: 12px; flex-wrap: wrap;">
                        <?php if ($pengajuan_terakhir['status_pengajuan'] == 'ditolak'): ?>
                            <button type="button" class="btn btn-primary" onclick="scrollToForm()" style="display: inline-flex; align-items: center; gap: 8px;">
                                <i class="bi bi-arrow-repeat"></i> Ajukan Ulang
                            </button>
                        <?php endif; ?>
                        
                        <?php if (!empty($pengajuan_terakhir['surat_permohonan_path'])): ?>
                            <a href="<?php echo htmlspecialchars($pengajuan_terakhir['surat_permohonan_path']); ?>" class="btn btn-secondary" target="_blank" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="bi bi-file-earmark-pdf"></i> Lihat Surat Permohonan
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($pengajuan_terakhir['surat_penerimaan_path'])): ?>
                            <a href="<?php echo htmlspecialchars($pengajuan_terakhir['surat_penerimaan_path']); ?>" class="btn btn-secondary" target="_blank" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="bi bi-file-earmark-check"></i> Lihat Surat Penerimaan
                            </a>
                        <?php endif; ?>
                    </div>
        
                </div>
            <?php endif; ?>
            
            <!-- Form Pengajuan -->
            <div class="form-section" id="form-pengajuan">
                <h3 class="section-title">Pengajuan Izin Belajar / Studi Lanjut</h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-input" value="<?php echo htmlspecialchars($nama_lengkap); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Institusi</label>
                            <input type="text" name="nama_institusi" class="form-input" placeholder="Universitas Gadjah Mada" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Jenjang Pendidikan</label>
                            <select name="jenjang_pendidikan" class="form-select" required>
                                <option value="">Pilih Jenjang</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3/Doktor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Program Studi</label>
                            <input type="text" name="program_studi" class="form-input" placeholder="Akuntansi" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tanggal Mulai Bekerja</label>
                            <input type="date" class="form-input" value="<?php echo $tanggal_mulai_bekerja; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Mulai Studi</label>
                            <input type="date" name="tanggal_mulai_studi" class="form-input" required>
                        </div>
                    </div>
                    
                    <div class="file-upload-group">
                        <label class="file-upload-label">Unggah Surat Permohonan <span style="color: red;">*</span></label>
                        <input type="file" name="surat_permohonan" class="file-upload-input" accept=".pdf" required>
                        <div class="file-note">Format: PDF maksimal 5 MB</div>
                    </div>
                    
                    <div class="file-upload-group">
                        <label class="file-upload-label">Unggah Surat Penerimaan dari Universitas (Opsional)</label>
                        <input type="file" name="surat_penerimaan" class="file-upload-input" accept=".pdf">
                        <div class="file-note">Format: PDF maksimal 5 MB</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="submit_pengajuan" class="btn btn-primary">Kirim Permohonan</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='dashboard.php'">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include '../partials/footer.php'; ?>
    
    <script>
        // Fungsi scroll ke form
        function scrollToForm() {
            const formSection = document.getElementById('form-pengajuan');
            if (formSection) {
                formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Highlight form sebentar
                formSection.style.transition = 'all 0.3s';
                formSection.style.boxShadow = '0 0 0 4px rgba(245, 163, 180, 0.3)';
                setTimeout(() => {
                    formSection.style.boxShadow = '';
                }, 2000);
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // File input styling
        const fileInputs = document.querySelectorAll('.file-upload-input');
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    this.style.borderColor = '#4caf50';
                    this.style.background = '#f1f8f4';
                }
            });
        });
    </script>
</body>
</html>