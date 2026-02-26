<?php
// Koneksi Database
require_once '../../config/database.php';
require_once '../../includes/sync_user_type.php'; 

// Get ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0) {
    header('Location: administrasiKepegawaian.php?tab=data-pegawai&error=1&message=' . urlencode('ID pegawai tidak valid'));
    exit;
}

// Get data pegawai
$query = "SELECT 
            p.*,
            sk.status_id,
            sk.jabatan,
            sk.jenis_kepegawaian,
            sk.status_aktif,
            sk.ptkp,
            sk.unit_kerja,
            sk.tanggal_mulai_kerja,
            sk.masa_kontrak_mulai,
            sk.masa_kontrak_selesai
        FROM pegawai p
        LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
        WHERE p.pegawai_id = :id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pegawai) {
    header('Location: administrasiKepegawaian.php?tab=data-pegawai&error=1&message=' . urlencode('Data pegawai tidak ditemukan'));
    exit;
}

// Proses Form Submit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];

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
        // Validasi logika tanggal kontrak
        if(!empty($_POST['masa_kontrak_mulai']) && !empty($_POST['masa_kontrak_selesai'])) {
            if($_POST['masa_kontrak_selesai'] <= $_POST['masa_kontrak_mulai']) {
                $errors[] = 'Masa kontrak selesai harus setelah masa kontrak mulai';
            }
        }
    }
    
    if(empty($errors)) {
        // Helper function untuk handle empty string
        function emptyToNull($value) {
            return (empty($value)) ? null : $value;
        }
        
        $jabatan           = emptyToNull(trim($_POST['jabatan']));
        $unit_kerja        = emptyToNull(trim($_POST['unit_kerja']));
        $ptkp              = emptyToNull($_POST['ptkp']);
        $masa_kontrak_mulai   = emptyToNull($_POST['masa_kontrak_mulai']);
        $masa_kontrak_selesai = emptyToNull($_POST['masa_kontrak_selesai']);

        $conn->beginTransaction();
        
        try {
            // Update atau Insert status_kepegawaian
            if(!empty($pegawai['status_id'])) {
                // Update existing
                $statusQuery = "UPDATE status_kepegawaian SET
                    jabatan = :jabatan,
                    jenis_kepegawaian = :jenis_kepegawaian,
                    status_aktif = :status_aktif,
                    ptkp = :ptkp,
                    unit_kerja = :unit_kerja,
                    tanggal_mulai_kerja = :tanggal_mulai_kerja,
                    masa_kontrak_mulai = :masa_kontrak_mulai,
                    masa_kontrak_selesai = :masa_kontrak_selesai,
                    updated_at = CURRENT_TIMESTAMP
                WHERE pegawai_id = :pegawai_id";
            } else {
                // Insert new
                $admin_id = 1;
                $statusQuery = "INSERT INTO status_kepegawaian (
                    pegawai_id, jabatan, jenis_kepegawaian, status_aktif, ptkp,
                    unit_kerja, tanggal_mulai_kerja, masa_kontrak_mulai,
                    masa_kontrak_selesai, created_by
                ) VALUES (
                    :pegawai_id, :jabatan, :jenis_kepegawaian, :status_aktif, :ptkp,
                    :unit_kerja, :tanggal_mulai_kerja, :masa_kontrak_mulai,
                    :masa_kontrak_selesai, $admin_id
                )";
            }
            
            $statusStmt = $conn->prepare($statusQuery);
            $statusStmt->bindParam(':pegawai_id', $id);
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
            
            $statusStmt->execute();
            
            // Ambil jenis_pegawai dari database
            $getJenis = $conn->prepare("SELECT jenis_pegawai FROM pegawai WHERE pegawai_id = ?");
            $getJenis->execute([$id]);
            $jenis_pegawai = $getJenis->fetchColumn();
            
            // Ambil user_id
            $getUserId = $conn->prepare("SELECT user_id FROM pegawai WHERE pegawai_id = ?");
            $getUserId->execute([$id]);
            $user_id = $getUserId->fetchColumn();
            
            // Sinkronisasi
            if ($user_id && $jenis_pegawai) {
                sinkronisasiUserType($conn, $user_id, $jenis_pegawai);
            }
            
            $conn->commit();

            // REDIRECT KE TAB DATA PEGAWAI
            header('Location: administrasiKepegawaian.php?tab=data-pegawai&success=1&message=' . urlencode('Data kepegawaian berhasil diperbarui'));
            exit;
            
        } catch(Exception $e) {
            $conn->rollBack();
            $errors[] = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Merge POST data dengan data existing jika ada error
$data = $_SERVER['REQUEST_METHOD'] == 'POST' ? array_merge($pegawai, $_POST) : $pegawai;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pegawai - Administrasi Kepegawaian</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
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

        /* Style untuk readonly fields */
        .form-control:disabled, .form-select:disabled {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
            border-color: #e5e7eb;
        }

        /* Highlight field yang belum terisi saat submit */
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .readonly-info {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #78350f;
        }

        .readonly-info i {
            color: #f59e0b;
            margin-right: 8px;
        }

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
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="administrasiKepegawaian.php?tab=data-pegawai">
                        <i class=""></i> Kembali
                    </a></li>
                    <li class="breadcrumb-item active">Edit Pegawai</li>
                </ol>
            </nav>
            <h1><i class="fas fa-user-edit me-2"></i>Edit Data Kepegawaian</h1>
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

            <form method="POST" action="" id="formEditPegawai" novalidate>
                <!-- Data Pribadi -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user"></i>
                        Data Pribadi
                    </div>
                    
                    <div class="readonly-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Informasi:</strong> Data pribadi pegawai hanya dapat diubah oleh pegawai yang bersangkutan melalui profil mereka. Admin hanya dapat mengubah data kepegawaian.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nik" class="form-label">NIK</label>
                            <input type="text" class="form-control" id="nik" value="<?= htmlspecialchars($pegawai['nik'] ?? '-') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" value="<?= htmlspecialchars($pegawai['nama_lengkap'] ?? '') ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                            <input type="text" class="form-control" id="tempat_lahir" value="<?= htmlspecialchars($pegawai['tempat_lahir'] ?? '-') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                            <input type="text" class="form-control" id="tanggal_lahir" value="<?= $pegawai['tanggal_lahir'] ? date('d-m-Y', strtotime($pegawai['tanggal_lahir'])) : '-' ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                            <input type="text" class="form-control" id="jenis_kelamin" value="<?= ($pegawai['jenis_kelamin'] ?? '') == 'L' ? 'Laki-laki' : (($pegawai['jenis_kelamin'] ?? '') == 'P' ? 'Perempuan' : '-') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="no_telepon" class="form-label">No. Telepon</label>
                            <input type="text" class="form-control" id="no_telepon" value="<?= htmlspecialchars($pegawai['no_telepon'] ?? '-') ?>" disabled>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="text" class="form-control" id="email" value="<?= htmlspecialchars($pegawai['email'] ?? '') ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_ktp" class="form-label">Alamat KTP</label>
                        <textarea class="form-control" id="alamat_ktp" rows="2" disabled><?= htmlspecialchars($pegawai['alamat_ktp'] ?? '-') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="alamat_domisili" class="form-label">Alamat Domisili</label>
                        <textarea class="form-control" id="alamat_domisili" rows="2" disabled><?= htmlspecialchars($pegawai['alamat_domisili'] ?? '-') ?></textarea>
                    </div>

                    <!-- JENIS PEGAWAI, NIP, NIDN, PRODI (READ ONLY - dari inputan user) -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_pegawai_display" class="form-label">Jenis Pegawai</label>
                            <input type="text" class="form-control" id="jenis_pegawai_display" value="<?= htmlspecialchars(ucfirst($pegawai['jenis_pegawai'] ?? '-')) ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nip_display" class="form-label">NIP</label>
                            <input type="text" class="form-control" id="nip_display" value="<?= htmlspecialchars($pegawai['nip'] ?? '-') ?>" disabled>
                        </div>
                    </div>

                    <?php if(($pegawai['jenis_pegawai'] ?? '') == 'dosen'): ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nidn_display" class="form-label">NIDN</label>
                            <input type="text" class="form-control" id="nidn_display" value="<?= htmlspecialchars($pegawai['nidn'] ?? '-') ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="prodi_display" class="form-label">Program Studi</label>
                            <input type="text" class="form-control" id="prodi_display" value="<?= htmlspecialchars($pegawai['prodi'] ?? '-') ?>" disabled>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Data Kepegawaian (EDITABLE) -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-briefcase"></i>
                        Data Kepegawaian
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jabatan" class="form-label">
                                Jabatan <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control <?= (isset($errors) && !empty($errors) && empty(trim($_POST['jabatan'] ?? ''))) ? 'is-invalid' : '' ?>" 
                                id="jabatan" name="jabatan" 
                                value="<?= htmlspecialchars($data['jabatan'] ?? '') ?>"
                                placeholder="Contoh: Staff Administrasi"
                                required>
                            <div class="invalid-feedback">Jabatan wajib diisi.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="unit_kerja" class="form-label">
                                Unit Kerja <span class="required">*</span>
                            </label>
                            <input type="text" class="form-control <?= (isset($errors) && !empty($errors) && empty(trim($_POST['unit_kerja'] ?? ''))) ? 'is-invalid' : '' ?>" 
                                id="unit_kerja" name="unit_kerja" 
                                value="<?= htmlspecialchars($data['unit_kerja'] ?? '') ?>"
                                placeholder="Contoh: Bagian Keuangan"
                                required>
                            <div class="invalid-feedback">Unit kerja wajib diisi.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="jenis_kepegawaian" class="form-label">
                                Jenis Kepegawaian <span class="required">*</span>
                            </label>
                            <select class="form-select <?= (isset($errors) && !empty($errors) && empty($_POST['jenis_kepegawaian'] ?? '')) ? 'is-invalid' : '' ?>" 
                                id="jenis_kepegawaian" name="jenis_kepegawaian" 
                                required onchange="toggleKontrakFields()">
                                <option value="">-- Pilih --</option>
                                <option value="tetap" <?= ($data['jenis_kepegawaian'] ?? '') == 'tetap' ? 'selected' : '' ?>>Tetap</option>
                                <option value="kontrak" <?= ($data['jenis_kepegawaian'] ?? '') == 'kontrak' ? 'selected' : '' ?>>Kontrak</option>
                            </select>
                            <div class="invalid-feedback">Jenis kepegawaian wajib dipilih.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status_aktif" class="form-label">
                                Status <span class="required">*</span>
                            </label>
                            <select class="form-select" id="status_aktif" name="status_aktif" required>
                                <option value="aktif" <?= ($data['status_aktif'] ?? 'aktif') == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="tidak_aktif" <?= ($data['status_aktif'] ?? '') == 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="ptkp" class="form-label">
                                PTKP (Status Pajak) <span class="required">*</span>
                            </label>
                            <select class="form-select <?= (isset($errors) && !empty($errors) && empty($_POST['ptkp'] ?? '')) ? 'is-invalid' : '' ?>" 
                                id="ptkp" name="ptkp" required>
                                <option value="">-- Pilih PTKP --</option>
                                <option value="TK0" <?= ($data['ptkp'] ?? '') == 'TK0' ? 'selected' : '' ?>>TK/0 - Tidak Kawin (tanpa tanggungan)</option>
                                <option value="TK1" <?= ($data['ptkp'] ?? '') == 'TK1' ? 'selected' : '' ?>>TK/1 - Tidak Kawin (1 tanggungan)</option>
                                <option value="TK2" <?= ($data['ptkp'] ?? '') == 'TK2' ? 'selected' : '' ?>>TK/2 - Tidak Kawin (2 tanggungan)</option>
                                <option value="TK3" <?= ($data['ptkp'] ?? '') == 'TK3' ? 'selected' : '' ?>>TK/3 - Tidak Kawin (3 tanggungan)</option>
                                <option value="K0" <?= ($data['ptkp'] ?? '') == 'K0' ? 'selected' : '' ?>>K/0 - Kawin (tanpa tanggungan)</option>
                                <option value="K1" <?= ($data['ptkp'] ?? '') == 'K1' ? 'selected' : '' ?>>K/1 - Kawin (1 tanggungan)</option>
                                <option value="K2" <?= ($data['ptkp'] ?? '') == 'K2' ? 'selected' : '' ?>>K/2 - Kawin (2 tanggungan)</option>
                                <option value="K3" <?= ($data['ptkp'] ?? '') == 'K3' ? 'selected' : '' ?>>K/3 - Kawin (3 tanggungan)</option>
                            </select>
                            <div class="invalid-feedback">PTKP wajib dipilih.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_mulai_kerja" class="form-label">
                                Tanggal Mulai Kerja <span class="required">*</span>
                            </label>
                            <input type="date" 
                                class="form-control <?= (isset($errors) && !empty($errors) && empty($_POST['tanggal_mulai_kerja'] ?? '')) ? 'is-invalid' : '' ?>" 
                                id="tanggal_mulai_kerja" name="tanggal_mulai_kerja" 
                                value="<?= htmlspecialchars($data['tanggal_mulai_kerja'] ?? '') ?>" 
                                required>
                            <div class="invalid-feedback">Tanggal mulai kerja wajib diisi.</div>
                        </div>
                    </div>

                    <!-- Fields khusus Kontrak -->
                    <div id="kontrakFields" style="display: <?= ($data['jenis_kepegawaian'] ?? '') == 'kontrak' ? 'block' : 'none' ?>;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="masa_kontrak_mulai" class="form-label">
                                    Masa Kontrak Mulai <span class="required">*</span>
                                </label>
                                <input type="date" 
                                    class="form-control kontrak-required <?= (isset($errors) && !empty($errors) && ($data['jenis_kepegawaian'] ?? '') == 'kontrak' && empty($_POST['masa_kontrak_mulai'] ?? '')) ? 'is-invalid' : '' ?>" 
                                    id="masa_kontrak_mulai" name="masa_kontrak_mulai" 
                                    value="<?= htmlspecialchars($data['masa_kontrak_mulai'] ?? '') ?>">
                                <div class="invalid-feedback">Masa kontrak mulai wajib diisi.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="masa_kontrak_selesai" class="form-label">
                                    Masa Kontrak Selesai <span class="required">*</span>
                                </label>
                                <input type="date" 
                                    class="form-control kontrak-required <?= (isset($errors) && !empty($errors) && ($data['jenis_kepegawaian'] ?? '') == 'kontrak' && empty($_POST['masa_kontrak_selesai'] ?? '')) ? 'is-invalid' : '' ?>" 
                                    id="masa_kontrak_selesai" name="masa_kontrak_selesai" 
                                    value="<?= htmlspecialchars($data['masa_kontrak_selesai'] ?? '') ?>">
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
                    <button type="submit" class="btn btn-primary-custom" id="btnSimpan">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle field kontrak
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
                    input.classList.remove('is-invalid');
                });
            }
        }

        // Validasi client-side sebelum submit
        document.getElementById('formEditPegawai').addEventListener('submit', function(e) {
            let isValid = true;
            const fields = this.querySelectorAll('[required]');

            fields.forEach(function(field) {
                // Lewati field yang tersembunyi
                if (field.closest('#kontrakFields') && field.closest('#kontrakFields').style.display === 'none') {
                    return;
                }

                if (field.value.trim() === '' || field.value === '') {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });

            // Validasi logika tanggal kontrak
            const jenisKepegawaian = document.getElementById('jenis_kepegawaian').value;
            if (jenisKepegawaian === 'kontrak') {
                const mulai   = document.getElementById('masa_kontrak_mulai').value;
                const selesai = document.getElementById('masa_kontrak_selesai').value;
                if (mulai && selesai && selesai <= mulai) {
                    document.getElementById('masa_kontrak_selesai').classList.add('is-invalid');
                    document.getElementById('masa_kontrak_selesai').nextElementSibling.textContent 
                        = 'Masa kontrak selesai harus setelah masa kontrak mulai.';
                    isValid = false;
                }
            }

            if (!isValid) {
                e.preventDefault();
                // Scroll ke error pertama
                const firstInvalid = document.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });

        // Hapus class is-invalid saat user mulai mengisi
        document.querySelectorAll('.form-control, .form-select').forEach(function(el) {
            el.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
            el.addEventListener('change', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleKontrakFields();
        });
    </script>
</body>
</html>