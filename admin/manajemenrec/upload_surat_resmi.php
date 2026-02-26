<?php
ob_start();

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

header('Content-Type: application/json');

try {
    $lamaran_id = isset($_POST['lamaran_id']) ? intval($_POST['lamaran_id']) : 0;
    $action = isset($_POST['action']) ? trim($_POST['action']) : ''; 
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    if ($lamaran_id <= 0) {
        throw new Exception('ID lamaran tidak valid');
    }

    if (!in_array($action, ['terima', 'tolak'])) {
        throw new Exception('Action tidak valid');
    }

    if (!isset($_FILES['surat_file']) || $_FILES['surat_file']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('File surat wajib diunggah');
    }

    $file = $_FILES['surat_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error saat upload file: ' . $file['error']);
    }

    $allowed_types = ['application/pdf'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        throw new Exception('File harus berformat PDF');
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; 
    if ($file['size'] > $max_size) {
        throw new Exception('Ukuran file maksimal 5MB');
    }

    $conn->beginTransaction();

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

    $upload_dir = '../../uploads/surat_resmi/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_extension = 'pdf';
    $jenis_surat = ($action === 'terima') ? 'penerimaan' : 'penolakan';
    $filename = $jenis_surat . '_' . $lamaran_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Gagal menyimpan file');
    }

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
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        throw new Exception('Gagal update status lamaran');
    }

    error_log("Surat {$jenis_surat} uploaded for lamaran_id: {$lamaran_id}");
    error_log("File saved to: {$file_path}");
    $conn->commit();

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
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    if (isset($file_path) && file_exists($file_path)) {
        unlink($file_path);
    }

    error_log("Upload Surat Resmi Error: " . $e->getMessage());

    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit();
?>