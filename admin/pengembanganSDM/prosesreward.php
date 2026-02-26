<?php

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// folder upload
$possible_paths = [
    __DIR__ . "/../../uploads/reward/",
    __DIR__ . "/../uploads/reward/",
    $_SERVER['DOCUMENT_ROOT'] . "/uploads/reward/",
    dirname(dirname(__DIR__)) . "/uploads/reward/"
];

$upload_dir = null;
foreach ($possible_paths as $path) {
    $parent = dirname($path);
    if (is_dir($parent) || mkdir($parent, 0777, true)) {
        $upload_dir = $path;
        break;
    }
}

if (!$upload_dir) {
    $upload_dir = __DIR__ . "/../../uploads/reward/";
}

// Buat folder jika belum ada
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Gagal membuat folder: " . $upload_dir);
    }
}

// Pastikan folder writable
if (!is_writable($upload_dir)) {
    chmod($upload_dir, 0777);
}

// upload file
function uploadFile($file)
{
    global $upload_dir;

    if (!isset($file) || $file['error'] === 4) {
        return null;
    }

    if ($file['error'] !== 0) {
        throw new Exception("Upload error kode: " . $file['error']);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Ukuran file maksimal 5MB");
    }

    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        throw new Exception("Format harus JPG, PNG, PDF");
    }

    $newName = "reward_" . time() . "_" . rand(1000,9999) . "." . $ext;
    $target = $upload_dir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception("Gagal upload file ke: " . $target);
    }

    return $newName;
}

function redirectBack($msg, $type="success"){
    $_SESSION['flash_message'] = $msg;
    $_SESSION['flash_type'] = $type;
    header("Location: pengembangan-sdm.php?tab=reward&status=$type&message=".urlencode($msg));
    exit();
}

// tambah
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'tambah') {
    try {
        // Validasi input
        if (empty($_POST['pegawai_id']) || empty($_POST['judul_reward']) || empty($_POST['kategori']) || empty($_POST['tanggal_reward'])) {
            throw new Exception("Field wajib belum lengkap");
        }

        $pegawai_id = (int)$_POST['pegawai_id'];
        $judul_reward = trim($_POST['judul_reward']);
        $kategori = trim($_POST['kategori']);
        $tanggal_reward = $_POST['tanggal_reward'];
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $created_by = $_SESSION['user_id'];

        // Upload file
        $fileName = null;
        if (isset($_FILES['file_bukti']) && $_FILES['file_bukti']['error'] !== 4) {
            $fileName = uploadFile($_FILES['file_bukti']);
        }

        $stmt = $conn->prepare("
            INSERT INTO reward_pegawai
            (pegawai_id, judul_reward, deskripsi, tanggal_reward, kategori, file_bukti, created_by, created_at)
            VALUES
            (:pegawai_id, :judul_reward, :deskripsi, :tanggal_reward, :kategori, :file_bukti, :created_by, NOW())
        ");

        $result = $stmt->execute([
            ':pegawai_id' => $pegawai_id,
            ':judul_reward' => $judul_reward,
            ':deskripsi' => $deskripsi,
            ':tanggal_reward' => $tanggal_reward,
            ':kategori' => $kategori,
            ':file_bukti' => $fileName,
            ':created_by' => $created_by
        ]);

        if (!$result) {
            throw new Exception("Gagal menyimpan data ke database");
        }

        redirectBack("Reward berhasil ditambahkan", "success");

    } catch (Exception $e) {
        error_log("Error tambah reward: " . $e->getMessage());
        redirectBack($e->getMessage(), "error");
    }
}

// edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    try {
        // Validasi input
        if (empty($_POST['reward_id']) || empty($_POST['pegawai_id']) || empty($_POST['judul_reward']) || empty($_POST['kategori']) || empty($_POST['tanggal_reward'])) {
            throw new Exception("Field wajib belum lengkap");
        }

        $reward_id = (int)$_POST['reward_id'];
        $pegawai_id = (int)$_POST['pegawai_id'];
        $judul_reward = trim($_POST['judul_reward']);
        $kategori = trim($_POST['kategori']);
        $tanggal_reward = $_POST['tanggal_reward'];
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        // Ambil file lama
        $q = $conn->prepare("SELECT file_bukti FROM reward_pegawai WHERE reward_id=?");
        $q->execute([$reward_id]);
        $old = $q->fetch(PDO::FETCH_ASSOC);
        $fileName = $old['file_bukti'] ?? null;

        // Upload baru jika ada
        if (isset($_FILES['file_bukti']) && $_FILES['file_bukti']['error'] !== 4) {
            $newFile = uploadFile($_FILES['file_bukti']);

            // Hapus file lama
            if ($fileName && file_exists($upload_dir . $fileName)) {
                unlink($upload_dir . $fileName);
            }

            $fileName = $newFile;
        }

        $stmt = $conn->prepare("
            UPDATE reward_pegawai SET
            pegawai_id = :pegawai_id,
            judul_reward = :judul_reward,
            deskripsi = :deskripsi,
            tanggal_reward = :tanggal_reward,
            kategori = :kategori,
            file_bukti = :file_bukti
            WHERE reward_id = :id
        ");

        $result = $stmt->execute([
            ':pegawai_id' => $pegawai_id,
            ':judul_reward' => $judul_reward,
            ':deskripsi' => $deskripsi,
            ':tanggal_reward' => $tanggal_reward,
            ':kategori' => $kategori,
            ':file_bukti' => $fileName,
            ':id' => $reward_id
        ]);

        if (!$result) {
            throw new Exception("Gagal mengupdate data");
        }

        redirectBack("Reward berhasil diupdate", "success");

    } catch (Exception $e) {
        error_log("Error edit reward: " . $e->getMessage());
        redirectBack($e->getMessage(), "error");
    }
}

// hapus
if (isset($_GET['action']) && $_GET['action'] == 'hapus') {
    try {
        if (empty($_GET['id'])) {
            throw new Exception("ID tidak valid");
        }

        $id = (int)$_GET['id'];

        // Ambil file
        $q = $conn->prepare("SELECT file_bukti FROM reward_pegawai WHERE reward_id=?");
        $q->execute([$id]);
        $old = $q->fetch(PDO::FETCH_ASSOC);

        if ($old && $old['file_bukti']) {
            $filePath = $upload_dir . $old['file_bukti'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = $conn->prepare("DELETE FROM reward_pegawai WHERE reward_id=?");
        $result = $stmt->execute([$id]);

        if (!$result) {
            throw new Exception("Gagal menghapus data");
        }

        redirectBack("Reward berhasil dihapus", "success");

    } catch (Exception $e) {
        error_log("Error hapus reward: " . $e->getMessage());
        redirectBack($e->getMessage(), "error");
    }
}

redirectBack("Akses tidak valid", "error");