<?php
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tracking Lamaran - Login Required</title>
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">
        <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
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
                    window.location.href = '<?php echo BASE_URL; ?>';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

$email = $_SESSION['email'];
$lamaran_list = [];
$error = '';

try {
    $check_pelamar = $conn->prepare("SELECT pelamar_id, email_aktif FROM pelamar WHERE email_aktif = ?");
    $check_pelamar->execute([$email]);
    $pelamar_data = $check_pelamar->fetch();
    
    if (!$pelamar_data) {
        $error = 'Data pelamar tidak ditemukan. Email: ' . $email;
    } else {
        $pelamar_id = $pelamar_data['pelamar_id'];
        
        $query = "
            SELECT 
                l.lamaran_id,
                l.status_lamaran,
                l.tanggal_daftar,
                l.tanggal_update,
                l.catatan_admin,
                l.surat_resmi_path,
                l.surat_resmi_jenis,
                l.surat_terkirim_at,
                lp.posisi,
                lp.lowongan_id,
                p.nama_lengkap,
                p.email_aktif,
                ji.tanggal_interview,
                ji.lokasi AS lokasi_interview,
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
    }
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage();
}

function getProgressPercentage($status) {
    $progress_map = [
        'dikirim' => 10,
        'seleksi_administrasi' => 20,
        'lolos_administrasi' => 30,
        'tidak_lolos_administrasi' => 25,
        'form_lanjutan' => 40,
        'lolos_form' => 50,
        'ditolak_form' => 40,
        'psikotes' => 60,
        'lolos_psikotes' => 70,
        'ditolak_psikotes' => 60,
        'interview' => 80,
        'diterima' => 100,
        'ditolak_interview' => 80
    ];
    
    return isset($progress_map[$status]) ? $progress_map[$status] : 0;
}

function getStatusBadge($status) {
    $badges = [
        'dikirim' => ['text' => 'Menunggu Verifikasi', 'class' => 'badge-warning'],
        'seleksi_administrasi' => ['text' => 'Sedang Diverifikasi', 'class' => 'badge-info'],
        'lolos_administrasi' => ['text' => 'Lolos - Isi Form', 'class' => 'badge-success'],
        'tidak_lolos_administrasi' => ['text' => 'Tidak Lolos Administrasi', 'class' => 'badge-danger'],
        'form_lanjutan' => ['text' => 'Menunggu Verifikasi Form', 'class' => 'badge-info'],
        'lolos_form' => ['text' => 'Lolos - Tunggu Psikotes', 'class' => 'badge-success'],
        'ditolak_form' => ['text' => 'Tidak Lolos Form', 'class' => 'badge-danger'],
        'psikotes' => ['text' => 'Psikotes Dijadwalkan', 'class' => 'badge-info'],
        'lolos_psikotes' => ['text' => 'Lolos - Tunggu Interview', 'class' => 'badge-success'],
        'ditolak_psikotes' => ['text' => 'Tidak Lolos Psikotes', 'class' => 'badge-danger'],
        'interview' => ['text' => 'Interview Dijadwalkan', 'class' => 'badge-info'],
        'diterima' => ['text' => 'Diterima', 'class' => 'badge-success'],
        'ditolak_interview' => ['text' => 'Tidak Lolos Interview', 'class' => 'badge-danger']
    ];
    
    return isset($badges[$status]) ? $badges[$status] : ['text' => $status, 'class' => 'badge-secondary'];
}

function getTimelineSteps($status, $lamaran) {
    $steps = [];
    
    // Step 1: Lamaran Dikirim
    $steps[] = [
        'name' => 'Lamaran Dikirim',
        'date' => date('d F Y', strtotime($lamaran['tanggal_daftar'])),
        'completed' => true
    ];
    
    // Step 2: Verifikasi Administrasi 
    if ($status == 'seleksi_administrasi') {
        $steps[] = [
            'name' => 'Verifikasi Administrasi',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    if ($status == 'tidak_lolos_administrasi') {
        $steps[] = [
            'name' => 'Tidak Lolos Administrasi',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true,
            'failed' => true
        ];
        return $steps;
    }
    
    // Step 3: Lolos Administrasi
    if (in_array($status, ['lolos_administrasi', 'form_lanjutan', 'lolos_form', 'ditolak_form', 'psikotes', 'lolos_psikotes', 'ditolak_psikotes', 'interview', 'diterima', 'ditolak_interview'])) {
        $steps[] = [
            'name' => 'Lolos Administrasi',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    }
    
    // Step 4: Isi Form Lanjutan
    if ($status == 'lolos_administrasi') {
        $steps[] = [
            'name' => 'Isi Form Lanjutan',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    if (in_array($status, ['form_lanjutan', 'lolos_form', 'ditolak_form', 'psikotes', 'lolos_psikotes', 'ditolak_psikotes', 'interview', 'diterima', 'ditolak_interview'])) {
        $steps[] = [
            'name' => 'Form Lanjutan Selesai',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    }
    
    // Step 5: Verifikasi Form
    if ($status == 'form_lanjutan') {
        $steps[] = [
            'name' => 'Verifikasi Form',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    if ($status == 'ditolak_form') {
        $steps[] = [
            'name' => 'Tidak Lolos Verifikasi Form',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true,
            'failed' => true
        ];
        return $steps;
    }
    
    // Step 6: Lolos Form
    if (in_array($status, ['lolos_form', 'psikotes', 'lolos_psikotes', 'ditolak_psikotes', 'interview', 'diterima', 'ditolak_interview'])) {
        $steps[] = [
            'name' => 'Lolos Verifikasi Form',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    }
    
    // Step 7: Psikotes
    if ($status == 'lolos_form') {
        $steps[] = [
            'name' => 'Menunggu Jadwal Psikotes',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    if (in_array($status, ['psikotes', 'lolos_psikotes', 'ditolak_psikotes', 'interview', 'diterima', 'ditolak_interview'])) {
        $steps[] = [
            'name' => 'Psikotes',
            'date' => !empty($lamaran['tanggal_psikotes']) ? date('d F Y', strtotime($lamaran['tanggal_psikotes'])) : date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    }
    
    // Step 8: Hasil Psikotes
    if ($status == 'psikotes') {
        $steps[] = [
            'name' => 'Menunggu Hasil Psikotes',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    if ($status == 'ditolak_psikotes') {
        $steps[] = [
            'name' => 'Tidak Lolos Psikotes',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true,
            'failed' => true
        ];
        return $steps;
    }
    
    // Step 9: Lolos Psikotes
    if (in_array($status, ['lolos_psikotes', 'interview', 'diterima', 'ditolak_interview'])) {
        $steps[] = [
            'name' => 'Lolos Psikotes',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    }
    
    // Step 10: Interview
    if ($status == 'lolos_psikotes') {
        $steps[] = [
            'name' => 'Menunggu Jadwal Interview',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    if (in_array($status, ['interview', 'diterima', 'ditolak_interview'])) {
        $steps[] = [
            'name' => 'Interview',
            'date' => !empty($lamaran['tanggal_interview']) ? date('d F Y', strtotime($lamaran['tanggal_interview'])) : date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    }
    
    // Step 11: Hasil Interview
    if ($status == 'interview') {
        $steps[] = [
            'name' => 'Menunggu Hasil Interview',
            'date' => '',
            'completed' => false,
            'pending' => true
        ];
        return $steps;
    }
    
    // Final Result
    if ($status == 'diterima') {
        $steps[] = [
            'name' => 'Diterima Sebagai Karyawan',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true
        ];
    } elseif ($status == 'ditolak_interview') {
        $steps[] = [
            'name' => 'Tidak Lolos Interview',
            'date' => date('d F Y', strtotime($lamaran['tanggal_update'])),
            'completed' => true,
            'failed' => true
        ];
    }
    
    return $steps;
}

// Function untuk mendapatkan instruksi langkah selanjutnya
function getNextStepInstruction($status) {
    $instructions = [
        'dikirim' => [
            'icon' => 'hourglass-half',
            'color' => 'warning',
            'title' => 'Menunggu Verifikasi Admin',
            'text' => 'Lamaran Anda sedang dalam antrian verifikasi. Tim HR akan memeriksa kelengkapan dokumen Anda. Mohon menunggu, Anda akan mendapat notifikasi via email.'
        ],
        'seleksi_administrasi' => [
            'icon' => 'search',
            'color' => 'info',
            'title' => 'Sedang Diverifikasi',
            'text' => 'Tim HR sedang memverifikasi dokumen lamaran Anda. Pastikan email Anda aktif untuk menerima notifikasi hasil seleksi administrasi.'
        ],
        'tidak_lolos_administrasi' => [
            'icon' => 'times-circle',
            'color' => 'danger',
            'title' => 'Tidak Lolos Administrasi',
            'text' => 'Mohon maaf, lamaran Anda tidak memenuhi persyaratan administrasi. Anda dapat mencoba melamar kembali di lowongan lain yang sesuai dengan kualifikasi Anda.'
        ],
        'lolos_administrasi' => [
            'icon' => 'file-alt',
            'color' => 'success',
            'title' => 'Action Required: Lengkapi Form Data Diri',
            'text' => 'Selamat! Anda lolos seleksi administrasi. Silakan klik tombol di bawah untuk mengisi form data diri lengkap sebagai syarat tahap selanjutnya.'
        ],
        'form_lanjutan' => [
            'icon' => 'clock',
            'color' => 'info',
            'title' => 'Form Sedang Diverifikasi',
            'text' => 'Form data diri Anda telah diterima dan sedang dalam proses verifikasi. Anda akan dihubungi jika lolos ke tahap psikotes.'
        ],
        'ditolak_form' => [
            'icon' => 'times-circle',
            'color' => 'danger',
            'title' => 'Tidak Lolos Verifikasi Form',
            'text' => 'Mohon maaf, data yang Anda berikan tidak memenuhi persyaratan yang kami butuhkan. Terima kasih atas partisipasi Anda.'
        ],
        'lolos_form' => [
            'icon' => 'check-circle',
            'color' => 'success',
            'title' => 'Menunggu Jadwal Psikotes',
            'text' => 'Form Anda telah diverifikasi dan dinyatakan lolos! Admin akan segera menghubungi Anda untuk jadwal psikotes. Pastikan nomor telepon Anda aktif.'
        ],
        'psikotes' => [
            'icon' => 'calendar-check',
            'color' => 'info',
            'title' => 'Psikotes Telah Dijadwalkan',
            'text' => 'Persiapkan diri Anda dengan baik. Harap hadir 15 menit sebelum jadwal dan membawa:<br>• KTP Asli<br>• Alat tulis<br>• Pakaian rapi'
        ],
        'ditolak_psikotes' => [
            'icon' => 'times-circle',
            'color' => 'danger',
            'title' => 'Tidak Lolos Psikotes',
            'text' => 'Mohon maaf, hasil psikotes Anda belum memenuhi standar yang kami butuhkan. Kami menghargai usaha Anda dan semoga sukses di kesempatan lainnya.'
        ],
        'lolos_psikotes' => [
            'icon' => 'check-circle',
            'color' => 'success',
            'title' => 'Menunggu Jadwal Interview',
            'text' => 'Selamat! Anda lolos tahap psikotes. Tim HR akan menghubungi Anda untuk penjadwalan interview. Persiapkan diri untuk tahap wawancara.'
        ],
        'interview' => [
            'icon' => 'user-tie',
            'color' => 'info',
            'title' => 'Interview Telah Dijadwalkan',
            'text' => 'Tips Interview:<br>• Datang tepat waktu<br>• Berpakaian formal<br>• Siapkan dokumen pendukung<br>• Pelajari profil perusahaan<br>• Percaya diri dan jujur'
        ],
        'ditolak_interview' => [
            'icon' => 'times-circle',
            'color' => 'danger',
            'title' => 'Tidak Lolos Interview',
            'text' => 'Mohon maaf, setelah evaluasi interview, kami memutuskan untuk melanjutkan dengan kandidat lain. Terima kasih atas waktu dan usaha Anda.'
        ],
        'diterima' => [
            'icon' => 'trophy',
            'color' => 'success',
            'title' => 'Selamat! Anda Diterima',
            'text' => 'Selamat bergabung di Politeknik NEST! Surat penerimaan resmi telah tersedia. Silakan login ke sistem kepegawaian menggunakan token yang dikirim ke email Anda.'
        ]
    ];
    
    return isset($instructions[$status]) ? $instructions[$status] : null;
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
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
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
            flex-wrap: wrap;
            gap: 15px;
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
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
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
            transition: width 0.6s ease;
        }
        
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
            background: #ffc107;
            color: white;
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
        
        .next-step-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .next-step-box.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .next-step-box.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .next-step-box.danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .next-step-box.info {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }
        
        .next-step-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .next-step-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .next-step-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        
        .next-step-text {
            font-size: 14px;
            line-height: 1.8;
            opacity: 0.95;
        }
        
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
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .surat-penerimaan-box {
            background: white;
            border: 2px solid #0D5E9D;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 25px;
            box-shadow: 0 8px 25px rgba(13, 94, 157, 0.15);
        }
        
        .surat-penolakan-box {
            background: white;
            border: 2px solid #ef4444;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 25px;
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.15);
        }
        
        .surat-header {
            background: linear-gradient(135deg, #0D5E9D, #0a4a7a);
            color: white;
            padding: 20px 25px;
            font-size: 17px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .surat-header.rejected {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .surat-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .surat-header-right {
            font-size: 13px;
            font-weight: 400;
            opacity: 0.95;
        }
        
        .surat-viewer {
            background: #f8f9fa;
            padding: 0;
        }
        
        .pdf-container {
            background: #525659;
            padding: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 600px;
        }
        
        .pdf-container.rejected {
            background: #3f3f46;
        }
        
        .pdf-container iframe {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .file-info {
            background: white;
            padding: 15px 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .file-info i {
            color: #0D5E9D;
        }
        
        .file-info.rejected i {
            color: #ef4444;
        }
        
        .surat-actions {
            display: flex;
            gap: 15px;
            padding: 20px 25px;
            background: #f8f9fa;
            justify-content: center;
            flex-wrap: wrap;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn-action {
            padding: 14px 35px;
            border-radius: 12px;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-action i {
            font-size: 16px;
        }
        
        .btn-download {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-download:hover {
            background: linear-gradient(135deg, #059669, #10b981);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-download-rejected {
            background: linear-gradient(135deg, #ef4444, #f87171);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .btn-download-rejected:hover {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
            color: white;
        }
        
        .token-info-box {
            background: white;
            border: 2px solid #F6C35A;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 25px;
            box-shadow: 0 8px 25px rgba(246, 195, 90, 0.15);
        }
        
        .token-info-header {
            background: linear-gradient(135deg, #F6C35A, #F19BB8);
            color: white;
            padding: 20px 25px;
            font-size: 17px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .token-info-content {
            padding: 35px 30px;
        }
        
        .token-step {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            align-items: start;
        }
        
        .token-step:last-child {
            margin-bottom: 0;
        }
        
        .step-number {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #F6C35A, #F19BB8);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 20px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(246, 195, 90, 0.3);
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-content strong {
            color: #1a1a1a;
            font-size: 16px;
            display: block;
            margin-bottom: 10px;
        }
        
        .step-content p {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }
        
        .step-content ol {
            color: #6b7280;
            font-size: 14px;
            line-height: 2;
            margin-bottom: 0;
            padding-left: 20px;
        }
        
        .step-content ol li {
            margin-bottom: 5px;
        }
        
        .alert-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 18px 22px;
            border-radius: 10px;
            font-size: 14px;
            color: #92400e;
            margin-top: 25px;
            line-height: 1.6;
        }
        
        .alert-note i {
            color: #f59e0b;
        }
        
        .token-info-action {
            padding: 30px;
            background: #f9fafb;
            text-align: center;
            border-top: 2px solid #e5e7eb;
        }
        
        .btn-login-pegawai {
            display: inline-block;
            background: linear-gradient(135deg, #F6C35A, #F19BB8);
            color: white !important;
            padding: 16px 45px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(246, 195, 90, 0.35);
        }
        
        .btn-login-pegawai:hover {
            background: linear-gradient(135deg, #f59e0b, #ec4899);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(246, 195, 90, 0.45);
            color: white !important;
        }
        
        .btn-login-pegawai i {
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 28px;
            }
            
            .lamaran-header {
                flex-direction: column;
            }
            
            .next-step-header {
                flex-direction: column;
                text-align: center;
            }
            
            .surat-header {
                flex-direction: column;
                text-align: center;
            }
            
            .surat-header-left,
            .surat-header-right {
                width: 100%;
                justify-content: center;
            }
            
            .pdf-container {
                padding: 10px;
            }
            
            .pdf-container iframe {
                height: 400px;
            }
            
            .token-step {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .step-content ol {
                text-align: left;
            }
            
            .btn-login-pegawai {
                width: 100%;
                padding: 15px 20px;
            }
            
            .btn-download {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../partials/navbar_req.php'; ?>
    
    <div class="main-content">
        <h1 class="page-title">Tracking Lamaran</h1>
        <p class="page-subtitle">Pantau status lamaran pekerjaan Anda</p>
        
        <div class="alert-box alert-info">
            <i class="fas fa-user me-2"></i>
            Menampilkan lamaran untuk: <strong><?php echo htmlspecialchars($email); ?></strong>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert-box alert-danger">
            <i class="fas fa-info-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo BASE_URL; ?>users/pelamar/dashboard.php" class="btn-form">
                <i class="fas fa-briefcase me-2"></i> Lihat Lowongan Tersedia
            </a>
        </div>
        <?php endif; ?>
        
        <?php foreach ($lamaran_list as $lamaran): 
            $progress = getProgressPercentage($lamaran['status_lamaran']);
            $badge = getStatusBadge($lamaran['status_lamaran']);
            $timeline = getTimelineSteps($lamaran['status_lamaran'], $lamaran);
            $nextStep = getNextStepInstruction($lamaran['status_lamaran']);
        ?>
        <div class="lamaran-card">
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
            
            <div class="progress-section">
                <div class="progress-label">
                    <span>Progress</span>
                    <span><?php echo $progress; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                </div>
            </div>
            
            <?php if ($nextStep): ?>
            <div class="next-step-box <?php echo $nextStep['color']; ?>">
                <div class="next-step-header">
                    <div class="next-step-icon">
                        <i class="fas fa-<?php echo $nextStep['icon']; ?>"></i>
                    </div>
                    <h3 class="next-step-title"><?php echo $nextStep['title']; ?></h3>
                </div>
                <div class="next-step-text">
                    <?php echo $nextStep['text']; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="timeline-section">
                <div class="timeline-title">Timeline Proses:</div>
                <?php foreach ($timeline as $step): ?>
                <div class="timeline-item">
                    <div class="timeline-icon <?php echo isset($step['failed']) && $step['failed'] ? 'failed' : (isset($step['pending']) && $step['pending'] ? 'pending' : ($step['completed'] ? 'completed' : 'pending')); ?>">
                        <i class="fas fa-<?php echo isset($step['failed']) && $step['failed'] ? 'times' : ($step['completed'] ? 'check' : 'hourglass-half'); ?>" style="font-size: 12px;"></i>
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
            
            <?php if ($lamaran['status_lamaran'] == 'lolos_administrasi'): ?>
            <div style="margin-top: 20px;">
                <a href="form_lanjutan.php?lamaran_id=<?php echo $lamaran['lamaran_id']; ?>" class="btn-form">
                    <i class="fas fa-file-alt me-2"></i> Lengkapi Form Data Diri
                </a>
            </div>
            
            <?php elseif ($lamaran['status_lamaran'] == 'psikotes' && !empty($lamaran['tanggal_psikotes'])): ?>
            <div class="alert-box alert-info">
                <strong><i class="fas fa-calendar-check me-2"></i>Detail Jadwal Psikotes:</strong><br>
                📅 <strong>Tanggal:</strong> <?php echo date('l, d F Y', strtotime($lamaran['tanggal_psikotes'])); ?><br>
                🕐 <strong>Waktu:</strong> <?php echo date('H:i', strtotime($lamaran['tanggal_psikotes'])); ?> WIB
            </div>
            
            <?php elseif ($lamaran['status_lamaran'] == 'interview' && !empty($lamaran['tanggal_interview'])): ?>
            <div class="alert-box alert-info">
                <strong><i class="fas fa-calendar-check me-2"></i>Detail Jadwal Interview:</strong><br>
                📅 <strong>Tanggal:</strong> <?php echo date('l, d F Y', strtotime($lamaran['tanggal_interview'])); ?><br>
                🕐 <strong>Waktu:</strong> <?php echo date('H:i', strtotime($lamaran['tanggal_interview'])); ?> WIB<br>
                <?php if (!empty($lamaran['lokasi_interview'])): ?>
                📍 <strong>Lokasi:</strong> <?php echo htmlspecialchars($lamaran['lokasi_interview']); ?>
                <?php endif; ?>
            </div>
            
            <?php elseif ($lamaran['status_lamaran'] == 'diterima'): ?>
            
            <?php if (!empty($lamaran['surat_resmi_path']) && file_exists($lamaran['surat_resmi_path'])): ?>
            <div class="surat-penerimaan-box">
                <div class="surat-header">
                    <div class="surat-header-left">
                        <i class="fas fa-file-pdf"></i>
                        <strong>Surat Penerimaan Resmi</strong>
                    </div>
                    <div class="surat-header-right">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo !empty($lamaran['surat_terkirim_at']) ? date('d F Y', strtotime($lamaran['surat_terkirim_at'])) : date('d F Y', strtotime($lamaran['tanggal_update'])); ?>
                    </div>
                </div>
                
                <div class="surat-viewer">
                    <?php 
                    $file_ext = strtolower(pathinfo($lamaran['surat_resmi_path'], PATHINFO_EXTENSION));
                    $file_url = BASE_URL . str_replace('../../', '', $lamaran['surat_resmi_path']);
                    ?>
                    
                    <div class="pdf-container">
                        <?php if ($file_ext == 'pdf'): ?>
                            <iframe src="<?php echo $file_url; ?>"></iframe>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-word" style="font-size: 80px; color: #2b579a; margin-bottom: 20px;"></i>
                                <h5 style="color: white;">Surat Penerimaan (<?php echo strtoupper($file_ext); ?>)</h5>
                                <p style="color: #d1d5db;">Klik tombol download di bawah untuk melihat surat</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="file-info">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            <?php echo basename($lamaran['surat_resmi_path']); ?>
                            <?php 
                            if (file_exists($lamaran['surat_resmi_path'])) {
                                $file_size = filesize($lamaran['surat_resmi_path']);
                                echo ' (' . round($file_size / 1024, 2) . ' KB)';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="surat-actions">
                    <a href="<?php echo $file_url; ?>" 
                       download 
                       class="btn-action btn-download">
                        <i class="fas fa-download"></i> Download Surat
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="alert-box alert-warning">
                <i class="fas fa-hourglass-half me-2"></i>
                <strong>Surat penerimaan resmi sedang diproses oleh admin.</strong><br>
                Anda akan menerima notifikasi email setelah surat tersedia untuk diunduh.
            </div>
            <?php endif; ?>
            
            <div class="token-info-box">
                <div class="token-info-header">
                    <i class="fas fa-key me-2"></i>
                    <strong>Informasi Login Sistem Kepegawaian</strong>
                </div>
                <div class="token-info-content">
                    <div class="token-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <strong>Token Login Telah Dikirim ke Email Anda</strong>
                            <p>Silakan cek inbox atau folder spam di email: 
                                <strong><?php echo htmlspecialchars($lamaran['email_aktif']); ?></strong>
                            </p>
                        </div>
                    </div>
                    
                    <div class="token-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <strong>Cara Login Sistem Kepegawaian</strong>
                            <ol style="margin: 10px 0 0 0; padding-left: 20px;">
                                <li>Klik tombol "Login ke Sistem Kepegawaian" di bawah</li>
                                <li>Email Anda akan otomatis terisi</li>
                                <li>Masukkan token yang Anda terima via email</li>
                                <li>Anda akan diminta membuat password baru pada login pertama</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="token-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <strong>Belum Menerima Email Token?</strong>
                            <p style="margin: 5px 0 0 0;">
                                Hubungi kami melalui:<br>
                                <i class="fas fa-envelope me-1"></i> Email: <strong>sdm@polnest.ac.id</strong><br>
                                <i class="fas fa-phone me-1"></i> WhatsApp: <strong>+62 811-2951-003</strong>
                            </p>
                        </div>
                    </div>
                    
                    <div class="alert-note">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Penting:</strong> Token hanya berlaku untuk login pertama kali. 
                        Setelah login, Anda <strong>wajib membuat password baru</strong> untuk keamanan akun.
                    </div>
                </div>
                
                <div class="token-info-action">
                    <a href="<?php echo BASE_URL; ?>auth/login_pegawai_new.php?email=<?php echo urlencode($lamaran['email_aktif']); ?>" 
                       class="btn-login-pegawai">
                        <i class="fas fa-sign-in-alt me-2"></i> Login ke Sistem Kepegawaian
                    </a>
                </div>
            </div>
            
            <?php elseif (in_array($lamaran['status_lamaran'], ['ditolak_interview', 'ditolak_psikotes', 'ditolak_form', 'tidak_lolos_administrasi'])): ?>
            
            <?php if (!empty($lamaran['surat_resmi_path']) && file_exists($lamaran['surat_resmi_path'])): ?>
            <div class="surat-penolakan-box">
                <div class="surat-header rejected">
                    <div class="surat-header-left">
                        <i class="fas fa-file-pdf"></i>
                        <strong>Surat Pemberitahuan Hasil Seleksi</strong>
                    </div>
                    <div class="surat-header-right">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo !empty($lamaran['surat_terkirim_at']) ? date('d F Y', strtotime($lamaran['surat_terkirim_at'])) : date('d F Y', strtotime($lamaran['tanggal_update'])); ?>
                    </div>
                </div>
                
                <div class="surat-viewer">
                    <?php 
                    $file_ext = strtolower(pathinfo($lamaran['surat_resmi_path'], PATHINFO_EXTENSION));
                    $file_url = BASE_URL . str_replace('../../', '', $lamaran['surat_resmi_path']);
                    ?>
                    
                    <div class="pdf-container rejected">
                        <?php if ($file_ext == 'pdf'): ?>
                            <iframe src="<?php echo $file_url; ?>"></iframe>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-word" style="font-size: 80px; color: #ef4444; margin-bottom: 20px;"></i>
                                <h5 style="color: white;">Surat Pemberitahuan (<?php echo strtoupper($file_ext); ?>)</h5>
                                <p style="color: #d1d5db;">Klik tombol download di bawah untuk melihat surat</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="file-info rejected">
                        <i class="fas fa-info-circle"></i>
                        <span>
                            <?php echo basename($lamaran['surat_resmi_path']); ?>
                            <?php 
                            if (file_exists($lamaran['surat_resmi_path'])) {
                                $file_size = filesize($lamaran['surat_resmi_path']);
                                echo ' (' . round($file_size / 1024, 2) . ' KB)';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="surat-actions">
                    <a href="<?php echo $file_url; ?>" 
                       download 
                       class="btn-action btn-download-rejected">
                        <i class="fas fa-download"></i> Download Surat
                    </a>
                </div>
            </div>
            
            <div class="alert-box alert-info" style="margin-top: 25px;">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Jangan berkecil hati!</strong><br>
                Terima kasih atas partisipasi Anda dalam proses seleksi ini. 
                Kami menghargai waktu dan usaha yang telah Anda berikan. 
                Semoga sukses untuk kesempatan berikutnya!
            </div>
            <?php else: ?>
            <div class="alert-box alert-warning">
                <i class="fas fa-hourglass-half me-2"></i>
                <strong>Surat pemberitahuan sedang diproses oleh admin.</strong><br>
                Anda akan menerima notifikasi email setelah surat tersedia.
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            <?php if (!empty($lamaran['catatan_admin'])): ?>
            <div class="alert-box alert-warning" style="margin-top: 20px;">
                <strong><i class="fas fa-comment-dots me-2"></i>Catatan dari Admin:</strong><br>
                <?php echo nl2br(htmlspecialchars($lamaran['catatan_admin'])); ?>
            </div>
            <?php endif; ?>
            
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php include '../partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>