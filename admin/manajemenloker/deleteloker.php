<?php
// Koneksi database
$host = 'localhost';
$dbname = 'sdm_polnest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// Proses hapus lowongan
$lowongan_id = $_GET['id'] ?? 0;

if ($lowongan_id > 0) {
    try {
        // Hapus lowongan
        $stmt = $pdo->prepare("DELETE FROM lowongan_pekerjaan WHERE lowongan_id = ?");
        $stmt->execute([$lowongan_id]);
        
        // Redirect ke index dengan pesan sukses
        header('Location:index.php?deleted=1');
        exit;
    } catch(PDOException $e) {
        // Redirect ke index dengan pesan error
        header('Location: index.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>