<?php
require_once '../../config/database.php';

// Cek user login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// proses tambah lowongan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil & bersihkan input gaji
    $gaji_range = $_POST['gaji_range'] ?? '';
    $gaji_range = preg_replace('/[^0-9\-]/', '', $gaji_range);
    $gaji = explode('-', $gaji_range);

    // db null
    $gaji_min = (isset($gaji[0]) && $gaji[0] !== '') ? (int)$gaji[0] : null;
    $gaji_max = (isset($gaji[1]) && $gaji[1] !== '') ? (int)$gaji[1] : null;

    if ($gaji_min !== null || $gaji_max !== null) {
        // validasi
        if ($gaji_min === null || $gaji_max === null || $gaji_min <= 0 || $gaji_max <= 0) {
            $error = "Jika rentang gaji diisi, kedua nilai harus valid dan lebih dari 0";
        } elseif ($gaji_min > $gaji_max) {
            $error = "Gaji minimum tidak boleh lebih besar dari gaji maksimum";
        }
    }

    // deadline NULL
    $deadline = $_POST['deadline_lamaran'] ?? '';
    if ($deadline !== '' && strtotime($deadline) < strtotime(date('Y-m-d'))) {
        $error = "Deadline lamaran tidak boleh tanggal yang sudah lewat";
    }

    // Validasi jenis_posisi
    $jenis_posisi = $_POST['jenis_posisi'] ?? '';
    if (!in_array($jenis_posisi, ['dosen', 'staff', 'tendik'])) {
        $error = "Jenis posisi harus dipilih (Dosen, Staff, atau Tendik)";
    }

    if (!isset($error)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO lowongan_pekerjaan (
                    posisi, 
                    jenis_posisi,
                    formasi, 
                    gaji_min, 
                    gaji_max, 
                    deadline_lamaran, 
                    deskripsi_pekerjaan, 
                    tanggung_jawab, 
                    kualifikasi, 
                    status, 
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?)
            ");

            $stmt->execute([
                $_POST['posisi'],
                $jenis_posisi,
                $_POST['formasi'],
                $gaji_min,
                $gaji_max,
                ($deadline !== '') ? $deadline : null,
                $_POST['deskripsi_pekerjaan'],
                $_POST['tanggung_jawab'],
                $_POST['kualifikasi'],
                $_SESSION['user_id']
            ]);

            $_SESSION['flash_message'] = 'Lowongan berhasil ditambahkan';
            $_SESSION['flash_type'] = 'success';
            header('Location: manajemen-loker.php');
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menambahkan lowongan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Lowongan Baru</title>

    <title>Tambah Lowongan - Sistem SDM Polnest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
     <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f5f7fa; }
        .app-container { display: flex; min-height: 100vh; }
        .main-content { margin-left: 280px; padding: 30px; width: 100%; }

        .modal-overlay { display: flex; justify-content: center; align-items: center; }
        .modal { background: #fff; width: 100%; max-width: 650px; border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
        .modal-header { padding: 22px; border-bottom: 1px solid #e5e7eb; }
        .modal-header h2 { font-size: 20px; font-weight: 600; }
        .modal-body { padding: 24px; }

        .form-group { margin-bottom: 18px; }
        label { font-weight: 500; font-size: 14px; margin-bottom: 6px; display: block; }
        label .required { color: #ef4444; margin-left: 2px; }
        input, textarea, select {
            width: 100%; padding: 12px 14px; border-radius: 8px;
            border: 1px solid #d1d5db; font-size: 14px; font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }
        input:focus, textarea:focus, select:focus { 
            outline: none; 
            border-color: #1e40af; 
            box-shadow: 0 0 0 3px rgba(30,64,175,0.1); 
        }
        textarea { resize: vertical; min-height: 90px; }
        .input-helper { font-size: 12px; color: #6b7280; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
        .input-helper i { font-size: 13px; }

        .modal-footer { padding: 18px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }
        .btn { padding: 10px 22px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-cancel { background: #e5e7eb; color: #374151; }
        .btn-cancel:hover { background: #d1d5db; }
        .btn-submit { background: #1e40af; color: white; }
        .btn-submit:hover { background: #1e3a8a; }

        .alert { padding: 12px 16px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 15px; font-size: 14px; border-left: 4px solid #ef4444; display: flex; align-items: center; gap: 8px; }

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../sidebar/sidebar.php'; ?>

    <main class="main-content">
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2><i class="bi bi-plus-circle"></i> Tambah Lowongan Kerja Baru</h2>
                </div>

                <div class="modal-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Nama Posisi <span class="required">*</span></label>
                            <input type="text" 
                                   name="posisi" 
                                   placeholder="Contoh: Dosen Teknik Informatika" 
                                   value="<?= htmlspecialchars($_POST['posisi'] ?? '') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>
                                <i class="bi bi-people-fill"></i> Jenis Posisi 
                                <span class="required">*</span>
                            </label>
                            <select name="jenis_posisi" id="jenis_posisi" required>
                                <option value="">-- Pilih Jenis Posisi --</option>
                                <option value="dosen" <?= ($_POST['jenis_posisi'] ?? '') === 'dosen' ? 'selected' : '' ?>>
                                    Dosen
                                </option>
                                <option value="staff" <?= ($_POST['jenis_posisi'] ?? '') === 'staff' ? 'selected' : '' ?>>
                                    Staff
                                </option>
                                <option value="tendik" <?= ($_POST['jenis_posisi'] ?? '') === 'tendik' ? 'selected' : '' ?>>
                                    Tenaga Kependidikan (Tendik)
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Jumlah Formasi <span class="required">*</span></label>
                            <input type="number" 
                                   name="formasi" 
                                   placeholder="Berapa orang yang dibutuhkan?"
                                   value="<?= htmlspecialchars($_POST['formasi'] ?? '') ?>"
                                   min="1" 
                                   required>
                            <span class="input-helper">
                                <i class="bi bi-info-circle"></i>
                                Jumlah pegawai yang akan diterima
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Rentang Gaji</label>
                            <input type="text" 
                                   name="gaji_range"
                                   placeholder="Contoh: 8.000.000 - 12.000.000"
                                   value="<?= htmlspecialchars($_POST['gaji_range'] ?? '') ?>">
                            <span class="input-helper">
                                <i class="bi bi-info-circle"></i>
                                Kosongkan jika gaji dirahasiakan
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Deadline Lamaran</label>
                            <input type="date" 
                                   name="deadline_lamaran"
                                   value="<?= htmlspecialchars($_POST['deadline_lamaran'] ?? '') ?>"
                                   min="<?= date('Y-m-d') ?>">
                            <span class="input-helper">
                                <i class="bi bi-info-circle"></i>
                                Kosongkan jika belum ditentukan
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Deskripsi Pekerjaan</label>
                            <textarea name="deskripsi_pekerjaan" 
                                      placeholder="Jelaskan secara detail tentang pekerjaan ini..."><?= htmlspecialchars($_POST['deskripsi_pekerjaan'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Tanggung Jawab</label>
                            <textarea name="tanggung_jawab" 
                                      placeholder="Tulis tanggung jawab, pisahkan dengan Enter..."><?= htmlspecialchars($_POST['tanggung_jawab'] ?? '') ?></textarea>
                            <span class="input-helper">
                                <i class="bi bi-info-circle"></i>
                                Pisahkan setiap poin dengan baris baru (Enter)
                            </span>
                        </div>

                        <div class="form-group">
                            <label>Kualifikasi <span class="required">*</span></label>
                            <textarea name="kualifikasi" 
                                      placeholder="Tulis kualifikasi yang dibutuhkan, pisahkan dengan Enter..."
                                      required><?= htmlspecialchars($_POST['kualifikasi'] ?? '') ?></textarea>
                            <span class="input-helper">
                                <i class="bi bi-info-circle"></i>
                                Pisahkan setiap poin dengan baris baru (Enter)
                            </span>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-cancel" onclick="location.href='manajemen-loker.php'">
                                <i class="bi bi-x-circle"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-submit">
                                <i class="bi bi-save"></i> Simpan Lowongan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    document.querySelector('input[name="gaji_range"]').addEventListener('input', function (e) {
        // Izinkan angka, titik (separator), spasi, dan minus
        e.target.value = e.target.value.replace(/[^0-9\.\-\s]/g, '');
    });
</script>
</body>
</html>