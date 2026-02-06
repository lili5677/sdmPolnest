<?php
/**
 * MIDDLEWARE: Check Pelamar Status
 * File: middleware/check_pelamar_status.php
 * 
 * Fungsi: Mencegah pelamar yang sudah jadi pegawai untuk melamar lagi
 * 
 * Usage: Include file ini di halaman-halaman yang berhubungan dengan melamar lowongan
 */

if (!isset($_SESSION)) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Fungsi untuk cek apakah pelamar sudah jadi pegawai
 * 
 * @param int $userId User ID yang akan dicek
 * @param PDO $conn Database connection
 * @return array ['is_pegawai' => bool, 'message' => string, 'roles' => array]
 */
function checkPelamarStatus($userId, $conn) {
    try {
        // Ambil data user dan pelamar
        $stmt = $conn->prepare("
            SELECT 
                u.user_id,
                u.email,
                u.user_type,
                u.user_roles,
                p.pelamar_id,
                p.nama_lengkap,
                p.is_pegawai,
                p.is_complete
            FROM users u
            LEFT JOIN pelamar p ON u.user_id = p.user_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ");
        $stmt->execute(['user_id' => $userId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            return [
                'is_pegawai' => false,
                'can_apply' => false,
                'message' => 'Data user tidak ditemukan',
                'roles' => []
            ];
        }
        
        // Parse user roles
        $userRoles = json_decode($data['user_roles'], true) ?? [$data['user_type']];
        
        // Cek apakah user punya role pegawai/dosen
        $isPegawai = in_array('pegawai', $userRoles) || in_array('dosen', $userRoles);
        
        // Atau cek dari tabel pelamar
        $isPegawaiFromTable = $data['is_pegawai'] == 1;
        
        if ($isPegawai || $isPegawaiFromTable) {
            return [
                'is_pegawai' => true,
                'can_apply' => false,
                'message' => 'Anda sudah terdaftar sebagai pegawai. Pelamar yang sudah menjadi pegawai tidak dapat melamar lowongan baru.',
                'roles' => $userRoles,
                'pelamar_data' => $data
            ];
        }
        
        return [
            'is_pegawai' => false,
            'can_apply' => true,
            'message' => 'Anda dapat melamar lowongan',
            'roles' => $userRoles,
            'pelamar_data' => $data
        ];
        
    } catch (Exception $e) {
        return [
            'is_pegawai' => false,
            'can_apply' => false,
            'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage(),
            'roles' => []
        ];
    }
}

/**
 * Fungsi untuk redirect dengan pesan error jika pelamar sudah jadi pegawai
 */
function blockPegawaiFromApplying($userId, $conn, $redirectUrl = '../pelamar/dashboard.php') {
    $status = checkPelamarStatus($userId, $conn);
    
    if ($status['is_pegawai']) {
        $_SESSION['error_message'] = $status['message'];
        $_SESSION['alert_type'] = 'warning';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

/**
 * Fungsi untuk mendapatkan pesan yang lebih friendly
 */
function getPelamarStatusMessage($userId, $conn) {
    $status = checkPelamarStatus($userId, $conn);
    
    if ($status['is_pegawai']) {
        return [
            'type' => 'info',
            'title' => 'Informasi Status Kepegawaian',
            'message' => 'Selamat! Anda saat ini terdaftar sebagai pegawai di Politeknik NEST. Sebagai pegawai aktif, Anda tidak dapat mengajukan lamaran baru. Silakan gunakan portal pegawai untuk mengakses fitur-fitur kepegawaian Anda.',
            'action' => [
                'text' => 'Ke Portal Pegawai',
                'url' => '../pegawai/administrasi.php'
            ]
        ];
    }
    
    return null;
}

/**
 * Auto-check saat file ini di-include
 * Hanya untuk halaman-halaman yang require blocking
 */
if (defined('BLOCK_PEGAWAI_APPLY') && BLOCK_PEGAWAI_APPLY === true) {
    if (isset($_SESSION['user_id'])) {
        blockPegawaiFromApplying($_SESSION['user_id'], $conn);
    }
}