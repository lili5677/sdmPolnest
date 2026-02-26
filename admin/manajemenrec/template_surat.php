<?php
// Koneksi database
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ../auth/login_pegawai.php');
    exit();
}

if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $baseUri = str_replace(basename($scriptName), '', $scriptName);
    define('BASE_URL', $protocol . "://" . $host . $baseUri);
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_template'])) {
    ob_start();
    
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    
    header('Content-Type: application/json');
    
    try {
        if (ob_get_length()) ob_clean();
        
        // Check file upload
        if (!isset($_FILES['template_file']) || $_FILES['template_file']['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File melebihi upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File melebihi MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh extension'
            ];
            
            $error_code = isset($_FILES['template_file']) ? $_FILES['template_file']['error'] : UPLOAD_ERR_NO_FILE;
            $error_msg = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Error code: ' . $error_code;
            
            throw new Exception('Gagal upload file. ' . $error_msg);
        }
        
        $file = $_FILES['template_file'];
        
        // Validate extension
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'pdf') {
            throw new Exception('File harus berformat PDF');
        }
        
        // Validate size (5MB)
        $file_size_mb = $file['size'] / 1024 / 1024;
        if ($file_size_mb > 5) {
            throw new Exception('Ukuran file maksimal 5 MB. File Anda: ' . round($file_size_mb, 2) . ' MB');
        }
        
        // Validasi file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type !== 'application/pdf') {
            throw new Exception('File yang diupload bukan PDF yang valid');
        }
        
        // Create directory
        $upload_dir = __DIR__ . '/../../assets/templates/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Gagal membuat direktori upload. Periksa permission folder.');
            }
            @chmod($upload_dir, 0755);
        }
        
        // Cek apakah direktori writable
        if (!is_writable($upload_dir)) {
            throw new Exception('Direktori upload tidak dapat ditulis. Hubungi administrator.');
        }
        
        $filename = 'surat_pernyataan_template.pdf';
        $file_path = $upload_dir . $filename;
        
        // Backup old file
        if (file_exists($file_path)) {
            $backup_name = 'surat_pernyataan_template_backup_' . date('YmdHis') . '.pdf';
            if (!@rename($file_path, $upload_dir . $backup_name)) {
                @unlink($file_path);
            }
        }
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Gagal memindahkan file. Periksa permission folder.');
        }
        
        // Set permission file
        @chmod($file_path, 0644);
        
        $db_saved = false;
        try {
            if (isset($conn) && $conn instanceof PDO) {
                $db_path = '/assets/templates/' . $filename;
                $file_size_kb = round($file['size'] / 1024);
                $check_query = "SELECT template_id FROM template_surat WHERE jenis_template = 'surat_pernyataan_pelamar'";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->execute();
                $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    $update_query = "UPDATE template_surat 
                                   SET nama_template = ?,
                                       path_file = ?, 
                                       ukuran_file = ?,
                                       upload_by = ?,
                                       created_at = NOW()
                                   WHERE jenis_template = 'surat_pernyataan_pelamar'";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->execute([
                        'Template Surat Pernyataan Kebenaran Dokumen',
                        $db_path, 
                        $file_size_kb, 
                        $_SESSION['user_id']
                    ]);
                } else {
                    // Insert new template
                    $insert_query = "INSERT INTO template_surat 
                                   (jenis_template, nama_template, path_file, ukuran_file, upload_by, created_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->execute([
                        'surat_pernyataan_pelamar',
                        'Template Surat Pernyataan Kebenaran Dokumen',
                        $db_path,
                        $file_size_kb,
                        $_SESSION['user_id']
                    ]);
                }
                $db_saved = true;
            }
        } catch (Exception $db_error) {
            error_log('Database save failed: ' . $db_error->getMessage());
        }

        if (ob_get_length()) ob_clean();
        
        echo json_encode([
            'success' => true,
            'message' => 'Template berhasil diupload!' . ($db_saved ? '' : ' (File tersimpan, database tidak terupdate)'),
            'filename' => $filename,
            'size' => round($file_size_mb, 2)
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        if (ob_get_length()) ob_clean();
        
        error_log('Upload error: ' . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    ob_end_flush();
    exit();
}

$template_file = __DIR__ . '/../../assets/templates/surat_pernyataan_template.pdf';
$template_exists = file_exists($template_file);
$template_info = null;

if ($template_exists) {
    $template_info = [
        'name' => 'surat_pernyataan_template.pdf',
        'size' => round(filesize($template_file) / 1024 / 1024, 2),
        'date' => date('d F Y H:i', filemtime($template_file)),
        'path' => '/assets/templates/surat_pernyataan_template.pdf'
    ];
}

$page_title = 'Kelola Template Surat';
include '../sidebar/sidebar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="icon" href="/sdmPolnest/users/assets/logo.png?v=1">


    
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f7fa;
        }
        
        /* END OVERRIDE SIDEBAR */
        
            .main-content{
            margin-left:290px;
            padding:30px 40px;
            min-height:100vh;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .page-header p {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
            background: white;
            margin-bottom: 20px;
        }
        .card-header {
            background: linear-gradient(135deg, #2563eb, #2563eb);
            color: white;
            font-weight: 600;
            font-size: 16px;
            padding: 20px 24px;
            border: none;
        }
        .card-body {
            padding: 28px;
        }
        
        .upload-area {
            border: 3px dashed #e5e7eb;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafafa;
        }
        .upload-area:hover, 
        .upload-area.dragover {
            border-color: #ec4899;
            background: #fef2f2;
        }
        .upload-area i.bi-cloud-arrow-up {
            color: #ec4899;
        }
        
        .template-info-card {
            background: linear-gradient(135deg, #2563eb, #2563eb);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .template-info-card .icon-box {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #9db5e8);
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(236,72,153,0.3);
            background: linear-gradient(135deg, #9db5e8, #2563eb);
        }
        
        .btn-outline-primary {
            border: 2px solid #ec4899;
            color: #ec4899;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-outline-primary:hover {
            background: #ec4899;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-success {
            border: 2px solid #10b981;
            color: #10b981;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-outline-success:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
        }
        
        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
            border-radius: 8px;
        }
        
        .alert-warning {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            color: #92400e;
            border-radius: 8px;
        }
        
        .tips-card {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 12px;
            padding: 20px;
        }
        .tips-card h6 {
            color: #92400e;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .tips-card ul {
            color: #78350f;
            font-size: 13px;
            margin: 0;
            padding-left: 20px;
        }
        .tips-card li {
            margin-bottom: 8px;
        }
        
            @media (max-width: 968px){
            .main-content{
                margin-left:80px;
                padding:24px;
            }
        }
                
            @media (max-width: 480px){
            .main-content{
                margin-left:70px;
                padding:16px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header">
            <h1><i class="bi bi-file-earmark-text me-2"></i>Kelola Template Surat</h1>
            <p>Upload dan kelola template surat pernyataan untuk pelamar</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Template Baru
                    </div>
                    <div class="card-body">
                        <form id="formUploadTemplate" enctype="multipart/form-data">
                            <input type="hidden" name="upload_template" value="1">
                            
                            <div id="uploadArea" class="upload-area mb-4">
                                <input type="file" id="templateFile" name="template_file" accept=".pdf" style="display: none;">
                                <div id="uploadContent" onclick="document.getElementById('templateFile').click()">
                                    <i class="bi bi-cloud-arrow-up" style="font-size: 64px;"></i>
                                    <h5 class="mt-3 mb-2">Klik atau Drag & Drop File</h5>
                                    <p class="text-muted mb-0">Format: PDF | Maksimal: 5 MB</p>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Informasi:</strong>
                                <ul class="mb-0 mt-2" style="padding-left: 20px;">
                                    <li>Template akan digunakan untuk surat pernyataan pelamar</li>
                                    <li>File lama akan otomatis digantikan dengan yang baru</li>
                                    <li>Backup otomatis dibuat sebelum penggantian</li>
                                </ul>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload me-2"></i>Upload Template
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-4" style="font-weight: 600; color: #1a1a1a;">
                            <i class="bi bi-file-check me-2 text-success"></i>
                            Status Template
                        </h5>

                        <?php if ($template_exists && $template_info): ?>
                        <div class="template-info-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1" style="font-weight: 600;">Template Aktif</h6>
                                    <small style="opacity: 0.9;">Tersedia untuk pelamar</small>
                                </div>
                            </div>
                            <hr style="border-color: rgba(255,255,255,0.3); margin: 16px 0;">
                            <div class="small">
                                <div class="mb-2">
                                    <i class="bi bi-file-text me-2"></i>
                                    <?= htmlspecialchars($template_info['name']) ?>
                                </div>
                                <div class="mb-2">
                                    <i class="bi bi-hdd me-2"></i>
                                    <?= htmlspecialchars($template_info['size']) ?> MB
                                </div>
                                <div>
                                    <i class="bi bi-calendar me-2"></i>
                                    <?= htmlspecialchars($template_info['date']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="<?= BASE_URL ?>assets/templates/surat_pernyataan_template.pdf" 
                               class="btn btn-outline-primary" target="_blank">
                                <i class="bi bi-eye me-2"></i>Preview
                            </a>
                            <a href="<?= BASE_URL ?>assets/templates/surat_pernyataan_template.pdf" 
                               class="btn btn-outline-success" download>
                                <i class="bi bi-download me-2"></i>Download
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Belum Ada Template</strong><br>
                            <small>Silakan upload template terlebih dahulu.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="tips-card">
                    <h6><i class="bi bi-lightbulb me-2"></i>Tips</h6>
                    <ul>
                        <li>Pastikan template berisi kop surat</li>
                        <li>Sediakan kolom tanda tangan</li>
                        <li>Gunakan placeholder yang jelas</li>
                        <li>File akan menggantikan template lama</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('templateFile');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.add('dragover'));
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('dragover'));
        });
        
        uploadArea.addEventListener('drop', e => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });
        
        fileInput.addEventListener('change', handleFileSelect);
        
        function handleFileSelect() {
            const file = fileInput.files[0];
            if (!file) return;
            
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            
            if (file.type !== 'application/pdf') {
                Swal.fire({
                    icon: 'error',
                    title: 'Format Salah',
                    text: 'File harus berformat PDF',
                    confirmButtonColor: '#ec4899'
                });
                fileInput.value = '';
                return;
            }
            
            if (fileSize > 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Terlalu Besar',
                    text: `Maksimal 5 MB. File Anda: ${fileSize} MB`,
                    confirmButtonColor: '#ec4899'
                });
                fileInput.value = '';
                return;
            }
            
            document.getElementById('uploadContent').innerHTML = `
                <i class="bi bi-file-earmark-pdf-fill" style="font-size: 64px; color: #dc3545;"></i>
                <h5 class="mt-3 mb-2">${file.name}</h5>
                <p class="text-muted mb-0">${fileSize} MB | PDF</p>
                <button type="button" class="btn btn-sm btn-outline-danger mt-3" onclick="clearFile()">
                    <i class="bi bi-x-circle me-1"></i>Hapus
                </button>
            `;
        }
        
        function clearFile() {
            fileInput.value = '';
            document.getElementById('uploadContent').innerHTML = `
                <i class="bi bi-cloud-arrow-up" style="font-size: 64px; color: #2563eb;"></i>
                <h5 class="mt-3 mb-2">Klik atau Drag & Drop File</h5>
                <p class="text-muted mb-0">Format: PDF | Maksimal: 5 MB</p>
            `;
        }
        
        document.getElementById('formUploadTemplate').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih File',
                    text: 'Silakan pilih file terlebih dahulu',
                    confirmButtonColor: '#ec4899'
                });
                return;
            }
            
            Swal.fire({
                title: 'Mengupload...',
                html: 'Mohon tunggu',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // PERBAIKAN: Improved error handling
                const contentType = response.headers.get('content-type');
                
                // Clone response untuk bisa dibaca dua kali
                return response.clone().text().then(text => {
                    // Debug: log response text
                    console.log('Response text:', text);
                    
                    if (!contentType || !contentType.includes('application/json')) {
                        // Jika bukan JSON, coba ekstrak error message dari HTML
                        const errorMatch = text.match(/<b>(.*?)<\/b>/);
                        const errorMsg = errorMatch ? errorMatch[1] : 'Server tidak mengembalikan JSON';
                        throw new Error(errorMsg + '. Periksa error log PHP atau hubungi administrator.');
                    }
                    
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Response bukan JSON valid: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: data.message,
                        confirmButtonColor: '#10b981'
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message,
                        confirmButtonColor: '#ef4444'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    html: `<div style="text-align: left;">${error.message}</div>`,
                    confirmButtonColor: '#ef4444'
                });
            });
        });
    </script>
</body>
</html>