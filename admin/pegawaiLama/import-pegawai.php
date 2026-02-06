<?php
session_start();
require_once '../../config/database.php';

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Fungsi untuk generate token unik
function generateUniqueToken($conn, $length = 16) {
    do {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        $check_query = "SELECT COUNT(*) FROM users WHERE token = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->execute([$token]);
        $exists = $stmt->fetchColumn() > 0;
        
    } while ($exists);
    
    return $token;
}

// Fungsi untuk generate NIK dummy 16 digit yang unik
function generateUniqueNIK($conn) {
    do {
        // Generate NIK 16 digit
        $nik = (string)random_int(1, 9); // Digit pertama 1-9
        
        for ($i = 1; $i < 16; $i++) {
            $nik .= (string)random_int(0, 9);
        }
        
        // Cek apakah NIK sudah ada
        $check_query = "SELECT COUNT(*) FROM pegawai WHERE nik = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->execute([$nik]);
        $exists = $stmt->fetchColumn() > 0;
        
    } while ($exists);
    
    return $nik;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Terjadi kesalahan saat upload file";
        header("Location: manajemen-pegawai.php");
        exit();
    }
    
    // Validasi ekstensi file
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        $_SESSION['error'] = "File harus berformat CSV";
        header("Location: manajemen-pegawai.php");
        exit();
    }
    
    // Baca file CSV
    $csv_file = fopen($file['tmp_name'], 'r');
    
    if ($csv_file === false) {
        $_SESSION['error'] = "Gagal membaca file CSV";
        header("Location: manajemen-pegawai.php");
        exit();
    }
    
    // Skip BOM (Byte Order Mark) jika ada
    $bom = fread($csv_file, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($csv_file);
    }
    
    // Deteksi delimiter otomatis
    $first_line = fgets($csv_file);
    rewind($csv_file);
    
    // Skip BOM lagi setelah rewind jika ada
    if ($bom === "\xEF\xBB\xBF") {
        fread($csv_file, 3);
    }
    
    $delimiter = ',';
    $delimiters = [',', ';', "\t", '|'];
    foreach ($delimiters as $del) {
        if (substr_count($first_line, $del) >= 2) {
            $delimiter = $del;
            break;
        }
    }
    
    $success_count = 0;
    $error_count = 0;
    $error_messages = [];
    $row_number = 0;
    
    try {
        $conn->beginTransaction();
        
        // Skip header row
        $header = fgetcsv($csv_file, 0, $delimiter);
        
        // Validasi header
        if (!$header || count($header) < 3) {
            throw new Exception("Format CSV tidak valid. Header harus memiliki minimal 3 kolom.");
        }
        
        while (($data = fgetcsv($csv_file, 0, $delimiter)) !== false) {
            $row_number++;
            
            // Skip baris kosong
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Validasi jumlah kolom minimal
            if (count($data) < 3) {
                $error_messages[] = "Baris $row_number: Data tidak lengkap (minimal 3 kolom)";
                $error_count++;
                continue;
            }
            
            // ===== AMBIL DATA WAJIB =====
            $nama_lengkap = preg_replace('/\s+/', ' ', trim($data[0]));
            $nama_lengkap = preg_replace('/[\x00-\x1F\x7F]/u', '', $nama_lengkap);

            $email = trim($data[1]);
            $email = preg_replace('/\s+/', '', $email);
            $email = strtolower($email);
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $email = preg_replace('/[\x00-\x1F\x7F]/u', '', $email);

            $jenis_pegawai = trim($data[2]);
            $jenis_pegawai = preg_replace('/\s+/', '', $jenis_pegawai);
            $jenis_pegawai = strtolower($jenis_pegawai);
            $jenis_pegawai = preg_replace('/[\x00-\x1F\x7F]/u', '', $jenis_pegawai);

            // ===== AMBIL DATA STATUS KEPEGAWAIAN (OPSIONAL) =====
            $jabatan = isset($data[3]) ? trim($data[3]) : null;
            $jenis_kepegawaian = isset($data[4]) ? strtolower(trim($data[4])) : 'tetap';
            $status_aktif = isset($data[5]) ? strtolower(trim($data[5])) : 'aktif';
            $unit_kerja = isset($data[6]) ? trim($data[6]) : null;
            $tanggal_mulai_kerja = isset($data[7]) ? trim($data[7]) : null;
            $masa_kontrak_mulai = isset($data[8]) ? trim($data[8]) : null;
            $masa_kontrak_selesai = isset($data[9]) ? trim($data[9]) : null;

            // ===== TAMBAHAN: AMBIL NIK DARI CSV (OPSIONAL) =====
            $nik = isset($data[10]) ? trim($data[10]) : null;

            // Validasi NIK jika diisi
            if (!empty($nik)) {
                // Pastikan NIK hanya berisi angka
                if (!preg_match('/^\d+$/', $nik)) {
                    $error_messages[] = "Baris $row_number: NIK harus berisi angka saja";
                    $error_count++;
                    continue;
                }
                
                // Pastikan NIK tepat 16 digit
                if (strlen($nik) !== 16) {
                    $error_messages[] = "Baris $row_number: NIK harus 16 digit (saat ini: " . strlen($nik) . " digit)";
                    $error_count++;
                    continue;
                }
                
                // Cek apakah NIK sudah ada di database
                $check_nik = "SELECT COUNT(*) FROM pegawai WHERE nik = ?";
                $stmt_check_nik = $conn->prepare($check_nik);
                $stmt_check_nik->execute([$nik]);
                
                if ($stmt_check_nik->fetchColumn() > 0) {
                    $error_messages[] = "Baris $row_number: NIK sudah terdaftar ($nik)";
                    $error_count++;
                    continue;
                }
            }
            
            // Generate token unik
            $token = generateUniqueToken($conn);
            
            // Generate password ter-hash
            $default_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            
            // Tentukan user_type
            $user_type = ($jenis_pegawai === 'dosen') ? 'dosen' : 'pegawai';
            
            try {
                // Insert ke tabel users
                $query_user = "INSERT INTO users (email, password, user_type, token, password_changed, is_active) 
                              VALUES (?, ?, ?, ?, 0, 1)";
                $stmt_user = $conn->prepare($query_user);
                $stmt_user->execute([$email, $default_password, $user_type, $token]);
                $user_id = $conn->lastInsertId();
                
                // Insert ke tabel pegawai
                $query_pegawai = "INSERT INTO pegawai (user_id, nik, nama_lengkap, email, jenis_pegawai, is_pegawai_lama) 
                                 VALUES (?, ?, ?, ?, ?, 1)";
                $stmt_pegawai = $conn->prepare($query_pegawai);
                $stmt_pegawai->execute([$user_id, $nik, $nama_lengkap, $email, $jenis_pegawai]);
                
                $pegawai_id = $conn->lastInsertId();
                
                // Insert ke tabel status_kepegawaian
                // PERBAIKAN: Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk handle unique constraint
                if (!empty($jabatan) || !empty($unit_kerja) || !empty($tanggal_mulai_kerja) || 
                    !empty($masa_kontrak_mulai) || !empty($masa_kontrak_selesai)) {
                    
                    $query_status = "INSERT INTO status_kepegawaian 
                                    (pegawai_id, jabatan, jenis_kepegawaian, masa_kontrak_mulai, 
                                     masa_kontrak_selesai, status_aktif, unit_kerja, tanggal_mulai_kerja, created_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                    jabatan = VALUES(jabatan),
                                    jenis_kepegawaian = VALUES(jenis_kepegawaian),
                                    masa_kontrak_mulai = VALUES(masa_kontrak_mulai),
                                    masa_kontrak_selesai = VALUES(masa_kontrak_selesai),
                                    status_aktif = VALUES(status_aktif),
                                    unit_kerja = VALUES(unit_kerja),
                                    tanggal_mulai_kerja = VALUES(tanggal_mulai_kerja),
                                    created_by = VALUES(created_by),
                                    updated_at = CURRENT_TIMESTAMP";
                    
                    $stmt_status = $conn->prepare($query_status);
                    $stmt_status->execute([
                        $pegawai_id,
                        $jabatan,
                        $jenis_kepegawaian,
                        !empty($masa_kontrak_mulai) ? $masa_kontrak_mulai : null,
                        !empty($masa_kontrak_selesai) ? $masa_kontrak_selesai : null,
                        $status_aktif,
                        $unit_kerja,
                        !empty($tanggal_mulai_kerja) ? $tanggal_mulai_kerja : null,
                        $_SESSION['user_id']
                    ]);
                }
                
                $success_count++;
                
            } catch (PDOException $e) {
                $error_messages[] = "Baris $row_number: Gagal insert data - " . $e->getMessage();
                $error_count++;
                continue;
            }
        }
        
        fclose($csv_file);
        
        // Commit transaction
        if ($success_count > 0) {
            $conn->commit();
            
            $message = "Berhasil mengimport $success_count pegawai";
            if ($error_count > 0) {
                $message .= " ($error_count gagal)";
            }
            $_SESSION['success'] = $message;
            
            if (!empty($error_messages)) {
                $_SESSION['import_errors'] = array_slice($error_messages, 0, 20);
                if (count($error_messages) > 20) {
                    $_SESSION['import_errors'][] = "... dan " . (count($error_messages) - 20) . " error lainnya";
                }
            }
        } else {
            $conn->rollBack();
            $_SESSION['error'] = "Tidak ada data yang berhasil diimport.";
            
            if (!empty($error_messages)) {
                $_SESSION['import_errors'] = array_slice($error_messages, 0, 10);
            }
        }
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        if (isset($csv_file) && is_resource($csv_file)) {
            fclose($csv_file);
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = $e->getMessage();
        if (isset($csv_file) && is_resource($csv_file)) {
            fclose($csv_file);
        }
    }
    
    header("Location: manajemen-pegawai.php");
    exit();
}

// Jika bukan POST request, redirect ke halaman utama
header("Location: manajemen-pegawai.php");
exit();
?>