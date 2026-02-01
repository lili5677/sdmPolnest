<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../../config/database.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

try {
    $conn->beginTransaction();

    // Cek  pegawai masih di struktur organisasi
    $cek = $conn->prepare(
        "SELECT 1 FROM struktur_organisasi WHERE pegawai_id = :id LIMIT 1"
    );
    $cek->execute([':id' => $id]);

    if ($cek->rowCount() > 0) {
        throw new Exception(
            'Pegawai masih terdaftar dalam struktur organisasi. Hapus dari struktur terlebih dahulu.'
        );
    }

    // Ambil user_id
    $stmt = $conn->prepare(
        "SELECT user_id FROM pegawai WHERE pegawai_id = :id"
    );
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Data pegawai tidak ditemukan');
    }

    // Hapus pegawai
    $conn->prepare(
        "DELETE FROM pegawai WHERE pegawai_id = :id"
    )->execute([':id' => $id]);

    // Hapus user
    $conn->prepare(
        "DELETE FROM users WHERE user_id = :uid"
    )->execute([':uid' => $user['user_id']]);

    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
