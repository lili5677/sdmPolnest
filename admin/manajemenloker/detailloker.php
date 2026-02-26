<?php
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

$lowongan_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM lowongan_pekerjaan WHERE lowongan_id = ?");
$stmt->execute([$lowongan_id]);
$lowongan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lowongan) {
    header('Location: manajemen-loker.php');
    exit;
}

$stmt_pendaftar = $conn->prepare("SELECT COUNT(*) as jumlah FROM lamaran WHERE lowongan_id = ?");
$stmt_pendaftar->execute([$lowongan_id]);
$jumlah_pendaftar = $stmt_pendaftar->fetchColumn();

$deadline_display = !empty($lowongan['deadline_lamaran'])
    ? date('d F Y', strtotime($lowongan['deadline_lamaran']))
    : null;

$gaji_display = null;
if (!empty($lowongan['gaji_min']) && !empty($lowongan['gaji_max'])) {
    $gaji_display = 'Rp ' . number_format($lowongan['gaji_min'], 0, ',', '.') . ' – ' . number_format($lowongan['gaji_max'], 0, ',', '.');
}

$status_class = 'status-' . strtolower($lowongan['status']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Lowongan - <?= htmlspecialchars($lowongan['posisi']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fa; color: #333; }
        .app-container { display: flex; min-height: 100vh; }
        .main-content { margin-left: 280px; padding: 30px; flex: 1; width: calc(100% - 280px); }
        .content-wrapper { max-width: 900px; margin: 0 auto; }

        .back-button { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 14px; margin-bottom: 20px; transition: color 0.2s; }
        .back-button:hover { color: #3b82f6; }

        .header-card {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white; border-radius: 14px; padding: 28px 32px;
            margin-bottom: 24px; box-shadow: 0 4px 14px rgba(59,130,246,0.35);
        }
        .header-card h1 { font-size: 24px; font-weight: 700; margin-bottom: 16px; }
        .header-meta { display: flex; flex-wrap: wrap; gap: 20px; font-size: 14px; opacity: 0.92; }
        .header-meta > div { display: flex; align-items: center; gap: 7px; }
        .header-meta i { font-size: 15px; }

        .info-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; }
        .card-header { background: #f8fafc; padding: 16px 24px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; color: #1e293b; }
        .card-header i { color: #3b82f6; font-size: 18px; }
        .card-body { padding: 24px; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-box { background: white; border-radius: 10px; padding: 18px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .stat-value { font-size: 26px; font-weight: 700; color: #3b82f6; }
        .stat-label { font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-label { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 5px; }
        .info-value { font-size: 14px; color: #1e293b; font-weight: 500; line-height: 1.5; }
        .text-muted { color: #94a3b8; font-style: italic; }

        .badge { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .status-aktif { background-color: #d1fae5; color: #065f46; }
        .status-ditutup { background-color: #fee2e2; color: #991b1b; }
        .status-draft { background-color: #f1f5f9; color: #475569; }

        .text-block { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 14px; color: #334155; line-height: 1.7; white-space: pre-wrap; }
        .text-block.empty { color: #94a3b8; font-style: italic; background: #fafafa; }

        .action-row { display: flex; gap: 12px; margin-top: 8px; }
        .btn {
            padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; border: none; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-primary { background-color: #3b82f6; color: white; }
        .btn-primary:hover { background-color: #2563eb; }
        .btn-secondary { background-color: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background-color: #e2e8f0; }
        .btn-danger { background-color: #fee2e2; color: #991b1b; }
        .btn-danger:hover { background-color: #fecaca; }

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../sidebar/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                <a href="manajemen-loker.php" class="back-button"><i class="fas fa-arrow-left"></i> Kembali ke Daftar Lowongan</a>

                <div class="header-card">
                    <h1><?= htmlspecialchars($lowongan['posisi']) ?></h1>
                    <div class="header-meta">
                        <div>
                            <i class="fas fa-circle-check"></i>
                            <span class="badge" style="color: inherit; background: rgba(255,255,255,0.2);">
                                <?= ucfirst($lowongan['status']) ?>
                            </span>
                        </div>
                        <div>
                            <i class="fas fa-users"></i>
                            <span><?= $jumlah_pendaftar ?> Pendaftar</span>
                        </div>
                        <div>
                            <i class="fas fa-calendar-days"></i>
                            <span>Deadline: <?= $deadline_display ?? 'Belum ditentukan' ?></span>
                        </div>
                    </div>
                </div>

                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-value"><?= $lowongan['formasi'] ?></div>
                        <div class="stat-label">Formasi</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= $jumlah_pendaftar ?></div>
                        <div class="stat-label">Pendaftar</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" style="font-size: 18px; color: <?= $lowongan['status'] === 'aktif' ? '#16a34a' : '#dc2626' ?>;">
                            <?= ucfirst($lowongan['status']) ?>
                        </div>
                        <div class="stat-label">Status</div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header"><i class="fas fa-briefcase"></i> Informasi Lowongan</div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Posisi</div>
                                <div class="info-value"><?= htmlspecialchars($lowongan['posisi']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Formasi</div>
                                <div class="info-value"><?= $lowongan['formasi'] ?> orang</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Rentang Gaji</div>
                                <div class="info-value"><?= $gaji_display ?? '<span class="text-muted">Dirahasiakan</span>' ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Deadline Lamaran</div>
                                <div class="info-value"><?= $deadline_display ?? '<span class="text-muted">Belum ditentukan</span>' ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value"><span class="badge <?= $status_class ?>"><?= ucfirst($lowongan['status']) ?></span></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Dibuat</div>
                                <div class="info-value"><?= $lowongan['created_at'] ? date('d F Y, H:i', strtotime($lowongan['created_at'])) : '-' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header"><i class="fas fa-file-alt"></i> Deskripsi Pekerjaan</div>
                    <div class="card-body">
                        <?php $deskripsi = trim($lowongan['deskripsi_pekerjaan'] ?? ''); ?>
                        <div class="text-block <?= $deskripsi === '' ? 'empty' : '' ?>">
                            <?= $deskripsi !== '' ? htmlspecialchars($deskripsi) : 'Deskripsi pekerjaan belum diisi.' ?>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header"><i class="fas fa-list-check"></i> Tanggung Jawab</div>
                    <div class="card-body">
                        <?php $tangjawab = trim($lowongan['tanggung_jawab'] ?? ''); ?>
                        <div class="text-block <?= $tangjawab === '' ? 'empty' : '' ?>">
                            <?= $tangjawab !== '' ? htmlspecialchars($tangjawab) : 'Tanggung jawab belum diisi.' ?>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="card-header"><i class="fas fa-user-check"></i> Kualifikasi</div>
                    <div class="card-body">
                        <?php $kualifikasi = trim($lowongan['kualifikasi'] ?? ''); ?>
                        <div class="text-block <?= $kualifikasi === '' ? 'empty' : '' ?>">
                            <?= $kualifikasi !== '' ? htmlspecialchars($kualifikasi) : 'Kualifikasi belum diisi.' ?>
                        </div>
                    </div>
                </div>

                <div class="action-row">
                    <a href="editloker.php?id=<?= $lowongan_id ?>" class="btn btn-primary">
                        <i class="fas fa-pen"></i> Edit Lowongan
                    </a>
                    <button class="btn btn-danger" onclick="hapus(<?= $lowongan_id ?>)">
                        <i class="fas fa-trash"></i> Tutup Lowongan
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        function hapus(id) {
            if (confirm('Apakah Anda yakin ingin menutup lowongan ini?\nStatus akan diubah menjadi "Ditutup".')) {
                window.location.href = 'deleteloker.php?id=' + id;
            }
        }
    </script>
</body>
</html>