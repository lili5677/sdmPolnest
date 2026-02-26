<?php
session_start();
require_once '../../config/database.php';

// Cek user login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

try {
    // mengambil data pegawai lama dengan token
    $query = "SELECT 
                p.nama_lengkap,
                p.email,
                p.jenis_pegawai,
                u.token,
                CASE 
                    WHEN u.password_changed = 1 THEN 'Sudah Login'
                    ELSE 'Belum Login'
                END as status_login,
                DATE_FORMAT(p.created_at, '%d-%m-%Y') as tanggal_ditambahkan
              FROM pegawai p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.is_pegawai_lama = 1
              ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $pegawai_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pegawai_data)) {
        $_SESSION['error'] = "Tidak ada data pegawai lama untuk di-export";
        header("Location: manajemen-pegawai.php");
        exit();
    }
    
    // download CSV
    $filename = "Data_Pegawai_Lama_" . date('Y-m-d_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $header = [
        'Nama Lengkap',
        'Email',
        'Jenis Pegawai',
        'Token Login',
        'Status Login',
        'Tanggal Ditambahkan'
    ];
    fputcsv($output, $header);
    
    //  data pegawai
    foreach ($pegawai_data as $row) {
        $csv_row = [
            $row['nama_lengkap'],
            $row['email'],
            ucfirst($row['jenis_pegawai']),
            $row['token'],
            $row['status_login'],
            $row['tanggal_ditambahkan']
        ];
        fputcsv($output, $csv_row);
    }
    
    fclose($output);
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Terjadi kesalahan saat export data: " . $e->getMessage();
    header("Location: manajemen-pegawai.php");
    exit();
}
?>