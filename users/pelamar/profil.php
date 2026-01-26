<?php
require_once '../../includes/check_login.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login_pegawai.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data with pelamar details
$query = "
    SELECT u.*, p.* 
    FROM users u
    LEFT JOIN pelamar p ON u.user_id = p.user_id
    WHERE u.user_id = :user_id
";
$stmt = $conn->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get education history - sesuai struktur tabel pendidikan_pelamar
$edu_query = "SELECT * FROM pendidikan_pelamar WHERE pelamar_id = :pelamar_id ORDER BY tahun_lulus DESC";
$edu_stmt = $conn->prepare($edu_query);
$edu_stmt->execute(['pelamar_id' => $user['pelamar_id']]);
$education = $edu_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get work experience - sesuai struktur tabel pengalaman_pelamar
$work_query = "SELECT * FROM pengalaman_pelamar WHERE pelamar_id = :pelamar_id ORDER BY created_at DESC LIMIT 1";
$work_stmt = $conn->prepare($work_query);
$work_stmt->execute(['pelamar_id' => $user['pelamar_id']]);
$work_experience = $work_stmt->fetch(PDO::FETCH_ASSOC);

// Get documents
$doc_query = "SELECT * FROM dokumen_pelamar WHERE pelamar_id = :pelamar_id ORDER BY created_at DESC";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->execute(['pelamar_id' => $user['pelamar_id']]);
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate years of experience - estimasi berdasarkan data yang ada
$experience_years = 0;
if ($work_experience && !empty($work_experience['pengalaman_kerja_terakhir'])) {
    // Estimasi pengalaman berdasarkan pendidikan terakhir
    if ($user['pendidikan_terakhir'] == 'S3 Teknik Informatika') {
        $experience_years = 3;
    } elseif ($user['pendidikan_terakhir'] == 'S2' || strpos($user['pendidikan_terakhir'], 'S2') !== false) {
        $experience_years = 2;
    } else {
        $experience_years = 1;
    }
}

$page_title = 'Profil Saya - Politeknik NEST';
include '../partials/navbar_req.php';
?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <style>
        body {
            background: #f5f5f5;
        }

        /* Main Container */
        .profile-container {
            max-width: 900px;
            margin: 30px auto 30px;
            padding: 0 20px;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        /* Profile Header */
        .profile-header {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }

        .profile-info h1 {
            font-size: 28px;
            color: #1e3a5f;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            align-items: center;
            color: #546e7a;
            font-size: 14px;
        }

        .profile-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .profile-meta .dot {
            width: 6px;
            height: 6px;
            background: #0d47a1;
            border-radius: 50%;
        }

        /* Info Section */
        .info-section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 18px;
            color: #546e7a;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            gap: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #546e7a;
            font-size: 14px;
        }

        .info-item i {
            font-size: 18px;
            width: 24px;
            color: #546e7a;
        }

        /* Education Section */
        .education-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .education-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #0d47a1;
        }

        .education-degree {
            font-size: 16px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 5px;
        }

        .education-school {
            color: #546e7a;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .education-year {
            color: #9e9e9e;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .education-ipk {
            color: #0d47a1;
            font-size: 14px;
            font-weight: 600;
        }

        /* Work Experience */
        .work-item {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .work-icon {
            width: 48px;
            height: 48px;
            background: #0d47a1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .work-icon i {
            color: white;
            font-size: 24px;
        }

        .work-details h3 {
            font-size: 16px;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 5px;
        }

        .work-company {
            color: #0d47a1;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .work-period {
            color: #9e9e9e;
            font-size: 13px;
        }

        .work-description {
            color: #546e7a;
            font-size: 13px;
            margin-top: 8px;
            line-height: 1.5;
        }

        /* Documents */
        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .document-icon {
            width: 48px;
            height: 48px;
            background: #0d47a1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .document-icon i {
            color: white;
            font-size: 24px;
        }

        .document-details h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e3a5f;
            margin-bottom: 3px;
        }

        .document-meta {
            font-size: 12px;
            color: #9e9e9e;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9e9e9e;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Custom SweetAlert2 Styling */
        .swal2-popup {
            border-radius: 15px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .swal2-confirm {
            background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 30px !important;
            font-weight: 600 !important;
        }

        .swal2-cancel {
            background: #6c757d !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 30px !important;
            font-weight: 600 !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .education-grid {
                grid-template-columns: 1fr;
            }

            .profile-meta {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <div class="profile-card">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['nama_lengkap'] ?? 'Nama Lengkap') ?></h1>
                    <?php if (!empty($user['gelar']) || !empty($user['pendidikan_terakhir']) || $experience_years > 0): ?>
                    <div class="profile-meta">
                        <?php if (!empty($user['gelar']) || !empty($user['pendidikan_terakhir'])): ?>
                        <span><?= htmlspecialchars($user['gelar'] ?? $user['pendidikan_terakhir'] ?? '') ?></span>
                        <?php endif; ?>
                        
                        <?php if ((!empty($user['gelar']) || !empty($user['pendidikan_terakhir'])) && $experience_years > 0): ?>
                        <span class="dot"></span>
                        <?php endif; ?>
                        
                        <?php if ($experience_years > 0): ?>
                        <span><?= $experience_years ?> Tahun Pengalaman</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="info-section">
                <h2 class="section-title">Informasi Kontak</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <i class="bi bi-envelope-fill"></i>
                        <span><?= htmlspecialchars($user['email_aktif'] ?? $user['email']) ?></span>
                    </div>
                    <?php if (!empty($user['no_wa'])): ?>
                    <div class="info-item">
                        <i class="bi bi-whatsapp"></i>
                        <span><?= htmlspecialchars($user['no_wa']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['no_hp'])): ?>
                    <div class="info-item">
                        <i class="bi bi-telephone-fill"></i>
                        <span><?= htmlspecialchars($user['no_hp']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['tanggal_lahir'])): ?>
                    <div class="info-item">
                        <i class="bi bi-calendar-fill"></i>
                        <span><?= htmlspecialchars($user['tempat_lahir'] ?? '') ?>, <?= date('d F Y', strtotime($user['tanggal_lahir'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($user['alamat_domisili'])): ?>
                    <div class="info-item">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span><?= htmlspecialchars($user['alamat_domisili']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Education -->
            <div class="info-section">
                <h2 class="section-title">Riwayat Pendidikan</h2>
                <?php if (count($education) > 0): ?>
                <div class="education-grid">
                    <?php foreach ($education as $edu): ?>
                    <div class="education-item">
                        <div class="education-degree"><?= htmlspecialchars($edu['jenjang']) ?> - <?= htmlspecialchars($edu['program_studi']) ?></div>
                        <div class="education-school"><?= htmlspecialchars($edu['nama_universitas']) ?></div>
                        <div class="education-year">Lulus Tahun <?= htmlspecialchars($edu['tahun_lulus']) ?></div>
                        <?php if (!empty($edu['ipk'])): ?>
                        <div class="education-ipk">IPK: <?= htmlspecialchars($edu['ipk']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-mortarboard"></i>
                    <p>Belum ada data riwayat pendidikan</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Work Experience -->
            <div class="info-section">
                <h2 class="section-title">Pengalaman Kerja</h2>
                <?php if ($work_experience): ?>
                <div class="work-item">
                    <div class="work-icon">
                        <i class="bi bi-briefcase-fill"></i>
                    </div>
                    <div class="work-details">
                        <?php if (!empty($work_experience['nama_perusahaan'])): ?>
                        <h3>Pengalaman di <?= htmlspecialchars($work_experience['nama_perusahaan']) ?></h3>
                        <?php else: ?>
                        <h3>Pengalaman Kerja</h3>
                        <?php endif; ?>
                        
                        <?php if (!empty($work_experience['pengalaman_kerja_terakhir'])): ?>
                        <div class="work-description"><?= nl2br(htmlspecialchars($work_experience['pengalaman_kerja_terakhir'])) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($work_experience['pengalaman_mengajar'])): ?>
                        <div class="work-company" style="margin-top: 10px;">Pengalaman Mengajar:</div>
                        <div class="work-description"><?= nl2br(htmlspecialchars($work_experience['pengalaman_mengajar'])) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($work_experience['keahlian_utama'])): ?>
                        <div class="work-company" style="margin-top: 10px;">Keahlian Utama:</div>
                        <div class="work-description"><?= nl2br(htmlspecialchars($work_experience['keahlian_utama'])) ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($work_experience['tautan_portofolio'])): ?>
                        <div class="work-period" style="margin-top: 10px;">
                            <i class="bi bi-link-45deg"></i>
                            <a href="<?= htmlspecialchars($work_experience['tautan_portofolio']) ?>" target="_blank">Lihat Portofolio</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-briefcase"></i>
                    <p>Belum ada data pengalaman kerja</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Documents -->
            <div class="info-section">
                <h2 class="section-title">Dokumen Saya</h2>
                <?php if (count($documents) > 0): ?>
                    <?php foreach ($documents as $doc): ?>
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="bi bi-file-earmark-<?= $doc['jenis_dokumen'] == 'foto' ? 'image' : 'pdf' ?>-fill"></i>
                            </div>
                            <div class="document-details">
                                <h4><?= htmlspecialchars($doc['nama_file']) ?></h4>
                                <div class="document-meta">
                                    <?= ucfirst($doc['jenis_dokumen']) ?> • 
                                    <?= number_format($doc['ukuran_file'] / 1024, 1) ?> MB • 
                                    Diupload <?= date('d M Y', strtotime($doc['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-file-earmark"></i>
                        <p>Belum ada dokumen yang diupload</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah Anda yakin ingin keluar?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Logging out...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    window.location.href = '../../auth/logout.php';
                }
            });
        }
    </script>

<?php include '../partials/footer.php'; ?>