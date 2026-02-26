<?php
require_once '../../includes/check_login.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data with pegawai details
$query = "
    SELECT u.*, p.* 
    FROM users u
    LEFT JOIN pegawai p ON u.user_id = p.user_id
    WHERE u.user_id = :user_id
";
$stmt = $conn->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$pegawai_id = $user['pegawai_id'] ?? null;

// Get status kepegawaian (jabatan, unit kerja, dll)
$status_kepegawaian = null;
if ($pegawai_id) {
    $status_query = "SELECT * FROM status_kepegawaian WHERE pegawai_id = :pegawai_id AND status_aktif = 'aktif' ORDER BY created_at DESC LIMIT 1";
    $status_stmt = $conn->prepare($status_query);
    $status_stmt->execute(['pegawai_id' => $pegawai_id]);
    $status_kepegawaian = $status_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get riwayat kepegawaian
$riwayat_kepegawaian = [];
if ($pegawai_id) {
    $riwayat_query = "SELECT * FROM riwayat_kepegawaian WHERE pegawai_id = :pegawai_id ORDER BY tanggal_mulai DESC";
    $riwayat_stmt = $conn->prepare($riwayat_query);
    $riwayat_stmt->execute(['pegawai_id' => $pegawai_id]);
    $riwayat_kepegawaian = $riwayat_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get reward data
$rewards = [];
if ($pegawai_id) {
    $reward_query = "SELECT * FROM reward_pegawai WHERE pegawai_id = :pegawai_id ORDER BY tanggal_reward DESC, created_at DESC";
    $reward_stmt = $conn->prepare($reward_query);
    $reward_stmt->execute(['pegawai_id' => $pegawai_id]);
    $rewards = $reward_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get documents - mengambil dokumen terbaru per jenis
$documents = [];
if ($pegawai_id) {
    $doc_query = "SELECT d1.* 
                  FROM dokumen_pegawai d1
                  LEFT JOIN dokumen_pegawai d2 
                    ON d1.jenis_dokumen = d2.jenis_dokumen 
                    AND d1.pegawai_id = d2.pegawai_id
                    AND d1.created_at < d2.created_at
                  WHERE d1.pegawai_id = :pegawai_id 
                    AND d2.dokumen_pegawai_id IS NULL
                  ORDER BY 
                    CASE d1.jenis_dokumen
                        WHEN 'cv' THEN 1
                        WHEN 'ijazah' THEN 2
                        WHEN 'ktp' THEN 3
                        WHEN 'npwp' THEN 4
                        WHEN 'surat_sehat' THEN 5
                        WHEN 'surat_kerja_sebelumnya' THEN 6
                        WHEN 'skck' THEN 7
                        WHEN 'surat_bebas_napza' THEN 8
                    END";
    $doc_stmt = $conn->prepare($doc_query);
    $doc_stmt->execute(['pegawai_id' => $pegawai_id]);
    $documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get sertifikasi dosen (jika dosen)
$sertifikasi = [];
if ($pegawai_id && isset($user['jenis_pegawai']) && $user['jenis_pegawai'] == 'dosen') {
    $sertifikasi_query = "SELECT * FROM sertifikasi_dosen WHERE pegawai_id = :pegawai_id AND status_validasi = 'tervalidasi' ORDER BY tahun_sertifikasi DESC";
    $sertifikasi_stmt = $conn->prepare($sertifikasi_query);
    $sertifikasi_stmt->execute(['pegawai_id' => $pegawai_id]);
    $sertifikasi = $sertifikasi_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate years of experience
$experience_years = 0;
if ($status_kepegawaian && !empty($status_kepegawaian['tanggal_mulai_kerja'])) {
    $start_date = new DateTime($status_kepegawaian['tanggal_mulai_kerja']);
    $current_date = new DateTime();
    $interval = $start_date->diff($current_date);
    $experience_years = $interval->y;
}

// Fungsi untuk format label dokumen
function getDocumentLabel($jenis_dokumen) {
    $labels = [
        'cv' => 'Curriculum Vitae (CV)',
        'ktp' => 'Kartu Tanda Penduduk (KTP)',
        'npwp' => 'Nomor Pokok Wajib Pajak (NPWP)',
        'ijazah' => 'Ijazah Terakhir',
        'surat_sehat' => 'Surat Keterangan Sehat',
        'surat_kerja_sebelumnya' => 'Surat Keterangan Kerja',
        'skck' => 'Surat Keterangan Catatan Kepolisian (SKCK)',
        'surat_bebas_napza' => 'Surat Bebas Napza'
    ];
    return $labels[$jenis_dokumen] ?? ucfirst(str_replace('_', ' ', $jenis_dokumen));
}

// Fungsi untuk icon dokumen
function getDocumentIcon($jenis_dokumen, $nama_file) {
    $ext = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
    
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        return 'image';
    } elseif ($ext == 'pdf') {
        return 'pdf';
    } else {
        return 'text';
    }
}

// Fungsi untuk format jenis pegawai
function getJenisPegawaiLabel($jenis_pegawai) {
    $labels = [
        'dosen' => 'Dosen',
        'staff' => 'Staff',
        'tendik' => 'Tenaga Kependidikan'
    ];
    return $labels[$jenis_pegawai] ?? ucfirst($jenis_pegawai);
}

// Fungsi untuk format jenis kepegawaian
function getJenisKepegawaianLabel($jenis_kepegawaian) {
    $labels = [
        'kontrak' => 'Pegawai Kontrak',
        'tetap' => 'Pegawai Tetap'
    ];
    return $labels[$jenis_kepegawaian] ?? ucfirst($jenis_kepegawaian);
}

$page_title = 'Profil Saya - Politeknik NEST';
include '../partials/navbar.php';
?>
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        body {
            background: #f5f5f5;
        }

        /* Main Container */
        .profile-container {
            max-width: 900px;
            margin: 30px auto 30px;
            padding: 0 20px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        /* Profile Header */
        .profile-header {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }

        .profile-info h1 {
            font-size: 28px;
            color: #1e3a5f;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            align-items: center;
            color: #546e7a;
            font-size: 14px;
        }

        .profile-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .profile-meta .dot {
            width: 6px;
            height: 6px;
            background: #0d47a1;
            border-radius: 50%;
        }

        .badge-pegawai {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-dosen {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .badge-staff {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .badge-tendik {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .badge-kontrak {
            background: #ff9800;
            color: white;
        }

        .badge-tetap {
            background: #4caf50;
            color: white;
        }

        /* Info Section */
        .info-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 18px;
            color: #546e7a;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #546e7a;
            font-size: 14px;
        }

        .info-item i {
            font-size: 18px;
            width: 24px;
            color: #546e7a;
        }

        /* Status Kepegawaian Card */
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            border-radius: 15px;
            color: white;
            margin-bottom: 20px;
        }

        .status-card h3 {
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .status-item {
            display: flex;
            flex-direction: column;
        }

        .status-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .status-value {
            font-size: 16px;
            font-weight: 600;
        }

        /* Riwayat Kepegawaian */
        .riwayat-timeline {
            position: relative;
            padding-left: 30px;
        }

        .riwayat-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 8px;
            bottom: 8px;
            width: 2px;
            background: #e0e0e0;
        }

        .riwayat-item {
            position: relative;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .riwayat-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 24px;
            width: 12px;
            height: 12px;
            background: #0d47a1;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e0e0e0;
        }

        .riwayat-jabatan {
            font-size: 16px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 5px;
        }

        .riwayat-unit {
            color: #0d47a1;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .riwayat-periode {
            color: #9e9e9e;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .riwayat-keterangan {
            color: #546e7a;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Reward Section */
        .reward-item {
            background: #f1f8f4;
            border-left: 4px solid #4caf50;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .reward-item:hover {
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
            transform: translateX(2px);
        }

        .reward-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 8px;
        }

        .reward-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e3a5f;
            flex: 1;
        }

        .reward-category {
            background: #4caf50;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .reward-description {
            font-size: 14px;
            color: #546e7a;
            line-height: 1.5;
            margin-bottom: 10px;
        }

        .reward-date {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #2e7d32;
            font-weight: 500;
        }

        .reward-date i {
            font-size: 14px;
        }

        /* Sertifikasi Grid */
        .sertifikasi-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .sertifikasi-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #4caf50;
        }

        .sertifikasi-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 5px;
        }

        .sertifikasi-type {
            color: #4caf50;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .sertifikasi-year {
            color: #9e9e9e;
            font-size: 13px;
        }

        /* Documents */
        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .document-item:hover {
            background: #e3f2fd;
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(13, 71, 161, 0.15);
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .document-icon {
            width: 48px;
            height: 48px;
            background: #0d47a1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .document-item:hover .document-icon {
            background: #1565c0;
            transform: scale(1.05);
        }

        .document-icon i {
            color: white;
            font-size: 24px;
        }

        .document-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 3px;
        }

        .document-meta {
            font-size: 12px;
            color: #9e9e9e;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9e9e9e;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
            }

            .sertifikasi-grid {
                grid-template-columns: 1fr;
            }

            .profile-meta {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-info">
                    <h1>
                        <?= htmlspecialchars($user['nama_lengkap'] ?? 'Nama Lengkap') ?>
                        <?php if (isset($user['jenis_pegawai'])): ?>
                        <span class="badge-pegawai badge-<?= $user['jenis_pegawai'] ?>">
                            <?= getJenisPegawaiLabel($user['jenis_pegawai']) ?>
                        </span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($status_kepegawaian || $experience_years > 0): ?>
                    <div class="profile-meta">
                        <?php if ($status_kepegawaian && !empty($status_kepegawaian['jabatan'])): ?>
                        <span><?= htmlspecialchars($status_kepegawaian['jabatan']) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($status_kepegawaian && !empty($status_kepegawaian['unit_kerja'])): ?>
                        <span class="dot"></span>
                        <span><?= htmlspecialchars($status_kepegawaian['unit_kerja']) ?></span>
                        <?php endif; ?>
                        
                        <?php if ($experience_years > 0): ?>
                        <span class="dot"></span>
                        <span><?= $experience_years ?> Tahun Pengalaman</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Kepegawaian -->
            <?php if ($status_kepegawaian): ?>
            <div class="info-section">
                <div class="status-card">
                    <h3>Status Kepegawaian</h3>
                    <div class="status-grid">
                        <?php if (!empty($status_kepegawaian['jenis_kepegawaian'])): ?>
                        <div class="status-item">
                            <div class="status-label">Jenis Kepegawaian</div>
                            <div class="status-value"><?= getJenisKepegawaianLabel($status_kepegawaian['jenis_kepegawaian']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($status_kepegawaian['status_aktif'])): ?>
                        <div class="status-item">
                            <div class="status-label">Status</div>
                            <div class="status-value"><?= ucfirst($status_kepegawaian['status_aktif']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($status_kepegawaian['tanggal_mulai_kerja'])): ?>
                        <div class="status-item">
                            <div class="status-label">Tanggal Mulai Kerja</div>
                            <div class="status-value"><?= date('d F Y', strtotime($status_kepegawaian['tanggal_mulai_kerja'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($status_kepegawaian['jenis_kepegawaian'] == 'kontrak' && !empty($status_kepegawaian['masa_kontrak_selesai'])): ?>
                        <div class="status-item">
                            <div class="status-label">Masa Kontrak Berakhir</div>
                            <div class="status-value"><?= date('d F Y', strtotime($status_kepegawaian['masa_kontrak_selesai'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Contact Information -->
            <div class="info-section">
                <h2 class="section-title">Informasi Kontak</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <i class="bi bi-envelope-fill"></i>
                        <span><?= htmlspecialchars($user['email']) ?></span>
                    </div>
                    <?php if (!empty($user['no_telepon'])): ?>
                    <div class="info-item">
                        <i class="bi bi-telephone-fill"></i>
                        <span><?= htmlspecialchars($user['no_telepon']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['tanggal_lahir'])): ?>
                    <div class="info-item">
                        <i class="bi bi-calendar-fill"></i>
                        <span><?= htmlspecialchars($user['tempat_lahir'] ?? '') ?>, <?= date('d F Y', strtotime($user['tanggal_lahir'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['alamat_domisili'])): ?>
                    <div class="info-item">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span><?= htmlspecialchars($user['alamat_domisili']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['nik'])): ?>
                    <div class="info-item">
                        <i class="bi bi-credit-card-fill"></i>
                        <span>NIK: <?= htmlspecialchars($user['nik']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['nip'])): ?>
                    <div class="info-item">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>NIP: <?= htmlspecialchars($user['nip']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['nidn']) && $user['jenis_pegawai'] == 'dosen'): ?>
                    <div class="info-item">
                        <i class="bi bi-mortarboard-fill"></i>
                        <span>NIDN: <?= htmlspecialchars($user['nidn']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['prodi']) && $user['jenis_pegawai'] == 'dosen'): ?>
                    <div class="info-item">
                        <i class="bi bi-book-fill"></i>
                        <span>Program Studi: <?= htmlspecialchars($user['prodi']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reward dan Penghargaan -->
            <div class="info-section">
                <h2 class="section-title">Reward dan Penghargaan</h2>
                <?php if (count($rewards) > 0): ?>
                    <?php foreach ($rewards as $reward): ?>
                    <div class="reward-item">
                        <div class="reward-header">
                            <div class="reward-title"><?= htmlspecialchars($reward['judul_reward']) ?></div>
                            <?php if (!empty($reward['kategori'])): ?>
                            <span class="reward-category"><?= htmlspecialchars($reward['kategori']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($reward['deskripsi'])): ?>
                        <div class="reward-description"><?= nl2br(htmlspecialchars($reward['deskripsi'])) ?></div>
                        <?php endif; ?>
                        <div class="reward-date">
                            <i class="bi bi-calendar-check"></i>
                            <?= date('d F Y', strtotime($reward['tanggal_reward'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-trophy"></i>
                        <p>Belum ada reward atau penghargaan yang tercatat</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Riwayat Kepegawaian -->
            <?php if (count($riwayat_kepegawaian) > 0): ?>
            <div class="info-section">
                <h2 class="section-title">Riwayat Kepegawaian</h2>
                <div class="riwayat-timeline">
                    <?php foreach ($riwayat_kepegawaian as $riwayat): ?>
                    <div class="riwayat-item">
                        <?php if (!empty($riwayat['jabatan'])): ?>
                        <div class="riwayat-jabatan"><?= htmlspecialchars($riwayat['jabatan']) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($riwayat['unit_kerja'])): ?>
                        <div class="riwayat-unit"><?= htmlspecialchars($riwayat['unit_kerja']) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($riwayat['tanggal_mulai']) || !empty($riwayat['tanggal_selesai'])): ?>
                        <div class="riwayat-periode">
                            <?= !empty($riwayat['tanggal_mulai']) ? date('F Y', strtotime($riwayat['tanggal_mulai'])) : '-' ?> - 
                            <?= !empty($riwayat['tanggal_selesai']) ? date('F Y', strtotime($riwayat['tanggal_selesai'])) : 'Sekarang' ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($riwayat['keterangan'])): ?>
                        <div class="riwayat-keterangan"><?= nl2br(htmlspecialchars($riwayat['keterangan'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sertifikasi (Khusus Dosen) -->
            <?php if (count($sertifikasi) > 0): ?>
            <div class="info-section">
                <h2 class="section-title">Sertifikasi & Kompetensi</h2>
                <div class="sertifikasi-grid">
                    <?php foreach ($sertifikasi as $sert): ?>
                    <div class="sertifikasi-item">
                        <div class="sertifikasi-name"><?= htmlspecialchars($sert['nama_sertifikasi']) ?></div>
                        <div class="sertifikasi-type">
                            <?= ucfirst(str_replace('_', ' ', $sert['jenis_sertifikasi'])) ?> 
                            (<?= ucfirst($sert['kategori']) ?>)
                        </div>
                        <div class="sertifikasi-year">
                            Tahun: <?= $sert['tahun_sertifikasi'] ?>
                            <?php if (!empty($sert['tahun_masa_berlaku'])): ?>
                            - Berlaku hingga: <?= $sert['tahun_masa_berlaku'] ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <div class="info-section">
                <h2 class="section-title">Dokumen Kepegawaian</h2>
                <?php if (count($documents) > 0): ?>
                    <?php foreach ($documents as $doc): ?>
                    <a href="../../<?= htmlspecialchars($doc['path_file']) ?>" 
                       target="_blank" 
                       class="document-item" 
                       style="text-decoration: none; cursor: pointer;">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="bi bi-file-earmark-<?= getDocumentIcon($doc['jenis_dokumen'], $doc['nama_file']) ?>-fill"></i>
                            </div>
                            <div class="document-details">
                                <h4><?= getDocumentLabel($doc['jenis_dokumen']) ?></h4>
                                <div class="document-meta">
                                    <?= htmlspecialchars($doc['nama_file']) ?> • 
                                    <?= number_format($doc['ukuran_file'] / 1024, 1) ?> MB • 
                                    Diupload <?= date('d M Y', strtotime($doc['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-file-earmark"></i>
                        <p>Belum ada dokumen kepegawaian yang diupload</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Logging out...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    window.location.href = '../../auth/logout.php';
                }
            });
        }
    </script>

<?php include '../partials/footer.php'; ?>