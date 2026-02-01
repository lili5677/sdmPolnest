<?php
session_start();

// Hapus variabel session
$_SESSION = array();

// Jika menggunakan cookie session, hapus juga cookie-nya
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Hancurkan session
session_destroy();

// Hapus cookies terkait login jika ada
if (isset($_COOKIE['admin_remember'])) {
    setcookie('admin_remember', '', time()-3600, '/');
}

if (isset($_COOKIE['admin_id'])) {
    setcookie('admin_id', '', time()-3600, '/');
}

// Redirect ke halaman login dengan pesan sukses
header("Location: http://localhost/sdmPolnest/auth/login_pegawai.php?logout=success");
exit();
?>