<?php
require_once '../../config/database.php';

if (!isset($pdo) && isset($conn)) {
    $pdo = $conn; 
}

if (!isset($pdo) && isset($db)) {
    $pdo = $db; 
}

if (!isset($pdo)) {
    try {
        $host = 'localhost';
        $dbname = 'sdm_polnest'; 
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

// get statistik
$sertifikasi_list = [];
$stats = ['total_sertifikat' => 0, 'total_dosen' => 0];
$error_message = '';

try {
    // Hitung total sertifikat dan dosen
    $query_stats = "SELECT 
                        COUNT(DISTINCT sd.sertifikasi_id) as total_sertifikat,
                        COUNT(DISTINCT sd.pegawai_id) as total_dosen
                    FROM sertifikasi_dosen sd";
    $stmt_stats = $pdo->prepare($query_stats);
    $stmt_stats->execute();
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "Error mengambil statistik: " . $e->getMessage();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter status
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// data sertifikasi 
try {
    $query = "SELECT 
                sd.sertifikasi_id,
                sd.nama_sertifikasi,
                sd.jenis_sertifikasi,
                sd.tahun_sertifikasi,
                sd.kategori,
                sd.tahun_masa_berlaku,
                sd.status_validasi,
                sd.dokumen_sertifikat_path,
                p.nama_lengkap,
                p.prodi,
                p.nidn
              FROM sertifikasi_dosen sd
              JOIN pegawai p ON sd.pegawai_id = p.pegawai_id";
    
    
    if (!empty($status_filter)) {
        $query .= " WHERE sd.status_validasi = :status";
    }
    
    $query .= " ORDER BY sd.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }
    
    $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $sertifikasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error_message = "Error mengambil data: " . $e->getMessage();
}

// Total pages
$total_records = $stats['total_sertifikat'];
$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikasi Dosen - Admin</title>
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
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .header h2 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .header p  { font-size: 14px; opacity: 0.88; }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card .stat-info h3 {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-card .stat-info p {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card .stat-icon i {
            font-size: 24px;
            color: #1e40af;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 28px;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -28px;
            padding: 0 28px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .table-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        .filter-select {
            padding: 10px 40px 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #475569;
            background: white;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            transition: all 0.2s ease;
            min-width: 180px;
        }

        .filter-select:hover {
            border-color: #1e40af;
        }

        .filter-select:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background-color: #f8fafc;
        }

        th {
            padding: 15px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 15px;
            border-top: 1px solid #f0f0f0;
            font-size: 14px;
            color: #1e293b;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f0f9ff;
            transform: translateX(4px);
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .view-btn {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .view-btn:hover {
            background-color: #bfdbfe;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(30, 64, 175, 0.2);
        }

        .download-btn {
            background-color: #f0f9ff;
            color: #0284c7;
        }

        .download-btn:hover {
            background-color: #e0f2fe;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(2, 132, 199, 0.2);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination button {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            color: #475569;
            transition: all 0.3s ease;
        }

        .pagination button:hover {
            background-color: #dbeafe;
            border-color: #1e40af;
            color: #1e40af;
        }

        .pagination button.active {
            background-color: #1e40af;
            border-color: #1e40af;
            color: white;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px;
                padding: 24px;
            }

            .header h1 {
                font-size: 24px;
            }

            .header p {
                font-size: 14px;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-card .stat-info h3 {
                font-size: 12px;
            }

            .stat-card .stat-info p {
                font-size: 24px;
            }

            .stat-card .stat-icon {
                width: 40px;
                height: 40px;
            }

            .stat-card .stat-icon i {
                font-size: 20px;
            }

            .table-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .filter-select {
                width: 100%;
                min-width: auto;
            }

            .table-wrapper {
                margin: 0 -28px;
                padding: 0 28px;
            }

            /* Sidebar expanded state */
            body.sidebar-expanded .main-content {
                margin-left: 280px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .header h1 {
                font-size: 22px;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .table-container {
                padding: 20px;
                border-radius: 10px;
            }

            .table-wrapper {
                margin: 0 -20px;
                padding: 0 20px;
                border-radius: 10px;
            }

            table {
                font-size: 13px;
                min-width: 900px;
            }

            th {
                padding: 12px 10px;
                font-size: 11px;
                white-space: nowrap;
            }

            td {
                padding: 14px 10px;
                font-size: 13px;
            }

            .action-btn {
                width: 30px;
                height: 30px;
            }

            .action-btn i {
                font-size: 14px;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 6px;
            }

            .pagination button {
                padding: 8px 10px;
                font-size: 13px;
                min-width: 36px;
            }

            body.sidebar-expanded .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }

            .header {
                margin-bottom: 20px;
            }

            .header h1 {
                font-size: 20px;
            }

            .header p {
                font-size: 13px;
            }

            .stats-container {
                gap: 12px;
                margin-bottom: 24px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card .stat-info h3 {
                font-size: 11px;
            }

            .stat-card .stat-info p {
                font-size: 26px;
            }

            .stat-card .stat-icon {
                width: 36px;
                height: 36px;
            }

            .stat-card .stat-icon i {
                font-size: 18px;
            }

            .table-container {
                padding: 16px;
                border-radius: 10px;
            }

            .table-header {
                margin-bottom: 16px;
            }

            .table-header h2 {
                font-size: 16px;
            }

            .filter-select {
                font-size: 13px;
                padding: 10px 32px 10px 12px;
            }

            .table-wrapper {
                margin: 0 -16px;
                padding: 0 16px;
            }

            table {
                font-size: 12px;
                min-width: 900px;
            }

            th {
                font-size: 10px;
                padding: 12px 8px;
            }

            td {
                padding: 12px 8px;
                font-size: 12px;
            }

            .actions {
                gap: 6px;
            }

            .action-btn {
                width: 28px;
                height: 28px;
            }

            .action-btn i {
                font-size: 13px;
            }

            .pagination {
                gap: 4px;
                margin-top: 20px;
            }

            .pagination button {
                padding: 6px 8px;
                font-size: 12px;
                min-width: 32px;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Sertifikasi Dosen</h1>
            <p>Data dan Informasi untuk Sertifikasi Dosen</p>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Sertifikat</h3>
                    <p><?php echo $stats['total_sertifikat']; ?></p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-certificate"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Dosen</h3>
                    <p><?php echo $stats['total_dosen']; ?></p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h2>Daftar Sertifikasi</h2>
                <select class="filter-select" id="statusFilter" onchange="filterStatus(this.value)">
                    <option value="">Semua Status</option>
                    <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="tervalidasi" <?php echo ($status_filter == 'tervalidasi') ? 'selected' : ''; ?>>Tervalidasi</option>
                    <option value="ditolak" <?php echo ($status_filter == 'ditolak') ? 'selected' : ''; ?>>Ditolak</option>
                </select>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Pelamar</th>
                             <th>Nama Sertifikasi</th>
                            <th>Prodi</th>
                            <th>Jenis</th>
                            <th>Tahun</th>
                            <th>Kategori</th>
                            <th>Masa Berlaku</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                   <tbody>
                    <?php if(!empty($error_message)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                <div style="color: #e74c3c; background: #fee; padding: 20px; border-radius: 8px; margin: 0 auto; max-width: 600px;">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px;"></i>
                                    <p style="margin: 10px 0; font-weight: 600;">Kesalahan Database</p>
                                    <p style="font-size: 13px;"><?php echo $error_message; ?></p>
                                </div>
                            </td>
                        </tr>
                    <?php elseif(count($sertifikasi_list) > 0): ?>
                        
                        <?php foreach($sertifikasi_list as $sertifikasi): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sertifikasi['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($sertifikasi['nama_sertifikasi']); ?></td>
                            <td><?php echo htmlspecialchars($sertifikasi['prodi'] ?? '-'); ?></td>
                            <td><?php 
                                $jenis_display = [
                                    'sertifikasi_pendidik' => 'Pendidik',
                                    'profesi' => 'Profesi',
                                    'kompetensi' => 'Kompetensi'
                                ];
                                echo $jenis_display[$sertifikasi['jenis_sertifikasi']] ?? $sertifikasi['jenis_sertifikasi'];
                            ?></td>
                            <td><?php echo htmlspecialchars($sertifikasi['tahun_sertifikasi']); ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($sertifikasi['kategori'])); ?></td>
                            <td><?php 
                                if($sertifikasi['tahun_masa_berlaku']) {
                                    echo htmlspecialchars($sertifikasi['tahun_masa_berlaku']);
                                } else {
                                    echo '-';
                                }
                            ?></td>
                            <td>
                                <?php
                                $status = strtolower($sertifikasi['status_validasi']);
                                $badge_style = '';
                                $status_text = '';
                                
                                if ($status === 'pending') {
                                    $badge_style = 'background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;';
                                    $status_text = 'Pending';
                                } elseif ($status === 'tervalidasi') {
                                    $badge_style = 'background: #d1fae5; color: #065f46; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;';
                                    $status_text = 'Tervalidasi';
                                } elseif ($status === 'ditolak') {
                                    $badge_style = 'background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;';
                                    $status_text = 'Ditolak';
                                }
                                ?>
                                <span style="<?= $badge_style ?>"><?= $status_text ?></span>
                            </td>
                            <td>
                                <div class="actions">
                                    <button class="action-btn view-btn" onclick="viewDetail(<?php echo $sertifikasi['sertifikasi_id']; ?>)" title="Lihat Detail">
                                        <i class="far fa-eye"></i>
                                    </button>
                                    <?php if($sertifikasi['dokumen_sertifikat_path']): ?>
                                    <button class="action-btn download-btn" onclick="downloadFile('<?php echo htmlspecialchars($sertifikasi['dokumen_sertifikat_path']); ?>')" title="Download Sertifikat">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                    
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: #999;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                                <p>Tidak ada data sertifikasi</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>

            <div class="pagination">
                <button onclick="changePage('first')" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-double-left"></i>
                </button>
                <button onclick="changePage('prev')" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-left"></i>
                </button>
                
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <button onclick="changePage(<?php echo $i; ?>)" class="<?php echo ($page == $i) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
                
                <button onclick="changePage('next')" <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-right"></i>
                </button>
                <button onclick="changePage('last')" <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-double-right"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        function changePage(action) {
            const currentPage = <?php echo $page; ?>;
            const totalPages = <?php echo $total_pages; ?>;
            const currentStatus = '<?php echo $status_filter; ?>';
            let newPage = currentPage;

            switch(action) {
                case 'first':
                    newPage = 1;
                    break;
                case 'prev':
                    newPage = Math.max(1, currentPage - 1);
                    break;
                case 'next':
                    newPage = Math.min(totalPages, currentPage + 1);
                    break;
                case 'last':
                    newPage = totalPages;
                    break;
                default:
                    newPage = action;
            }

            if(newPage !== currentPage) {
                let url = '?page=' + newPage;
                if(currentStatus) {
                    url += '&status=' + currentStatus;
                }
                window.location.href = url;
            }
        }

        function viewDetail(id) {
            // Redirect ke halaman detail sertifikasi
            window.location.href = 'detail-sertifikasi.php?id=' + id;
        }

        function downloadFile(filepath) {
            // Redirect ke halaman download sertifikat
            window.open('../../' + filepath, '_blank');
        }

        function filterStatus(status) {
            if(status) {
                window.location.href = '?status=' + status;
            } else {
                window.location.href = window.location.pathname;
            }
        }
    </script>
</body>
</html>