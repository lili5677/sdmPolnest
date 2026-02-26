<?php
session_start();

// Validasi login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';
// tambah pelatihan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    try {
        $judul_pelatihan = $_POST['judul_pelatihan'];
        $deskripsi = $_POST['deskripsi'] ?? null;
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $lokasi = $_POST['lokasi'];
        $instruktur = $_POST['instruktur'] ?? null;
        $created_by = $_SESSION['user_id'];

        if (!isset($_SESSION['user_id'])) {
            header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Session tidak valid'));
            exit();
        }

        if (strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
            header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Tanggal selesai tidak boleh lebih awal dari tanggal mulai'));
            exit();
        }

        $upload_dir = '../../uploads/pelatihan/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $path_flyer = null;
        $path_undangan = null;

        // Upload Flyer
        if (isset($_FILES['flyer']) && $_FILES['flyer']['error'] === UPLOAD_ERR_OK) {
            $flyer = $_FILES['flyer'];
            $flyer_ext = strtolower(pathinfo($flyer['name'], PATHINFO_EXTENSION));
            $allowed_image = ['jpg', 'jpeg', 'png'];

            if (!in_array($flyer_ext, $allowed_image)) {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Format flyer tidak valid'));
                exit();
            }

            if ($flyer['size'] > 3 * 1024 * 1024) {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file flyer maksimal 3MB'));
                exit();
            }

            $flyer_name = 'flyer_' . time() . '_' . uniqid() . '.' . $flyer_ext;
            $flyer_path = $upload_dir . $flyer_name;

            if (move_uploaded_file($flyer['tmp_name'], $flyer_path)) {
                $path_flyer = 'uploads/pelatihan/' . $flyer_name;
            }
        }

        // Upload Undangan
        if (isset($_FILES['undangan']) && $_FILES['undangan']['error'] === UPLOAD_ERR_OK) {
            $undangan = $_FILES['undangan'];
            $undangan_ext = strtolower(pathinfo($undangan['name'], PATHINFO_EXTENSION));

            if ($undangan_ext !== 'pdf') {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Format undangan tidak valid'));
                exit();
            }

            if ($undangan['size'] > 5 * 1024 * 1024) {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file undangan maksimal 5MB'));
                exit();
            }

            $undangan_name = 'undangan_' . time() . '_' . uniqid() . '.pdf';
            $undangan_path = $upload_dir . $undangan_name;

            if (move_uploaded_file($undangan['tmp_name'], $undangan_path)) {
                $path_undangan = 'uploads/pelatihan/' . $undangan_name;
            }
        }

        // Simpan data ke database
        $query = "INSERT INTO pelatihan 
        (judul_pelatihan, deskripsi, tanggal_mulai, tanggal_selesai, lokasi, instruktur, flyer, undangan, created_by)
        VALUES (:judul, :deskripsi, :mulai, :selesai, :lokasi, :instruktur, :flyer, :undangan, :created_by)";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':judul' => $judul_pelatihan,
            ':deskripsi' => $deskripsi,
            ':mulai' => $tanggal_mulai,
            ':selesai' => $tanggal_selesai,
            ':lokasi' => $lokasi,
            ':instruktur' => $instruktur,
            ':flyer' => $path_flyer,
            ':undangan' => $path_undangan,
            ':created_by' => $created_by
        ]);

        header('Location: pengembangan-sdm.php?tab=pelatihan&status=success&message=' . urlencode('Pelatihan berhasil ditambahkan'));
        exit();

    } catch (Exception $e) {
        error_log('Error tambah pelatihan: ' . $e->getMessage());
        header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Gagal menambahkan pelatihan: ' . $e->getMessage()));
        exit();
    }
}

// edit pelatihan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $judul_pelatihan = $_POST['judul_pelatihan'];
        $deskripsi = $_POST['deskripsi'] ?? null;
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $lokasi = $_POST['lokasi'];
        $instruktur = $_POST['instruktur'] ?? null;

        if (strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
            header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Tanggal selesai tidak boleh lebih awal dari tanggal mulai'));
            exit();
        }

        // Ambil data lama untuk flyer dan undangan
        $query_old = "SELECT flyer, undangan FROM pelatihan WHERE pelatihan_id = :id";
        $stmt_old = $conn->prepare($query_old);
        $stmt_old->execute([':id' => $pelatihan_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
        
        $path_flyer = $old_data['flyer'] ?? null;
        $path_undangan = $old_data['undangan'] ?? null;

        $upload_dir = '../../uploads/pelatihan/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Upload Flyer baru jika ada
        if (isset($_FILES['flyer']) && $_FILES['flyer']['error'] === UPLOAD_ERR_OK) {
            $flyer = $_FILES['flyer'];
            $flyer_ext = strtolower(pathinfo($flyer['name'], PATHINFO_EXTENSION));
            $allowed_image = ['jpg', 'jpeg', 'png'];

            if (!in_array($flyer_ext, $allowed_image)) {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Format flyer tidak valid'));
                exit();
            }

            if ($flyer['size'] > 3 * 1024 * 1024) {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file flyer maksimal 3MB'));
                exit();
            }

            // Hapus file lama
            if ($path_flyer && file_exists('../../' . $path_flyer)) {
                unlink('../../' . $path_flyer);
            }

            $flyer_name = 'flyer_' . time() . '_' . uniqid() . '.' . $flyer_ext;
            $flyer_path = $upload_dir . $flyer_name;

            if (move_uploaded_file($flyer['tmp_name'], $flyer_path)) {
                $path_flyer = 'uploads/pelatihan/' . $flyer_name;
            }
        }

        // Upload Undangan baru jika ada
        if (isset($_FILES['undangan']) && $_FILES['undangan']['error'] === UPLOAD_ERR_OK) {
            $undangan = $_FILES['undangan'];
            $undangan_ext = strtolower(pathinfo($undangan['name'], PATHINFO_EXTENSION));

            if ($undangan_ext !== 'pdf') {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Format undangan tidak valid'));
                exit();
            }

            if ($undangan['size'] > 5 * 1024 * 1024) {
                header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file undangan maksimal 5MB'));
                exit();
            }

            // Hapus file lama
            if ($path_undangan && file_exists('../../' . $path_undangan)) {
                unlink('../../' . $path_undangan);
            }

            $undangan_name = 'undangan_' . time() . '_' . uniqid() . '.pdf';
            $undangan_path = $upload_dir . $undangan_name;

            if (move_uploaded_file($undangan['tmp_name'], $undangan_path)) {
                $path_undangan = 'uploads/pelatihan/' . $undangan_name;
            }
        }

        // Update data di database
        $query = "UPDATE pelatihan SET
            judul_pelatihan = :judul,
            deskripsi = :deskripsi,
            tanggal_mulai = :mulai,
            tanggal_selesai = :selesai,
            lokasi = :lokasi,
            instruktur = :instruktur,
            flyer = :flyer,
            undangan = :undangan
            WHERE pelatihan_id = :id";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':judul' => $judul_pelatihan,
            ':deskripsi' => $deskripsi,
            ':mulai' => $tanggal_mulai,
            ':selesai' => $tanggal_selesai,
            ':lokasi' => $lokasi,
            ':instruktur' => $instruktur,
            ':flyer' => $path_flyer,
            ':undangan' => $path_undangan,
            ':id' => $pelatihan_id
        ]);

        header('Location: pengembangan-sdm.php?tab=pelatihan&status=success&message=' . urlencode('Pelatihan berhasil diupdate'));
        exit();

    } catch (Exception $e) {
        error_log('Error edit pelatihan: ' . $e->getMessage());
        header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Gagal mengupdate pelatihan: ' . $e->getMessage()));
        exit();
    }
}

// hapus pelatihan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hapus') {
    try {
        $pelatihan_id = (int)$_POST['pelatihan_id'];

        $stmt = $conn->prepare("SELECT flyer, undangan FROM pelatihan WHERE pelatihan_id = :id");
        $stmt->execute([':id' => $pelatihan_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['flyer'] && file_exists('../../' . $row['flyer'])) {
                unlink('../../' . $row['flyer']);
            }
            if ($row['undangan'] && file_exists('../../' . $row['undangan'])) {
                unlink('../../' . $row['undangan']);
            }

            $stmt = $conn->prepare("DELETE FROM pelatihan WHERE pelatihan_id = :id");
            $stmt->execute([':id' => $pelatihan_id]);
        }

        header('Location: pengembangan-sdm.php?tab=pelatihan&status=success&message=' . urlencode('Pelatihan berhasil dihapus'));
        exit();

    } catch (Exception $e) {
        error_log('Error hapus pelatihan: ' . $e->getMessage());
        header('Location: pengembangan-sdm.php?tab=pelatihan&status=error&message=' . urlencode('Gagal menghapus pelatihan'));
        exit();
    }
}

// Jika tidak ada action yang valid, redirect kembali
header('Location: pengembangan-sdm.php?tab=pelatihan');
exit();