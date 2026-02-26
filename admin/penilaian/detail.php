<?php
session_start();
require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID penilaian tidak valid.";
    header("Location: penilaianKinerja.php");
    exit;
}

$penilaian_id = (int)$_GET['id'];

// Ambil data penilaian + pegawai + template
$stmt = $conn->prepare("
    SELECT 
        pk.*,
        p.nama_lengkap,
        p.foto_path,
        sk.jabatan,
        sk.unit_kerja,
        pt.nama_template,
        pt.periode,
        u_verif.email as verified_by_email
    FROM penilaian_kinerja pk
    JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
    JOIN penilaian_template pt ON pk.template_id = pt.template_id
    LEFT JOIN users u_verif ON pk.verified_by = u_verif.user_id
    WHERE pk.penilaian_id = ?
");
$stmt->execute([$penilaian_id]);
$penilaian = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$penilaian) {
    $_SESSION['error_message'] = "Data penilaian tidak ditemukan.";
    header("Location: penilaianKinerja.php");
    exit;
}

// Ambil detail nilai per indikator
$stmt_detail = $conn->prepare("
    SELECT 
        pi.nama_indikator,
        pi.keterangan,
        pi.urutan,
        pkd.nilai
    FROM penilaian_kinerja_detail pkd
    JOIN penilaian_indikator pi ON pkd.indikator_id = pi.indikator_id
    WHERE pkd.penilaian_id = ?
    ORDER BY pi.urutan ASC
");
$stmt_detail->execute([$penilaian_id]);
$detail_list = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik skala penilaian
$statistik = [
    'Sangat Baik' => 0,
    'Baik'        => 0,
    'Cukup'       => 0,
    'Kurang'      => 0,
];
$total_indikator = count($detail_list);

foreach ($detail_list as $d) {
    if (isset($statistik[$d['nilai']])) {
        $statistik[$d['nilai']]++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_viewed'])) {
    $stmt_update = $conn->prepare("
        UPDATE penilaian_kinerja 
        SET status_verifikasi = 'sudah_dilihat',
            verified_by = ?,
            verified_at = NOW()
        WHERE penilaian_id = ?
    ");
    if ($stmt_update->execute([$_SESSION['user_id'], $penilaian_id])) {
        $_SESSION['success_message'] = "Status penilaian berhasil ditandai sebagai 'Sudah Dilihat'.";
    }
    header("Location: detail.php?id=" . $penilaian_id);
    exit;
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

$nilai_config = [
    'Sangat Baik' => ['bg' => '#10b981', 'text' => 'white', 'icon' => 'emoji-smile'],
    'Baik'        => ['bg' => '#3b82f6', 'text' => 'white', 'icon' => 'hand-thumbs-up'],
    'Cukup'       => ['bg' => '#f59e0b', 'text' => 'white', 'icon' => 'dash-circle'],
    'Kurang'      => ['bg' => '#ef4444', 'text' => 'white', 'icon' => 'emoji-frown'],
];

$statistik_config = [
    'Sangat Baik' => ['color' => '#10b981', 'light' => '#d1fae5'],
    'Baik'        => ['color' => '#3b82f6', 'light' => '#dbeafe'],
    'Cukup'       => ['color' => '#f59e0b', 'light' => '#fef3c7'],
    'Kurang'      => ['color' => '#ef4444', 'light' => '#fee2e2'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Penilaian Kinerja - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
     <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: -0.025em;
        }

        .page-header p {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 400;
        }

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            border: 1px solid;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.5);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.35);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            margin-bottom: 24px;
            border: 1px solid #f1f5f9;
        }

        .card-title {
            font-size: 17px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.025em;
        }

        /* Profile Card */
        .profile-card {
            background: linear-gradient(135deg, #e8f4f8 0%, #d8eaf2 100%);
            border: 1px solid #b8d8e6;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 28px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .profile-card-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f4c75;
            margin-bottom: 24px;
            padding-bottom: 14px;
            border-bottom: 2px solid #b8d8e6;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .profile-info-column {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .info-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            border: 1px solid rgba(184, 216, 230, 0.5);
            transition: all 0.2s;
        }

        .info-row:hover {
            background: rgba(255, 255, 255, 0.95);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .info-label {
            font-size: 11px;
            color: #0f4c75;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .jenis-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .jenis-dosen { background: #dbeafe; color: #1e40af; }
        .jenis-staff { background: #f3e8ff; color: #6b21a8; }
        .jenis-tendik { background: #fed7aa; color: #92400e; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .badge-belum {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-sudah {
            background: #d1fae5;
            color: #065f46;
        }

        /* Statistik Cards */
        .statistik-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #e8f0ff;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            font-weight: 600;
            color: #666;
        }

        .stat-card .stat-percent {
            font-size: 12px;
            margin-top: 6px;
            color: #888;
            font-weight: 500;
        }

        /* Indikator Table */
        .indikator-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .indikator-table thead tr {
            background: #f8fafc;
        }

        .indikator-table thead th {
            padding: 16px 18px;
            text-align: left;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .indikator-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s;
        }

        .indikator-table tbody tr:hover {
            background: #f8fafc;
        }

        .indikator-table tbody td {
            padding: 18px;
            font-size: 14px;
            vertical-align: middle;
        }

        .nilai-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        /* Catatan box */
        .catatan-box {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-left: 4px solid #64748b;
            border-radius: 12px;
            padding: 18px 20px;
            font-size: 14px;
            color: #475569;
            line-height: 1.6;
        }

        .no-catatan {
            color: #94a3b8;
            font-style: italic;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .statistik-grid { grid-template-columns: repeat(2, 1fr); }
            .profile-info-grid { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 992px) and (min-width: 769px) {
            .profile-info-grid { gap: 18px; }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <div>
                <h1><i class=""></i> Detail Penilaian Kinerja</h1>
                <p>
                    <?php echo htmlspecialchars($penilaian['nama_template']); ?> &mdash;
                    Periode <?php echo date('F Y', strtotime($penilaian['periode'])); ?>
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <?php if ($penilaian['status_verifikasi'] === 'belum_dilihat'): ?>
                    <form method="POST" onsubmit="return confirm('Tandai penilaian ini sebagai sudah dilihat?');">
                        <button type="submit" name="mark_viewed" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Tandai Sudah Dilihat
                        </button>
                    </form>
                <?php endif; ?>
                <a href="penilaianKinerja.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill" style="font-size: 18px;"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Card  -->
        <div class="profile-card">
            <div class="profile-card-title">
                <i class=""></i> Informasi Pegawai
            </div>
            
            <div class="profile-info-grid">
                <!-- Kolom Kiri -->
                <div class="profile-info-column">
                    <div class="info-row">
                        <div class="info-label">Nama Lengkap</div>
                        <div class="info-value"><?php echo htmlspecialchars($penilaian['nama_lengkap']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Jabatan / Posisi</div>
                        <div class="info-value"><?php echo htmlspecialchars($penilaian['jabatan'] ?? '-'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Unit Kerja / Divisi</div>
                        <div class="info-value"><?php echo htmlspecialchars($penilaian['unit_kerja'] ?? '-'); ?></div>
                    </div>
                    
                </div>
                
                <!-- Kolom Kanan -->
                <div class="profile-info-column">
                    <div class="info-row">
                        <div class="info-label">Tanggal Penilaian</div>
                        <div class="info-value"><?php echo date('d F Y', strtotime($penilaian['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Status Verifikasi</div>
                        <div class="info-value">
                            <?php if ($penilaian['status_verifikasi'] === 'sudah_dilihat'): ?>
                                <span class="status-badge badge-sudah">
                                    <i class="bi bi-check-circle-fill"></i> Sudah Dilihat
                                </span>
                            <?php else: ?>
                                <span class="status-badge badge-belum">
                                    <i class="bi bi-clock"></i> Belum Dilihat
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Waktu Verifikasi</div>
                        <div class="info-value">
                            <?php if ($penilaian['verified_at']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($penilaian['verified_at'])); ?> WIB
                            <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">Belum diverifikasi</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Penilaian Per Indikator -->
        <div class="card">
            <div class="card-title">
                <i class=""></i> Detail Penilaian Per Indikator
            </div>

            <?php if (count($detail_list) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="indikator-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>Kriteria / Indikator</th>
                                <th style="width: 200px; text-align: center;">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail_list as $index => $item):
                                $cfg = $nilai_config[$item['nilai']] ?? ['bg' => '#94a3b8', 'text' => 'white', 'icon' => 'dash'];
                            ?>
                                <tr>
                                    <td style="color: #64748b; font-weight: 700;">
                                        <?php echo $index + 1; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #0f172a; margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($item['nama_indikator']); ?>
                                        </div>
                                        <?php if (!empty($item['keterangan'])): ?>
                                            <div style="font-size: 13px; color: #64748b;">
                                                <?php echo htmlspecialchars($item['keterangan']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="nilai-badge"
                                              style="background: <?php echo $cfg['bg']; ?>; color: <?php echo $cfg['text']; ?>;">
                                            <i class="bi bi-<?php echo $cfg['icon']; ?>"></i>
                                            <?php echo htmlspecialchars($item['nilai']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #94a3b8;">
                    <i class="bi bi-inbox" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                    Belum ada data detail penilaian.
                </div>
            <?php endif; ?>
        </div>

        <!-- Statistik Ringkasan -->
        <div class="card">
            <div class="card-title">
                <i class=""></i> Ringkasan Hasil Penilaian
                <span style="margin-left: auto; font-size: 13px; font-weight: 600; color: #64748b;">
                    Total: <?php echo $total_indikator; ?> indikator
                </span>
            </div>

            <div class="statistik-grid">
                <?php foreach ($statistik as $label => $jumlah):
                    $cfg = $statistik_config[$label];
                    $persen = $total_indikator > 0 ? round(($jumlah / $total_indikator) * 100) : 0;
                ?>
                    <div class="stat-card" style="background: <?php echo $cfg['light']; ?>;">
                        <div class="stat-number" style="color: <?php echo $cfg['color']; ?>;">
                            <?php echo $jumlah; ?>
                        </div>
                        <div class="stat-label">
                            <?php echo $label; ?>
                        </div>
                        <div class="stat-percent">
                            <?php echo $persen; ?>% dari total
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Catatan Tambahan -->
        <div class="card">
            <div class="card-title">
                <i class=""></i> Catatan Tambahan
            </div>
            <?php if (!empty($penilaian['catatan'])): ?>
                <div class="catatan-box">
                    <?php echo nl2br(htmlspecialchars($penilaian['catatan'])); ?>
                </div>
            <?php else: ?>
                <p class="no-catatan">
                    <i class="bi bi-dash-circle"></i> Tidak ada catatan tambahan.
                </p>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>