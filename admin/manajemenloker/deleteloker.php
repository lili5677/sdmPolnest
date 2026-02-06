<?php
/**
 * DELETE LOWONGAN - WITH SWEETALERT
 * File: users/pegawai/deleteloker.php
 * 
 * Fitur:
 * - Hapus PERMANEN dari database
 * - SweetAlert confirmation
 * - Check cascade delete (hapus lamaran terkait juga)
 */

// Koneksi Database
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// Get lowongan_id
$lowongan_id = $_GET['id'] ?? 0;

if ($lowongan_id > 0) {
    try {
        // STEP 1: Check apakah lowongan punya lamaran
        $check_query = "SELECT COUNT(*) as total FROM lamaran WHERE lowongan_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute([$lowongan_id]);
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $total_lamaran = $check_result['total'];
        
        // STEP 2: Mulai transaction
        $conn->beginTransaction();
        
        // STEP 3: Hapus semua lamaran terkait (CASCADE DELETE)
        if ($total_lamaran > 0) {
            $delete_lamaran = "DELETE FROM lamaran WHERE lowongan_id = ?";
            $stmt_lamaran = $conn->prepare($delete_lamaran);
            $stmt_lamaran->execute([$lowongan_id]);
        }
        
        // STEP 4: Hapus lowongan PERMANEN
        $delete_lowongan = "DELETE FROM lowongan_pekerjaan WHERE lowongan_id = ?";
        $stmt_lowongan = $conn->prepare($delete_lowongan);
        $stmt_lowongan->execute([$lowongan_id]);
        
        // STEP 5: Commit transaction
        $conn->commit();
        
        // Success message
        $_SESSION['flash_message'] = 'Lowongan berhasil dihapus permanen' . ($total_lamaran > 0 ? " (termasuk {$total_lamaran} lamaran terkait)" : '');
        $_SESSION['flash_type'] = 'success';
        
        header('Location: loker.php');
        exit;
        
    } catch (PDOException $e) {
        // Rollback jika error
        $conn->rollBack();
        
        $_SESSION['flash_message'] = 'Gagal menghapus lowongan: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        
        header('Location: loker.php');
        exit;
    }
} else {
    $_SESSION['flash_message'] = 'ID lowongan tidak valid';
    $_SESSION['flash_type'] = 'error';
    
    header('Location: index.php');
    exit;
}
?>