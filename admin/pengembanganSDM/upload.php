<?php

require_once '../../config/database.php';

define('MAX_FILE_SIZE', 2 * 1024 * 1024); 
define('ALLOWED_EXTENSIONS', ['doc', 'docx', 'pdf']);
define('UPLOAD_DIR', '../../uploads/templates/');

// Fungsi untuk redirect 
function redirect($success, $message) {
    $status = $success ? 'success' : 'error';
    header("Location: pengembangan-sdm.php?tab=template&status=$status&message=" . urlencode($message));
    exit();
}

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(false, 'Metode request tidak valid');
}

// Validasi input
if (empty($_POST['nama_template'])) {
    redirect(false, 'Nama template harus diisi');
}

if (!isset($_FILES['file_template']) || $_FILES['file_template']['error'] === UPLOAD_ERR_NO_FILE) {
    redirect(false, 'File template harus dipilih');
}

$nama_template = trim($_POST['nama_template']);
$file = $_FILES['file_template'];

// Validasi error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi batas server)',
        UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
        UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
        UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
        UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi'
    ];
    $message = $error_messages[$file['error']] ?? 'Terjadi kesalahan saat upload';
    redirect(false, $message);
}

// Validasi ukuran file
if ($file['size'] > MAX_FILE_SIZE) {
    redirect(false, 'Ukuran file maksimal 2MB');
}

// Validasi ekstensi file
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_extension, ALLOWED_EXTENSIONS)) {
    redirect(false, 'Format file tidak valid. Gunakan .doc, .docx, atau .pdf');
}

// Buat folder upload jika belum ada
if (!file_exists(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        redirect(false, 'Gagal membuat folder upload');
    }
}

// Generate nama file unik
$file_name = 'template_' . time() . '_' . uniqid() . '.' . $file_extension;
$file_path = UPLOAD_DIR . $file_name;
$file_path_db = 'uploads/templates/' . $file_name; 

// Upload file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    redirect(false, 'Gagal mengupload file');
}

// Simpan ke database
try {
    $ukuran_file = round($file['size'] / 1024, 2); 
    $jenis_template = 'izin_belajar'; 
    $admin_id = 1; 
    
    $query = "INSERT INTO template_surat 
          (nama_template, jenis_template, path_file, ukuran_file, upload_by, created_at) 
          VALUES 
          (:nama_template, :jenis_template, :path_file, :ukuran_file, :upload_by, NOW())";

    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':nama_template' => $nama_template,
        ':jenis_template' => $jenis_template,
        ':path_file' => $file_path_db,  
        ':ukuran_file' => $ukuran_file,
        ':upload_by' => $admin_id
    ]);
    
    redirect(true, 'Template berhasil diupload');
    
} catch (PDOException $e) {
   
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    redirect(false, 'Gagal menyimpan data: ' . $e->getMessage());
}
?>