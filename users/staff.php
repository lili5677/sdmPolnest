<?php
// Include konfigurasi database
require_once '../config/database.php';

// Query untuk mengambil data dari struktur_organisasi dan pegawai
$query = "
    SELECT 
        so.struktur_id,
        so.jabatan_struktur,
        so.level_struktur,
        p.nama_lengkap,
        p.foto_path
    FROM struktur_organisasi so
    JOIN pegawai p ON so.pegawai_id = p.pegawai_id
    ORDER BY so.level_struktur ASC, so.struktur_id ASC
";

$stmt = $conn->query($query);
$staff_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan data berdasarkan level
$staff_by_level = [
    1 => [], // Top level (grey)
    2 => [], // Middle level (pink)
    3 => []  // Lower level (orange)
];

foreach ($staff_data as $staff) {
    $level = $staff['level_struktur'];
    if (isset($staff_by_level[$level])) {
        $staff_by_level[$level][] = $staff;
    }
}
$page_title = 'Staff Berpengalaman - Politeknik NEST';
include 'partials/navbar.php';
?>
    <style>
        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .page-title {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 50px;
        }

        /* Staff Cards Container */
        .staff-section {
            margin-bottom: 60px;
        }

        .staff-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        .staff-row.top-level {
            grid-template-columns: 1fr;
            justify-items: center;
        }

        /* Staff Card */
        .staff-card {
            background: white;
            border-radius: 15px;
            padding: 0;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            overflow: hidden;
        }

        .staff-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .staff-photo-container {
            width: 100%;
            height: 220px;
            border-radius: 15px 15px 0 0;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        /* Level Colors */
        .staff-card.level-1 .staff-photo-container {
            background: linear-gradient(135deg, #b8b8b8 0%, #8e8e8e 100%);
        }

        .staff-card.level-2 .staff-photo-container {
            background: linear-gradient(135deg, #ffc0d3 0%, #ff8fab 100%);
        }

        .staff-card.level-3 .staff-photo-container {
            background: linear-gradient(135deg, #ffd4a3 0%, #ffb366 100%);
        }

        .staff-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
        }

        .staff-info {
            padding: 20px;
            background: white;
        }

        .staff-name {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .staff-position {
            font-size: 13px;
            color: #7f8c8d;
            line-height: 1.4;
        }

        .staff-expertise {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 5px;
            font-style: italic;
        }

        /* Top Level Special Style */
        .staff-row.top-level .staff-card {
            max-width: 350px;
        }

        .staff-row.top-level .staff-photo-container {
            height: 280px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .staff-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .staff-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            .staff-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Main Content -->
    <div class="container">
        <h1 class="page-title">Staf Berpengalaman</h1>
        <p class="page-subtitle">
            Staff kami berpengalaman sesuai bidang masing-masing seperti kepegawaian, keuangan,<br>
            organisasi, peraturan perundang-undangan dan teknologi informasi.
        </p>

        <!-- Top Level Staff (Grey) -->
        <?php if (!empty($staff_by_level[1])): ?>
        <div class="staff-section">
            <div class="staff-row top-level">
                <?php foreach ($staff_by_level[1] as $staff): ?>
                <div class="staff-card level-1">
                    <div class="staff-photo-container">
                        <?php if (!empty($staff['foto_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $staff['foto_path'])): ?>
                            <img src="<?= htmlspecialchars($staff['foto_path']) ?>" alt="<?= htmlspecialchars($staff['nama_lengkap']) ?>" class="staff-photo">
                        <?php else: ?>
                            <img src="../assets/images/default-avatar.png" alt="Default Photo" class="staff-photo">
                        <?php endif; ?>
                    </div>
                    <div class="staff-info">
                        <div class="staff-name"><?= htmlspecialchars($staff['nama_lengkap']) ?></div>
                        <div class="staff-position"><?= htmlspecialchars($staff['jabatan_struktur']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="staff-section">
            <div class="staff-row top-level">
                <div class="staff-card level-1">
                    <div class="staff-photo-container">
                        <img src="../assets/images/default-avatar.png" alt="Staff Photo" class="staff-photo">
                    </div>
                    <div class="staff-info">
                        <div class="staff-name">&nbsp;</div>
                        <div class="staff-position">&nbsp;</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Middle Level Staff (Pink) -->
        <div class="staff-section">
            <div class="staff-row">
                <?php if (!empty($staff_by_level[2])): ?>
                    <?php foreach ($staff_by_level[2] as $staff): ?>
                    <div class="staff-card level-2">
                        <div class="staff-photo-container">
                            <?php if (!empty($staff['foto_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $staff['foto_path'])): ?>
                                <img src="<?= htmlspecialchars($staff['foto_path']) ?>" alt="<?= htmlspecialchars($staff['nama_lengkap']) ?>" class="staff-photo">
                            <?php else: ?>
                                <img src="../assets/images/default-avatar.png" alt="Default Photo" class="staff-photo">
                            <?php endif; ?>
                        </div>
                        <div class="staff-info">
                            <div class="staff-name"><?= htmlspecialchars($staff['nama_lengkap']) ?></div>
                            <div class="staff-position"><?= htmlspecialchars($staff['jabatan_struktur']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="staff-card level-2">
                        <div class="staff-photo-container">
                            <img src="../assets/images/default-avatar.png" alt="Staff Photo" class="staff-photo">
                        </div>
                        <div class="staff-info">
                            <div class="staff-name">&nbsp;</div>
                            <div class="staff-position">&nbsp;</div>
                        </div>
                    </div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lower Level Staff (Orange) -->
        <div class="staff-section">
            <div class="staff-row">
                <?php if (!empty($staff_by_level[3])): ?>
                    <?php foreach ($staff_by_level[3] as $staff): ?>
                    <div class="staff-card level-3">
                        <div class="staff-photo-container">
                            <?php if (!empty($staff['foto_path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $staff['foto_path'])): ?>
                                <img src="<?= htmlspecialchars($staff['foto_path']) ?>" alt="<?= htmlspecialchars($staff['nama_lengkap']) ?>" class="staff-photo">
                            <?php else: ?>
                                <img src="../assets/images/default-avatar.png" alt="Default Photo" class="staff-photo">
                            <?php endif; ?>
                        </div>
                        <div class="staff-info">
                            <div class="staff-name"><?= htmlspecialchars($staff['nama_lengkap']) ?></div>
                            <div class="staff-position"><?= htmlspecialchars($staff['jabatan_struktur']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="staff-card level-3">
                        <div class="staff-photo-container">
                            <img src="../assets/images/default-avatar.png" alt="Staff Photo" class="staff-photo">
                        </div>
                        <div class="staff-info">
                            <div class="staff-name">&nbsp;</div>
                            <div class="staff-position">&nbsp;</div>
                        </div>
                    </div>
                    <?php endfor; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'partials/footer.php'; ?>