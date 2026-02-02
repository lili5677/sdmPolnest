<?php
/**
 * UPLOAD SURAT RESMI
 * File: upload_surat_resmi.php
 * Handler untuk upload surat penerimaan/penolakan dan update status final
 */

// Prevent any output before JSON
ob_start();

// Database connection
require_once '../../config/database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

try {
    // Get POST data
    $lamaran_id = isset($_POST['lamaran_id']) ? intval($_POST['lamaran_id']) : 0;
    $action = isset($_POST['action']) ? trim($_POST['action']) : ''; // 'terima' atau 'tolak'
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    // Validate input
    if ($lamaran_id <= 0) {
        throw new Exception('ID lamaran tidak valid');
    }

    if (!in_array($action, ['terima', 'tolak'])) {
        throw new Exception('Action tidak valid');
    }

    // Check if file is uploaded
    if (!isset($_FILES['surat_file']) || $_FILES['surat_file']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('File surat wajib diunggah');
    }

    $file = $_FILES['surat_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error saat upload file: ' . $file['error']);
    }

    // Validate file type (PDF only)
    $allowed_types = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('File harus berformat PDF');
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file maksimal 5MB');
    }

    // Start transaction
    $conn->beginTransaction();

    // Get pelamar info
    $query_pelamar = "SELECT p.nama_lengkap, p.email_aktif, lp.posisi 
                      FROM lamaran l 
                      JOIN pelamar p ON l.pelamar_id = p.pelamar_id 
                      JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
                      WHERE l.lamaran_id = :lamaran_id";
    $stmt_pelamar = $conn->prepare($query_pelamar);
    $stmt_pelamar->execute([':lamaran_id' => $lamaran_id]);
    $pelamar = $stmt_pelamar->fetch(PDO::FETCH_ASSOC);

    if (!$pelamar) {
        throw new Exception('Data pelamar tidak ditemukan');
    }

    // Create upload directory if not exists
    $upload_dir = '../../uploads/surat_resmi/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = 'pdf';
    $jenis_surat = ($action === 'terima') ? 'penerimaan' : 'penolakan';
    $filename = $jenis_surat . '_' . $lamaran_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Gagal menyimpan file');
    }

    // Determine new status and message
    if ($action === 'terima') {
        $new_status = 'diterima';
        $default_catatan = 'Diterima sebagai pegawai. Surat penerimaan telah diunggah.';
        $success_message = 'Pelamar berhasil diterima dan surat penerimaan telah diunggah';
    } else {
        $new_status = 'ditolak_interview';
        $default_catatan = 'Tidak lolos tahap interview. Surat penolakan telah diunggah.';
        $success_message = 'Pelamar ditolak dan surat penolakan telah diunggah';
    }

    $final_catatan = !empty($catatan) ? $catatan : $default_catatan;

    // Update lamaran status
    $update_query = "UPDATE lamaran 
                     SET status_lamaran = :status,
                         catatan_admin = :catatan,
                         surat_resmi_path = :file_path,
                         surat_resmi_jenis = :jenis,
                         tanggal_update = NOW()
                     WHERE lamaran_id = :lamaran_id";
    
    $stmt_update = $conn->prepare($update_query);
    $result = $stmt_update->execute([
        ':status' => $new_status,
        ':catatan' => $final_catatan,
        ':file_path' => $file_path,
        ':jenis' => $jenis_surat,
        ':lamaran_id' => $lamaran_id
    ]);

    if (!$result) {
        // Delete uploaded file if update fails
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        throw new Exception('Gagal update status lamaran');
    }

    // Log activity
    error_log("Surat {$jenis_surat} uploaded for lamaran_id: {$lamaran_id}");
    error_log("File saved to: {$file_path}");

    // TODO: Send email notification with attachment
    // sendEmailWithAttachment($pelamar['email_aktif'], $pelamar['nama_lengkap'], $action, $file_path);

    // Commit transaction
    $conn->commit();

    // Clear output buffer and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'data' => [
            'lamaran_id' => $lamaran_id,
            'status' => $new_status,
            'file_path' => $file_path,
            'filename' => $filename,
            'pelamar' => $pelamar['nama_lengkap'],
            'posisi' => $pelamar['posisi']
        ]
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Delete uploaded file if exists and there was an error
    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }

    // Log error
    error_log("Upload Surat Resmi Error: " . $e->getMessage());

    // Clear output buffer and send error response
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit();
?>