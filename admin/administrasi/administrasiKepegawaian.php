<?php
// ===== UNTUK AUTHORIZATION =====
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

// Koneksi Database
require_once '../../config/database.php';

// HANDLE AJAX REQUESTS
if(isset($_GET['action']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'] ?? $_POST['action'];
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        // GET DATA BY LEVEL
        if($action == 'get_by_level' && isset($_GET['level'])) {
            $level = intval($_GET['level']);
            
            $query = "SELECT 
                        so.struktur_id,
                        so.pegawai_id,
                        so.jabatan_struktur,
                        so.level_struktur,
                        so.parent_id,
                        so.path_gambar,
                        p.nama_lengkap,
                        p.email,
                        p.jenis_pegawai,
                        sk.unit_kerja,
                        sk.jabatan
                    FROM struktur_organisasi so
                    INNER JOIN pegawai p ON so.pegawai_id = p.pegawai_id
                    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
                    WHERE so.level_struktur = :level
                    ORDER BY so.created_at ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':level', $level);
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = 'Data berhasil diambil';
            $response['data'] = $data;
        }
        
        // GET DATA BY ID
        elseif($action == 'get_by_id' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            
            $query = "SELECT 
                        so.*,
                        p.nama_lengkap,
                        p.email,
                        sk.unit_kerja
                    FROM struktur_organisasi so
                    INNER JOIN pegawai p ON so.pegawai_id = p.pegawai_id
                    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
                    WHERE so.struktur_id = :id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($data) {
                $response['success'] = true;
                $response['message'] = 'Data berhasil diambil';
                $response['data'] = $data;
            } else {
                $response['message'] = 'Data tidak ditemukan';
            }
        }
        
        // GET PEGAWAI LIST
elseif($action == 'get_pegawai_list') {
    $query = "SELECT 
                p.pegawai_id,
                p.nama_lengkap,
                p.email,
                p.jenis_pegawai,
                sk.jabatan,
                sk.unit_kerja,
                sk.jenis_kepegawaian,
                sk.tanggal_mulai_kerja,
                sk.status_aktif,
                sk.ptkp,
                sk.masa_kontrak_mulai,
                sk.masa_kontrak_selesai
            FROM pegawai p
            INNER JOIN (
                SELECT sk1.*
                FROM status_kepegawaian sk1
                INNER JOIN (
                    SELECT pegawai_id, MAX(created_at) as max_created
                    FROM status_kepegawaian
                    GROUP BY pegawai_id
                ) sk2 
                ON sk1.pegawai_id = sk2.pegawai_id 
                AND sk1.created_at = sk2.max_created
            ) sk ON p.pegawai_id = sk.pegawai_id
            
            -- FILTER: Status Aktif
            WHERE sk.status_aktif = 'aktif'
            
            -- FILTER: Field Wajib Umum
            AND sk.jabatan IS NOT NULL 
            AND sk.jabatan != ''
            AND sk.jenis_kepegawaian IS NOT NULL
            AND sk.unit_kerja IS NOT NULL
            AND sk.unit_kerja != ''
            AND sk.tanggal_mulai_kerja IS NOT NULL
            
            -- FILTER: PTKP 
            AND sk.ptkp IS NOT NULL
            AND sk.ptkp != ''
            
            -- FILTER: Khusus Pegawai Kontrak
            AND (
                LOWER(sk.jenis_kepegawaian) = 'tetap'
                OR (
                    LOWER(sk.jenis_kepegawaian) = 'kontrak'
                    AND sk.masa_kontrak_mulai IS NOT NULL
                    AND sk.masa_kontrak_selesai IS NOT NULL
                )
            )
            
            --  Pegawai yang sudah ada di struktur
            AND p.pegawai_id NOT IN (
                SELECT pegawai_id FROM struktur_organisasi
            )
            
            ORDER BY p.nama_lengkap ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['message'] = 'Data pegawai berhasil diambil';
    $response['data'] = $data;
}

        
        // GET PARENT LIST
        elseif($action == 'get_parent_list') {
            $query = "SELECT 
                        so.struktur_id,
                        so.pegawai_id,
                        p.nama_lengkap,
                        so.jabatan_struktur,
                        so.level_struktur
                    FROM struktur_organisasi so
                    INNER JOIN pegawai p ON so.pegawai_id = p.pegawai_id
                    WHERE so.level_struktur < 7
                    ORDER BY so.level_struktur ASC, p.nama_lengkap ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = 'Data parent berhasil diambil';
            $response['data'] = $data;
        }
        
        // ADD NEW ANGGOTA
        elseif($action == 'add' && $_SERVER['REQUEST_METHOD'] == 'POST') {

            $foto_path = null;
            
            if(isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                $max_size = 5 * 1024 * 1024; // 5MB dalam bytes
                
                if(!in_array($_FILES['foto']['type'], $allowed_types)) {
                    $response['message'] = 'Format foto harus JPG, JPEG, atau PNG';
                    echo json_encode($response);
                    exit;
                }
                
                if($_FILES['foto']['size'] > $max_size) {
                    $response['message'] = 'Ukuran foto maksimal 5MB';
                    echo json_encode($response);
                    exit;
                }
                
                // Upload foto
                $upload_dir = '../../uploads/struktur_organisasi/';
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $file_name = 'foto_' . $_POST['pegawai_id'] . '_' . time() . '.' . $file_ext;
                $foto_path = $upload_dir . $file_name;
                
                if(!move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                    $foto_path = null;
                }
            }
            
            // Ambil data dari POST
            $pegawai_id = $_POST['pegawai_id'] ?? null;
            $jabatan_struktur = $_POST['jabatan_struktur'] ?? null;
            $level_struktur = $_POST['level_struktur'] ?? null;
            $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
            
            if(!empty($pegawai_id) && !empty($jabatan_struktur) && !empty($level_struktur)) {
                // Cek apakah pegawai sudah terdaftar
                $check_query = "SELECT struktur_id FROM struktur_organisasi WHERE pegawai_id = :pegawai_id";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(':pegawai_id', $pegawai_id);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $response['message'] = 'Pegawai sudah terdaftar dalam struktur organisasi';
                } else {
                    $admin_id = $_SESSION['user_id'];
                    
                    $query = "INSERT INTO struktur_organisasi 
                             (pegawai_id, jabatan_struktur, level_struktur, parent_id, path_gambar, created_by)
                             VALUES 
                             (:pegawai_id, :jabatan_struktur, :level_struktur, :parent_id, :path_gambar, :created_by)";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':pegawai_id', $pegawai_id);
                    $stmt->bindParam(':jabatan_struktur', $jabatan_struktur);
                    $stmt->bindParam(':level_struktur', $level_struktur);
                    $stmt->bindParam(':parent_id', $parent_id);
                    $stmt->bindParam(':path_gambar', $foto_path);
                    $stmt->bindParam(':created_by', $admin_id);
                    
                    if($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Data anggota berhasil ditambahkan';
                        $response['data'] = ['id' => $conn->lastInsertId()];
                    } else {
                        $response['message'] = 'Gagal menambahkan data';
                    }
                }
            } else {
                $response['message'] = 'Data tidak lengkap';
            }
        }
        
        // UPDATE ANGGOTA
        elseif($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
            // Handle foto upload jika ada
            $foto_path = null;
            $update_foto = false;
            
            $struktur_id = $_POST['struktur_id'] ?? null;
            
            if(!$struktur_id) {
                $response['message'] = 'ID struktur tidak ditemukan';
                echo json_encode($response);
                exit;
            }
            
            $get_pegawai = "SELECT pegawai_id FROM struktur_organisasi WHERE struktur_id = :id";
            $get_stmt = $conn->prepare($get_pegawai);
            $get_stmt->bindParam(':id', $struktur_id);
            $get_stmt->execute();
            $current_data = $get_stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$current_data) {
                $response['message'] = 'Data tidak ditemukan';
                echo json_encode($response);
                exit;
            }
            
            $pegawai_id = $current_data['pegawai_id'];
            
            if(isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                $max_size = 5 * 1024 * 1024;
                
                if(!in_array($_FILES['foto']['type'], $allowed_types)) {
                    $response['message'] = 'Format foto harus JPG, JPEG, atau PNG';
                    echo json_encode($response);
                    exit;
                }
                
                if($_FILES['foto']['size'] > $max_size) {
                    $response['message'] = 'Ukuran foto maksimal 5MB';
                    echo json_encode($response);
                    exit;
                }
                
                // Upload foto
                $upload_dir = '../../uploads/struktur_organisasi/';
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $file_name = 'foto_' . $pegawai_id . '_' . time() . '.' . $file_ext;
                $foto_path = $upload_dir . $file_name;
                
                if(move_uploaded_file($_FILES['foto']['tmp_name'], $foto_path)) {
                    $update_foto = true;
                    
                    // Hapus foto lama jika ada
                    $old_query = "SELECT path_gambar FROM struktur_organisasi WHERE struktur_id = :id";
                    $old_stmt = $conn->prepare($old_query);
                    $old_stmt->bindParam(':id', $struktur_id);
                    $old_stmt->execute();
                    $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if($old_data && $old_data['path_gambar'] && file_exists($old_data['path_gambar'])) {
                        unlink($old_data['path_gambar']);
                    }
                }
            }
            
            $jabatan_struktur = $_POST['jabatan_struktur'] ?? null;
            $level_struktur = $_POST['level_struktur'] ?? null;
            $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
            
            if(!empty($struktur_id) && !empty($jabatan_struktur) && !empty($level_struktur)) {
                if($update_foto) {
                    $query = "UPDATE struktur_organisasi 
                             SET jabatan_struktur = :jabatan_struktur,
                                 level_struktur = :level_struktur,
                                 parent_id = :parent_id,
                                 path_gambar = :path_gambar,
                                 updated_at = CURRENT_TIMESTAMP
                             WHERE struktur_id = :struktur_id";
                } else {
                    $query = "UPDATE struktur_organisasi 
                             SET jabatan_struktur = :jabatan_struktur,
                                 level_struktur = :level_struktur,
                                 parent_id = :parent_id,
                                 updated_at = CURRENT_TIMESTAMP
                             WHERE struktur_id = :struktur_id";
                }
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':jabatan_struktur', $jabatan_struktur);
                $stmt->bindParam(':level_struktur', $level_struktur);
                $stmt->bindParam(':parent_id', $parent_id);
                $stmt->bindParam(':struktur_id', $struktur_id);
                
                if($update_foto) {
                    $stmt->bindParam(':path_gambar', $foto_path);
                }
                
                if($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Data anggota berhasil diperbarui';
                } else {
                    $response['message'] = 'Gagal memperbarui data';
                }
            } else {
                $response['message'] = 'Data tidak lengkap';
            }
        }
        
        // DELETE ANGGOTA
        elseif ($action == 'delete' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];

            try {
                // Cek apakah masih punya bawahan
                $check = $conn->prepare(
                    "SELECT 1 FROM struktur_organisasi WHERE parent_id = :id LIMIT 1"
                );
                $check->execute([':id' => $id]);

                if ($check->rowCount() > 0) {
                    $response['message'] =
                        'Tidak dapat menghapus data karena masih memiliki bawahan. Hapus bawahan terlebih dahulu.';
                } else {
                    // Ambil path foto untuk dihapus
                    $foto_query = "SELECT path_gambar FROM struktur_organisasi WHERE struktur_id = :id";
                    $foto_stmt = $conn->prepare($foto_query);
                    $foto_stmt->execute([':id' => $id]);
                    $foto_data = $foto_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $conn->prepare(
                        "DELETE FROM struktur_organisasi WHERE struktur_id = :id"
                    );

                    if ($stmt->execute([':id' => $id])) {
                        // Hapus foto jika ada
                        if($foto_data && $foto_data['path_gambar'] && file_exists($foto_data['path_gambar'])) {
                            unlink($foto_data['path_gambar']);
                        }
                        
                        $response['success'] = true;
                        $response['message'] = 'Data anggota berhasil dihapus';
                    } else {
                        $response['message'] = 'Gagal menghapus data';
                    }
                }
            } catch (Exception $e) {
                $response['message'] = $e->getMessage();
            }
        }

        // GET ALL PEGAWAI
        elseif($action == 'get_all_pegawai') {
            $query = "SELECT 
                        p.pegawai_id,
                        p.nama_lengkap,
                        p.email,
                        p.jenis_pegawai,
                        p.nip,
                        sk.jabatan,
                        sk.unit_kerja,
                        sk.status_aktif,
                        sk.jenis_kepegawaian,
                        sk.ptkp,
                        sk.masa_kontrak_mulai,
                        sk.masa_kontrak_selesai,
                        sk.tanggal_mulai_kerja,
                        DATEDIFF(sk.masa_kontrak_selesai, CURDATE()) as sisa_hari_kontrak
                    FROM pegawai p
                    LEFT JOIN (
                        SELECT sk1.*
                        FROM status_kepegawaian sk1
                        INNER JOIN (
                            SELECT pegawai_id, MAX(created_at) as max_created
                            FROM status_kepegawaian
                            GROUP BY pegawai_id
                        ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                            AND sk1.created_at = sk2.max_created
                    ) sk ON p.pegawai_id = sk.pegawai_id
                    ORDER BY p.nama_lengkap ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = 'Data pegawai berhasil diambil';
            $response['data'] = $data;
        }

        else {
            $response['message'] = 'Action tidak valid';
        }
        
    } catch(Exception $e) {
        $response['success'] = false;
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrasi Kepegawaian</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        .main-content {
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .page-header h1 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-header p  { font-size: 14px; opacity: 0.88; }

        .custom-tabs {
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 30px;
        }

        .custom-tabs .nav-link {
            color: #6b7280;
            font-weight: 500;
            padding: 12px 24px;
            border: none;
            border-bottom: 3px solid transparent;
            background: none;
            transition: all 0.3s;
        }

        .custom-tabs .nav-link:hover {
            color: #1f2937;
            border-bottom-color: #d1d5db;
        }

        .custom-tabs .nav-link.active {
            color: #1f2937;
            font-weight: 600;
            border-bottom-color: #2563eb;
            background: none;
        }

        .custom-tabs .nav-link i {
            margin-right: 8px;
        }

        /* Modal Backdrop*/
        .modal-backdrop {
            z-index: 9998 !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }

        /* Modal Container */
        .modal {
            z-index: 9999 !important;
        }

        /* Modal Dialog */
        .modal-dialog {
            z-index: 10000 !important;
        }

        /* Modal Content (form popup) */
        .modal-content {
            position: relative;
            z-index: 10001 !important;
        }

        /* Spesifik modalAnggota */
        #modalAnggota {
            z-index: 9999 !important;
        }

        #modalAnggota .modal-dialog {
            z-index: 10000 !important;
        }

        #modalAnggota .modal-content {
            z-index: 10001 !important;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: #f9fafb;
            border-radius: 12px 12px 0 0;
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header .modal-title {
            font-weight: 600;
            font-size: 20px;
        }

        .modal-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            font-size: 13px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Responsive */
        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px;
                z-index: 1; 
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
                padding-top: 90px;
                z-index: 1;
            }

            .page-header h1 {
                font-size: 24px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
                padding-top: 85px;
                z-index: 1;
            }
        }
    </style>
</head>
<body>
    <?php 

        $halaman_sekarang = basename($_SERVER['PHP_SELF']);
        include '../sidebar/sidebar.php';  
    ?>

    <div class="main-content">
        <!-- Header Halaman -->
        <div class="page-header">
            <h1><i class=""></i>Administrasi Kepegawaian</h1>
            <p>Kelola data informasi dan dokumen kepegawaian Anda</p>
        </div>

        <!-- Custom Tabs -->
        <ul class="nav custom-tabs" id="kepegawaianTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="data-pegawai-tab" data-bs-toggle="tab" data-bs-target="#data-pegawai" type="button" role="tab">
                    <i class="fas fa-users"></i>
                    Data Pegawai
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="struktur-tab" data-bs-toggle="tab" data-bs-target="#struktur-organisasi" type="button" role="tab">
                    <i class="fas fa-sitemap"></i>
                    Struktur Organisasi
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="kepegawaianTabContent">
            
            <!-- Tab Data Pegawai -->
            <div class="tab-pane fade show active" id="data-pegawai" role="tabpanel">
                <?php include __DIR__ . '/tab_data_pegawai.php'; ?>
            </div>

            <!-- Tab Struktur Organisasi -->
            <div class="tab-pane fade" id="struktur-organisasi" role="tabpanel">
                <?php include __DIR__ . '/tab_struktur_organisasi.php'; ?>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit Anggota - DIPINDAHKAN KE SINI AGAR TIDAK TERTIMPA -->
    <div class="modal fade" id="modalAnggota" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-user-plus me-2"></i>
                        <span id="modalTitleText">Tambah Anggota Baru</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formAnggota" enctype="multipart/form-data">
                        <input type="hidden" id="struktur_id" name="struktur_id">
                        <input type="hidden" id="mode" name="mode" value="add">
                        
                        <div class="mb-3">
                            <label for="pegawai_id" class="form-label">
                                <i class=""></i> Pilih Pegawai *
                            </label>
                            <select class="form-select" id="pegawai_id" name="pegawai_id" required>
                                <option value="">-- Pilih Pegawai --</option>
                            </select>
                            <small class="text-muted" id="pegawai-info-text">Hanya pegawai aktif dengan data kepegawaian lengkap yang ditampilkan</small>
                        </div>

                        <div class="mb-3">
                            <label for="jabatan_struktur" class="form-label">
                                <i class=""></i> Jabatan dalam Struktur *
                            </label>
                            <input type="text" class="form-control" id="jabatan_struktur" name="jabatan_struktur" 
                                   placeholder="Contoh: Direktur, Wakil Direktur, Kepala Prodi" required>
                        </div>

                        <div class="mb-3">
                            <label for="level_struktur" class="form-label">
                                <i class=""></i> Level Struktur *
                            </label>
                            <select class="form-select" id="level_struktur" name="level_struktur" required>
                                <option value="">-- Pilih Level --</option>
                                <option value="1">Level 1 - Direktur</option>
                                <option value="2">Level 2 - Wakil Direktur</option>
                                <option value="3">Level 3 - Kaprodi</option>
                                <option value="4">Level 4 - Kepala Unit</option>
                                <option value="5">Level 5 - Laboran</option>
                                <option value="6">Level 6 - Tendik</option>
                                <option value="7">Level 7 - Staff</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="foto" class="form-label">
                                <i class=""></i> Foto (Opsional)
                            </label>
                            
                            <!-- Preview Foto yang Sudah Ada -->
                            <div id="current-foto-preview" style="display: none; margin-bottom: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                                    <img id="preview-img" src="" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                    <div style="flex: 1;">
                                        <div style="font-size: 13px; color: #374151; font-weight: 500;">Foto saat ini</div>
                                        <div style="font-size: 12px; color: #6b7280;">Upload foto baru untuk menggantinya</div>
                                    </div>
                                </div>
                            </div>
                            
                            <input type="file" class="form-control" id="foto" name="foto" accept="image/jpeg,image/jpg,image/png">
                            <small class="text-muted">Format: JPG, JPEG, PNG. Maksimal 5MB. Jika tidak diupload akan menggunakan avatar nama.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-custom" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button type="button" class="btn btn-primary-custom" onclick="simpanAnggota()">
                        <i class="fas fa-save me-1"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // AUTO ACTIVATE TAB BASED ON URL HASH
        document.addEventListener('DOMContentLoaded', function() {
            // Check if URL has hash #struktur-organisasi
            if (window.location.hash === '#struktur-organisasi') {
                const strukturTab = document.querySelector('#struktur-tab');
                if (strukturTab) {
                    const tab = new bootstrap.Tab(strukturTab);
                    tab.show();
                }
            }

            // Cek jika ada parameter success dari redirect edit
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('success') === '1') {
                // Aktifkan tab Data Pegawai
                const dataPegawaiTab = new bootstrap.Tab(document.getElementById('data-pegawai-tab'));
                dataPegawaiTab.show();
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: urlParams.get('message') || 'Data berhasil diperbarui',
                    confirmButtonColor: '#2563eb',
                    timer: 3000,
                    timerProgressBar: true
                });
                
                // Bersihkan URL parameter
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</body>
</html>