<?php
// STEP 1: Start session
session_start();

// STEP 2: Include database
require_once '../../config/database.php';

// STEP 3: Cek login -DIGANTI
// if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in'])) {
//     header("Location: ../../auth/login_pegawai.php");
//     exit;
// }

//JADI INI
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../auth/login_pegawai.php");
    exit;
}

// STEP 4: Ambil pegawai_id
// Kalau admin akses dengan ?pegawai_id=X, kalau pegawai biasa pakai session
if ($_SESSION['user_type'] === 'admin' && isset($_GET['pegawai_id'])) {
    $pegawai_id = (int)$_GET['pegawai_id'];
} else {
    // Pegawai biasa hanya bisa lihat data sendiri
    $pegawai_id = $_SESSION['pegawai_id']; // ← PAKAI DARI SESSION
}

// STEP 5: Security check - pegawai biasa tidak boleh akses data orang lain
if ($_SESSION['user_type'] !== 'admin' && isset($_GET['pegawai_id']) && (int)$_GET['pegawai_id'] !== $_SESSION['pegawai_id']) {
    header("Location: administrasi.php"); // redirect ke data sendiri
    exit;
}

// Query Data Pegawai dengan Status Kepegawaian
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        sk.jabatan, 
        sk.jenis_kepegawaian, 
        sk.status_aktif, 
        sk.unit_kerja, 
        sk.tanggal_mulai_kerja,
        sk.masa_kontrak_mulai,
        sk.masa_kontrak_selesai
    FROM pegawai p
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
    WHERE p.pegawai_id = ?
");
$stmt->execute([$pegawai_id]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    die("Pegawai tidak ditemukan. Pegawai ID: " . $pegawai_id); // ← UNTUK DEBUG
}

// ... sisa kode ...

// Tentukan jenis pegawai untuk menampilkan dokumen yang sesuai
$is_dosen = ($pegawai['jenis_pegawai'] === 'dosen' || $pegawai['is_dosen_nest'] == 1);


// Query Dokumen Pegawai
$stmt_dokumen = $conn->prepare("
    SELECT * FROM dokumen_pegawai 
    WHERE pegawai_id = ?
    ORDER BY created_at DESC
");
$stmt_dokumen->execute([$pegawai_id]);
$dokumen = $stmt_dokumen->fetchAll(PDO::FETCH_ASSOC);

// Hitung Sisa Masa Kontrak
$sisa_kontrak_text = '-';
$badge_kontrak = 'badge-secondary';

if (!empty($pegawai['masa_kontrak_mulai']) && !empty($pegawai['masa_kontrak_selesai'])) {
    $tanggal_mulai = new DateTime($pegawai['masa_kontrak_mulai']);
    $tanggal_selesai = new DateTime($pegawai['masa_kontrak_selesai']);
    $sekarang = new DateTime();
    
    // Hitung selisih dari sekarang ke tanggal selesai
    $interval = $sekarang->diff($tanggal_selesai);
    
    // Jika kontrak sudah habis
    if ($sekarang > $tanggal_selesai) {
        $sisa_kontrak_text = 'Kontrak Habis';
        $badge_kontrak = 'badge-danger';
    } 
    // Jika kontrak belum dimulai
    elseif ($sekarang < $tanggal_mulai) {
        $sisa_kontrak_text = 'Belum Dimulai';
        $badge_kontrak = 'badge-warning';
    }
    // Kontrak masih berjalan
    else {
        $tahun = $interval->y;
        $bulan = $interval->m;
        $hari = $interval->d;
        
        $parts = [];
        if ($tahun > 0) $parts[] = $tahun . ' tahun';
        if ($bulan > 0) $parts[] = $bulan . ' bulan';
        if ($hari > 0) $parts[] = $hari . ' hari';  // Hapus kondisi tahun dan bulan == 0
        
        $sisa_kontrak_text = !empty($parts) ? implode(', ', $parts) : 'Kurang dari 1 hari';
        
        // Tentukan warna badge berdasarkan sisa waktu
        $total_bulan = ($tahun * 12) + $bulan;
        if ($total_bulan <= 1) {
            $badge_kontrak = 'badge-danger'; // Merah jika sisa <= 1 bulan
        } elseif ($total_bulan <= 3) {
            $badge_kontrak = 'badge-warning'; // Kuning jika sisa <= 3 bulan
        } else {
            $badge_kontrak = 'badge-success'; // Hijau jika masih lama
        }
    }
} elseif (!empty($pegawai['masa_kontrak_mulai'])) {
    // Jika hanya ada tanggal mulai (pegawai tetap)
    $sisa_kontrak_text = 'Tetap';
    $badge_kontrak = 'badge-info';
}

// Mapping jenis dokumen berdasarkan jenis pegawai (OPSIONAL - BISA KOSONG)
if ($is_dosen) {
    $jenis_dokumen_label = [
        'cv' => 'Curriculum Vitae (CV)',
        'ktp' => 'KTP (Kartu Tanda Penduduk)',
        'npwp' => 'NPWP (Nomor Pokok Wajib Pajak)',
        'ijazah' => 'Ijazah/Sertifikat Pendidikan',
        'surat_sehat' => 'Surat Keterangan Sehat',
        'surat_kerja_sebelumnya' => 'Surat Keterangan Kerja Sebelumnya'
    ];
    $page_title = 'Administrasi Kepegawaian - Dosen';
} else {
    $jenis_dokumen_label = [
        'cv' => 'Curriculum Vitae (CV)',
        'ktp' => 'KTP (Kartu Tanda Penduduk)',
        'npwp' => 'NPWP (Nomor Pokok Wajib Pajak)',
        'ijazah' => 'Ijazah/Sertifikat Pendidikan',
        'surat_sehat' => 'Surat Keterangan Sehat',
        'surat_kerja_sebelumnya' => 'Surat Keterangan Kerja Sebelumnya',
        'skck' => 'SKCK (Surat Keterangan Catatan Kepolisian)',
        'surat_bebas_napza' => 'Surat Keterangan Bebas Napza'
    ];
    $page_title = 'Administrasi Kepegawaian - Pegawai';
}

$message = '';
$message_type = '';

// Update Data Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_pegawai') {
        try {
            $sql = "UPDATE pegawai SET 
                    nama_lengkap = ?,
                    tempat_lahir = ?,
                    tanggal_lahir = ?,
                    jenis_kelamin = ?,
                    email = ?,
                    no_telepon = ?,
                    alamat_domisili = ?,
                    alamat_ktp = ?
                    WHERE pegawai_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $_POST['nama_lengkap'],
                $_POST['tempat_lahir'],
                $_POST['tanggal_lahir'],
                $_POST['jenis_kelamin'],
                $_POST['email'],
                $_POST['no_telepon'],
                $_POST['alamat_domisili'],
                $_POST['alamat_ktp'],
                $pegawai_id
            ]);
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?pegawai_id=" . $pegawai_id . "&success=1");
            exit;
        } catch (Exception $e) {
            $message = 'Terjadi kesalahan saat menyimpan data.';
            $message_type = 'danger';
        }
    }
    
    // Edit Dokumen Handler
    if (isset($_POST['action']) && $_POST['action'] === 'edit_dokumen') {
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_dokumen'];
            $jenis_dokumen = $_POST['jenis_dokumen'];
            $dokumen_pegawai_id = $_POST['dokumen_pegawai_id'];
            
            // Validasi tipe file
            $allowed_type = 'application/pdf';
            $file_type = mime_content_type($file['tmp_name']);
            
            if ($file_type !== $allowed_type) {
                $message = 'Hanya file PDF yang diperbolehkan!';
                $message_type = 'danger';
            }
            elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = 'Ukuran file maksimal 5 MB!';
                $message_type = 'danger';
            }
            else {
                // Get old document
                $stmt_old = $conn->prepare("SELECT * FROM dokumen_pegawai WHERE dokumen_pegawai_id = ?");
                $stmt_old->execute([$dokumen_pegawai_id]);
                $old_doc = $stmt_old->fetch(PDO::FETCH_ASSOC);
                
                if ($old_doc) {
                    // Generate filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $pegawai_id . '_' . $jenis_dokumen . '_' . time() . '.' . $extension;
                    $upload_dir = '../../uploads/dokumen/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $destination = $upload_dir . $filename;
                    $path_file = 'uploads/dokumen/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Delete old file
                        if (file_exists($upload_dir . $old_doc['nama_file'])) {
                            unlink($upload_dir . $old_doc['nama_file']);
                        }
                        
                        // Update database
                        $stmt_update = $conn->prepare("UPDATE dokumen_pegawai SET nama_file = ?, path_file = ?, ukuran_file = ?, updated_at = NOW() WHERE dokumen_pegawai_id = ?");
                        $stmt_update->execute([$filename, $path_file, $file['size'], $dokumen_pegawai_id]);
                        
                        header("Location: " . $_SERVER['PHP_SELF'] . "?pegawai_id=" . $pegawai_id . "&edit_success=1");
                        exit;
                    }
                }
            }
        }
    }

    // Upload Dokumen Handler
    if ($_POST['action'] === 'upload_dokumen') {
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_dokumen'];
            $jenis_dokumen = $_POST['jenis_dokumen'];
            
            // Validasi tipe file (hanya PDF)
            $allowed_type = 'application/pdf';
            $file_type = mime_content_type($file['tmp_name']);
            
            if ($file_type !== $allowed_type) {
                $message = 'Hanya file PDF yang diperbolehkan!';
                $message_type = 'danger';
            }
            // Validasi ukuran file (maksimal 5 MB)
            elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = 'Ukuran file maksimal 5 MB!';
                $message_type = 'danger';
            }
            else {
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $pegawai_id . '_' . $jenis_dokumen . '_' . time() . '.' . $extension;
                $upload_dir = '../../uploads/dokumen/';
                
                // Create directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old document if exists
                    $stmt_check = $conn->prepare("SELECT * FROM dokumen_pegawai WHERE pegawai_id = ? AND jenis_dokumen = ?");
                    $stmt_check->execute([$pegawai_id, $jenis_dokumen]);
                    $old_doc = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_doc && file_exists($upload_dir . $old_doc['nama_file'])) {
                        unlink($upload_dir . $old_doc['nama_file']);
                        // Update existing record
                        $stmt_update = $conn->prepare("UPDATE dokumen_pegawai SET nama_file = ?, path_file = ?, ukuran_file = ?, updated_at = NOW() WHERE dokumen_pegawai_id = ?");
                        $stmt_update->execute([$filename, $path_file, $file['size'], $old_doc['dokumen_pegawai_id']]);
                    } else {
                        // Insert new record
                        $stmt_insert = $conn->prepare("INSERT INTO dokumen_pegawai (pegawai_id, jenis_dokumen, nama_file, ukuran_file) VALUES (?, ?, ?, ?)");
                        $stmt_insert->execute([$pegawai_id, $jenis_dokumen, $filename, $file['size']]);
                    }
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?pegawai_id=" . $pegawai_id . "&upload_success=1");
                    exit;
                } else {
                    $message = 'Gagal mengupload file!';
                    $message_type = 'danger';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SDM POLNEST</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #f1f5f9;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1e293b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #fef3e2;
            color: var(--text-dark);
        }

        /* Navbar Custom */
        .navbar-custom {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-dark) !important;
        }

        /* Header Simple */
        .header-section {
            background-color: transparent;
            padding: 2rem 0 1rem 0;
            margin-bottom: 1.5rem;
        }

        .header-section h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-dark);
        }

        .header-section p {
            margin: 0.25rem 0 0 0;
            color: #64748b;
            font-size: 0.875rem;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            background-color: #ffffff;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-dark);
            border-radius: 12px 12px 0 0 !important;
        }

        .card-body {
            padding: 1.25rem;
        }

        /* Info Row Styling */
        .info-grid {
            display: grid;
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }

        .info-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--text-dark);
            font-size: 0.875rem;
            font-weight: 500;
            text-align: right;
        }

        /* Badge dengan warna solid */
        .badge {
            padding: 0.35rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .badge-success {
            background-color: #10b981;
            color: #ffffff;
        }

        .badge-warning {
            background-color: #f59e0b;
            color: #ffffff;
        }

        .badge-info {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .badge-danger {
            background-color: #ef4444;
            color: #ffffff;
        }

        .badge-secondary {
            background-color: #6b7280;
            color: #ffffff;
        }

        /* Form Styling */
        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.6rem 0.875rem;
            font-size: 0.875rem;
            background-color: #f8fafc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: #ffffff;
        }

        .form-control:disabled, .form-select:disabled {
            background-color: #f8fafc;
            color: #64748b;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        /* Button Styling */
        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--success-color);
            border: none;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-outline-secondary {
            border: 1px solid #d1d5db;
            color: #6b7280;
            background-color: transparent;
        }

        .btn-outline-secondary:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.8125rem;
        }

        /* Document Item Styling */
        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background-color: #f8fafc;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }

        .document-item:hover {
            background-color: #f1f5f9;
        }

        .document-left {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-dark);
            margin-bottom: 0.125rem;
        }

        .document-meta {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .document-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .btn-outline-primary {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .btn-outline-primary:hover {
            background-color: #3b82f6;
            color: white;
        }

        .btn-outline-danger {
            border-color: #ef4444;
            color: #ef4444;
        }

        .btn-outline-danger:hover {
            background-color: #ef4444;
            color: white;
        }

        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #e5e7eb;
            margin-bottom: 1rem;
        }

        .progress-bar {
            background-color: #10b981;
            border-radius: 10px;
        }

        .progress-text {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Alert Styling */
        .alert {
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
        }

        /* Upload Button for Empty Document */
        .btn-upload {
            background-color: #ffffff;
            border: 1px dashed #d1d5db;
            color: #6b7280;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            transition: all 0.2s;
        }

        .btn-upload:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background-color: #eff6ff;
        }

        .badge-not-uploaded {
            background-color: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 768px) {
            .document-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .document-actions {
                margin-top: 0.75rem;
                width: 100%;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-value {
                text-align: left;
                margin-top: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../partials/navbar.php'; ?>

    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <h1>Administrasi Kepegawaian</h1>
            <p>Kelola data identitas dan dokumen kepegawaian secara lengkap</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil!</strong> Data pegawai telah diperbarui.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['upload_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil!</strong> Dokumen berhasil diupload.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['edit_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                Dokumen berhasil diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>


        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

       <!-- Status Kepegawaian -->
        <div class="card">
            <div class="card-header">
                Status Kepegawaian
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <!-- Jabatan -->
                    <div class="info-item">
                        <span class="info-label">Jabatan</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pegawai['jabatan'] ?? '-') ?>
                        </span>
                    </div>
                    
                    <!-- Jenis Kepegawaian -->
                    <div class="info-item">
                        <span class="info-label">Jenis Kepegawaian</span>
                        <span class="info-value">
                            <span class="badge badge-info"><?= ucfirst($pegawai['jenis_kepegawaian'] ?? 'Staff') ?></span>
                        </span>
                    </div>
                    
                    <!-- Masa Kontrak (Teks biasa tanpa badge) -->
                    <div class="info-item">
                        <span class="info-label">Sisa Kontrak</span>
                        <span class="info-value">
                            <?= $sisa_kontrak_text ?>
                        </span>
                    </div>
                    
                    <!-- Status Kepegawaian -->
                    <div class="info-item">
                        <span class="info-label">Status Kepegawaian</span>
                        <span class="info-value">
                            <span class="badge <?= ($pegawai['status_aktif'] ?? 'aktif') === 'aktif' ? 'badge-success' : 'badge-warning' ?>">
                                <?= ucfirst($pegawai['status_aktif'] ?? 'Aktif') ?>
                            </span>
                        </span>
                    </div>
                    
                    <!-- Unit Kerja -->
                    <div class="info-item">
                        <span class="info-label">Unit Kerja</span>
                        <span class="info-value"><?= htmlspecialchars($pegawai['unit_kerja'] ?? '-') ?></span>
                    </div>
                    
                    <!-- Tanggal Mulai Kerja -->
                    <div class="info-item">
                        <span class="info-label">Tanggal Mulai Kerja</span>
                        <span class="info-value">
                            <?= $pegawai['tanggal_mulai_kerja'] ? date('d F Y', strtotime($pegawai['tanggal_mulai_kerja'])) : '-' ?>
                        </span>
                    </div>
                    
                    <!-- Tanggal Selesai Kontrak -->
                    <div class="info-item">
                        <span class="info-label">Tanggal Selesai Kontrak</span>
                        <span class="info-value">
                            <?php 
                            if (!empty($pegawai['masa_kontrak_selesai'])) {
                                echo date('d F Y', strtotime($pegawai['masa_kontrak_selesai']));
                            } elseif (!empty($pegawai['masa_kontrak_mulai'])) {
                                echo 'Pegawai Tetap';
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>


        <!-- Data Identitas Pegawai -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Data Identitas Pegawai</span>
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" id="btnEdit" onclick="toggleEdit()">
                        <i class="bi bi-pencil me-1"></i>Edit Data
                    </button>
                    <button class="btn btn-success btn-sm" id="btnSave" style="display: none;" onclick="document.getElementById('formPegawai').submit()">
                        <i class="bi bi-check2 me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="formPegawai">
                    <input type="hidden" name="action" value="update_pegawai">
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">NIK Pegawai</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($pegawai['nik'] ?? '-') ?>" disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control editable" 
                                   value="<?= htmlspecialchars($pegawai['nama_lengkap'] ?? 'Contoh : Soekarno') ?>" disabled required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="form-control editable" 
                                   value="<?= htmlspecialchars($pegawai['tempat_lahir'] ?? 'Contoh : Yogyakarta') ?>" disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control editable" 
                                   value="<?= htmlspecialchars($pegawai['tanggal_lahir'] ?? '2005-01-06') ?>" disabled>
                        </div>
                        
                                                <div class="col-12 mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select editable" disabled>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="L" <?= ($pegawai['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($pegawai['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control editable" 
                                   value="<?= htmlspecialchars($pegawai['email'] ?? 'soekarno@example.com') ?>" disabled required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="no_telepon" class="form-control editable" 
                                   value="<?= htmlspecialchars($pegawai['no_telepon'] ?? '08123456789') ?>" disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Alamat KTP</label>
                            <textarea name="alamat_ktp" class="form-control editable" rows="2" disabled><?= htmlspecialchars($pegawai['alamat_ktp'] ?? 'Jl. Contoh No. 123, Jakarta') ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea name="alamat_domisili" class="form-control editable" rows="2" disabled><?= htmlspecialchars($pegawai['alamat_domisili'] ?? 'Jl. Contoh No. 123, Jakarta') ?></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Kelengkapan Dokumen -->
        <div class="card">
            <div class="card-header">
                Kelengkapan Dokumen
                <small class="text-muted">(Opsional)</small>
            </div>
            <div class="card-body">
                <!-- Progress Bar -->
                <?php 
                $total_dokumen = count($jenis_dokumen_label);
                $dokumen_array = [];
                foreach ($dokumen as $d) {
                    $dokumen_array[$d['jenis_dokumen']] = $d;
                }
                $dokumen_lengkap = count($dokumen_array);
                $persentase = $total_dokumen > 0 ? ($dokumen_lengkap / $total_dokumen) * 100 : 0;
                ?>
                <div class="progress-text">
                    <strong><?= $dokumen_lengkap ?> dari <?= $total_dokumen ?></strong> dokumen telah diunggah
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" 
                         style="width: <?= $persentase ?>%;" 
                         aria-valuenow="<?= $persentase ?>" aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>

                <!-- Document List -->
                <?php foreach ($jenis_dokumen_label as $jenis => $label): ?>
                    <?php 
                    $doc = isset($dokumen_array[$jenis]) ? $dokumen_array[$jenis] : null;
                    ?>
                    <div class="document-item">
                        <div class="document-left">
                            <div class="document-icon">
                                <i class="bi bi-file-earmark-pdf-fill text-danger" style="font-size: 1.25rem;"></i>
                            </div>
                            <div class="document-info">
                                <div class="document-name"><?= $label ?></div>
                                <div class="document-meta">
                                    <?php if ($doc): ?>
                                        <span class="badge badge-success me-1">
                                            <i class="bi bi-check-circle me-1"></i>Terkirim
                                        </span>
                                        <span><?= round($doc['ukuran_file'] / 1024, 2) ?> KB</span>
                                        <span class="text-muted ms-1">• <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-not-uploaded">
                                            <i class="bi bi-exclamation-circle me-1"></i>Belum diunggah
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="document-actions">
                            <?php if ($doc): ?>
                                <!-- Tombol Lihat -->
                                <a href="../../uploads/dokumen/<?= $doc['nama_file'] ?>" target="_blank" 
                                class="btn btn-outline-primary btn-icon" title="Lihat Dokumen">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <!-- Tombol Edit -->
                                <button class="btn btn-outline-warning btn-icon" title="Edit/Ganti Dokumen" 
                                        onclick="openEditModal('<?= $jenis ?>', '<?= $label ?>', <?= $doc['dokumen_pegawai_id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            <?php else: ?>
                                <!-- Tombol Upload -->
                                <button class="btn btn-upload" onclick="openUploadModal('<?= $jenis ?>', '<?= $label ?>')">
                                    <i class="bi bi-upload me-1"></i>Upload
                                </button>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_dokumen">
                        <input type="hidden" name="jenis_dokumen" id="jenis_dokumen">
                        
                        <div class="mb-3">
                            <label class="form-label">Jenis Dokumen</label>
                            <input type="text" class="form-control" id="label_dokumen" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">File Dokumen (PDF, Max 5MB)</label>
                            <input type="file" class="form-control" name="file_dokumen" 
                                   accept=".pdf" required onchange="validateFile(this)">
                            <small class="text-muted">Format: PDF | Ukuran maksimal: 5 MB</small>
                        </div>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Pastikan file yang diupload adalah PDF dengan ukuran maksimal 5 MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Upload Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Document Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Edit Dokumen: <span id="edit_label"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_dokumen">
                        <input type="hidden" name="jenis_dokumen" id="edit_jenis_dokumen">
                        <input type="hidden" name="dokumen_pegawai_id" id="edit_dokumen_pegawai_id">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Upload dokumen baru untuk mengganti dokumen yang lama.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Pilih File Baru (PDF, Max 5MB)
                            </label>
                            <input type="file" class="form-control" name="file_dokumen" accept=".pdf" required>
                            <small class="text-muted">Format: PDF, Ukuran maksimal: 5 MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-pencil me-1"></i>Update Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../partials/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle Edit Mode
        function toggleEdit() {
            const editables = document.querySelectorAll('.editable');
            const btnEdit = document.getElementById('btnEdit');
            const btnSave = document.getElementById('btnSave');
            
            editables.forEach(el => {
                el.disabled = !el.disabled;
                if (!el.disabled) {
                    el.style.backgroundColor = '#ffffff';
                } else {
                    el.style.backgroundColor = '#f8fafc';
                }
            });
            
            if (btnEdit.style.display === 'none') {
                btnEdit.style.display = 'inline-block';
                btnSave.style.display = 'none';
            } else {
                btnEdit.style.display = 'none';
                btnSave.style.display = 'inline-block';
            }
        }

        // Open Upload Modal
        function openUploadModal(jenis, label) {
            document.getElementById('jenis_dokumen').value = jenis;
            document.getElementById('label_dokumen').value = label;
            const uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
            uploadModal.show();
        }

        // Validate File Size and Type
        function validateFile(input) {
            const file = input.files[0];
            if (file) {
                // Check file type
                if (file.type !== 'application/pdf') {
                    alert('Hanya file PDF yang diperbolehkan!');
                    input.value = '';
                    return false;
                }
                
                // Check file size (5 MB = 5 * 1024 * 1024 bytes)
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('Ukuran file maksimal 5 MB!');
                    input.value = '';
                    return false;
                }
            }
        }

        // Open Edit Modal
        function openEditModal(jenis, label, dokumenPegawaiId) {
            document.getElementById('edit_jenis_dokumen').value = jenis;
            document.getElementById('edit_label').textContent = label;
            document.getElementById('edit_dokumen_pegawai_id').value = dokumenPegawaiId;
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
                                        
        // Auto dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>