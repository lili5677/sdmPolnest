<?php
session_start();
require_once '../../config/database.php';

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Ambil jumlah pending reset password requests
$stmt_reset_count = $conn->query("
    SELECT COUNT(*) as total 
    FROM password_reset_requests 
    WHERE status = 'pending'
");
$reset_pending_count = $stmt_reset_count->fetch()['total'] ?? 0;

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $pegawai_id = intval($_GET['id']);
    
    try {
        
        $conn->beginTransaction();
        
        // Ambil user_id dari pegawai
        $query_check = "SELECT u.user_id, p.nama_lengkap, u.password_changed 
                        FROM pegawai p 
                        JOIN users u ON p.user_id = u.user_id 
                        WHERE p.pegawai_id = ? AND p.is_pegawai_lama = 1";
        $stmt = $conn->prepare($query_check);
        $stmt->execute([$pegawai_id]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_id = $row['user_id'];
            $nama = $row['nama_lengkap'];
            
            // Hapus user 
            $query_delete_user = "DELETE FROM users WHERE user_id = ?";
            $stmt_delete = $conn->prepare($query_delete_user);
            $stmt_delete->execute([$user_id]);
        
            $conn->commit();
            
            $_SESSION['success'] = "Pegawai '$nama' berhasil dihapus beserta semua data terkait";
        } else {
            $_SESSION['error'] = "Data pegawai tidak ditemukan atau bukan pegawai lama";
        }
        
    } catch (PDOException $e) {
        // Rollback jika terjadi error
        $conn->rollBack();
        $_SESSION['error'] = "Terjadi kesalahan saat menghapus data: " . $e->getMessage();
    }
    
    header("Location: manajemen-pegawai.php");
    exit();
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filter dan Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_jenis = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

$where_conditions = ["p.is_pegawai_lama = 1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nama_lengkap LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($filter_jenis)) {
    $where_conditions[] = "p.jenis_pegawai = ?";
    $params[] = $filter_jenis;
}

if (!empty($filter_status)) {
    if ($filter_status === 'sudah_login') {
        $where_conditions[] = "u.password_changed = 1";
    } elseif ($filter_status === 'belum_login') {
        $where_conditions[] = "u.password_changed = 0";
    }
}

$where_sql = implode(" AND ", $where_conditions);

// Get total records (tanpa filter untuk stats)
$query_count_all = "SELECT COUNT(*) as total 
                    FROM pegawai p
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.is_pegawai_lama = 1";
$total_all = $conn->query($query_count_all)->fetch(PDO::FETCH_ASSOC)['total'];

// Get total records (untuk pagination)
$query_count = "SELECT COUNT(*) as total 
                FROM pegawai p
                JOIN users u ON p.user_id = u.user_id
                WHERE $where_sql";

$stmt_count = $conn->prepare($query_count);
$stmt_count->execute($params);
$total_records = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

$total_pages = ceil($total_records / $limit);

// Get pegawai lama data
$query = "SELECT p.pegawai_id, p.nama_lengkap, p.email, p.jenis_pegawai, 
                 u.token, u.password_changed, u.created_at
          FROM pegawai p
          JOIN users u ON p.user_id = u.user_id
          WHERE $where_sql
          ORDER BY p.created_at DESC
          LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->execute($params);

// Get statistics (tanpa filter)
$query_sudah_login = "SELECT COUNT(*) as total FROM pegawai p 
                     JOIN users u ON p.user_id = u.user_id 
                     WHERE p.is_pegawai_lama = 1 AND u.password_changed = 1";
$sudah_login = $conn->query($query_sudah_login)->fetch(PDO::FETCH_ASSOC)['total'];

$query_dosen = "SELECT COUNT(*) as total FROM pegawai 
               WHERE is_pegawai_lama = 1 AND jenis_pegawai = 'dosen'";
$total_dosen = $conn->query($query_dosen)->fetch(PDO::FETCH_ASSOC)['total'];

$page_title = 'Manajemen Pegawai Lama - POLNEST';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pegawai Lama - POLNEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        :root {
            --primary-blue: #1e40af;
            --secondary-blue: #3b82f6;
            --light-blue: #dbeafe;
            --dark-blue: #1e3a8a;
            --accent-blue: #60a5fa;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Poppins', sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .page-header h2 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-header p  { font-size: 14px; opacity: 0.88; }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }

        .btn-primary {
            background: var(--primary-blue);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(30, 64, 175, 0.3);
        }

        .btn-success {
            background: #10b981;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-info {
            background: var(--secondary-blue);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: #ef4444;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .btn-warning {
            background: #f59e0b;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            color: white;
            font-size: 0.9rem;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 0.875rem;
        }

        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px 12px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: white;
        }

        .table thead th:first-child {
            border-left: none;
        }

        .table thead th:last-child {
            border-right: none;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border: 1px solid #e5e7eb;
            font-size: 0.85rem;
            background-color: white;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .table tbody tr:hover td {
            background-color: #f9fafb;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.7rem;
        }

        .badge-dosen {
            background: #8b5cf6;
            color: white;
        }

        .badge-staff {
            background: var(--secondary-blue);
            color: white;
        }

        .badge-tendik {
            background: #f59e0b;
            color: white;
        }

        .badge-success {
            background: #10b981;
            color: white;
        }

        .badge-warning {
            background: #f59e0b;
            color: white;
        }

        .search-filter-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .token-display {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            cursor: pointer;
            user-select: all;
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .token-display:hover {
            background: #e5e7eb;
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .page-link {
            color: var(--primary-blue);
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin: 0;
            padding: 8px 14px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            background: white;
        }

        .page-link:hover {
            background: var(--light-blue);
            border-color: var(--secondary-blue);
            color: var(--primary-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            border-color: var(--primary-blue);
            color: white;
            box-shadow: 0 4px 8px rgba(30, 64, 175, 0.3);
        }

        .page-item.disabled .page-link {
            color: #9ca3af;
            border-color: #e5e7eb;
            background: #f3f4f6;
            cursor: not-allowed;
        }

        .page-item.disabled .page-link:hover {
            transform: none;
            box-shadow: none;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .copy-btn {
            background: transparent;
            border: none;
            color: var(--secondary-blue);
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .copy-btn:hover {
            color: var(--dark-blue);
            transform: scale(1.2);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .modal-title {
            font-size: 1.1rem;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }

        .stats-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .stats-label {
            color: #6b7280;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .token-cell {
            max-width: 180px;
        }

        .btn-outline-secondary {
            font-size: 0.9rem;
            padding: 8px 16px;
        }

        /* Custom Tabs Navigation */
        .custom-tabs {
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 30px;
        }

        .custom-tabs .nav-link {
            color: #6b7280;
            font-weight: 500;
            padding: 12px 24px;
            border: none;
            border-bottom: 3px solid transparent;
            background: none;
            transition: all 0.3s;
            text-decoration: none;
        }

        .custom-tabs .nav-link:hover {
            color: #1f2937;
            border-bottom-color: #d1d5db;
            background: none;
        }

        .custom-tabs .nav-link.active {
            color: #1f2937;
            font-weight: 600;
            border-bottom-color: #2563eb;
            background: none;
        }

        .custom-tabs .nav-link i {
            margin-right: 8px;
        }

        .custom-tabs .nav-link .badge {
            font-size: 0.65rem;
            padding: 3px 7px;
            vertical-align: middle;
        }

        .btn-filter i {
            font-size: 1rem;
        }

        @media (max-width: 991px) {
            .btn-filter .btn-text {
                display: none;
            }
            
            .btn-filter i {
                margin: 0 !important;
            }
        }

        @media (min-width: 992px) {
            .btn-filter .btn-text {
                display: inline;
            }
        }

        .btn-filter {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .swal2-popup {
            border-radius: 15px;
        }

        .swal2-icon.swal2-warning {
            border-color: #f59e0b;
            color: #f59e0b;
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class=""></i>Manajemen Pegawai Lama</h2>
            <p>Kelola data pegawai yang sudah bekerja sebelum sistem diterapkan</p>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav custom-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" href="manajemen-pegawai.php">
                    <i class="fas fa-users me-2"></i>Daftar Pegawai
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="reset-password-requests.php">
                    <i class="fas fa-key me-2"></i>Reset Password
                    <?php if ($reset_pending_count > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $reset_pending_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_all; ?></div>
                    <div class="stats-label">Total Pegawai Lama</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #10b981;">
                    <div class="stats-number" style="color: #10b981;"><?php echo $sudah_login; ?></div>
                    <div class="stats-label">Sudah Login</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #f59e0b;">
                    <div class="stats-number" style="color: #f59e0b;"><?php echo $total_all - $sudah_login; ?></div>
                    <div class="stats-label">Belum Login</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-left-color: #8b5cf6;">
                    <div class="stats-number" style="color: #8b5cf6;"><?php echo $total_dosen; ?></div>
                    <div class="stats-label">Dosen</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <a href="download-template.php" class="btn btn-info me-2 mb-2">
                            <i class="fas fa-download me-1"></i> Download Template CSV
                        </a>
                        <button type="button" class="btn btn-primary me-2 mb-2" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-file-upload me-1"></i> Import CSV
                        </button>
                        <a href="export-pegawai.php" class="btn btn-success me-2 mb-2">
                            <i class="fas fa-file-download me-1"></i> Export Data ke CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-container">
            <form method="GET" action="">
                <div class="row align-items-end">
                    <div class="col-lg-4 col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fas fa-search me-1"></i> Cari Pegawai
                        </label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Cari nama atau email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fas fa-filter me-1"></i> Jenis Pegawai
                        </label>
                        <select class="form-select" name="jenis">
                            <option value="">Semua Jenis</option>
                            <option value="dosen" <?php echo $filter_jenis === 'dosen' ? 'selected' : ''; ?>>Dosen</option>
                            <option value="staff" <?php echo $filter_jenis === 'staff' ? 'selected' : ''; ?>>Staff</option>
                            <option value="tendik" <?php echo $filter_jenis === 'tendik' ? 'selected' : ''; ?>>Tendik</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label class="form-label">
                            <i class="fas fa-user-check me-1"></i> Status Login
                        </label>
                        <select class="form-select" name="status">
                            <option value="">Semua Status</option>
                            <option value="sudah_login" <?php echo $filter_status === 'sudah_login' ? 'selected' : ''; ?>>Sudah Login</option>
                            <option value="belum_login" <?php echo $filter_status === 'belum_login' ? 'selected' : ''; ?>>Belum Login</option>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 mb-3">
                        <button type="submit" class="btn btn-primary w-100 btn-filter">
                            <i class="fas fa-search"></i> 
                            <span class="btn-text">Cari</span>
                        </button>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6 mb-3">
                        <a href="manajemen-pegawai.php" class="btn btn-outline-secondary w-100 btn-filter">
                            <i class="fas fa-redo"></i> 
                            <span class="btn-text">Reset</span>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Filter Info -->
        <?php if (!empty($search) || !empty($filter_jenis) || !empty($filter_status)): ?>
        <div class="alert alert-info">
            <i class="fas fa-filter me-2"></i>
            <strong>Filter Aktif:</strong>
            <?php if (!empty($search)): ?>
                Pencarian: "<?php echo htmlspecialchars($search); ?>"
            <?php endif; ?>
            <?php if (!empty($filter_jenis)): ?>
                | Jenis: <?php echo strtoupper($filter_jenis); ?>
            <?php endif; ?>
            <?php if (!empty($filter_status)): ?>
                | Status: <?php echo $filter_status === 'sudah_login' ? 'Sudah Login' : 'Belum Login'; ?>
            <?php endif; ?>
            | Menampilkan <?php echo $total_records; ?> dari <?php echo $total_all; ?> data
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Jenis Pegawai</th>
                        <th>Token Login</th>
                        <th>Status Login</th>
                        <th>Tanggal Ditambahkan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($stmt->rowCount() > 0): ?>
                        <?php 
                        $no = $offset + 1;
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                        ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['nama_lengkap']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td>
                                <?php
                                $badge_class = 'badge-staff';
                                if ($row['jenis_pegawai'] === 'dosen') {
                                    $badge_class = 'badge-dosen';
                                } elseif ($row['jenis_pegawai'] === 'tendik') {
                                    $badge_class = 'badge-tendik';
                                }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo strtoupper($row['jenis_pegawai']); ?>
                                </span>
                            </td>
                            <td class="token-cell">
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($row['token'])): ?>
                                        <span class="token-display" 
                                              id="token-<?php echo $row['pegawai_id']; ?>" 
                                              onclick="copyToken('<?php echo $row['pegawai_id']; ?>', '<?php echo htmlspecialchars($row['token']); ?>')"
                                              title="<?php echo htmlspecialchars($row['token']); ?> - Klik untuk copy">
                                            <?php echo htmlspecialchars($row['token']); ?>
                                        </span>
                                        <button class="copy-btn ms-2" 
                                                onclick="copyToken('<?php echo $row['pegawai_id']; ?>', '<?php echo htmlspecialchars($row['token']); ?>')"
                                                title="Copy Token">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['password_changed'] == 1): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle me-1"></i> Sudah Login
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock me-1"></i> Belum Login
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-danger btn-sm" 
                                            onclick="confirmDelete(<?php echo $row['pegawai_id']; ?>, '<?php echo htmlspecialchars($row['nama_lengkap'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php if (!empty($search) || !empty($filter_jenis) || !empty($filter_status)): ?>
                                        Tidak ada data yang sesuai dengan filter
                                    <?php else: ?>
                                        Tidak ada data pegawai lama
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap">
            <div class="mb-2 mb-md-0">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan halaman <?php echo $page; ?> dari <?php echo $total_pages; ?> 
                    (Total: <?php echo $total_records; ?> data)
                </small>
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>" title="Halaman Sebelumnya">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php 
        
                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&jenis=<?php echo urlencode($filter_jenis); ?>&status=<?php echo urlencode($filter_status); ?>" title="Halaman Berikutnya">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="fas fa-file-upload me-2"></i>Import Data Pegawai Lama
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="import-pegawai.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Pilih File CSV</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Contoh format: nama_lengkap, email, jenis_pegawai, jabatan, jenis_kepegawaian,
                                status_aktif, unit_kerja, tanggal_mulai_kerja, masa_kontrak_mulai,
                                masa_kontrak_selesai

                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tips:</strong> Download template CSV terlebih dahulu untuk memastikan format yang benar.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i> Import Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function copyToken(pegawaiId, token) {
            const textarea = document.createElement('textarea');
            textarea.value = token;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            const btn = event.target.closest('.copy-btn');
            if (btn) {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                btn.style.color = '#10b981';
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.color = '';
                }, 2000);
            }
            
            showToast('Token berhasil disalin!');
        }

        function confirmDelete(pegawaiId, nama) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                html: `
                    <p>Anda akan menghapus pegawai:</p>
                    <strong style="font-size: 1.2em; color: #1e40af;">${nama}</strong>
                    <div style="margin-top: 15px; padding: 15px; background-color: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; text-align: left;">
                        <strong style="color: #f59e0b;"><i class="fas fa-exclamation-triangle"></i> PERINGATAN:</strong>
                        <ul style="margin: 10px 0 0 0; padding-left: 20px; font-size: 0.9em;">
                            <li>Data pegawai akan dihapus permanen</li>
                            <li>Data user terkait akan dihapus</li>
                            <li>Data status kepegawaian akan dihapus</li>
                            <li>Semua data terkait akan hilang!</li>
                        </ul>
                    </div>
                    <p style="margin-top: 15px; color: #ef4444; font-weight: 600;">Proses ini TIDAK DAPAT dibatalkan!</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash me-2"></i>Ya, Hapus!',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Batal',
                customClass: {
                    popup: 'animated-popup',
                    confirmButton: 'btn-delete-confirm',
                    cancelButton: 'btn-cancel'
                },
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Menghapus data...',
                        html: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Redirect to delete
                    window.location.href = `manajemen-pegawai.php?action=delete&id=${pegawaiId}`;
                }
            });
        }

        function showToast(message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            Toast.fire({
                icon: 'success',
                title: message
            });
        }
    </script>
    <style>
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translate3d(0, -100%, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes fadeOutUp {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
                transform: translate3d(0, -100%, 0);
            }
        }

        .animate__animated {
            animation-duration: 0.5s;
            animation-fill-mode: both;
        }

        .animate__fadeInDown {
            animation-name: fadeInDown;
        }

        .animate__fadeOutUp {
            animation-name: fadeOutUp;
        }

        .btn-delete-confirm {
            font-weight: 600;
            padding: 10px 24px;
        }

        .btn-cancel {
            font-weight: 600;
            padding: 10px 24px;
        }
    </style>
</body>
</html>