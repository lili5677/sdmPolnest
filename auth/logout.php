<?php
// Logout untuk pelamar

session_start();

// Unset semua variabel session
$_SESSION = array();

// Hapus cookie session jika ada
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Hapus cookie remember me jika ada
if (isset($_COOKIE['remember_email'])) {
    setcookie('remember_email', '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect ke login dengan notifikasi logout
header('Location: ../index.php?logout=1');
exit;
?>