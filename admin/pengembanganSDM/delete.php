<?php

require_once '../../config/database.php';

// Fungsi untuk redirect dengan notifikasi
function redirect($success, $message) {
    $status = $success ? 'success' : 'error';
    header("Location: pengembangan-sdm.php?tab=template&status=$status&message=" . urlencode($message));
    exit();
}

// Validasi parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect(false, 'ID template tidak valid');
}

$template_id = (int) $_GET['id'];

try {
    // Ambil data template dari database
    $query = "SELECT * FROM template_surat WHERE template_id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        redirect(false, 'Template tidak ditemukan');
    }
    
    // Hapus file fisik jika ada
    if (file_exists($template['path_file'])) {
        if (!unlink($template['path_file'])) {
            redirect(false, 'Gagal menghapus file template');
        }
    }
    
    // Hapus data dari database
    $query_delete = "DELETE FROM template_surat WHERE template_id = :id";
    $stmt_delete = $conn->prepare($query_delete);
    $stmt_delete->execute([':id' => $template_id]);
    
    redirect(true, 'Template berhasil dihapus');
    
} catch (PDOException $e) {
    redirect(false, 'Gagal menghapus template: ' . $e->getMessage());
}
?>