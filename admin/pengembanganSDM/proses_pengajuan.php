<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// redirect
function redirect($success, $message, $return_to = 'pengembangan-sdm.php') {
    $status = $success ? 'success' : 'error';
    header("Location: $return_to?status=$status&message=" . urlencode($message));
    exit();
}

if (!isset($_GET['action']) || !isset($_GET['id'])) {
    redirect(false, 'Parameter tidak lengkap');
}

$action = $_GET['action'];
$pengajuan_id = (int)$_GET['id'];

// Validasi action
if (!in_array($action, ['approve', 'reject'])) {
    redirect(false, 'Aksi tidak valid');
}

// Validasi ID
if ($pengajuan_id <= 0) {
    redirect(false, 'ID pengajuan tidak valid');
}

if ($action === 'reject') {
    if (!isset($_GET['reason']) || empty(trim($_GET['reason']))) {
        redirect(false, 'Alasan penolakan harus diisi', "detail_pengajuan.php?id=$pengajuan_id");
    }
    
    $reason = trim($_GET['reason']);
    
    // Validasi panjang alasan
    if (strlen($reason) < 10) {
        redirect(false, 'Alasan penolakan minimal 10 karakter', "detail_pengajuan.php?id=$pengajuan_id");
    }
}

try {
    // Mulai transaksi
    $conn->beginTransaction();
    
    // Cek apakah pengajuan ada dan statusnya valid untuk diproses
    $query_check = "SELECT ps.pengajuan_id, 
                           ps.status_pengajuan, 
                           ps.nama_lengkap,
                           ps.pegawai_id,
                           ps.jenjang_pendidikan,
                           ps.nama_institusi,
                           ps.program_studi,
                           p.email
                    FROM pengajuan_studi ps
                    JOIN pegawai p ON ps.pegawai_id = p.pegawai_id
                    WHERE ps.pengajuan_id = :pengajuan_id";
    
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->execute([':pengajuan_id' => $pengajuan_id]);
    $pengajuan = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    // Validasi pengajuan exists
    if (!$pengajuan) {
        $conn->rollBack();
        redirect(false, 'Pengajuan tidak ditemukan');
    }
    
    // Validasi status - hanya bisa diproses jika statusnya belum final
    $allowed_statuses = ['diajukan', 'ditinjau', 'menunggu_persetujuan'];
    if (!in_array($pengajuan['status_pengajuan'], $allowed_statuses)) {
        $conn->rollBack();
        redirect(false, 'Pengajuan sudah diproses sebelumnya', "detail_pengajuan.php?id=$pengajuan_id");
    }
    
    // Proses berdasarkan action
    if ($action === 'approve') {
        // APPROVE PENGAJUAN
        $new_status = 'disetujui';
        $catatan = 'Pengajuan disetujui oleh admin';
        
        $query_update = "UPDATE pengajuan_studi 
                        SET status_pengajuan = :status,
                            catatan_hrd = :catatan,
                            updated_at = NOW()
                        WHERE pengajuan_id = :pengajuan_id";
        
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->execute([
            ':status' => $new_status,
            ':catatan' => $catatan,
            ':pengajuan_id' => $pengajuan_id
        ]);
        
        $success_message = "Pengajuan dari {$pengajuan['nama_lengkap']} berhasil disetujui";
        
    } else {
        // REJECT PENGAJUAN
        $new_status = 'ditolak';
        $catatan = "Pengajuan ditolak. Alasan: " . $reason;
       
        $query_update = "UPDATE pengajuan_studi 
                        SET status_pengajuan = :status,
                            catatan_hrd = :catatan,
                            updated_at = NOW()
                        WHERE pengajuan_id = :pengajuan_id";
        
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->execute([
            ':status' => $new_status,
            ':catatan' => $catatan,
            ':pengajuan_id' => $pengajuan_id
        ]);
        
        $success_message = "Pengajuan dari {$pengajuan['nama_lengkap']} berhasil ditolak";
    }
    
    // Cek apakah update berhasil
    if ($stmt_update->rowCount() === 0) {
        $conn->rollBack();
        redirect(false, 'Gagal memproses pengajuan. Tidak ada perubahan data');
    }

    $conn->commit();
    
    // Redirect dengan pesan sukses
    redirect(true, $success_message, "detail_pengajuan.php?id=$pengajuan_id");
    
} catch (PDOException $e) {
    // Rollback jika ada error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log error 
    error_log("Error processing pengajuan: " . $e->getMessage());
    
    redirect(false, 'Terjadi kesalahan sistem. Silakan coba lagi.', "detail_pengajuan.php?id=$pengajuan_id");
}
?>