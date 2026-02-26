<?php
session_start();
require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Pagination
$limit = 10;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter
$filter_template = isset($_GET['template_id']) ? (int)$_GET['template_id'] : '';
$filter_status   = isset($_GET['status'])      ? $_GET['status']           : '';
$search          = isset($_GET['search'])      ? trim($_GET['search'])     : '';

// Dropdown templates
$stmt_templates = $conn->prepare("
    SELECT template_id, nama_template, periode 
    FROM penilaian_template 
    ORDER BY periode DESC
");
$stmt_templates->execute();
$templates = $stmt_templates->fetchAll(PDO::FETCH_ASSOC);

$where_clauses = ["1=1"];
$params = [];

if (!empty($filter_template)) {
    $where_clauses[] = "pk.template_id = ?";
    $params[] = $filter_template;
}
if (!empty($filter_status)) {
    $where_clauses[] = "pk.status_verifikasi = ?";
    $params[] = $filter_status;
}
if (!empty($search)) {
    $where_clauses[] = "(p.nama_lengkap LIKE ? OR sk.jabatan LIKE ? OR sk.unit_kerja LIKE ?)";
    $sp = "%{$search}%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
}

$where_sql = implode(' AND ', $where_clauses);

$stmt_count = $conn->prepare("
    SELECT COUNT(*) as total
    FROM penilaian_kinerja pk
    JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
    LEFT JOIN penilaian_template pt ON pk.template_id = pt.template_id
    WHERE {$where_sql}
");
$stmt_count->execute($params);
$total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages   = ceil($total_records / $limit);

// Data
$stmt_penilaian = $conn->prepare("
    SELECT 
        pk.penilaian_id,
        pk.template_id,
        p.nama_lengkap,
        p.jenis_pegawai,
        sk.jabatan,
        sk.unit_kerja,
        pk.created_at as tanggal_isi,
        pk.status_verifikasi,
        pt.nama_template,
        pt.periode
    FROM penilaian_kinerja pk
    JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
    LEFT JOIN penilaian_template pt ON pk.template_id = pt.template_id
    WHERE {$where_sql}
    ORDER BY pk.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt_penilaian->execute($params);
$penilaian_list = $stmt_penilaian->fetchAll(PDO::FETCH_ASSOC);

// Statistikbelum dilihat & sudah dilihat
$stmt_stat = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_verifikasi = 'belum_dilihat' THEN 1 ELSE 0 END) as belum,
        SUM(CASE WHEN status_verifikasi = 'sudah_dilihat' THEN 1 ELSE 0 END) as sudah
    FROM penilaian_kinerja
");
$stat_global = $stmt_stat->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_viewed'])) {
    $pid = (int)$_POST['penilaian_id'];
    $stmt_update = $conn->prepare("
        UPDATE penilaian_kinerja 
        SET status_verifikasi = 'sudah_dilihat',
            verified_by = ?,
            verified_at = NOW()
        WHERE penilaian_id = ?
    ");
    if ($stmt_update->execute([$_SESSION['user_id'], $pid])) {
        $_SESSION['success_message'] = "Status penilaian berhasil diubah menjadi 'Sudah Dilihat'.";
    } else {
        $_SESSION['error_message'] = "Gagal mengubah status penilaian.";
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

$success_message = $_SESSION['success_message'] ?? null;
$error_message   = $_SESSION['error_message']   ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Penilaian Kinerja - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
     <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f4f8;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .page-header h1 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-header p  { font-size: 14px; opacity: 0.88; }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success { background: #d4edda; border-left: 4px solid #28a745; color: #155724; }
        .alert-danger  { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 16px;
            border-left: 4px solid;
        }

        .summary-card .sc-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .summary-card .sc-value { font-size: 26px; font-weight: 700; }
        .summary-card .sc-label { font-size: 13px; color: #666; }

        .sc-total  { border-color: #1976d2; }
        .sc-total .sc-icon  { background: #e3f2fd; color: #1976d2; }
        .sc-total .sc-value { color: #1976d2; }

        .sc-belum  { border-color: #ffc107; }
        .sc-belum .sc-icon  { background: #fff8e1; color: #f59f00; }
        .sc-belum .sc-value { color: #f59f00; }

        .sc-sudah  { border-color: #28a745; }
        .sc-sudah .sc-icon  { background: #e8f5e9; color: #28a745; }
        .sc-sudah .sc-value { color: #28a745; }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(21, 101, 192, 0.35);
        }

        .btn-info    { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }

        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }

        .btn-sm { padding: 6px 14px; font-size: 13px; }

        /* Action Bar */
        .action-bar {
            background: white;
            padding: 18px 22px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* Filter */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .filter-title {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            align-items: end;
        }

        .filter-group { display: flex; flex-direction: column; }

        .filter-label {
            font-size: 12px;
            font-weight: 500;
            color: #555;
            margin-bottom: 5px;
        }

        .filter-input, .filter-select {
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.2s;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .btn-filter { background: #1976d2; color: white; }
        .btn-reset  { background: #6c757d; color: white; }

        /* Table */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-responsive { overflow-x: auto; }

        table { width: 100%; border-collapse: collapse; }

        thead {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
        }

        thead th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: white;
            font-size: 13px;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }

        tbody tr:hover { background: #f5f9ff; }

        tbody td {
            padding: 14px 16px;
            font-size: 13px;
            color: #333;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-belum { background: #fff3cd; color: #856404; }
        .badge-sudah { background: #d4edda; color: #155724; }

        .jenis-badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .jenis-dosen  { background: #e3f2fd; color: #1565c0; }
        .jenis-staff  { background: #f3e5f5; color: #6a1b9a; }
        .jenis-tendik { background: #fff3e0; color: #e65100; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            padding: 20px;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 7px 13px;
            border: 1px solid #ddd;
            border-radius: 6px;
            color: #1976d2;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s;
        }

        .page-link:hover       { background: #1976d2; color: white; border-color: #1976d2; }
        .page-link.active      { background: #1976d2; color: white; border-color: #1976d2; }
        .page-link.disabled    { opacity: 0.45; cursor: not-allowed; pointer-events: none; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i { font-size: 3.5rem; color: #ccc; display: block; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; color: #666; margin-bottom: 8px; }
        .empty-state p  { color: #999; font-size: 14px; }

        /* Responsive */
        @media (max-width: 900px) {
            .summary-cards { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .summary-cards { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .filter-grid { grid-template-columns: 1fr; }
            table { min-width: 750px; }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h1><i class=""></i> Manajemen Penilaian Kinerja</h1>
            <p>Kelola dan pantau hasil penilaian kinerja seluruh pegawai</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill" style="font-size:18px;"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:18px;"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="summary-cards">
            <div class="summary-card sc-total">
                <div class="sc-icon"><i class="bi bi-clipboard-data"></i></div>
                <div>
                    <div class="sc-value"><?php echo $stat_global['total'] ?? 0; ?></div>
                    <div class="sc-label">Total Penilaian</div>
                </div>
            </div>
            <div class="summary-card sc-belum">
                <div class="sc-icon"><i class="bi bi-clock"></i></div>
                <div>
                    <div class="sc-value"><?php echo $stat_global['belum'] ?? 0; ?></div>
                    <div class="sc-label">Belum Dilihat</div>
                </div>
            </div>
            <div class="summary-card sc-sudah">
                <div class="sc-icon"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="sc-value"><?php echo $stat_global['sudah'] ?? 0; ?></div>
                    <div class="sc-label">Sudah Dilihat</div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <a href="template.php" class="btn btn-primary">
                <i class="bi bi-file-earmark-text"></i> Kelola Template Penilaian
            </a>
            <div style="color: #666; font-size: 13px;">
                <i class="bi bi-list-ul"></i>
                Menampilkan <strong><?php echo count($penilaian_list); ?></strong> dari
                <strong><?php echo $total_records; ?></strong> data
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="bi bi-funnel"></i> Filter Data
            </div>
            <form method="GET">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Template Penilaian</label>
                        <select name="template_id" class="filter-select">
                            <option value="">Semua Template</option>
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?php echo $tpl['template_id']; ?>"
                                    <?php echo ($filter_template == $tpl['template_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tpl['nama_template']); ?>
                                    (<?php echo date('M Y', strtotime($tpl['periode'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status Verifikasi</label>
                        <select name="status" class="filter-select">
                            <option value="">Semua Status</option>
                            <option value="belum_dilihat" <?php echo ($filter_status === 'belum_dilihat') ? 'selected' : ''; ?>>
                                Belum Dilihat
                            </option>
                            <option value="sudah_dilihat" <?php echo ($filter_status === 'sudah_dilihat') ? 'selected' : ''; ?>>
                                Sudah Dilihat
                            </option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Cari Pegawai</label>
                        <input type="text" name="search" class="filter-input"
                               placeholder="Nama, Jabatan, Unit Kerja..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-filter btn-sm">
                                <i class="bi bi-funnel"></i> Filter
                            </button>
                            <a href="penilaianKinerja.php" class="btn btn-reset btn-sm">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container">
            <?php if (count($penilaian_list) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Pegawai</th>
                                <th>Jabatan</th>
                                <th>Unit Kerja</th>
                                <th>Jenis</th>
                                <th>Template</th>
                                <th>Tanggal Isi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $offset + 1;
                            foreach ($penilaian_list as $item):
                                $jenis_class = 'jenis-' . strtolower($item['jenis_pegawai']);
                            ?>
                                <tr>
                                    <td style="color: #999; font-weight: 600;"><?php echo $no++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['nama_lengkap']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['jabatan'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($item['unit_kerja'] ?? '-'); ?></td>
                                    <td>
                                        <span class="jenis-badge <?php echo $jenis_class; ?>">
                                            <?php echo ucfirst($item['jenis_pegawai']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; font-weight: 600; color: #333;">
                                            <?php echo htmlspecialchars($item['nama_template']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #999;">
                                            <?php echo date('F Y', strtotime($item['periode'])); ?>
                                        </div>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?php echo date('d/m/Y H:i', strtotime($item['tanggal_isi'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($item['status_verifikasi'] === 'sudah_dilihat'): ?>
                                            <span class="status-badge badge-sudah">
                                                <i class="bi bi-check-circle-fill"></i> Sudah Dilihat
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge badge-belum">
                                                <i class="bi bi-clock"></i> Belum Dilihat
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 7px; flex-wrap: wrap;">
                                            <a href="detail.php?id=<?php echo $item['penilaian_id']; ?>"
                                               class="btn btn-info btn-sm" title="Lihat Detail">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                            <?php if ($item['status_verifikasi'] === 'belum_dilihat'): ?>
                                                <form method="POST" style="display:inline;"
                                                      onsubmit="return confirm('Tandai sebagai sudah dilihat?');">
                                                    <input type="hidden" name="penilaian_id"
                                                           value="<?php echo $item['penilaian_id']; ?>">
                                                    <button type="submit" name="mark_viewed"
                                                            class="btn btn-success btn-sm"
                                                            title="Tandai Sudah Dilihat">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $qs = $_GET;

                        if ($page > 1):
                            $qs['page'] = $page - 1;
                        ?>
                            <a href="?<?php echo http_build_query($qs); ?>" class="page-link">
                                <i class="bi bi-chevron-left"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled"><i class="bi bi-chevron-left"></i> Prev</span>
                        <?php endif; ?>

                        <?php
                        $start_p = max(1, $page - 2);
                        $end_p   = min($total_pages, $page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++):
                            $qs['page'] = $i;
                            $active = ($i === $page) ? 'active' : '';
                        ?>
                            <a href="?<?php echo http_build_query($qs); ?>"
                               class="page-link <?php echo $active; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php
                        if ($page < $total_pages):
                            $qs['page'] = $page + 1;
                        ?>
                            <a href="?<?php echo http_build_query($qs); ?>" class="page-link">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">Next <i class="bi bi-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>Belum Ada Data Penilaian</h3>
                    <p>Belum ada pegawai yang mengisi penilaian kinerja<?php echo !empty($search) ? " dengan kata kunci \"$search\"" : ''; ?>.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>