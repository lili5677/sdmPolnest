<?php
/**
 * GENERATE TOKEN PEGAWAI - FIXED VERSION
 * File: admin/generate_token_pegawai.php
 * 
 * FIXED: Remove ALL reference to lp.kategori column
 * Auto-detect role ONLY from posisi
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$lamaran_id = isset($_POST['lamaran_id']) ? intval($_POST['lamaran_id']) : 0;

if ($lamaran_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID lamaran tidak valid'
    ]);
    exit;
}

try {
    // Get data lamaran dan pelamar
    // HANYA ambil kolom yang ADA di database
    $stmt = $conn->prepare("
        SELECT 
            l.lamaran_id,
            l.pelamar_id,
            l.lowongan_id,
            l.status_lamaran,
            p.nama_lengkap,
            p.email_aktif,
            p.user_id,
            lp.posisi,
            lp.lowongan_id
        FROM lamaran l
        INNER JOIN pelamar p ON l.pelamar_id = p.pelamar_id
        INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
        WHERE l.lamaran_id = :lamaran_id
          AND l.status_lamaran = 'diterima'
        LIMIT 1
    ");
    
    $stmt->execute(['lamaran_id' => $lamaran_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo json_encode([
            'success' => false,
            'message' => 'Data lamaran tidak ditemukan atau belum diterima'
        ]);
        exit;
    }
    
    // Cek apakah sudah punya token
    $stmtCheckToken = $conn->prepare("
        SELECT token, expired_at, is_used, role
        FROM activation_tokens
        WHERE pelamar_id = :pelamar_id
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmtCheckToken->execute(['pelamar_id' => $data['pelamar_id']]);
    $existingToken = $stmtCheckToken->fetch(PDO::FETCH_ASSOC);
    
    // Jika sudah ada token yang belum dipakai dan masih valid
    if ($existingToken && $existingToken['is_used'] == 0) {
        $expiredDate = new DateTime($existingToken['expired_at']);
        $now = new DateTime();
        
        if ($expiredDate > $now) {
            echo json_encode([
                'success' => true,
                'message' => 'Token sudah ada dan masih berlaku',
                'data' => [
                    'token' => $existingToken['token'],
                    'role' => $existingToken['role'],
                    'expired_at' => $existingToken['expired_at'],
                    'is_new' => false
                ]
            ]);
            exit;
        }
    }
    
    // AUTO-DETECT ROLE berdasarkan posisi SAJA
    $posisi = strtolower($data['posisi']);
    $role = 'pegawai'; // Default
    
    // Jika posisi mengandung kata "dosen" → role = dosen
    if (
        stripos($posisi, 'dosen') !== false ||
        stripos($posisi, 'lecturer') !== false ||
        stripos($posisi, 'pengajar') !== false
    ) {
        $role = 'dosen';
    }
    
    // Generate token unik
    $token = 'PGW-' . strtoupper(bin2hex(random_bytes(6))); // Format: PGW-XXXXXXXXXXXX
    
    // Set expired date (7 hari dari sekarang)
    $expiredAt = (new DateTime())->modify('+7 days')->format('Y-m-d H:i:s');
    
    // Insert token ke database
    $stmtInsert = $conn->prepare("
        INSERT INTO activation_tokens 
        (token, pelamar_id, role, is_used, expired_at, created_at)
        VALUES 
        (:token, :pelamar_id, :role, 0, :expired_at, NOW())
    ");
    
    $stmtInsert->execute([
        'token' => $token,
        'pelamar_id' => $data['pelamar_id'],
        'role' => $role,
        'expired_at' => $expiredAt
    ]);
    
    // Update user: set token di tabel users
    $stmtUpdateUser = $conn->prepare("
        UPDATE users
        SET token = :token,
            password_changed = 0,
            updated_at = NOW()
        WHERE user_id = :user_id
    ");
    
    $stmtUpdateUser->execute([
        'token' => $token,
        'user_id' => $data['user_id']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Token berhasil dibuat',
        'data' => [
            'token' => $token,
            'role' => $role,
            'posisi' => $data['posisi'],
            'expired_at' => $expiredAt,
            'is_new' => true,
            'pelamar' => [
                'nama' => $data['nama_lengkap'],
                'email' => $data['email_aktif']
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>