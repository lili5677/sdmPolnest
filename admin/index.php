<?php
require_once '../config/database.php';

// cek user login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

try {
    // total pegawai dnegan status aktif =====
    $query_total = "
        SELECT COUNT(DISTINCT p.pegawai_id) as total
        FROM pegawai p
        LEFT JOIN (
            SELECT sk1.*
            FROM status_kepegawaian sk1
            INNER JOIN (
                SELECT pegawai_id, MAX(created_at) as max_created
                FROM status_kepegawaian
                GROUP BY pegawai_id
            ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                 AND sk1.created_at = sk2.max_created
        ) latest_sk ON p.pegawai_id = latest_sk.pegawai_id
        WHERE COALESCE(latest_sk.status_aktif, 'aktif') = 'aktif'
    ";
    $stmt_total = $conn->query($query_total);
    $total_pegawai = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    //statisktik status pegawai aktik (kontrak)
    $query_kontrak = "
        SELECT COUNT(DISTINCT p.pegawai_id) as total
        FROM pegawai p
        LEFT JOIN (
            SELECT sk1.*
            FROM status_kepegawaian sk1
            INNER JOIN (
                SELECT pegawai_id, MAX(created_at) as max_created
                FROM status_kepegawaian
                GROUP BY pegawai_id
            ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                 AND sk1.created_at = sk2.max_created
        ) latest_sk ON p.pegawai_id = latest_sk.pegawai_id
        WHERE COALESCE(latest_sk.status_aktif, 'aktif') = 'aktif'
        AND LOWER(COALESCE(latest_sk.jenis_kepegawaian, 'tetap')) = 'kontrak'
    ";
    $stmt_kontrak = $conn->query($query_kontrak);
    $pegawai_kontrak = $stmt_kontrak->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // statsitik kontrak akan habis(30 hari dnegan status aktif)
    $query_habis = "
        SELECT COUNT(DISTINCT p.pegawai_id) as total
        FROM pegawai p
        LEFT JOIN (
            SELECT sk1.*
            FROM status_kepegawaian sk1
            INNER JOIN (
                SELECT pegawai_id, MAX(created_at) as max_created
                FROM status_kepegawaian
                GROUP BY pegawai_id
            ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                 AND sk1.created_at = sk2.max_created
        ) latest_sk ON p.pegawai_id = latest_sk.pegawai_id
        WHERE COALESCE(latest_sk.status_aktif, 'aktif') = 'aktif'
        AND LOWER(COALESCE(latest_sk.jenis_kepegawaian, 'tetap')) = 'kontrak'
        AND latest_sk.masa_kontrak_selesai IS NOT NULL
        AND DATEDIFF(latest_sk.masa_kontrak_selesai, CURDATE()) BETWEEN 0 AND 30
    ";
    $stmt_habis = $conn->query($query_habis);
    $kontrak_habis = $stmt_habis->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // statistik lamaran
    $query_lamaran = "
        SELECT COUNT(*) as total 
        FROM lamaran 
        WHERE status_lamaran IN ('dikirim', 'seleksi_administrasi')
    ";
    $stmt_lamaran = $conn->query($query_lamaran);
    $lamaran_baru = $stmt_lamaran->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // statistik sertifikasi
    $query_sertifikasi = "
        SELECT COUNT(DISTINCT s.sertifikasi_id) as total
        FROM sertifikasi_dosen s
        INNER JOIN pegawai p ON s.pegawai_id = p.pegawai_id
        LEFT JOIN (
            SELECT sk1.*
            FROM status_kepegawaian sk1
            INNER JOIN (
                SELECT pegawai_id, MAX(created_at) as max_created
                FROM status_kepegawaian
                GROUP BY pegawai_id
            ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                 AND sk1.created_at = sk2.max_created
        ) latest_sk ON p.pegawai_id = latest_sk.pegawai_id
        WHERE COALESCE(latest_sk.status_aktif, 'aktif') = 'aktif'
        AND s.tahun_masa_berlaku IS NOT NULL
        AND s.tahun_masa_berlaku <= YEAR(DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
        AND s.status_validasi = 'tervalidasi'
    ";
    $stmt_sertifikasi = $conn->query($query_sertifikasi);
    $sertifikasi_habis = $stmt_sertifikasi->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // chart pegawai aktif
    $query_status = "
        SELECT 
            LOWER(COALESCE(latest_sk.jenis_kepegawaian, 'tetap')) as jenis_kepegawaian,
            COUNT(DISTINCT p.pegawai_id) as jumlah
        FROM pegawai p
        LEFT JOIN (
            SELECT sk1.*
            FROM status_kepegawaian sk1
            INNER JOIN (
                SELECT pegawai_id, MAX(created_at) as max_created
                FROM status_kepegawaian
                GROUP BY pegawai_id
            ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                 AND sk1.created_at = sk2.max_created
        ) latest_sk ON p.pegawai_id = latest_sk.pegawai_id
        WHERE COALESCE(latest_sk.status_aktif, 'aktif') = 'aktif'
        GROUP BY LOWER(COALESCE(latest_sk.jenis_kepegawaian, 'tetap'))
    ";
    $stmt_status = $conn->query($query_status);
    $data_status_raw = $stmt_status->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung persentase
    $total_for_chart = array_sum(array_column($data_status_raw, 'jumlah'));
    $data_status = [];
    foreach ($data_status_raw as $row) {
        $persentase = $total_for_chart > 0 
            ? round(($row['jumlah'] / $total_for_chart) * 100) 
            : 0;
        $data_status[] = [
            'jenis_kepegawaian' => $row['jenis_kepegawaian'],
            'jumlah' => $row['jumlah'],
            'persentase' => $persentase
        ];
    }

} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem SDM Polnest</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- favicon -->
     <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
    <!-- Load Notification CSS -->
    <link rel="stylesheet" href="api/notifications.php?get=css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .main-content {
            padding: 30px;
            margin-left: 290px;
            transition: margin-left 0.3s ease;
        }
        .dashboard-top {
            margin-bottom: 30px;
        }

        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .header {
            margin-bottom: 20px;
        }

        .header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .header-left {
            flex: 1;
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .header-left p {
            font-size: 14px;
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-info h3 {
            font-size: 14px;
            font-weight: 500;
            color: #666;
            margin-bottom: 8px;
        }

        .stat-info .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .icon-blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .icon-green {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .icon-orange {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .icon-red {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .icon-purple {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
        }

        .content-grid {
          grid-template-columns: 1fr;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .chart-container {
            padding: 30px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-container canvas {
            max-width: 100%;
            max-height: 100%;
        }

        .empty-state {
            text-align: center;
            color: #999;
        }

        .empty-state i {
            display: block;
            margin-bottom: 10px;
        }

    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="main-content">

    <div class="dashboard-top">
        <div class="header">
            <div class="header-left">
                <h1>Dashboard Admin</h1>
                <p>Selamat datang di Sistem Manajemen SDM Polnest</p>
            </div>

            <div class="notification-wrapper">
                <a href="javascript:void(0)" 
                   class="notification-bell" 
                   id="notification-bell">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notification-badge"></span>
                </a>

                <div class="notification-dropdown" id="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifikasi</h3>
                    </div>
                    <div class="notification-list" id="notification-list">
                        <div class="notification-empty">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Memuat notifikasi...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <a href="administrasi/administrasiKepegawaian.php" class="stat-card">
                <div class="stat-info">
                    <h3>Total Pegawai</h3>
                    <div class="stat-number"><?= number_format($total_pegawai) ?></div>
                </div>
                <div class="stat-icon icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
            </a>

            <a href="administrasi/administrasiKepegawaian.php" class="stat-card">
                <div class="stat-info">
                    <h3>Pegawai Kontrak</h3>
                    <div class="stat-number"><?= number_format($pegawai_kontrak) ?></div>
                </div>
                <div class="stat-icon icon-green">
                    <i class="bi bi-file-earmark-text-fill"></i>
                </div>
            </a>

            <a href="administrasi/administrasiKepegawaian.php" class="stat-card">
                <div class="stat-info">
                    <h3>Kontrak Akan Habis</h3>
                    <div class="stat-number"><?= number_format($kontrak_habis) ?></div>
                </div>
                <div class="stat-icon icon-red">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </a>

            <a href="manajemenrec/manajemenrec.php" class="stat-card">
                <div class="stat-info">
                    <h3>Lamaran Baru</h3>
                    <div class="stat-number"><?= number_format($lamaran_baru) ?></div>
                </div>
                <div class="stat-icon icon-orange">
                    <i class="bi bi-envelope-fill"></i>
                </div>
            </a>

            <a href="sertifikasi/sertifikasi-dosen.php" class="stat-card">
                <div class="stat-info">
                    <h3>Sertifikasi Akan Habis</h3>
                    <div class="stat-number"><?= number_format($sertifikasi_habis) ?></div>
                </div>
                <div class="stat-icon icon-purple">
                    <i class="bi bi-award-fill"></i>
                </div>
            </a>
        </div>

        <!-- chart -->
        <div class="content-grid">
            <div class="card">
                <div class="card-header">
                    <h2>Status Kepegawaian</h2>
                </div>
                <div class="chart-container">
                    <?php if (!empty($data_status)): ?>
                        <canvas id="statusChart"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-pie-chart" style="font-size: 48px; color: #ddd;"></i>
                            <p style="margin-top: 10px;">Tidak ada data pegawai</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- notifikasi -->
    <script src="api/notifications.php?get=js"></script>

    <!-- script chart -->
    <script>
        <?php if (!empty($data_status)): ?>
        const ctx = document.getElementById('statusChart');
        
        const data_chart = {
            labels: <?= json_encode(array_map(function($item) {
                return ucfirst($item['jenis_kepegawaian']);
            }, $data_status)) ?>,
            datasets: [{
                data: <?= json_encode(array_column($data_status, 'persentase')) ?>,
                backgroundColor: ['#60a5fa', '#fbbf24', '#34d399', '#f87171'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        };

        const config = {
            type: 'doughnut',
            data: data_chart,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: { size: 12, family: 'Poppins' },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        return {
                                            text: `${label} - ${value}%`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        };

        new Chart(ctx, config);
        <?php endif; ?>
    </script>
</body>
</html>