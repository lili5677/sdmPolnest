<?php

session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pegawai_id'])) {
    header("Location: ../../auth/login_pegawai.php");
    exit;
}

// Ambil pegawai_id
if ($_SESSION['user_type'] === 'admin' && isset($_GET['pegawai_id'])) {
    $pegawai_id = (int)$_GET['pegawai_id'];
} else {
    // Pegawai biasa hanya bisa lihat data sendiri
    $pegawai_id = $_SESSION['pegawai_id'];
}

// Security check - pegawai biasa tidak boleh akses data orang lain
if ($_SESSION['user_type'] !== 'admin' && isset($_GET['pegawai_id']) && (int)$_GET['pegawai_id'] !== $_SESSION['pegawai_id']) {
    header("Location: administrasi.php");
    exit;
}
// Query Data Pegawai dengan Status Kepegawaian
$stmt = $conn->prepare("
    SELECT 
        p.pegawai_id,
        p.nik,
        p.nip,
        p.nidn,
        p.prodi,
        p.nama_lengkap,
        p.jenis_pegawai,
        p.is_dosen_nest,
        p.tempat_lahir,
        p.tanggal_lahir,
        p.jenis_kelamin,
        p.email,
        p.no_telepon,
        p.alamat_domisili,
        p.alamat_ktp,
        sk.jabatan, 
        sk.jenis_kepegawaian, 
        sk.status_aktif, 
        sk.unit_kerja, 
        sk.tanggal_mulai_kerja,
        sk.masa_kontrak_mulai,
        sk.masa_kontrak_selesai,
        sk.ptkp
    FROM pegawai p
    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
    WHERE p.pegawai_id = ?
");
$stmt->execute([$pegawai_id]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    die("Pegawai tidak ditemukan. Pegawai ID: " . $pegawai_id);
}

// Tentukan jenis pegawai untuk menampilkan dokumen yang sesuai
$is_dosen = ($pegawai['jenis_pegawai'] === 'dosen' || $pegawai['is_dosen_nest'] == 1);

// Query Dokumen Pegawai
$stmt_dokumen = $conn->prepare("
    SELECT * FROM dokumen_pegawai 
    WHERE pegawai_id = ?
    ORDER BY created_at DESC
");
$stmt_dokumen->execute([$pegawai_id]);
$dokumen = $stmt_dokumen->fetchAll(PDO::FETCH_ASSOC);

// Hitung Sisa Masa Kontrak
$sisa_kontrak_text = '-';
$badge_kontrak = 'badge-secondary';

if (!empty($pegawai['masa_kontrak_mulai']) && !empty($pegawai['masa_kontrak_selesai'])) {
    $tanggal_mulai = new DateTime($pegawai['masa_kontrak_mulai']);
    $tanggal_selesai = new DateTime($pegawai['masa_kontrak_selesai']);
    $sekarang = new DateTime();
    
    // Set semua waktu ke midnight untuk perhitungan yang akurat
    $tanggal_selesai->setTime(0, 0, 0);
    $sekarang->setTime(0, 0, 0);
    
    // Hitung selisih dari sekarang ke tanggal selesai
    $interval = $sekarang->diff($tanggal_selesai);
    
    // Jika kontrak sudah habis
    if ($sekarang > $tanggal_selesai) {
        $sisa_kontrak_text = 'Kontrak Habis';
        $badge_kontrak = 'badge-danger';
    } 
    // Jika kontrak belum dimulai
    elseif ($sekarang < $tanggal_mulai) {
        $sisa_kontrak_text = 'Belum Dimulai';
        $badge_kontrak = 'badge-warning';
    }
    // Kontrak masih berjalan
    else {
        $tahun = $interval->y;
        $bulan = $interval->m;
        $hari = $interval->d;
        
        $parts = [];
        if ($tahun > 0) $parts[] = $tahun . ' tahun';
        if ($bulan > 0) $parts[] = $bulan . ' bulan';
        if ($hari > 0) $parts[] = $hari . ' hari';
        
        $sisa_kontrak_text = !empty($parts) ? implode(', ', $parts) : 'Hari ini terakhir';
        
        $total_bulan = ($tahun * 12) + $bulan;
        if ($total_bulan <= 1) {
            $badge_kontrak = 'badge-danger';
        } elseif ($total_bulan <= 3) {
            $badge_kontrak = 'badge-warning';
        } else {
            $badge_kontrak = 'badge-success';
        }
    }
} elseif (!empty($pegawai['masa_kontrak_mulai'])) {
    $sisa_kontrak_text = 'Tetap';
    $badge_kontrak = 'badge-info';
}

// Mapping jenis dokumen berdasarkan jenis pegawai
if ($is_dosen) {
    // Dosen: 8 dokumen (termasuk SKCK dan Surat Bebas Napza)
    $jenis_dokumen_label = [
        'cv' => 'Curriculum Vitae (CV)',
        'ktp' => 'KTP (Kartu Tanda Penduduk)',
        'npwp' => 'NPWP (Nomor Pokok Wajib Pajak)',
        'ijazah' => 'Ijazah/Sertifikat Pendidikan',
        'surat_sehat' => 'Surat Keterangan Sehat',
        'surat_kerja_sebelumnya' => 'Surat Keterangan Kerja Sebelumnya',
        'skck' => 'SKCK (Surat Keterangan Catatan Kepolisian)',
        'surat_bebas_napza' => 'Surat Keterangan Bebas Napza'
    ];
    $page_title = 'Administrasi Kepegawaian - Dosen';
} else {
    // Staff/Tendik: 6 dokumen (tanpa SKCK dan Surat Bebas Napza)
    $jenis_dokumen_label = [
        'cv' => 'Curriculum Vitae (CV)',
        'ktp' => 'KTP (Kartu Tanda Penduduk)',
        'npwp' => 'NPWP (Nomor Pokok Wajib Pajak)',
        'ijazah' => 'Ijazah/Sertifikat Pendidikan',
        'surat_sehat' => 'Surat Keterangan Sehat',
        'surat_kerja_sebelumnya' => 'Surat Keterangan Kerja Sebelumnya'
    ];
    $page_title = 'Administrasi Kepegawaian - Pegawai';
}

$message = '';
$message_type = '';

// Update Data Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_pegawai') {
        try {
            // Helper function untuk handle empty string
            function emptyToNull($value) {
                if ($value === null || $value === '') {
                    return null;
                }
                $trimmed = trim($value);
                return ($trimmed === '') ? null : $trimmed;
            }
            
            // Process data - convert empty to NULL
            $nik = emptyToNull($_POST['nik'] ?? null);
            $nip = emptyToNull($_POST['nip'] ?? null);
            $nidn = emptyToNull($_POST['nidn'] ?? null);
            $prodi = emptyToNull($_POST['prodi'] ?? null);
            $nama_lengkap = emptyToNull($_POST['nama_lengkap'] ?? null);
            $jenis_pegawai = emptyToNull($_POST['jenis_pegawai'] ?? null);
            $tempat_lahir = emptyToNull($_POST['tempat_lahir'] ?? null);
            $tanggal_lahir = emptyToNull($_POST['tanggal_lahir'] ?? null);
            $jenis_kelamin = emptyToNull($_POST['jenis_kelamin'] ?? null);
            $email = emptyToNull($_POST['email'] ?? null);
            $no_telepon = emptyToNull($_POST['no_telepon'] ?? null);
            $alamat_domisili = emptyToNull($_POST['alamat_domisili'] ?? null);
            $alamat_ktp = emptyToNull($_POST['alamat_ktp'] ?? null);
            
            //VALIDASI DATA WAJIB
            $errors = [];
            
            if (empty($nama_lengkap)) {
                $errors[] = 'Nama lengkap wajib diisi!';
            }
            
            if (empty($email)) {
                $errors[] = 'Email wajib diisi!';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid!';
            }
            
            if (empty($jenis_pegawai)) {
                $errors[] = 'Jenis pegawai wajib diisi!';
            } elseif (!in_array($jenis_pegawai, ['dosen', 'staff', 'tendik'])) {
                $errors[] = 'Jenis pegawai tidak valid!';
            }
            
            //VALIDASI OPSIONAL (JIKA DIISI)
            
            // Validasi NIK jika diisi
            if (!empty($nik)) {
                if (!preg_match('/^\d+$/', $nik)) {
                    $errors[] = 'NIK harus berisi angka saja!';
                } elseif (strlen($nik) !== 16) {
                    $errors[] = 'NIK harus 16 digit!';
                } else {
                    // Cek duplikat NIK (kecuali NIK sendiri)
                    $check_nik = $conn->prepare("SELECT COUNT(*) FROM pegawai WHERE nik = ? AND pegawai_id != ?");
                    $check_nik->execute([$nik, $pegawai_id]);
                    if ($check_nik->fetchColumn() > 0) {
                        $errors[] = 'NIK sudah terdaftar!';
                    }
                }
            }
            
            // Validasi NIP jika diisi
            if (!empty($nip)) {
                if (!preg_match('/^\d+$/', $nip)) {
                    $errors[] = 'NIP harus berisi angka saja!';
                } elseif (strlen($nip) !== 18) {
                    $errors[] = 'NIP harus 18 digit!';
                }
            }
            
            // Validasi NIDN jika diisi (TAMBAHAN)
            if (!empty($nidn)) {
                if (!preg_match('/^\d+$/', $nidn)) {
                    $errors[] = 'NIDN harus berisi angka saja!';
                } else {
                    // Cek duplikat NIDN (kecuali NIDN sendiri)
                    $check_nidn = $conn->prepare("SELECT COUNT(*) FROM pegawai WHERE nidn = ? AND pegawai_id != ?");
                    $check_nidn->execute([$nidn, $pegawai_id]);
                    if ($check_nidn->fetchColumn() > 0) {
                        $errors[] = 'NIDN sudah terdaftar!';
                    }
                }
            }
            
            // Validasi jenis kelamin jika diisi
            if (!empty($jenis_kelamin) && !in_array($jenis_kelamin, ['L', 'P'])) {
                $errors[] = 'Jenis kelamin tidak valid!';
            }
            
            // Cek duplikat email (kecuali email sendiri)
            if (!empty($email) && $email !== $pegawai['email']) {
                $check_email = $conn->prepare("SELECT COUNT(*) FROM pegawai WHERE email = ? AND pegawai_id != ?");
                $check_email->execute([$email, $pegawai_id]);
                if ($check_email->fetchColumn() > 0) {
                    $errors[] = 'Email sudah terdaftar!';
                }
            }
            
            // ADA ERROR
            if (!empty($errors)) {
                $message = implode('<br>', $errors);
                $message_type = 'danger';
            } 
            // JIKA TIDAK ADA ERROR
            else {
                $sql = "UPDATE pegawai SET 
                        nik = ?,
                        nip = ?,
                        nidn = ?,
                        prodi = ?,
                        nama_lengkap = ?,
                        jenis_pegawai = ?,
                        tempat_lahir = ?,
                        tanggal_lahir = ?,
                        jenis_kelamin = ?,
                        email = ?,
                        no_telepon = ?,
                        alamat_domisili = ?,
                        alamat_ktp = ?
                        WHERE pegawai_id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(1, $nik, $nik === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(2, $nip, $nip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(3, $nidn, $nidn === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(4, $prodi, $prodi === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(5, $nama_lengkap, PDO::PARAM_STR);
                $stmt->bindValue(6, $jenis_pegawai, PDO::PARAM_STR);
                $stmt->bindValue(7, $tempat_lahir, $tempat_lahir === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(8, $tanggal_lahir, $tanggal_lahir === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(9, $jenis_kelamin, $jenis_kelamin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(10, $email, PDO::PARAM_STR);
                $stmt->bindValue(11, $no_telepon, $no_telepon === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(12, $alamat_domisili, $alamat_domisili === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(13, $alamat_ktp, $alamat_ktp === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                $stmt->bindValue(14, $pegawai_id, PDO::PARAM_INT);
                
                $stmt->execute();
                
                // Update email di tabel users juga jika berubah
                if ($email !== $pegawai['email']) {
                    $update_user = $conn->prepare("UPDATE users SET email = ? WHERE user_id = (SELECT user_id FROM pegawai WHERE pegawai_id = ?)");
                    $update_user->execute([$email, $pegawai_id]);
                }
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?pegawai_id=" . $pegawai_id . "&success=1");
                exit;
            }
        } catch (Exception $e) {
            $message = 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
    
    // Edit Dokumen Handler
    if (isset($_POST['action']) && $_POST['action'] === 'edit_dokumen') {
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_dokumen'];
            $jenis_dokumen = $_POST['jenis_dokumen'];
            $dokumen_pegawai_id = $_POST['dokumen_pegawai_id'];
            
            // Validasi tipe file
            $allowed_type = 'application/pdf';
            $file_type = mime_content_type($file['tmp_name']);
            
            if ($file_type !== $allowed_type) {
                $message = 'Hanya file PDF yang diperbolehkan!';
                $message_type = 'danger';
            }
            elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = 'Ukuran file maksimal 5 MB!';
                $message_type = 'danger';
            }
            else {
                // Get old document
                $stmt_old = $conn->prepare("SELECT * FROM dokumen_pegawai WHERE dokumen_pegawai_id = ?");
                $stmt_old->execute([$dokumen_pegawai_id]);
                $old_doc = $stmt_old->fetch(PDO::FETCH_ASSOC);
                
                if ($old_doc) {
                    // Generate filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = $pegawai_id . '_' . $jenis_dokumen . '_' . time() . '.' . $extension;
                    $upload_dir = '../../uploads/dokumen/';
                    
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $destination = $upload_dir . $filename;
                    $path_file = 'uploads/dokumen/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Delete old file
                        if (file_exists($upload_dir . $old_doc['nama_file'])) {
                            unlink($upload_dir . $old_doc['nama_file']);
                        }
                        
                        // Update database
                        $stmt_update = $conn->prepare("UPDATE dokumen_pegawai SET nama_file = ?, path_file = ?, ukuran_file = ?, updated_at = NOW() WHERE dokumen_pegawai_id = ?");
                        $stmt_update->execute([$filename, $path_file, $file['size'], $dokumen_pegawai_id]);
                        
                        header("Location: " . $_SERVER['PHP_SELF'] . "?pegawai_id=" . $pegawai_id . "&edit_success=1");
                        exit;
                    }
                }
            }
        }
    }

    // Upload Dokumen Handler
    if ($_POST['action'] === 'upload_dokumen') {
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_dokumen'];
            $jenis_dokumen = $_POST['jenis_dokumen'];
            
            // Validasi tipe file (hanya PDF)
            $allowed_type = 'application/pdf';
            $file_type = mime_content_type($file['tmp_name']);
            
            if ($file_type !== $allowed_type) {
                $message = 'Hanya file PDF yang diperbolehkan!';
                $message_type = 'danger';
            }
            // Validasi ukuran file (maksimal 5 MB)
            elseif ($file['size'] > 5 * 1024 * 1024) {
                $message = 'Ukuran file maksimal 5 MB!';
                $message_type = 'danger';
            }
            else {
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $pegawai_id . '_' . $jenis_dokumen . '_' . time() . '.' . $extension;
                $upload_dir = '../../uploads/dokumen/';
                
                // Create directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $filename;
                $path_file = 'uploads/dokumen/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old document if exists
                    $stmt_check = $conn->prepare("SELECT * FROM dokumen_pegawai WHERE pegawai_id = ? AND jenis_dokumen = ?");
                    $stmt_check->execute([$pegawai_id, $jenis_dokumen]);
                    $old_doc = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($old_doc && file_exists($upload_dir . $old_doc['nama_file'])) {
                        unlink($upload_dir . $old_doc['nama_file']);
                        // Update existing record
                        $stmt_update = $conn->prepare("UPDATE dokumen_pegawai SET nama_file = ?, path_file = ?, ukuran_file = ?, updated_at = NOW() WHERE dokumen_pegawai_id = ?");
                        $stmt_update->execute([$filename, $path_file, $file['size'], $old_doc['dokumen_pegawai_id']]);
                    } else {
                        // Insert new record
                        $stmt_insert = $conn->prepare("INSERT INTO dokumen_pegawai (pegawai_id, jenis_dokumen, nama_file, ukuran_file) VALUES (?, ?, ?, ?)");
                        $stmt_insert->execute([$pegawai_id, $jenis_dokumen, $filename, $file['size']]);
                    }
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?pegawai_id=" . $pegawai_id . "&upload_success=1");
                    exit;
                } else {
                    $message = 'Gagal mengupload file!';
                    $message_type = 'danger';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SDM POLNEST</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #f1f5f9;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1e293b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #fef3e2;
            color: var(--text-dark);
        }

        .navbar-custom {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--text-dark) !important;
        }

        .header-section {
            background-color: transparent;
            padding: 2rem 0 1rem 0;
            margin-bottom: 1.5rem;
        }

        .header-section h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-dark);
        }

        .header-section p {
            margin: 0.25rem 0 0 0;
            color: #64748b;
            font-size: 0.875rem;
        }

        .container {
            max-width: 800px;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            background-color: #ffffff;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-dark);
            border-radius: 12px 12px 0 0 !important;
        }

        .card-header .bi-info-circle {
            opacity: 0.6;
            transition: opacity 0.2s ease;
        }

        .card-header .bi-info-circle:hover {
            opacity: 1;
        }

        .card-body {
            padding: 1.25rem;
        }

        .info-grid {
            display: grid;
            gap: 0.75rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }

        .info-label {
            font-weight: 500;
            color: #64748b;
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--text-dark);
            font-size: 0.875rem;
            font-weight: 500;
            text-align: right;
        }

        /* Badge dengan warna solid */
        .badge {
            padding: 0.35rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .badge-success {
            background-color: #10b981;
            color: #ffffff;
        }

        .badge-warning {
            background-color: #f59e0b;
            color: #ffffff;
        }

        .badge-info {
            background-color: #3b82f6;
            color: #ffffff;
        }

        .badge-danger {
            background-color: #ef4444;
            color: #ffffff;
        }

        .badge-secondary {
            background-color: #6b7280;
            color: #ffffff;
        }

        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.6rem 0.875rem;
            font-size: 0.875rem;
            background-color: #f8fafc;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background-color: #ffffff;
        }

        .form-control:disabled, .form-select:disabled {
            background-color: #f8fafc;
            color: #64748b;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        /* Button Styling */
        .btn {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--success-color);
            border: none;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-outline-secondary {
            border: 1px solid #d1d5db;
            color: #6b7280;
            background-color: transparent;
        }

        .btn-outline-secondary:hover {
            background-color: #f9fafb;
            border-color: #9ca3af;
        }

        .btn-sm {
            padding: 0.375rem 0.875rem;
            font-size: 0.8125rem;
        }

        /* Document Item Styling */
        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background-color: #f8fafc;
            border-radius: 8px;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }

        .document-item:hover {
            background-color: #f1f5f9;
        }

        .document-left {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background-color: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .document-info {
            flex: 1;
        }

        .document-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-dark);
            margin-bottom: 0.125rem;
        }

        .document-meta {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .document-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }

        .btn-outline-primary {
            border-color: #3b82f6;
            color: #3b82f6;
        }

        .btn-outline-primary:hover {
            background-color: #3b82f6;
            color: white;
        }

        .btn-outline-danger {
            border-color: #ef4444;
            color: #ef4444;
        }

        .btn-outline-danger:hover {
            background-color: #ef4444;
            color: white;
        }

        .btn-outline-warning {
            border-color: #f59e0b;
            color: #f59e0b;
        }

        .btn-outline-warning:hover {
            background-color: #f59e0b;
            color: white;
        }

        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: #e5e7eb;
            margin-bottom: 1rem;
        }

        .progress-bar {
            background-color: #10b981;
            border-radius: 10px;
        }

        .progress-text {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Alert Styling */
        .alert {
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
        }

        /* Upload Button for Empty Document */
        .btn-upload {
            background-color: #ffffff;
            border: 1px dashed #d1d5db;
            color: #6b7280;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            transition: all 0.2s;
        }

        .btn-upload:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background-color: #eff6ff;
        }

        .badge-not-uploaded {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Custom Notification */
        .custom-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            min-width: 350px;
            max-width: 450px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 1.25rem;
            z-index: 9999;
            animation: slideInRight 0.4s ease-out;
            border-left: 4px solid #ef4444;
        }

        .custom-notification.success {
            border-left-color: #10b981;
        }

        .custom-notification-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .custom-notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .custom-notification.error .custom-notification-icon {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .custom-notification.success .custom-notification-icon {
            background-color: #d1fae5;
            color: #059669;
        }

        .custom-notification-title {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-dark);
            flex: 1;
        }

        .custom-notification-close {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1.25rem;
            line-height: 1;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .custom-notification-close:hover {
            background-color: #f1f5f9;
            color: #475569;
        }

        .custom-notification-body {
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .custom-notification-body ul {
            margin: 0.5rem 0 0 0;
            padding-left: 1.25rem;
        }

        .custom-notification-body li {
            margin-bottom: 0.375rem;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .custom-notification.hiding {
            animation: slideOutRight 0.3s ease-in forwards;
        }

        @media (max-width: 768px) {
            .document-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .document-actions {
                margin-top: 0.75rem;
                width: 100%;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-value {
                text-align: left;
                margin-top: 0.25rem;
            }

            .custom-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
                max-width: none;
            }

            @keyframes slideInRight {
                from {
                    transform: translateY(-100px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            @keyframes slideOutRight {
                from {
                    transform: translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateY(-100px);
                    opacity: 0;
                }
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../partials/navbar.php'; ?>

    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <h1>Administrasi Kepegawaian</h1>
            <p>Kelola data identitas dan dokumen kepegawaian secara lengkap</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil!</strong> Data pegawai telah diperbarui.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['upload_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil!</strong> Dokumen berhasil diupload.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['edit_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                Dokumen berhasil diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $message_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

       <!-- Status Kepegawaian -->
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span>Status Kepegawaian</span>
                <?php if ($_SESSION['user_type'] !== 'admin'): ?>
                    <i class="bi bi-info-circle text-muted ms-2" 
                       style="cursor: help; font-size: 0.9rem;" 
                       data-bs-toggle="tooltip" 
                       data-bs-placement="right" 
                       title="Hanya admin yang dapat mengubah Status Kepegawaian, hubungi admin jika ingin melengkapi data"></i>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <!-- Jabatan -->
                    <div class="info-item">
                        <span class="info-label">Jabatan</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pegawai['jabatan'] ?? '-') ?>
                        </span>
                    </div>
                    
                    <!-- Jenis Kepegawaian -->
                    <div class="info-item">
                        <span class="info-label">Jenis Kepegawaian</span>
                        <span class="info-value">
                            <span class="badge badge-info"><?= ucfirst($pegawai['jenis_kepegawaian'] ?? 'Staff') ?></span>
                        </span>
                    </div>
                    
                    <!-- PTKP (Status Pajak) -->
                    <div class="info-item">
                        <span class="info-label">PTKP (Status Pajak)</span>
                        <span class="info-value">
                            <?= htmlspecialchars($pegawai['ptkp'] ?? '-') ?>
                        </span>
                    </div>
                    
                    <!-- Masa Kontrak (Teks biasa tanpa badge) -->
                    <div class="info-item">
                        <span class="info-label">Sisa Kontrak</span>
                        <span class="info-value">
                            <?= $sisa_kontrak_text ?>
                        </span>
                    </div>
                    
                    <!-- Status Kepegawaian -->
                    <div class="info-item">
                        <span class="info-label">Status Kepegawaian</span>
                        <span class="info-value">
                            <span class="badge <?= ($pegawai['status_aktif'] ?? 'aktif') === 'aktif' ? 'badge-success' : 'badge-warning' ?>">
                                <?= ucfirst($pegawai['status_aktif'] ?? 'Aktif') ?>
                            </span>
                        </span>
                    </div>
                    
                    <!-- Unit Kerja -->
                    <div class="info-item">
                        <span class="info-label">Unit Kerja</span>
                        <span class="info-value"><?= htmlspecialchars($pegawai['unit_kerja'] ?? '-') ?></span>
                    </div>
                    
                    <!-- Tanggal Mulai Kerja -->
                    <div class="info-item">
                        <span class="info-label">Tanggal Mulai Kerja</span>
                        <span class="info-value">
                            <?= $pegawai['tanggal_mulai_kerja'] ? date('d F Y', strtotime($pegawai['tanggal_mulai_kerja'])) : '-' ?>
                        </span>
                    </div>
                    
                    <!-- Tanggal Selesai Kontrak -->
                    <div class="info-item">
                        <span class="info-label">Tanggal Selesai Kontrak</span>
                        <span class="info-value">
                            <?php 
                            if (!empty($pegawai['masa_kontrak_selesai'])) {
                                echo date('d F Y', strtotime($pegawai['masa_kontrak_selesai']));
                            } elseif (!empty($pegawai['masa_kontrak_mulai'])) {
                                echo 'Pegawai Tetap';
                            } else {
                                echo '-';
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Identitas Pegawai -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Data Identitas Pegawai</span>
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" id="btnEdit" onclick="toggleEdit()">
                        <i class="bi bi-pencil me-1"></i>Edit Data
                    </button>
                    <button class="btn btn-success btn-sm" id="btnSave" style="display: none;" type="button" onclick="submitFormPegawai()">
                        <i class="bi bi-check2 me-1"></i>Simpan Perubahan
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="formPegawai">
                    <input type="hidden" name="action" value="update_pegawai">
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">NIK Pegawai</label>
                            <input type="text" name="nik" class="form-control editable" 
                                   value="<?= !empty($pegawai['nik']) ? htmlspecialchars($pegawai['nik']) : '' ?>" 
                                   placeholder="-"
                                   maxlength="16" 
                                   disabled>
                            <small class="text-muted">Format: 16 digit angka</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">NIP (Nomor Induk Pegawai)</label>
                            <input type="text" name="nip" class="form-control editable" 
                                   value="<?= !empty($pegawai['nip']) ? htmlspecialchars($pegawai['nip']) : '' ?>" 
                                   placeholder="-"
                                   maxlength="18" 
                                   disabled>
                            <small class="text-muted">Format: 18 digit angka</small>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control editable" 
                                   value="<?= !empty($pegawai['nama_lengkap']) ? htmlspecialchars($pegawai['nama_lengkap']) : '' ?>" 
                                   placeholder="-"
                                   disabled>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Jenis Pegawai</label>
                            <select name="jenis_pegawai" class="form-select editable" id="jenis_pegawai_select" disabled onchange="toggleDosenFields()">
                                <option value="">-</option>
                                <option value="dosen" <?= ($pegawai['jenis_pegawai'] ?? '') === 'dosen' ? 'selected' : '' ?>>Dosen</option>
                                <option value="staff" <?= ($pegawai['jenis_pegawai'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="tendik" <?= ($pegawai['jenis_pegawai'] ?? '') === 'tendik' ? 'selected' : '' ?>>Tendik</option>
                            </select>
                        </div>

                        <!-- Field khusus DOSEN (NIDN & Prodi) -->
                        <div id="dosenFields" style="display: <?= ($pegawai['jenis_pegawai'] ?? '') === 'dosen' ? 'block' : 'none' ?>;">
                            <div class="col-12 mb-3">
                                <label class="form-label">NIDN (Nomor Induk Dosen Nasional)</label>
                                <input type="text" name="nidn" class="form-control editable" 
                                    value="<?= !empty($pegawai['nidn']) ? htmlspecialchars($pegawai['nidn']) : '' ?>" 
                                    placeholder="-"
                                    disabled>
                                <small class="text-muted">Khusus untuk dosen</small>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Program Studi</label>
                                <input type="text" name="prodi" class="form-control editable" 
                                    value="<?= !empty($pegawai['prodi']) ? htmlspecialchars($pegawai['prodi']) : '' ?>" 
                                    placeholder="-"
                                    disabled>
                                <small class="text-muted">Contoh: Teknik Informatika, Teknik Sipil, dll</small>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="form-control editable" 
                                   value="<?= !empty($pegawai['tempat_lahir']) ? htmlspecialchars($pegawai['tempat_lahir']) : '' ?>" 
                                   placeholder="-"
                                   disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control editable" 
                                   value="<?= !empty($pegawai['tanggal_lahir']) ? htmlspecialchars($pegawai['tanggal_lahir']) : '' ?>" 
                                   disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select editable" disabled>
                                <option value="">-</option>
                                <option value="L" <?= ($pegawai['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= ($pegawai['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control editable" 
                                   value="<?= !empty($pegawai['email']) ? htmlspecialchars($pegawai['email']) : '' ?>" 
                                   placeholder="-"
                                   disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="no_telepon" class="form-control editable" 
                                   value="<?= !empty($pegawai['no_telepon']) ? htmlspecialchars($pegawai['no_telepon']) : '' ?>" 
                                   placeholder="-"
                                   disabled>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Alamat KTP</label>
                            <textarea name="alamat_ktp" class="form-control editable" rows="2" placeholder="-" disabled><?= !empty($pegawai['alamat_ktp']) ? htmlspecialchars($pegawai['alamat_ktp']) : '' ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Alamat Domisili</label>
                            <textarea name="alamat_domisili" class="form-control editable" rows="2" placeholder="-" disabled><?= !empty($pegawai['alamat_domisili']) ? htmlspecialchars($pegawai['alamat_domisili']) : '' ?></textarea>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Kelengkapan Dokumen -->
        <div class="card">
            <div class="card-header">
                Kelengkapan Dokumen
                <small class="text-muted">(Opsional)</small>
            </div>
            <div class="card-body">
                <!-- Progress Bar -->
                <?php 
                $total_dokumen = count($jenis_dokumen_label);
                $dokumen_array = [];
                foreach ($dokumen as $d) {
                    $dokumen_array[$d['jenis_dokumen']] = $d;
                }
                $dokumen_lengkap = count($dokumen_array);
                $persentase = $total_dokumen > 0 ? ($dokumen_lengkap / $total_dokumen) * 100 : 0;
                ?>
                <div class="progress-text">
                    <strong><?= $dokumen_lengkap ?> dari <?= $total_dokumen ?></strong> dokumen telah diunggah
                </div>
                <div class="progress">
                    <div class="progress-bar" role="progressbar" 
                         style="width: <?= $persentase ?>%;" 
                         aria-valuenow="<?= $persentase ?>" aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>

                <!-- Document List -->
                <?php foreach ($jenis_dokumen_label as $jenis => $label): ?>
                    <?php 
                    $doc = isset($dokumen_array[$jenis]) ? $dokumen_array[$jenis] : null;
                    ?>
                    <div class="document-item">
                        <div class="document-left">
                            <div class="document-icon">
                                <i class="bi bi-file-earmark-pdf-fill text-danger" style="font-size: 1.25rem;"></i>
                            </div>
                            <div class="document-info">
                                <div class="document-name"><?= $label ?></div>
                                <div class="document-meta">
                                    <?php if ($doc): ?>
                                        <span class="badge badge-success me-1">
                                            <i class="bi bi-check-circle me-1"></i>Terkirim
                                        </span>
                                        <span><?= round($doc['ukuran_file'] / 1024, 2) ?> KB</span>
                                        <span class="text-muted ms-1">• <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-not-uploaded">
                                            <i class="bi bi-exclamation-circle me-1"></i>Belum diunggah
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="document-actions">
                            <?php if ($doc): ?>
                                <!-- Tombol Lihat -->
                                <a href="../../uploads/dokumen/<?= $doc['nama_file'] ?>" target="_blank" 
                                class="btn btn-outline-primary btn-icon" title="Lihat Dokumen">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <!-- Tombol Edit -->
                                <button class="btn btn-outline-warning btn-icon" title="Edit/Ganti Dokumen" 
                                        onclick="openEditModal('<?= $jenis ?>', '<?= $label ?>', <?= $doc['dokumen_pegawai_id'] ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            <?php else: ?>
                                <!-- Tombol Upload -->
                                <button class="btn btn-upload" onclick="openUploadModal('<?= $jenis ?>', '<?= $label ?>')">
                                    <i class="bi bi-upload me-1"></i>Upload
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_dokumen">
                        <input type="hidden" name="jenis_dokumen" id="jenis_dokumen">
                        
                        <div class="mb-3">
                            <label class="form-label">Jenis Dokumen</label>
                            <input type="text" class="form-control" id="label_dokumen" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">File Dokumen (PDF, Max 5MB)</label>
                            <input type="file" class="form-control" name="file_dokumen" 
                                   accept=".pdf" required onchange="validateFile(this)">
                            <small class="text-muted">Format: PDF | Ukuran maksimal: 5 MB</small>
                        </div>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <small>Pastikan file yang diupload adalah PDF dengan ukuran maksimal 5 MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Upload Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Document Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Edit Dokumen: <span id="edit_label"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_dokumen">
                        <input type="hidden" name="jenis_dokumen" id="edit_jenis_dokumen">
                        <input type="hidden" name="dokumen_pegawai_id" id="edit_dokumen_pegawai_id">
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Upload dokumen baru untuk mengganti dokumen yang lama.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Pilih File Baru (PDF, Max 5MB)
                            </label>
                            <input type="file" class="form-control" name="file_dokumen" accept=".pdf" required>
                            <small class="text-muted">Format: PDF, Ukuran maksimal: 5 MB</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-pencil me-1"></i>Update Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include '../partials/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Custom Notification Function
        function showNotification(title, messages, type = 'error') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notif => notif.remove());

            // Create notification element
            const notification = document.createElement('div');
            notification.className = `custom-notification ${type}`;
            
            // Icon based on type
            const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
            
            // Build message body
            let messageBody = '';
            if (Array.isArray(messages) && messages.length > 0) {
                messageBody = '<ul>';
                messages.forEach(msg => {
                    messageBody += `<li>${msg}</li>`;
                });
                messageBody += '</ul>';
            } else if (typeof messages === 'string') {
                messageBody = `<p style="margin: 0;">${messages}</p>`;
            }
            
            notification.innerHTML = `
                <div class="custom-notification-header">
                    <div class="custom-notification-icon">
                        <i class="bi ${iconClass}"></i>
                    </div>
                    <div class="custom-notification-title">${title}</div>
                    <button class="custom-notification-close" onclick="closeNotification(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                <div class="custom-notification-body">
                    ${messageBody}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                closeNotification(notification.querySelector('.custom-notification-close'));
            }, 5000);
        }

        function closeNotification(button) {
            const notification = button.closest('.custom-notification');
            if (notification) {
                notification.classList.add('hiding');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }

        // Initialize Bootstrap Tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Toggle Edit Mode
        function toggleEdit() {
            const editables = document.querySelectorAll('.editable');
            const btnEdit = document.getElementById('btnEdit');
            const btnSave = document.getElementById('btnSave');
            
            editables.forEach(el => {
                el.disabled = !el.disabled;
                if (!el.disabled) {
                    el.style.backgroundColor = '#ffffff';
                } else {
                    el.style.backgroundColor = '#f8fafc';
                }
            });
            
            if (btnEdit.style.display === 'none') {
                btnEdit.style.display = 'inline-block';
                btnSave.style.display = 'none';
            } else {
                btnEdit.style.display = 'none';
                btnSave.style.display = 'inline-block';
            }
        }

        // Toggle Dosen Fields
        function toggleDosenFields() {
            const jenisPegawai = document.getElementById('jenis_pegawai_select').value;
            const dosenFields = document.getElementById('dosenFields');
            
            if (jenisPegawai === 'dosen') {
                dosenFields.style.display = 'block';
            } else {
                dosenFields.style.display = 'none';
            }
        }

        // Submit Form dengan Validasi
        function submitFormPegawai() {
            const form = document.getElementById('formPegawai');
            const nikInput = document.querySelector('input[name="nik"]');
            const nipInput = document.querySelector('input[name="nip"]');
            const nidnInput = document.querySelector('input[name="nidn"]');
            const namaLengkapInput = document.querySelector('input[name="nama_lengkap"]');
            const emailInput = document.querySelector('input[name="email"]');
            
            const nik = nikInput.value.trim();
            const nip = nipInput.value.trim();
            const nidn = nidnInput ? nidnInput.value.trim() : '';
            const namaLengkap = namaLengkapInput.value.trim();
            const email = emailInput.value.trim();
            
            let errors = [];
            
            // Reset semua border merah
            [nikInput, nipInput, nidnInput, namaLengkapInput, emailInput].forEach(input => {
                if (input) {
                    input.style.borderColor = '';
                }
            });
            
            // Validasi NIK jika diisi
            if (nik !== '' && nik.length !== 16) {
                errors.push('NIK harus 16 digit angka');
                nikInput.style.borderColor = '#ef4444';
            }
            
            // Validasi NIP jika diisi
            if (nip !== '' && nip.length !== 18) {
                errors.push('NIP harus 18 digit angka');
                nipInput.style.borderColor = '#ef4444';
            }
            
            // Validasi NIDN jika diisi
            if (nidn !== '' && !/^\d+$/.test(nidn)) {
                errors.push('NIDN harus berisi angka saja');
                if (nidnInput) nidnInput.style.borderColor = '#ef4444';
            }
            
            // Validasi Nama Lengkap (wajib)
            if (namaLengkap === '') {
                errors.push('Nama lengkap wajib diisi');
                namaLengkapInput.style.borderColor = '#ef4444';
            }
            
            // Validasi Email (wajib)
            if (email === '') {
                errors.push('Email wajib diisi');
                emailInput.style.borderColor = '#ef4444';
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.push('Format email tidak valid');
                emailInput.style.borderColor = '#ef4444';
            }
            
            // Jika ada error, tampilkan notifikasi
            if (errors.length > 0) {
                showNotification('Validasi Gagal', errors, 'error');
                return false;
            }
            
            // Jika valid, submit form
            form.submit();
        }

        // Validasi NIK - hanya angka dan maksimal 16 digit
        const nikInput = document.querySelector('input[name="nik"]');
        nikInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 16);
            this.style.borderColor = ''; // Reset border color saat user mengetik
        });

        // Validasi NIP - hanya angka dan maksimal 18 digit
        const nipInput = document.querySelector('input[name="nip"]');
        nipInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 18);
            this.style.borderColor = ''; // Reset border color saat user mengetik
        });

        // Validasi NIDN - hanya angka
        const nidnInput = document.querySelector('input[name="nidn"]');
        if (nidnInput) {
            nidnInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                this.style.borderColor = ''; // Reset border color saat user mengetik
            });
        }

        // Reset border color untuk Nama Lengkap saat user mengetik
        const namaLengkapInput = document.querySelector('input[name="nama_lengkap"]');
        namaLengkapInput.addEventListener('input', function() {
            this.style.borderColor = '';
        });

        // Reset border color untuk Email saat user mengetik
        const emailInput = document.querySelector('input[name="email"]');
        emailInput.addEventListener('input', function() {
            this.style.borderColor = '';
        });

        // Open Upload Modal
        function openUploadModal(jenis, label) {
            document.getElementById('jenis_dokumen').value = jenis;
            document.getElementById('label_dokumen').value = label;
            const uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
            uploadModal.show();
        }

        // Validate File Size and Type
        function validateFile(input) {
            const file = input.files[0];
            if (file) {
                // Check file type
                if (file.type !== 'application/pdf') {
                    showNotification('File Tidak Valid', ['Hanya file PDF yang diperbolehkan'], 'error');
                    input.value = '';
                    return false;
                }
                
                // Check file size
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    showNotification('Ukuran File Terlalu Besar', ['Ukuran file maksimal 5 MB'], 'error');
                    input.value = '';
                    return false;
                }
            }
        }

        // Open Edit Modal
        function openEditModal(jenis, label, dokumenPegawaiId) {
            document.getElementById('edit_jenis_dokumen').value = jenis;
            document.getElementById('edit_label').textContent = label;
            document.getElementById('edit_dokumen_pegawai_id').value = dokumenPegawaiId;
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
                                        
        // Auto dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>