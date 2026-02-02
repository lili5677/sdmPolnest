<?php
/**
 * UPDATE STATUS LAMARAN
 * File: update_status_lamaran.php
 * Handler untuk update status lamaran (lolos/tolak/terima)
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
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $catatan = isset($_POST['catatan']) ? trim($_POST['catatan']) : '';

    // Validate input
    if ($lamaran_id <= 0) {
        throw new Exception('ID lamaran tidak valid');
    }

    if (empty($status)) {
        throw new Exception('Status tidak boleh kosong');
    }

    // Allowed statuses
    $allowed_statuses = [
        'lolos_administrasi',
        'tidak_lolos_administrasi',
        'lolos_form',
        'ditolak_form',
        'lolos_psikotes',
        'ditolak_psikotes',
        'ditolak_interview',
        'diterima',
        'psikotes',
        'interview'
    ];

    if (!in_array($status, $allowed_statuses)) {
        throw new Exception('Status tidak valid: ' . $status);
    }

    // Start transaction
    $conn->beginTransaction();

    // Determine catatan based on status
    $catatan_final = $catatan;
    $success_message = 'Status berhasil diupdate';

    switch ($status) {
        case 'lolos_administrasi':
            if (empty($catatan_final)) {
                $catatan_final = 'Lolos seleksi administrasi';
            }
            $success_message = 'Pelamar berhasil diloloskan ke tahap pengisian formulir';
            break;

        case 'tidak_lolos_administrasi':
            if (empty($catatan_final)) {
                $catatan_final = 'Tidak lolos seleksi administrasi';
            }
            $success_message = 'Pelamar tidak lolos seleksi administrasi';
            break;

        case 'lolos_form':
            if (empty($catatan_final)) {
                $catatan_final = 'Lolos verifikasi formulir';
            }
            $success_message = 'Formulir berhasil diverifikasi';
            break;

        case 'ditolak_form':
            if (empty($catatan_final)) {
                $catatan_final = 'Ditolak pada tahap pengisian formulir';
            }
            $success_message = 'Pelamar ditolak pada tahap formulir';
            break;

        case 'lolos_psikotes':
            if (empty($catatan_final)) {
                $catatan_final = 'Lolos tahap psikotes';
            }
            $success_message = 'Pelamar lolos psikotes';
            break;

        case 'ditolak_psikotes':
            if (empty($catatan_final)) {
                $catatan_final = 'Tidak lolos tahap psikotes';
            }
            $success_message = 'Pelamar tidak lolos psikotes';
            break;

        case 'ditolak_interview':
            if (empty($catatan_final)) {
                $catatan_final = 'Tidak lolos tahap interview';
            }
            $success_message = 'Pelamar tidak lolos interview';
            break;

        case 'diterima':
            if (empty($catatan_final)) {
                $catatan_final = 'Diterima sebagai pegawai';
            }
            $success_message = 'Pelamar berhasil diterima';
            break;

        case 'psikotes':
            if (empty($catatan_final)) {
                $catatan_final = 'Dijadwalkan untuk psikotes';
            }
            $success_message = 'Status diupdate ke tahap psikotes';
            break;

        case 'interview':
            if (empty($catatan_final)) {
                $catatan_final = 'Dijadwalkan untuk interview';
            }
            $success_message = 'Status diupdate ke tahap interview';
            break;
    }

    // Update lamaran status
    $query = "UPDATE lamaran 
              SET status_lamaran = :status,
                  catatan_admin = :catatan,
                  tanggal_update = NOW()
              WHERE lamaran_id = :lamaran_id";
    
    $stmt = $conn->prepare($query);
    $result = $stmt->execute([
        ':status' => $status,
        ':catatan' => $catatan_final,
        ':lamaran_id' => $lamaran_id
    ]);

    if (!$result) {
        throw new Exception('Gagal update status');
    }

    if ($stmt->rowCount() === 0) {
        // Check if lamaran exists
        $checkQuery = "SELECT lamaran_id FROM lamaran WHERE lamaran_id = :lamaran_id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':lamaran_id' => $lamaran_id]);
        
        if ($checkStmt->rowCount() === 0) {
            throw new Exception('Data lamaran tidak ditemukan');
        }
        
        // If exists but no rows affected, it means status is already the same
        // This is OK, we can still return success
    }

    // Get pelamar email for notification
    $queryEmail = "SELECT p.email_aktif, p.nama_lengkap, lp.posisi 
                   FROM lamaran l 
                   JOIN pelamar p ON l.pelamar_id = p.pelamar_id 
                   JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
                   WHERE l.lamaran_id = :lamaran_id";
    $stmtEmail = $conn->prepare($queryEmail);
    $stmtEmail->execute([':lamaran_id' => $lamaran_id]);
    $pelamarData = $stmtEmail->fetch(PDO::FETCH_ASSOC);

    // TODO: Send email notification
    // sendEmailNotification($pelamarData['email_aktif'], $status, $catatan_final);

    // Commit transaction
    $conn->commit();

    // Clear output buffer and send success response
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => $success_message,
        'new_status' => $status,
        'pelamar' => [
            'nama' => $pelamarData['nama_lengkap'] ?? '',
            'email' => $pelamarData['email_aktif'] ?? '',
            'posisi' => $pelamarData['posisi'] ?? ''
        ]
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    // Log error for debugging
    error_log("Update Status Error: " . $e->getMessage());

    // Clear output buffer and send error response
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'lamaran_id' => $lamaran_id ?? null,
            'status' => $status ?? null
        ]
    ]);
}
exit();
?>