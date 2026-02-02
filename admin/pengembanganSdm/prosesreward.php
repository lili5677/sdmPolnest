<?php
// =====================================================
// PROSES REWARD - HANDLER TERPISAH
// =====================================================

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// HANDLER: TAMBAH REWARD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    try {
        $nama = $_POST['nama'];
        $jabatan = $_POST['jabatan'];
        $keterangan = $_POST['keterangan'];
        $created_by = $_SESSION['user_id'];

        // Validasi keterangan max 100 karakter
        if (strlen($keterangan) > 100) {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Keterangan maksimal 100 karakter'));
            exit();
        }

        $upload_dir = '../../uploads/reward/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $path_gambar = null;

        // Upload Gambar (Wajib)
        if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Gambar wajib diupload'));
            exit();
        }

        $gambar = $_FILES['gambar'];
        $gambar_ext = strtolower(pathinfo($gambar['name'], PATHINFO_EXTENSION));
        $allowed_image = ['jpg', 'jpeg', 'png'];

        if (!in_array($gambar_ext, $allowed_image)) {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Format gambar harus JPG, JPEG, atau PNG'));
            exit();
        }

        if ($gambar['size'] > 5 * 1024 * 1024) {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Ukuran gambar maksimal 5MB'));
            exit();
        }

        $gambar_name = 'reward_' . time() . '_' . uniqid() . '.' . $gambar_ext;
        $gambar_path = $upload_dir . $gambar_name;

        if (move_uploaded_file($gambar['tmp_name'], $gambar_path)) {
            $path_gambar = 'uploads/reward/' . $gambar_name;
        } else {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Gagal mengupload gambar'));
            exit();
        }

        $query = "INSERT INTO reward_pegawai (nama, jabatan, keterangan, gambar, created_by, created_at) 
                  VALUES (:nama, :jabatan, :keterangan, :gambar, :created_by, NOW())";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nama' => $nama,
            ':jabatan' => $jabatan,
            ':keterangan' => $keterangan,
            ':gambar' => $path_gambar,
            ':created_by' => $created_by
        ]);

        header('Location: index.php?tab=reward&status=success&message=' . urlencode('Reward berhasil ditambahkan'));
        exit();

    } catch (Exception $e) {
        error_log('Error tambah reward: ' . $e->getMessage());
        header('Location: index.php?tab=reward&status=error&message=' . urlencode('Gagal menambahkan reward'));
        exit();
    }
}

// HANDLER: EDIT REWARD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $reward_id = (int)$_POST['reward_id'];
        $nama = $_POST['nama'];
        $jabatan = $_POST['jabatan'];
        $keterangan = $_POST['keterangan'];

        // Validasi keterangan max 100 karakter
        if (strlen($keterangan) > 100) {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Keterangan maksimal 100 karakter'));
            exit();
        }

        // Ambil data lama
        $query_old = "SELECT gambar FROM reward_pegawai WHERE reward_id = :id";
        $stmt_old = $conn->prepare($query_old);
        $stmt_old->execute([':id' => $reward_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
        
        $path_gambar = $old_data['gambar'];

        $upload_dir = '../../uploads/reward/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Upload Gambar baru jika ada
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $gambar = $_FILES['gambar'];
            $gambar_ext = strtolower(pathinfo($gambar['name'], PATHINFO_EXTENSION));
            $allowed_image = ['jpg', 'jpeg', 'png'];

            if (!in_array($gambar_ext, $allowed_image)) {
                header('Location: index.php?tab=reward&status=error&message=' . urlencode('Format gambar harus JPG, JPEG, atau PNG'));
                exit();
            }

            if ($gambar['size'] > 5 * 1024 * 1024) {
                header('Location: index.php?tab=reward&status=error&message=' . urlencode('Ukuran gambar maksimal 5MB'));
                exit();
            }

            // Hapus file lama
            if ($path_gambar && file_exists('../../' . $path_gambar)) {
                unlink('../../' . $path_gambar);
            }

            $gambar_name = 'reward_' . time() . '_' . uniqid() . '.' . $gambar_ext;
            $gambar_path = $upload_dir . $gambar_name;

            if (move_uploaded_file($gambar['tmp_name'], $gambar_path)) {
                $path_gambar = 'uploads/reward/' . $gambar_name;
            }
        }

        $query = "UPDATE reward_pegawai 
                  SET nama = :nama, 
                      jabatan = :jabatan, 
                      keterangan = :keterangan, 
                      gambar = :gambar
                  WHERE reward_id = :id";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nama' => $nama,
            ':jabatan' => $jabatan,
            ':keterangan' => $keterangan,
            ':gambar' => $path_gambar,
            ':id' => $reward_id
        ]);

        header('Location: index.php?tab=reward&status=success&message=' . urlencode('Reward berhasil diupdate'));
        exit();

    } catch (Exception $e) {
        error_log('Error edit reward: ' . $e->getMessage());
        header('Location: index.php?tab=reward&status=error&message=' . urlencode('Gagal mengupdate reward'));
        exit();
    }
}

// HANDLER: HAPUS REWARD
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    try {
        $reward_id = (int)$_GET['id'];

        // Ambil path gambar untuk dihapus
        $query_get = "SELECT gambar FROM reward_pegawai WHERE reward_id = :id";
        $stmt_get = $conn->prepare($query_get);
        $stmt_get->execute([':id' => $reward_id]);
        $reward_data = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($reward_data) {
            // Hapus file gambar
            if ($reward_data['gambar'] && file_exists('../../' . $reward_data['gambar'])) {
                unlink('../../' . $reward_data['gambar']);
            }

            // Hapus dari database
            $query_delete = "DELETE FROM reward_pegawai WHERE reward_id = :id";
            $stmt_delete = $conn->prepare($query_delete);
            $stmt_delete->execute([':id' => $reward_id]);

            header('Location: index.php?tab=reward&status=success&message=' . urlencode('Reward berhasil dihapus'));
        } else {
            header('Location: index.php?tab=reward&status=error&message=' . urlencode('Reward tidak ditemukan'));
        }
        exit();

    } catch (Exception $e) {
        error_log('Error hapus reward: ' . $e->getMessage());
        header('Location: index.php?tab=reward&status=error&message=' . urlencode('Gagal menghapus reward'));
        exit();
    }
}

// Jika tidak ada action, redirect ke index
header('Location: index.php?tab=reward');
exit();
?>