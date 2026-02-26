<?php
session_start();

require_once '../../config/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: pengembangan-sdm.php?status=error&message=' . urlencode('ID tidak valid'));
    exit();
}

$pengajuan_id = (int)$_GET['id'];

try {
    // Update status pengajuan 
    $query = "UPDATE pengajuan_studi 
              SET status_pengajuan = 'ditinjau',
                  updated_at = CURRENT_TIMESTAMP
              WHERE pengajuan_id = :pengajuan_id";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':pengajuan_id' => $pengajuan_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: detail_pengajuan.php?id=' . $pengajuan_id . '&status=success&message=' . urlencode('Status berhasil diubah menjadi Sedang Ditinjau'));
    } else {
        header('Location: detail_pengajuan.php?id=' . $pengajuan_id . '&status=error&message=' . urlencode('Gagal mengubah status'));
    }
} catch (PDOException $e) {
    header('Location: detail_pengajuan.php?id=' . $pengajuan_id . '&status=error&message=' . urlencode('Error: ' . $e->getMessage()));
}
exit();
?>