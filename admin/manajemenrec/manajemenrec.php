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

$tahapFilter = isset($_GET['tahap']) ? $_GET['tahap'] : 'semua';

try {
    $queryStats = "
        SELECT 
            COUNT(*) AS total_pelamar,
            SUM(CASE WHEN status_lamaran IN ('dikirim','seleksi_administrasi','lolos_administrasi','form_lanjutan','lolos_form','psikotes','lolos_psikotes','interview') THEN 1 ELSE 0 END) AS dalam_proses,
            SUM(CASE WHEN status_lamaran = 'diterima' THEN 1 ELSE 0 END) AS diterima,
            SUM(CASE WHEN status_lamaran IN ('ditolak_interview','ditolak_psikotes','ditolak_form','tidak_lolos_administrasi') THEN 1 ELSE 0 END) AS ditolak
        FROM lamaran
    ";
    $stmt = $conn->prepare($queryStats);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$whereClause = "";
switch ($tahapFilter) {
    case 'seleksi_admin':
        $whereClause = "WHERE l.status_lamaran IN ('dikirim', 'seleksi_administrasi')";
        break;
    case 'pengisian_formulir':
        $whereClause = "WHERE l.status_lamaran IN ('lolos_administrasi', 'form_lanjutan', 'lolos_form')";
        break;
    case 'psikotes':
        $whereClause = "WHERE l.status_lamaran IN ('lolos_form', 'psikotes', 'lolos_psikotes', 'ditolak_psikotes')";
        break;
    case 'interview':
        $whereClause = "WHERE l.status_lamaran IN ('lolos_psikotes', 'interview', 'ditolak_interview')";
        break;
    case 'hasil':
        $whereClause = "WHERE l.status_lamaran IN ('diterima', 'ditolak_interview', 'ditolak_psikotes', 'ditolak_form', 'tidak_lolos_administrasi')";
        break;
}

try {
    $queryLamaran = "
        SELECT 
            l.lamaran_id,
            l.pelamar_id,
            l.status_lamaran,
            l.tanggal_daftar,
            l.tanggal_update,
            l.surat_resmi_path,
            l.surat_resmi_jenis,
            l.surat_terkirim_at,
            p.nama_lengkap,
            p.email_aktif,
            lp.posisi,
            jp.jadwal_psikotes_id,
            jp.tanggal_psikotes,
            ji.jadwal_interview_id,
            ji.tanggal_interview
        FROM lamaran l
        INNER JOIN pelamar p ON l.pelamar_id = p.pelamar_id
        INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
        LEFT JOIN jadwal_psikotes jp ON l.lamaran_id = jp.lamaran_id
        LEFT JOIN jadwal_interview ji ON l.lamaran_id = ji.lamaran_id
        $whereClause
        ORDER BY l.tanggal_daftar DESC
    ";
    
    $stmt = $conn->prepare($queryLamaran);
    $stmt->execute();
    $dataLamaran = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

function getStatusBadge($status) {
    $map = [
        'dikirim' => ['class' => 'warning', 'text' => 'Menunggu Verifikasi'],
        'seleksi_administrasi' => ['class' => 'info', 'text' => 'Sedang Diverifikasi'],
        'lolos_administrasi' => ['class' => 'success', 'text' => 'Lolos Administrasi'],
        'form_lanjutan' => ['class' => 'info', 'text' => 'Mengisi Form'],
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
    return '<span class="badge bg-' . $badge['class'] . '">' . $badge['text'] . '</span>';
}

function getTahapLabel($tahap) {
    $labels = [
        'semua' => 'Semua Data Pelamar',
        'seleksi_admin' => 'Seleksi Administrasi',
        'pengisian_formulir' => 'Pengisian Formulir',
        'psikotes' => 'Psikotes',
        'interview' => 'Interview',
        'hasil' => 'Hasil Seleksi'
    ];
    return $labels[$tahap] ?? 'Semua Data Pelamar';
}

function getTahapSekarang($row) {
    if ($row['status_lamaran'] == 'diterima') return 'Diterima';
    if (in_array($row['status_lamaran'], ['ditolak_interview', 'ditolak_psikotes', 'ditolak_form', 'tidak_lolos_administrasi'])) return 'Ditolak';
    if ($row['status_lamaran'] == 'interview' || $row['tanggal_interview']) return 'Interview';
    if (in_array($row['status_lamaran'], ['psikotes', 'lolos_psikotes']) || $row['tanggal_psikotes']) return 'Psikotes';
    if ($row['status_lamaran'] == 'lolos_form') return 'Form Selesai';
    if ($row['status_lamaran'] == 'form_lanjutan') return 'Mengisi Form';
    if ($row['status_lamaran'] == 'lolos_administrasi') return 'Lolos Administrasi';
    if ($row['status_lamaran'] == 'seleksi_administrasi' || $row['status_lamaran'] == 'dikirim') return 'Seleksi Administrasi';
    return 'Dikirim';
}

$page_title = 'Manajemen Recruitment';
$current_page = 'manajemen_recruitment';
include '../sidebar/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= $page_title ?> - SDM Polnest</title>

     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">
 


    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f7fa;
        }
        .main-content {
            margin-left: 290px;
            padding: 30px 40px;
            min-height: 100vh;
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .stat-card .icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            background: linear-gradient(135deg, #ec4899, #f472b6);
            color: white;
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .progress-section {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .progress-container {
            display: flex;
            justify-content: space-between;
            margin: 40px 0 30px;
        }
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        .progress-step:hover {
            transform: translateY(-3px);
        }
        .progress-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #9ca3af;
            margin-bottom: 12px;
            transition: all 0.3s;
        }
        .progress-step.active .progress-icon {
            background: linear-gradient(135deg, #ec4899, #f472b6);
            border-color: #ec4899;
            color: white;
            box-shadow: 0 4px 12px rgba(236,72,153,0.3);
        }
        .progress-label {
            font-size: 13px;
            font-weight: 500;
            color: #9ca3af;
            text-align: center;
        }
        .progress-step.active .progress-label {
            color: #1a1a1a;
            font-weight: 600;
        }
        
        .table-section {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .table-header {
            padding: 24px 28px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Table Responsive Wrapper */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }
        
        .data-table {
            width: 100%;
            margin-bottom: 0;
        }
        .data-table thead {
            background: #f9fafb;
        }
        .data-table thead th {
            padding: 16px 28px;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            border: none;
            white-space: nowrap;
        }
        .data-table tbody td {
            padding: 20px 28px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            white-space: nowrap;
        }
        .data-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .action-btn i {
            font-size: 13px;
        }
        
        .action-btn.view {
            background: #dbeafe;
            color: #3b82f6;
        }
        .action-btn.view:hover {
            background: #3b82f6;
            color: white;
        }
        .action-btn.approve {
            background: #d1fae5;
            color: #10b981;
        }
        .action-btn.approve:hover {
            background: #10b981;
            color: white;
        }
        .action-btn.reject {
            background: #fecaca;
            color: #ef4444;
        }
        .action-btn.reject:hover {
            background: #ef4444;
            color: white;
        }
        .action-btn.schedule {
            background: #fef3c7;
            color: #f59e0b;
        }
        .action-btn.schedule:hover {
            background: #f59e0b;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 16px;
        }
        
        /* RESPONSIVE CSS - Sesuai dengan sidebar.php */
        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px;
                padding: 20px 16px;
            }
            
            .page-header h1 {
                font-size: 24px;
                margin-bottom: 6px;
            }
            
            .page-header p {
                font-size: 13px;
            }
            
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 20px;
            }
            
            .stat-card {
                padding: 16px;
                gap: 12px;
            }
            
            .stat-card .icon-wrapper {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            
            .stat-card .stat-label {
                font-size: 11px;
            }
            
            .stat-card .stat-value {
                font-size: 22px;
            }
            
            /* Template Surat Card */
            .card.mb-4 {
                margin-bottom: 20px !important;
            }
            
            .card.mb-4 .card-body {
                padding: 16px !important;
            }
            
            .card.mb-4 .d-flex {
                flex-direction: column !important;
                gap: 16px !important;
            }
            
            .card.mb-4 .d-flex > div:first-child {
                width: 100% !important;
            }
            
            .card.mb-4 .d-flex > div > div:first-child {
                width: 48px !important;
                height: 48px !important;
                font-size: 24px !important;
            }
            
            .card.mb-4 h5 {
                font-size: 15px !important;
                margin-bottom: 4px !important;
            }
            
            .card.mb-4 p {
                font-size: 12px !important;
            }
            
            .card.mb-4 .btn {
                width: 100% !important;
                padding: 10px 16px !important;
                font-size: 13px !important;
                justify-content: center !important;
                display: flex !important;
                align-items: center !important;
            }
            
            /* Progress Section */
            .progress-section {
                padding: 20px 16px;
                margin-bottom: 20px;
            }
            
            .progress-section h2 {
                font-size: 16px;
                margin-bottom: 16px;
            }
            
            .progress-container {
                margin: 20px 0;
                gap: 8px;
                overflow-x: auto;
                padding-bottom: 10px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            
            .progress-container::-webkit-scrollbar {
                height: 4px;
            }
            
            .progress-container::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            .progress-container::-webkit-scrollbar-thumb {
                background: #ec4899;
                border-radius: 10px;
            }
            
            .progress-step {
                min-width: 80px;
            }
            
            .progress-icon {
                width: 44px;
                height: 44px;
                font-size: 18px;
                margin-bottom: 8px;
            }
            
            .progress-label {
                font-size: 11px;
                padding: 0 4px;
            }
            
            /* Table Section */
            .table-section {
                border-radius: 12px;
            }
            
            .table-header {
                padding: 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .table-title {
                font-size: 16px;
            }
            
            /* Tabel Responsive */
            .table-responsive {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                display: block !important;
                width: 100% !important;
            }
            
            .table-responsive::-webkit-scrollbar {
                height: 8px;
            }
            
            .table-responsive::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 10px;
            }
            
            .table-responsive::-webkit-scrollbar-thumb {
                background: #ec4899;
                border-radius: 10px;
            }
            
            .table-responsive::-webkit-scrollbar-thumb:hover {
                background: #db2777;
            }
            
            .data-table {
                min-width: 900px !important;
                width: 100%;
                display: table !important;
            }
            
            .data-table thead th {
                padding: 12px 16px;
                font-size: 11px;
            }
            
            .data-table tbody td {
                padding: 14px 16px;
                font-size: 13px;
            }
            
            .action-buttons {
                gap: 4px;
                flex-wrap: nowrap;
            }
            
            .action-btn {
                padding: 5px 10px;
                font-size: 11px;
                white-space: nowrap;
            }
            
            .action-btn i {
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                margin-left: 70px;
                padding: 16px 12px;
            }
            
            .page-header h1 {
                font-size: 20px;
            }
            
            .page-header p {
                font-size: 12px;
            }

            .stats-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stat-card {
                padding: 14px;
            }
            
            .stat-card .icon-wrapper {
                width: 36px;
                height: 36px;
                font-size: 16px;
            }
            
            .stat-card .stat-value {
                font-size: 20px;
            }
            
            /* Template Card */
            .card.mb-4 .card-body {
                padding: 14px !important;
            }
            
            .card.mb-4 .d-flex > div > div:first-child {
                width: 44px !important;
                height: 44px !important;
                font-size: 22px !important;
            }
            
            .card.mb-4 h5 {
                font-size: 14px !important;
            }
            
            .card.mb-4 p {
                font-size: 11px !important;
            }
            
            .card.mb-4 .btn {
                padding: 8px 14px !important;
                font-size: 12px !important;
            }
            
            .card.mb-4 .btn i {
                font-size: 13px !important;
            }
            
            /* Progress Section */
            .progress-section {
                padding: 16px 12px;
            }
            
            .progress-section h2 {
                font-size: 15px;
            }
            
            .progress-step {
                min-width: 70px;
            }
            
            .progress-icon {
                width: 38px;
                height: 38px;
                font-size: 16px;
            }
            
            .progress-label {
                font-size: 10px;
            }
            
            /* Table */
            .table-header {
                padding: 12px;
            }
            
            .table-title {
                font-size: 14px;
            }
            
            .table-responsive {
                overflow-x: auto !important;
            }
            
            .data-table {
                min-width: 800px !important;
            }
            
            .data-table thead th {
                padding: 10px 12px;
                font-size: 10px;
            }
            
            .data-table tbody td {
                padding: 12px;
                font-size: 12px;
            }
            
            .action-btn {
                padding: 4px 8px;
                font-size: 10px;
            }
            
            .action-btn i {
                font-size: 11px;
            }
            
            /* Badges */
            .badge {
                font-size: 10px !important;
                padding: 4px 8px !important;
            }
        }
        
        @media (max-width: 375px) {
            .main-content {
                padding: 12px 8px;
            }
            
            .page-header h1 {
                font-size: 18px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .progress-section {
                padding: 14px 10px;
            }
            
            .table-header {
                padding: 10px;
            }
            
            .alert.alert-info {
                padding: 12px !important;
            }
            
            .card.mb-4 .card-body {
                padding: 12px !important;
            }
            
            .card.mb-4 .d-flex > div > div:first-child {
                width: 40px !important;
                height: 40px !important;
                font-size: 20px !important;
            }
            
            .card.mb-4 h5 {
                font-size: 13px !important;
            }
            
            .card.mb-4 p {
                font-size: 10px !important;
            }
            
            .card.mb-4 .btn {
                padding: 8px 12px !important;
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Manajemen Recruitment</h1>
        <p>Kelola proses seleksi dan penerimaan pegawai baru</p>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="icon-wrapper">
                <i class="bi bi-person-lines-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Pelamar</div>
                <div class="stat-value"><?= $stats['total_pelamar'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-wrapper">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Dalam Proses</div>
                <div class="stat-value"><?= $stats['dalam_proses'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-wrapper">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Diterima</div>
                <div class="stat-value"><?= $stats['diterima'] ?? 0 ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-wrapper">
                <i class="bi bi-x-circle-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Ditolak</div>
                <div class="stat-value"><?= $stats['ditolak'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <div class="card mb-4" style="background: linear-gradient(135deg, rgb(132, 151, 234) 0%, #1245b5 100%); border-radius: 16px; box-shadow: 0 4px 12px rgba(102,126,234,0.3);">
        <div class="card-body p-4">
            <div class="d-flex align-items-center justify-content-between text-white">
                <div class="d-flex align-items-center gap-3">
                    <div style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-file-earmark-text" style="font-size: 28px;"></i>
                    </div>
                    <div>
                        <h5 class="mb-1" style="font-weight: 600;">Kelola Template Surat</h5>
                        <p class="mb-0" style="font-size: 14px; opacity: 0.9;">Upload dan kelola template surat pernyataan untuk pelamar</p>
                    </div>
                </div>
                <a href="template_surat.php" class="btn btn-light" style="padding: 12px 24px; border-radius: 8px; font-weight: 600;">
                    <i class="bi bi-gear me-2"></i>Kelola Template
                </a>
            </div>
        </div>
    </div>

    <div class="progress-section">
        <div class="progress-container">
            <a href="?tahap=semua" class="progress-step <?= $tahapFilter == 'semua' ? 'active' : '' ?>">
                <div class="progress-icon"><i class="bi bi-list-ul"></i></div>
                <div class="progress-label">Semua</div>
            </a>
            <a href="?tahap=seleksi_admin" class="progress-step <?= $tahapFilter == 'seleksi_admin' ? 'active' : '' ?>">
                <div class="progress-icon"><i class="bi bi-file-earmark-text"></i></div>
                <div class="progress-label">Seleksi Administrasi</div>
            </a>
            <a href="?tahap=pengisian_formulir" class="progress-step <?= $tahapFilter == 'pengisian_formulir' ? 'active' : '' ?>">
                <div class="progress-icon"><i class="bi bi-pencil-square"></i></div>
                <div class="progress-label">Pengisian Formulir</div>
            </a>
            <a href="?tahap=psikotes" class="progress-step <?= $tahapFilter == 'psikotes' ? 'active' : '' ?>">
                <div class="progress-icon"><i class="bi bi-clipboard-data"></i></div>
                <div class="progress-label">Psikotes</div>
            </a>
            <a href="?tahap=interview" class="progress-step <?= $tahapFilter == 'interview' ? 'active' : '' ?>">
                <div class="progress-icon"><i class="bi bi-chat-dots"></i></div>
                <div class="progress-label">Interview</div>
            </a>
            <a href="?tahap=hasil" class="progress-step <?= $tahapFilter == 'hasil' ? 'active' : '' ?>">
                <div class="progress-icon"><i class="bi bi-flag-fill"></i></div>
                <div class="progress-label">Hasil</div>
            </a>
        </div>
    </div>

    <div class="table-section">
        <div class="table-header">
            <div class="table-title">
                <i class="bi bi-list-ul"></i>
                <?= getTahapLabel($tahapFilter) ?>
            </div>
        </div>

        <?php if (count($dataLamaran) > 0): ?>
        <div class="table-responsive">
            <table class="table data-table">
                <thead>
                    <tr>
                        <th>NAMA</th>
                        <th>EMAIL</th>
                        <th>POSISI</th>
                        <th>TAHAP</th>
                        <th>STATUS</th>
                        <th style="min-width: 250px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dataLamaran as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong></td>
                        <td><?= htmlspecialchars($row['email_aktif']) ?></td>
                        <td><?= htmlspecialchars($row['posisi']) ?></td>
                        <td><?= getTahapSekarang($row) ?></td>
                        <td><?= getStatusBadge($row['status_lamaran']) ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="detail_pelamar.php?id=<?= $row['lamaran_id'] ?>" class="action-btn view" title="Lihat Detail">
                                    <i class="bi bi-eye"></i> Lihat
                                </a>
                                
                                <?php 
                                $status = $row['status_lamaran'];
                                
                                if (in_array($status, ['dikirim', 'seleksi_administrasi'])): ?>
                                    <button class="action-btn approve" onclick="loloskanAdmin(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-check-circle"></i> Loloskan
                                    </button>
                                    <button class="action-btn reject" onclick="tolakLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', 'tidak_lolos_administrasi')">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                    
                                <?php 
                                elseif ($status == 'form_lanjutan'): ?>
                                    <button class="action-btn approve" onclick="loloskanForm(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-check-circle"></i> Verifikasi
                                    </button>
                                    <button class="action-btn reject" onclick="tolakLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', 'ditolak_form')">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                    
                                <?php 
                                elseif ($status == 'lolos_form'): ?>
                                    <button class="action-btn schedule" onclick="jadwalkanPsikotes(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-calendar-check"></i> Jadwalkan Psikotes
                                    </button>
                                    <button class="action-btn reject" onclick="tolakLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', 'ditolak_form')">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                    
                                <?php 
                                elseif ($status == 'psikotes'): ?>
                                    <button class="action-btn approve" onclick="loloskanPsikotes(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-check-circle"></i> Lolos
                                    </button>
                                    <button class="action-btn reject" onclick="tolakLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', 'ditolak_psikotes')">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                    
                                <?php 
                                elseif ($status == 'lolos_psikotes'): ?>
                                    <button class="action-btn schedule" onclick="jadwalkanInterview(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-calendar-check"></i> Jadwalkan Interview
                                    </button>
                                    <button class="action-btn reject" onclick="tolakLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', 'ditolak_psikotes')">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                    
                                <?php
                                elseif ($status == 'interview'): ?>
                                    <button class="action-btn approve" onclick="terimaLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>')">
                                        <i class="bi bi-check-circle"></i> Terima
                                    </button>
                                    <button class="action-btn reject" onclick="tolakLamaran(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', 'ditolak_interview')">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                    
                                <?php 
                                elseif ($status == 'diterima'): 
                                    if (!empty($row['surat_resmi_path'])): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-file-earmark-check me-1"></i>
                                            Surat Terkirim
                                        </span>
                                        <a href="<?= htmlspecialchars($row['surat_resmi_path']) ?>" class="action-btn view" target="_blank" title="Lihat Surat">
                                            <i class="bi bi-download"></i> Unduh
                                        </a>
                                    <?php else: ?>
                                        <button class="action-btn schedule" onclick="kirimSuratDiterima(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['posisi'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-file-earmark-arrow-up"></i> Kirim Surat
                                        </button>
                                    <?php endif; ?>
                                    
                                <?php elseif (in_array($status, ['ditolak_interview', 'ditolak_psikotes', 'ditolak_form', 'tidak_lolos_administrasi'])): 
                                    if (!empty($row['surat_resmi_path'])): ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-file-earmark-x me-1"></i>
                                            Surat Terkirim
                                        </span>
                                        <a href="<?= htmlspecialchars($row['surat_resmi_path']) ?>" class="action-btn view" target="_blank" title="Lihat Surat">
                                            <i class="bi bi-download"></i> Unduh
                                        </a>
                                    <?php else: ?>
                                        <button class="action-btn reject" onclick="kirimSuratDitolak(<?= $row['lamaran_id'] ?>, '<?= htmlspecialchars($row['nama_lengkap'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['posisi'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-file-earmark-arrow-up"></i> Kirim Surat
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h5>Tidak Ada Data</h5>
            <p>Belum ada pelamar untuk tahap <?= getTahapLabel($tahapFilter) ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!--JADWAL PSIKOTES -->
<div class="modal fade" id="modalPsikotes" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ec4899, #f472b6); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-check me-2"></i>Jadwalkan Psikotes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Jadwalkan psikotes untuk: <strong id="psikoNama"></strong>
                </div>
                <form id="formPsikotes">
                    <input type="hidden" id="psikoLamaranId" name="lamaran_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Psikotes</label>
                        <input type="date" class="form-control" name="tanggal_psikotes" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Waktu Mulai</label>
                        <input type="time" class="form-control" name="waktu_mulai" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Lokasi</label>
                        <input type="text" class="form-control" name="lokasi" placeholder="Contoh: Ruang Direktur" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan (Opsional)</label>
                        <textarea class="form-control" name="keterangan" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" onclick="submitJadwalPsikotes()">
                    <i class="bi bi-check-circle me-2"></i>Jadwalkan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JADWAL INTERVIEW -->
<div class="modal fade" id="modalInterview" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ec4899, #f472b6); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-calendar-check me-2"></i>Jadwalkan Interview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Jadwalkan interview untuk: <strong id="interviewNama"></strong>
                </div>
                <form id="formInterview">
                    <input type="hidden" id="interviewLamaranId" name="lamaran_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Interview</label>
                        <input type="date" class="form-control" name="tanggal_interview" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Waktu Mulai</label>
                        <input type="time" class="form-control" name="waktu_mulai" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Lokasi</label>
                        <input type="text" class="form-control" name="lokasi" placeholder="Contoh: Ruang Rapat" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pewawancara</label>
                        <input type="text" class="form-control" name="pewawancara" placeholder="Contoh: Dr. Budi Santoso" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan (Opsional)</label>
                        <textarea class="form-control" name="keterangan" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success" onclick="submitJadwalInterview()">
                    <i class="bi bi-check-circle me-2"></i>Jadwalkan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- KIRIM SURAT DITERIMA -->
<div class="modal fade" id="modalSuratDiterima" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981, #34d399); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-check me-2"></i>Kirim Surat Keterangan Diterima
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Konfirmasi Penerimaan</strong><br>
                    Apakah Anda yakin ingin menerima pelamar ini dan mengirimkan surat keterangan diterima?
                </div>
                
                <div class="mb-3">
                    <strong>Pelamar:</strong> <span id="diterimaName"></span>
                </div>
                <div class="mb-3">
                    <strong>Posisi:</strong> <span id="diterimaPosition"></span>
                </div>
                
                <form id="formSuratDiterima" enctype="multipart/form-data">
                    <input type="hidden" id="diterimaLamaranId" name="lamaran_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Unggah Surat Keterangan Diterima
                        </label>
                        <input type="file" class="form-control" name="surat_file" id="suratDiterimaFile" 
                               accept=".pdf,.doc,.docx" required>
                        <small class="text-muted">Format: PDF (Maks. 5MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan untuk Pelamar</label>
                        <textarea class="form-control" name="catatan" rows="3" 
                                  placeholder="Selamat! Anda diterima di posisi..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-success" onclick="submitSuratDiterima()">
                    <i class="bi bi-send me-2"></i>Kirim Surat
                </button>
            </div>
        </div>
    </div>
</div>

<!-- KIRIM SURAT DITOLAK -->
<div class="modal fade" id="modalSuratDitolak" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444, #f87171); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-file-earmark-x me-2"></i>Kirim Surat Keterangan Penolakan
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Konfirmasi Penolakan</strong><br>
                    Apakah Anda yakin ingin menolak pelamar ini dan mengirimkan surat keterangan penolakan?
                </div>
                
                <div class="mb-3">
                    <strong>Pelamar:</strong> <span id="ditolakName"></span>
                </div>
                <div class="mb-3">
                    <strong>Posisi:</strong> <span id="ditolakPosition"></span>
                </div>
                
                <form id="formSuratDitolak" enctype="multipart/form-data">
                    <input type="hidden" id="ditolakLamaranId" name="lamaran_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Unggah Surat Keterangan Penolakan
                        </label>
                        <input type="file" class="form-control" name="surat_file" id="suratDitolakFile" 
                               accept=".pdf,.doc,.docx" required>
                        <small class="text-muted">Format: PDF, DOC, DOCX (Maks. 5MB)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Catatan untuk Pelamar</label>
                        <textarea class="form-control" name="catatan" rows="3" 
                                  placeholder="Terima kasih atas minat Anda..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Batal
                </button>
                <button type="button" class="btn btn-danger" onclick="submitSuratDitolak()">
                    <i class="bi bi-send me-2"></i>Kirim Surat
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<script>
function loloskanAdmin(lamaranId, nama) {
    Swal.fire({
        title: 'Konfirmasi Loloskan',
        html: `Loloskan pelamar <strong>${nama}</strong> ke tahap Pengisian Formulir?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Ya, Loloskan',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatus(lamaranId, 'lolos_administrasi');
        }
    });
}

function loloskanForm(lamaranId, nama) {
    Swal.fire({
        title: 'Konfirmasi Verifikasi',
        html: `Verifikasi formulir pelamar <strong>${nama}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Ya, Verifikasi',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatus(lamaranId, 'lolos_form');
        }
    });
}

function loloskanPsikotes(lamaranId, nama) {
    Swal.fire({
        title: 'Lolos Psikotes',
        html: `Pelamar <strong>${nama}</strong> lolos psikotes?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Ya, Lolos',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatus(lamaranId, 'lolos_psikotes');
        }
    });
}

function terimaLamaran(lamaranId, nama) {
    Swal.fire({
        title: 'Terima Pelamar',
        html: `Terima pelamar <strong>${nama}</strong> sebagai pegawai?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-check-circle me-2"></i>Ya, Terima',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatus(lamaranId, 'diterima');
        }
    });
}

function tolakLamaran(lamaranId, nama, statusTolak) {
    Swal.fire({
        title: 'Tolak Pelamar',
        html: `Tolak pelamar <strong>${nama}</strong>?`,
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Tulis alasan penolakan...',
        inputAttributes: { rows: 4 },
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="bi bi-x-circle me-2"></i>Ya, Tolak',
        cancelButtonText: 'Batal',
        inputValidator: (value) => {
            if (!value) return 'Mohon isi alasan penolakan!'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            updateStatus(lamaranId, statusTolak, result.value);
        }
    });
}

function updateStatus(lamaranId, status, catatan = '') {
    Swal.fire({
        title: 'Memproses...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('update_status_lamaran.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            lamaran_id: lamaranId,
            status: status,
            catatan: catatan
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: data.message || 'Status berhasil diupdate',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + error,
            icon: 'error'
        });
    });
}

function jadwalkanPsikotes(lamaranId, nama) {
    document.getElementById('psikoLamaranId').value = lamaranId;
    document.getElementById('psikoNama').textContent = nama;
    document.getElementById('formPsikotes').reset();
    document.getElementById('psikoLamaranId').value = lamaranId;
    
    const modal = new bootstrap.Modal(document.getElementById('modalPsikotes'));
    modal.show();
}

function submitJadwalPsikotes() {
    const form = document.getElementById('formPsikotes');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const tanggal = formData.get('tanggal_psikotes');
    const waktu = formData.get('waktu_mulai');
    formData.set('tanggal_psikotes', tanggal + ' ' + waktu + ':00');
    formData.delete('waktu_mulai');

    Swal.fire({
        title: 'Memproses...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('jadwalkan_psikotes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalPsikotes')).hide();
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Jadwal psikotes berhasil dibuat',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        bootstrap.Modal.getInstance(document.getElementById('modalPsikotes')).hide();
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + error,
            icon: 'error'
        });
    });
}

// JADWAL INTERVIEW
function jadwalkanInterview(lamaranId, nama) {
    document.getElementById('interviewLamaranId').value = lamaranId;
    document.getElementById('interviewNama').textContent = nama;
    document.getElementById('formInterview').reset();
    document.getElementById('interviewLamaranId').value = lamaranId;
    
    const modal = new bootstrap.Modal(document.getElementById('modalInterview'));
    modal.show();
}

// KIRIM SURAT DITERIMA
function kirimSuratDiterima(lamaranId, nama, posisi) {
    document.getElementById('diterimaLamaranId').value = lamaranId;
    document.getElementById('diterimaName').textContent = nama;
    document.getElementById('diterimaPosition').textContent = posisi;
    document.getElementById('formSuratDiterima').reset();
    document.getElementById('diterimaLamaranId').value = lamaranId;
    
    const modal = new bootstrap.Modal(document.getElementById('modalSuratDiterima'));
    modal.show();
}

function submitSuratDiterima() {
    const form = document.getElementById('formSuratDiterima');
    const fileInput = document.getElementById('suratDiterimaFile');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire({
            title: 'Gagal!',
            text: 'Mohon pilih file surat terlebih dahulu',
            icon: 'error'
        });
        return;
    }

    const file = fileInput.files[0];
    const allowedTypes = ['application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            title: 'Gagal!',
            text: 'File harus berformat PDF',
            icon: 'error'
        });
        return;
    }

    // Validate file size (max 5MB)
    const maxSize = 5 * 1024 * 1024; 
    if (file.size > maxSize) {
        Swal.fire({
            title: 'Gagal!',
            text: 'Ukuran file maksimal 5MB',
            icon: 'error'
        });
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'terima');

    Swal.fire({
        title: 'Mengunggah...',
        html: 'Mohon tunggu, sedang mengunggah surat...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('upload_surat_resmi.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalSuratDiterima')).hide();
        
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Surat penerimaan berhasil diunggah dan dikirim ke pelamar',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan saat mengunggah surat',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        bootstrap.Modal.getInstance(document.getElementById('modalSuratDiterima')).hide();
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + error,
            icon: 'error'
        });
    });
}

// KIRIM SURAT DITOLAK
function kirimSuratDitolak(lamaranId, nama, posisi) {
    document.getElementById('ditolakLamaranId').value = lamaranId;
    document.getElementById('ditolakName').textContent = nama;
    document.getElementById('ditolakPosition').textContent = posisi;
    document.getElementById('formSuratDitolak').reset();
    document.getElementById('ditolakLamaranId').value = lamaranId;
    
    const modal = new bootstrap.Modal(document.getElementById('modalSuratDitolak'));
    modal.show();
}

function submitSuratDitolak() {
    const form = document.getElementById('formSuratDitolak');
    const fileInput = document.getElementById('suratDitolakFile');
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    if (!fileInput.files || fileInput.files.length === 0) {
        Swal.fire({
            title: 'Gagal!',
            text: 'Mohon pilih file surat terlebih dahulu',
            icon: 'error'
        });
        return;
    }

    const file = fileInput.files[0];
    const allowedTypes = ['application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            title: 'Gagal!',
            text: 'File harus berformat PDF',
            icon: 'error'
        });
        return;
    }

    // Validate file size (max 5MB)
    const maxSize = 5 * 1024 * 1024; 
    if (file.size > maxSize) {
        Swal.fire({
            title: 'Gagal!',
            text: 'Ukuran file maksimal 5MB',
            icon: 'error'
        });
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'tolak');

    Swal.fire({
        title: 'Mengunggah...',
        html: 'Mohon tunggu, sedang mengunggah surat...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('upload_surat_resmi.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalSuratDitolak')).hide();
        
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Surat penolakan berhasil diunggah dan dikirim ke pelamar',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan saat mengunggah surat',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        bootstrap.Modal.getInstance(document.getElementById('modalSuratDitolak')).hide();
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + error,
            icon: 'error'
        });
    });
}

function submitJadwalInterview() {
    const form = document.getElementById('formInterview');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const tanggal = formData.get('tanggal_interview');
    const waktu = formData.get('waktu_mulai');
    formData.set('tanggal_interview', tanggal + ' ' + waktu + ':00');
    formData.delete('waktu_mulai');

    Swal.fire({
        title: 'Memproses...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch('jadwalkan_interview.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('modalInterview')).hide();
        if (data.success) {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Jadwal interview berhasil dibuat',
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: data.message || 'Terjadi kesalahan',
                icon: 'error'
            });
        }
    })
    .catch(error => {
        bootstrap.Modal.getInstance(document.getElementById('modalInterview')).hide();
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan: ' + error,
            icon: 'error'
        });
    });
}
</script>

</body>
</html>