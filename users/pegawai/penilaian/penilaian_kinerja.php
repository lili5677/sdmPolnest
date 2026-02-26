<?php
session_start();

//Cek login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../../auth/login_pegawai.php");
    exit;
}

require_once '../../../config/check_completion.php'; 
require_once '../../../config/database.php';
$check_result = checkPegawaiCompletion($conn, $_SESSION['pegawai_id']);

//Jika data belum lengkap, redirect ke administrasi
if (!$check_result['is_complete']) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'message' => 'Anda harus melengkapi data administrasi kepegawaian terlebih dahulu sebelum mengakses halaman ini.'
    ];
    header("Location: ../../../users/pegawai/administrasi.php");
    exit;
}

//Ambil pegawai_id
$pegawai_id = $_SESSION['pegawai_id'];

//Ambil template penilaian 
$stmt_templates = $conn->prepare("
    SELECT 
        pt.*,
        COUNT(pi.indikator_id) as jumlah_indikator,
        MAX(pk.penilaian_id) as penilaian_id,
        MAX(pk.created_at) as tanggal_isi,
        MAX(pk.status_verifikasi) as status_verifikasi
    FROM penilaian_template pt
    LEFT JOIN penilaian_indikator pi ON pt.template_id = pi.template_id
    LEFT JOIN penilaian_kinerja pk ON pt.template_id = pk.template_id AND pk.pegawai_id = ?
    GROUP BY pt.template_id, pt.nama_template, pt.periode, pt.created_by, pt.created_at, pt.updated_at
    ORDER BY pt.periode DESC
");
$stmt_templates->execute([$pegawai_id]);
$templates = $stmt_templates->fetchAll(PDO::FETCH_ASSOC);

// Handle success message
$success_message = null;
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$error_message = null;
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Kinerja - Politeknik NEST</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fef3e2;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .main-content {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            margin-top: 20px;
            margin-bottom: 40px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 16px;
            color: #666;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        /* Template Cards */
        .templates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .template-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: #f5a3b4;
        }
        
        .template-card.completed {
            border-color: #28a745;
            background: linear-gradient(to bottom right, #ffffff, #f1f9f4);
        }
        
        .template-card.completed::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: #28a745;
            clip-path: polygon(100% 0, 0 0, 100% 100%);
        }
        
        .template-card.completed::after {
            content: '✓';
            position: absolute;
            top: 5px;
            right: 8px;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }
        
        .template-header {
            margin-bottom: 20px;
        }
        
        .template-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .template-periode {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .template-body {
            margin-bottom: 20px;
        }
        
        .template-info {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .info-item {
            flex: 1;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #2c5f7d;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-belum {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-sudah {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-dilihat {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .template-footer {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #2c5f7d;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1f4459;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 95, 125, 0.3);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .templates-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .template-info {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../partials/navbar.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="page-header">
                <h1>Penilaian Kinerja Pegawai</h1>
                <p>Evaluasi kinerja berbasis penilaian</p>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill" style="font-size: 20px;"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 20px;"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (count($templates) > 0): ?>
                <div class="templates-grid">
                    <?php foreach ($templates as $template): 
                        $is_completed = !empty($template['penilaian_id']);
                        $status_class = $is_completed ? 'completed' : '';
                    ?>
                        <div class="template-card <?php echo $status_class; ?>">
                            <div class="template-header">
                                <div class="template-title">
                                    <i class="bi bi-clipboard-check"></i>
                                    <?php echo htmlspecialchars($template['nama_template']); ?>
                                </div>
                                <div class="template-periode">
                                    <i class="bi bi-calendar-event"></i>
                                    Periode: <?php echo date('F Y', strtotime($template['periode'])); ?>
                                </div>
                            </div>
                            
                            <div class="template-body">
                                <div class="template-info">
                                    <div class="info-item">
                                        <div class="info-label">Jumlah Indikator</div>
                                        <div class="info-value"><?php echo $template['jumlah_indikator']; ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Status</div>
                                        <div>
                                            <?php if ($is_completed): ?>
                                                <span class="status-badge badge-sudah">
                                                    <i class="bi bi-check-circle-fill"></i> Sudah Diisi
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge badge-belum">
                                                    <i class="bi bi-clock"></i> Belum Diisi
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($is_completed): ?>
                                    <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 8px; font-size: 13px; color: #666;">
                                        <i class="bi bi-info-circle"></i>
                                        Diisi pada: <?php echo date('d F Y, H:i', strtotime($template['tanggal_isi'])); ?> WIB
                                        <?php if ($template['status_verifikasi'] == 'sudah_dilihat'): ?>
                                            <br>
                                            <span class="status-badge badge-dilihat" style="margin-top: 8px; display: inline-block;">
                                                <i class="bi bi-eye-fill"></i> Sudah Dilihat Admin
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="template-footer">
                                <?php if ($is_completed): ?>
                                    <a href="form.php?template_id=<?php echo $template['template_id']; ?>" class="btn btn-success">
                                        <i class="bi bi-pencil-square"></i>
                                        Lihat / Edit Penilaian
                                    </a>
                                <?php else: ?>
                                    <a href="form.php?template_id=<?php echo $template['template_id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-play-circle-fill"></i>
                                        Mulai Penilaian
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>Belum Ada Template Penilaian</h3>
                    <p>Saat ini belum ada template penilaian yang tersedia. Silakan hubungi admin untuk informasi lebih lanjut.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../../partials/footer.php'; ?>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>