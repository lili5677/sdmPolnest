<?php
session_start();
require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle tambah Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_template'])) {
    $nama_template          = trim($_POST['nama_template']);
    $periode                = $_POST['periode'];
    $indikator_names        = $_POST['indikator_name']        ?? [];
    $indikator_keterangans  = $_POST['indikator_keterangan']  ?? [];

    $errors = [];

    if (empty($nama_template)) $errors[] = "Nama template wajib diisi.";
    if (empty($periode))       $errors[] = "Periode wajib diisi.";

    $valid_names = array_filter(array_map('trim', $indikator_names));
    if (empty($valid_names))   $errors[] = "Minimal 1 indikator harus ditambahkan.";

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("
                INSERT INTO penilaian_template (nama_template, periode, created_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$nama_template, $periode . '-01', $_SESSION['user_id']]);
            $template_id = $conn->lastInsertId();

            $stmt_ind = $conn->prepare("
                INSERT INTO penilaian_indikator (template_id, nama_indikator, keterangan, urutan)
                VALUES (?, ?, ?, ?)
            ");

            $urutan = 1;
            foreach ($indikator_names as $i => $name) {
                $name = trim($name);
                if (!empty($name)) {
                    $ket = trim($indikator_keterangans[$i] ?? '');
                    $stmt_ind->execute([$template_id, $name, $ket, $urutan]);
                    $urutan++;
                }
            }

            $conn->commit();
            $_SESSION['success_message'] = "Template penilaian berhasil dibuat dan siap digunakan pegawai!";
            header("Location: template.php");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Gagal membuat template: " . $e->getMessage();
        }
    }
}

// Handle Delete Template 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $del_id = (int)$_POST['template_id'];

    try {
        $stmt_check = $conn->prepare("
            SELECT COUNT(*) as total FROM penilaian_kinerja WHERE template_id = ?
        ");
        $stmt_check->execute([$del_id]);
        $has_data = $stmt_check->fetch(PDO::FETCH_ASSOC)['total'] > 0;

        if ($has_data) {
            $_SESSION['error_message'] = "Template tidak dapat dihapus karena sudah ada penilaian yang menggunakan template ini.";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM penilaian_template WHERE template_id = ?");
            $stmt_del->execute([$del_id]);
            $_SESSION['success_message'] = "Template berhasil dihapus.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Gagal menghapus template: " . $e->getMessage();
    }

    header("Location: template.php");
    exit;
}

// Ambil semua template + statistik lngkap
$stmt = $conn->prepare("
    SELECT 
        pt.*,
        COUNT(DISTINCT pi.indikator_id) as jumlah_indikator,
        COUNT(DISTINCT pk.penilaian_id) as jumlah_penilaian,
        COUNT(DISTINCT CASE WHEN pk.status_verifikasi = 'belum_dilihat' THEN pk.penilaian_id END) as belum_dilihat,
        COUNT(DISTINCT CASE WHEN pk.status_verifikasi = 'sudah_dilihat' THEN pk.penilaian_id END) as sudah_dilihat
    FROM penilaian_template pt
    LEFT JOIN penilaian_indikator pi ON pt.template_id = pi.template_id
    LEFT JOIN penilaian_kinerja pk ON pt.template_id = pk.template_id
    GROUP BY pt.template_id
    ORDER BY pt.created_at DESC
");
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success_message = $_SESSION['success_message'] ?? null;
$error_message   = $_SESSION['error_message']   ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Template Penilaian - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
     <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8fafc;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 28px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .page-header h1 { 
            font-size: 24px; 
            font-weight: 700; 
            margin-bottom: 6px;
            letter-spacing: -0.025em;
        }
        .page-header p  { 
            font-size: 14px; 
            opacity: 0.9;
            font-weight: 400;
        }

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
            border: 1px solid;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-success { 
            background: #f0fdf4; 
            border-color: #86efac; 
            color: #166534; 
        }
        .alert-danger  { 
            background: #fef2f2; 
            border-color: #fca5a5; 
            color: #991b1b; 
        }
        .alert ul { margin-left: 20px; margin-top: 5px; }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(21, 101, 192, 0.35);
        }

        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }

        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }

        .btn-info { background: #17a2b8; color: white; }
        .btn-info:hover { background: #138496; }

        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }

        .btn-sm { padding: 7px 14px; font-size: 13px; }

        .action-bar {
            margin-bottom: 22px;
        }

        .layout-container {
            display: grid;
            grid-template-columns: 480px 1fr;
            gap: 28px;
            align-items: start;
        }

        /* Card  */
        .card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #f1f5f9;
        }

        .card-sticky {
            position: sticky;
            top: 30px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 9px;
            letter-spacing: -0.025em;
        }

        /* Form */
        .form-group { margin-bottom: 18px; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 7px;
        }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #334155;
            box-shadow: 0 0 0 3px rgba(51, 65, 85, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }

        /* Indikator */
        .indikator-container {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 14px;
            background: #f8fafc;
        }

        .indikator-item {
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .indikator-item:last-child { margin-bottom: 0; }

        .indikator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .indikator-number {
            background: #1976d2;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .btn-remove-ind {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 11px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }

        .btn-remove-ind:hover {
            background: #c82333;
        }

        .btn-add-indikator {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .btn-add-indikator:hover { 
            background: #218838; 
            transform: translateY(-1px); 
        }

        .scale-info-box {
            margin-top: 14px;
            padding: 12px 15px;
            background: #e3f0ff;
            border-radius: 8px;
            font-size: 13px;
            color: #1565c0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Templates List */
        .templates-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .template-item {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1.5px solid #f1f5f9;
            transition: all 0.3s;
        }

        .template-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }

        .template-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            letter-spacing: -0.025em;
        }

        .template-periode {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .template-actions {
            display: flex;
            gap: 6px;
        }

        .template-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .tstat {
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .tstat-value {
            font-size: 26px;
            font-weight: 800;
            color: #1565c0;
            letter-spacing: -0.05em;
        }

        .tstat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Status row inside template */
        .template-status {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .tstat-mini {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
        }

        .tstat-mini-belum { background: #fef3c7; color: #92400e; }
        .tstat-mini-sudah { background: #d1fae5; color: #065f46; }

        .template-footer {
            display: flex;
            gap: 8px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 2px dashed #e2e8f0;
        }

        .empty-state i { 
            font-size: 3.5rem; 
            color: #cbd5e1; 
            display: block; 
            margin-bottom: 16px; 
        }
        .empty-state h3 { 
            font-size: 18px; 
            color: #64748b; 
            margin-bottom: 8px;
            font-weight: 700;
        }
        .empty-state p { 
            font-size: 14px; 
            color: #94a3b8; 
        }

       /* Responsive untuk sidebar */
        @media (max-width: 1200px) {
            .layout-container {
                grid-template-columns: 1fr;
            }
            .card-sticky {
                position: static;
            }
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px !important;
                padding: 20px;
            }
            
            .page-header {
                padding: 20px 24px;
            }
            
            .page-header h1 {
                font-size: 20px;
            }
            
            .layout-container {
                grid-template-columns: 1fr;
            }
            
            .card-sticky {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .main-content { 
                margin-left: 70px !important;
                padding: 15px; 
            }
            
            .page-header {
                padding: 18px 20px;
            }
            
            .page-header h1 {
                font-size: 18px;
            }
            
            .page-header p {
                font-size: 13px;
            }
            
            .template-stats { 
                grid-template-columns: 1fr; 
            }
            
            .layout-container {
                grid-template-columns: 1fr;
            }
            
            .card-sticky {
                position: static;
            }
            
            .template-footer {
                flex-wrap: wrap;
            }
            
            .btn-sm {
                font-size: 12px;
                padding: 6px 10px;
            }
            
            .action-bar {
                margin-bottom: 16px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 70px !important;
                padding: 12px;
            }
            
            .page-header {
                padding: 16px;
                margin-bottom: 20px;
            }
            
            .page-header h1 {
                font-size: 16px;
            }
            
            .page-header p {
                font-size: 12px;
            }
            
            .template-item {
                padding: 16px;
            }
            
            .card {
                padding: 20px;
            }
            
            .btn {
                font-size: 13px;
                padding: 8px 16px;
            }
        }

        .swal2-popup {
            font-family: 'Inter', sans-serif !important;
            border-radius: 16px !important;
        }
        
        .swal2-title {
            font-size: 20px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
        }
        
        .swal2-html-container {
            font-size: 14px !important;
            color: #64748b !important;
        }
        
        .swal2-confirm {
            background: #dc3545 !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            padding: 10px 24px !important;
        }
        
        .swal2-cancel {
            background: #6c757d !important;
            border-radius: 10px !important;
            font-weight: 600 !important;
            padding: 10px 24px !important;
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h1><i class=""></i> Kelola Template Penilaian</h1>
            <p>Buat template baru dan kelola daftar template penilaian kinerja pegawai</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill" style="font-size:18px; flex-shrink:0;"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:18px; flex-shrink:0;"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:18px; flex-shrink:0;"></i>
                <div>
                    <strong>Terjadi kesalahan:</strong>
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="penilaianKinerja.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Daftar Penilaian
            </a>
        </div>

        <div class="layout-container">
            
            <!-- form template kiri -->
            <div class="card card-sticky">
                <h2 class="card-title">
                    <i class=""></i> Buat Template Baru
                </h2>

                <form method="POST" id="templateForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Nama Template <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="text" name="nama_template" class="form-input"
                                   placeholder="Contoh: Penilaian Kinerja Semester 1 2026"
                                   value="<?php echo htmlspecialchars($_POST['nama_template'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                Periode (Bulan & Tahun) <span style="color: #ef4444;">*</span>
                            </label>
                            <input type="month" name="periode" class="form-input"
                                   value="<?php echo htmlspecialchars($_POST['periode'] ?? ''); ?>"
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Kriteria / Indikator Penilaian <span style="color: #ef4444;">*</span>
                        </label>

                        <div class="indikator-container" id="indikatorContainer">
                            <div class="indikator-item">
                                <div class="indikator-header">
                                    <div class="indikator-number">1</div>
                                    <button type="button" class="btn-remove-ind" onclick="removeIndikator(this)">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                                <div class="form-group" style="margin-bottom:10px;">
                                    <input type="text" name="indikator_name[]" class="form-input"
                                           placeholder="Nama Indikator (contoh: Integritas)" required>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <input type="text" name="indikator_keterangan[]" class="form-input"
                                           placeholder="Keterangan tambahan (opsional)">
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-add-indikator" onclick="addIndikator()">
                            <i class="bi bi-plus-circle"></i> Tambah Indikator
                        </button>

                    </div>

                    <div style="display: flex; gap: 12px; margin-top: 10px;">
                        <button type="submit" name="create_template" class="btn btn-primary">
                            <i class="bi bi-save"></i> Simpan Template
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="bi bi-x-circle"></i> Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- daftar template (kanan) -->
            <div>
                <div class="card" style="margin-bottom: 16px;">
                    <h2 class="card-title">
                        <i class=""></i> Daftar Template Penilaian
                        <span style="margin-left:auto; font-size:13px; font-weight:600; color:#64748b;">
                            <?php echo count($templates); ?> template
                        </span>
                    </h2>
                </div>

                <?php if (count($templates) > 0): ?>
                    <div class="templates-list">
                        <?php foreach ($templates as $tpl): ?>
                            <div class="template-item">
                                <div class="template-header">
                                    <div>
                                        <div class="template-title">
                                            <?php echo htmlspecialchars($tpl['nama_template']); ?>
                                        </div>
                                        <div class="template-periode">
                                            <i class="bi bi-calendar-event"></i>
                                            Periode: <?php echo date('F Y', strtotime($tpl['periode'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistik -->
                                <div class="template-stats">
                                    <div class="tstat">
                                        <div class="tstat-value"><?php echo $tpl['jumlah_indikator']; ?></div>
                                        <div class="tstat-label">Indikator</div>
                                    </div>
                                    <div class="tstat">
                                        <div class="tstat-value"><?php echo $tpl['jumlah_penilaian']; ?></div>
                                        <div class="tstat-label">Terisi</div>
                                    </div>
                                </div>

                                <!-- Status verifikasi -->
                                <?php if ($tpl['jumlah_penilaian'] > 0): ?>
                                    <div class="template-status">
                                        <span class="tstat-mini tstat-mini-belum">
                                            <i class="bi bi-clock"></i>
                                            <?php echo $tpl['belum_dilihat']; ?> Belum Dilihat
                                        </span>
                                        <span class="tstat-mini tstat-mini-sudah">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <?php echo $tpl['sudah_dilihat']; ?> Sudah Dilihat
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-bottom:16px;">
                                        <span style="font-size:12px; color:#94a3b8; font-style:italic;">
                                            <i class="bi bi-info-circle"></i> Belum ada pegawai yang mengisi
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Aksi -->
                                <div class="template-footer">
                                    <a href="penilaianKinerja.php?template_id=<?php echo $tpl['template_id']; ?>"
                                       class="btn btn-info btn-sm" style="flex:1;">
                                        <i class="bi bi-eye"></i> Lihat Penilaian
                                    </a>
                                    <a href="edit_template.php?id=<?php echo $tpl['template_id']; ?>"
                                       class="btn btn-warning btn-sm"
                                       title="Edit Template">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                   
                                    <button type="button" 
                                            class="btn btn-danger btn-sm"
                                            onclick="confirmDelete(<?php echo $tpl['template_id']; ?>, '<?php echo addslashes($tpl['nama_template']); ?>')"
                                            title="Hapus Template">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>Belum Ada Template</h3>
                        <p>Buat template penilaian baru menggunakan form di sebelah kiri.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <!--  Form untuk Delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="delete_template" value="1">
        <input type="hidden" name="template_id" id="deleteTemplateId">
    </form>

    <script>
        let indikatorCount = 1;

        function addIndikator() {
            indikatorCount++;
            const container = document.getElementById('indikatorContainer');
            const item = document.createElement('div');
            item.className = 'indikator-item';
            item.innerHTML = `
                <div class="indikator-header">
                    <div class="indikator-number">${indikatorCount}</div>
                    <button type="button" class="btn-remove-ind" onclick="removeIndikator(this)">
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </div>
                <div class="form-group" style="margin-bottom:10px;">
                    <input type="text" name="indikator_name[]" class="form-input"
                           placeholder="Nama Indikator" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <input type="text" name="indikator_keterangan[]" class="form-input"
                           placeholder="Keterangan tambahan (opsional)">
                </div>
            `;
            container.appendChild(item);
            updateNumbers();
        }

        function removeIndikator(btn) {
            const items = document.querySelectorAll('.indikator-item');
            if (items.length > 1) {
                btn.closest('.indikator-item').remove();
                updateNumbers();
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak Bisa Dihapus',
                    text: 'Minimal harus ada 1 indikator!',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#1565c0'
                });
            }
        }

        function updateNumbers() {
            document.querySelectorAll('.indikator-item').forEach((item, i) => {
                item.querySelector('.indikator-number').textContent = i + 1;
            });
            indikatorCount = document.querySelectorAll('.indikator-item').length;
        }

        function resetForm() {
            document.getElementById('indikatorContainer').innerHTML = `
                <div class="indikator-item">
                    <div class="indikator-header">
                        <div class="indikator-number">1</div>
                        <button type="button" class="btn-remove-ind" onclick="removeIndikator(this)">
                            <i class="bi bi-trash"></i> Hapus
                        </button>
                    </div>
                    <div class="form-group" style="margin-bottom:10px;">
                        <input type="text" name="indikator_name[]" class="form-input"
                               placeholder="Nama Indikator (contoh: Integritas)" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <input type="text" name="indikator_keterangan[]" class="form-input"
                               placeholder="Keterangan tambahan (opsional)">
                    </div>
                </div>
            `;
            indikatorCount = 1;
            document.getElementById('templateForm').reset();
        }

        // Delete Confirmation 
        function confirmDelete(templateId, templateName) {
            Swal.fire({
                title: 'Hapus Template?',
                html: `Apakah Anda yakin ingin menghapus template:<br><strong>"${templateName}"</strong>?<br><br><small style="color: #dc3545;">⚠️ Template yang sudah digunakan untuk penilaian tidak dapat dihapus.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bi bi-trash"></i> Ya, Hapus',
                cancelButtonText: '<i class="bi bi-x-circle"></i> Batal',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit form delete
                    document.getElementById('deleteTemplateId').value = templateId;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(a => {
                a.style.transition = 'opacity 0.5s';
                a.style.opacity = '0';
                setTimeout(() => a.remove(), 500);
            });
        }, 6000);
    </script>
</body>
</html>