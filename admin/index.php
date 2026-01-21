<?php
session_start();

// Data kosong - nanti diisi dari database
$stats = [
    'users' => 0,
    'applicants' => 0,
    'active_jobs' => 0,
    'pending_interviews' => 0
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SDM Perusahaan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            color: #1c1e21;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
            position: fixed;
            height: 100vh;
            padding: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 30px 25px;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 14px 25px;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 15px;
            margin: 2px 15px;
            border-radius: 8px;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-item.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            font-weight: 600;
        }

        .menu-icon {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            display: inline-block;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 0;
            background: #f0f2f5;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 20px 35px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title h1 {
            font-size: 26px;
            color: #1c1e21;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            font-size: 14px;
            color: #65676b;
            margin-top: 4px;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-bell {
            position: relative;
            width: 40px;
            height: 40px;
            background: #f0f2f5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
        }

        .notification-bell:hover {
            background: #e4e6eb;
        }

        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 30px 35px;
        }

        /* Stats Grid */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .stat-box.blue::before { background: #3b82f6; }
        .stat-box.yellow::before { background: #fbbf24; }
        .stat-box.cyan::before { background: #06b6d4; }
        .stat-box.red::before { background: #ef4444; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #1c1e21;
            margin-bottom: 4px;
        }

        .stat-info p {
            font-size: 13px;
            color: #65676b;
            font-weight: 500;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-box.blue .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .stat-box.yellow .stat-icon {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
        }

        .stat-box.cyan .stat-icon {
            background: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
        }

        .stat-box.red .stat-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            font-size: 13px;
            margin-top: 10px;
        }

        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        /* Two Column Layout */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1c1e21;
            margin: 0;
        }

        .card-body {
            padding: 24px;
        }

        /* Progress Items */
        .progress-item {
            margin-bottom: 24px;
        }

        .progress-item:last-child {
            margin-bottom: 0;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .progress-label-text {
            color: #1c1e21;
            font-weight: 500;
        }

        .progress-label-value {
            color: #65676b;
        }

        .progress-bar-container {
            height: 8px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            border-radius: 10px;
            width: 0%;
            transition: width 1s ease;
        }

        /* Donut Chart */
        .chart-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 0;
        }

        .chart-wrapper {
            position: relative;
            width: 220px;
            height: 220px;
        }

        /* Alert Section */
        .alert-section {
            margin-top: 20px;
        }

        .alert-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .alert-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .alert-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .alert-box.danger .alert-icon {
            background: #fee2e2;
            color: #dc2626;
        }

        .alert-box.warning .alert-icon {
            background: #fef3c7;
            color: #d97706;
        }

        .alert-content {
            flex: 1;
        }

        .alert-title {
            font-size: 14px;
            font-weight: 600;
            color: #1c1e21;
            margin-bottom: 4px;
        }

        .alert-desc {
            font-size: 13px;
            color: #65676b;
        }

        .alert-count {
            font-size: 24px;
            font-weight: 700;
            color: #1c1e21;
        }

        @media (max-width: 1400px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .two-column {
                grid-template-columns: 1fr;
            }
            .alert-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php include 'sidebar/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="page-title">
                    <h1>Dashboard Admin</h1>
                    <p class="page-subtitle">Monitoring Kualifikasi dan Rekrutmen</p>
                </div>
                <div class="user-section">
                    <div class="notification-bell">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span class="notification-badge"></span>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Stats -->
                <div class="stats-container">
                    <div class="stat-box blue">
                        <div class="stat-header">
                            <div class="stat-info">
                                <h3><?php echo $stats['users']; ?></h3>
                                <p>Users</p>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <span>↑ 0%</span>
                        </div>
                    </div>

                    <div class="stat-box yellow">
                        <div class="stat-header">
                            <div class="stat-info">
                                <h3><?php echo $stats['applicants']; ?></h3>
                                <p>Applicants</p>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <polyline points="17 11 19 13 23 9"></polyline>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <span>↑ 0%</span>
                        </div>
                    </div>

                    <div class="stat-box cyan">
                        <div class="stat-header">
                            <div class="stat-info">
                                <h3><?php echo $stats['active_jobs']; ?></h3>
                                <p>Active Jobs</p>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-trend trend-down">
                            <span>↓ 0%</span>
                        </div>
                    </div>

                    <div class="stat-box red">
                        <div class="stat-header">
                            <div class="stat-info">
                                <h3><?php echo $stats['pending_interviews']; ?></h3>
                                <p>Pending Interviews</p>
                            </div>
                            <div class="stat-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                        </div>
                        <div class="stat-trend trend-up">
                            <span>↑ 0%</span>
                        </div>
                    </div>
                </div>

                <!-- Two Column Section -->
                <div class="two-column">
                    <!-- Monitoring Kualifikasi Pelamar -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Monitoring Kualifikasi Pelamar</h3>
                        </div>
                        <div class="card-body">
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span class="progress-label-text">SMA Sederajat</span>
                                    <span class="progress-label-value">0</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span class="progress-label-text">D3 Sederajat</span>
                                    <span class="progress-label-value">0</span>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Pelamar -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Status Pelamar</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <div class="chart-wrapper">
                                    <canvas id="donutChart" width="220" height="220"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert & Notification -->
                <div class="alert-section">
                    <div class="card">
                        <div class="card-header">
                            <h3>Alert & Notifikasi</h3>
                        </div>
                        <div class="card-body">
                            <div class="alert-grid">
                                <div class="alert-box danger">
                                    <div class="alert-icon">!</div>
                                    <div class="alert-content">
                                        <div class="alert-title">Verifikasi Pelamar Anda</div>
                                        <div class="alert-desc">Menunggu Proses Verif</div>
                                    </div>
                                    <div class="alert-count">0</div>
                                </div>
                                <div class="alert-box warning">
                                    <div class="alert-icon">!</div>
                                    <div class="alert-content">
                                        <div class="alert-title">Pengisian Formulir Anda</div>
                                        <div class="alert-desc">Menunggu Proses Verif</div>
                                    </div>
                                    <div class="alert-count">0</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Donut Chart
        window.addEventListener('load', function() {
            const canvas = document.getElementById('donutChart');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                const centerX = 110;
                const centerY = 110;
                const radius = 80;
                const innerRadius = 55;
                
                // Data kosong - nanti dari database
                const data = [
                    {label: 'Lulus Adm', value: 0, color: '#60a5fa'},
                    {label: 'Pengisian Form', value: 0, color: '#fbbf24'},
                    {label: 'Lolos', value: 0, color: '#34d399'},
                    {label: 'Tidak Lolos', value: 0, color: '#f87171'}
                ];
                
                const total = data.reduce((sum, item) => sum + item.value, 0);
                
                if (total === 0) {
                    // Draw empty circle
                    ctx.beginPath();
                    ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
                    ctx.arc(centerX, centerY, innerRadius, 2 * Math.PI, 0, true);
                    ctx.closePath();
                    ctx.fillStyle = '#e5e7eb';
                    ctx.fill();
                } else {
                    let currentAngle = -Math.PI / 2;
                    
                    data.forEach(item => {
                        const sliceAngle = (item.value / total) * 2 * Math.PI;
                        
                        ctx.beginPath();
                        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                        ctx.arc(centerX, centerY, innerRadius, currentAngle + sliceAngle, currentAngle, true);
                        ctx.closePath();
                        ctx.fillStyle = item.color;
                        ctx.fill();
                        
                        currentAngle += sliceAngle;
                    });
                }
                
                // Center circle
                ctx.beginPath();
                ctx.arc(centerX, centerY, innerRadius, 0, 2 * Math.PI);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                
                // Center text
                ctx.fillStyle = '#1c1e21';
                ctx.font = 'bold 28px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(total, centerX, centerY - 5);
                ctx.font = '14px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                ctx.fillStyle = '#65676b';
                ctx.fillText('Total', centerX, centerY + 18);
            }
        });
    </script>
</body>
</html>
