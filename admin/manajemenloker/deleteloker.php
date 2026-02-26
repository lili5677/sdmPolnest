<?php
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

$lowongan_id = $_GET['id'] ?? 0;

if ($lowongan_id > 0) {
    try {
        $check_query = "SELECT COUNT(*) as total FROM lamaran WHERE lowongan_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$lowongan_id]);
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $total_lamaran = $check_result['total'];
        
        $conn->beginTransaction();
        
        if ($total_lamaran > 0) {
            $delete_lamaran = "DELETE FROM lamaran WHERE lowongan_id = ?";
            $stmt_lamaran = $conn->prepare($delete_lamaran);
            $stmt_lamaran->execute([$lowongan_id]);
        }
        
        $delete_lowongan = "DELETE FROM lowongan_pekerjaan WHERE lowongan_id = ?";
        $stmt_lowongan = $conn->prepare($delete_lowongan);
        $stmt_lowongan->execute([$lowongan_id]);
        
        $conn->commit();
        
        $_SESSION['flash_message'] = 'Lowongan berhasil dihapus permanen' . ($total_lamaran > 0 ? " (termasuk {$total_lamaran} lamaran terkait)" : '');
        $_SESSION['flash_type'] = 'success';
        
        header('Location: manajemen-loker.php');
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        
        $_SESSION['flash_message'] = 'Gagal menghapus lowongan: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        
        header('Location: manajemen-loker.php');
        exit;
    }
} else {
    $_SESSION['flash_message'] = 'ID lowongan tidak valid';
    $_SESSION['flash_type'] = 'error';
    
    header('Location: manajemen-manajemen-loker.php');
    exit;
}
?>