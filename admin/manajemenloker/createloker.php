<?php
// Koneksi Database
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validasi dan bersihkan input gaji
        $gaji_range = $_POST['gaji_range'] ?? '';
        $gaji_range = preg_replace('/[^0-9\-]/', '', $gaji_range);
        $gaji = explode('-', $gaji_range);

        $gaji_min = isset($gaji[0]) && $gaji[0] !== '' ? (int)trim($gaji[0]) : null;
        $gaji_max = isset($gaji[1]) && $gaji[1] !== '' ? (int)trim($gaji[1]) : null;

        // Validasi gaji hanya kalau ada isi
        if ($gaji_min !== null || $gaji_max !== null) {
            if ($gaji_min === null || $gaji_max === null || $gaji_min <= 0 || $gaji_max <= 0) {
                throw new Exception('Jika rentang gaji diisi, kedua nilai harus valid dan lebih dari 0');
            }
            if ($gaji_min > $gaji_max) {
                throw new Exception('Gaji minimum tidak boleh lebih besar dari gaji maksimum');
            }
        }

        // Validasi formasi
        $formasi = (int)$_POST['formasi'];
        if ($formasi <= 0) {
            throw new Exception('Formasi harus lebih dari 0');
        }

        // Validasi deadline
        $deadline = $_POST['deadline_lamaran'] ?? '';
        if ($deadline === '') {
            throw new Exception('Deadline lamaran wajib diisi');
        }
        if (strtotime($deadline) < strtotime(date('Y-m-d'))) {
            throw new Exception('Deadline lamaran tidak boleh tanggal yang sudah lewat');
        }

        // FIX: created_by pakai $_SESSION['user_id'], bukan hardcoded 1
        $stmt = $conn->prepare("
            INSERT INTO lowongan_pekerjaan 
            (posisi, gaji_min, gaji_max, formasi, deadline_lamaran, deskripsi_pekerjaan, tanggung_jawab, kualifikasi, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif', ?)
        ");

        $stmt->execute([
            $_POST['posisi'],
            $gaji_min,                      // NULL kalau kosong
            $gaji_max,                      // NULL kalau kosong
            $formasi,
            $deadline,
            $_POST['deskripsi_pekerjaan'],
            $_POST['tanggung_jawab'],
            $_POST['kualifikasi'],
            $_SESSION['user_id']            // FIX: dari session, bukan 1
        ]);

        header('Location: index.php?success=1');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = "Gagal menambahkan lowongan: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Lowongan - Sistem SDM Polnest</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f5f7fa; color: #333; }
        .app-container { display: flex; min-height: 100vh; }
        .main-content { margin-left: 280px; padding: 30px; flex: 1; width: calc(100% - 280px); display: flex; flex-direction: column; align-items: center; }
        .content-wrapper { width: 100%; max-width: 900px; }

        .back-button { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; font-size: 14px; margin-bottom: 20px; transition: color 0.2s; }
        .back-button:hover { color: #3b82f6; }

        .form-card { background: white; border-radius: 12px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .form-header { margin-bottom: 32px; }
        .form-header h1 { font-size: 24px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
        .form-header p { font-size: 14px; color: #64748b; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: 1 / -1; }

        label { font-size: 14px; font-weight: 600; color: #334155; }
        label .required { color: #ef4444; }

        input[type="text"], input[type="number"], input[type="date"], textarea {
            padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s; width: 100%;
        }
        input:focus, textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        textarea { min-height: 100px; resize: vertical; }
        textarea.large { min-height: 150px; }

        .input-helper { font-size: 12px; color: #64748b; margin-top: -4px; }

        .button-group { display: flex; gap: 12px; justify-content: flex-end; margin-top: 32px; }
        .btn {
            padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; border: none; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-cancel { background-color: #f1f5f9; color: #475569; }
        .btn-cancel:hover { background-color: #e2e8f0; }
        .btn-submit { background-color: #3b82f6; color: white; }
        .btn-submit:hover { background-color: #2563eb; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-error { background-color: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        @media (max-width: 1024px) { .main-content { margin-left: 0; width: 100%; } }
        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .main-content { padding: 20px; } }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../sidebar/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-wrapper">
                <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i> Kembali</a>

                <div class="form-card">
                    <div class="form-header">
                        <h1>Tambah Lowongan Kerja</h1>
                        <p>Isi form di bawah untuk menambahkan lowongan baru</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="posisi">Posisi <span class="required">*</span></label>
                                <input type="text" id="posisi" name="posisi" placeholder="Contoh: Dosen Teknik Informatika" required
                                       value="<?= isset($_POST['posisi']) ? htmlspecialchars($_POST['posisi']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="formasi">Formasi <span class="required">*</span></label>
                                <input type="number" id="formasi" name="formasi" placeholder="Jumlah formasi" min="1" required
                                       value="<?= isset($_POST['formasi']) ? htmlspecialchars($_POST['formasi']) : '' ?>">
                                <span class="input-helper">Jumlah orang yang dibutuhkan</span>
                            </div>

                            <div class="form-group">
                                <label for="gaji_range">Rentang Gaji</label>
                                <input type="text" id="gaji_range" name="gaji_range" placeholder="Contoh: 8000000 - 12000000"
                                       value="<?= isset($_POST['gaji_range']) ? htmlspecialchars($_POST['gaji_range']) : '' ?>">
                                <span class="input-helper">Format: angka - angka. Kosongkan jika dirahasiakan</span>
                            </div>

                            <div class="form-group">
                                <label for="deadline_lamaran">Deadline Lamaran <span class="required">*</span></label>
                                <input type="date" id="deadline_lamaran" name="deadline_lamaran" required min="<?= date('Y-m-d') ?>"
                                       value="<?= isset($_POST['deadline_lamaran']) ? htmlspecialchars($_POST['deadline_lamaran']) : '' ?>">
                                <span class="input-helper">Tanggal terakhir penerimaan lamaran</span>
                            </div>

                            <div class="form-group full-width">
                                <label for="deskripsi_pekerjaan">Deskripsi Pekerjaan</label>
                                <textarea id="deskripsi_pekerjaan" name="deskripsi_pekerjaan" placeholder="Deskripsikan pekerjaan secara umum"><?= isset($_POST['deskripsi_pekerjaan']) ? htmlspecialchars($_POST['deskripsi_pekerjaan']) : '' ?></textarea>
                                <span class="input-helper">Gambaran umum tentang pekerjaan ini</span>
                            </div>

                            <div class="form-group full-width">
                                <label for="tanggung_jawab">Tanggung Jawab Utama</label>
                                <textarea id="tanggung_jawab" name="tanggung_jawab" placeholder="Tulis setiap tanggung jawab per baris"><?= isset($_POST['tanggung_jawab']) ? htmlspecialchars($_POST['tanggung_jawab']) : '' ?></textarea>
                                <span class="input-helper">Pisahkan setiap poin dengan baris baru (Enter)</span>
                            </div>

                            <div class="form-group full-width">
                                <label for="kualifikasi">Kualifikasi <span class="required">*</span></label>
                                <textarea id="kualifikasi" name="kualifikasi" class="large" placeholder="Tulis setiap kualifikasi per baris" required><?= isset($_POST['kualifikasi']) ? htmlspecialchars($_POST['kualifikasi']) : '' ?></textarea>
                                <span class="input-helper">Persyaratan yang harus dipenuhi pelamar (pisahkan dengan baris baru)</span>
                            </div>
                        </div>

                        <div class="button-group">
                            <a href="index.php" class="btn btn-cancel"><i class="fas fa-times"></i> Batal</a>
                            <button type="submit" class="btn btn-submit"><i class="fas fa-save"></i> Simpan Lowongan</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('gaji_range').addEventListener('input', function (e) {
            // Izinkan angka dan minus saja
            e.target.value = e.target.value.replace(/[^0-9\-]/g, '');
        });

        document.querySelector('form').addEventListener('submit', function (e) {
            const gajiRange = document.getElementById('gaji_range').value.trim();
            const formasi = document.getElementById('formasi').value;

            // Validasi formasi
            if (parseInt(formasi) <= 0) {
                e.preventDefault();
                alert('Formasi harus lebih dari 0');
                return false;
            }

            // Validasi gaji hanya kalau ada isi
            if (gajiRange !== '') {
                if (!gajiRange.includes('-') || gajiRange.split('-').length !== 2) {
                    e.preventDefault();
                    alert('Format rentang gaji harus: angka - angka\nContoh: 8000000 - 12000000');
                    return false;
                }

                const gaji = gajiRange.split('-');
                const gajiMin = parseInt(gaji[0].trim());
                const gajiMax = parseInt(gaji[1].trim());

                if (isNaN(gajiMin) || isNaN(gajiMax)) {
                    e.preventDefault();
                    alert('Rentang gaji harus berupa angka yang valid');
                    return false;
                }
                if (gajiMin > gajiMax) {
                    e.preventDefault();
                    alert('Gaji minimum tidak boleh lebih besar dari gaji maksimum');
                    return false;
                }
            }
        });
    </script>
</body>
</html>