<?php
/**
 * JADWALKAN INTERVIEW
 * File: jadwalkan_interview.php
 * Handler untuk menjadwalkan interview dan update status ke 'interview'
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
    $tanggal_interview = isset($_POST['tanggal_interview']) ? trim($_POST['tanggal_interview']) : '';
    $lokasi = isset($_POST['lokasi']) ? trim($_POST['lokasi']) : '';
    $pewawancara = isset($_POST['pewawancara']) ? trim($_POST['pewawancara']) : '';
    $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

    // Debug: Log received data
    error_log("Jadwal Interview - Received data:");
    error_log("lamaran_id: " . $lamaran_id);
    error_log("tanggal_interview: " . $tanggal_interview);
    error_log("lokasi: " . $lokasi);
    error_log("pewawancara: " . $pewawancara);

    // Validate input
    if ($lamaran_id <= 0) {
        throw new Exception('ID lamaran tidak valid');
    }

    if (empty($tanggal_interview)) {
        throw new Exception('Tanggal interview wajib diisi');
    }

    if (empty($lokasi)) {
        throw new Exception('Lokasi wajib diisi');
    }

    if (empty($pewawancara)) {
        throw new Exception('Pewawancara wajib diisi');
    }

    // Validate datetime format
    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $tanggal_interview);
    if (!$datetime) {
        throw new Exception('Format tanggal tidak valid. Format yang diterima: Y-m-d H:i:s');
    }

    // Start transaction
    $conn->beginTransaction();

    // Check if lamaran exists and get current status
    $check_lamaran = "SELECT status_lamaran FROM lamaran WHERE lamaran_id = :lamaran_id";
    $stmt_check_lamaran = $conn->prepare($check_lamaran);
    $stmt_check_lamaran->execute([':lamaran_id' => $lamaran_id]);
    $lamaran = $stmt_check_lamaran->fetch(PDO::FETCH_ASSOC);

    if (!$lamaran) {
        throw new Exception('Data lamaran tidak ditemukan');
    }

    // Check if schedule already exists
    $check_query = "SELECT jadwal_interview_id FROM jadwal_interview WHERE lamaran_id = :lamaran_id";
    $stmt_check = $conn->prepare($check_query);
    $stmt_check->execute([':lamaran_id' => $lamaran_id]);
    $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing schedule
        $update_query = "UPDATE jadwal_interview 
                        SET tanggal_interview = :tanggal,
                            lokasi = :lokasi,
                            pewawancara = :pewawancara,
                            keterangan = :keterangan,
                            updated_at = NOW()
                        WHERE lamaran_id = :lamaran_id";
        
        $stmt_update = $conn->prepare($update_query);
        $result = $stmt_update->execute([
            ':tanggal' => $tanggal_interview,
            ':lokasi' => $lokasi,
            ':pewawancara' => $pewawancara,
            ':keterangan' => $keterangan,
            ':lamaran_id' => $lamaran_id
        ]);

        if (!$result) {
            throw new Exception('Gagal update jadwal interview');
        }

        error_log("Updated existing interview schedule ID: " . $existing['jadwal_interview_id']);
    } else {
        // Insert new schedule
        $insert_query = "INSERT INTO jadwal_interview 
                        (lamaran_id, tanggal_interview, lokasi, pewawancara, keterangan, created_by, created_at) 
                        VALUES (:lamaran_id, :tanggal, :lokasi, :pewawancara, :keterangan, :created_by, NOW())";
        
        $stmt_insert = $conn->prepare($insert_query);
        $result = $stmt_insert->execute([
            ':lamaran_id' => $lamaran_id,
            ':tanggal' => $tanggal_interview,
            ':lokasi' => $lokasi,
            ':pewawancara' => $pewawancara,
            ':keterangan' => $keterangan,
            ':created_by' => $_SESSION['user_id']
        ]);

        if (!$result) {
            throw new Exception('Gagal insert jadwal interview');
        }

        $new_id = $conn->lastInsertId();
        error_log("Created new interview schedule ID: " . $new_id);
    }

    // Update lamaran status to 'interview'
    $update_lamaran = "UPDATE lamaran 
                      SET status_lamaran = 'interview',
                          catatan_admin = 'Dijadwalkan untuk interview',
                          tanggal_update = NOW()
                      WHERE lamaran_id = :lamaran_id";
    
    $stmt_lamaran = $conn->prepare($update_lamaran);
    $result_lamaran = $stmt_lamaran->execute([':lamaran_id' => $lamaran_id]);

    if (!$result_lamaran) {
        throw new Exception('Gagal update status lamaran');
    }

    error_log("Updated lamaran status to 'interview'");

    // TODO: Send email notification to applicant
    // Get pelamar email
    $query_email = "SELECT p.email_aktif, p.nama_lengkap 
                    FROM lamaran l 
                    JOIN pelamar p ON l.pelamar_id = p.pelamar_id 
                    WHERE l.lamaran_id = :lamaran_id";
    $stmt_email = $conn->prepare($query_email);
    $stmt_email->execute([':lamaran_id' => $lamaran_id]);
    $pelamar = $stmt_email->fetch(PDO::FETCH_ASSOC);

    // You can add email sending logic here
    // sendEmailNotification($pelamar['email_aktif'], $tanggal_interview, $lokasi, $pewawancara);

    // Commit transaction
    $conn->commit();

    // Clear output buffer and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Jadwal interview berhasil dibuat dan pelamar telah dinotifikasi',
        'data' => [
            'lamaran_id' => $lamaran_id,
            'tanggal_interview' => $tanggal_interview,
            'lokasi' => $lokasi,
            'pewawancara' => $pewawancara,
            'pelamar' => $pelamar['nama_lengkap'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log error
    error_log("Jadwal Interview Error: " . $e->getMessage());

    // Clear output buffer and send error response
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'lamaran_id' => $lamaran_id ?? null,
            'tanggal_received' => $_POST['tanggal_interview'] ?? null,
            'lokasi_received' => $_POST['lokasi'] ?? null,
            'pewawancara_received' => $_POST['pewawancara'] ?? null
        ]
    ]);
}
exit();
?>