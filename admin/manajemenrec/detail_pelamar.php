<?php

// Koneksi database
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ../auth/login_pegawai.php');
    exit();
}

// GET LAMARAN ID
$lamaran_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lamaran_id <= 0) {
    die("ID Lamaran tidak valid");
}

// GET COMPLETE DATA
try {
    $query = "
        SELECT 
            -- Data Lamaran
            l.lamaran_id,
            l.pelamar_id,
            l.lowongan_id,
            l.status_lamaran,
            l.tanggal_daftar,
            l.tanggal_update,
            l.catatan_admin,
            
            -- Data Pelamar (CV)
            p.nama_lengkap,
            p.gelar,
            p.email_aktif,
            p.no_wa,
            p.tempat_lahir,
            p.tanggal_lahir,
            p.alamat_ktp,
            p.alamat_domisili,
            
            -- Lowongan
            lp.posisi,
            
            -- Pendidikan (latest)
            pd.jenjang,
            pd.nama_universitas,
            pd.program_studi,
            pd.ipk,
            
            -- Pengalaman (latest)
            pg.nama_perusahaan,
            pg.pengalaman_kerja_terakhir,
            pg.pengalaman_mengajar,
            pg.tautan_portofolio,
            pg.keahlian_utama,
            pg.skk_path,
            pg.skk_nama,
            pg.skk_size,
            
            -- Dokumen
            (SELECT path_file FROM dokumen_pelamar WHERE pelamar_id = p.pelamar_id AND jenis_dokumen = 'cv' ORDER BY dokumen_id DESC LIMIT 1) as cv_path,
            (SELECT path_file FROM dokumen_pelamar WHERE pelamar_id = p.pelamar_id AND jenis_dokumen = 'ijazah' ORDER BY dokumen_id DESC LIMIT 1) as ijazah_path,
            (SELECT path_file FROM dokumen_pelamar WHERE pelamar_id = p.pelamar_id AND jenis_dokumen = 'kartu identitas' ORDER BY dokumen_id DESC LIMIT 1) as ktp_path,
            
            -- Form Lanjutan: Susunan Keluarga
            sk.nama_ayah,
            sk.nama_ibu,
            sk.pekerjaan_ayah,
            sk.pekerjaan_ibu,
            
            -- Form Lanjutan: Kontak Darurat
            kd.nama as kontak_darurat_nama,
            kd.hubungan as kontak_darurat_hubungan,
            kd.nomor_telepon as kontak_darurat_telp,
            
            -- Form Lanjutan: Kondisi Kesehatan
            kes.riwayat_sakit_berat,
            kes.detail_penyakit,
            
            -- Form Lanjutan: Riwayat Pekerjaan Sebelumnya
            rps.alasan_berhenti,
            rps.gaji_terakhir,
            rps.surat_keterangan_kerja,
            
            -- Form Lanjutan: Surat SKCK (UPDATED - dengan file upload)
            skck.punya_skck,
            skck.keterangan as keterangan_skck,
            skck.path_file as skck_file_path,
            skck.nama_file as skck_file_nama,
            skck.ukuran_file as skck_file_size,
            skck.tanggal_upload as skck_tanggal_upload,
            
            -- Form Lanjutan: Kesediaan & Komitmen (UPDATED - tambah waktu_mulai_kerja)
            kom.kesediaan_tunduk_peraturan,
            kom.waktu_mulai_kerja,
            
            -- Form Lanjutan: Surat Pernyataan (NEW!)
            sp.path_file as surat_pernyataan_path,
            sp.nama_file as surat_pernyataan_nama,
            sp.ukuran_file as surat_pernyataan_ukuran,
            sp.uploaded_at as surat_pernyataan_tanggal,
            
            -- Jadwal Psikotes
            jp.tanggal_psikotes,
            jp.lokasi as lokasi_psikotes,
            jp.keterangan as keterangan_psikotes,
            
            -- Jadwal Interview
            ji.tanggal_interview,
            ji.lokasi as lokasi_interview,
            ji.pewawancara,
            ji.keterangan as keterangan_interview
            
        FROM lamaran l
        INNER JOIN pelamar p ON l.pelamar_id = p.pelamar_id
        INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
        LEFT JOIN pendidikan_pelamar pd ON p.pelamar_id = pd.pelamar_id
        LEFT JOIN pengalaman_pelamar pg ON p.pelamar_id = pg.pelamar_id
        LEFT JOIN susunan_keluarga sk ON l.lamaran_id = sk.lamaran_id
        LEFT JOIN kontak_darurat kd ON l.lamaran_id = kd.lamaran_id
        LEFT JOIN kondisi_kesehatan kes ON l.lamaran_id = kes.lamaran_id
        LEFT JOIN riwayat_pekerjaan_sebelumnya rps ON l.lamaran_id = rps.lamaran_id
        LEFT JOIN surat_skck skck ON l.lamaran_id = skck.lamaran_id
        LEFT JOIN kesediaan_komitmen kom ON l.lamaran_id = kom.lamaran_id
        LEFT JOIN surat_pernyataan_pelamar sp ON l.lamaran_id = sp.lamaran_id
        LEFT JOIN jadwal_psikotes jp ON l.lamaran_id = jp.lamaran_id
        LEFT JOIN jadwal_interview ji ON l.lamaran_id = ji.lamaran_id
        WHERE l.lamaran_id = :lamaran_id
        ORDER BY pd.pendidikan_id DESC, pg.pengalaman_id DESC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':lamaran_id', $lamaran_id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        die("Data tidak ditemukan");
    }
    
    // GET Saudara Kandung
    $querySaudara = "
        SELECT 
            saud.nama_saudara,
            saud.pekerjaan_saudara
        FROM saudara_kandung saud
        INNER JOIN susunan_keluarga sk ON saud.keluarga_id = sk.keluarga_id
        WHERE sk.lamaran_id = :lamaran_id
    ";
    $stmtSaudara = $conn->prepare($querySaudara);
    $stmtSaudara->bindParam(':lamaran_id', $lamaran_id, PDO::PARAM_INT);
    $stmtSaudara->execute();
    $saudaraKandung = $stmtSaudara->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function getStatusBadge($status) {
    $map = [
        'dikirim' => ['class' => 'warning', 'text' => 'Menunggu Verifikasi'],
        'seleksi_administrasi' => ['class' => 'info', 'text' => 'Sedang Diverifikasi'],
        'lolos_administrasi' => ['class' => 'success', 'text' => 'Lolos Administrasi'],
        'form_lanjutan' => ['class' => 'info', 'text' => 'Mengisi Form Lanjutan'],
        'lolos_form' => ['class' => 'success', 'text' => 'Form Selesai'],
        'ditolak_form' => ['class' => 'danger', 'text' => 'Ditolak (Form)'],
        'tidak_lolos_administrasi' => ['class' => 'danger', 'text' => 'Tidak Lolos'],
        'psikotes' => ['class' => 'primary', 'text' => 'Psikotes'],
        'lolos_psikotes' => ['class' => 'success', 'text' => 'Lolos Psikotes'],
        'ditolak_psikotes' => ['class' => 'danger', 'text' => 'Ditolak (Psikotes)'],
        'interview' => ['class' => 'primary', 'text' => 'Interview'],
        'ditolak_interview' => ['class' => 'danger', 'text' => 'Ditolak (Interview)'],
        'diterima' => ['class' => 'success', 'text' => 'Diterima'],
    ];
    $badge = $map[$status] ?? ['class' => 'secondary', 'text' => $status];
    return '<span class="badge bg-' . $badge['class'] . ' fs-6 px-3 py-2">' . $badge['text'] . '</span>';
}

// Check which sections to display based on status
$showFormCV = in_array($data['status_lamaran'], ['dikirim', 'seleksi_administrasi', 'lolos_administrasi', 'form_lanjutan', 'lolos_form', 'psikotes', 'lolos_psikotes', 'interview', 'diterima', 'ditolak_interview', 'ditolak_psikotes', 'ditolak_form', 'tidak_lolos_administrasi']);
$showFormLanjutan = in_array($data['status_lamaran'], ['form_lanjutan', 'lolos_form', 'psikotes', 'lolos_psikotes', 'interview', 'diterima', 'ditolak_interview', 'ditolak_psikotes']);
$showJadwalPsikotes = in_array($data['status_lamaran'], ['psikotes', 'lolos_psikotes', 'ditolak_psikotes', 'interview', 'diterima', 'ditolak_interview']);
$showJadwalInterview = in_array($data['status_lamaran'], ['interview', 'diterima', 'ditolak_interview']);

$page_title = 'Detail Pelamar - ' . $data['nama_lengkap'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f7fa;
            padding: 20px 0;
        }
        .container-detail {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px; /* Added padding for mobile */
        }
        .header-detail {
            background: linear-gradient(135deg, #ec4899, #f472b6);
            color: white;
            padding: 30px;
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header-detail h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        .header-detail p {
            margin: 5px 0 0;
            opacity: 0.9;
        }
        .content-detail {
            background: white;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .section-title {
            background: #f9fafb;
            padding: 20px 30px;
            border-bottom: 2px solid #e5e7eb;
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #ec4899;
            font-size: 24px;
        }
        .section-content {
            padding: 30px;
        }
        .info-group {
            margin-bottom: 25px;
        }
        .info-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 6px;
        }
        .info-value {
            font-size: 15px;
            color: #1a1a1a;
            font-weight: 600;
            word-wrap: break-word;
        }
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 30px 0;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #6b7280;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.2s;
        }
        .back-btn:hover {
            background: #4b5563;
            color: white;
            transform: translateX(-3px);
        }
        .document-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }
        .document-card:hover {
            border-color: #ec4899;
            box-shadow: 0 4px 12px rgba(236,72,153,0.2);
        }
        .document-card i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .table-saudara {
            margin-top: 20px;
        }
        .table-saudara th {
            background: #f9fafb;
            font-weight: 600;
            color: #6b7280;
            font-size: 13px;
        }
        .alert-section {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .timeline-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .print-btn {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .print-btn:hover {
            background: #2563eb;
        }
        .empty-data {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
        }
        .empty-data i {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .btn .bi-eye {
            font-size: 20px;
        }
        
        /* RESPONSIVE STYLES */
        
        /* Tablet (iPad) - 768px to 1024px */
        @media (max-width: 1024px) {
            .container-detail {
                padding: 0 20px;
            }
            
            .header-detail {
                padding: 25px 20px;
            }
            
            .header-detail h1 {
                font-size: 24px;
            }
            
            .header-detail p {
                font-size: 14px;
            }
            
            .section-content {
                padding: 25px 20px;
            }
            
            .section-title {
                padding: 18px 20px;
                font-size: 16px;
            }
            
            .document-card {
                margin-bottom: 15px;
            }
        }
        
        /* Mobile - up to 767px */
        @media (max-width: 767px) {
            body {
                padding: 15px 0;
            }
            
            .container-detail {
                padding: 0 15px;
            }
            
            .header-detail {
                padding: 20px 15px;
                border-radius: 12px 12px 0 0;
            }
            
            .header-detail h1 {
                font-size: 20px;
                line-height: 1.4;
            }
            
            .header-detail h1 i {
                font-size: 20px;
            }
            
            .header-detail p {
                font-size: 13px;
                line-height: 1.6;
            }
            
            .header-detail p .ms-4 {
                margin-left: 0 !important;
                display: block;
                margin-top: 5px;
            }
            
            .content-detail {
                border-radius: 0 0 12px 12px;
            }
            
            .section-title {
                padding: 15px;
                font-size: 15px;
                flex-wrap: wrap;
            }
            
            .section-title i {
                font-size: 20px;
            }
            
            .section-content {
                padding: 20px 15px;
            }
            
            .info-group {
                margin-bottom: 20px;
            }
            
            .info-label {
                font-size: 12px;
            }
            
            .info-value {
                font-size: 14px;
            }
            
            .divider {
                margin: 20px 0;
            }
            
            /* Buttons responsive */
            .back-btn {
                padding: 8px 15px;
                font-size: 14px;
                margin-bottom: 15px;
                width: 100%;
                justify-content: center;
            }
            
            .print-btn {
                padding: 8px 15px;
                font-size: 14px;
                margin-bottom: 15px;
                width: 100%;
                justify-content: center;
            }
            
            .float-end {
                float: none !important;
            }
            
            /* Document cards stack properly */
            .document-card {
                margin-bottom: 15px;
                padding: 15px;
            }
            
            .document-card i {
                font-size: 36px;
            }
            
            .document-card h6 {
                font-size: 14px;
            }
            
            /* Table responsive */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table-saudara {
                font-size: 13px;
            }
            
            .table-saudara th,
            .table-saudara td {
                padding: 10px 8px;
            }
            
            /* Timeline boxes */
            .timeline-box {
                padding: 15px;
            }
            
            .timeline-box h6 {
                font-size: 14px;
            }
            
            .timeline-box .row {
                font-size: 13px;
            }
            
            /* Alert sections */
            .alert-section {
                padding: 12px 15px;
                font-size: 13px;
            }
            
            /* File display boxes */
            .alert-success,
            .alert-warning,
            .alert-info {
                font-size: 13px;
                padding: 12px 15px;
            }
            
            /* Badge sizes */
            .badge {
                font-size: 12px !important;
                padding: 4px 8px !important;
            }
            
            /* Empty state */
            .empty-data {
                padding: 30px 15px;
            }
            
            .empty-data i {
                font-size: 36px;
            }
            
            .empty-data h5 {
                font-size: 16px;
            }
            
            .empty-data p {
                font-size: 13px;
            }
        }
        
        /* Small mobile - up to 480px */
        @media (max-width: 480px) {
            .header-detail h1 {
                font-size: 18px;
            }
            
            .header-detail p {
                font-size: 12px;
            }
            
            .section-title {
                font-size: 14px;
                padding: 12px;
            }
            
            .section-content {
                padding: 15px 12px;
            }
            
            .info-label {
                font-size: 11px;
            }
            
            .info-value {
                font-size: 13px;
            }
            
            .document-card {
                padding: 12px;
            }
            
            .btn-sm {
                font-size: 12px;
                padding: 5px 10px;
            }
        }
        
        /* Make tables scrollable on mobile */
        @media (max-width: 767px) {
            .table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 0 -15px;
                padding: 0 15px;
            }
        }
        
        @media print {
            .back-btn, .print-btn {
                display: none !important;
            }
            
            body {
                padding: 0;
            }
            
            .container-detail {
                padding: 0;
            }
        }
    </style>
</head>
<body>

<div class="container-detail">
    <a href="manajemenrec.php" class="back-btn">
        <i class="bi bi-arrow-left"></i>
        Kembali ke Daftar Pelamar
    </a>
    
    <button onclick="window.print()" class="print-btn float-end">
        <i class="bi bi-printer"></i>
        Cetak Detail
    </button>
    
    <div class="clearfix mb-3"></div>
    
    <!-- HEADER -->
    <div class="header-detail">
        <h1><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($data['nama_lengkap']) ?></h1>
        <p>
            <i class="bi bi-briefcase me-2"></i>Melamar sebagai: <strong><?= htmlspecialchars($data['posisi']) ?></strong>
            <span class="ms-4">
                <i class="bi bi-calendar me-2"></i>Tanggal Daftar: <?= date('d F Y', strtotime($data['tanggal_daftar'])) ?>
            </span>
            <span class="ms-4">Status: <?= getStatusBadge($data['status_lamaran']) ?></span>
        </p>
    </div>
    
    <div class="content-detail">
        
        <?php if ($showFormCV): ?>
        <!-- ===== SECTION: DATA CV ===== -->
        
        <!-- SECTION 1: DATA PRIBADI -->
        <div class="section-title">
            <i class="bi bi-person-badge"></i>
            Data Pribadi (dari CV)
        </div>
        <div class="section-content">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Nama Lengkap & Gelar</div>
                        <div class="info-value"><?= htmlspecialchars($data['nama_lengkap']) ?><?= $data['gelar'] ? ', ' . htmlspecialchars($data['gelar']) : '' ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Tempat, Tanggal Lahir</div>
                        <div class="info-value"><?= htmlspecialchars($data['tempat_lahir'] ?? '-') ?>, <?= $data['tanggal_lahir'] ? date('d F Y', strtotime($data['tanggal_lahir'])) : '-' ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Email Aktif</div>
                        <div class="info-value"><i class="bi bi-envelope me-2 text-primary"></i><?= htmlspecialchars($data['email_aktif']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Nomor WhatsApp</div>
                        <div class="info-value"><i class="bi bi-whatsapp me-2 text-success"></i><?= htmlspecialchars($data['no_wa']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Alamat KTP</div>
                        <div class="info-value"><?= htmlspecialchars($data['alamat_ktp']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Alamat Domisili</div>
                        <div class="info-value"><?= htmlspecialchars($data['alamat_domisili']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SECTION 2: PENDIDIKAN -->
        <div class="section-title">
            <i class="bi bi-mortarboard"></i>
            Riwayat Pendidikan
        </div>
        <div class="section-content">
            <?php if ($data['jenjang']): ?>
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="info-group">
                        <div class="info-label">Jenjang Pendidikan</div>
                        <div class="info-value"><?= htmlspecialchars($data['jenjang']) ?></div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="info-group">
                        <div class="info-label">Nama Universitas/Institut</div>
                        <div class="info-value"><?= htmlspecialchars($data['nama_universitas']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-group">
                        <div class="info-label">Program Studi</div>
                        <div class="info-value"><?= htmlspecialchars($data['program_studi']) ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-group">
                        <div class="info-label">IPK / GPA</div>
                        <div class="info-value">
                            <span class="badge bg-success fs-6"><?= number_format($data['ipk'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-data">
                <i class="bi bi-inbox"></i>
                <p>Data pendidikan belum diisi</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- SECTION 3: PENGALAMAN -->
        <div class="section-title">
            <i class="bi bi-briefcase"></i>
            Pengalaman Kerja & Keahlian
        </div>
        <div class="section-content">
            <?php if ($data['pengalaman_kerja_terakhir']): ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Pengalaman Kerja Terakhir (Jabatan)</div>
                        <div class="info-value"><?= htmlspecialchars($data['pengalaman_kerja_terakhir']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Nama Perusahaan</div>
                        <div class="info-value"><?= htmlspecialchars($data['nama_perusahaan']) ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Pengalaman Mengajar (untuk Dosen)</div>
                        <div class="info-value"><?= htmlspecialchars($data['pengalaman_mengajar'] ?? 'Tidak ada') ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Tautan Portofolio</div>
                        <div class="info-value">
                            <?php if ($data['tautan_portofolio']): ?>
                                <a href="<?= htmlspecialchars($data['tautan_portofolio']) ?>" target="_blank" class="text-primary">
                                    <i class="bi bi-link-45deg"></i> <?= htmlspecialchars($data['tautan_portofolio']) ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="info-group">
                        <div class="info-label">Keahlian Utama</div>
                        <div class="info-value">
                            <div class="p-3 bg-light border rounded">
                                <?= htmlspecialchars($data['keahlian_utama'] ?? '-') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-data">
                <i class="bi bi-inbox"></i>
                <p>Data pengalaman kerja belum diisi</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- SECTION 4: DOKUMEN LAMARAN -->
        <div class="section-title">
            <i class="bi bi-folder-check"></i>
            Dokumen Lamaran
        </div>
        <div class="section-content">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="document-card">
                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                        <h6>CV / Resume</h6>
                        <?php if ($data['cv_path']): ?>
                            <a href="<?= htmlspecialchars($data['cv_path']) ?>" target="_blank" class="btn btn-outline-danger btn-sm mt-2 w-100">
                                <i class="bi bi-eye me-2"></i>Lihat CV
                            </a>
                        <?php else: ?>
                            <span class="badge bg-warning mt-2">Belum Diupload</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="document-card">
                        <i class="bi bi-file-earmark-pdf text-danger"></i>
                        <h6>Ijazah / SKL</h6>
                        <?php if ($data['ijazah_path']): ?>
                            <a href="<?= htmlspecialchars($data['ijazah_path']) ?>" target="_blank" class="btn btn-outline-danger btn-sm mt-2 w-100">
                                <i class="bi bi-eye me-2"></i>Lihat Ijazah
                            </a>
                        <?php else: ?>
                            <span class="badge bg-warning mt-2">Belum Diupload</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="document-card">
                        <i class="bi bi-file-earmark-image text-primary"></i>
                        <h6>Kartu Identitas</h6>
                        <?php if ($data['ktp_path']): ?>
                            <a href="<?= htmlspecialchars($data['ktp_path']) ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-2 w-100">
                                <i class="bi bi-eye me-2"></i>Lihat KTP/SIM
                            </a>
                        <?php else: ?>
                            <span class="badge bg-warning mt-2">Belum Diupload</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; // End showFormCV ?>
        
        <?php if ($showFormLanjutan): ?>
        <!-- ===== SECTION: FORM LANJUTAN ===== -->
        
        <div class="section-title">
            <i class="bi bi-clipboard-data"></i>
            Data Formulir Lanjutan
        </div>
        <div class="section-content">
            
            <?php if ($data['nama_ayah'] || $data['nama_ibu']): ?>
            <!-- Susunan Keluarga -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-people me-2 text-primary"></i>Susunan Keluarga
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Nama Ayah</div>
                        <div class="info-value"><?= htmlspecialchars($data['nama_ayah'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Pekerjaan Ayah</div>
                        <div class="info-value"><?= htmlspecialchars($data['pekerjaan_ayah'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Nama Ibu</div>
                        <div class="info-value"><?= htmlspecialchars($data['nama_ibu'] ?? '-') ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Pekerjaan Ibu</div>
                        <div class="info-value"><?= htmlspecialchars($data['pekerjaan_ibu'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (count($saudaraKandung) > 0): ?>
            <h6 class="mb-3">Saudara Kandung</h6>
            <div class="table-wrapper">
                <table class="table table-bordered table-saudara">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Nama Saudara</th>
                            <th>Pekerjaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saudaraKandung as $idx => $saudara): ?>
                        <tr>
                            <td class="text-center"><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($saudara['nama_saudara']) ?></td>
                            <td><?= htmlspecialchars($saudara['pekerjaan_saudara']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-secondary">Tidak ada data saudara kandung</div>
            <?php endif; ?>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if ($data['kontak_darurat_nama']): ?>
            <!-- Kontak Darurat -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-telephone-outbound me-2 text-warning"></i>Kontak Darurat
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="info-group">
                        <div class="info-label">Nama Kontak</div>
                        <div class="info-value"><?= htmlspecialchars($data['kontak_darurat_nama']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-group">
                        <div class="info-label">Hubungan</div>
                        <div class="info-value"><?= htmlspecialchars($data['kontak_darurat_hubungan']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-group">
                        <div class="info-label">Nomor Telepon Darurat</div>
                        <div class="info-value text-danger fw-bold">
                            <i class="bi bi-telephone-fill me-2"></i><?= htmlspecialchars($data['kontak_darurat_telp']) ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if (isset($data['riwayat_sakit_berat'])): ?>
            <!-- Kondisi Kesehatan -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-heart-pulse me-2 text-danger"></i>Kondisi Kesehatan
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <div class="info-group">
                        <div class="info-label">Apakah pernah menderita sakit berat?</div>
                        <div class="info-value">
                            <span class="badge <?= $data['riwayat_sakit_berat'] == 'ya' ? 'bg-danger' : 'bg-success' ?>">
                                <?= $data['riwayat_sakit_berat'] == 'ya' ? 'Ya' : 'Tidak' ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($data['riwayat_sakit_berat'] == 'ya' && $data['detail_penyakit']): ?>
                    <div class="alert alert-warning mt-2">
                        <strong>Detail Penyakit:</strong><br>
                        <?= nl2br(htmlspecialchars($data['detail_penyakit'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if ($data['alasan_berhenti'] || $data['gaji_terakhir'] || $data['surat_keterangan_kerja']): ?>
            <!-- Riwayat Pekerjaan Sebelumnya -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-clock-history me-2 text-info"></i>Riwayat Pekerjaan Sebelumnya
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Alasan Berhenti Dari Pekerjaan Terakhir</div>
                        <div class="info-value"><?= htmlspecialchars($data['alasan_berhenti'] ?? 'Tidak ada pekerjaan sebelumnya') ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-group">
                        <div class="info-label">Gaji Terakhir</div>
                        <div class="info-value">
                            <?= $data['gaji_terakhir'] ? 'Rp ' . number_format($data['gaji_terakhir'], 0, ',', '.') : '-' ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-group">
                        <div class="info-label">Surat Keterangan Kerja</div>
                        <div class="info-value">
                            <span class="badge <?= $data['surat_keterangan_kerja'] == 'ya' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $data['surat_keterangan_kerja'] == 'ya' ? 'Ada' : 'Tidak Ada' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($data['surat_keterangan_kerja'] == 'ya' && $data['skk_path']): ?>
            <!-- Upload Surat Keterangan Kerja -->
            <div class="alert alert-success mb-3">
                <i class="bi bi-file-earmark-check me-2"></i>
                <strong>Surat Keterangan Kerja Telah Diupload</strong>
            </div>
            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="bi bi-file-earmark-pdf text-info" style="font-size: 40px;"></i>
                    </div>
                    <div class="col">
                        <h6 class="mb-1" style="font-size: 14px; font-weight: 600;"><?= htmlspecialchars($data['skk_nama']) ?></h6>
                        <div class="text-muted" style="font-size: 12px;">
                            <i class="bi bi-hdd me-1"></i>Ukuran: <?= number_format($data['skk_size'], 2) ?> MB
                        </div>
                    </div>
                    <div class="col-auto">
                        <a href="<?= htmlspecialchars($data['skk_path']) ?>" 
                           target="_blank" 
                           class="btn btn-info btn-sm me-1"
                           style="padding: 6px 15px; font-size: 13px;">
                            <i class="bi bi-eye me-1"></i>Lihat
                        </a>
                        <a href="<?= htmlspecialchars($data['skk_path']) ?>" 
                           download 
                           class="btn btn-outline-secondary btn-sm"
                           style="padding: 6px 15px; font-size: 13px;">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif ($data['surat_keterangan_kerja'] == 'ya'): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Pelamar menyatakan memiliki surat keterangan kerja, tetapi belum mengupload filenya.
            </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if (isset($data['punya_skck'])): ?>
            <!-- Surat SKCK (UPDATED - dengan upload file PDF) -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-shield-check me-2 text-success"></i>Surat Keterangan Catatan Kepolisian (SKCK)
            </h6>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <div class="info-group">
                        <div class="info-label">Apakah memiliki SKCK?</div>
                        <div class="info-value">
                            <span class="badge <?= $data['punya_skck'] == 'ya' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $data['punya_skck'] == 'ya' ? 'Ya, Punya SKCK' : 'Tidak Punya SKCK' ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($data['punya_skck'] == 'ya' && $data['skck_file_path']): ?>
                    <!-- SKCK File Uploaded -->
                    <div class="alert alert-success mt-3 mb-3">
                        <i class="bi bi-file-earmark-check me-2"></i>
                        <strong>File SKCK Telah Diupload</strong>
                    </div>
                    <div style="background: #f0fdf4; border: 2px solid #10b981; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <i class="bi bi-file-earmark-pdf text-danger" style="font-size: 40px;"></i>
                            </div>
                            <div class="col">
                                <h6 class="mb-1" style="font-size: 14px; font-weight: 600; color: #059669;">
                                    <?= htmlspecialchars($data['skck_file_nama']) ?>
                                </h6>
                                <div class="text-muted" style="font-size: 12px;">
                                    <i class="bi bi-hdd me-1"></i>Ukuran: <?= number_format($data['skck_file_size'], 2) ?> MB
                                    <?php if ($data['skck_tanggal_upload']): ?>
                                    <span class="mx-2">•</span>
                                    <i class="bi bi-calendar me-1"></i>Diupload: <?= date('d F Y, H:i', strtotime($data['skck_tanggal_upload'])) ?> WIB
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <a href="<?= htmlspecialchars($data['skck_file_path']) ?>" 
                                   target="_blank" 
                                   class="btn btn-success btn-sm me-1"
                                   style="padding: 6px 15px; font-size: 13px;">
                                    <i class="bi bi-eye me-1"></i>Lihat SKCK
                                </a>
                                <a href="<?= htmlspecialchars($data['skck_file_path']) ?>" 
                                   download 
                                   class="btn btn-outline-secondary btn-sm"
                                   style="padding: 6px 15px; font-size: 13px;">
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php elseif ($data['punya_skck'] == 'ya'): ?>
                    <!-- Punya SKCK tapi belum upload file -->
                    <div class="alert alert-warning mt-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Pelamar menyatakan memiliki SKCK, tetapi belum mengupload file PDF.</strong>
                    </div>
                    
                    <?php elseif ($data['punya_skck'] == 'tidak' && $data['keterangan_skck']): ?>
                    <!-- Tidak punya SKCK dengan keterangan -->
                    <div class="alert alert-info mt-2">
                        <strong><i class="bi bi-info-circle me-2"></i>Keterangan:</strong><br>
                        <?= nl2br(htmlspecialchars($data['keterangan_skck'])) ?>
                    </div>
                    
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if (isset($data['kesediaan_tunduk_peraturan']) || isset($data['waktu_mulai_kerja'])): ?>
            <!-- Kesediaan & Komitmen (UPDATED) -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-hand-thumbs-up me-2 text-primary"></i>Kesediaan & Komitmen
            </h6>
            
            <?php if (isset($data['kesediaan_tunduk_peraturan'])): ?>
            <div class="alert-section">
                <strong>Kesediaan Tunduk Pada Peraturan Perusahaan:</strong>
                <span class="badge <?= $data['kesediaan_tunduk_peraturan'] == 'ya' ? 'bg-success' : 'bg-danger' ?> ms-2">
                    <?= $data['kesediaan_tunduk_peraturan'] == 'ya' ? 'YA, BERSEDIA' : 'TIDAK BERSEDIA' ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($data['waktu_mulai_kerja'])): ?>
            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <div class="info-group">
                        <div class="info-label">Kapan bisa mulai bekerja?</div>
                        <div class="info-value">
                            <i class="bi bi-calendar-check me-2 text-success"></i>
                            <?php
                            $waktu_map = [
                                'segera' => 'Segera (1-3 hari)',
                                '1_minggu' => '1 Minggu',
                                '2_minggu' => '2 Minggu',
                                '1_bulan' => '1 Bulan'
                            ];
                            echo $waktu_map[$data['waktu_mulai_kerja']] ?? '-';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if ($data['surat_pernyataan_path']): ?>
            <!-- Surat Pernyataan (UPDATED - sama seperti Surat Keterangan Kerja) -->
            <h6 class="mb-3 pb-2 border-bottom">
                <i class="bi bi-file-earmark-text me-2 text-info"></i>Surat Pernyataan Kebenaran Dokumen
            </h6>
            
            <div class="alert alert-success mb-3">
                <i class="bi bi-file-earmark-check me-2"></i>
                <strong>Surat Pernyataan Telah Diupload</strong>
            </div>
            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="bi bi-file-earmark-pdf text-info" style="font-size: 40px;"></i>
                    </div>
                    <div class="col">
                        <h6 class="mb-1" style="font-size: 14px; font-weight: 600;"><?= htmlspecialchars($data['surat_pernyataan_nama']) ?></h6>
                        <div class="text-muted" style="font-size: 12px;">
                            <i class="bi bi-hdd me-1"></i>Ukuran: <?= number_format($data['surat_pernyataan_ukuran'], 2) ?> MB
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar me-1"></i>Diupload: <?= date('d F Y, H:i', strtotime($data['surat_pernyataan_tanggal'])) ?> WIB
                        </div>
                    </div>
                    <div class="col-auto">
                        <a href="<?= htmlspecialchars($data['surat_pernyataan_path']) ?>" 
                           target="_blank" 
                           class="btn btn-info btn-sm me-1"
                           style="padding: 6px 15px; font-size: 13px;">
                            <i class="bi bi-eye me-1"></i>Lihat
                        </a>
                        <a href="<?= htmlspecialchars($data['surat_pernyataan_path']) ?>" 
                           download 
                           class="btn btn-outline-secondary btn-sm"
                           style="padding: 6px 15px; font-size: 13px;">
                            <i class="bi bi-download me-1"></i>Download
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="divider"></div>
            <?php endif; ?>
            
            <?php if (!$data['nama_ayah'] && !$data['nama_ibu'] && !$data['kontak_darurat_nama']): ?>
            <!-- Empty State if no form lanjutan data -->
            <div class="empty-data">
                <i class="bi bi-card-checklist" style="font-size: 64px;"></i>
                <h5 class="mt-3">Data Formulir Lanjutan Belum Diisi</h5>
                <p>Pelamar belum mengisi formulir lanjutan</p>
            </div>
            <?php endif; ?>
            
        </div>
        
        <?php endif; // End showFormLanjutan ?>
        
        <!-- SECTION: TIMELINE & JADWAL -->
        <?php if ($showJadwalPsikotes || $showJadwalInterview): ?>
        <div class="section-title">
            <i class="bi bi-calendar-event"></i>
            Jadwal & Timeline
        </div>
        <div class="section-content">
            
            <?php if ($showJadwalPsikotes && $data['tanggal_psikotes']): ?>
            <div class="timeline-box">
                <h6><i class="bi bi-clipboard-data me-2"></i>Jadwal Psikotes</h6>
                <div class="row g-2 mt-2">
                    <div class="col-md-6">
                        <strong>Tanggal & Waktu:</strong><br>
                        <?= date('d F Y, H:i', strtotime($data['tanggal_psikotes'])) ?> WIB
                    </div>
                    <div class="col-md-6">
                        <strong>Lokasi:</strong><br>
                        <?= htmlspecialchars($data['lokasi_psikotes'] ?? '-') ?>
                    </div>
                    <?php if ($data['keterangan_psikotes']): ?>
                    <div class="col-12 mt-2">
                        <strong>Keterangan:</strong><br>
                        <?= nl2br(htmlspecialchars($data['keterangan_psikotes'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($showJadwalPsikotes): ?>
            <div class="alert alert-warning">
                <i class="bi bi-calendar-x me-2"></i>
                <strong>Jadwal psikotes belum ditentukan</strong>
            </div>
            <?php endif; ?>
            
            <?php if ($showJadwalInterview && $data['tanggal_interview']): ?>
            <div class="timeline-box">
                <h6><i class="bi bi-chat-dots me-2"></i>Jadwal Interview</h6>
                <div class="row g-2 mt-2">
                    <div class="col-md-4">
                        <strong>Tanggal & Waktu:</strong><br>
                        <?= date('d F Y, H:i', strtotime($data['tanggal_interview'])) ?> WIB
                    </div>
                    <div class="col-md-4">
                        <strong>Lokasi:</strong><br>
                        <?= htmlspecialchars($data['lokasi_interview'] ?? '-') ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Pewawancara:</strong><br>
                        <?= htmlspecialchars($data['pewawancara'] ?? '-') ?>
                    </div>
                    <?php if ($data['keterangan_interview']): ?>
                    <div class="col-12 mt-2">
                        <strong>Keterangan:</strong><br>
                        <?= nl2br(htmlspecialchars($data['keterangan_interview'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($showJadwalInterview): ?>
            <div class="alert alert-warning">
                <i class="bi bi-calendar-x me-2"></i>
                <strong>Jadwal interview belum ditentukan</strong>
            </div>
            <?php endif; ?>
            
        </div>
        <?php endif; ?>
        
        <!-- CATATAN ADMIN -->
        <?php if ($data['catatan_admin']): ?>
        <div class="section-title">
            <i class="bi bi-sticky"></i>
            Catatan Admin
        </div>
        <div class="section-content">
            <div class="alert alert-secondary">
                <strong><i class="bi bi-info-circle me-2"></i>Catatan:</strong><br>
                <?= nl2br(htmlspecialchars($data['catatan_admin'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>