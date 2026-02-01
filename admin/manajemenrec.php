<?php
// =====================
// KONEKSI DATABASE  
// =====================
$host = 'localhost';
$dbname = 'sdm_polnest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// =====================
// GET TAHAP FILTER
// =====================
$tahapFilter = isset($_GET['tahap']) ? $_GET['tahap'] : 'semua';

// =====================
// STATISTIK LAMARAN
// =====================
$queryStats = "
    SELECT 
        COUNT(*) AS total_pelamar,
        SUM(CASE WHEN status_lamaran IN ('dalam_proses','verifikasi','psikotes','interview') THEN 1 ELSE 0 END) AS dalam_proses,
        SUM(CASE WHEN status_lamaran = 'diterima' THEN 1 ELSE 0 END) AS diterima,
        SUM(CASE WHEN status_lamaran = 'ditolak' THEN 1 ELSE 0 END) AS ditolak
    FROM lamaran
";
$stats = $pdo->query($queryStats)->fetch(PDO::FETCH_ASSOC);

// =====================
// DATA LAMARAN BERDASARKAN TAHAP
// =====================
$whereClause = "";
switch($tahapFilter) {
    case 'seleksi_admin':
        $whereClause = "WHERE l.status_lamaran = 'verifikasi'";
        break;
    case 'pengisian_formulir':
        $whereClause = "WHERE l.status_lamaran = 'dalam_proses'";
        break;
    case 'psikotes':
        $whereClause = "WHERE l.status_lamaran = 'psikotes'";
        break;
    case 'interview':
        $whereClause = "WHERE l.status_lamaran = 'interview'";
        break;
    case 'hasil':
        $whereClause = "WHERE l.status_lamaran IN ('diterima', 'ditolak')";
        break;
    default:
        $whereClause = "";
}

$queryLamaran = "
    SELECT 
        l.lamaran_id,
        l.status_lamaran,
        l.tanggal_daftar,
        l.tanggal_update,

        p.pelamar_id,
        p.nama_lengkap,
        p.email_aktif,
        p.tempat_lahir,
        p.tanggal_lahir,
        p.alamat_domisili,

        lp.lowongan_id,
        lp.posisi,

        jp.tanggal_psikotes,
        ji.tanggal_interview

    FROM lamaran l
    INNER JOIN pelamar p ON l.pelamar_id = p.pelamar_id
    INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
    LEFT JOIN jadwal_psikotes jp ON l.lamaran_id = jp.lamaran_id
    LEFT JOIN jadwal_interview ji ON l.lamaran_id = ji.lamaran_id
    $whereClause
    ORDER BY l.tanggal_daftar DESC
";

$dataLamaran = $pdo->query($queryLamaran)->fetchAll(PDO::FETCH_ASSOC);

// =====================
// HELPER
// =====================
function getStatusBadge($status) {
    $map = [
        'dikirim' => 'secondary',
        'dalam_proses' => 'warning',
        'verifikasi' => 'info',
        'psikotes' => 'primary',
        'interview' => 'primary',
        'diterima' => 'success',
        'ditolak' => 'danger'
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-'.$color.'">'.ucfirst(str_replace('_',' ',$status)).'</span>';
}

function formatTanggalIndo($tanggal) {
    if (!$tanggal) return '-';
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli',
              'Agustus','September','Oktober','November','Desember'];
    $x = explode('-', $tanggal);
    return $x[2].' '.$bulan[(int)$x[1]].' '.$x[0];
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Recruitment - SDM Polnest</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
        }
        
        .main-content {
            margin-left: 240px;
            padding: 30px 40px;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 30px;
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
            margin: 0;
        }
        
        /* STATS CARDS - KONSISTEN */
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
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        /* SEMUA ICON PINK */
        .stat-card .icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%);
            color: white;
        }
        
        .stat-card .stat-content {
            flex: 1;
        }
        
        .stat-card .stat-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        /* PROGRESS SECTION */
        .progress-section {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin: 40px 0 30px 0;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
            z-index: 2;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .progress-step:hover {
            transform: translateY(-3px);
        }
        
        .progress-step::before {
            content: '';
            position: absolute;
            top: 26px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e5e7eb;
            z-index: 1;
        }
        
        .progress-step:first-child::before {
            display: none;
        }
        
        .progress-step:last-child::before {
            display: none;
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
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }
        
        .progress-step.active .progress-icon {
            background: linear-gradient(135deg, #ec4899 0%, #f472b6 100%);
            border-color: #ec4899;
            color: white;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3);
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
        
        /* TABLE SECTION */
        .table-section {
            background: white;
            border-radius: 16px;
            padding: 0;
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
        
        .filter-status {
            padding: 8px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            color: #374151;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-status:hover {
            border-color: #d1d5db;
            background: #f9fafb;
        }
        
        /* TABLE */
        .data-table {
            width: 100%;
            margin: 0;
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
            letter-spacing: 0.5px;
            border: none;
            white-space: nowrap;
        }
        
        .data-table tbody td {
            padding: 20px 28px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }
        
        .data-table tbody tr {
            transition: all 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: #f9fafb;
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 16px;
        }
        
        .empty-state h5 {
            color: #6b7280;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: #9ca3af;
            font-size: 14px;
        }
        
        /* BADGES */
        .badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .badge.bg-success {
            background: #10b981 !important;
            color: white;
        }
        
        .badge.bg-warning {
            background: #f59e0b !important;
            color: white;
        }
        
        .badge.bg-danger {
            background: #ef4444 !important;
            color: white;
        }
        
        .badge.bg-info {
            background: #3b82f6 !important;
            color: white;
        }
        
        .badge.bg-primary {
            background: #8b5cf6 !important;
            color: white;
        }
        
        .badge.bg-secondary {
            background: #6b7280 !important;
            color: white;
        }
        
        /* ACTION BUTTONS */
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
            margin: 0 4px;
            vertical-align: middle;
        }
        
        /* Kolom Aksi */
        .data-table tbody td:last-child {
            white-space: nowrap;
        }
        
        .action-btn.view {
            background: #dbeafe;
            color: #3b82f6;
        }
        
        .action-btn.view:hover {
            background: #3b82f6;
            color: white;
        }
        
        .action-btn.delete {
            background: #fecaca;
            color: #ef4444;
        }
        
        .action-btn.delete:hover {
            background: #ef4444;
            color: white;
        }
        
        /* PAGINATION */
        .pagination-wrapper {
            padding: 24px 28px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: center;
        }
        
        .pagination {
            margin: 0;
            gap: 8px;
        }
        
        .pagination .page-item .page-link {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 14px;
            color: #374151;
            font-weight: 500;
            margin: 0;
            transition: all 0.2s;
        }
        
        .pagination .page-item.active .page-link {
            background: #3b82f6;
            border-color: #3b82f6;
            color: white;
        }
        
        .pagination .page-item .page-link:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .pagination .page-item.disabled .page-link {
            background: #f9fafb;
            border-color: #e5e7eb;
            color: #9ca3af;
        }
        
        /* MODAL */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 16px 16px 0 0;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .modal-body {
            padding: 28px;
        }
        
        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        /* FORM ELEMENTS */
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-control, .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* BUTTONS */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }
        
        /* DETAIL VIEW */
        .detail-row {
            margin-bottom: 20px;
        }
        
        .detail-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        
        .detail-value {
            font-size: 14px;
            color: #1a1a1a;
            font-weight: 500;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .progress-container {
                flex-direction: column;
                gap: 20px;
            }
            
            .progress-step::before {
                display: none;
            }
        }
    </style>
</head>
<body>

    <?php include 'sidebar/sidebar.php'; ?>

    <div class="main-content">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1>Manajemen Recruitment</h1>
            <p>Alur seleksi dan penerimaan pegawai</p>
        </div>

        <!-- STATS CARDS -->
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

        <!-- PROGRESS SECTION -->
        <div class="progress-section">
            <div class="progress-container">
                <a href="?tahap=semua" class="progress-step <?= $tahapFilter == 'semua' ? 'active' : '' ?>">
                    <div class="progress-icon">
                        <i class="bi bi-list-ul"></i>
                    </div>
                    <div class="progress-label">Semua</div>
                </a>

                <a href="?tahap=seleksi_admin" class="progress-step <?= $tahapFilter == 'seleksi_admin' ? 'active' : '' ?>">
                    <div class="progress-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="progress-label">Seleksi Administrasi</div>
                </a>

                <a href="?tahap=pengisian_formulir" class="progress-step <?= $tahapFilter == 'pengisian_formulir' ? 'active' : '' ?>">
                    <div class="progress-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div class="progress-label">Pengisian Formulir</div>
                </a>

                <a href="?tahap=psikotes" class="progress-step <?= $tahapFilter == 'psikotes' ? 'active' : '' ?>">
                    <div class="progress-icon">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div class="progress-label">Psikotes</div>
                </a>

                <a href="?tahap=interview" class="progress-step <?= $tahapFilter == 'interview' ? 'active' : '' ?>">
                    <div class="progress-icon">
                        <i class="bi bi-chat-dots"></i>
                    </div>
                    <div class="progress-label">Interview</div>
                </a>

                <a href="?tahap=hasil" class="progress-step <?= $tahapFilter == 'hasil' ? 'active' : '' ?>">
                    <div class="progress-icon">
                        <i class="bi bi-flag-fill"></i>
                    </div>
                    <div class="progress-label">Hasil</div>
                </a>
            </div>
        </div>

        <!-- TABLE SECTION -->
        <div class="table-section">
            <div class="table-header">
                <div class="table-title">
                    <i class="bi bi-list-ul"></i>
                    <?= getTahapLabel($tahapFilter) ?>
                </div>
                <select class="filter-status" id="filterStatus">
                    <option value="">Semua Status</option>
                    <option value="dikirim">Dikirim</option>
                    <option value="dalam_proses">Dalam Proses</option>
                    <option value="verifikasi">Verifikasi</option>
                    <option value="psikotes">Psikotes</option>
                    <option value="interview">Interview</option>
                    <option value="diterima">Diterima</option>
                    <option value="ditolak">Ditolak</option>
                </select>
            </div>

            <?php if(count($dataLamaran) > 0): ?>
            <div class="table-responsive">
                <table class="table data-table">
                    <thead>
                        <tr>
                            <th>NAMA PELAMAR</th>
                            <th>EMAIL</th>
                            <th>POSISI DILAMAR</th>
                            <th>TAHAP SAAT INI</th>
                            <th>TANGGAL DAFTAR</th>
                            <th>STATUS</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach($dataLamaran as $row): ?>
                        <tr data-status="<?= $row['status_lamaran'] ?>">
                            <td><strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong></td>
                            <td><?= htmlspecialchars($row['email_aktif']) ?></td>
                            <td><?= htmlspecialchars($row['posisi']) ?></td>
                            <td>
                                <?php
                                if($row['status_lamaran'] == 'diterima') echo 'Diterima';
                                elseif($row['status_lamaran'] == 'ditolak') echo 'Ditolak';
                                elseif($row['tanggal_interview']) echo 'Interview';
                                elseif($row['tanggal_psikotes']) echo 'Psikotes';
                                elseif($row['status_lamaran'] == 'verifikasi') echo 'Seleksi Administrasi';
                                elseif($row['status_lamaran'] == 'dalam_proses') echo 'Pengisian Formulir';
                                else echo 'Dikirim';
                                ?>
                            </td>
                            <td><?= formatTanggalIndo($row['tanggal_daftar']) ?></td>
                            <td><?= getStatusBadge($row['status_lamaran']) ?></td>
                            <td>
                                <button class="action-btn view" onclick="showDetailModal(<?= $row['lamaran_id'] ?>)" title="Lihat Detail">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="action-btn delete" onclick="if(confirm('Hapus data ini?')) location.href='?delete=<?= $row['lamaran_id'] ?>'" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-wrapper">
                <nav>
                    <ul class="pagination">
                        <li class="page-item disabled">
                            <a class="page-link" href="#"><i class="bi bi-chevron-double-left"></i></a>
                        </li>
                        <li class="page-item disabled">
                            <a class="page-link" href="#"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#"><i class="bi bi-chevron-right"></i></a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="#"><i class="bi bi-chevron-double-right"></i></a>
                        </li>
                    </ul>
                </nav>
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

    <!-- MODAL DETAIL -->
    <div class="modal fade" id="modalDetail" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Pelamar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <p class="text-center text-muted">Memuat data...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const dataPelamar = <?= json_encode($dataLamaran) ?>;
        
        document.getElementById('filterStatus').addEventListener('change', function() {
            const status = this.value;
            const rows = document.querySelectorAll('#tableBody tr');
            
            rows.forEach(row => {
                if (status === '' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        function showDetailModal(lamaranId) {
            const modal = new bootstrap.Modal(document.getElementById('modalDetail'));
            const pelamar = dataPelamar.find(p => p.lamaran_id == lamaranId);
            
            if (pelamar) {
                const content = `
                    <div class="row g-3">
                        <div class="col-12 mb-3">
                            <h5 class="mb-3"><i class="bi bi-person-circle"></i> Informasi Pribadi</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Nama Lengkap</div>
                                <div class="detail-value">${pelamar.nama_lengkap}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value">${pelamar.email_aktif}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Alamat Domisili</div>
                                <div class="detail-value">${pelamar.alamat_domisili || '-'}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Tempat Lahir</div>
                                <div class="detail-value">${pelamar.tempat_lahir || '-'}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Tanggal Lahir</div>
                                <div class="detail-value">${pelamar.tanggal_lahir || '-'}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Posisi Dilamar</div>
                                <div class="detail-value">${pelamar.posisi}</div>
                            </div>
                        </div>
                        <div class="col-12 mt-4">
                            <h5 class="mb-3"><i class="bi bi-briefcase"></i> Status Lamaran</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">${pelamar.status_lamaran}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-row">
                                <div class="detail-label">Tanggal Daftar</div>
                                <div class="detail-value">${pelamar.tanggal_daftar}</div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('detailContent').innerHTML = content;
            }
            
            modal.show();
        }
    </script>
</body>
</html>