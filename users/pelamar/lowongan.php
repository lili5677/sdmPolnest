<?php
// Include konfigurasi database
require_once '../../config/database.php';

// Query untuk mengambil data lowongan pekerjaan
$query = "
    SELECT 
        lowongan_id,
        posisi,
        formasi,
        deskripsi_pekerjaan,
        kualifikasi,
        gaji_min,
        gaji_max,
        deadline_lamaran,
        status
    FROM lowongan_pekerjaan
    WHERE status = 'aktif'
    ORDER BY created_at DESC
";

$stmt = $conn->query($query);
$lowongan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Lowongan Pekerjaan - Politeknik NEST';
include '../partials/navbar_req.php';
?>
    <style>
        /* Main Content */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-title {
            font-size: 36px;
            color: #0d47a1;
            margin-bottom: 20px;
            font-weight: 700;
            text-align: center;
        }

        /* Info Alert */
        .info-alert {
            background: #e0f7fa;
            border: 2px solid #00acc1;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #00838f;
            font-size: 14px;
        }

        .info-alert i {
            font-size: 24px;
            color: #00acc1;
        }

        /* Job Card */
        .job-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: relative;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .job-card.closed {
            opacity: 0.7;
        }

        .closed-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #ef5350;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .job-title {
            font-size: 24px;
            color: #0d47a1;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .job-description {
            color: #546e7a;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .job-meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #546e7a;
        }

        .meta-item i {
            font-size: 16px;
            color: #546e7a;
        }

        .requirements-title {
            font-size: 14px;
            font-weight: 600;
            color: #0d47a1;
            margin-bottom: 10px;
        }

        .requirements-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 20px;
        }

        .requirements-list li {
            color: #546e7a;
            font-size: 13px;
            padding-left: 20px;
            margin-bottom: 5px;
            position: relative;
        }

        .requirements-list li:before {
            content: "•";
            position: absolute;
            left: 5px;
            color: #0d47a1;
            font-weight: bold;
        }

        .job-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn i {
            font-size: 16px;
        }

        .btn-detail {
            background: #0d47a1;
            color: white;
        }

        .btn-detail:hover {
            background: #0b3d91;
        }

        .btn-apply {
            background: #00897b;
            color: white;
        }

        .btn-apply:hover {
            background: #00796b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .job-meta {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .job-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Main Content -->
    <div class="container">
        <h1 class="page-title">Lowongan Pekerjaan</h1>

        <!-- Info Alert -->
        <div class="info-alert">
            <i class="bi bi-info-circle-fill"></i>
            <span>Anda perlu login untuk melamar pekerjaan</span>
        </div>

        <!-- Job Cards -->
        <?php if (!empty($lowongan_list)): ?>
            <?php foreach ($lowongan_list as $lowongan): ?>
                <?php
                // Parse kualifikasi menjadi array
                $kualifikasi_items = [];
                if (!empty($lowongan['kualifikasi'])) {
                    $kualifikasi_items = array_filter(array_map('trim', explode("\n", $lowongan['kualifikasi'])));
                    if (empty($kualifikasi_items)) {
                        $kualifikasi_items = array_filter(array_map('trim', explode("•", $lowongan['kualifikasi'])));
                    }
                }
                
                // Format gaji
                $gaji_text = 'Rp ' . number_format($lowongan['gaji_min'], 0, ',', '.') . ' - ' . number_format($lowongan['gaji_max'], 0, ',', '.');
                
                // Format deadline
                $deadline = date('d F Y', strtotime($lowongan['deadline_lamaran']));
                
                // Cek status
                $is_closed = strtotime($lowongan['deadline_lamaran']) < time() || $lowongan['status'] != 'aktif';
                ?>
                
                <div class="job-card <?= $is_closed ? 'closed' : '' ?>">
                    <?php if ($is_closed): ?>
                        <div class="closed-badge">Closed</div>
                    <?php endif; ?>
                    
                    <h2 class="job-title"><?= htmlspecialchars($lowongan['posisi']) ?></h2>
                    <p class="job-description">
                        <?= htmlspecialchars($lowongan['deskripsi_pekerjaan']) ?>
                    </p>

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
                            <i class="bi bi-cash-stack"></i>
                            <span><?= htmlspecialchars($gaji_text) ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="bi bi-calendar-event-fill"></i>
                            <span>Deadline: <?= htmlspecialchars($deadline) ?></span>
                        </div>
                    </div>

                    <div class="requirements-title">Persyaratan:</div>
                    <ol class="requirements-list">
                        <?php if (!empty($kualifikasi_items)): ?>
                            <?php foreach (array_slice($kualifikasi_items, 0, 4) as $item): ?>
                                <li><?= htmlspecialchars($item) ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>Minimal S2 Teknik Informatika atau bidang terkait</li>
                            <li>Pengalaman mengajar minimal 2 tahun</li>
                            <li>Menguasai bahasa pemrograman modern</li>
                            <li>Memiliki publikasi ilmiah (nilai plus)</li>
                        <?php endif; ?>
                    </ol>

                    <div class="job-actions">
                        <a href="detail_lowongan.php?id=<?= $lowongan['lowongan_id'] ?>" class="btn btn-detail">
                            <i class="bi bi-eye-fill"></i> Lihat Detail
                        </a>
                        <?php if (!$is_closed): ?>
                            <a href="../../auth/login_pegawai.php?redirect=lowongan&id=<?= $lowongan['lowongan_id'] ?>" class="btn btn-apply">
                                <i class="bi bi-send-fill"></i> Lamar Sekarang
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="job-card">
                <p style="text-align: center; color: #546e7a; padding: 40px 0;">
                    <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                    Belum ada lowongan pekerjaan yang tersedia saat ini.
                </p>
            </div>
        <?php endif; ?>
    </div>

<?php include '../partials/footer.php'; ?>