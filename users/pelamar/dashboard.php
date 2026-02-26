<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CEK STATUS LOGIN
$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'pelamar';

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $email = $_SESSION['email'];
    $username = explode('@', $email)[0];
} else {
    $username = 'Guest';
    $email = '';
}

// Database
require_once '../../config/database.php';

try {
    $conn->exec("
        UPDATE lowongan_pekerjaan lp
        LEFT JOIN (
            SELECT lowongan_id, COUNT(*) as total
            FROM lamaran
            WHERE status_lamaran = 'diterima'
            GROUP BY lowongan_id
        ) l ON lp.lowongan_id = l.lowongan_id
        SET lp.jumlah_diterima = COALESCE(l.total, 0)
    ");
    
    $conn->exec("
        UPDATE lowongan_pekerjaan
        SET status = 'ditutup'
        WHERE status = 'aktif'
        AND (
            deadline_lamaran < CURDATE()
            OR jumlah_diterima >= formasi
            OR is_active = 0
        )
    ");
} catch (Exception $e) {
}

// Get lowongan HANYA yang aktif
$query = "SELECT *, 
          (jumlah_diterima >= formasi) as is_full,
          (deadline_lamaran < CURDATE()) as is_expired
          FROM lowongan_pekerjaan 
          WHERE status = 'aktif' 
          AND deadline_lamaran >= CURDATE()
          AND jumlah_diterima < formasi
          AND is_active = 1
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$lowongan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_applications = [];
if ($is_logged_in) {
    // Get pelamar_id dari user_id
    $pelamar_query = "SELECT pelamar_id FROM pelamar WHERE user_id = :user_id";
    $pelamar_stmt = $conn->prepare($pelamar_query);
    $pelamar_stmt->execute(['user_id' => $user_id]);
    $pelamar_data = $pelamar_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pelamar_data) {
        $pelamar_id = $pelamar_data['pelamar_id'];
        
        // Get semua lamaran dari pelamar_id
        $app_query = "SELECT lowongan_id FROM lamaran WHERE pelamar_id = :pelamar_id";
        $app_stmt = $conn->prepare($app_query);
        $app_stmt->execute(['pelamar_id' => $pelamar_id]);
        $applications = $app_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($applications as $app) {
            $user_applications[] = $app['lowongan_id'];
        }
    }
}

$page_title = 'Dashboard - Politeknik NEST';
include '../partials/navbar_req.php';
?>
<link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    * {
        font-family: 'Poppins', sans-serif;
    }

    body {
        background: #f5f5f5;
        margin: 0;
        padding: 0;
    }
    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }
    .welcome-card {
        background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
        color: white;
        padding: 40px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .welcome-title {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .welcome-subtitle {
        font-size: 16px;
        opacity: 0.9;
    }
    .user-badge {
        display: inline-block;
        background: rgba(255,255,255,0.2);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 13px;
        margin-top: 10px;
    }
    .guest-cta {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn-login-guest {
        background: white;
        color: #0d47a1;
        padding: 10px 25px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-login-guest:hover {
        background: rgba(255,255,255,0.9);
        transform: translateY(-2px);
    }
    .page-title {
        font-size: 28px;
        color: #0d47a1;
        font-weight: 700;
        margin-bottom: 20px;
    }
    .job-card {
        background: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: all 0.3s;
        display: flex;
        flex-direction: column;
    }
    .job-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .job-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    .job-title {
        font-size: 24px;
        color: #0d47a1;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .job-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .job-badge {
        background: #4caf50;
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .job-badge.applied {
        background: #ff9800;
    }
    .job-badge.warning {
        background: #ff5722;
    }
    .job-badge.urgent {
        background: #f44336;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    .job-meta {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 20px;
    }
    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: #546e7a;
    }
    .meta-item i {
        font-size: 18px;
    }
    .meta-item.highlight {
        color: #f44336;
        font-weight: 600;
    }
    .job-desc {
        color: #546e7a;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 20px;
        flex: 1;
    }
    .job-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        margin: 0 -10px -10px -10px;
    }
    .btn {
        padding: 12px 28px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .btn-detail {
        background: white;
        color: #0d47a1;
        border: 2px solid #0d47a1;
    }
    .btn-detail:hover {
        background: #0d47a1;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 71, 161, 0.3);
    }
    .btn-apply {
        background: #00897b;
        color: white;
        border: 2px solid #00897b;
    }
    .btn-apply:hover {
        background: #00796b;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 137, 123, 0.3);
    }
    .btn-apply:disabled {
        background: #ccc;
        border-color: #ccc;
        cursor: not-allowed;
        box-shadow: none;
    }
    .btn-apply:disabled:hover {
        transform: none;
    }
    .btn-login-required {
        background: #f44336;
        color: white;
        border: 2px solid #f44336;
    }
    .btn-login-required:hover {
        background: #d32f2f;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #546e7a;
    }
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }
    .alert-info {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .alert-info i {
        font-size: 24px;
        color: #2196f3;
    }
    @media (max-width: 768px) {
        .job-meta {
            grid-template-columns: 1fr;
        }
        .job-actions {
            flex-direction: column;
            margin: 0 -10px -10px -10px;
        }
        .btn {
            text-align: center;
            justify-content: center;
            width: 100%;
        }
        .guest-cta {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="welcome-card">
            <?php if ($is_logged_in): ?>
                <h1 class="welcome-title">Selamat Datang, <?= htmlspecialchars(ucfirst($username)) ?>!</h1>
                <p class="welcome-subtitle">Temukan peluang karir terbaik di Politeknik NEST</p>
                <span class="user-badge">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($email) ?>
                </span>
            <?php else: ?>
                <h1 class="welcome-title">Selamat Datang di Portal Karir NEST!</h1>
                <p class="welcome-subtitle">Jelajahi lowongan pekerjaan terbaru kami. Login untuk melamar pekerjaan.</p>
                <div class="guest-cta">
                    <a href="../../auth/login_pelamar.php" class="btn-login-guest">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </a>
                    <a href="../../auth/register_pelamar.php" class="btn-login-guest">
                        <i class="bi bi-person-plus-fill"></i> Daftar Sekarang
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Lowongan List -->
        <h2 class="page-title">Lowongan Pekerjaan Tersedia</h2>

        <?php if (count($lowongan_list) > 0): ?>
            <?php foreach ($lowongan_list as $lowongan): ?>
                <?php
                // Gaji
                $gaji_text = 'Gaji Kompetitif';
                if (!empty($lowongan['gaji_min']) && !empty($lowongan['gaji_max'])) {
                    $gaji_text = 'Rp ' . number_format($lowongan['gaji_min'], 0, ',', '.') . ' - Rp ' . number_format($lowongan['gaji_max'], 0, ',', '.');
                }
                
                // Deadline
                $deadline = date('d F Y', strtotime($lowongan['deadline_lamaran']));
                $days_left = floor((strtotime($lowongan['deadline_lamaran']) - time()) / (60 * 60 * 24));
                
                // Check if already applied
                $already_applied = in_array($lowongan['lowongan_id'], $user_applications);
                
                // Sisa slot
                $sisa_slot = $lowongan['formasi'] - $lowongan['jumlah_diterima'];
                $is_almost_full = $sisa_slot <= 2 && $sisa_slot > 0;
                ?>
                
                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <h3 class="job-title"><?= htmlspecialchars($lowongan['posisi']) ?></h3>
                        </div>
                        <div class="job-badges">
                            <?php if ($already_applied): ?>
                                <span class="job-badge applied">Sudah Melamar</span>
                            <?php endif; ?>
                            
                            <?php if ($is_almost_full): ?>
                                <span class="job-badge warning">
                                    <i class="bi bi-exclamation-triangle-fill"></i> 
                                    Tinggal <?= $sisa_slot ?> Slot
                                </span>
                            <?php endif; ?>
                            
                            <?php if ($days_left <= 3): ?>
                                <span class="job-badge urgent">
                                    <i class="bi bi-clock-fill"></i> 
                                    <?= $days_left ?> Hari Lagi!
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="job-meta">
                        <div class="meta-item">
                            <i class="bi bi-people-fill"></i>
                            <span><?= $lowongan['formasi'] ?> Posisi (<?= $sisa_slot ?> tersisa)</span>
                        </div>
                        <div class="meta-item">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>Kampus NEST</span>
                        </div>
                        <div class="meta-item">
                            <i class="bi bi-cash-stack"></i>
                            <span><?= $gaji_text ?></span>
                        </div>
                        <div class="meta-item <?= $days_left <= 3 ? 'highlight' : '' ?>">
                            <i class="bi bi-calendar-event-fill"></i>
                            <span><?= $deadline ?></span>
                        </div>
                    </div>

                    <div class="job-desc">
                        <?= nl2br(htmlspecialchars(substr($lowongan['deskripsi_pekerjaan'], 0, 200))) ?>...
                    </div>

                    <div class="job-actions">
                        <a href="detail_lowongan.php?id=<?= $lowongan['lowongan_id'] ?>" class="btn btn-detail">
                            <i class="bi bi-eye-fill"></i> Lihat Detail
                        </a>
                        
                        <?php if (!$is_logged_in): ?>
                            <!-- Guest: Harus login dulu -->
                            <a href="../../auth/login_pelamar.php?redirect=lowongan&lowongan_id=<?= $lowongan['lowongan_id'] ?>" class="btn btn-login-required">
                                <i class="bi bi-lock-fill"></i> Login untuk Melamar
                            </a>
                        <?php elseif ($already_applied): ?>
                            <!-- Sudah melamar -->
                            <button class="btn btn-apply" disabled>
                                <i class="bi bi-check-circle-fill"></i> Sudah Melamar
                            </button>
                        <?php else: ?>
                            <!-- Bisa melamar -->
                            <a href="form_cv.php?lowongan_id=<?= $lowongan['lowongan_id'] ?>" class="btn btn-apply">
                                <i class="bi bi-send-fill"></i> Lamar Sekarang
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="job-card">
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p><strong>Belum ada lowongan tersedia saat ini.</strong></p>
                    <p style="font-size: 14px; color: #94a3b8; margin-top: 10px;">
                        Lowongan yang sudah melewati deadline atau formasi penuh tidak ditampilkan.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php include '../partials/footer.php'; ?>