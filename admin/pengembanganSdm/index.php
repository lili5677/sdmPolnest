<?php
// AUTHORIZATION 
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// Statistik Pengajuan
$query_stats = "SELECT 
    COUNT(*) as total_pengajuan,
    SUM(CASE WHEN status_pengajuan IN ('diajukan', 'ditinjau') THEN 1 ELSE 0 END) as menunggu_review,
    SUM(CASE WHEN status_pengajuan = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
    SUM(CASE WHEN status_pengajuan = 'ditolak' THEN 1 ELSE 0 END) as ditolak
FROM pengajuan_studi";

$stmt_stats = $conn->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengembangan SDM - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 15px;
            color: #64748b;
            font-weight: 400;
        }

        /* ===== STATS CARDS ===== */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }

        .stat-icon.blue {
            background: #e0f2fe;
            color: #0284c7;
        }

        .stat-icon.orange {
            background: #ffedd5;
            color: #ea580c;
        }

        .stat-icon.green {
            background: #d1fae5;
            color: #059669;
        }

        .stat-icon.red {
            background: #fee2e2;
            color: #dc2626;
        }

        /* ===== TABS ===== */
        .custom-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .custom-tabs .nav-link {
            color: #64748b;
            font-weight: 600;
            padding: 12px 24px;
            border: none;
            border-bottom: 3px solid transparent;
            background: none;
            transition: all 0.3s;
            font-size: 14px;
        }

        .custom-tabs .nav-link:hover {
            color: #1e293b;
            border-bottom-color: #cbd5e1;
        }

        .custom-tabs .nav-link.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: none;
        }

        /* ===== CONTENT CARD ===== */
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
        }

        /* ===== TAB CONTENT ===== */
        .tab-content {
            min-height: 400px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Pengembangan SDM</h1>
            <p class="page-subtitle">Kelola pengajuan izin belajar dan studi lanjut</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-label">Total Pengajuan</div>
                <div class="stat-value"><?php echo $stats['total_pengajuan'] ?? 0; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Menunggu Review</div>
                <div class="stat-value"><?php echo $stats['menunggu_review'] ?? 0; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Disetujui</div>
                <div class="stat-value"><?php echo $stats['disetujui'] ?? 0; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-label">Ditolak</div>
                <div class="stat-value"><?php echo $stats['ditolak'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs custom-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pengajuan-tab" data-bs-toggle="tab" data-bs-target="#tab-pengajuan" type="button" role="tab">
                    Manajemen Pengajuan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pelatihan-tab" data-bs-toggle="tab" data-bs-target="#tab-pelatihan" type="button" role="tab">
                    Manajemen Pelatihan
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reward-tab" data-bs-toggle="tab" data-bs-target="#tab-reward" type="button" role="tab">
                    Reward
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="template-tab" data-bs-toggle="tab" data-bs-target="#tab-template" type="button" role="tab">
                    Kelola Template
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="myTabContent">
            <!-- Tab: Manajemen Pengajuan -->
            <div class="tab-pane fade show active" id="tab-pengajuan" role="tabpanel">
                <?php include 'manajemenpengajuan.php'; ?>
            </div>

            <!-- Tab: Manajemen Pelatihan -->
            <div class="tab-pane fade" id="tab-pelatihan" role="tabpanel">
                <?php include 'pelatihan.php'; ?>
            </div>

            <!-- Tab: Reward -->
            <div class="tab-pane fade" id="tab-reward" role="tabpanel">
                <?php include 'reward.php'; ?>
            </div>

            <!-- Tab: Kelola Template -->
            <div class="tab-pane fade" id="tab-template" role="tabpanel">
                <?php include 'kelolatemplate.php'; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            const tab = urlParams.get('tab');
            
            if (tab) {
                const targetTab = document.querySelector(`[data-bs-target="#tab-${tab}"]`);
                if (targetTab) {
                    const bsTab = new bootstrap.Tab(targetTab);
                    bsTab.show();
                }
            }
            
            if (status && message) {
                const icon = status === 'success' ? 'success' : 'error';
                const title = status === 'success' ? 'Berhasil!' : 'Gagal!';
                
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: decodeURIComponent(message),
                    confirmButtonColor: '#3b82f6',
                    timer: 3000,
                    timerProgressBar: true
                });
                
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>