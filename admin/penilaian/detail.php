<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

$penilaian_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get penilaian detail
$stmt = $conn->prepare("SELECT 
                            pk.*,
                            IFNULL(pk.status_verifikasi, 'belum_dilihat') as status_verifikasi,
                            p.nama_lengkap,
                            p.email,
                            p.jenis_pegawai,
                            sk.jabatan,
                            sk.unit_kerja,
                            pt.nama_template,
                            u_creator.email as created_by_email,
                            u_verifier.email as verified_by_email
                        FROM penilaian_kinerja pk
                        INNER JOIN pegawai p ON pk.pegawai_id = p.pegawai_id
                        LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
                        INNER JOIN penilaian_template pt ON pk.template_id = pt.template_id
                        LEFT JOIN users u_creator ON pk.created_by = u_creator.user_id
                        LEFT JOIN users u_verifier ON pk.verified_by = u_verifier.user_id
                        WHERE pk.penilaian_id = ?");
$stmt->execute([$penilaian_id]);
$penilaian = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$penilaian) {
    $_SESSION['error'] = "Penilaian tidak ditemukan!";
    header("Location: penilaianKinerja.php");
    exit();
}

// Get detail jawaban
$stmt = $conn->prepare("SELECT 
                            pj.*,
                            pi.nama_indikator,
                            pi.keterangan,
                            pi.urutan
                        FROM penilaian_jawaban pj
                        INNER JOIN penilaian_indikator pi ON pj.indikator_id = pi.indikator_id
                        WHERE pj.penilaian_id = ?
                        ORDER BY pi.urutan ASC");
$stmt->execute([$penilaian_id]);
$jawaban_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map nilai ke label
$nilai_map = [
    'sangat_baik' => ['label' => 'Sangat Baik', 'color' => 'success', 'icon' => 'emoji-smile'],
    'baik' => ['label' => 'Baik', 'color' => 'info', 'icon' => 'emoji-neutral'],
    'cukup' => ['label' => 'Cukup', 'color' => 'warning', 'icon' => 'emoji-frown'],
    'kurang' => ['label' => 'Kurang', 'color' => 'danger', 'icon' => 'emoji-frown-fill']
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Penilaian - <?= htmlspecialchars($penilaian['nama_lengkap']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .main-content { 
            margin-left: 250px; 
            padding: 20px; 
            background: #f8f9fa; 
            min-height: 100vh; 
        }
        .card { 
            border: none; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px 8px 0 0;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-value {
            color: #212529;
            font-size: 1rem;
            margin-top: 5px;
        }
        .jawaban-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .jawaban-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .nilai-badge {
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .status-verified {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-pending {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Detail Penilaian Kinerja</h2>
                <a href="penilaianKinerja.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Card -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="profile-header">
                            <div class="text-center mb-3">
                                <i class="bi bi-person-circle" style="font-size: 5rem;"></i>
                            </div>
                            <h4 class="text-center mb-0"><?= htmlspecialchars($penilaian['nama_lengkap']) ?></h4>
                            <p class="text-center mb-0 mt-2 opacity-75">
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($penilaian['email']) ?>
                            </p>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-briefcase"></i> Jabatan/Posisi
                                </div>
                                <div class="info-value">
                                    <?= htmlspecialchars($penilaian['jabatan'] ?? '-') ?>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-building"></i> Unit Kerja
                                </div>
                                <div class="info-value">
                                    <?= htmlspecialchars($penilaian['unit_kerja'] ?? '-') ?>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-person-badge"></i> Jenis Pegawai
                                </div>
                                <div class="info-value">
                                    <span class="badge bg-info">
                                        <?= strtoupper($penilaian['jenis_pegawai']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-file-earmark-text"></i> Template
                                </div>
                                <div class="info-value">
                                    <?= htmlspecialchars($penilaian['nama_template']) ?>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-calendar-event"></i> Periode
                                </div>
                                <div class="info-value">
                                    <?= date('F Y', strtotime($penilaian['periode'])) ?>
                                </div>
                            </div>

                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-clock"></i> Tanggal Isi
                                </div>
                                <div class="info-value">
                                    <?= date('d F Y, H:i', strtotime($penilaian['created_at'])) ?> WIB
                                </div>
                            </div>

                            <?php if ($penilaian['created_by_email']): ?>
                            <div class="info-box">
                                <div class="info-label">
                                    <i class="bi bi-person"></i> Diisi Oleh
                                </div>
                                <div class="info-value">
                                    <?= htmlspecialchars($penilaian['created_by_email']) ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Penilaian Detail -->
                <div class="col-md-8">
                    <!-- Status Verifikasi -->
                    <?php if ($penilaian['status_verifikasi'] === 'sudah_dilihat'): ?>
                        <div class="status-verified">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="mb-1 text-success">
                                        <i class="bi bi-check-circle-fill"></i> Penilaian Sudah Dilihat
                                    </h5>
                                    <small class="text-muted">
                                        Dilihat pada: <?= date('d F Y, H:i', strtotime($penilaian['verified_at'])) ?> WIB
                                        <?php if ($penilaian['verified_by_email']): ?>
                                            oleh <?= htmlspecialchars($penilaian['verified_by_email']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <form method="POST" action="verifikasi.php" onsubmit="return confirm('Tandai penilaian ini belum dilihat?')">
                                    <input type="hidden" name="action" value="verify">
                                    <input type="hidden" name="penilaian_id" value="<?= $penilaian_id ?>">
                                    <input type="hidden" name="status" value="belum_dilihat">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Tandai Belum Dilihat
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="status-pending">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="mb-0 text-warning">
                                        <i class="bi bi-clock-fill"></i> Penilaian Belum Dilihat
                                    </h5>
                                    <small class="text-muted">Silakan verifikasi penilaian ini</small>
                                </div>
                                <form method="POST" action="verifikasi.php" onsubmit="return confirm('Tandai penilaian ini sudah dilihat?')">
                                    <input type="hidden" name="action" value="verify">
                                    <input type="hidden" name="penilaian_id" value="<?= $penilaian_id ?>">
                                    <input type="hidden" name="status" value="sudah_dilihat">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Tandai Sudah Dilihat
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Hasil Penilaian -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-check"></i> Hasil Penilaian Kinerja
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($jawaban_list) > 0): ?>
                                <?php foreach ($jawaban_list as $index => $jawaban): 
                                    $nilai_data = $nilai_map[$jawaban['nilai']] ?? ['label' => $jawaban['nilai'], 'color' => 'secondary', 'icon' => 'circle'];
                                ?>
                                    <div class="jawaban-card">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <span class="badge bg-light text-dark me-2"><?= $index + 1 ?></span>
                                                    <?= htmlspecialchars($jawaban['nama_indikator']) ?>
                                                </h6>
                                                <?php if ($jawaban['keterangan']): ?>
                                                    <small class="text-muted">
                                                        <i class="bi bi-info-circle"></i> 
                                                        <?= htmlspecialchars($jawaban['keterangan']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <span class="nilai-badge bg-<?= $nilai_data['color'] ?> text-white">
                                                <i class="bi bi-<?= $nilai_data['icon'] ?>"></i>
                                                <?= $nilai_data['label'] ?>
                                            </span>
                                        </div>

                                        <?php if ($jawaban['catatan']): ?>
                                            <div class="mt-3 p-3 bg-light rounded">
                                                <small class="text-muted d-block mb-1">
                                                    <i class="bi bi-chat-left-text"></i> <strong>Catatan:</strong>
                                                </small>
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($jawaban['catatan'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Summary -->
                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6 class="mb-3"><i class="bi bi-bar-chart"></i> Ringkasan Penilaian</h6>
                                        <div class="row text-center">
                                            <?php
                                            $summary = [
                                                'sangat_baik' => 0,
                                                'baik' => 0,
                                                'cukup' => 0,
                                                'kurang' => 0
                                            ];
                                            foreach ($jawaban_list as $j) {
                                                if (isset($summary[$j['nilai']])) {
                                                    $summary[$j['nilai']]++;
                                                }
                                            }
                                            ?>
                                            <div class="col-3">
                                                <div class="p-3 bg-success text-white rounded">
                                                    <h3 class="mb-0"><?= $summary['sangat_baik'] ?></h3>
                                                    <small>Sangat Baik</small>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="p-3 bg-info text-white rounded">
                                                    <h3 class="mb-0"><?= $summary['baik'] ?></h3>
                                                    <small>Baik</small>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="p-3 bg-warning text-dark rounded">
                                                    <h3 class="mb-0"><?= $summary['cukup'] ?></h3>
                                                    <small>Cukup</small>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="p-3 bg-danger text-white rounded">
                                                    <h3 class="mb-0"><?= $summary['kurang'] ?></h3>
                                                    <small>Kurang</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="text-muted mt-3">Tidak ada data penilaian</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>