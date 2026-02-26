<?php
// Koneksi Database
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// Query untuk mendapatkan data lowongan pekerjaan
$query = "SELECT * FROM lowongan_pekerjaan ORDER BY created_at DESC";
$stmt = $conn->query($query);
$data_lowongan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk menghitung jumlah pendaftar per lowongan
$query_pendaftar = "SELECT lowongan_id, COUNT(*) as jumlah FROM lamaran GROUP BY lowongan_id";
$stmt_pendaftar = $conn->query($query_pendaftar);
$data_pendaftar = [];
while ($row = $stmt_pendaftar->fetch(PDO::FETCH_ASSOC)) {
    $data_pendaftar[$row['lowongan_id']] = $row['jumlah'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Manajemen Lowongan Kerja - Sistem SDM Polnest</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fa; color: #333; }
        .app-container { display: flex; min-height: 100vh; }

        .main-content { 
            margin-left: 280px; 
            padding: 30px; 
            flex: 1; 
            width: calc(100% - 280px);
            transition: margin-left 0.3s ease;
        }

        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left h1 { font-size: 28px; font-weight: 700; color: #1a1a1a; margin-bottom: 5px; }
        .header-left p { font-size: 14px; color: #666; }

        .btn-primary {
            background: #3b82f6; color: white; border: none; padding: 12px 24px;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.3s; text-decoration: none;
        }
        .btn-primary:hover { background: #2563eb; }

        .table-card { 
            background: white; 
            border-radius: 12px; 
            padding: 24px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* WRAPPER UNTUK TABEL SCROLL */
        .table-wrapper {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            display: block !important;
            width: 100% !important;
        }
        
        .table-wrapper::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: #3b82f6;
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }

        table { 
            width: 100%; 
            border-collapse: collapse;
            min-width: 900px;
        }
        thead { background-color: #f8fafc; }
        thead th {
            padding: 14px 16px; text-align: left; font-size: 13px; font-weight: 600;
            color: #475569; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        tbody tr { border-bottom: 1px solid #e2e8f0; transition: background 0.2s; }
        tbody tr:hover { background-color: #f8fafc; }
        tbody td { 
            padding: 16px; 
            font-size: 14px; 
            color: #334155;
            white-space: nowrap;
        }

        .badge {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 6px 12px; border-radius: 6px; font-size: 12px;
            font-weight: 600; min-width: 32px; gap: 6px;
            white-space: nowrap;
        }
        .badge-primary { background-color: #dbeafe; color: #1e40af; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }

        .action-buttons { 
            display: flex; 
            gap: 8px; 
            align-items: center;
            flex-wrap: nowrap;
        }
        .btn-icon {
            width: 32px; height: 32px; border-radius: 6px; border: none;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; font-size: 14px;
        }
        .btn-view { background-color: #dbeafe; color: #1e40af; }
        .btn-view:hover { background-color: #bfdbfe; }
        .btn-edit { background-color: #d1fae5; color: #065f46; }
        .btn-edit:hover { background-color: #a7f3d0; }
        .btn-delete { background-color: #fee2e2; color: #991b1b; }
        .btn-delete:hover { background-color: #fecaca; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
        .empty-state h3 { font-size: 18px; color: #475569; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; color: #94a3b8; margin-bottom: 24px; }

        .status-aktif { background-color: #d1fae5; color: #065f46; }
        .status-ditutup { background-color: #fee2e2; color: #991b1b; }
        .status-draft { background-color: #f1f5f9; color: #475569; }

        .text-muted { color: #94a3b8; font-style: italic; }

        /*CSS */
    
        @media (max-width: 968px) {
            .main-content { 
                margin-left: 80px !important;
                padding: 20px !important;
                width: calc(100% - 80px) !important;
            }
            
            .page-header { 
                flex-direction: column; 
                gap: 16px;
                align-items: flex-start;
            }
            
            .header-left h1 {
                font-size: 24px;
            }
            
            .header-left p {
                font-size: 13px;
            }
            
            .btn-primary {
                width: 100%;
                justify-content: center;
                padding: 10px 20px;
                font-size: 13px;
            }
            
            .table-card {
                padding: 16px;
                border-radius: 10px;
            }
            
            /* Table scroll  */
            .table-wrapper {
                overflow-x: auto !important;
            }
            
            table {
                min-width: 800px !important;
            }
            
            thead th {
                padding: 12px;
                font-size: 11px;
            }
            
            tbody td {
                padding: 12px;
                font-size: 13px;
            }
            
            .btn-icon {
                width: 28px;
                height: 28px;
                font-size: 12px;
            }
            
            .action-buttons {
                gap: 4px;
            }
            
            .badge {
                font-size: 11px;
                padding: 5px 10px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content { 
                margin-left: 70px !important;
                padding: 16px 12px !important;
                width: calc(100% - 70px) !important;
            }
            
            .header-left h1 {
                font-size: 20px;
            }
            
            .header-left p {
                font-size: 12px;
            }
            
            .btn-primary {
                font-size: 12px;
                padding: 8px 16px;
            }
            
            .table-card {
                padding: 12px;
            }
            
            table {
                min-width: 700px !important;
            }
            
            thead th {
                padding: 10px 8px;
                font-size: 10px;
            }
            
            tbody td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .btn-icon {
                width: 26px;
                height: 26px;
                font-size: 11px;
            }
            
            .badge {
                font-size: 10px;
                padding: 4px 8px;
            }
            
            .empty-state {
                padding: 40px 15px;
            }
            
            .empty-state i {
                font-size: 48px;
            }
            
            .empty-state h3 {
                font-size: 16px;
            }
            
            .empty-state p {
                font-size: 13px;
            }
        }
        
        @media (max-width: 375px) {
            .main-content {
                padding: 14px 10px !important;
            }
            
            .header-left h1 {
                font-size: 18px;
            }
            
            table {
                min-width: 650px !important;
            }
            
            thead th,
            tbody td {
                padding: 8px 6px;
                font-size: 11px;
            }
            
            .btn-icon {
                width: 24px;
                height: 24px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../sidebar/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <div class="header-left">
                    <h1>Manajemen Lowongan Kerja</h1>
                    <p>Kelola posting lowongan pekerjaan</p>
                </div>
                <a href="createloker.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Tambah Lowongan
                </a>
            </div>

            <div class="table-card">
                <?php if (empty($data_lowongan)): ?>
                    <div class="empty-state">
                        <i class="fas fa-briefcase"></i>
                        <h3>Belum Ada Lowongan</h3>
                        <p>Mulai tambahkan lowongan pekerjaan dengan klik tombol "Tambah Lowongan"</p>
                        <a href="createloker.php" class="btn-primary">
                            <i class="fas fa-plus"></i> Tambah Lowongan
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Posisi</th>
                                    <th>Kualifikasi</th>
                                    <th>Formasi</th>
                                    <th>Pendaftar</th>
                                    <th>Batas Waktu</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_lowongan as $index => $lowongan): ?>
                                    <?php
                                        $jumlah_pendaftar = $data_pendaftar[$lowongan['lowongan_id']] ?? 0;
                                        $status_class = 'status-' . strtolower($lowongan['status']);

                                        // deadline_lamaran
                                        $deadline = !empty($lowongan['deadline_lamaran'])
                                            ? date('d/m/Y', strtotime($lowongan['deadline_lamaran']))
                                            : null;
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($lowongan['posisi']) ?></strong></td>
                                        <td><?= htmlspecialchars(substr($lowongan['kualifikasi'] ?? '', 0, 50)) ?>...</td>
                                        <td>
                                            <span class="badge badge-primary"><?= $lowongan['formasi'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-gray">
                                                <i class="fas fa-users"></i> <?= $jumlah_pendaftar ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $deadline ?? '<span class="text-muted">Belum ditentukan</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($lowongan['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-icon btn-view" onclick="viewDetail(<?= $lowongan['lowongan_id'] ?>)" title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon btn-edit" onclick="editLowongan(<?= $lowongan['lowongan_id'] ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon btn-delete" onclick="deleteLowongan(<?= $lowongan['lowongan_id'] ?>)" title="Tutup">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function viewDetail(id) {
            window.location.href = 'detailloker.php?id=' + id;
        }
        function editLowongan(id) {
            window.location.href = 'editloker.php?id=' + id;
        }
        function deleteLowongan(id) {
            if (confirm('Apakah Anda yakin ingin menutup lowongan ini?\nStatus akan diubah menjadi "Ditutup".')) {
                window.location.href = 'deleteloker.php?id=' + id;
            }
        }
    </script>
</body>
</html>