<?php
// Koneksi Database
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// ================== AMBIL DATA LOWONGAN ==================
$lowongan_id = $_GET['id'] ?? 0;

$stmt = $conn->prepare("SELECT * FROM lowongan_pekerjaan WHERE lowongan_id = ?");
$stmt->execute([$lowongan_id]);
$lowongan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lowongan) {
    header('Location: index.php');
    exit;
}

// ================== PROSES UPDATE ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil & bersihkan input gaji
    $gaji_range = $_POST['gaji_range'] ?? '';
    $gaji_range = preg_replace('/[^0-9\-]/', '', $gaji_range);
    $gaji = explode('-', $gaji_range);

    // FIX: izinkan kosong → NULL (DB nullable). Validasi hanya kalau ada isi.
    $gaji_min = (isset($gaji[0]) && $gaji[0] !== '') ? (int)$gaji[0] : null;
    $gaji_max = (isset($gaji[1]) && $gaji[1] !== '') ? (int)$gaji[1] : null;

    if ($gaji_min !== null || $gaji_max !== null) {
        // Kalau salah satu diisi, keduanya harus valid
        if ($gaji_min === null || $gaji_max === null || $gaji_min <= 0 || $gaji_max <= 0) {
            $error = "Jika rentang gaji diisi, kedua nilai harus valid dan lebih dari 0";
        } elseif ($gaji_min > $gaji_max) {
            $error = "Gaji minimum tidak boleh lebih besar dari gaji maksimum";
        }
    }

    // FIX: deadline boleh kosong → NULL
    $deadline = $_POST['deadline_lamaran'] ?? '';
    if ($deadline !== '' && strtotime($deadline) < strtotime(date('Y-m-d'))) {
        $error = "Deadline lamaran tidak boleh tanggal yang sudah lewat";
    }

    if (!isset($error)) {
        try {
            $stmt = $conn->prepare("
                UPDATE lowongan_pekerjaan SET
                    posisi = ?,
                    gaji_min = ?,
                    gaji_max = ?,
                    formasi = ?,
                    deadline_lamaran = ?,
                    deskripsi_pekerjaan = ?,
                    tanggung_jawab = ?,
                    kualifikasi = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE lowongan_id = ?
            ");

            $stmt->execute([
                $_POST['posisi'],
                $gaji_min,                                          // NULL kalau kosong
                $gaji_max,                                          // NULL kalau kosong
                $_POST['formasi'],
                ($deadline !== '') ? $deadline : null,              // NULL kalau kosong
                $_POST['deskripsi_pekerjaan'],
                $_POST['tanggung_jawab'],
                $_POST['kualifikasi'],
                $lowongan_id
            ]);

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = "Gagal mengupdate data: " . $e->getMessage();
        }
    }
}

// Siapkan nilai gaji untuk ditampilkan — guard NULL
$gaji_display = '';
if (!empty($lowongan['gaji_min']) && !empty($lowongan['gaji_max'])) {
    $gaji_display = number_format($lowongan['gaji_min'], 0, ',', '.') . ' - ' . number_format($lowongan['gaji_max'], 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Lowongan</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

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
        input, textarea {
            width: 100%; padding: 12px 14px; border-radius: 8px;
            border: 1px solid #d1d5db; font-size: 14px; font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }
        input:focus, textarea:focus { outline: none; border-color: #1e40af; box-shadow: 0 0 0 3px rgba(30,64,175,0.1); }
        textarea { resize: vertical; min-height: 90px; }
        .input-helper { font-size: 12px; color: #6b7280; margin-top: 4px; }

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
                    <h2><i class="bi bi-pencil-square"></i> Edit Lowongan Kerja</h2>
                </div>

                <div class="modal-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Posisi</label>
                            <input type="text" name="posisi" value="<?= htmlspecialchars($lowongan['posisi']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Formasi</label>
                            <input type="number" name="formasi" value="<?= $lowongan['formasi'] ?>" min="1" required>
                        </div>

                        <div class="form-group">
                            <label>Rentang Gaji</label>
                            <!-- FIX: value pakai $gaji_display yang sudah di-guard NULL di atas -->
                            <input type="text" name="gaji_range"
                                   value="<?= htmlspecialchars($gaji_display) ?>"
                                   placeholder="Contoh: 8.000.000 - 12.000.000">
                            <span class="input-helper">Kosongkan jika gaji dirahasiakan</span>
                        </div>

                        <div class="form-group">
                            <label>Deadline Lamaran</label>
                            <!-- FIX: guard NULL — kalau NULL value kosong, bukan string "NULL" -->
                            <input type="date" name="deadline_lamaran"
                                   value="<?= !empty($lowongan['deadline_lamaran']) ? $lowongan['deadline_lamaran'] : '' ?>">
                            <span class="input-helper">Kosongkan jika belum ditentukan</span>
                        </div>

                        <div class="form-group">
                            <label>Deskripsi Pekerjaan</label>
                            <textarea name="deskripsi_pekerjaan"><?= htmlspecialchars($lowongan['deskripsi_pekerjaan'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Tanggung Jawab</label>
                            <textarea name="tanggung_jawab"><?= htmlspecialchars($lowongan['tanggung_jawab'] ?? '') ?></textarea>
                            <span class="input-helper">Pisahkan setiap poin dengan baris baru (Enter)</span>
                        </div>

                        <div class="form-group">
                            <label>Kualifikasi</label>
                            <textarea name="kualifikasi" required><?= htmlspecialchars($lowongan['kualifikasi'] ?? '') ?></textarea>
                            <span class="input-helper">Pisahkan setiap poin dengan baris baru (Enter)</span>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-cancel" onclick="location.href='index.php'">
                                <i class="bi bi-x-circle"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-submit">
                                <i class="bi bi-save"></i> Simpan
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