<?php
session_start();
require_once '../../../config/database.php';

// Cek login pegawai
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../../auth/login_pegawai.php");
    exit;
}

$pegawai_id = $_SESSION['pegawai_id'];

// Ambil template_id dari URL
if (!isset($_GET['template_id']) || empty($_GET['template_id'])) {
    $_SESSION['error_message'] = "Template tidak valid.";
    header("Location: penilaian_kinerja.php");
    exit;
}

$template_id = (int)$_GET['template_id'];

// Ambil data pegawai
$stmt = $conn->prepare("
    SELECT p.*, sk.jabatan, sk.unit_kerja 
    FROM pegawai p 
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id 
    WHERE p.pegawai_id = ?
");
$stmt->execute([$pegawai_id]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    die("Data pegawai tidak ditemukan.");
}

// Ambil template berdasarkan template_id
$stmt_template = $conn->prepare("
    SELECT * FROM penilaian_template 
    WHERE template_id = ?
");
$stmt_template->execute([$template_id]);
$template = $stmt_template->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    $_SESSION['error_message'] = "Template penilaian tidak ditemukan.";
    header("Location: penilaian_kinerja.php");
    exit;
}

// Cek apakah pegawai sudah mengisi penilaian untuk template ini
$stmt_check = $conn->prepare("
    SELECT pk.*, 
           GROUP_CONCAT(CONCAT(pkd.indikator_id, ':', pkd.nilai) SEPARATOR '|') as nilai_data
    FROM penilaian_kinerja pk
    LEFT JOIN penilaian_kinerja_detail pkd ON pk.penilaian_id = pkd.penilaian_id
    WHERE pk.pegawai_id = ? AND pk.template_id = ?
    GROUP BY pk.penilaian_id
");
$stmt_check->execute([$pegawai_id, $template_id]);
$existing_penilaian = $stmt_check->fetch(PDO::FETCH_ASSOC);

// Parse nilai yang sudah ada
$existing_values = [];
if ($existing_penilaian && !empty($existing_penilaian['nilai_data'])) {
    $nilai_items = explode('|', $existing_penilaian['nilai_data']);
    foreach ($nilai_items as $item) {
        list($ind_id, $nilai) = explode(':', $item);
        $existing_values[$ind_id] = $nilai;
    }
}

// Ambil indikator untuk template ini
$stmt_indikator = $conn->prepare("
    SELECT * FROM penilaian_indikator 
    WHERE template_id = ? 
    ORDER BY urutan ASC
");
$stmt_indikator->execute([$template_id]);
$indikator_list = $stmt_indikator->fetchAll(PDO::FETCH_ASSOC);

if (empty($indikator_list)) {
    $_SESSION['error_message'] = "Kriteria penilaian belum tersedia untuk template ini.";
    header("Location: ../dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_penilaian'])) {
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $jabatan = trim($_POST['jabatan']);
    $unit_kerja = trim($_POST['unit_kerja']);
    $catatan = trim($_POST['catatan'] ?? '');
    
    $errors = [];
    
    // Validasi nama lengkap
    if (empty($nama_lengkap)) {
        $errors[] = "Nama lengkap wajib diisi";
    }
    
    // Validasi semua indikator terisi
    $all_filled = true;
    foreach ($indikator_list as $indikator) {
        $key = 'nilai_' . $indikator['indikator_id'];
        if (!isset($_POST[$key]) || empty($_POST[$key])) {
            $all_filled = false;
            break;
        }
    }
    
    if (!$all_filled) {
        $errors[] = "Semua kriteria penilaian wajib diisi";
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            if ($existing_penilaian) {
                // UPDATE mode
                $penilaian_id = $existing_penilaian['penilaian_id'];
                
                $stmt_update = $conn->prepare("
                    UPDATE penilaian_kinerja 
                    SET catatan = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE penilaian_id = ?
                ");
                $stmt_update->execute([$catatan, $penilaian_id]);
                
                // Delete detail lama
                $stmt_delete = $conn->prepare("DELETE FROM penilaian_kinerja_detail WHERE penilaian_id = ?");
                $stmt_delete->execute([$penilaian_id]);
                
                $message = "Penilaian kinerja berhasil diperbarui!";
                
            } else {
                // INSERT mode
                $stmt_insert = $conn->prepare("
                    INSERT INTO penilaian_kinerja 
                    (pegawai_id, template_id, periode, catatan, created_by) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_insert->execute([
                    $pegawai_id,
                    $template_id,
                    $template['periode'],
                    $catatan,
                    $_SESSION['user_id']
                ]);
                
                $penilaian_id = $conn->lastInsertId();
                $message = "Penilaian kinerja berhasil dikirim!";
            }
            
            // Insert detail nilai
            $stmt_detail = $conn->prepare("
                INSERT INTO penilaian_kinerja_detail 
                (penilaian_id, indikator_id, nilai) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($indikator_list as $indikator) {
                $key = 'nilai_' . $indikator['indikator_id'];
                $nilai = $_POST[$key];
                $stmt_detail->execute([$penilaian_id, $indikator['indikator_id'], $nilai]);
            }
            
            $conn->commit();
            
            $_SESSION['success_message'] = $message;
            header("Location: form.php?template_id=" . $template_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Gagal menyimpan penilaian: " . $e->getMessage();
        }
    }
}

// Ambil success message dari session
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
            max-width: 1000px;
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
        
        .alert ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        /* Form Section */
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid #f5a3b4;
            text-align: center;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-input,
        .form-textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
            background: white;
        }
        
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #f5a3b4;
            box-shadow: 0 0 0 3px rgba(245, 163, 180, 0.1);
        }
        
        .form-input[readonly] {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        /* Table Styling */
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        table thead tr {
            background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);
        }
        
        table th {
            color: white;
            font-weight: 600;
            padding: 18px 20px;
            border: 1px solid #dee2e6;
        }
        
        table td {
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        
.btn-option {
    display: inline-block;
    padding: 10px 18px;
    border: 2px solid;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Default state - outline style */
.btn-option[data-color="#28a745"] {
    background: white;
    border-color: #28a745;
    color: #28a745;
}

.btn-option[data-color="#17a2b8"] {
    background: white;
    border-color: #17a2b8;
    color: #17a2b8;
}

.btn-option[data-color="#ffc107"] {
    background: white;
    border-color: #ffc107;
    color: #e0a800;
}

.btn-option[data-color="#dc3545"] {
    background: white;
    border-color: #dc3545;
    color: #dc3545;
}

.btn-option:hover {
    opacity: 0.8;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

input[type="radio"]:checked + .btn-option[data-color="#28a745"] {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
    color: white;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
}

input[type="radio"]:checked + .btn-option[data-color="#17a2b8"] {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border-color: #17a2b8;
    color: white;
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
}

input[type="radio"]:checked + .btn-option[data-color="#ffc107"] {
    background: linear-gradient(135deg, #ffc107 0%, #f39c12 100%);
    border-color: #ffc107;
    color: #333;
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
}

input[type="radio"]:checked + .btn-option[data-color="#dc3545"] {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border-color: #dc3545;
    color: white;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
}

/* Add subtle glow effect on hover */
input[type="radio"]:not(:checked) + .btn-option:hover {
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            display: inline-flex;
            align-items: center;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        /* Info Badge */
        .info-badge {
            display: inline-block;
            background: #d1ecf1;
            color: #0c5460;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            table, thead, tbody, th, td, tr {
                display: block;
            }
            
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            tr {
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                overflow: hidden;
            }
            
            td {
                border: none;
                position: relative;
                padding: 15px !important;
            }
            
            td:first-child {
                background: #f8f9fa;
                font-weight: 600;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .btn-option {
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <?php include '../../partials/navbar.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="page-header">
                <h1>Penilaian dan Kinerja Pegawai</h1>
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
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 20px;"></i>
                    <div>
                        <strong>Terjadi kesalahan:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-section">
                <h2 class="section-title">Formulir Penilaian Kinerja Tim</h2>
                
                <?php if ($existing_penilaian): ?>
                    <div class="info-badge">
                        <i class="bi bi-info-circle-fill"></i>
                        Mode Edit - Anda dapat memperbarui penilaian yang sudah dikirim
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <!-- Data Pegawai -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-input" 
                                   value="<?php echo htmlspecialchars($pegawai['nama_lengkap']); ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jabatan / Posisi</label>
                            <input type="text" name="jabatan" class="form-input" 
                                   value="<?php echo htmlspecialchars($pegawai['jabatan'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group full-width" style="margin-bottom: 30px;">
                        <label class="form-label">Unit kerja / Divisi</label>
                        <input type="text" name="unit_kerja" class="form-input" 
                               value="<?php echo htmlspecialchars($pegawai['unit_kerja'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <!-- Kriteria Penilaian -->
                    <div style="margin-top: 30px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: linear-gradient(135deg, #F19BB8 0%, #F6C35A 100%);">
                                    <th style="padding: 18px 20px; text-align: left; border: 1px solid #dee2e6; font-weight: 600; color: white; font-size: 15px; width: 40%;">
                                        <i class="bi bi-list-check"></i> Kriteria Penilaian
                                    </th>
                                    <th style="padding: 18px 20px; text-align: center; border: 1px solid #dee2e6; font-weight: 600; color: white; font-size: 15px;">
                                        <i class="bi bi-star-fill"></i> Penilaian
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($indikator_list as $index => $indikator): 
                                    $bg_color = $index % 2 == 0 ? '#ffffff' : '#f8f9fa';
                                ?>
                                    <tr style="background: <?php echo $bg_color; ?>;">
                                        <td style="padding: 20px; border: 1px solid #dee2e6; vertical-align: top;">
                                            <div style="font-size: 15px; font-weight: 600; color: #333; margin-bottom: 6px;">
                                                <?php echo htmlspecialchars($indikator['nama_indikator']); ?>
                                            </div>
                                            <?php if (!empty($indikator['keterangan'])): ?>
                                                <div style="font-size: 13px; color: #666; font-style: italic; line-height: 1.5;">
                                                    <?php echo htmlspecialchars($indikator['keterangan']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 20px; border: 1px solid #dee2e6;">
                                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                                <?php 
                                                $options = [
                                                    'Sangat Baik' => ['color' => '#28a745', 'icon' => 'emoji-smile'],
                                                    'Baik' => ['color' => '#17a2b8', 'icon' => 'hand-thumbs-up'],
                                                    'Cukup' => ['color' => '#ffc107', 'icon' => 'dash-circle'],
                                                    'Kurang' => ['color' => '#dc3545', 'icon' => 'emoji-frown']
                                                ];
                                                
                                                foreach ($options as $option => $config):
                                                    $checked = isset($existing_values[$indikator['indikator_id']]) && 
                                                               $existing_values[$indikator['indikator_id']] === $option ? 'checked' : '';
                                                    $input_id = 'nilai_' . $indikator['indikator_id'] . '_' . str_replace(' ', '_', strtolower($option));
                                                ?>
                                                    <div style="position: relative;">
                                                        <input type="radio" 
                                                               name="nilai_<?php echo $indikator['indikator_id']; ?>" 
                                                               id="<?php echo $input_id; ?>" 
                                                               value="<?php echo $option; ?>"
                                                               <?php echo $checked; ?>
                                                               required
                                                               style="position: absolute; opacity: 0; cursor: pointer;">
                                                        <label for="<?php echo $input_id; ?>" 
                                                               class="btn-option"
                                                               data-color="<?php echo $config['color']; ?>"
                                                               style="display: inline-block; padding: 10px 18px; background: white; border: 2px solid #ddd; border-radius: 8px; font-size: 13px; font-weight: 600; color: #555; cursor: pointer; transition: all 0.3s; white-space: nowrap;">
                                                            <i class="bi bi-<?php echo $config['icon']; ?>"></i>
                                                            <?php echo $option; ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Catatan (Opsional) -->
                    <div class="form-group full-width" style="margin-top: 30px;">
                        <label class="form-label">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan" class="form-textarea" 
                                  placeholder="Tuliskan catatan atau komentar tambahan..."><?php echo $existing_penilaian['catatan'] ?? ''; ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="submit_penilaian" class="btn btn-primary">
                            <i class="bi bi-send-fill"></i>
                            <?php echo $existing_penilaian ? 'Perbarui Penilaian' : 'Kirim Penilaian'; ?>
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='penilaian_kinerja.php'">
                            <i class="bi bi-x-circle"></i>
                            Batal
                        </button>
                    </div>
                </form>
            </div>
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
        
        // Konfirmasi sebelum submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const isEdit = <?php echo $existing_penilaian ? 'true' : 'false'; ?>;
            const message = isEdit 
                ? 'Apakah Anda yakin ingin memperbarui penilaian ini?' 
                : 'Apakah Anda yakin ingin mengirim penilaian ini?';
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>