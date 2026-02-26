<?php
require_once 'config/database.php'; 

echo "<h2>Script Fix Data Pegawai</h2>";
echo "<hr>";

try {
    // Cari semua user dengan user_type 
    $query = "
        SELECT u.* 
        FROM users u
        LEFT JOIN pegawai p ON u.user_id = p.user_id
        WHERE u.user_type = 'pegawai' 
        AND u.is_active = 1
        AND p.pegawai_id IS NULL
    ";
    
    $result = $conn->query($query);
    
    if ($result->num_rows === 0) {
        echo "<p style='color: green;'>✓ Semua data sudah sinkron. Tidak ada yang perlu diperbaiki.</p>";
    } else {
        echo "<p>Ditemukan <strong>" . $result->num_rows . "</strong> pegawai yang belum ada di tabel pegawai.</p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>User ID</th>
                <th>Email</th>
                <th>Nama</th>
                <th>Status</th>
              </tr>";
        
        $sukses = 0;
        $gagal = 0;
        
        while ($user = $result->fetch_assoc()) {
            $user_id = $user['user_id'];
            $email = $user['email'];
            $nama = $user['nama_lengkap'] ?? 'Belum diisi';
            
            // Insert ke tabel pegawai
            $stmt = $conn->prepare("
                INSERT INTO pegawai (
                    user_id, 
                    nik, 
                    nama_lengkap, 
                    tempat_lahir, 
                    tanggal_lahir, 
                    jenis_kelamin, 
                    email, 
                    no_telepon, 
                    alamat_domisili, 
                    alamat_ktp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $nik = $user['nik'];
            $nama_lengkap = $user['nama_lengkap'];
            $tempat_lahir = $user['tempat_lahir'];
            $tanggal_lahir = $user['tanggal_lahir'];
            $jenis_kelamin = $user['jenis_kelamin'];
            $no_telepon = $user['no_telepon'];
            $alamat_domisili = $user['alamat_domisili'];
            $alamat_ktp = $user['alamat_ktp'];
            
            $stmt->bind_param(
                "isssssssss",
                $user_id,
                $nik,
                $nama_lengkap,
                $tempat_lahir,
                $tanggal_lahir,
                $jenis_kelamin,
                $email,
                $no_telepon,
                $alamat_domisili,
                $alamat_ktp
            );
            
            if ($stmt->execute()) {
                echo "<tr style='background: #e8f5e9;'>
                        <td>$user_id</td>
                        <td>$email</td>
                        <td>$nama</td>
                        <td style='color: green;'>✓ Berhasil ditambahkan</td>
                      </tr>";
                $sukses++;
            } else {
                echo "<tr style='background: #ffebee;'>
                        <td>$user_id</td>
                        <td>$email</td>
                        <td>$nama</td>
                        <td style='color: red;'>✗ Gagal: " . $stmt->error . "</td>
                      </tr>";
                $gagal++;
            }
        }
        
        echo "</table>";
        echo "<br>";
        echo "<p><strong>Ringkasan:</strong></p>";
        echo "<ul>";
        echo "<li style='color: green;'>Berhasil: $sukses pegawai</li>";
        echo "<li style='color: red;'>Gagal: $gagal pegawai</li>";
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Kembali</a></p>";
?>