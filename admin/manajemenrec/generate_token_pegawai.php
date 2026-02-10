<?php
/**
 * GENERATE TOKEN PEGAWAI - FINAL FIXED VERSION
 * File: admin/generate_token_pegawai.php
 * 
 * FIXED: Auto-detect role dari jenis_posisi lowongan yang dilamar
 * 
 * UPDATE LOG:
 * - Ambil jenis_posisi dari tabel lowongan_pekerjaan
 * - Role otomatis sesuai jenis_posisi (dosen/staff/tendik)
 * - Fallback ke deteksi nama posisi jika jenis_posisi NULL
 */

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';

// ========================================
// CEK LOGIN ADMIN
// ========================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - Hanya admin yang bisa generate token'
    ]);
    exit;
}

// ========================================
// VALIDASI REQUEST METHOD
// ========================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed - Gunakan POST'
    ]);
    exit;
}

// ========================================
// VALIDASI LAMARAN ID
// ========================================
$lamaran_id = isset($_POST['lamaran_id']) ? intval($_POST['lamaran_id']) : 0;

if ($lamaran_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID lamaran tidak valid'
    ]);
    exit;
}

try {
    // ========================================
    // GET DATA LAMARAN + JENIS_POSISI LOWONGAN
    // ========================================
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
            lp.jenis_posisi,
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
            'message' => 'Data lamaran tidak ditemukan atau status belum "diterima"',
            'hint' => 'Pastikan pelamar sudah di-set statusnya ke "diterima" terlebih dahulu'
        ]);
        exit;
    }
    
    // ========================================
    // CEK APAKAH SUDAH PUNYA TOKEN
    // ========================================
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
                    'is_new' => false,
                    'note' => 'Token yang sudah dibuat sebelumnya masih aktif'
                ]
            ]);
            exit;
        }
    }
    
    // ========================================
    // TENTUKAN ROLE BERDASARKAN JENIS_POSISI LOWONGAN
    // PRIORITAS:
    // 1. Jenis_posisi dari lowongan (PRIORITAS UTAMA)
    // 2. Fallback: deteksi dari nama posisi
    // ========================================
    $role = 'pegawai'; // Default
    $jenis_posisi = $data['jenis_posisi'] ?? null;
    
    if (!empty($jenis_posisi)) {
        // GUNAKAN JENIS_POSISI DARI LOWONGAN
        $jenis_posisi_lower = strtolower($jenis_posisi);
        
        if ($jenis_posisi_lower === 'dosen') {
            $role = 'dosen';
        } else {
            // staff dan tendik → role pegawai
            $role = 'pegawai';
        }
        
        $detection_method = 'jenis_posisi_lowongan';
    } else {
        // FALLBACK: Deteksi dari nama posisi (jika jenis_posisi NULL)
        $posisi = strtolower($data['posisi']);
        
        if (
            stripos($posisi, 'dosen') !== false ||
            stripos($posisi, 'lecturer') !== false ||
            stripos($posisi, 'pengajar') !== false
        ) {
            $role = 'dosen';
        } else {
            $role = 'pegawai';
        }
        
        $detection_method = 'nama_posisi_fallback';
        $jenis_posisi = ($role === 'dosen') ? 'dosen' : 'staff';
    }
    
    // ========================================
    // GENERATE TOKEN UNIK
    // ========================================
    $token = 'PGW-' . strtoupper(bin2hex(random_bytes(8))); // Format: PGW-XXXXXXXXXXXXXXXX
    
    // Set expired date (7 hari dari sekarang)
    $expiredAt = (new DateTime())->modify('+7 days')->format('Y-m-d H:i:s');
    
    // ========================================
    // INSERT TOKEN KE DATABASE
    // ========================================
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
    
    // ========================================
    // UPDATE USER: SET TOKEN DI TABEL USERS
    // ========================================
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
    
    // ========================================
    // RESPONSE SUCCESS
    // ========================================
    echo json_encode([
        'success' => true,
        'message' => 'Token berhasil dibuat',
        'data' => [
            'token' => $token,
            'role' => $role,
            'jenis_posisi' => $jenis_posisi,
            'posisi' => $data['posisi'],
            'detection_method' => $detection_method,
            'expired_at' => $expiredAt,
            'is_new' => true,
            'pelamar' => [
                'nama' => $data['nama_lengkap'],
                'email' => $data['email_aktif']
            ],
            'info' => [
                'role_explanation' => $role === 'dosen' 
                    ? 'Pegawai akan mendapat akses dashboard Dosen'
                    : 'Pegawai akan mendapat akses dashboard Pegawai',
                'jenis_pegawai' => $jenis_posisi,
                'user_type' => $role
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>