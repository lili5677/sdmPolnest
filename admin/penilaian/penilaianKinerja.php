<?php
session_start();
require_once '../../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : ''; // belum_dilihat, sudah_dilihat

// Build WHERE clause
$where = ["pk.penilaian_id IS NOT NULL"]; 
$params = [];

if ($search !== '') {
    $where[] = "(p.nama_lengkap LIKE :search OR p.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter_jenis !== '') {
    $where[] = "p.jenis_pegawai = :jenis";
    $params[':jenis'] = $filter_jenis;
}

if ($filter_status !== '') {
    if ($filter_status === 'belum_dilihat') {
        $where[] = "IFNULL(pk.status_verifikasi, 'belum_dilihat') = 'belum_dilihat'";
    } else {
        $where[] = "pk.status_verifikasi = :status";
        $params[':status'] = $filter_status;
    }
}

$where_sql = implode(" AND ", $where);

// Count total
$count_sql = "SELECT COUNT(DISTINCT pk.penilaian_id) as total 
              FROM penilaian_kinerja pk
              INNER JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
              INNER JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
              WHERE sk.status_aktif = 'aktif' AND $where_sql";
              
$count_stmt = $conn->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get data penilaian (setiap row = 1 penilaian)
$sql = "SELECT 
            pk.penilaian_id,
            pk.periode,
            pk.created_at,
            IFNULL(pk.status_verifikasi, 'belum_dilihat') as status_verifikasi,
            pk.verified_at,
            p.pegawai_id,
            p.nama_lengkap,
            p.email,
            p.jenis_pegawai,
            sk.jabatan,
            sk.unit_kerja,
            pt.nama_template
        FROM penilaian_kinerja pk
        INNER JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
        INNER JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
        INNER JOIN penilaian_template pt ON pk.template_id = pt.template_id
        WHERE sk.status_aktif = 'aktif' AND $where_sql
        ORDER BY 
            CASE WHEN IFNULL(pk.status_verifikasi, 'belum_dilihat') = 'belum_dilihat' THEN 0 ELSE 1 END,
            pk.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$penilaian_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary stats
$stats_sql = "SELECT 
                COUNT(*) as total_penilaian,
                SUM(CASE WHEN IFNULL(status_verifikasi, 'belum_dilihat') = 'belum_dilihat' THEN 1 ELSE 0 END) as belum_dilihat,
                SUM(CASE WHEN status_verifikasi = 'sudah_dilihat' THEN 1 ELSE 0 END) as sudah_dilihat
              FROM penilaian_kinerja pk
              INNER JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
              INNER JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
              WHERE sk.status_aktif = 'aktif'";
$stats = $conn->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Kinerja - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            background: #f8f9fa; 
            min-height: 100vh; 
        }
        .main-content h2 {
            font-weight: bold;
            font-size: 28px;
        }
        .card { 
            border: none; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card.total { border-left-color: #007bff; }
        .stats-card.pending { border-left-color: #ffc107; }
        .stats-card.verified { border-left-color: #28a745; }
        
        .table-hover tbody tr {
            transition: all 0.3s;
        }
        .table-hover tbody tr:hover { 
            background-color: #f1f3f5;
            transform: scale(1.01);
        }
        .table-hover tbody tr.verified-row {
            background-color: #f0f9f4;
        }
        .table-hover tbody tr.pending-row {
            background-color: #fffbf0;
        }
        
        .badge-jenis {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-badge {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 20px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        .btn-verify {
            transition: all 0.3s;
        }
        .btn-verify:hover {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Penilaian Kinerja Pegawai</h2>
                    <p class="text-muted">Kelola dan verifikasi penilaian kinerja pegawai</p>
                </div>
                <div>
                    <a href="template.php" class="btn btn-outline-primary">
                        <i class="bi bi-file-earmark-text"></i> Kelola Template
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card total">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Total Penilaian</h6>
                                    <h2 class="mb-0"><?= $stats['total_penilaian'] ?></h2>
                                </div>
                                <div class="text-primary">
                                    <i class="bi bi-clipboard-data" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card pending">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Belum Dilihat</h6>
                                    <h2 class="mb-0 text-warning"><?= $stats['belum_dilihat'] ?></h2>
                                </div>
                                <div class="text-warning">
                                    <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card verified">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Sudah Dilihat</h6>
                                    <h2 class="mb-0 text-success"><?= $stats['sudah_dilihat'] ?></h2>
                                </div>
                                <div class="text-success">
                                    <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Cari nama atau email..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="jenis" class="form-select">
                                <option value="">Semua Jenis</option>
                                <option value="dosen" <?= $filter_jenis === 'dosen' ? 'selected' : '' ?>>Dosen</option>
                                <option value="staff" <?= $filter_jenis === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="tendik" <?= $filter_jenis === 'tendik' ? 'selected' : '' ?>>Tendik</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="belum_dilihat" <?= $filter_status === 'belum_dilihat' ? 'selected' : '' ?>>Belum Dilihat</option>
                                <option value="sudah_dilihat" <?= $filter_status === 'sudah_dilihat' ? 'selected' : '' ?>>Sudah Dilihat</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <a href="penilaianKinerja.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (count($penilaian_list) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 40px;">No</th>
                                        <th>Nama Lengkap</th>
                                        <th>Jabatan/Posisi</th>
                                        <th>Unit Kerja</th>
                                        <th style="width: 90px;">Jenis</th>
                                        <th style="width: 140px;">Tanggal Isi</th>
                                        <th style="width: 140px;">Status</th>
                                        <th style="width: 150px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = $offset + 1;
                                    foreach ($penilaian_list as $row): 
                                        $row_class = $row['status_verifikasi'] === 'sudah_dilihat' ? 'verified-row' : 'pending-row';
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_lengkap']) ?></strong><br>
                                            <small class="text-muted">
                                                <i class="bi bi-envelope"></i>
                                                <?= htmlspecialchars($row['email']) ?>
                                            </small>
                                        </td>
                                        <td><?= htmlspecialchars($row['jabatan'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['unit_kerja'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-info badge-jenis">
                                                <?= strtoupper($row['jenis_pegawai']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="bi bi-calendar-event"></i>
                                                <?= date('d M Y', strtotime($row['created_at'])) ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($row['created_at'])) ?> WIB
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($row['status_verifikasi'] === 'sudah_dilihat'): ?>
                                                <span class="badge bg-success status-badge">
                                                    <i class="bi bi-check-circle-fill"></i> Sudah Dilihat
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark status-badge">
                                                    <i class="bi bi-clock-fill"></i> Belum Dilihat
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="detail.php?id=<?= $row['penilaian_id'] ?>" 
                                                   class="btn btn-sm btn-info text-white"
                                                   title="Lihat Detail">
                                                    <i class="bi bi-eye"></i> Detail
                                                </a>
                                                
                                                <?php if ($row['status_verifikasi'] === 'belum_dilihat'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-success btn-verify" 
                                                            onclick="verifyPenilaian(<?= $row['penilaian_id'] ?>, 'sudah_dilihat')"
                                                            title="Tandai Sudah Dilihat">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-secondary btn-verify" 
                                                            onclick="verifyPenilaian(<?= $row['penilaian_id'] ?>, 'belum_dilihat')"
                                                            title="Tandai Belum Dilihat">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
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
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $filter_jenis ? '&jenis='.$filter_jenis : '' ?><?= $filter_status ? '&status='.$filter_status : '' ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>

                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $filter_jenis ? '&jenis='.$filter_jenis : '' ?><?= $filter_status ? '&status='.$filter_status : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?><?= $filter_jenis ? '&jenis='.$filter_jenis : '' ?><?= $filter_status ? '&status='.$filter_status : '' ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>

                        <div class="text-center text-muted mt-3">
                            <small>
                                Menampilkan <?= $offset + 1 ?> - <?= min($offset + $per_page, $total_records) ?> 
                                dari <?= $total_records ?> penilaian
                            </small>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-clipboard-data"></i>
                            <h5>Belum Ada Penilaian Kinerja</h5>
                            <p>Belum ada pegawai yang mengisi penilaian. Pastikan template sudah dibuat dan pegawai sudah mengisi penilaian mereka.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Form for verification (hidden) -->
    <form id="verifyForm" method="POST" action="verifikasi.php" style="display: none;">
        <input type="hidden" name="action" value="verify">
        <input type="hidden" name="penilaian_id" id="verify_penilaian_id">
        <input type="hidden" name="status" id="verify_status">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function verifyPenilaian(penilaianId, status) {
            const confirmMsg = status === 'sudah_dilihat' 
                ? 'Tandai penilaian ini sudah dilihat?' 
                : 'Tandai penilaian ini belum dilihat?';
            
            if (confirm(confirmMsg)) {
                document.getElementById('verify_penilaian_id').value = penilaianId;
                document.getElementById('verify_status').value = status;
                document.getElementById('verifyForm').submit();
            }
        }
    </script>
</body>
</html>