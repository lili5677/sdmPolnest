<?php
// Koneksi database
// Path yang benar dari admin/index.php ke config
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// Ganti $pdo jadi $conn (sesuai database.php)
try {
    // Query untuk mendapatkan data dashboard menggunakan view yang sudah ada
    $query_dashboard = "SELECT * FROM v_dashboard_admin"; 
    $stmt_dashboard = $conn->query($query_dashboard);
    $data_dashboard = $stmt_dashboard->fetch(PDO::FETCH_ASSOC);

    // Data untuk card statistik
    $total_pegawai = $data_dashboard['total_pegawai_aktif'] ?? 0;
    $pegawai_kontrak = $data_dashboard['pegawai_kontrak'] ?? 0;
    $kontrak_habis = $data_dashboard['kontrak_akan_habis'] ?? 0;
    $lamaran_baru = $data_dashboard['lamaran_baru'] ?? 0;

    // Query untuk Monitoring Kuota Formasi
    $query_formasi = "SELECT nama_posisi, kuota_total, kuota_terisi, jumlah_pendaftar FROM kuota_formasi ORDER BY created_at DESC";
    $stmt_formasi = $conn->query($query_formasi);
    $data_formasi = $stmt_formasi->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk Status Pegawai (untuk pie chart)
    $query_status = "
        SELECT 
            jenis_kepegawaian,
            COUNT(*) as jumlah,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM status_kepegawaian WHERE status_aktif = 'aktif')), 0) as persentase
        FROM status_kepegawaian 
        WHERE status_aktif = 'aktif'
        GROUP BY jenis_kepegawaian
    ";
    $stmt_status = $conn->query($query_status);
    $data_status = $stmt_status->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk Alert & Notifikasi
    $query_notif = "SELECT * FROM notifikasi_admin WHERE is_read = 0 ORDER BY created_at DESC LIMIT 4";
    $stmt_notif = $conn->query($query_notif);
    $data_notif = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);

    // Hitung persentase untuk pie chart
    $total_pegawai_chart = array_sum(array_column($data_status, 'jumlah'));
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Main Content - Sesuaikan dengan sidebar yang baru */
        .main-content {
            padding: 30px;
            margin-left: 290px;
            transition: margin-left 0.3s ease;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            color: #666;
        }

        /* Card Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-info h3 {
            font-size: 13px;
            color: #666;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-info .number {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .stat-info .subtitle {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .icon-green { background-color: #d4f4dd; color: #22c55e; }
        .icon-blue { background-color: #dbeafe; color: #3b82f6; }
        .icon-yellow { background-color: #fef3c7; color: #f59e0b; }
        .icon-red { background-color: #fee2e2; color: #ef4444; }
        .icon-purple { background-color: #e9d5ff; color: #a855f7; }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card-header {
            margin-bottom: 20px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        /* Progress Bar */
        .progress-item {
            margin-bottom: 16px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .progress-label {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .progress-value {
            font-size: 13px;
            color: #666;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background-color: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Pie Chart */
        .chart-container {
            position: relative;
            height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-state {
            text-align: center;
            color: #999;
            font-size: 14px;
        }

        /* Alert Section */
        .alert-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .alert-item {
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid;
            background-color: #fafafa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-yellow { border-left-color: #f59e0b; background-color: #fffbeb; }
        .alert-red { border-left-color: #ef4444; background-color: #fef2f2; }
        .alert-blue { border-left-color: #3b82f6; background-color: #eff6ff; }

        .alert-content h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        .alert-content p {
            font-size: 12px;
            color: #666;
        }

        .alert-badge {
            min-width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .badge-yellow { background-color: #f59e0b; }
        .badge-red { background-color: #ef4444; }
        .badge-blue { background-color: #3b82f6; }

        .full-width {
            grid-column: 1 / -1;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .alert-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1>Dashboard Admin</h1>
            <p>Pusat Monitoring & Kendali Sistem HR</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Pegawai Aktif</h3>
                    <div class="number"><?= $total_pegawai ?></div>
                    <div class="subtitle">Semua status</div>
                </div>
                <div class="stat-icon icon-green">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Pegawai Kontrak</h3>
                    <div class="number"><?= $pegawai_kontrak ?></div>
                    <div class="subtitle">Status kontrak</div>
                </div>
                <div class="stat-icon icon-blue">
                    <i class="bi bi-clipboard-check-fill"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Kontrak Habis</h3>
                    <div class="number"><?= $kontrak_habis ?></div>
                    <div class="subtitle">30 hari ke depan</div>
                </div>
                <div class="stat-icon icon-yellow">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Lamaran Menunggu</h3>
                    <div class="number"><?= $lamaran_baru ?></div>
                    <div class="subtitle">Menunggu verifikasi</div>
                </div>
                <div class="stat-icon icon-red">
                    <i class="bi bi-envelope-fill"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h3>Sertifikasi Dosen Habis</h3>
                    <div class="number">0</div>
                    <div class="subtitle">Perlu diperpanjang</div>
                </div>
                <div class="stat-icon icon-purple">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Monitoring Kuota Formasi -->
            <div class="card">
                <div class="card-header">
                    <h2>Monitoring Kuota Formasi</h2>
                </div>
                <div class="progress-list">
                    <?php if (empty($data_formasi)): ?>
                        <div class="empty-state">
                            <p>Belum ada data kuota formasi</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($data_formasi as $formasi): ?>
                            <?php 
                                $persentase = $formasi['kuota_total'] > 0 
                                    ? round(($formasi['kuota_terisi'] / $formasi['kuota_total']) * 100) 
                                    : 0;
                            ?>
                            <div class="progress-item">
                                <div class="progress-header">
                                    <span class="progress-label"><?= htmlspecialchars($formasi['nama_posisi']) ?></span>
                                    <span class="progress-value"><?= $formasi['kuota_terisi'] ?>/<?= $formasi['kuota_total'] ?></span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?= $persentase ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Pegawai Chart -->
            <div class="card">
                <div class="card-header">
                    <h2>Status Pegawai</h2>
                </div>
                <div class="chart-container">
                    <?php if (empty($data_status)): ?>
                        <div class="empty-state">
                            <p>Belum ada data pegawai</p>
                        </div>
                    <?php else: ?>
                        <canvas id="statusChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Alert & Notifikasi -->
        <div class="card full-width">
            <div class="card-header">
                <h2>Alert & Notifikasi</h2>
            </div>
            <div class="alert-grid">
                <?php if (empty($data_notif)): ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <p>Tidak ada notifikasi baru</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($data_notif as $notif): ?>
                        <?php
                            $alert_class = 'alert-blue';
                            $badge_class = 'badge-blue';
                            
                            if ($notif['jenis_notifikasi'] == 'kontrak_habis') {
                                $alert_class = 'alert-yellow';
                                $badge_class = 'badge-yellow';
                            } elseif ($notif['jenis_notifikasi'] == 'verifikasi_pegawai') {
                                $alert_class = 'alert-red';
                                $badge_class = 'badge-red';
                            }
                        ?>
                        <div class="alert-item <?= $alert_class ?>">
                            <div class="alert-content">
                                <h3><?= htmlspecialchars($notif['judul']) ?></h3>
                                <p><?= htmlspecialchars($notif['deskripsi']) ?></p>
                            </div>
                            <div class="alert-badge <?= $badge_class ?>">
                                <?= $notif['jumlah_item'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

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