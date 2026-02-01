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
                        sk.unit_kerja
                    FROM pegawai p
                    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
                    WHERE sk.status_aktif = 'aktif'
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
                    WHERE so.level_struktur < 3
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
            $data = json_decode(file_get_contents("php://input"), true);
            
            if(!empty($data['pegawai_id']) && !empty($data['jabatan_struktur']) && !empty($data['level_struktur'])) {
                // Cek apakah pegawai sudah terdaftar
                $check_query = "SELECT struktur_id FROM struktur_organisasi WHERE pegawai_id = :pegawai_id";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(':pegawai_id', $data['pegawai_id']);
                $check_stmt->execute();
                
                if($check_stmt->rowCount() > 0) {
                    $response['message'] = 'Pegawai sudah terdaftar dalam struktur organisasi';
                } else {
                    $admin_id = $_SESSION['user_id'];
                    $parent_id = !empty($data['parent_id']) ? $data['parent_id'] : null;
                    
                    $query = "INSERT INTO struktur_organisasi 
                             (pegawai_id, jabatan_struktur, level_struktur, parent_id, created_by)
                             VALUES 
                             (:pegawai_id, :jabatan_struktur, :level_struktur, :parent_id, :created_by)";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':pegawai_id', $data['pegawai_id']);
                    $stmt->bindParam(':jabatan_struktur', $data['jabatan_struktur']);
                    $stmt->bindParam(':level_struktur', $data['level_struktur']);
                    $stmt->bindParam(':parent_id', $parent_id);
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
            $data = json_decode(file_get_contents("php://input"), true);
            
            if(!empty($data['struktur_id']) && !empty($data['jabatan_struktur']) && !empty($data['level_struktur'])) {
                $parent_id = !empty($data['parent_id']) ? $data['parent_id'] : null;
                
                $query = "UPDATE struktur_organisasi 
                         SET jabatan_struktur = :jabatan_struktur,
                             level_struktur = :level_struktur,
                             parent_id = :parent_id,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE struktur_id = :struktur_id";
                
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':jabatan_struktur', $data['jabatan_struktur']);
                $stmt->bindParam(':level_struktur', $data['level_struktur']);
                $stmt->bindParam(':parent_id', $parent_id);
                $stmt->bindParam(':struktur_id', $data['struktur_id']);
                
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
                    $stmt = $conn->prepare(
                        "DELETE FROM struktur_organisasi WHERE struktur_id = :id"
                    );

                    if ($stmt->execute([':id' => $id])) {
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

        // GET ALL PEGAWAI - DIPERBAIKI UNTUK MENGAMBIL DATA TERBARU
        elseif($action == 'get_all_pegawai') {
            // PERBAIKAN: Gunakan subquery untuk mengambil status_kepegawaian terbaru
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
                        sk.masa_kontrak_mulai,
                        sk.masa_kontrak_selesai,
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
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 15px;
            margin: 0;
        }

        /* Custom Tabs */
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

        /* ===== PERBAIKAN Z-INDEX UNTUK MODAL ===== */
        /* PENTING: CSS ini harus ada di file utama agar modal muncul dengan benar */
        
        /* Modal Backdrop (background blur) */
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

        /* Spesifik untuk modalAnggota */
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
        // Set halaman saat ini untuk sidebar active state
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
                    <form id="formAnggota">
                        <input type="hidden" id="struktur_id" name="struktur_id">
                        <input type="hidden" id="mode" name="mode" value="add">
                        
                        <div class="mb-3">
                            <label for="pegawai_id" class="form-label">
                                <i class="fas fa-user me-1"></i> Pilih Pegawai *
                            </label>
                            <select class="form-select" id="pegawai_id" name="pegawai_id" required>
                                <option value="">-- Pilih Pegawai --</option>
                            </select>
                            <small class="text-muted">Hanya pegawai aktif yang belum terdaftar yang ditampilkan</small>
                        </div>

                        <div class="mb-3">
                            <label for="jabatan_struktur" class="form-label">
                                <i class="fas fa-briefcase me-1"></i> Jabatan dalam Struktur *
                            </label>
                            <input type="text" class="form-control" id="jabatan_struktur" name="jabatan_struktur" 
                                   placeholder="Contoh: Direktur, Wakil Direktur, Kepala Prodi" required>
                        </div>

                        <div class="mb-3">
                            <label for="level_struktur" class="form-label">
                                <i class="fas fa-layer-group me-1"></i> Level Struktur *
                            </label>
                            <select class="form-select" id="level_struktur" name="level_struktur" required>
                                <option value="">-- Pilih Level --</option>
                                <option value="1">Level 1 - Pimpinan Tertinggi</option>
                                <option value="2">Level 2 - Kepala Unit</option>
                                <option value="3">Level 3 - Anggota</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">
                                <i class="fas fa-sitemap me-1"></i> Atasan Langsung (Opsional)
                            </label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- Tidak ada atasan --</option>
                            </select>
                            <small class="text-muted">Pilih atasan langsung jika ada</small>
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