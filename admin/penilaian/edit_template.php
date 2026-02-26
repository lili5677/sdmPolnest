<?php
session_start();
require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID template tidak valid.";
    header("Location: template.php");
    exit;
}

$template_id = (int)$_GET['id'];

// Ambil data template
$stmt = $conn->prepare("SELECT * FROM penilaian_template WHERE template_id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    $_SESSION['error_message'] = "Template tidak ditemukan.";
    header("Location: template.php");
    exit;
}

// Ambil indikator template
$stmt_ind = $conn->prepare("
    SELECT * FROM penilaian_indikator 
    WHERE template_id = ? 
    ORDER BY urutan ASC
");
$stmt_ind->execute([$template_id]);
$indikators = $stmt_ind->fetchAll(PDO::FETCH_ASSOC);

// Handle Update Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_template'])) {
    $nama_template          = trim($_POST['nama_template']);
    $periode                = $_POST['periode'];
    $indikator_ids          = $_POST['indikator_id']          ?? [];
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

            // Update template
            $stmt = $conn->prepare("
                UPDATE penilaian_template 
                SET nama_template = ?, periode = ?, updated_at = NOW()
                WHERE template_id = ?
            ");
            $stmt->execute([$nama_template, $periode . '-01', $template_id]);

            // Hapus indikator lama yang tidak ada di form
            $existing_ids = array_filter($indikator_ids);
            if (!empty($existing_ids)) {
                $placeholders = str_repeat('?,', count($existing_ids) - 1) . '?';
                $stmt_del = $conn->prepare("
                    DELETE FROM penilaian_indikator 
                    WHERE template_id = ? AND indikator_id NOT IN ($placeholders)
                ");
                $stmt_del->execute(array_merge([$template_id], $existing_ids));
            } else {
                $stmt_del = $conn->prepare("DELETE FROM penilaian_indikator WHERE template_id = ?");
                $stmt_del->execute([$template_id]);
            }

            // Update indikator
            $stmt_update = $conn->prepare("
                UPDATE penilaian_indikator 
                SET nama_indikator = ?, keterangan = ?, urutan = ?
                WHERE indikator_id = ?
            ");

            $stmt_insert = $conn->prepare("
                INSERT INTO penilaian_indikator (template_id, nama_indikator, keterangan, urutan)
                VALUES (?, ?, ?, ?)
            ");

            $urutan = 1;
            foreach ($indikator_names as $i => $name) {
                $name = trim($name);
                if (!empty($name)) {
                    $ket = trim($indikator_keterangans[$i] ?? '');
                    $ind_id = $indikator_ids[$i] ?? null;

                    if ($ind_id && is_numeric($ind_id)) {
                        $stmt_update->execute([$name, $ket, $urutan, $ind_id]);
                    } else {
                        $stmt_insert->execute([$template_id, $name, $ket, $urutan]);
                    }
                    $urutan++;
                }
            }

            $conn->commit();
            $_SESSION['success_message'] = "Template penilaian berhasil diperbarui!";
            header("Location: template.php");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Gagal memperbarui template: " . $e->getMessage();
        }
    }
}

$success_message = $_SESSION['success_message'] ?? null;
$error_message   = $_SESSION['error_message']   ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

$periode_input = date('Y-m', strtotime($template['periode']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Template Penilaian - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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

        /* Card */
        .card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            border: 1px solid #f1f5f9;
            max-width: 800px;
            margin: 0 auto;
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
            grid-template-columns: 1fr 1fr;
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 15px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h1><i class=""></i> Edit Template Penilaian</h1>
            <p>Perbarui informasi template dan indikator penilaian</p>
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

        <!-- Form Edit Template -->
        <div class="card">
            <h2 class="card-title">
                <i class=""></i> Edit Template: <?php echo htmlspecialchars($template['nama_template']); ?>
            </h2>

            <form method="POST" id="templateForm">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Nama Template <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="nama_template" class="form-input"
                               placeholder="Contoh: Penilaian Kinerja Semester 1 2026"
                               value="<?php echo htmlspecialchars($template['nama_template']); ?>"
                               required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            Periode (Bulan & Tahun) <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="month" name="periode" class="form-input"
                               value="<?php echo htmlspecialchars($periode_input); ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Kriteria / Indikator Penilaian <span style="color: #ef4444;">*</span>
                    </label>

                    <div class="indikator-container" id="indikatorContainer">
                        <?php foreach ($indikators as $idx => $ind): ?>
                            <div class="indikator-item">
                                <input type="hidden" name="indikator_id[]" value="<?php echo $ind['indikator_id']; ?>">
                                <div class="indikator-header">
                                    <div class="indikator-number"><?php echo $idx + 1; ?></div>
                                    <button type="button" class="btn-remove-ind" onclick="removeIndikator(this)">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                                <div class="form-group" style="margin-bottom:10px;">
                                    <input type="text" name="indikator_name[]" class="form-input"
                                           placeholder="Nama Indikator" 
                                           value="<?php echo htmlspecialchars($ind['nama_indikator']); ?>"
                                           required>
                                </div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <input type="text" name="indikator_keterangan[]" class="form-input"
                                           placeholder="Keterangan tambahan (opsional)"
                                           value="<?php echo htmlspecialchars($ind['keterangan']); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add-indikator" onclick="addIndikator()">
                        <i class="bi bi-plus-circle"></i> Tambah Indikator
                    </button>

                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 24px; border-top: 1px solid #f1f5f9;">
                    <button type="submit" name="update_template" class="btn btn-primary">
                        <i class="bi bi-save"></i> Update Template
                    </button>
                    <a href="template.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                </div>
            </form>
        </div>

    </div>

    <script>
        let indikatorCount = <?php echo count($indikators); ?>;

        function addIndikator() {
            indikatorCount++;
            const container = document.getElementById('indikatorContainer');
            const item = document.createElement('div');
            item.className = 'indikator-item';
            item.innerHTML = `
                <input type="hidden" name="indikator_id[]" value="">
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
                if (confirm('Yakin ingin menghapus indikator ini?')) {
                    btn.closest('.indikator-item').remove();
                    updateNumbers();
                }
            } else {
                alert('Minimal harus ada 1 indikator!');
            }
        }

        function updateNumbers() {
            document.querySelectorAll('.indikator-item').forEach((item, i) => {
                item.querySelector('.indikator-number').textContent = i + 1;
            });
            indikatorCount = document.querySelectorAll('.indikator-item').length;
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