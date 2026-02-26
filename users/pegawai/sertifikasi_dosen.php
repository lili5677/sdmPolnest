<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../auth/login_pegawai.php");
    exit;
}

// Include helper untuk cek kelengkapan
require_once '../../config/check_completion.php';
require_once '../../config/database.php';

// Cek kelengkapan data pegawai
$check_result = checkPegawaiCompletion($conn, $_SESSION['pegawai_id']);

// Jika data belum lengkap, redirect ke administrasi
if (!$check_result['is_complete']) {
    $_SESSION['flash_message'] = [
        'type' => 'warning',
        'message' => 'Anda harus melengkapi data administrasi kepegawaian terlebih dahulu sebelum mengakses halaman ini.'
    ];
    header("Location: ../../users/pegawai/administrasi.php");
    exit;
}
// Cek login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../auth/login_pegawai.php");
    exit;
}

// Ambil pegawai_id dari session
$pegawai_id = $_SESSION['pegawai_id'];

$message = '';
$message_type = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'tambah_sertifikasi') {
        try {
            // Validasi input
            $nama_sertifikasi = trim($_POST['nama_sertifikasi']);
            $jenis_sertifikasi = trim($_POST['jenis_sertifikasi']);
            $tahun_sertifikasi = (int)$_POST['tahun_sertifikasi'];
            $kategori = trim($_POST['kategori']);
            $tahun_masa_berlaku = (int)$_POST['tahun_masa_berlaku'];
            
            // Validasi tahun sertifikasi
            if ($tahun_sertifikasi < 1900 || $tahun_sertifikasi > (date('Y') + 10)) {
                throw new Exception('Tahun sertifikasi tidak valid!');
            }
            
            // Validasi jenis sertifikasi 
            $jenis_valid = ['sertifikasi_pendidik', 'profesi', 'kompetensi'];
            if (!in_array($jenis_sertifikasi, $jenis_valid)) {
                throw new Exception('Jenis sertifikasi tidak valid!');
            }
            
            // Validasi kategori
            $kategori_valid = ['nasional', 'internasional'];
            if (!in_array($kategori, $kategori_valid)) {
                throw new Exception('Kategori tidak valid!');
            }
            
            // Validasi file upload
            if (!isset($_FILES['dokumen_sertifikat']) || $_FILES['dokumen_sertifikat']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File sertifikat wajib diupload!');
            }
            
            $file = $_FILES['dokumen_sertifikat'];
            
            // Validasi tipe file (hanya PDF)
            $allowed_type = 'application/pdf';
            $file_type = mime_content_type($file['tmp_name']);
            
            if ($file_type !== $allowed_type) {
                throw new Exception('Hanya file PDF yang diperbolehkan!');
            }
            
            // Validasi ukuran file (maksimal 5 MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Ukuran file maksimal 5 MB!');
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $pegawai_id . '_sertifikasi_' . time() . '.' . $extension;
            $upload_dir = '../../uploads/sertifikasi/';
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $filename;
            $path_file = 'uploads/sertifikasi/' . $filename;
            
            // Upload file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Insert ke database
                $stmt_insert = $conn->prepare("
                    INSERT INTO sertifikasi_dosen 
                    (pegawai_id, nama_sertifikasi, jenis_sertifikasi, tahun_sertifikasi, 
                     kategori, tahun_masa_berlaku, dokumen_sertifikat_path, status_validasi) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt_insert->execute([
                    $pegawai_id,
                    $nama_sertifikasi,
                    $jenis_sertifikasi,
                    $tahun_sertifikasi,
                    $kategori,
                    $tahun_masa_berlaku,
                    $path_file
                ]);
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit;
            } else {
                throw new Exception('Gagal mengupload file!');
            }
            
        } catch (Exception $e) {
            $message = $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Query Data Sertifikasi Dosen
$stmt_sertifikasi = $conn->prepare("
    SELECT * FROM sertifikasi_dosen 
    WHERE pegawai_id = ?
    ORDER BY created_at DESC
");
$stmt_sertifikasi->execute([$pegawai_id]);
$sertifikasi_list = $stmt_sertifikasi->fetchAll(PDO::FETCH_ASSOC);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5;
$total_records = count($sertifikasi_list);
$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
$offset = ($page - 1) * $per_page;
$sertifikasi_paged = array_slice($sertifikasi_list, $offset, $per_page);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikasi Dosen - SDM POLNEST</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #f1f5f9;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1e293b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #fef3e2;
            color: var(--text-dark);
        }

        .navbar-custom {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-dark) !important;
        }

        /* Header Section */
        .header-section {
            background-color: transparent;
            padding: 2rem 0 1rem 0;
            margin-bottom: 1.5rem;
        }

        .header-section h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-dark);
        }

        .header-section p {
            margin: 0.25rem 0 0 0;
            color: #64748b;
            font-size: 0.875rem;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            background-color: #ffffff;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-dark);
            border-radius: 12px 12px 0 0 !important;
        }

        .card-body {
            padding: 1.25rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.6rem 0.875rem;
            font-size: 0.875rem;
            background-color: #f8fafc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: #ffffff;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        /* File Upload Custom */
        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-wrapper input[type="file"] {
            cursor: pointer;
        }

        .file-upload-wrapper input[type="file"]::file-selector-button {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            margin-right: 1rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }

        .file-upload-wrapper input[type="file"]::file-selector-button:hover {
            background-color: #e2e8f0;
        }

        /* Button Styling */
        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: #0f4c75;
            border: none;
        }

        .btn-primary:hover {
            background-color: #0a3a5a;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #6b7280;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        /* Table Styling */
        .table {
            margin-bottom: 0;
        }

        .table thead {
            background-color: #f8fafc;
        }

        .table thead th {
            padding: 1rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
            color: var(--text-dark);
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Badge Styling */
        .badge {
            padding: 0.35rem 0.75rem;
            font-weight: 700;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .badge-pending {
            background-color: #f59e0b;
            color: #ffffff;
        }

        .badge-tervalidasi {
            background-color: #10b981;
            color: #ffffff;
        }

        .badge-ditolak {
            background-color: #ef4444;
            color: #ffffff;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .pagination .page-link {
            border: none;
            background-color: #1e3a5f;
            color: #ffffff;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            min-width: 36px;
            text-align: center;
            transition: all 0.2s;
        }

        .pagination .page-link:hover {
            background-color: #0f4c75;
        }

        .pagination .page-item.active .page-link {
            background-color: #3b82f6;
        }

        .pagination .page-item.disabled .page-link {
            background-color: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state p {
            font-size: 0.875rem;
            margin: 0;
        }

        /* Alert */
        .alert {
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../partials/navbar.php'; ?>

    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <h1>Sertifikasi Dosen</h1>
            <p>Kelola sertifikasi akademik</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil!</strong> Dokumen sertifikasi telah berhasil dikirim dan menunggu validasi.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Form Dokumen Sertifikasi -->
        <div class="card">
            <div class="card-header">
                Dokumen Sertifikasi
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="formSertifikasi">
                    <input type="hidden" name="action" value="tambah_sertifikasi">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Sertifikasi</label>
                        <input type="text" 
                               name="nama_sertifikasi" 
                               class="form-control" 
                               placeholder="Contoh: Sertifikasi Kompetensi Teknik Informatika"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jenis Sertifikasi</label>
                        <select name="jenis_sertifikasi" 
                                class="form-select" 
                                required>
                            <option value="">-- Pilih Jenis Sertifikat--</option>
                            <option value="sertifikasi_pendidik">Sertifikat Pendidik</option>
                            <option value="profesi">Sertifikat Profesi</option>
                            <option value="kompetensi">Sertifikat Kompetensi</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tahun Sertifikasi</label>
                        <input type="number" 
                               name="tahun_sertifikasi" 
                               class="form-control" 
                               placeholder="Contoh: <?= date('Y') ?>" 
                               min="1900" 
                               max="<?= date('Y') + 10 ?>"
                               value="<?= date('Y') ?>"
                               required>
                        <small class="text-muted">Masukkan tahun (contoh: <?= date('Y') ?>)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="kategori" 
                                class="form-select" 
                                required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="nasional">Nasional</option>
                            <option value="internasional">Internasional</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tahun Masa Berlaku Berakhir</label>
                        <input type="number" 
                               name="tahun_masa_berlaku" 
                               class="form-control" 
                               placeholder="Contoh: <?= date('Y') + 2 ?>" 
                               min="<?= date('Y') ?>" 
                               max="2100"
                               required>
                        <small class="text-muted">Tahun sertifikat berakhir (contoh: <?= date('Y') + 2 ?>)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Unggah Dokumen Sertifikat</label>
                        <div class="file-upload-wrapper">
                            <input type="file" 
                                   name="dokumen_sertifikat" 
                                   class="form-control" 
                                   accept=".pdf" 
                                   required
                                   onchange="validateFile(this)">
                        </div>
                        <small class="text-muted">Format: PDF maksimal 5 MB</small>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Kirim Dokumen Sertifikasi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Daftar Sertifikasi -->
        <div class="card">
            <div class="card-header">
                Daftar Sertifikasi
            </div>
            <div class="card-body">
                <?php if (count($sertifikasi_list) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama Sertifikasi</th>
                                <th>Jenis</th>
                                <th>Tahun</th>
                                <th>Kategori</th>
                                <th>Berlaku Hingga</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sertifikasi_paged as $sertifikasi): ?>
                            <tr>
                                <td><?= htmlspecialchars($sertifikasi['nama_sertifikasi']) ?></td>
                                <td>
                                    <?php
                                    $jenis_display = [
                                        'sertifikasi_pendidik' => 'Pendidik',
                                        'profesi' => 'Profesi',
                                        'kompetensi' => 'Kompetensi'
                                    ];
                                    echo $jenis_display[$sertifikasi['jenis_sertifikasi']] ?? $sertifikasi['jenis_sertifikasi'];
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($sertifikasi['tahun_sertifikasi']) ?></td>
                                <td><?= ucfirst(htmlspecialchars($sertifikasi['kategori'])) ?></td>
                                <td><?= htmlspecialchars($sertifikasi['tahun_masa_berlaku']) ?></td>
                                <td>
                                    <?php
                                    $status = strtolower($sertifikasi['status_validasi']);
                                    $badge_class = 'badge-pending';
                                    $status_text = 'Pending';
                                    
                                    if ($status === 'tervalidasi') {
                                        $badge_class = 'badge-tervalidasi';
                                        $status_text = 'Tervalidasi';
                                    } elseif ($status === 'ditolak') {
                                        $badge_class = 'badge-ditolak';
                                        $status_text = 'Ditolak';
                                    }
                                    ?>
                                    <span class="badge <?= $badge_class ?>"><?= $status_text ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=1">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                        </li>
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $total_pages ?>">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <p>Belum ada sertifikasi yang ditambahkan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../partials/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validate File Size and Type
        function validateFile(input) {
            const file = input.files[0];
            if (file) {
                // Check file type
                if (file.type !== 'application/pdf') {
                    alert('Hanya file PDF yang diperbolehkan!');
                    input.value = '';
                    return false;
                }
                
                // Check file size
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('Ukuran file maksimal 5 MB!');
                    input.value = '';
                    return false;
                }
            }
        }

        // Reset Form
        function resetForm() {
            document.getElementById('formSertifikasi').reset();
        }

        // Auto dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Form validation before submit
        document.getElementById('formSertifikasi')?.addEventListener('submit', function(e) {
            const fileInput = this.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length === 0) {
                e.preventDefault();
                alert('Dokumen sertifikat wajib diupload!');
                return false;
            }
        });
    </script>
</body>
</html>