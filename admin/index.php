<?php
// Koneksi database
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

try {
    // ===== 1. TOTAL PEGAWAI AKTIF (HANYA YANG STATUS AKTIF!) =====
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
    
    // ===== 2. PEGAWAI KONTRAK (HANYA YANG STATUS AKTIF!) =====
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
    
    // ===== 3. KONTRAK AKAN HABIS (30 HARI, HANYA YANG STATUS AKTIF!) =====
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
    
    // ===== 4. LAMARAN BARU MENUNGGU VERIFIKASI =====
    $query_lamaran = "
        SELECT COUNT(*) as total 
        FROM lamaran 
        WHERE status_lamaran IN ('dikirim', 'seleksi_administrasi')
    ";
    $stmt_lamaran = $conn->query($query_lamaran);
    $lamaran_baru = $stmt_lamaran->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // ===== 5. SERTIFIKASI DOSEN AKAN HABIS (TAHUN INI DAN TAHUN DEPAN) =====
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

    // ===== 6. PIE CHART STATUS PEGAWAI (HANYA YANG AKTIF!) =====
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

        /* ===== HEADER WITH NOTIFICATION BELL ===== */
        .header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
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

        /* Notification Bell */
        .notification-bell {
            position: relative;
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .notification-bell:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            background: #f8f9fa;
        }

        .notification-bell i {
            font-size: 22px;
            color: #666;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 7px;
            border-radius: 10px;
            min-width: 22px;
            height: 22px;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        }

        .notification-badge.pulse {
            animation: pulse 1s ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            width: 420px;
            max-height: 550px;
            overflow-y: auto;
            display: none;
            z-index: 9999;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-dropdown::-webkit-scrollbar {
            width: 6px;
        }

        .notification-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .notification-dropdown::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        .notification-dropdown-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            background: #f8f9fa;
            border-radius: 12px 12px 0 0;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .notification-dropdown-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }

        .notification-empty {
            padding: 60px 20px;
            text-align: center;
            color: #999;
        }

        .notification-empty i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 10px;
        }

        .notification-empty p {
            font-size: 14px;
            margin: 0;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            cursor: pointer;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .notif-danger .notification-icon {
            background: #fee2e2;
            color: #ef4444;
        }

        .notif-warning .notification-icon {
            background: #fef3c7;
            color: #f59e0b;
        }

        .notif-info .notification-icon {
            background: #dbeafe;
            color: #3b82f6;
        }

        .notif-success .notification-icon {
            background: #d4f4dd;
            color: #22c55e;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-content h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0 0 4px 0;
        }

        .notification-content p {
            font-size: 13px;
            color: #666;
            margin: 0 0 4px 0;
            line-height: 1.4;
        }

        .notification-content small {
            font-size: 11px;
            color: #999;
        }

        .notification-count {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .notif-danger .notification-count {
            background: #ef4444;
            color: white;
        }

        .notif-warning .notification-count {
            background: #f59e0b;
            color: white;
        }

        .notif-info .notification-count {
            background: #3b82f6;
            color: white;
        }

        .notif-success .notification-count {
            background: #22c55e;
            color: white;
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

        /* Content Grid - Full Width untuk Pie Chart */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
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

        .chart-container {
            position: relative;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-state {
            text-align: center;
            color: #999;
            font-size: 14px;
            padding: 40px 20px;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-dropdown {
                width: calc(100vw - 40px);
                right: -10px;
            }
        }
        .stat-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .stat-card-link:hover .stat-card {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            cursor: pointer;
            transition: 0.2s;
        }

    </style>
</head>
<body>
    <?php include 'sidebar/sidebar.php'; ?>

    <div class="main-content">
        <!-- HEADER WITH NOTIFICATION BELL -->
        <div class="header">
            <div class="header-left">
                <h1>Dashboard Admin</h1>
                <p>Selamat datang di Sistem SDM Politeknik Negeri Nusa Utara Tarakan</p>
            </div>
            
            <!-- NOTIFICATION BELL -->
            <div class="notification-bell" id="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notification-badge">0</span>
                
                <!-- DROPDOWN NOTIFIKASI -->
                <div class="notification-dropdown" id="notification-dropdown">
                    <div class="notification-dropdown-header">
                        <h3>Notifikasi & Peringatan</h3>
                    </div>
                    <div id="notification-list">
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>Memuat notifikasi...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<div class="stats-grid">

    <!-- Total Pegawai Aktif (opsional: mau diarahkan juga?) -->
    <a href="administrasi/administrasiKepegawaian.php" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Total Pegawai Aktif</h3>
                <div class="number"><?= number_format($total_pegawai) ?></div>
                <div class="subtitle">Pegawai aktif saat ini</div>
            </div>
            <div class="stat-icon icon-green">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </a>

    <!-- Pegawai Kontrak -->
    <a href="administrasi/administrasiKepegawaian.php" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Pegawai Kontrak</h3>
                <div class="number"><?= number_format($pegawai_kontrak) ?></div>
                <div class="subtitle">Status kontrak aktif</div>
            </div>
            <div class="stat-icon icon-blue">
                <i class="bi bi-file-text-fill"></i>
            </div>
        </div>
    </a>

    <!-- Kontrak Akan Habis -->
    <a href="administrasi/administrasiKepegawaian.php" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Kontrak Akan Habis</h3>
                <div class="number"><?= number_format($kontrak_habis) ?></div>
                <div class="subtitle">Dalam 30 hari ke depan</div>
            </div>
            <div class="stat-icon icon-yellow">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
        </div>
    </a>

    <!-- Lamaran Baru -->
    <a href="manajemenrec/manajemenrec.php" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Lamaran Baru</h3>
                <div class="number"><?= number_format($lamaran_baru) ?></div>
                <div class="subtitle">Menunggu verifikasi</div>
            </div>
            <div class="stat-icon icon-red">
                <i class="bi bi-envelope-fill"></i>
            </div>
        </div>
    </a>

    <!-- Sertifikasi -->
    <a href="sertifikasi/sertifikasi-dosen.php" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Sertifikasi Kadaluarsa</h3>
                <div class="number"><?= number_format($sertifikasi_habis) ?></div>
                <div class="subtitle">Dalam 6 bulan ke depan</div>
            </div>
            <div class="stat-icon icon-purple">
                <i class="bi bi-award-fill"></i>
            </div>
        </div>
    </a>

</div>


        <!-- PIE CHART STATUS PEGAWAI (Full Width) -->
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

    <!-- NOTIFICATION JAVASCRIPT -->
    <script>
    // Toggle Dropdown
    document.addEventListener('DOMContentLoaded', function() {
        const bell = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');
        
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function(e) {
            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Load notifications
        loadNotifications();
        
        // Auto-refresh every 30 seconds
        setInterval(loadNotifications, 30000);
    });
    
    let lastCheck = null;
    
    async function loadNotifications() {
        try {
            const url = 'api/notifications.php' + (lastCheck ? '?last_check=' + lastCheck : '');
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                updateBadge(data.total);
                updateDropdown(data.notifications);
                lastCheck = data.timestamp;
                
                console.log('📊 Notifikasi:', data.total, 'Baru:', data.new_count);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }
    
    function updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
            badge.classList.add('pulse');
            setTimeout(() => badge.classList.remove('pulse'), 1000);
        } else {
            badge.style.display = 'none';
        }
    }
    
    function updateDropdown(notifications) {
        const list = document.getElementById('notification-list');
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>Tidak ada notifikasi</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        notifications.forEach(notif => {
            const iconClass = getIconClass(notif.type);
            const colorClass = getColorClass(notif.priority);
            
            html += `
                <a href="${notif.url}" class="notification-item ${colorClass}">
                    <div class="notification-icon">
                        <i class="fas ${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <h4>${notif.title}</h4>
                        <p>${notif.message}</p>
                        <small>${timeAgo(notif.created_at)}</small>
                    </div>
                    <div class="notification-count">${notif.count}</div>
                </a>
            `;
        });
        
        list.innerHTML = html;
    }
    
    function getIconClass(type) {
        const icons = {
            'lamaran': 'fa-envelope',
            'kontrak': 'fa-file-contract',
            'studi': 'fa-graduation-cap',
            'sertifikasi': 'fa-certificate',
            'sertifikasi_habis': 'fa-certificate',
            'password': 'fa-key',
            'dokumen': 'fa-file-alt',
            'kinerja': 'fa-chart-line'
        };
        return icons[type] || 'fa-bell';
    }
    
    function getColorClass(priority) {
        const colors = {
            'danger': 'notif-danger',
            'warning': 'notif-warning',
            'info': 'notif-info',
            'success': 'notif-success'
        };
        return colors[priority] || 'notif-info';
    }
    
    function timeAgo(datetime) {
        const now = new Date();
        const past = new Date(datetime);
        const diff = Math.floor((now - past) / 1000);
        
        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
        if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
        if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
        return past.toLocaleDateString('id-ID');
    }
    </script>

    <!-- PIE CHART SCRIPT -->
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