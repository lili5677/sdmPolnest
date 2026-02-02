<?php
// Koneksi Database
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// Proses tutup lowongan
$lowongan_id = $_GET['id'] ?? 0;

if ($lowongan_id > 0) {
    try {
        $stmt = $conn->prepare("UPDATE lowongan_pekerjaan SET status = 'ditutup', updated_at = CURRENT_TIMESTAMP WHERE lowongan_id = ?");
        $stmt->execute([$lowongan_id]);

        header('Location: index.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        header('Location: index.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>