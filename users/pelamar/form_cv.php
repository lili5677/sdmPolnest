<?php
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pelamar.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT pelamar_id, nama_lengkap FROM pelamar WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$user_id]);
$pelamar = $stmt->fetch();

if (!$pelamar) {
    die('Data pelamar tidak ditemukan');
}

$pelamar_id = $pelamar['pelamar_id'];

$lowongan_id = isset($_GET['lowongan_id']) ? (int)$_GET['lowongan_id'] : 0;

$current_step = isset($_GET['step']) ? $_GET['step'] : 1;
if ($current_step !== 'success' && $current_step !== 'complete') {
    $current_step = (int)$current_step;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
    
    try {
        if ($step == 1) {
            // Data Diri
            $stmt = $conn->prepare("
                UPDATE pelamar SET
                    nama_lengkap = ?,
                    gelar = ?,
                    email_aktif = ?,
                    no_wa = ?,
                    tempat_lahir = ?,
                    tanggal_lahir = ?,
                    alamat_ktp = ?,
                    alamat_domisili = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE pelamar_id = ?
            ");
            $result = $stmt->execute([
                $_POST['nama_lengkap'],
                $_POST['gelar'] ?? null,
                $_POST['email_aktif'],
                $_POST['no_wa'],
                $_POST['tempat_lahir'],
                $_POST['tanggal_lahir'],
                $_POST['alamat_ktp'],
                $_POST['alamat_domisili'],
                $pelamar_id
            ]);
            
            if (!$result) {
                throw new Exception("Gagal menyimpan data diri");
            }
            
            $redirect_url = '?step=2';
            if ($lowongan_id > 0) {
                $redirect_url .= '&lowongan_id=' . $lowongan_id;
            }
            header('Location: ' . $redirect_url);
            exit();
            
        } elseif ($step == 2) {
            // Pendidikan
            $stmt = $conn->prepare("DELETE FROM pendidikan_pelamar WHERE pelamar_id = ?");
            $stmt->execute([$pelamar_id]);
            
            // Insert new
            if (isset($_POST['jenjang']) && is_array($_POST['jenjang'])) {
                $stmt = $conn->prepare("
                    INSERT INTO pendidikan_pelamar (pelamar_id, jenjang, nama_universitas, program_studi, ipk)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($_POST['jenjang'] as $i => $jenjang) {
                    $stmt->execute([
                        $pelamar_id,
                        $jenjang,
                        $_POST['nama_universitas'][$i],
                        $_POST['program_studi'][$i],
                        $_POST['ipk'][$i] ?? null
                    ]);
                }
            }
            
            $redirect_url = '?step=3';
            if (isset($_POST['lowongan_id']) && $_POST['lowongan_id'] > 0) {
                $redirect_url .= '&lowongan_id=' . $_POST['lowongan_id'];
            } elseif ($lowongan_id > 0) {
                $redirect_url .= '&lowongan_id=' . $lowongan_id;
            }
            header('Location: ' . $redirect_url);
            exit();
            
        } elseif ($step == 3) {
            // Pengalaman
            $stmt = $conn->prepare("
                INSERT INTO pengalaman_pelamar (
                    pelamar_id, pengalaman_kerja_terakhir, nama_perusahaan,
                    tautan_portofolio, pengalaman_mengajar, keahlian_utama
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    pengalaman_kerja_terakhir = VALUES(pengalaman_kerja_terakhir),
                    nama_perusahaan = VALUES(nama_perusahaan),
                    tautan_portofolio = VALUES(tautan_portofolio),
                    pengalaman_mengajar = VALUES(pengalaman_mengajar),
                    keahlian_utama = VALUES(keahlian_utama)
            ");
            $stmt->execute([
                $pelamar_id,
                $_POST['pengalaman_kerja'],
                $_POST['nama_perusahaan'],
                $_POST['tautan_portofolio'] ?? null,
                $_POST['pengalaman_mengajar'] ?? null,
                $_POST['keahlian_utama']
            ]);
            
            $redirect_url = '?step=4';
            if (isset($_POST['lowongan_id']) && $_POST['lowongan_id'] > 0) {
                $redirect_url .= '&lowongan_id=' . $_POST['lowongan_id'];
            } elseif ($lowongan_id > 0) {
                $redirect_url .= '&lowongan_id=' . $lowongan_id;
            }
            header('Location: ' . $redirect_url);
            exit();
            
        } elseif ($step == 4) {
            // Get lowongan_id from POST
            $post_lowongan_id = isset($_POST['lowongan_id']) ? (int)$_POST['lowongan_id'] : $lowongan_id;
            
            // Handle file uploads
            $upload_dir = '../../uploads/dokumen_pelamar/';
            
            // Debug: Check directory
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Gagal membuat folder upload: " . $upload_dir);
                }
            }
            
            // Check if directory is writable
            if (!is_writable($upload_dir)) {
                throw new Exception("Folder upload tidak bisa ditulis: " . $upload_dir);
            }
            
            // Required files
            $files = ['cv' => 'cv', 'ijazah' => 'ijazah', 'kartu_identitas' => 'kartu identitas'];
            $file_too_large = false;
            $missing_files = [];
            $invalid_format = [];
            
            // Check if all required files are uploaded and validate format
            foreach ($files as $field => $jenis) {
                if (!isset($_FILES[$field]) || $_FILES[$field]['error'] != 0) {
                    $missing_files[] = $field;
                } else {
                    // Validate file extension
                    $file_ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
                    if ($file_ext !== 'pdf') {
                        $invalid_format[] = $jenis . ' (File harus PDF, file yang Anda upload: ' . strtoupper($file_ext) . ')';
                    }
                    
                    // Validate MIME type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $_FILES[$field]['tmp_name']);
                    finfo_close($finfo);
                    
                    if ($mime_type !== 'application/pdf') {
                        $invalid_format[] = $jenis . ' (format file tidak valid)';
                    }
                }
            }
            
            // If there are missing files, show error
            if (!empty($missing_files)) {
                throw new Exception('Mohon upload semua dokumen yang diperlukan: ' . implode(', ', $missing_files));
            }
            
            // If there are invalid formats, show error
            if (!empty($invalid_format)) {
                throw new Exception('File harus dalam format PDF! File tidak valid: ' . implode(', ', $invalid_format));
            }
            
            $uploaded_files = [];
            foreach ($files as $field => $jenis) {
                $file = $_FILES[$field];
                $file_size = round($file['size'] / 1024); 
                
                // Check max 5MB
                if ($file_size > 5120) {
                    $file_too_large = true;
                    continue;
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = $pelamar_id . '_' . $jenis . '_' . time() . '.' . $ext;
                $file_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    // Save to database
                    $stmt = $conn->prepare("
                        INSERT INTO dokumen_pelamar (pelamar_id, jenis_dokumen, nama_file, path_file, ukuran_file)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $pelamar_id,
                        $jenis,
                        $file['name'],
                        $file_path,
                        $file_size
                    ]);
                    $uploaded_files[] = $field;
                } else {
                    throw new Exception("Gagal upload file: " . $field);
                }
            }
            
            // Show error if file too large
            if ($file_too_large) {
                $_SESSION['flash_message'] = [
                    'type' => 'error',
                    'message' => 'Beberapa file melebihi batas maksimal 5 MB dan tidak diunggah.'
                ];
            }
            
            // Update pelamar is_complete
            $stmt = $conn->prepare("UPDATE pelamar SET is_complete = 1 WHERE pelamar_id = ?");
            $stmt->execute([$pelamar_id]);
            
            // CREATE LAMARAN RECORD if lowongan_id is provided
            if ($post_lowongan_id > 0) {
                // Check if already applied
                $check = $conn->prepare("SELECT lamaran_id FROM lamaran WHERE pelamar_id = ? AND lowongan_id = ?");
                $check->execute([$pelamar_id, $post_lowongan_id]);
                
                if (!$check->fetch()) {
                    // Create new lamaran with 'dikirim' status (waiting admin verification)
                    $stmt = $conn->prepare("
                        INSERT INTO lamaran (pelamar_id, lowongan_id, status_lamaran, tanggal_daftar)
                        VALUES (?, ?, 'dikirim', CURRENT_TIMESTAMP)
                    ");
                    $stmt->execute([$pelamar_id, $post_lowongan_id]);
                    
                    error_log("Lamaran created: pelamar_id=$pelamar_id, lowongan_id=$post_lowongan_id, status=dikirim");
                }
            }
            
            // Set session for success alert
            $_SESSION['cv_completed'] = true;
            $_SESSION['cv_message'] = $file_too_large ? 
                'CV berhasil dilengkapi! Namun beberapa file melebihi batas 5 MB.' : 
                'Selamat! Anda telah menyelesaikan pengisian formulir lamaran kerja.';
            
            // Redirect based on whether lowongan_id exists
            if ($post_lowongan_id > 0) {
                $_SESSION['lamaran_success'] = true;
                $_SESSION['success_message'] = $file_too_large ? 
                    'Lamaran berhasil dikirim! Namun beberapa file melebihi batas 5 MB.' : 
                    'Selamat! Lamaran Anda berhasil dikirim dan sedang menunggu verifikasi admin.';
                
                header('Location: ?step=complete&lowongan_id=' . $post_lowongan_id);
            } else {
                $_SESSION['cv_completed'] = true;
                $_SESSION['cv_message'] = $file_too_large ? 
                    'CV berhasil dilengkapi! Namun beberapa file melebihi batas 5 MB.' : 
                    'Selamat! Anda telah menyelesaikan pengisian formulir CV.';
                
                header('Location: ?step=success');
            }
            exit();
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load existing data
$pelamar_data = $conn->prepare("SELECT * FROM pelamar WHERE pelamar_id = ?");
$pelamar_data->execute([$pelamar_id]);
$pelamar_info = $pelamar_data->fetch();

$pendidikan_data = $conn->prepare("SELECT * FROM pendidikan_pelamar WHERE pelamar_id = ?");
$pendidikan_data->execute([$pelamar_id]);
$pendidikan_list = $pendidikan_data->fetchAll();

$pengalaman_data = $conn->prepare("SELECT * FROM pengalaman_pelamar WHERE pelamar_id = ? LIMIT 1");
$pengalaman_data->execute([$pelamar_id]);
$pengalaman_info = $pengalaman_data->fetch();

// Check if this is success page
$show_success_alert = false;
$show_complete_alert = false;
$success_message = '';

// Debug log
error_log("DEBUG: GET step = " . ($_GET['step'] ?? 'not set'));
error_log("DEBUG: cv_completed session = " . (isset($_SESSION['cv_completed']) ? 'YES' : 'NO'));
error_log("DEBUG: lamaran_success session = " . (isset($_SESSION['lamaran_success']) ? 'YES' : 'NO'));

// Check for CV completion (no lowongan)
if (isset($_GET['step']) && $_GET['step'] == 'success' && isset($_SESSION['cv_completed'])) {
    $show_success_alert = true;
    $success_message = $_SESSION['cv_message'];
    error_log("DEBUG: Success alert WILL SHOW");
    unset($_SESSION['cv_completed']);
    unset($_SESSION['cv_message']);
} 
// Check for lamaran completion (with lowongan)
elseif (isset($_GET['step']) && $_GET['step'] == 'complete' && isset($_SESSION['lamaran_success'])) {
    $show_complete_alert = true;
    $success_message = $_SESSION['success_message'];
    error_log("DEBUG: Complete alert WILL SHOW");
    unset($_SESSION['lamaran_success']);
    unset($_SESSION['success_message']);
} else {
    error_log("DEBUG: No alert will show");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Lamaran Kerja - Politeknik Nest</title>
    
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: #f5f5f5;
        }
        
        /* Main Container */
        .main-content {
            max-width: 1000px;
            margin: 0 auto;
            padding: 60px 20px;
        }
        
        .page-title {
            font-size: 36px;
            font-weight: 700;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .page-subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 16px;
            font-style: italic;
            margin-bottom: 50px;
        }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            position: relative;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        
        .step-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 12px;
            border-radius: 12px;
            background: white;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #bbb;
            transition: all 0.3s;
        }
        
        .step.active .step-icon {
            background: linear-gradient(135deg, #0D9ED5, #0a7ba8);
            border-color: #0D9ED5;
            color: white;
            box-shadow: 0 6px 15px rgba(13, 158, 213, 0.3);
        }
        
        .step.completed .step-icon {
            background: white;
            border-color: #0D9ED5;
            color: #0D9ED5;
        }
        
        .step-label {
            font-size: 13px;
            font-weight: 600;
            color: #999;
        }
        
        .step.active .step-label,
        .step.completed .step-label {
            color: #0D9ED5;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .form-section-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .form-control,
        .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #0D9ED5;
            box-shadow: 0 0 0 0.2rem rgba(13, 158, 213, 0.1);
        }
        
        .form-control::placeholder {
            color: #bbb;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* File Upload */
        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload:hover {
            border-color: #0D9ED5;
            background: #f8f9fa;
        }
        
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .file-upload-label strong {
            color: #2c3e50;
        }
        
        .file-upload-label small {
            display: block;
            margin-top: 5px;
            font-size: 12px;
            color: #999;
        }
        
        /* Buttons */
        .btn-nav {
            padding: 12px 35px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .btn-prev {
            background: white;
            color: #7f8c8d;
            border: 2px solid #e0e0e0;
        }
        
        .btn-prev:hover {
            border-color: #bbb;
            color: #2c3e50;
        }
        
        .btn-next {
            background: linear-gradient(135deg, #0D5E9D, #0a4a7a);
            color: white !important;
            border: none;
            box-shadow: 0 5px 15px rgba(13, 94, 157, 0.3);
        }
        
        .btn-next:hover {
            background: linear-gradient(135deg, #0a4a7a, #0D5E9D);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(13, 94, 157, 0.4);
            color: white !important;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #0D9ED5, #0a7ba8);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-add:hover {
            background: linear-gradient(135deg, #0a7ba8, #0D9ED5);
            color: white;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .progress-steps {
                flex-wrap: wrap;
            }
            
            .step {
                flex: 0 0 50%;
                margin-bottom: 25px;
            }
            
            .step-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .page-title {
                font-size: 28px;
            }
            
            .form-card {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include '../partials/navbar_req.php'; ?>
    
    <?php if ($show_success_alert): ?>
    <!-- Success Alert Modal (CV Completion - No Lowongan) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'CV Berhasil Dilengkapi!',
                html: '<p style="font-size: 15px; color: #666;"><?php echo addslashes($success_message); ?></p><p style="font-size: 14px; margin-top: 15px;">Selanjutnya, pilih posisi yang ingin Anda lamar:</p>',
                showCancelButton: false,
                confirmButtonText: '<i class="fas fa-briefcase me-2"></i> Pilih Lowongan',
                confirmButtonColor: '#667eea',
                allowOutsideClick: false,
                allowEscapeKey: false,
                width: '600px',
                customClass: {
                    confirmButton: 'btn-lg px-4'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?php echo BASE_URL; ?>users/pelamar/pilih-lowongan.php';
                }
            });
        });
    </script>
    <?php endif; ?>
    
    <?php if ($show_complete_alert): ?>
    <!-- Complete Alert Modal (Lamaran Sent Successfully) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: '🎉 Lamaran Berhasil Dikirim!',
                html: `
                    <div style="text-align: center; padding: 20px;">
                        <p style="font-size: 16px; color: #2c3e50; margin-bottom: 20px;">
                            <strong><?php echo addslashes($success_message); ?></strong>
                        </p>
                        <p style="font-size: 14px; color: #666; margin-bottom: 15px;">
                            Lamaran Anda telah berhasil dikirim dan sedang dalam proses seleksi administrasi.
                        </p>
                        <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; margin: 20px 0;">
                            <i class="fas fa-info-circle" style="color: #2196f3; font-size: 20px;"></i>
                            <p style="font-size: 13px; color: #1976d2; margin: 10px 0 0 0;">
                                Anda dapat memantau status lamaran Anda secara real-time di halaman Tracking Lamaran
                            </p>
                        </div>
                    </div>
                `,
                showDenyButton: true,
                showCancelButton: false,
                confirmButtonText: '<i class="fas fa-chart-line me-2"></i> Lihat Tracking Lamaran',
                denyButtonText: '<i class="fas fa-home me-2"></i> Kembali ke Dashboard',
                confirmButtonColor: '#4CAF50',
                denyButtonColor: '#6c757d',
                allowOutsideClick: false,
                allowEscapeKey: false,
                width: '650px',
                customClass: {
                    confirmButton: 'btn-lg px-4',
                    denyButton: 'btn-lg px-4',
                    popup: 'animated-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Go to tracking lamaran
                    window.location.href = '<?php echo BASE_URL; ?>users/pelamar/tracking_lamaran.php';
                } else if (result.isDenied) {
                    // Go to dashboard
                    window.location.href = '<?php echo BASE_URL; ?>users/pelamar/dashboard.php';
                }
            });
        });
    </script>
    <style>
        .animated-popup {
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
    <?php endif; ?>
    
    <div class="main-content">
        <h1 class="page-title">Formulir Lamaran Kerja</h1>
        <p class="page-subtitle">"Lengkapi data diri Anda untuk memulai langkah karier baru"</p>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Progress Steps -->
        <?php if ($current_step !== 'success' && $current_step !== 'complete'): ?>
        <div class="progress-steps">
            <div class="step <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $current_step > 1 ? 'completed' : ''; ?>">
                <div class="step-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="step-label">Data Diri</div>
            </div>
            
            <div class="step <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $current_step > 2 ? 'completed' : ''; ?>">
                <div class="step-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="step-label">Pendidikan</div>
            </div>
            
            <div class="step <?php echo $current_step >= 3 ? 'active' : ''; ?> <?php echo $current_step > 3 ? 'completed' : ''; ?>">
                <div class="step-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="step-label">Pengalaman</div>
            </div>
            
            <div class="step <?php echo $current_step >= 4 ? 'active' : ''; ?>">
                <div class="step-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="step-label">Dokumen</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Forms -->
        <?php if ($current_step == 1): ?>

        <!-- Step 1: Data Diri -->
        <form method="POST" class="form-card">
            <input type="hidden" name="step" value="1">
            <?php if ($lowongan_id > 0): ?>
            <input type="hidden" name="lowongan_id" value="<?php echo $lowongan_id; ?>">
            <?php endif; ?>
            <h3 class="form-section-title">Informasi Pribadi</h3>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" 
                           value="<?php echo htmlspecialchars($pelamar_info['nama_lengkap'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Gelar (Opsional)</label>
                    <input type="text" name="gelar" class="form-control" 
                           placeholder="Contoh: S.Kom, M.T"
                           value="<?php echo htmlspecialchars($pelamar_info['gelar'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email Aktif</label>
                    <input type="email" name="email_aktif" class="form-control" 
                           value="<?php echo htmlspecialchars($pelamar_info['email_aktif'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="tel" name="no_wa" class="form-control" 
                           value="<?php echo htmlspecialchars($pelamar_info['no_wa'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" class="form-control" 
                           placeholder="Contoh: Sukoharjo"
                           value="<?php echo htmlspecialchars($pelamar_info['tempat_lahir'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" class="form-control" 
                           value="<?php echo htmlspecialchars($pelamar_info['tanggal_lahir'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Alamat KTP</label>
                    <textarea name="alamat_ktp" class="form-control" required><?php echo htmlspecialchars($pelamar_info['alamat_ktp'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Alamat Domisili</label>
                    <textarea name="alamat_domisili" class="form-control" required><?php echo htmlspecialchars($pelamar_info['alamat_domisili'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-nav btn-prev" disabled>
                    <i class="fas fa-chevron-left me-2"></i> Sebelumnya
                </button>
                <button type="submit" class="btn btn-nav btn-next">
                    Selanjutnya <i class="fas fa-chevron-right ms-2"></i>
                </button>
            </div>
        </form>
        
        <?php elseif ($current_step == 2): ?>

        <!-- Step 2: Pendidikan -->
        <form method="POST" class="form-card">
            <input type="hidden" name="step" value="2">
            <?php if ($lowongan_id > 0): ?>
            <input type="hidden" name="lowongan_id" value="<?php echo $lowongan_id; ?>">
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="form-section-title mb-0">Riwayat Pendidikan</h3>
                <button type="button" class="btn btn-add" onclick="addPendidikan()">
                    <i class="fas fa-plus me-2"></i> Tambah
                </button>
            </div>
            
            <div id="pendidikanContainer">
                <?php if (empty($pendidikan_list)): ?>
                <div class="pendidikan-item mb-4 p-3 border rounded">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenjang</label>
                            <select name="jenjang[]" class="form-select" required>
                                <option value="">Pilih Jenjang</option>
                                <option value="SMA">SMA/SMK</option>
                                <option value="D3">D3</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bidang Studi</label>
                            <input type="text" name="program_studi[]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Institusi Pendidikan</label>
                            <input type="text" name="nama_universitas[]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IPK / GPA</label>
                            <input type="number" step="0.01" name="ipk[]" class="form-control" min="0" max="4">
                        </div>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($pendidikan_list as $pend): ?>
                    <div class="pendidikan-item mb-4 p-3 border rounded">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Jenjang</label>
                                <select name="jenjang[]" class="form-select" required>
                                    <option value="">Pilih Jenjang</option>
                                    <option value="SMA" <?php echo $pend['jenjang'] == 'SMA' ? 'selected' : ''; ?>>SMA/SMK</option>
                                    <option value="D3" <?php echo $pend['jenjang'] == 'D3' ? 'selected' : ''; ?>>D3</option>
                                    <option value="S1" <?php echo $pend['jenjang'] == 'S1' ? 'selected' : ''; ?>>S1</option>
                                    <option value="S2" <?php echo $pend['jenjang'] == 'S2' ? 'selected' : ''; ?>>S2</option>
                                    <option value="S3" <?php echo $pend['jenjang'] == 'S3' ? 'selected' : ''; ?>>S3</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Program Studi</label>
                                <input type="text" name="program_studi[]" class="form-control" value="<?php echo htmlspecialchars($pend['program_studi']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Universitas</label>
                                <input type="text" name="nama_universitas[]" class="form-control" value="<?php echo htmlspecialchars($pend['nama_universitas']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">IPK / GPA</label>
                                <input type="number" step="0.01" name="ipk[]" class="form-control" value="<?php echo htmlspecialchars($pend['ipk']); ?>" min="0" max="4">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="?step=1<?php echo $lowongan_id > 0 ? '&lowongan_id=' . $lowongan_id : ''; ?>" class="btn btn-nav btn-prev">
                    <i class="fas fa-chevron-left me-2"></i> Sebelumnya
                </a>
                <button type="submit" class="btn btn-nav btn-next">
                    Selanjutnya <i class="fas fa-chevron-right ms-2"></i>
                </button>
            </div>
        </form>
        
        <?php elseif ($current_step == 3): ?>

        <!-- Step 3: Pengalaman -->
        <form method="POST" class="form-card">
            <input type="hidden" name="step" value="3">
            <?php if ($lowongan_id > 0): ?>
            <input type="hidden" name="lowongan_id" value="<?php echo $lowongan_id; ?>">
            <?php endif; ?>
            <h3 class="form-section-title">Pengalaman dan Keahlian</h3>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Pengalaman Kerja Terakhir</label>
                    <input type="text" name="pengalaman_kerja" class="form-control" 
                           placeholder="Jabatan: (Contoh: Admin Staff)"
                           value="<?php echo htmlspecialchars($pengalaman_info['pengalaman_kerja_terakhir'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" name="nama_perusahaan" class="form-control"
                           value="<?php echo htmlspecialchars($pengalaman_info['nama_perusahaan'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Tautan Portofolio (Opsional)</label>
                    <input type="url" name="tautan_portofolio" class="form-control"
                           value="<?php echo htmlspecialchars($pengalaman_info['tautan_portofolio'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Pengalaman Mengajar (Tahun)</label>
                    <input type="text" name="pengalaman_mengajar" class="form-control"
                           placeholder="Pelamar Dosen wajib mengisi kolom ini"
                           value="<?php echo htmlspecialchars($pengalaman_info['pengalaman_mengajar'] ?? ''); ?>">
                </div>
                
                <div class="col-12">
                    <label class="form-label">Keahlian Utama (Pisahkan dengan Koma)</label>
                    <textarea name="keahlian_utama" class="form-control" 
                              placeholder="Contoh: Microsoft Office, Komunikasi, Manajemen Waktu" required><?php echo htmlspecialchars($pengalaman_info['keahlian_utama'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="?step=2<?php echo $lowongan_id > 0 ? '&lowongan_id=' . $lowongan_id : ''; ?>" class="btn btn-nav btn-prev">
                    <i class="fas fa-chevron-left me-2"></i> Sebelumnya
                </a>
                <button type="submit" class="btn btn-nav btn-next">
                    Selanjutnya <i class="fas fa-chevron-right ms-2"></i>
                </button>
            </div>
        </form>
        
        <?php elseif ($current_step == 4): ?>

        <!-- Step 4: Dokumen -->
        <form method="POST" enctype="multipart/form-data" class="form-card" onsubmit="return validatePDFFiles()">
            <input type="hidden" name="step" value="4">
            <?php if ($lowongan_id > 0): ?>
            <input type="hidden" name="lowongan_id" value="<?php echo $lowongan_id; ?>">
            <?php endif; ?>
            <h3 class="form-section-title">Berkas Lamaran</h3>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">CV atau Resume <span class="text-danger">*</span></label>
                    <div class="file-upload" onclick="document.getElementById('cv').click()">
                        <input type="file" id="cv" name="cv" accept=".pdf,application/pdf" onchange="updateFileName(this, 'cv-label')" required>
                        <div class="file-upload-label" id="cv-label">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #0D9ED5; margin-bottom: 10px;"></i><br>
                            <strong>Lampirkan CV/Resume terbaru (PDF)</strong>
                            <small>*format PDF | maksimal 5 MB</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Ijazah atau SKL <span class="text-danger">*</span></label>
                    <div class="file-upload" onclick="document.getElementById('ijazah').click()">
                        <input type="file" id="ijazah" name="ijazah" accept=".pdf,application/pdf" onchange="updateFileName(this, 'ijazah-label')" required>
                        <div class="file-upload-label" id="ijazah-label">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #0D9ED5; margin-bottom: 10px;"></i><br>
                            <strong>Lampirkan Ijazah atau SKL terbaru (PDF)</strong>
                            <small>*format PDF | maksimal 5 MB</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Kartu Identitas (KTP/SIM) <span class="text-danger">*</span></label>
                    <div class="file-upload" onclick="document.getElementById('kartu_identitas').click()">
                        <input type="file" id="kartu_identitas" name="kartu_identitas" accept=".pdf,application/pdf" onchange="updateFileName(this, 'kartu-label')" required>
                        <div class="file-upload-label" id="kartu-label">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 32px; color: #0D9ED5; margin-bottom: 10px;"></i><br>
                            <strong>Lampirkan KTP atau SIM (PDF)</strong>
                            <small>*format PDF | maksimal 5 MB</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="?step=3<?php echo $lowongan_id > 0 ? '&lowongan_id=' . $lowongan_id : ''; ?>" class="btn btn-nav btn-prev">
                    <i class="fas fa-chevron-left me-2"></i> Sebelumnya
                </a>
                <button type="submit" class="btn btn-nav btn-next">
                    Kirim Lamaran <i class="fas fa-chevron-right ms-2"></i>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include '../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
    <script>
        // Strict PDF validation function
        function validatePDFFile(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileName = file.name.toLowerCase();
                const fileSize = file.size;
                const maxSize = 5 * 1024 * 1024; 
                
                // Check file extension
                if (!fileName.endsWith('.pdf')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Salah!',
                        html: `
                            <p>File harus dalam format <strong>PDF</strong>!</p>
                            <p style="color: #666; font-size: 14px;">File yang Anda pilih: <strong>${fileName}</strong></p>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                    input.value = '';
                    return false;
                }
                
                // Check file size
                if (fileSize > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar!',
                        html: `
                            <p>Ukuran file maksimal <strong>5 MB</strong></p>
                            <p style="color: #666; font-size: 14px;">File yang Anda pilih: <strong>${(fileSize / 1024 / 1024).toFixed(2)} MB</strong></p>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#d33'
                    });
                    input.value = '';
                    return false;
                }
                
                return true;
            }
            return false;
        }
        
        // Validate all PDF files before submit
        function validatePDFFiles() {
            const cvInput = document.getElementById('cv');
            const ijazahInput = document.getElementById('ijazah');
            const ktpInput = document.getElementById('kartu_identitas');
            
            // Check if all files are selected
            if (!cvInput.files[0] || !ijazahInput.files[0] || !ktpInput.files[0]) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Belum Lengkap!',
                    text: 'Mohon upload semua dokumen yang diperlukan',
                    confirmButtonColor: '#ffc107'
                });
                return false;
            }
            
            // Validate each file
            const files = [
                { input: cvInput, name: 'CV' },
                { input: ijazahInput, name: 'Ijazah' },
                { input: ktpInput, name: 'KTP/SIM' }
            ];
            
            for (let fileInfo of files) {
                const file = fileInfo.input.files[0];
                const fileName = file.name.toLowerCase();
                const fileSize = file.size;
                const maxSize = 5 * 1024 * 1024;
                
                if (!fileName.endsWith('.pdf')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format File Salah!',
                        html: `
                            <p><strong>${fileInfo.name}</strong> harus dalam format PDF!</p>
                            <p style="color: #666; font-size: 14px;">File: ${file.name}</p>
                        `,
                        confirmButtonColor: '#d33'
                    });
                    return false;
                }
                
                if (fileSize > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar!',
                        html: `
                            <p><strong>${fileInfo.name}</strong> melebihi batas 5 MB</p>
                            <p style="color: #666; font-size: 14px;">Ukuran: ${(fileSize / 1024 / 1024).toFixed(2)} MB</p>
                        `,
                        confirmButtonColor: '#d33'
                    });
                    return false;
                }
            }
            
            return true;
        }
        
        function addPendidikan() {
            const container = document.getElementById('pendidikanContainer');
            const newItem = `
                <div class="pendidikan-item mb-4 p-3 border rounded">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenjang</label>
                            <select name="jenjang[]" class="form-select" required>
                                <option value="">Pilih Jenjang</option>
                                <option value="SMA">SMA/SMK</option>
                                <option value="D3">D3</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Program Studi</label>
                            <input type="text" name="program_studi[]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Universitas</label>
                            <input type="text" name="nama_universitas[]" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IPK / GPA</label>
                            <input type="number" step="0.01" name="ipk[]" class="form-control" min="0" max="4">
                        </div>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', newItem);
        }
        
        function updateFileName(input, labelId) {
            // Validate PDF first
            if (!validatePDFFile(input)) {
                return;
            }
            
            const label = document.getElementById(labelId);
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
                label.innerHTML = `
                    <i class="fas fa-check-circle" style="font-size: 32px; color: #4CAF50; margin-bottom: 10px;"></i><br>
                    <strong>${fileName}</strong>
                    <small>${fileSize} MB | Format: PDF ✓</small>
                `;
            }
        }
        
        // Add event listeners to all file inputs
        document.addEventListener('DOMContentLoaded', function() {
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(function(input) {
                input.addEventListener('change', function() {
                    validatePDFFile(this);
                });
            });
        });
    </script>
</body>
</html>