<?php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

$query_stats = "SELECT 
    COUNT(*) as total_pengajuan,
    SUM(CASE WHEN status_pengajuan IN ('diajukan', 'ditinjau') THEN 1 ELSE 0 END) as menunggu_review,
    SUM(CASE WHEN status_pengajuan = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
    SUM(CASE WHEN status_pengajuan = 'ditolak' THEN 1 ELSE 0 END) as ditolak
FROM pengajuan_studi";

$stmt_stats = $conn->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'pengajuan';
$validTabs = ['pengajuan', 'pelatihan', 'reward'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'pengajuan';
}

// data pengajuan
$query_pengajuan = "SELECT 
    ps.*,
    p.nama_lengkap,
    p.email,
    sk.jabatan,
    sk.unit_kerja
FROM pengajuan_studi ps
JOIN pegawai p ON ps.pegawai_id = p.pegawai_id
LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
ORDER BY ps.created_at DESC";

$stmt_pengajuan = $conn->prepare($query_pengajuan);
$stmt_pengajuan->execute();
$data_pengajuan = $stmt_pengajuan->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
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
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .page-header h1 { 
            font-size: 26px; 
            font-weight: 700; 
            margin-bottom: 6px; 
        }
        
        .page-header p { 
            font-size: 14px; 
            opacity: 0.88; 
        }

        .btn-kelola-template {
            padding: 10px 20px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
        }

        .btn-kelola-template:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
            color: white;
        }

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
            text-decoration: none;
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

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
        }

        .content-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }

        .content-card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .content-card-title i {
            color: #3b82f6;
            font-size: 20px;
        }

        .tab-content-wrapper {
            min-height: 400px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* DataTables Custom Styling */
        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_wrapper .dataTables_length {
            margin-bottom: 20px;
        }

        .dataTables_wrapper .dataTables_length label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 8px 32px 8px 12px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .dataTables_wrapper .dataTables_length select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 20px;
        }

        .dataTables_wrapper .dataTables_filter label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 10px 16px 10px 40px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            font-family: 'Poppins', sans-serif;
            font-size: 13px;
            transition: all 0.2s;
            width: 280px;
            background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E") no-repeat 12px center;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .dataTables_wrapper .dataTables_info {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            padding: 16px 0;
        }

        .dataTables_wrapper .dataTables_paginate {
            padding: 16px 0;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 14px;
            margin: 0 3px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            background: white;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f8fafc;
            border-color: #3b82f6;
            color: #3b82f6;
            transform: translateY(-1px);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            transform: translateY(-1px);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: white;
            border-color: #e2e8f0;
            color: #475569;
            transform: none;
        }

        table.dataTable {
            width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 10px;
            overflow: hidden;
            font-size: 12px;
        }

        table.dataTable thead {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
        }

        table.dataTable thead th {
            background: transparent;
            color: white;
            font-weight: 600;
            font-size: 11px;
            padding: 12px 8px;
            border: none;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            vertical-align: middle;
        }

        table.dataTable thead th:first-child {
            border-top-left-radius: 10px;
        }

        table.dataTable thead th:last-child {
            border-top-right-radius: 10px;
        }

        table.dataTable tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s;
        }

        table.dataTable tbody tr:last-child {
            border-bottom: none;
        }

        table.dataTable tbody tr:hover {
            background: linear-gradient(to right, #f8fafc, #ffffff);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        table.dataTable tbody td {
            padding: 10px 8px;
            vertical-align: middle;
            font-size: 12px;
            color: #475569;
            border: none;
        }

        .employee-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
            max-width: 180px;
        }

        .employee-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .employee-email {
            font-size: 10px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .employee-email i {
            font-size: 9px;
            flex-shrink: 0;
        }

        .jenjang-badge {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        .date-info {
            color: #64748b;
            white-space: nowrap;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .date-info i {
            font-size: 10px;
            color: #94a3b8;
        }

        .badge-status {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-status i {
            font-size: 9px;
        }

        .badge-diajukan {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .badge-ditinjau {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .badge-disetujui {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .badge-ditolak {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: nowrap;
            justify-content: flex-start;
        }

        .btn-action {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            flex-shrink: 0;
        }

        .btn-action i {
            font-size: 9px;
        }

        .btn-detail {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .btn-detail:hover {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
        }

        .btn-approve {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #a7f3d0 0%, #6ee7b7 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 95, 70, 0.25);
        }

        .btn-reject {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(153, 27, 27, 0.25);
        }

        .row-number {
            text-align: center;
            color: #94a3b8;
            font-weight: 700;
            font-size: 12px;
        }

        /* Responsive adjustments */
        @media (max-width: 1400px) {
            table.dataTable {
                font-size: 11px;
            }
            
            .employee-info {
                max-width: 150px;
            }
            
            .btn-action {
                padding: 5px 8px;
                font-size: 9px;
            }
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px;
                padding: 24px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
                padding-top: 90px;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Pengembangan SDM</h1>
                <p class="page-subtitle">Kelola pengajuan izin belajar dan studi lanjut</p>
            </div>
        </div>

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

        <ul class="nav nav-tabs custom-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab == 'pengajuan' ? 'active' : ''; ?>" href="?tab=pengajuan">
                    Manajemen Pengajuan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab == 'pelatihan' ? 'active' : ''; ?>" href="?tab=pelatihan">
                    Manajemen Pelatihan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab == 'reward' ? 'active' : ''; ?>" href="?tab=reward">
                    Reward
                </a>
            </li>
        </ul>

        <div class="tab-content-wrapper">
            <?php if ($activeTab == 'pengajuan'): ?>
                <div class="content-card">
                    <div class="content-card-header">
                        <h3 class="content-card-title">
                            <i class="fas fa-table"></i>
                            Data Pengajuan Studi Lanjut
                        </h3>
                        <a href="kelolatemplate.php" class="btn-kelola-template">
                            <i class="fas fa-file-alt"></i>
                            Kelola Template
                        </a>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="tablePengajuan" class="table table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">No</th>
                                    <th style="width: 160px;">Pegawai</th>
                                    <th style="width: 110px;">Jabatan</th>
                                    <th style="width: 70px;">Jenjang</th>
                                    <th style="width: 140px;">Institusi</th>
                                    <th style="width: 140px;">Prodi</th>
                                    <th style="width: 100px;">Tanggal</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                foreach ($data_pengajuan as $row): 
                                    
                                    $badge_class = 'badge-diajukan';
                                    $badge_icon = 'fa-paper-plane';
                                    switch($row['status_pengajuan']) {
                                        case 'ditinjau':
                                            $badge_class = 'badge-ditinjau';
                                            $badge_icon = 'fa-clock';
                                            break;
                                        case 'disetujui':
                                            $badge_class = 'badge-disetujui';
                                            $badge_icon = 'fa-check-circle';
                                            break;
                                        case 'ditolak':
                                            $badge_class = 'badge-ditolak';
                                            $badge_icon = 'fa-times-circle';
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td class="row-number">
                                        <?php echo $no++; ?>
                                    </td>
                                    <td>
                                        <div class="employee-info">
                                            <div class="employee-name" title="<?php echo htmlspecialchars($row['nama_lengkap']); ?>">
                                                <?php echo htmlspecialchars($row['nama_lengkap']); ?>
                                            </div>
                                            <div class="employee-email" title="<?php echo htmlspecialchars($row['email']); ?>">
                                                <i class="fas fa-envelope"></i>
                                                <?php echo htmlspecialchars($row['email']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: #475569; font-weight: 500;" title="<?php echo htmlspecialchars($row['jabatan'] ?? '-'); ?>">
                                        <div style="max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['jabatan'] ?? '-'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="jenjang-badge">
                                            <?php echo strtoupper(substr($row['jenjang_pendidikan'], 0, 2)); ?>
                                        </span>
                                    </td>
                                    <td style="color: #475569; font-weight: 500;" title="<?php echo htmlspecialchars($row['nama_institusi']); ?>">
                                        <div style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['nama_institusi']); ?>
                                        </div>
                                    </td>
                                    <td style="color: #475569; font-weight: 500;" title="<?php echo htmlspecialchars($row['program_studi']); ?>">
                                        <div style="max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['program_studi']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-info">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('d/m/y', strtotime($row['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo $badge_class; ?>">
                                            <i class="fas <?php echo $badge_icon; ?>"></i>
                                            <?php echo ucfirst($row['status_pengajuan']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-detail" onclick="detailPengajuan(<?php echo $row['pengajuan_id']; ?>)" title="Lihat Detail">
                                                <i class="fas fa-eye"></i> Detail
                                            </button>
                                            <?php if ($row['status_pengajuan'] == 'diajukan' || $row['status_pengajuan'] == 'ditinjau'): ?>
                                            <button class="btn-action btn-approve" onclick="approvePengajuan(<?php echo $row['pengajuan_id']; ?>)" title="Setujui">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn-action btn-reject" onclick="rejectPengajuan(<?php echo $row['pengajuan_id']; ?>)" title="Tolak">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($activeTab == 'pelatihan'): ?>
                <?php include 'pelatihan.php'; ?>
            <?php elseif ($activeTab == 'reward'): ?>
                <?php include 'reward.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            //  DataTable
            $('#tablePengajuan').DataTable({
                language: {
                    search: "",
                    searchPlaceholder: "Cari data...",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Menampilkan 0 - 0 dari 0 data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    zeroRecords: "Tidak ada data yang ditemukan",
                    emptyTable: "Tidak ada data tersedia",
                    paginate: {
                        first: "Pertama",
                        last: "Terakhir",
                        next: "›",
                        previous: "‹"
                    }
                },
                order: [[6, 'desc']], 
                pageLength: 10,
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: [8] },
                    { className: "text-center", targets: [0, 3, 7] }
                ],
                dom: '<"row mb-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row mt-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
        });

        // Alert handler
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            
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
                }).then(() => {
                    const tab = urlParams.get('tab') || 'pengajuan';
                    window.location.href = '?tab=' + tab;
                });
            }
        });

        function detailPengajuan(id) {
            window.location.href = 'detail_pengajuan.php?id=' + id;
        }

        function approvePengajuan(id) {
            Swal.fire({
                title: 'Setujui Pengajuan?',
                text: 'Pengajuan akan disetujui dan pegawai akan mendapatkan notifikasi',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-check"></i> Ya, Setujui',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'proses_pengajuan.php?action=approve&id=' + id;
                }
            });
        }

        function rejectPengajuan(id) {
            Swal.fire({
                title: 'Tolak Pengajuan?',
                text: 'Pengajuan akan ditolak dan pegawai akan mendapatkan notifikasi',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-times"></i> Ya, Tolak',
                cancelButtonText: 'Batal',
                input: 'textarea',
                inputPlaceholder: 'Alasan penolakan (wajib)',
                inputAttributes: {
                    'aria-label': 'Alasan penolakan'
                },
                inputValidator: (value) => {
                    if (!value) {
                        return 'Alasan penolakan harus diisi!'
                    }
                    if (value.trim().length < 10) {
                        return 'Alasan penolakan minimal 10 karakter!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'proses_pengajuan.php?action=reject&id=' + id + '&reason=' + encodeURIComponent(result.value);
                }
            });
        }
    </script>
</body>
</html>