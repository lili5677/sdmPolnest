<?php

function sinkronisasiUserType($conn, $user_id, $jenis_pegawai) {
    // Mapping jenis_pegawai ke user_type
    $user_type_map = [
        'dosen' => 'dosen',
        'staff' => 'pegawai',
        'tendik' => 'pegawai'
    ];
    
    // Ambil user_type sesuai mapping, default 'pegawai' kalau tidak ketemu
    $user_type = $user_type_map[$jenis_pegawai] ?? 'pegawai';
    
    try {
        $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE user_id = ?");
        $stmt->execute([$user_type, $user_id]);
        return true;
    } catch (PDOException $e) {
        // Log error kalau perlu
        error_log("Error sinkronisasi user_type: " . $e->getMessage());
        return false;
    }
}
?>