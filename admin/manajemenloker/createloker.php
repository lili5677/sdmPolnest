<?php
// Koneksi database
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

// Proses form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO lowongan_pekerjaan 
            (posisi, gaji_min, gaji_max, formasi, deadline_lamaran, deskripsi_pekerjaan, tanggung_jawab, kualifikasi, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif', 1)
        ");
        
        $stmt->execute([
            $_POST['posisi'],
            $gaji_min,
            $gaji_max,
            $_POST['formasi'],
            $_POST['deadline_lamaran'],
            $_POST['deskripsi_pekerjaan'],
            $_POST['tanggung_jawab'],
            $_POST['kualifikasi']
        ]);

$gaji_range = $_POST['gaji_range'];

// Hapus semua karakter selain angka dan tanda minus
$gaji_range = preg_replace('/[^0-9\-]/', '', $gaji_range);

// Pecah berdasarkan tanda -
$gaji = explode('-', $gaji_range);

$gaji_min = isset($gaji[0]) ? (int) $gaji[0] : null;
$gaji_max = isset($gaji[1]) ? (int) $gaji[1] : null;

// Validasi
if (!$gaji_min || !$gaji_max) {
    throw new Exception('Format rentang gaji tidak valid');
}

if ($gaji_min > $gaji_max) {
    throw new Exception('Gaji minimum tidak boleh lebih besar dari gaji maksimum');
}

        header('Location: index.php');
        exit;
    } catch(PDOException $e) {
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            flex: 1;
            width: calc(100% - 280px);
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: color 0.2s;
        }

        .back-button:hover {
            color: #3b82f6;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 900px;
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .form-header p {
            font-size: 14px;
            color: #64748b;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea {
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
            width: 100%;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #3b82f6;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        textarea.large {
            min-height: 150px;
        }

        /* Button Group */
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-cancel {
            background-color: #f1f5f9;
            color: #475569;
        }

        .btn-cancel:hover {
            background-color: #e2e8f0;
        }

        .btn-submit {
            background-color: #3b82f6;
            color: white;
        }

        .btn-submit:hover {
            background-color: #2563eb;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar akan di-include dari file terpisah -->
        <?php // include 'sidebar.php'; ?>

        <main class="main-content">
            <!-- Back Button -->
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </a>

            <!-- Form Card -->
            <div class="form-card">
                <div class="form-header">
                    <h1>Manajemen Lowongan Kerja</h1>
                    <p>Tambah lowongan pekerjaan</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-grid">
                        <!-- Posisi -->
                        <div class="form-group">
                            <label for="posisi">Posisi</label>
                            <input type="text" id="posisi" name="posisi" placeholder="Nama posisi" required>
                        </div>

                        <!-- Gaji -->
                        <div class="form-group">
                            <label for="gaji_range">Rentang Gaji</label>
                            <input type="text"
                                id="gaji_range"
                                name="gaji_range"
                                placeholder="Contoh: 2.000.000 - 3.500.000"
                                required>
                        </div>


                        <!-- Formasi -->
                        <div class="form-group">
                            <label for="formasi">Formasi</label>
                            <input type="number" id="formasi" name="formasi" placeholder="Jumlah formasi" required>
                        </div>

                        <!-- Deadline Lamaran -->
                        <div class="form-group">
                            <label for="deadline_lamaran">Deadline Lamaran</label>
                            <input type="date" id="deadline_lamaran" name="deadline_lamaran" required>
                        </div>

                        <!-- Deskripsi Pekerjaan -->
                        <div class="form-group full-width">
                            <label for="deskripsi_pekerjaan">Deskripsi Pekerjaan</label>
                            <textarea id="deskripsi_pekerjaan" name="deskripsi_pekerjaan" placeholder="Deskripsikan pekerjaan"></textarea>
                        </div>

                        <!-- Tanggung Jawab Utama -->
                        <div class="form-group full-width">
                            <label for="tanggung_jawab">Tanggung Jawab Utama</label>
                            <textarea id="tanggung_jawab" name="tanggung_jawab" placeholder="Tanggung jawab utama"></textarea>
                        </div>

                        <!-- Kualifikasi -->
                        <div class="form-group full-width">
                            <label for="kualifikasi">Kualifikasi</label>
                            <textarea id="kualifikasi" name="kualifikasi" class="large" placeholder="Persyaratan kualifikasi" required></textarea>
                        </div>
                    </div>

                    <!-- Button Group -->
                    <div class="button-group">
                        <a href="index.php" class="btn btn-cancel">Batal</a>
                        <button type="submit" class="btn btn-submit">Simpan</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>