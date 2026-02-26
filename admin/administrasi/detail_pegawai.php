<?php

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
            COALESCE(sk.status_aktif, 'aktif') as status_aktif,
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
    header('Location: administrasiKepegawaian.php?error=1&message=' . urlencode('Data pegawai tidak ditemukan'));
    exit;
}

//Set default values jika NULL
if(empty($pegawai['status_aktif'])) {
    $pegawai['status_aktif'] = 'aktif';
}
if(empty($pegawai['jenis_kepegawaian'])) {
    $pegawai['jenis_kepegawaian'] = 'tetap';
}

// Get data dokumen pegawai
$queryDokumen = "SELECT * FROM dokumen_pegawai WHERE pegawai_id = :id ORDER BY created_at DESC";
$stmtDokumen = $conn->prepare($queryDokumen);
$stmtDokumen->bindParam(':id', $id);
$stmtDokumen->execute();
$dokumenList = $stmtDokumen->fetchAll(PDO::FETCH_ASSOC);

// Function untuk format PTKP
function formatPTKP($ptkp) {
    $ptkpLabels = [
        'TK0' => 'TK/0 - Tidak Kawin (tanpa tanggungan)',
        'TK1' => 'TK/1 - Tidak Kawin (1 tanggungan)',
        'TK2' => 'TK/2 - Tidak Kawin (2 tanggungan)',
        'TK3' => 'TK/3 - Tidak Kawin (3 tanggungan)',
        'K0' => 'K/0 - Kawin (tanpa tanggungan)',
        'K1' => 'K/1 - Kawin (1 tanggungan)',
        'K2' => 'K/2 - Kawin (2 tanggungan)',
        'K3' => 'K/3 - Kawin (3 tanggungan)'
    ];
    
    return $ptkpLabels[$ptkp] ?? '-';
}

// Hitung masa kontrak dan sisa kontrak
$masa_kontrak_hari = 0;
$masa_kontrak_text = '-';
$sisa_kontrak_hari = 0;
$sisa_kontrak_text = '-';
$status_kontrak = '';

if($pegawai['jenis_kepegawaian'] == 'kontrak' && $pegawai['masa_kontrak_mulai'] && $pegawai['masa_kontrak_selesai']) {
    $mulai = new DateTime($pegawai['masa_kontrak_mulai']);
    $selesai = new DateTime($pegawai['masa_kontrak_selesai']);
    $sekarang = new DateTime();
    
    // Hitung masa kontrak (total durasi)
    $interval_total = $mulai->diff($selesai);
    $masa_kontrak_hari = $interval_total->days;
    
    $tahun = $interval_total->y;
    $bulan = $interval_total->m;
    $hari = $interval_total->d;
    
    $masa_parts = [];
    if($tahun > 0) $masa_parts[] = $tahun . ' tahun';
    if($bulan > 0) $masa_parts[] = $bulan . ' bulan';
    if($hari > 0) $masa_parts[] = $hari . ' hari';
    $masa_kontrak_text = !empty($masa_parts) ? implode(' ', $masa_parts) : '0 hari';
    
    // Hitung sisa kontrak
    if($sekarang <= $selesai) {
        $interval_sisa = $sekarang->diff($selesai);
        $sisa_kontrak_hari = $interval_sisa->days;
        
        $tahun_sisa = $interval_sisa->y;
        $bulan_sisa = $interval_sisa->m;
        $hari_sisa = $interval_sisa->d;
        
        $sisa_parts = [];
        if($tahun_sisa > 0) $sisa_parts[] = $tahun_sisa . ' tahun';
        if($bulan_sisa > 0) $sisa_parts[] = $bulan_sisa . ' bulan';
        if($hari_sisa > 0) $sisa_parts[] = $hari_sisa . ' hari';
        $sisa_kontrak_text = !empty($sisa_parts) ? implode(' ', $sisa_parts) : '0 hari';
        
        // Tentukan status kontrak
        if($sisa_kontrak_hari <= 30) {
            $status_kontrak = 'segera-habis';
        } elseif($sisa_kontrak_hari <= 90) {
            $status_kontrak = 'mendekati';
        } else {
            $status_kontrak = 'normal';
        }
    } else {
        $sisa_kontrak_text = 'Kontrak sudah berakhir';
        $status_kontrak = 'habis';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pegawai - <?= htmlspecialchars($pegawai['nama_lengkap']) ?></title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
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
        .badge-ptkp { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
        .badge-kontrak-normal { background: #dcfce7; color: #166534; }
        .badge-kontrak-mendekati { background: #fef3c7; color: #92400e; }
        .badge-kontrak-segera { background: #fed7aa; color: #9a3412; }
        .badge-kontrak-habis { background: #fee2e2; color: #991b1b; }
        .kontrak-info-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-top: 15px; }
        .kontrak-info-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .kontrak-info-item { display: flex; align-items: center; gap: 12px; }
        .kontrak-info-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .icon-clock { background: #dbeafe; color: #1e40af; }
        .icon-calendar { background: #fef3c7; color: #92400e; }
        .kontrak-info-content { flex: 1; }
        .kontrak-info-label { font-size: 12px; color: #6b7280; margin-bottom: 2px; }
        .kontrak-info-value { font-size: 16px; font-weight: 600; color: #1f2937; }
        .btn-group { display: flex; gap: 10px; margin-top: 30px; padding-top: 30px; border-top: 1px solid #e5e7eb; }
        .btn-primary-custom { background: #1f2937; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary-custom:hover { background: #374151; color: white; }
        .btn-outline-custom { background: white; border: 1px solid #d1d5db; color: #374151; padding: 12px 24px; border-radius: 8px; font-weight: 500; text-decoration: none; }
        .btn-outline-custom:hover { background: #f9fafb; }
        
        /* Style untuk dokumen */
        .dokumen-list { margin-top: 15px; }
        .dokumen-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; }
        .dokumen-info { display: flex; align-items: center; gap: 12px; flex: 1; }
        .dokumen-icon { width: 40px; height: 40px; background: #fee2e2; color: #dc2626; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .dokumen-details { flex: 1; }
        .dokumen-name { font-size: 14px; font-weight: 500; color: #1f2937; margin-bottom: 4px; }
        .dokumen-meta { font-size: 12px; color: #6b7280; }
        .dokumen-actions { display: flex; gap: 8px; }
        .btn-view { background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-view:hover { background: #1d4ed8; color: white; }
        .badge-success { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
        .progress-bar-custom { height: 6px; background: #e5e7eb; border-radius: 3px; margin-top: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: #2563eb; border-radius: 3px; transition: width 0.3s ease; }
        .dokumen-count { font-size: 13px; color: #6b7280; margin-top: 5px; }
        
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
                <div class="detail-row">
                    <div class="detail-group">
                        <div class="detail-label">PTKP (Status Pajak)</div>
                        <div class="detail-value">
                            <?php if(!empty($pegawai['ptkp'])): ?>
                                <span class="badge-custom badge-ptkp">
                                    <i class="fas fa-file-invoice me-1"></i>
                                    <?= formatPTKP($pegawai['ptkp']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted"> - </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Tanggal Mulai Kerja</div>
                        <div class="detail-value"><?= $pegawai['tanggal_mulai_kerja'] ?? '-' ?></div>
                    </div>
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
                
                <!-- Info Masa Kontrak dan Sisa Kontrak -->
                <div class="kontrak-info-box">
                    <div class="kontrak-info-row">
                        <div class="kontrak-info-item">
                            <div class="kontrak-info-icon icon-clock">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="kontrak-info-content">
                                <div class="kontrak-info-label">Masa Kontrak</div>
                                <div class="kontrak-info-value"><?= $masa_kontrak_text ?></div>
                            </div>
                        </div>
                        <div class="kontrak-info-item">
                            <div class="kontrak-info-icon icon-calendar">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="kontrak-info-content">
                                <div class="kontrak-info-label">Sisa Kontrak</div>
                                <div class="kontrak-info-value">
                                    <?= $sisa_kontrak_text ?>
                                    <?php if($status_kontrak == 'segera-habis'): ?>
                                        <span class="badge-custom badge-kontrak-segera ms-2">
                                            <i class="fas fa-exclamation-triangle"></i> Segera Habis
                                        </span>
                                    <?php elseif($status_kontrak == 'mendekati'): ?>
                                        <span class="badge-custom badge-kontrak-mendekati ms-2">
                                            <i class="fas fa-info-circle"></i> Mendekati
                                        </span>
                                    <?php elseif($status_kontrak == 'habis'): ?>
                                        <span class="badge-custom badge-kontrak-habis ms-2">
                                            <i class="fas fa-times-circle"></i> Habis
                                        </span>
                                    <?php elseif($status_kontrak == 'normal'): ?>
                                        <span class="badge-custom badge-kontrak-normal ms-2">
                                            <i class="fas fa-check-circle"></i> Normal
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Kelengkapan Dokumen -->
            <div class="detail-section">
                <div class="detail-section-title">
                    <i class="fas fa-file-alt"></i>
                    Kelengkapan Dokumen
                </div>
                
                <?php 
                // Daftar dokumen yang diperlukan 
                $requiredDokumen = [
                    'cv' => 'Curriculum Vitae (CV)',
                    'ktp' => 'KTP (Kartu Tanda Penduduk)',
                    'npwp' => 'NPWP (Nomor Pokok Wajib Pajak)',
                    'ijazah' => 'Ijazah/Sertifikat Pendidikan',
                    'surat_sehat' => 'Surat Keterangan Sehat',
                    'surat_kerja_sebelumnya' => 'Surat Keterangan Kerja Sebelumnya',
                    'skck' => 'SKCK (Surat Keterangan Catatan Kepolisian)',
                    'surat_bebas_napza' => 'Surat Keterangan Bebas Napza'
                ];
                
                // array dokumen berdasarkan jenis
                $dokumenByJenis = [];
                foreach($dokumenList as $dok) {
                    $dokumenByJenis[$dok['jenis_dokumen']] = $dok;
                }
                
                $totalDokumen = count($requiredDokumen);
                $uploadedDokumen = count($dokumenByJenis);
                $progressPercentage = $totalDokumen > 0 ? ($uploadedDokumen / $totalDokumen) * 100 : 0;
                ?>
                
                <div class="dokumen-count">
                    <?= $uploadedDokumen ?> dari <?= $totalDokumen ?> dokumen telah diupload
                </div>
                <div class="progress-bar-custom">
                    <div class="progress-fill" style="width: <?= $progressPercentage ?>%"></div>
                </div>
                
                <div class="dokumen-list">
                    <?php foreach($requiredDokumen as $jenis => $namaLabel): ?>
                        <?php 
                        $dokumen = isset($dokumenByJenis[$jenis]) ? $dokumenByJenis[$jenis] : null;
                        ?>
                        <div class="dokumen-item">
                            <div class="dokumen-info">
                                <div class="dokumen-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="dokumen-details">
                                    <div class="dokumen-name">
                                        <?= htmlspecialchars($namaLabel) ?>
                                        <?php if($dokumen): ?>
                                            <span class="badge-success ms-2">
                                                <i class="fas fa-check"></i> Berhasil diunggah
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-custom badge-tidak-aktif ms-2">
                                                <i class="fas fa-times"></i> Belum diunggah
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dokumen-meta">
                                        <?php if($dokumen): ?>
                                            <?= number_format($dokumen['ukuran_file'] / 1024, 2) ?> KB • 
                                            <?= date('d/m/Y H:i', strtotime($dokumen['created_at'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Belum ada dokumen yang diupload</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="dokumen-actions">
                                <?php if($dokumen): ?>
                                    <a href="../../uploads/dokumen/<?= htmlspecialchars($dokumen['nama_file']) ?>" 
                                       target="_blank" 
                                       class="btn-view">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 12px;">-</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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