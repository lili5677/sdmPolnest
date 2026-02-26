<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

$lowongan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($lowongan_id == 0) {
    header('Location: lowongan_pekerjaan.php');
    exit;
}

// Query detail lowongan
$query = "
    SELECT 
        lowongan_id,
        posisi,
        formasi,
        deskripsi_pekerjaan,
        kualifikasi,
        tanggung_jawab,
        gaji_min,
        gaji_max,
        deadline_lamaran,
        status
    FROM lowongan_pekerjaan
    WHERE lowongan_id = :lowongan_id
";

$stmt = $conn->prepare($query);
$stmt->execute(['lowongan_id' => $lowongan_id]);
$lowongan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lowongan) {
    header('Location: lowongan_pekerjaan.php');
    exit;
}

// Cek apakah user sudah login
$is_logged_in = isset($_SESSION['user_id']) && 
                isset($_SESSION['user_type']) && 
                $_SESSION['user_type'] == 'pelamar';

// Parse tanggung jawab dan kualifikasi
$tanggung_jawab_items = [];
if (!empty($lowongan['tanggung_jawab'])) {
    $tanggung_jawab_items = array_filter(array_map('trim', explode("\n", $lowongan['tanggung_jawab'])));
}

$kualifikasi_items = [];
if (!empty($lowongan['kualifikasi'])) {
    $kualifikasi_items = array_filter(array_map('trim', explode("\n", $lowongan['kualifikasi'])));
}

// Format gaji
if (!empty($lowongan['gaji_min']) && !empty($lowongan['gaji_max'])) {
    $gaji_text = 'Rp ' . number_format($lowongan['gaji_min'], 0, ',', '.') . ' - ' . number_format($lowongan['gaji_max'], 0, ',', '.');
} else {
    $gaji_text = 'Gaji Kompetitif';
}

// Format deadline
$deadline = date('d F Y', strtotime($lowongan['deadline_lamaran']));

// Cek status
$is_active = strtotime($lowongan['deadline_lamaran']) >= time() && $lowongan['status'] == 'aktif';

$page_title = $lowongan['posisi'] . ' - Politeknik NEST';
include '../partials/navbar_req.php';
?>
<link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        .back-link {
            max-width: 1300px;
            margin: 15px auto 10px;
            padding: 0 5px;
        }

        .back-link a {
            color: #546e7a;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .back-link a:hover {
            color: #0d47a1;
        }

        .back-link i {
            font-size: 20px;
        }

        /* Main Content */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 5px;
        }

        .detail-wrapper {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            align-items: start;
        }

        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
        }

        .job-title {
            font-size: 32px;
            color: #0d47a1;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .action-icons {
            display: flex;
            gap: 10px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            color: #546e7a;
            font-size: 18px;
        }

        .icon-btn:hover {
            border-color: #0d47a1;
            background: #f5f5f5;
            color: #0d47a1;
        }

        .job-meta {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
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
            color: #546e7a;
        }

        .salary-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #0d47a1;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .salary-label {
            font-size: 14px;
            color: #0d47a1;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .salary-amount {
            font-size: 20px;
            color: #0d47a1;
            font-weight: 700;
        }

        /* Sticky Sidebar */
        .sidebar-sticky {
            position: sticky;
            top: 20px;
        }

        .apply-section {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border: 2px solid #4caf50;
            border-radius: 12px;
            padding: 25px;
        }

        .apply-title {
            font-size: 18px;
            color: #2e7d32;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .apply-deadline {
            font-size: 13px;
            color: #558b2f;
            margin-bottom: 10px;
        }

        .apply-status {
            display: inline-block;
            background: #66bb6a;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .apply-btn {
            background: #00897b;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .apply-btn:hover {
            background: #00796b;
        }

        .apply-btn i {
            font-size: 18px;
        }

        .contact-link {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #546e7a;
        }

        .contact-link a {
            color: #0d47a1;
            text-decoration: none;
            font-weight: 600;
        }

        .section-title {
            font-size: 20px;
            color: #0d47a1;
            font-weight: 700;
            margin-bottom: 15px;
            margin-top: 30px;
        }

        .section-content {
            color: #546e7a;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .requirements-list {
            list-style: none;
            padding-left: 0;
        }

        .requirements-list li {
            color: #546e7a;
            font-size: 14px;
            padding-left: 25px;
            margin-bottom: 10px;
            position: relative;
            line-height: 1.6;
        }

        .requirements-list li:before {
            content: "•";
            position: absolute;
            left: 8px;
            color: #0d47a1;
            font-weight: bold;
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .detail-wrapper {
                grid-template-columns: 1fr;
            }

            .sidebar-sticky {
                position: relative;
                top: 0;
            }

            .detail-card {
                padding: 25px;
            }

            .job-title {
                font-size: 24px;
            }

            .job-meta {
                flex-direction: column;
                gap: 10px;
            }

            .detail-header {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Back Button -->
    <div class="back-link">
        <a href="dashboard.php">
            <i class="bi bi-arrow-left"></i>
            Kembali ke Lowongan
        </a>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="detail-wrapper">
            <!-- Job Details -->
            <div class="detail-card">
                <div class="detail-header">
                    <div>
                        <h1 class="job-title"><?= htmlspecialchars($lowongan['posisi']) ?></h1>
                    </div>
                    <div class="action-icons">
                        <div class="icon-btn" title="Print" onclick="window.print()">
                            <i class="bi bi-printer-fill"></i>
                        </div>
                    </div>
                </div>

                <div class="job-meta">
                    <div class="meta-item">
                        <i class="bi bi-briefcase-fill"></i>
                        <span>Teknik Informatika</span>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span>Kampus Politeknik NEST</span>
                    </div>
                    <div class="meta-item">
                        <i class="bi bi-calendar-event-fill"></i>
                        <span>Deadline: <?= htmlspecialchars($deadline) ?></span>
                    </div>
                </div>

                <div class="salary-box">
                    <div class="salary-label">Gaji Ditawarkan</div>
                    <div class="salary-amount"><?= htmlspecialchars($gaji_text) ?></div>
                </div>

                <div class="section-title">Deskripsi Pekerjaan</div>
                <div class="section-content">
                    <?= nl2br(htmlspecialchars($lowongan['deskripsi_pekerjaan'])) ?>
                </div>

                <?php if (!empty($tanggung_jawab_items)): ?>
                <div class="section-title">Tanggung Jawab</div>
                <ul class="requirements-list">
                    <?php foreach ($tanggung_jawab_items as $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if (!empty($kualifikasi_items)): ?>
                <div class="section-title">Persyaratan Pelamar</div>
                <ol class="requirements-list">
                    <?php foreach ($kualifikasi_items as $index => $item): ?>
                        <li><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>

            <!-- Apply Section (Sticky) -->
            <div class="sidebar-sticky">
                <div class="apply-section">
                    <div class="apply-title">Siap untuk melamar?</div>
                    <div class="apply-deadline">Deadline: <?= htmlspecialchars($deadline) ?></div>
                    <?php if ($is_active): ?>
                        <div class="apply-status">Active</div>
                    <?php endif; ?>
                    
                    <?php if ($is_logged_in): ?>
                        <!-- Sudah login - langsung ke form_cv.php -->
                        <button class="apply-btn" onclick="window.location.href='../pelamar/form_cv.php?lowongan_id=<?= $lowongan['lowongan_id'] ?>'">
                            <i class="bi bi-send-fill"></i> Lamar Sekarang
                        </button>
                    <?php else: ?>
                        <!-- Belum login - ke login dulu -->
                        <button class="apply-btn" onclick="window.location.href='../../auth/login_pelamar.php?redirect=apply&lowongan_id=<?= $lowongan['lowongan_id'] ?>'">
                            <i class="bi bi-send-fill"></i> Lamar Sekarang
                        </button>
                    <?php endif; ?>
                    
                    <div class="contact-link">
                        Mengalami kendala? <a href="mailto:info@politekniknest.ac.id">Hubungi Kami</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
<?php include '../partials/footer.php'; ?>