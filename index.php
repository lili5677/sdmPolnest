<?php
require_once 'config/database.php';

// user login
$is_logged_in = isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Cek pegawai dan mengambil jenis pegawai
$is_pegawai = false;
$jenis_pegawai = null;

// data pegawai
$data_complete = true;
$completion_message = '';

if ($is_logged_in && $user_id) {
    try {
        $stmt = $conn->prepare("SELECT jenis_pegawai, pegawai_id FROM pegawai WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $pegawai_data = $stmt->fetch();
        
        if ($pegawai_data) {
            $is_pegawai = true;
            $jenis_pegawai = $pegawai_data['jenis_pegawai'];
            
            require_once __DIR__ . '/config/check_completion.php';
            
            $check_result = checkPegawaiCompletion($conn, $pegawai_data['pegawai_id']);
            $data_complete = $check_result['is_complete'];
            $completion_message = $check_result['message'];
        }
    } catch (PDOException $e) {
        $is_pegawai = false;
    }
}

// ambil pelatihan dari db
try {
    $stmt = $conn->query("SELECT pelatihan_id, judul_pelatihan, deskripsi, tanggal_mulai, tanggal_selesai, lokasi, instruktur, flyer, undangan, created_at FROM pelatihan ORDER BY created_at DESC LIMIT 6");
    $pelatihan_data = $stmt->fetchAll();
    
    $stmt = $conn->query("
        SELECT 
            r.reward_id,
            r.judul_reward,
            r.deskripsi,
            r.tanggal_reward,
            r.kategori,
            r.file_bukti,
            r.created_at,
            p.nama_lengkap,
            p.jenis_pegawai,
            p.prodi,
            p.foto_path
        FROM reward_pegawai r
        LEFT JOIN pegawai p ON r.pegawai_id = p.pegawai_id
        ORDER BY r.created_at DESC 
        LIMIT 6
    ");
    $reward_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $pelatihan_data = [];
    $reward_data = [];
}

$flash = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
if ($flash) {
    unset($_SESSION['flash_message']);
}

function formatTanggal($date) {
    if (empty($date)) return '-';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = date('d', strtotime($date));
    $m = date('n', strtotime($date));
    $y = date('Y', strtotime($date));
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}

function formatRangeTanggal($tanggal_mulai, $tanggal_selesai) {
    if (empty($tanggal_mulai)) return '-';
    
    $tgl_mulai = formatTanggal($tanggal_mulai);
    
    if (empty($tanggal_selesai) || $tanggal_mulai == $tanggal_selesai) {
        return $tgl_mulai;
    }
    
    $tgl_selesai = formatTanggal($tanggal_selesai);
    return $tgl_mulai . ' - ' . $tgl_selesai;
}

$services = [];
if ($is_pegawai) {
    if ($jenis_pegawai === 'dosen') {
        $services = [
            [
                'title' => 'Layanan Administrasi Kepegawaian',
                'image' => 'users/assets/layanan/administrasi.png',
                'link' => BASE_URL . 'users/pegawai/administrasi.php',
                'locked' => false
            ],
            [
                'title' => 'Pengembangan SDM',
                'image' => 'users/assets/layanan/sdm.png', 
                'link' => BASE_URL . 'users/pegawai/pengembangan_sdm.php',
                'locked' => !$data_complete
            ],
            [
                'title' => 'Sertifikasi Dosen',
                'image' => 'users/assets/layanan/sertifikasi.png', 
                'link' => BASE_URL . 'users/pegawai/sertifikasi_dosen.php',
                'locked' => !$data_complete
            ],
            [
                'title' => 'Penilaian & Kinerja Pegawai',
                'image' => 'users/assets/layanan/evaluasi.png',
                'link' => BASE_URL . 'users/pegawai/penilaian/penilaian_kinerja.php',
                'locked' => !$data_complete
            ]
        ];
    } elseif ($jenis_pegawai === 'tendik' || $jenis_pegawai === 'staff') {
        $services = [
            [
                'title' => 'Layanan Administrasi Kepegawaian',
                'image' => 'users/assets/layanan/administrasi.png', 
                'link' => BASE_URL . 'users/pegawai/administrasi.php',
                'locked' => false
            ],
            [
                'title' => 'Pengembangan SDM',
                'image' => 'users/assets/layanan/sdm.png', 
                'link' => BASE_URL . 'users/pegawai/pengembangan.php',
                'locked' => !$data_complete
            ],
            [
                'title' => 'Penilaian & Kinerja Pegawai',
                'image' => 'users/assets/layanan/evaluasi.png',
                'link' => BASE_URL . 'users/pegawai/penilaian.php',
                'locked' => !$data_complete
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Politeknik Nest</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
    <style>
        .hero-section {
            background: url('<?php echo BASE_URL; ?>users/assets/dashboard.png') center center/cover no-repeat;
            min-height: 750px;
            display: flex;
            align-items: center;
            padding-top: 80px;
            padding-bottom: 80px;
            position: relative;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        }
        
        .hero-section .container {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            text-shadow: 2px 2px 6px rgba(0,0,0,0.6);
            font-size: clamp(1.5rem, 4vw + 0.5rem, 3rem);
            line-height: 1.3;
        }

        @media (min-width: 1400px) { .hero-section { min-height: 750px; } }
        @media (max-width: 1199px) { .hero-section { min-height: 550px; } }
        @media (max-width: 991px)  { .hero-section { min-height: 500px; padding-top: 60px; padding-bottom: 60px; } }
        @media (max-width: 767px)  { .hero-section { min-height: 450px; padding-top: 50px; padding-bottom: 50px; background-position: center center; } }
        @media (max-width: 575px)  { .hero-section { min-height: 400px; padding-top: 40px; padding-bottom: 40px; } }
        @media (max-width: 400px)  { .hero-section { min-height: 350px; padding-top: 30px; padding-bottom: 30px; } }

        .pelatihan-section { background-color: #F5F7FA; }
        
        .services-section {
            background-color: #F5F7FA;
            padding: 80px 0;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e3a5f;
            margin-bottom: 15px;
            text-align: center;
        }

        .section-subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 50px;
            font-size: 1.1rem;
        }

        .service-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: all 0.4s ease;
            height: 300px;
            cursor: pointer;
            text-decoration: none;
            display: block;
            position: relative;
        }

        .service-card.locked { opacity: 0.8; cursor: not-allowed; }

        .service-card:hover:not(.locked) {
            transform: translateY(-15px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }

        .service-card-image {
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .service-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .service-card:hover:not(.locked) .service-card-image img { transform: scale(1.15); }

        .service-card-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 35px 25px;
            transition: background-color 0.3s ease;
        }

        .service-card:hover:not(.locked) .service-card-overlay {
            background: linear-gradient(to bottom, rgba(0,0,0,0.5) 0%, rgba(0,0,0,0.85) 100%);
        }

        .service-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-align: center;
            line-height: 1.4;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.7);
            transition: transform 0.3s ease;
        }

        .service-card:hover:not(.locked) .service-card-title { transform: scale(1.05); }

        @media (max-width: 991px) { .service-card { height: 350px; } .service-card-title { font-size: 1.2rem; } }
        @media (max-width: 767px) { .service-card { height: 300px; } .service-card-title { font-size: 1.1rem; } }
        
        .carousel-container {
            position: relative;
            padding: 0 60px;
            margin: 0 auto;
            max-width: 1400px;
        }
        
        .carousel-wrapper {
            overflow: hidden;
            padding: 10px 0;
        }
        
        .carousel-track {
            display: flex;
            gap: 24px;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .card-slide {
            flex: 0 0 calc(33.333% - 16px);
            min-width: 0;
        }
        
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .carousel-nav:hover:not(:disabled) {
            background: #667eea;
            color: white;
            transform: translateY(-50%) scale(1.1);
        }
        
        .carousel-nav:disabled { opacity: 0.3; cursor: not-allowed; }
        .carousel-nav-prev { left: 0; }
        .carousel-nav-next { right: 0; }
        
        @media (max-width: 1024px) {
            .card-slide { flex: 0 0 calc(50% - 12px); }
            .carousel-container { padding: 0 50px; }
        }
        
        @media (max-width: 768px) {
            .card-slide { flex: 0 0 100%; }
            .carousel-container { padding: 0 45px; }
            .carousel-nav { width: 40px; height: 40px; font-size: 16px; }
        }
        
        @media (max-width: 576px) {
            .carousel-container { padding: 0 40px; }
            .carousel-nav { width: 35px; height: 35px; font-size: 14px; }
        }
        
        .pelatihan-card, .reward-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .pelatihan-card:hover, .reward-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .card-image {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px 15px 0 0;
        }
        .card-image img { width: 100%; height: 100%; object-fit: cover; }
        .badge-overlay {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #667eea;
        }
        .card-meta { font-size: 13px; }
        .meta-item { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .card-image-reward {
            height: 350px;
            overflow: hidden;
            background: #f0f0f0;
            border-radius: 15px 15px 0 0;
        }
        .card-image-reward img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .reward-card:hover .card-image-reward img { transform: scale(1.05); }
        .achievement-badge {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
        .badge { font-size: 11px; padding: 6px 12px; font-weight: 600; }
        .flyer-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 18px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'users/partials/navbar.php'; ?>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title display-4 fw-bold text-white mb-4">
                        Manajemen & Pengembangan SDM Politeknik Nest
                    </h1>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <?php if ($is_pegawai && !empty($services)): ?>
    <section class="services-section">
        <div class="container">
            <h2 class="section-title">Layanan MSDM</h2>
            <p class="section-subtitle">Layanan terintegrasi untuk pengelolaan dan pengembangan sumber daya manusia</p>

            <div class="row g-4">
                <?php foreach ($services as $service): ?>
                <div class="col-lg-3 col-md-6">
                    <?php if ($service['locked']): ?>
                        <a href="javascript:void(0)" 
                           class="service-card locked" 
                           onclick="showIncompleteAlertIndex(event)">
                            <div class="service-card-image">
                                <img src="<?php echo BASE_URL . $service['image']; ?>" 
                                     alt="<?php echo $service['title']; ?>"
                                     loading="lazy"
                                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)';">
                                <div class="service-card-overlay">
                                    <h5 class="service-card-title"><?php echo $service['title']; ?></h5>
                                </div>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $service['link']; ?>" class="service-card">
                            <div class="service-card-image">
                                <img src="<?php echo BASE_URL . $service['image']; ?>" 
                                     alt="<?php echo $service['title']; ?>"
                                     loading="lazy"
                                     onerror="this.onerror=null; this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)';">
                                <div class="service-card-overlay">
                                    <h5 class="service-card-title"><?php echo $service['title']; ?></h5>
                                </div>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Pelatihan Section -->
    <section class="pelatihan-section py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title fw-bold mb-3">Pelatihan</h2>
                <p class="text-muted">Program pelatihan dan workshop untuk meningkatkan kompetensi pegawai</p>
            </div>

            <?php if (empty($pelatihan_data)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Belum ada data pelatihan tersedia.
                </div>
            <?php elseif (count($pelatihan_data) <= 3): ?>
                <!-- Grid untuk data <= 3 -->
                <div class="row justify-content-center g-4">
                    <?php foreach ($pelatihan_data as $pelatihan): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card pelatihan-card h-100">
                            <div class="card-image">
                                <?php if (!empty($pelatihan['flyer'])): ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($pelatihan['flyer']); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($pelatihan['judul_pelatihan'] ?? 'Pelatihan'); ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="flyer-placeholder" style="display: none;">
                                        <i class="fas fa-chalkboard-teacher fa-3x"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="flyer-placeholder">
                                        <i class="fas fa-chalkboard-teacher fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="badge-overlay">Pelatihan</span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($pelatihan['judul_pelatihan'] ?? 'Pelatihan'); ?></h5>
                                <p class="card-text text-muted">
                                    <?php 
                                    $deskripsi = $pelatihan['deskripsi'] ?? 'Tidak ada deskripsi';
                                    echo strlen($deskripsi) > 100 ? substr(htmlspecialchars($deskripsi), 0, 100) . '...' : htmlspecialchars($deskripsi);
                                    ?>
                                </p>
                                <div class="card-meta mb-3">
                                    <div class="meta-item">
                                        <i class="far fa-calendar text-primary"></i>
                                        <span><?php echo formatRangeTanggal($pelatihan['tanggal_mulai'] ?? null, $pelatihan['tanggal_selesai'] ?? null); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                        <span><?php echo htmlspecialchars($pelatihan['lokasi'] ?? '-'); ?></span>
                                    </div>
                                    <?php if (!empty($pelatihan['instruktur'])): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-user-tie text-primary"></i>
                                        <span><?php echo htmlspecialchars($pelatihan['instruktur']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-grid">
                                    <button class="btn btn-primary" onclick='showPelatihanDetail(<?php echo json_encode($pelatihan, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="fas fa-info-circle me-1"></i> Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Carousel untuk data > 3 -->
                <div class="carousel-container position-relative">
                    <button class="carousel-nav carousel-nav-prev" id="pelatihan-prev" aria-label="Previous">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="carousel-nav carousel-nav-next" id="pelatihan-next" aria-label="Next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <div class="carousel-wrapper" id="pelatihan-carousel">
                        <div class="carousel-track" id="pelatihan-track">
                            <?php foreach ($pelatihan_data as $pelatihan): ?>
                            <div class="card-slide">
                                <div class="card pelatihan-card h-100">
                                    <div class="card-image">
                                        <?php if (!empty($pelatihan['flyer'])): ?>
                                            <img src="<?php echo BASE_URL . htmlspecialchars($pelatihan['flyer']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($pelatihan['judul_pelatihan'] ?? 'Pelatihan'); ?>"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="flyer-placeholder" style="display: none;">
                                                <i class="fas fa-chalkboard-teacher fa-3x"></i>
                                            </div>
                                        <?php else: ?>
                                            <div class="flyer-placeholder">
                                                <i class="fas fa-chalkboard-teacher fa-3x"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="badge-overlay">Pelatihan</span>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($pelatihan['judul_pelatihan'] ?? 'Pelatihan'); ?></h5>
                                        <p class="card-text text-muted">
                                            <?php 
                                            $deskripsi = $pelatihan['deskripsi'] ?? 'Tidak ada deskripsi';
                                            echo strlen($deskripsi) > 100 ? substr(htmlspecialchars($deskripsi), 0, 100) . '...' : htmlspecialchars($deskripsi);
                                            ?>
                                        </p>
                                        <div class="card-meta mb-3">
                                            <div class="meta-item">
                                                <i class="far fa-calendar text-primary"></i>
                                                <span><?php echo formatRangeTanggal($pelatihan['tanggal_mulai'] ?? null, $pelatihan['tanggal_selesai'] ?? null); ?></span>
                                            </div>
                                            <div class="meta-item">
                                                <i class="fas fa-map-marker-alt text-primary"></i>
                                                <span><?php echo htmlspecialchars($pelatihan['lokasi'] ?? '-'); ?></span>
                                            </div>
                                            <?php if (!empty($pelatihan['instruktur'])): ?>
                                            <div class="meta-item">
                                                <i class="fas fa-user-tie text-primary"></i>
                                                <span><?php echo htmlspecialchars($pelatihan['instruktur']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-grid">
                                            <button class="btn btn-primary" onclick='showPelatihanDetail(<?php echo json_encode($pelatihan, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <i class="fas fa-info-circle me-1"></i> Detail
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Reward Pegawai Section -->
    <section class="reward-section py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title fw-bold">Reward Pegawai</h2>
                <p class="text-muted">Apresiasi untuk pegawai berprestasi</p>
            </div>

            <?php if (empty($reward_data)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Belum ada data reward tersedia.
                </div>
            <?php elseif (count($reward_data) <= 3): ?>
                <!-- Grid Biasa untuk data <= 3 -->
                <div class="row justify-content-center g-4">
                    <?php foreach ($reward_data as $reward): 
                        $jenis_display = 'Pegawai';
                        if (!empty($reward['jenis_pegawai'])) {
                            switch($reward['jenis_pegawai']) {
                                case 'dosen':
                                    $jenis_display = 'Dosen';
                                    if (!empty($reward['prodi'])) {
                                        $jenis_display .= ' - ' . htmlspecialchars($reward['prodi']);
                                    }
                                    break;
                                case 'staff':
                                    $jenis_display = 'Staff';
                                    break;
                                case 'tendik':
                                    $jenis_display = 'Tenaga Kependidikan';
                                    break;
                            }
                        }
                    ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card reward-card h-100">
                            <div class="card-image-reward">
                                <?php if (!empty($reward['file_bukti'])): ?>
                                    <?php $file_bukti_path = 'uploads/reward/' . htmlspecialchars($reward['file_bukti']); ?>
                                    <img src="<?php echo BASE_URL . $file_bukti_path; ?>" 
                                         class="card-img-top" 
                                         alt="Bukti Reward - <?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Pegawai'); ?>"
                                         onerror="this.src='https://via.placeholder.com/300x350/667eea/ffffff?text=<?php echo urlencode(substr($reward['nama_lengkap'] ?? 'P', 0, 1)); ?>'">
                                <?php elseif (!empty($reward['foto_path'])): ?>
                                    <img src="<?php echo BASE_URL . htmlspecialchars($reward['foto_path']); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Pegawai'); ?>"
                                         onerror="this.src='https://via.placeholder.com/300x350/667eea/ffffff?text=<?php echo urlencode(substr($reward['nama_lengkap'] ?? 'P', 0, 1)); ?>'">
                                <?php else: ?>
                                    <img src="https://via.placeholder.com/300x350/667eea/ffffff?text=<?php echo urlencode(substr($reward['nama_lengkap'] ?? 'P', 0, 1)); ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Pegawai'); ?>">
                                <?php endif; ?>
                            </div>
                            <div class="card-body text-center">
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Nama Pegawai'); ?></h5>
                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($jenis_display); ?></p>
                                <?php if (!empty($reward['kategori'])): ?>
                                <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($reward['kategori']); ?></span>
                                <?php endif; ?>
                                <div class="achievement-badge">
                                    <i class="fas fa-trophy me-2"></i>
                                    <?php echo htmlspecialchars($reward['judul_reward'] ?? 'Reward'); ?>
                                </div>
                                <?php if (!empty($reward['tanggal_reward'])): ?>
                                <p class="text-muted small mt-2 mb-0">
                                    <i class="far fa-calendar me-1"></i>
                                    <?php echo formatTanggal($reward['tanggal_reward']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Carousel untuk data > 3 -->
                <div class="carousel-container position-relative">
                    <button class="carousel-nav carousel-nav-prev" id="reward-prev" aria-label="Previous">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="carousel-nav carousel-nav-next" id="reward-next" aria-label="Next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <div class="carousel-wrapper" id="reward-carousel">
                        <div class="carousel-track" id="reward-track">
                            <?php foreach ($reward_data as $reward): 
                                $jenis_display = 'Pegawai';
                                if (!empty($reward['jenis_pegawai'])) {
                                    switch($reward['jenis_pegawai']) {
                                        case 'dosen':
                                            $jenis_display = 'Dosen';
                                            if (!empty($reward['prodi'])) {
                                                $jenis_display .= ' - ' . htmlspecialchars($reward['prodi']);
                                            }
                                            break;
                                        case 'staff':
                                            $jenis_display = 'Staff';
                                            break;
                                        case 'tendik':
                                            $jenis_display = 'Tenaga Kependidikan';
                                            break;
                                    }
                                }
                            ?>
                            <div class="card-slide">
                                <div class="card reward-card h-100">
                                    <div class="card-image-reward">
                                        <?php if (!empty($reward['file_bukti'])): ?>
                                            <?php $file_bukti_path = 'uploads/reward/' . htmlspecialchars($reward['file_bukti']); ?>
                                            <img src="<?php echo BASE_URL . $file_bukti_path; ?>" 
                                                 class="card-img-top" 
                                                 alt="Bukti Reward - <?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Pegawai'); ?>"
                                                 onerror="this.src='https://via.placeholder.com/300x350/667eea/ffffff?text=<?php echo urlencode(substr($reward['nama_lengkap'] ?? 'P', 0, 1)); ?>'">
                                        <?php elseif (!empty($reward['foto_path'])): ?>
                                            <img src="<?php echo BASE_URL . htmlspecialchars($reward['foto_path']); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Pegawai'); ?>"
                                                 onerror="this.src='https://via.placeholder.com/300x350/667eea/ffffff?text=<?php echo urlencode(substr($reward['nama_lengkap'] ?? 'P', 0, 1)); ?>'">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/300x350/667eea/ffffff?text=<?php echo urlencode(substr($reward['nama_lengkap'] ?? 'P', 0, 1)); ?>" 
                                                 class="card-img-top" 
                                                 alt="<?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Pegawai'); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body text-center">
                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($reward['nama_lengkap'] ?? 'Nama Pegawai'); ?></h5>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($jenis_display); ?></p>
                                        <?php if (!empty($reward['kategori'])): ?>
                                        <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($reward['kategori']); ?></span>
                                        <?php endif; ?>
                                        <div class="achievement-badge">
                                            <i class="fas fa-trophy me-2"></i>
                                            <?php echo htmlspecialchars($reward['judul_reward'] ?? 'Reward'); ?>
                                        </div>
                                        <?php if (!empty($reward['tanggal_reward'])): ?>
                                        <p class="text-muted small mt-2 mb-0">
                                            <i class="far fa-calendar me-1"></i>
                                            <?php echo formatTanggal($reward['tanggal_reward']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'users/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
    
    <script>
        <?php if ($flash): ?>
        Swal.fire({
            icon: '<?php echo $flash['type'] == 'success' ? 'success' : ($flash['type'] == 'error' ? 'error' : 'info'); ?>',
            title: '<?php echo $flash['type'] == 'success' ? 'Berhasil!' : ($flash['type'] == 'error' ? 'Gagal!' : 'Informasi'); ?>',
            text: '<?php echo addslashes($flash['message']); ?>',
            showConfirmButton: true,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>
        
        function showIncompleteAlertIndex(event) {
            event.preventDefault();
            event.stopPropagation();
            
            Swal.fire({
                icon: 'warning',
                title: 'Data Belum Lengkap!',
                html: '<?php echo addslashes($completion_message); ?>',
                showCancelButton: false,
                confirmButtonText: 'Lengkapi Data Sekarang',
                confirmButtonColor: '#F6C35A',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo BASE_URL; ?>users/pegawai/administrasi.php';
                }
            });
        }
        
        function showPelatihanDetail(pelatihan) {
            const tanggalMulai = pelatihan.tanggal_mulai ? new Date(pelatihan.tanggal_mulai).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'}) : '-';
            const tanggalSelesai = pelatihan.tanggal_selesai ? new Date(pelatihan.tanggal_selesai).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'}) : '-';
            
            let tanggalText = '';
            if (pelatihan.tanggal_mulai && pelatihan.tanggal_selesai && pelatihan.tanggal_mulai !== pelatihan.tanggal_selesai) {
                tanggalText = tanggalMulai + ' - ' + tanggalSelesai;
            } else if (pelatihan.tanggal_mulai) {
                tanggalText = tanggalMulai;
            } else {
                tanggalText = '-';
            }
            
            let htmlContent = `
                <div class="text-start">
                    <p><strong>Deskripsi:</strong></p>
                    <p>${pelatihan.deskripsi || 'Tidak ada deskripsi'}</p>
                    <hr>
                    <p><strong><i class="far fa-calendar me-2"></i>Tanggal:</strong> ${tanggalText}</p>
                    <p><strong><i class="fas fa-map-marker-alt me-2"></i>Lokasi:</strong> ${pelatihan.lokasi || '-'}</p>
            `;
            
            if (pelatihan.instruktur) {
                htmlContent += `<p><strong><i class="fas fa-user-tie me-2"></i>Instruktur:</strong> ${pelatihan.instruktur}</p>`;
            }
            
            htmlContent += `</div>`;
            
            const swalConfig = {
                title: pelatihan.judul_pelatihan || 'Detail Pelatihan',
                html: htmlContent,
                icon: 'info',
                showCancelButton: pelatihan.undangan ? true : false,
                confirmButtonText: pelatihan.undangan ? '<i class="fas fa-download me-2"></i>Unduh Undangan' : 'Tutup',
                cancelButtonText: 'Tutup',
                confirmButtonColor: '#0D5E9D',
                width: '600px'
            };
            
            if (!pelatihan.undangan) {
                swalConfig.showCancelButton = false;
            }
            
            Swal.fire(swalConfig).then((result) => {
                if (result.isConfirmed && pelatihan.undangan) {
                    window.open('<?php echo BASE_URL; ?>' + pelatihan.undangan, '_blank');
                }
            });
        }
        
        // Fungsi carousel
        class Carousel {
            constructor(carouselId, trackId, prevBtnId, nextBtnId) {
                this.carousel = document.getElementById(carouselId);
                this.track = document.getElementById(trackId);
                this.prevBtn = document.getElementById(prevBtnId);
                this.nextBtn = document.getElementById(nextBtnId);
                this.currentIndex = 0;
                this.itemsPerView = this.getItemsPerView();
                
                if (this.track && this.prevBtn && this.nextBtn) {
                    this.init();
                }
            }
            
            getItemsPerView() {
                const width = window.innerWidth;
                if (width <= 768) return 1;
                if (width <= 1024) return 2;
                return 3;
            }
            
            init() {
                this.updateButtons();
                this.prevBtn.addEventListener('click', () => this.prev());
                this.nextBtn.addEventListener('click', () => this.next());
                
                window.addEventListener('resize', () => {
                    this.itemsPerView = this.getItemsPerView();
                    this.updatePosition();
                    this.updateButtons();
                });
            }
            
            updatePosition() {
                const items = this.track.children;
                const totalItems = items.length;
                
                const maxIndex = Math.max(0, totalItems - this.itemsPerView);
                this.currentIndex = Math.min(this.currentIndex, maxIndex);
                
                const itemWidth = items[0]?.offsetWidth || 0;
                const gap = 24;
                const offset = -(this.currentIndex * (itemWidth + gap));
                
                this.track.style.transform = `translateX(${offset}px)`;
            }
            
            updateButtons() {
                const totalItems = this.track.children.length;
                const maxIndex = Math.max(0, totalItems - this.itemsPerView);
                
                this.prevBtn.disabled = this.currentIndex === 0;
                this.nextBtn.disabled = this.currentIndex >= maxIndex;
            }
            
            prev() {
                if (this.currentIndex > 0) {
                    this.currentIndex--;
                    this.updatePosition();
                    this.updateButtons();
                }
            }
            
            next() {
                const totalItems = this.track.children.length;
                const maxIndex = Math.max(0, totalItems - this.itemsPerView);
                
                if (this.currentIndex < maxIndex) {
                    this.currentIndex++;
                    this.updatePosition();
                    this.updateButtons();
                }
            }
        }
        
        // Inisiasi carousels
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($pelatihan_data) > 3): ?>
            new Carousel('pelatihan-carousel', 'pelatihan-track', 'pelatihan-prev', 'pelatihan-next');
            <?php endif; ?>
            
            <?php if (count($reward_data) > 3): ?>
            new Carousel('reward-carousel', 'reward-track', 'reward-prev', 'reward-next');
            <?php endif; ?>
        });
    </script>
</body>
</html>