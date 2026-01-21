<?php
// Pengelolaan Session
if (!isset($_SESSION)) {
    session_start();
}

// Cek Apakah User Sudah Login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Cek Session Timeout (30 menit)
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    
    if ($inactive_time > SESSION_TIMEOUT) {
        // Session Expired
        session_unset();
        session_destroy();
        
        // Hapus Remember Me Cookie
        if (isset($_COOKIE['remember_user'])) {
            setcookie('remember_user', '', time() - 3600, '/');
        }
        
        header('Location: ../auth/login_pelamar.php?timeout=1');
        header('Location: ../auth/login_pegawai.php?timeout=1');
        exit();
    }
}

// Update Last Activity Time
$_SESSION['last_activity'] = time();
?>