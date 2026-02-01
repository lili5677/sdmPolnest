<?php
// ================== KONEKSI DATABASE ==================
$host = 'localhost';
$dbname = 'sdm_polnest';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// ================== AMBIL DATA LOWONGAN ==================
$lowongan_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM lowongan_pekerjaan WHERE lowongan_id = ?");
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

    $gaji_min = isset($gaji[0]) ? (int)$gaji[0] : 0;
    $gaji_max = isset($gaji[1]) ? (int)$gaji[1] : 0;

    if ($gaji_min <= 0 || $gaji_max <= 0 || $gaji_min > $gaji_max) {
        $error = "Format rentang gaji tidak valid";
    } else {
        try {
            $stmt = $pdo->prepare("
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
                $gaji_min,
                $gaji_max,
                $_POST['formasi'],
                $_POST['deadline_lamaran'],
                $_POST['deskripsi_pekerjaan'],
                $_POST['tanggung_jawab'],
                $_POST['kualifikasi'],
                $lowongan_id
            ]);

            header('Location: index.php');
            exit;
        } catch(PDOException $e) {
            $error = "Gagal mengupdate data: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Lowongan</title>

    <!-- FONT POPPINS -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- BOOTSTRAP ICON -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f5f7fa;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: 100%;
        }

        .modal-overlay {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal {
            background: #fff;
            width: 100%;
            max-width: 650px;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }

        .modal-header {
            padding: 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 600;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 6px;
            display: block;
        }

        input, textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            font-size: 14px;
        }

        textarea {
            resize: vertical;
        }

        .modal-footer {
            padding: 18px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn {
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        .btn-cancel {
            background: #e5e7eb;
        }

        .btn-submit {
            background: #1e40af;
            color: white;
        }

        .alert {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>
</head>

<body>
<div class="app-container">

    <!-- SIDEBAR -->
    <?php include '../sidebar/sidebar.php'; ?>

    <main class="main-content">
        <div class="modal-overlay">
            <div class="modal">

                <div class="modal-header">
                    <h2><i class="bi bi-pencil-square"></i> Edit Lowongan Kerja</h2>
                </div>

                <div class="modal-body">

                    <?php if (!empty($error)): ?>
                        <div class="alert"><?= $error ?></div>
                    <?php endif; ?>

                    <form method="POST">

                        <div class="form-group">
                            <label>Posisi</label>
                            <input type="text" name="posisi" value="<?= htmlspecialchars($lowongan['posisi']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Formasi</label>
                            <input type="number" name="formasi" value="<?= $lowongan['formasi'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Rentang Gaji</label>
                            <input type="text" name="gaji_range"
                                   value="<?= number_format($lowongan['gaji_min'],0,',','.') ?> - <?= number_format($lowongan['gaji_max'],0,',','.') ?>"
                                   placeholder="2.000.000 - 3.500.000"
                                   required>
                        </div>

                        <div class="form-group">
                            <label>Deadline Lamaran</label>
                            <input type="date" name="deadline_lamaran" value="<?= $lowongan['deadline_lamaran'] ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Deskripsi Pekerjaan</label>
                            <textarea name="deskripsi_pekerjaan"><?= htmlspecialchars($lowongan['deskripsi_pekerjaan']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Tanggung Jawab</label>
                            <textarea name="tanggung_jawab"><?= htmlspecialchars($lowongan['tanggung_jawab']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Kualifikasi</label>
                            <textarea name="kualifikasi" required><?= htmlspecialchars($lowongan['kualifikasi']) ?></textarea>
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
</body>
</html>