<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

// Handle verification 
if (isset($_POST['action']) && $_POST['action'] === 'verify') {
    $penilaian_id = (int)$_POST['penilaian_id'];
    $status = $_POST['status']; 
    
    try {
        if ($status === 'sudah_dilihat') {
            $stmt = $conn->prepare("UPDATE penilaian_kinerja 
                                   SET status_verifikasi = 'sudah_dilihat', 
                                       verified_by = ?, 
                                       verified_at = NOW() 
                                   WHERE penilaian_id = ?");
            $stmt->execute([$_SESSION['user_id'], $penilaian_id]);
            $_SESSION['success'] = "Penilaian berhasil ditandai sudah dilihat!";
        } else {
            $stmt = $conn->prepare("UPDATE penilaian_kinerja 
                                   SET status_verifikasi = 'belum_dilihat', 
                                       verified_by = NULL, 
                                       verified_at = NULL 
                                   WHERE penilaian_id = ?");
            $stmt->execute([$penilaian_id]);
            $_SESSION['success'] = "Penilaian berhasil ditandai belum dilihat!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal mengupdate status: " . $e->getMessage();
    }
    
    // Redirect kembali
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'detail.php') !== false) {
        header("Location: detail.php?id=" . $penilaian_id);
    } else {
        header("Location: penilaianKinerja.php");
    }
    exit();
}

header("Location: penilaianKinerja.php");
exit();
?>