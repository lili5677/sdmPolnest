<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $lamaran_id = $_POST['lamaran_id'] ?? null;
    $user_type = $_POST['user_type'] ?? 'pegawai'; 
    
    if (empty($lamaran_id)) {
        throw new Exception('Lamaran ID tidak valid');
    }
    
    $query = "SELECT 
                l.lamaran_id,
                l.pelamar_id,
                l.status_lamaran,
                p.nama_lengkap,
                p.email_aktif,
                p.tempat_lahir,
                p.tanggal_lahir,
                p.jenis_kelamin,
                p.nomor_ktp,
                p.alamat,
                p.nomor_telepon,
                lp.posisi,
                lp.lowongan_id
              FROM lamaran l
              INNER JOIN pelamar p ON l.pelamar_id = p.pelamar_id
              INNER JOIN lowongan_pekerjaan lp ON l.lowongan_id = lp.lowongan_id
              WHERE l.lamaran_id = :lamaran_id 
              AND l.status_lamaran = 'diterima'
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->execute(['lamaran_id' => $lamaran_id]);
    $lamaran = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lamaran) {
        throw new Exception('Data lamaran tidak ditemukan atau status belum diterima');
    }
    
    $checkUser = "SELECT user_id FROM users WHERE email = :email";
    $stmt = $conn->prepare($checkUser);
    $stmt->execute(['email' => $lamaran['email_aktif']]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingUser) {
        throw new Exception('User account sudah ada untuk email ini');
    }
    
    $conn->beginTransaction();
    
    // generate token
    $token = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    
    // membuat akun user
    $insertUser = "INSERT INTO users 
                   (email, password, user_type, is_active, email_verified, token, password_changed, created_at)
                   VALUES 
                   (:email, :password, :user_type, 1, 1, :token, 0, NOW())";
    

    $hashedPassword = password_hash($token, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare($insertUser);
    $stmt->execute([
        'email' => $lamaran['email_aktif'],
        'password' => $hashedPassword,
        'user_type' => $user_type,
        'token' => $token
    ]);
    
    $user_id = $conn->lastInsertId();
    
    // membuat pegawai
    $insertPegawai = "INSERT INTO pegawai 
                     (user_id, nama_lengkap, email, tempat_lahir, tanggal_lahir, 
                      jenis_kelamin, nomor_ktp, alamat, nomor_telepon, jabatan, 
                      status_pegawai, tanggal_masuk, is_pegawai_lama, created_at)
                     VALUES 
                     (:user_id, :nama, :email, :tempat_lahir, :tanggal_lahir,
                      :jenis_kelamin, :nomor_ktp, :alamat, :nomor_telepon, :jabatan,
                      'aktif', NOW(), 0, NOW())";
    
    $stmt = $conn->prepare($insertPegawai);
    $stmt->execute([
        'user_id' => $user_id,
        'nama' => $lamaran['nama_lengkap'],
        'email' => $lamaran['email_aktif'],
        'tempat_lahir' => $lamaran['tempat_lahir'] ?? '',
        'tanggal_lahir' => $lamaran['tanggal_lahir'] ?? null,
        'jenis_kelamin' => $lamaran['jenis_kelamin'] ?? '',
        'nomor_ktp' => $lamaran['nomor_ktp'] ?? '',
        'alamat' => $lamaran['alamat'] ?? '',
        'nomor_telepon' => $lamaran['nomor_telepon'] ?? '',
        'jabatan' => $lamaran['posisi']
    ]);
    
    $pegawai_id = $conn->lastInsertId();
    
    //  update lamaran
    $updateLamaran = "UPDATE lamaran 
                     SET catatan = CONCAT(COALESCE(catatan, ''), 
                                         '\n[', NOW(), '] Token generated. User ID: ', :user_id, 
                                         ', Pegawai ID: ', :pegawai_id)
                     WHERE lamaran_id = :lamaran_id";
    $stmt = $conn->prepare($updateLamaran);
    $stmt->execute([
        'user_id' => $user_id,
        'pegawai_id' => $pegawai_id,
        'lamaran_id' => $lamaran_id
    ]);
    
    $conn->commit();
    
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://" . $_SERVER['HTTP_HOST'] 
                . dirname(dirname($_SERVER['PHP_SELF'])) . "/";
    
    $login_link = $base_url . "auth/login_pegawai_new.php?email=" . urlencode($lamaran['email_aktif']) . "&token=" . $token;
    
    echo json_encode([
        'success' => true,
        'message' => 'Token berhasil di-generate',
        'data' => [
            'nama' => $lamaran['nama_lengkap'],
            'email' => $lamaran['email_aktif'],
            'token' => $token,
            'user_type' => $user_type,
            'login_link' => $login_link
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}