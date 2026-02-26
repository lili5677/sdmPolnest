<?php

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
    $token_data = null;

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
            
            // AUTO-GENERATE TOKEN SAAT DITERIMA
            
            // 1. Ambil data pelamar dan lowongan
            $stmtPelamar = $conn->prepare("
                SELECT 
                    l.pelamar_id,
                    p.user_id,
                    p.email_aktif,
                    p.nama_lengkap,
                    lp.jenis_posisi,
                    lp.posisi
                FROM lamaran l
                INNER JOIN pelamar p ON l.pelamar_id = p.pelamar_id
                INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
                WHERE l.lamaran_id = :lamaran_id
            ");
            $stmtPelamar->execute([':lamaran_id' => $lamaran_id]);
            $pelamarInfo = $stmtPelamar->fetch(PDO::FETCH_ASSOC);
            
            if ($pelamarInfo) {
                // 2. Menentukan role berdasarkan jenis_posisi
                $role = 'pegawai';
                $jenis_posisi = $pelamarInfo['jenis_posisi'] ?? null;
                
                if (!empty($jenis_posisi) && strtolower($jenis_posisi) === 'dosen') {
                    $role = 'dosen';
                }
                
                // 3. Cek ketersediaan token aktif
                $stmtCheckToken = $conn->prepare("
                    SELECT token, expired_at, is_used, role
                    FROM activation_tokens
                    WHERE pelamar_id = :pelamar_id
                      AND is_used = 0
                      AND expired_at > NOW()
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmtCheckToken->execute([':pelamar_id' => $pelamarInfo['pelamar_id']]);
                $existingToken = $stmtCheckToken->fetch(PDO::FETCH_ASSOC);
                
                // 4. Generate token baru jika token belum ada atau sudah expired
                if (!$existingToken) {
                    // Generate token unik
                    $token = 'PGW-' . strtoupper(bin2hex(random_bytes(8)));
                    $expiredAt = (new DateTime())->modify('+7 days')->format('Y-m-d H:i:s');
                    
                    // Insert token baru
                    $stmtInsertToken = $conn->prepare("
                        INSERT INTO activation_tokens 
                        (token, pelamar_id, role, is_used, expired_at, created_at)
                        VALUES 
                        (:token, :pelamar_id, :role, 0, :expired_at, NOW())
                    ");
                    
                    $stmtInsertToken->execute([
                        'token' => $token,
                        'pelamar_id' => $pelamarInfo['pelamar_id'],
                        'role' => $role,
                        'expired_at' => $expiredAt
                    ]);
                    
                    // Update users table dengan token
                    $stmtUpdateUser = $conn->prepare("
                        UPDATE users
                        SET token = :token,
                            password_changed = 0,
                            updated_at = NOW()
                        WHERE user_id = :user_id
                    ");
                    
                    $stmtUpdateUser->execute([
                        'token' => $token,
                        'user_id' => $pelamarInfo['user_id']
                    ]);
                    
                    // Store token data for response
                    $token_data = [
                        'token' => $token,
                        'role' => $role,
                        'jenis_posisi' => $jenis_posisi,
                        'expired_at' => $expiredAt,
                        'activation_link' => 'http://sdmpolnest.test/auth/login_pegawai_new.php?email=' . urlencode($pelamarInfo['email_aktif']),
                        'is_new' => true
                    ];
                    
                    $success_message .= ". Token aktivasi berhasil dibuat.";
                } else {
                    // Token sudah ada
                    $token_data = [
                        'token' => $existingToken['token'],
                        'role' => $existingToken['role'],
                        'expired_at' => $existingToken['expired_at'],
                        'activation_link' => 'http://sdmpolnest.test/auth/login_pegawai_new.php?email=' . urlencode($pelamarInfo['email_aktif']),
                        'is_new' => false
                    ];
                    
                    $success_message .= ". Token aktivasi sudah ada sebelumnya.";
                }
            }
            
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

    // Commit transaction
    $conn->commit();

    // Prepare response
    $response = [
        'success' => true,
        'message' => $success_message,
        'new_status' => $status,
        'pelamar' => [
            'nama' => $pelamarData['nama_lengkap'] ?? '',
            'email' => $pelamarData['email_aktif'] ?? '',
            'posisi' => $pelamarData['posisi'] ?? ''
        ]
    ];

    // Add token data if generated
    if ($token_data) {
        $response['token'] = $token_data;
    }

    // Clear output buffer and send success response
    ob_end_clean();
    echo json_encode($response);

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