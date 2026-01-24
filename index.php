<?php
require_once 'config/database.php';

// Fetch pelatihan data from database
try {
    // Ambil 6 pelatihan terbaru
    $stmt = $conn->query("SELECT * FROM pelatihan ORDER BY created_at DESC LIMIT 6");
    $pelatihan_data = $stmt->fetchAll();
    
    // Ambil 6 reward terbaru
    $stmt = $conn->query("SELECT * FROM reward ORDER BY created_at DESC LIMIT 6");
    $reward_data = $stmt->fetchAll();
} catch (PDOException $e) {
    $pelatihan_data = [];
    $reward_data = [];
}

// Get flash message
$flash = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
if ($flash) {
    unset($_SESSION['flash_message']);
}

// Format tanggal Indonesia
function formatTanggal($date) {
    if (empty($date)) return '-';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $d = date('d', strtotime($date));
    $m = date('n', strtotime($date));
    $y = date('Y', strtotime($date));
    return $d . ' ' . $bulan[$m] . ' ' . $y;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Politeknik Nest</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        .hero-section {
            background: url('<?php echo BASE_URL; ?>users/assets/dashboard.png') center/cover;
            min-height: 500px;
            display: flex;
            align-items: center;
            padding: 80px 0;
        }
        .hero-title {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .btn-pink {
            background: linear-gradient(135deg, #FF6B9D, #ff4d85);
            color: white !important;
            font-weight: 600;
            padding: 15px 50px;
            border-radius: 50px; 
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.5);
            text-decoration: none;
            display: inline-block;
        }

        .btn-pink:hover {
            background: linear-gradient(135deg, #ff4d85, #FF6B9D);
            transform: translateY(-3px); 
            box-shadow: 0 12px 35px rgba(255, 107, 157, 0.6); 
        }
        .pelatihan-section {
            background-color: #F5F7FA;
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
            background: #2c3e50;
        }
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .badge-overlay {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .card-meta {
            font-size: 13px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        .card-image-reward {
            height: 350px;
            overflow: hidden;
            background: #f0f0f0;
        }
        .card-image-reward img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .achievement-badge {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'users/partials/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title display-4 fw-bold text-white mb-4">
                        Manajemen & Pengembangan SDM Politeknik Nest
                    </h1>
                    <a href="<?php echo BASE_URL; ?>users/pelamar/lowongan.php" class="btn btn-pink btn-lg rounded-pill px-5">
                        Lihat Lowongan
                    </a>
                </div>
            </div>
        </div>
    </section>

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
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pelatihan_data as $pelatihan): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card pelatihan-card h-100">
                            <div class="card-image">
                                <img src="https://via.placeholder.com/400x250/2c3e50/ffffff?text=Pelatihan" 
                                     class="card-img-top" alt="Pelatihan">
                                <span class="badge-overlay">Pelatihan</span>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($pelatihan['judul']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($pelatihan['deskripsi']); ?></p>
                                
                                <div class="card-meta mb-3">
                                    <div class="meta-item">
                                        <i class="far fa-calendar text-primary"></i>
                                        <span><?php echo formatTanggal($pelatihan['tanggal']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-map-marker-alt text-primary"></i>
                                        <span><?php echo htmlspecialchars($pelatihan['lokasi']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-users text-primary"></i>
                                        <span><?php echo htmlspecialchars($pelatihan['pembicara']); ?></span>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button class="btn btn-primary" onclick='showPelatihanDetail(<?php echo json_encode($pelatihan); ?>)'>
                                        <i class="fas fa-info-circle me-1"></i> Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Reward Pegawai Section -->
    <section class="reward-section py-5 bg-white">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title fw-bold">Reward Pegawai</h2>
            </div>

            <?php if (empty($reward_data)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    Belum ada data reward tersedia.
                </div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <?php foreach ($reward_data as $reward): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card reward-card h-100">
                            <div class="card-image-reward">
                                <img src="https://via.placeholder.com/300x350/f0f0f0/333333?text=Employee" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($reward['nama']); ?>">
                            </div>
                            <div class="card-body text-center">
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($reward['nama']); ?></h5>
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars($reward['posisi']); ?></p>
                                <div class="achievement-badge">
                                    <?php echo htmlspecialchars($reward['achievement']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'users/partials/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
    
    <script>
        // Show flash message if exists
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
        
        // Show pelatihan detail
        function showPelatihanDetail(pelatihan) {
            Swal.fire({
                title: pelatihan.judul,
                html: `
                    <div class="text-start">
                        <p><strong>Deskripsi:</strong></p>
                        <p>${pelatihan.deskripsi}</p>
                        
                        <hr>
                        
                        <p><strong><i class="far fa-calendar me-2"></i>Tanggal:</strong> ${pelatihan.tanggal}</p>
                        <p><strong><i class="fas fa-map-marker-alt me-2"></i>Lokasi:</strong> ${pelatihan.lokasi}</p>
                        <p><strong><i class="fas fa-users me-2"></i>Pembicara:</strong> ${pelatihan.pembicara}</p>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-download me-2"></i>Unduh Undangan',
                cancelButtonText: 'Tutup',
                confirmButtonColor: '#0D5E9D',
                width: '600px'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Download',
                        text: 'Fitur download akan segera tersedia!',
                        showConfirmButton: true
                    });
                }
            });
        }
    </script>
</body>
</html>