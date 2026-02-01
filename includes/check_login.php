<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function checkLogin($required_user_type = null) {
    if (!isset($_SESSION['user_id'])) {
        // Redirect to appropriate login page
        if ($required_user_type === 'admin') {
            header('Location: ' . BASE_URL . 'auth/login_admin.php');
        } elseif ($required_user_type === 'pegawai' || $required_user_type === 'dosen') {
            header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
        } else {
            header('Location: ' . BASE_URL . 'auth/login_pelamar.php');
        }
        exit;
    }
    
    // Check user type if specified
    if ($required_user_type && $_SESSION['user_type'] !== $required_user_type) {
        header('Location: ' . BASE_URL . 'auth/unauthorized.php');
        exit;
    }
    
    return true;
}

// Function to check if user is logged in (without redirect)
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'user_type' => $_SESSION['user_type'] ?? null,
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? null
    ];
}