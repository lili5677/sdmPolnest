<?php
/**
 * File: admin/detail_pegawai.php
 * Deskripsi: Tampil detail data pegawai
 */

require_once '../../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($id <= 0) {
    header('Location: administrasiKepegawaian.php?error=1&message=' . urlencode('ID pegawai tidak valid'));
    exit;
}

// Get data pegawai
$query = "SELECT 
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
        WHERE p.pegawai_id = :id";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$pegawai) {
    header('Location: administrasiKepegawaian.php?error=1&message=' . urlencode('Data pegawai tidak ditemukan'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pegawai - <?= htmlspecialchars($pegawai['nama_lengkap']) ?></title>
    
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
        .detail-section { margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e5e7eb; }
        .detail-section:last-child { border-bottom: none; }
        .detail-section-title { font-size: 18px; font-weight: 600; color: #1f2937; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .detail-section-title i { color: #2563eb; }
        .detail-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .detail-group { }
        .detail-label { font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500; }
        .detail-value { font-size: 15px; color: #1f2937; }
        .badge-custom { padding: 6px 12px; border-radius: 6px; font-weight: 500; font-size: 12px; }
        .badge-aktif { background: #dcfce7; color: #166534; }
        .badge-tidak-aktif { background: #fee2e2; color: #991b1b; }
        .badge-tetap { background: #dbeafe; color: #1e40af; }
        .badge-kontrak { background: #fef3c7; color: #92400e; }
        .badge-dosen { background: #e0e7ff; color: #3730a3; }
        .badge-staff { background: #fce7f3; color: #831843; }
        .badge-tendik { background: #e0f2fe; color: #075985; }
        .btn-group { display: flex; gap: 10px; margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e7eb; }
        .btn-primary-custom { background: #1f2937; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary-custom:hover { background: #374151; color: white; }
        .btn-outline-custom { background: white; border: 1px solid #d1d5db; color: #374151; padding: 12px 24px; border-radius: 8px; font-weight: 500; text-decoration: none; }
        .btn-outline-custom:hover { background: #f9fafb; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 20px; } .content-card { padding: 20px; } }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="administrasiKepegawaian.php?tab=data-pegawai">Data Pegawai</a></li>
                    <li class="breadcrumb-item active">Detail Pegawai</li>
                </ol>
            </nav>
            <h1><i class="fas fa-user-circle me-2"></i>Detail Pegawai</h1>
        </div>

        <div class="content-card">
            <!-- Data Pribadi -->
            <div class="detail-section">
                <div class="detail-section-title">
                    <i class="fas fa-user"></i>
                    Data Pribadi
                </div>
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">NIK</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['nik'] ?? '-') ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Nama Lengkap</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['nama_lengkap']) ?></div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">Tempat, Tanggal Lahir</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['tempat_lahir'] ?? '-') ?>, <?= $pegawai['tanggal_lahir'] ?? '-' ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Jenis Kelamin</div>
                        <div class="detail-value"><?= $pegawai['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($pegawai['jenis_kelamin'] === 'P' ? 'Perempuan' : '-') ?></div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">Email</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['email']) ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">No. Telepon</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['no_telepon'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Alamat KTP</div>
                    <div class="detail-value"><?= htmlspecialchars($pegawai['alamat_ktp'] ?? '-') ?></div>
                </div>
                <div class="detail-group" style="margin-top: 15px;">
                    <div class="detail-label">Alamat Domisili</div>
                    <div class="detail-value"><?= htmlspecialchars($pegawai['alamat_domisili'] ?? '-') ?></div>
                </div>
            </div>

            <!-- Data Kepegawaian -->
            <div class="detail-section">
                <div class="detail-section-title">
                    <i class="fas fa-briefcase"></i>
                    Data Kepegawaian
                </div>
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">Jenis Pegawai</div>
                        <div class="detail-value">
                            <?php if($pegawai['jenis_pegawai'] == 'dosen'): ?>
                                <span class="badge-custom badge-dosen">Dosen</span>
                            <?php elseif($pegawai['jenis_pegawai'] == 'staff'): ?>
                                <span class="badge-custom badge-staff">Staff</span>
                            <?php else: ?>
                                <span class="badge-custom badge-tendik">Tendik</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">NIP</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['nip'] ?? '-') ?></div>
                    </div>
                </div>
                
                <?php if($pegawai['jenis_pegawai'] == 'dosen'): ?>
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">NIDN</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['nidn'] ?? '-') ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Program Studi</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['prodi'] ?? '-') ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">Jabatan</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['jabatan'] ?? '-') ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Unit Kerja</div>
                        <div class="detail-value"><?= htmlspecialchars($pegawai['unit_kerja'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">Jenis Kepegawaian</div>
                        <div class="detail-value">
                            <?php if($pegawai['jenis_kepegawaian'] == 'tetap'): ?>
                                <span class="badge-custom badge-tetap">Tetap</span>
                            <?php elseif($pegawai['jenis_kepegawaian'] == 'kontrak'): ?>
                                <span class="badge-custom badge-kontrak">Kontrak</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value">
                            <?php if($pegawai['status_aktif'] == 'aktif'): ?>
                                <span class="badge-custom badge-aktif">Aktif</span>
                            <?php else: ?>
                                <span class="badge-custom badge-tidak-aktif">Tidak Aktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="detail-group">
                    <div class="detail-label">Tanggal Mulai Kerja</div>
                    <div class="detail-value"><?= $pegawai['tanggal_mulai_kerja'] ?? '-' ?></div>
                </div>
                
                <?php if($pegawai['jenis_kepegawaian'] == 'kontrak'): ?>
                <div class="detail-row" style="margin-top: 15px;">
                    <div class="detail-group">
                        <div class="detail-label">Masa Kontrak Mulai</div>
                        <div class="detail-value"><?= $pegawai['masa_kontrak_mulai'] ?? '-' ?></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Masa Kontrak Selesai</div>
                        <div class="detail-value"><?= $pegawai['masa_kontrak_selesai'] ?? '-' ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="btn-group">
                <a href="administrasiKepegawaian.php?tab=data-pegawai" class="btn btn-outline-custom">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
                <a href="edit_pegawai.php?id=<?= $pegawai['pegawai_id'] ?>" class="btn btn-primary-custom">
                    <i class="fas fa-edit me-1"></i> Edit Data
                </a>
            </div>
        </div>
    </div>
</body>
</html>