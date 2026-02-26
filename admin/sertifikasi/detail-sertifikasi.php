<?php
session_start();
require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Ambil ID dari URL
$sertifikasi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sertifikasi_id <= 0) {
    header("Location: sertifikasi-dosen.php");
    exit;
}

// Cek koneksi database
if (!isset($pdo) && isset($conn)) {
    $pdo = $conn;
}

$message = '';
$message_type = '';

// Handle form validasi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status_baru = $_POST['status_validasi'];
    $catatan = trim($_POST['catatan_validasi']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE sertifikasi_dosen 
            SET status_validasi = ?, 
                catatan_validasi = ?,
                updated_at = NOW()
            WHERE sertifikasi_id = ?
        ");
        $stmt->execute([$status_baru, $catatan, $sertifikasi_id]);
        
        $message = 'Status validasi berhasil diperbarui!';
        $message_type = 'success';
    } catch(PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

//  data sertifikasi
try {
    $stmt = $pdo->prepare("
        SELECT 
            sd.*,
            p.nama_lengkap,
            p.nidn,
            p.nip,
            p.prodi,
            p.email,
            p.no_telepon
        FROM sertifikasi_dosen sd
        JOIN pegawai p ON sd.pegawai_id = p.pegawai_id
        WHERE sd.sertifikasi_id = ?
    ");
    $stmt->execute([$sertifikasi_id]);
    $sertifikasi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sertifikasi) {
        header("Location: sertifikasi-dosen.php");
        exit;
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Sertifikasi - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
        }

        .header {
            margin-bottom: 24px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 14px;
            color: #64748b;
        }

        .breadcrumb a {
            color: #3b82f6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 24px;
        }

        .back-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 28px;
            margin-bottom: 24px;
        }

        .card-header {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-tervalidasi {
            background: #d1fae5;
            color: #065f46;
        }

        .status-ditolak {
            background: #fee2e2;
            color: #991b1b;
        }

        .document-preview {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            margin-top: 20px;
        }

        .document-preview i {
            font-size: 48px;
            color: #94a3b8;
            margin-bottom: 16px;
        }

        .document-preview p {
            color: #64748b;
            margin-bottom: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }

        .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px;
                padding: 24px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header h1 {
                font-size: 24px;
            }

            .card {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <a href="sertifikasi-dosen.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar
        </a>

        <div class="header">
            <h1>Detail Sertifikasi</h1>
            <div class="breadcrumb">
                <a href="sertifikasi-dosen.php">Sertifikasi Dosen</a>
                <span>/</span>
                <span>Detail</span>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
            <?= $message ?>
        </div>
        <?php endif; ?>

        <!-- Informasi Dosen -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user"></i> Informasi Dosen
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nama Lengkap</div>
                    <div class="info-value"><?= htmlspecialchars($sertifikasi['nama_lengkap']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">NIDN / NIP</div>
                    <div class="info-value">
                        <?= htmlspecialchars($sertifikasi['nidn'] ?? $sertifikasi['nip'] ?? '-') ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Program Studi</div>
                    <div class="info-value"><?= htmlspecialchars($sertifikasi['prodi'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($sertifikasi['email']) ?></div>
                </div>
            </div>
        </div>

        <!-- Informasi Sertifikasi -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-certificate"></i> Informasi Sertifikasi
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Nama Sertifikasi</div>
                    <div class="info-value"><?= htmlspecialchars($sertifikasi['nama_sertifikasi']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Jenis Sertifikasi</div>
                    <div class="info-value">
                        <?php
                        $jenis_display = [
                            'sertifikasi_pendidik' => 'Sertifikat Pendidik',
                            'profesi' => 'Sertifikat Profesi',
                            'kompetensi' => 'Sertifikat Kompetensi'
                        ];
                        echo $jenis_display[$sertifikasi['jenis_sertifikasi']] ?? $sertifikasi['jenis_sertifikasi'];
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tahun Sertifikasi</div>
                    <div class="info-value"><?= htmlspecialchars($sertifikasi['tahun_sertifikasi']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Kategori</div>
                    <div class="info-value"><?= ucfirst(htmlspecialchars($sertifikasi['kategori'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Masa Berlaku Hingga</div>
                    <div class="info-value"><?= htmlspecialchars($sertifikasi['tahun_masa_berlaku']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status Validasi</div>
                    <div class="info-value">
                        <?php
                        $status = strtolower($sertifikasi['status_validasi']);
                        $badge_class = 'status-pending';
                        $status_text = 'Pending';
                        
                        if ($status === 'tervalidasi') {
                            $badge_class = 'status-tervalidasi';
                            $status_text = 'Tervalidasi';
                        } elseif ($status === 'ditolak') {
                            $badge_class = 'status-ditolak';
                            $status_text = 'Ditolak';
                        }
                        ?>
                        <span class="status-badge <?= $badge_class ?>"><?= $status_text ?></span>
                    </div>
                </div>
            </div>

            <!-- Dokumen -->
            <div class="document-preview">
                <i class="fas fa-file-pdf"></i>
                <p>Dokumen Sertifikat</p>
                <?php if ($sertifikasi['dokumen_sertifikat_path']): ?>
                <a href="../../<?= htmlspecialchars($sertifikasi['dokumen_sertifikat_path']) ?>" 
                   class="btn btn-primary" 
                   target="_blank">
                    <i class="fas fa-download"></i> Download Sertifikat
                </a>
                <?php else: ?>
                <p style="color: #94a3b8;">Tidak ada dokumen</p>
                <?php endif; ?>
            </div>

            <?php if ($sertifikasi['catatan_validasi']): ?>
            <div class="info-item" style="margin-top: 20px;">
                <div class="info-label">Catatan Validasi</div>
                <div class="info-value" style="background: #f8fafc; padding: 12px; border-radius: 8px;">
                    <?= nl2br(htmlspecialchars($sertifikasi['catatan_validasi'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form Validasi -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-clipboard-check"></i> Validasi Sertifikasi
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Status Validasi</label>
                    <select name="status_validasi" class="form-select" required>
                        <option value="pending" <?= $sertifikasi['status_validasi'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="tervalidasi" <?= $sertifikasi['status_validasi'] === 'tervalidasi' ? 'selected' : '' ?>>Tervalidasi</option>
                        <option value="ditolak" <?= $sertifikasi['status_validasi'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Validasi</label>
                    <textarea name="catatan_validasi" class="form-textarea" placeholder="Tambahkan catatan atau alasan jika ditolak..."><?= htmlspecialchars($sertifikasi['catatan_validasi'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                    <a href="sertifikasi-dosen.php" class="btn" style="background: #e2e8f0; color: #475569;">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>