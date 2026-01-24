<?php
require_once '../../includes/check_login.php';
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    // User not logged in - show SweetAlert and redirect
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tracking Lamaran - Login Required</title>
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">
    </head>
    <body>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Login Diperlukan',
                text: 'Silakan login terlebih dahulu untuk melihat tracking lamaran Anda.',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-sign-in-alt"></i> Login Sekarang',
                cancelButtonText: 'Kembali ke Beranda',
                confirmButtonColor: '#0D5E9D',
                cancelButtonColor: '#6c757d',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo BASE_URL; ?>auth/login_pelamar.php';
                } else {
                    window.location.href = '<?php echo BASE_URL; ?>users/pelamar/dashboard.php';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// User is logged in - get email from session
$email = $_SESSION['email'];
$lamaran_list = [];
$error = '';

try {
    // Query untuk mengambil semua lamaran berdasarkan email dari session
    $query = "
        SELECT 
            l.lamaran_id,
            l.status_lamaran,
            l.tanggal_daftar,
            l.tanggal_update,
            l.catatan_admin,
            lp.posisi,
            lp.lowongan_id,
            p.nama_lengkap,
            p.email_aktif,
            ji.tanggal_interview,
            ji.lokasi_interview,
            jp.tanggal_psikotes
        FROM lamaran l
        JOIN pelamar p ON l.pelamar_id = p.pelamar_id
        JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
        LEFT JOIN jadwal_interview ji ON l.lamaran_id = ji.lamaran_id
        LEFT JOIN jadwal_psikotes jp ON l.lamaran_id = jp.lamaran_id
        WHERE p.email_aktif = ?
        ORDER BY l.tanggal_daftar DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$email]);
    $lamaran_list = $stmt->fetchAll();
    
    if (empty($lamaran_list)) {
        $error = 'Anda belum memiliki lamaran yang terdaftar.';
    }
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan saat mengambil data.';
}

// Function untuk mendapatkan progress percentage
function getProgressPercentage($status) {
    $progress_map = [
        'dikirim' => 10,
        'seleksi_administrasi' => 20,
        'lolos_administrasi' => 30,
        'tidak_lolos_administrasi' => 25,
        'form_lanjutan' => 50,
        'psikotes' => 60,
        'interview' => 75,
        'diterima' => 100,
        'ditolak' => 0
    ];
    
    return isset($progress_map[$status]) ? $progress_map[$status] : 0;
}

// Function untuk status badge
function getStatusBadge($status) {
    $badges = [
        'dikirim' => ['text' => 'Dikirim', 'class' => 'badge-info'],
        'seleksi_administrasi' => ['text' => 'Seleksi Administrasi', 'class' => 'badge-warning'],
        'lolos_administrasi' => ['text' => 'Lolos Administrasi', 'class' => 'badge-success'],
        'tidak_lolos_administrasi' => ['text' => 'Tidak Lolos', 'class' => 'badge-danger'],
        'form_lanjutan' => ['text' => 'Pengisian Form', 'class' => 'badge-warning'],
        'psikotes' => ['text' => 'Psikotes', 'class' => 'badge-info'],
        'interview' => ['text' => 'Interview', 'class' => 'badge-info'],
        'diterima' => ['text' => 'Diterima', 'class' => 'badge-success'],
        'ditolak' => ['text' => 'Ditolak', 'class' => 'badge-danger']
    ];
    
    return isset($badges[$status]) ? $badges[$status] : ['text' => $status, 'class' => 'badge-secondary'];
}

// Function untuk timeline steps
function getTimelineSteps($status, $lamaran) {
    $steps = [
        [
            'name' => 'Lamaran Dikirim',
            'date' => date('d F Y', strtotime($lamaran['tanggal_daftar'])),
            'completed' => true
        ],
        [
            'name' => 'Seleksi Administrasi',
            'date' => in_array($status, ['seleksi_administrasi', 'lolos_administrasi', 'form_lanjutan', 'psikotes', 'interview', 'diterima']) ? date('d F Y', strtotime($lamaran['tanggal_update'])) : '',
            'completed' => in_array($status, ['lolos_administrasi', 'form_lanjutan', 'psikotes', 'interview', 'diterima'])
        ]
    ];
    
    // Add conditional steps
    if ($status == 'tidak_lolos_administrasi') {
        $steps[] = [
            'name' => 'Tidak Lolos',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true,
            'failed' => true
        ];
    } else {
        if (in_array($status, ['form_lanjutan', 'psikotes', 'interview', 'diterima'])) {
            $steps[] = [
                'name' => 'Isi Form Lanjutan',
                'date' => '',
                'completed' => in_array($status, ['psikotes', 'interview', 'diterima'])
            ];
        }
        
        if (in_array($status, ['psikotes', 'interview', 'diterima'])) {
            $steps[] = [
                'name' => 'Interview',
                'date' => !empty($lamaran['tanggal_interview']) ? date('d F Y', strtotime($lamaran['tanggal_interview'])) : '',
                'completed' => in_array($status, ['interview', 'diterima'])
            ];
        }
        
        if ($status == 'diterima') {
            $steps[] = [
                'name' => 'Hasil Akhir',
                'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
                'completed' => true
            ];
        }
    }
    
    return $steps;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Lamaran - Politeknik Nest</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f5f5;
        }
        
        .main-content {
            max-width: 900px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        .page-title {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: #7f8c8d;
            font-size: 15px;
            margin-bottom: 40px;
        }
        
        /* Search Form */
        .search-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .search-form input {
            height: 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 0 20px;
        }
        
        .search-form button {
            height: 50px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        /* Lamaran Card */
        .lamaran-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 2px solid #e8e8e8;
        }
        
        .lamaran-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 25px;
        }
        
        .lamaran-title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .lamaran-date {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Progress Bar */
        .progress-section {
            margin-bottom: 25px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .progress {
            height: 12px;
            border-radius: 10px;
            background: #e0e0e0;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #2c3e50, #34495e);
            border-radius: 10px;
        }
        
        /* Timeline */
        .timeline-section {
            margin-bottom: 25px;
        }
        
        .timeline-title {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .timeline-item {
            display: flex;
            align-items: start;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .timeline-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }
        
        .timeline-icon.completed {
            background: #4CAF50;
            color: white;
        }
        
        .timeline-icon.pending {
            background: #e0e0e0;
            color: #999;
        }
        
        .timeline-icon.failed {
            background: #f44336;
            color: white;
        }
        
        .timeline-content {
            flex: 1;
        }
        
        .timeline-name {
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #999;
        }
        
        /* Alert Messages */
        .alert-box {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        /* Button */
        .btn-form {
            background: #2c3e50;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-form:hover {
            background: #1a252f;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include '../partials/navbar_req.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <h1 class="page-title">Tracking Lamaran</h1>
        <p class="page-subtitle">Pantau status lamaran pekerjaan Anda</p>
        
        <!-- User Info -->
        <div class="alert-box alert-info">
            <i class="fas fa-user me-2"></i>
            Menampilkan lamaran untuk: <strong><?php echo htmlspecialchars($email); ?></strong>
        </div>
        
        <!-- Error/Empty Message -->
        <?php if (!empty($error)): ?>
        <div class="alert-box alert-danger">
            <i class="fas fa-info-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>pelamar/lowongan.php" class="btn-form">
                <i class="fas fa-briefcase me-2"></i> Lihat Lowongan Tersedia
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Lamaran List -->
        <?php foreach ($lamaran_list as $lamaran): 
            $progress = getProgressPercentage($lamaran['status_lamaran']);
            $badge = getStatusBadge($lamaran['status_lamaran']);
            $timeline = getTimelineSteps($lamaran['status_lamaran'], $lamaran);
        ?>
        <div class="lamaran-card">
            <!-- Header -->
            <div class="lamaran-header">
                <div>
                    <div class="lamaran-title"><?php echo htmlspecialchars($lamaran['posisi']); ?></div>
                    <div class="lamaran-date">
                        <i class="far fa-calendar"></i>
                        Dilamar <?php echo date('d F Y', strtotime($lamaran['tanggal_daftar'])); ?>
                    </div>
                </div>
                <div>
                    <span class="status-badge <?php echo $badge['class']; ?>">
                        <?php echo $badge['text']; ?>
                    </span>
                </div>
            </div>
            
            <!-- Progress -->
            <div class="progress-section">
                <div class="progress-label">
                    <span>Progress</span>
                    <span><?php echo $progress; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>
            
            <!-- Timeline -->
            <div class="timeline-section">
                <div class="timeline-title">Timeline Proses:</div>
                <?php foreach ($timeline as $step): ?>
                <div class="timeline-item">
                    <div class="timeline-icon <?php echo isset($step['failed']) ? 'failed' : ($step['completed'] ? 'completed' : 'pending'); ?>">
                        <i class="fas fa-<?php echo isset($step['failed']) ? 'times' : ($step['completed'] ? 'check' : 'circle'); ?>" style="font-size: 12px;"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-name"><?php echo $step['name']; ?></div>
                        <?php if (!empty($step['date'])): ?>
                        <div class="timeline-date"><?php echo $step['date']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Admin Notes / Actions -->
            <?php if ($lamaran['status_lamaran'] == 'tidak_lolos_administrasi' && !empty($lamaran['catatan_admin'])): ?>
            <div class="alert-box alert-danger">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo htmlspecialchars($lamaran['catatan_admin']); ?>
            </div>
            <?php elseif ($lamaran['status_lamaran'] == 'form_lanjutan'): ?>
            <div class="alert-box alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Silahkan lengkapi form data diri untuk melanjutkan ke tahap  interview.
            </div>
            <a href="../pelamar/form-lanjutan.php?lamaran_id=<?php echo $lamaran['lamaran_id']; ?>" class="btn-form">
                Lengkapi Form Data Diri
            </a>
            <?php elseif ($lamaran['status_lamaran'] == 'interview' && !empty($lamaran['tanggal_interview'])): ?>
            <div class="alert-box alert-info">
                <i class="fas fa-calendar-check me-2"></i>
                Interview dijadwalkan pada <?php echo date('d F Y, H:i', strtotime($lamaran['tanggal_interview'])); ?> WIB 
                <?php if (!empty($lamaran['lokasi_interview'])): ?>
                di <?php echo htmlspecialchars($lamaran['lokasi_interview']); ?>
                <?php endif; ?>.
            </div>
            <?php elseif ($lamaran['status_lamaran'] == 'diterima'): ?>
            <div class="alert-box alert-success">
                <i class="fas fa-check-circle me-2"></i>
                Selamat! Anda telah lolos seleksi administrasi dan dapat melanjutkan ke tahap berikutnya.
                <?php if (!empty($lamaran['catatan_admin'])): ?>
                <br><?php echo htmlspecialchars($lamaran['catatan_admin']); ?>
                <?php endif; ?>
            </div>
            <a href="../auth/login-pegawai.php" class="btn-form">
                <i class="fas fa-sign-in-alt me-2"></i> Login ke Akun Pegawai
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Footer -->
    <?php include '../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>