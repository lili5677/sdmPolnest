<?php
// ================= KONEKSI DATABASE =================
$host = 'localhost';
$dbname = 'sdm_polnest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// ================= AMBIL DATA LOWONGAN =================
$lowongan_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM lowongan_pekerjaan WHERE lowongan_id = ?");
$stmt->execute([$lowongan_id]);
$lowongan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lowongan) {
    header('Location: index.php');
    exit;
}

// ================= JUMLAH PENDAFTAR =================
$stmt_pendaftar = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE lowongan_id = ?");
$stmt_pendaftar->execute([$lowongan_id]);
$jumlah_pendaftar = $stmt_pendaftar->fetchColumn();

// ================= FORMAT GAJI =================
if (!empty($lowongan['gaji_min']) && !empty($lowongan['gaji_max'])) {
    $gaji_range = "Rp " . number_format($lowongan['gaji_min'],0,',','.') .
                  " – Rp " . number_format($lowongan['gaji_max'],0,',','.') . " / bulan";
} else {
    $gaji_range = "Gaji dirahasiakan";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($lowongan['posisi']) ?> - Detail Lowongan</title>

    <!-- POPPINS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- BOOTSTRAP ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            color: #334155;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: calc(100% - 280px);
        }

        .detail-card {
            background: #fff;
            border-radius: 14px;
            padding: 32px;
            max-width: 900px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 32px;
        }

        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

        .meta-info {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 10px;
            font-size: 14px;
        }

        .meta-info i {
            margin-right: 6px;
            color: #3b82f6;
        }

        .salary-box {
            background: linear-gradient(135deg,#2563eb,#1e40af);
            color: #fff;
            padding: 16px 22px;
            border-radius: 10px;
            text-align: right;
            min-width: 230px;
        }

        .salary-box small {
            font-size: 12px;
            opacity: .9;
        }

        .salary-box div {
            font-size: 16px;
            font-weight: 700;
            margin-top: 4px;
        }

        .section {
            margin-bottom: 32px;
        }

        .section h2 {
            font-size: 18px;
            margin-bottom: 12px;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section p, .section li {
            font-size: 14px;
            line-height: 1.8;
        }

        ul, ol {
            margin-left: 20px;
        }

        .actions {
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-danger {
            background: #ef4444;
            color: #fff;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .detail-header {
                flex-direction: column;
            }
            .salary-box {
                text-align: left;
            }
        }
    </style>
</head>

<body>
<div class="app-container">

    <?php include '../sidebar/sidebar.php'; ?>

    <main class="main-content">

        <div class="detail-card">

            <div class="detail-header">
                <div>
                    <h1><?= htmlspecialchars($lowongan['posisi']) ?></h1>
                    <div class="meta-info">
                        <span><i class="bi bi-building"></i> Politeknik NEST</span>
                        <span><i class="bi bi-calendar-event"></i> Deadline: <?= date('d F Y', strtotime($lowongan['deadline_lamaran'])) ?></span>
                        <span><i class="bi bi-people"></i> <?= $jumlah_pendaftar ?> Pelamar</span>
                    </div>
                </div>

                <div class="salary-box">
                    <small>Gaji Ditawarkan</small>
                    <div><?= $gaji_range ?></div>
                </div>
            </div>

            <div class="section">
                <h2><i class="bi bi-file-text"></i> Deskripsi Pekerjaan</h2>
                <p><?= nl2br(htmlspecialchars($lowongan['deskripsi_pekerjaan'])) ?></p>
            </div>

            <div class="section">
                <h2><i class="bi bi-list-check"></i> Tanggung Jawab</h2>
                <ul>
                    <?php foreach (explode("\n", $lowongan['tanggung_jawab']) as $item): ?>
                        <?php if (trim($item)): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="section">
                <h2><i class="bi bi-patch-check"></i> Kualifikasi</h2>
                <ol>
                    <?php foreach (explode("\n", $lowongan['kualifikasi']) as $item): ?>
                        <?php if (trim($item)): ?>
                            <li><?= htmlspecialchars(trim($item)) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </div>

            <div class="actions">
                <a href="editloker.php?id=<?= $lowongan_id ?>" class="btn btn-primary">
                    <i class="bi bi-pencil-square"></i> Edit
                </a>
                <button onclick="hapus(<?= $lowongan_id ?>)" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Hapus
                </button>
            </div>

        </div>
    </main>
</div>

<script>
function hapus(id){
    if(confirm('Yakin hapus lowongan ini?')){
        location.href = 'deleteloker.php?id=' + id;
    }
}
</script>
</body>
</html>
