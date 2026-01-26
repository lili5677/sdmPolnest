<?php
/**
 * Halaman: Administrasi Kepegawaian - Struktur Organisasi
 * File: admin/administrasi_kepegawaian.php
 * Versi: Tanpa API (All-in-One)
 */

// ===== UNTUK AUTHORIZATION =====
// session_start();
// if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
//     header('Location: ../login.php');
//     exit();
// }
// ================================================

// Koneksi Database
$host = "localhost";
$db_name = "sdm_polnest - revisi";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("set names utf8");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

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
                    $admin_id = 1; // TEMPORARY - akan diubah saat implementasi login
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

    $response = [
        'success' => false,
        'message' => ''
    ];

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

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

        // HANDLE AJAX REQUESTS - DATA PEGAWAI

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
                        sk.masa_kontrak_mulai,
                        sk.masa_kontrak_selesai,
                        DATEDIFF(sk.masa_kontrak_selesai, CURDATE()) as sisa_hari_kontrak
                    FROM pegawai p
                    LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
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
    <title>Administrasi Kepegawaian - Struktur Organisasi</title>
    
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
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 16px;
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

        /* Card Styling */
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .card-header-custom {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-title-custom {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .card-title-custom i {
            margin-right: 10px;
            color: #2563eb;
        }

        .card-description {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
        }

        /* Level Tabs */
        .level-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .level-tab {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: white;
            color: #6b7280;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .level-tab:hover {
            border-color: #2563eb;
            color: #2563eb;
        }

        .level-tab.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* Member Card */
        .member-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s;
            background: white;
        }

        .member-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #d1d5db;
        }

        .avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
            font-weight: 600;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
            font-size: 16px;
        }

        .member-position {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .member-department {
            color: #9ca3af;
            font-size: 13px;
        }

        .member-department i {
            margin-right: 5px;
        }

        .member-actions {
            display: flex;
            gap: 8px;
        }

        /* Buttons */
        .btn-custom {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-custom i {
            font-size: 16px;
        }

        .btn-primary-custom {
            background: #1f2937;
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            background: #374151;
            color: white;
        }

        .btn-outline-custom {
            background: white;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline-custom:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .btn-edit {
            background: none;
            border: none;
            color: #2563eb;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 18px;
            transition: all 0.3s;
            border-radius: 6px;
        }

        .btn-edit:hover {
            background: #eff6ff;
        }

        .btn-delete {
            background: none;
            border: none;
            color: #ef4444;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 18px;
            transition: all 0.3s;
            border-radius: 6px;
        }

        .btn-delete:hover {
            background: #fef2f2;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #6b7280;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #9ca3af;
            font-size: 14px;
        }

        /* Loading */
        .spinner-container {
            text-align: center;
            padding: 40px;
        }

        .spinner-border {
            color: #2563eb;
        }

        /* Modal */
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
            padding: 10px 15px;
            font-size: 14px;
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

            .card-header-custom {
                flex-direction: column;
            }

            .member-card {
                flex-direction: column;
                text-align: center;
            }

            .member-actions {
                justify-content: center;
            }

            .level-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 5px;
            }

            .level-tab {
                white-space: nowrap;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
                padding-top: 85px;
                z-index: 1;
            }

            .content-card {
                padding: 20px;
            }
        }


        /* STYLING UNTUK DATA PEGAWAI */
        /* Input Group Custom */
        .input-group-text {
            border-right: 0;
        }

        .input-group .form-control:focus {
            border-left: 1px solid #2563eb;
        }

        /* Table Responsive */
        .table-responsive {
            border-radius: 8px;
            overflow-x: auto;
            overflow-y: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        .table-pegawai {
            margin-bottom: 0;
            font-size: 13px;
            width: 100%;
            table-layout: fixed;
        }

        .table-pegawai thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table-pegawai thead th {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            font-weight: 700;
            color: white !important;
            border: none;
            padding: 14px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
            vertical-align: middle;
        }

        .table-pegawai tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-pegawai tbody tr {
            transition: all 0.2s;
        }

        .table-pegawai tbody tr:hover {
            background: #f9fafb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        /* Badge Styling */
        .badge-custom {
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            white-space: nowrap;
            display: inline-block;
        }

        .badge-kontrak {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .badge-tetap {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .badge-aktif {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .badge-tidak-aktif {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 4px;
            justify-content: center;
        }

        .btn-action {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-view {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        .btn-edit-table {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
        }

        .btn-edit-table:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .btn-delete-table {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
        }

        .btn-delete-table:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        /* Nama Pegawai Bold */
        .nama-pegawai {
            font-weight: 600;
            color: #1f2937;
            display: block;
            margin-bottom: 0;
        }

        /* No urut column */
        .no-column {
            width: 50px;
            text-align: center;
            font-weight: 600;
            color: #6b7280;
        }

        /* Aksi column */
        .aksi-column {
            width: 150px;
            text-align: center;
        }

        /* Sisa Kontrak Warning */
        .sisa-kontrak-warning {
            color: #dc2626 !important;
            font-weight: 700 !important;
            background: #fee2e2;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .sisa-kontrak-normal {
            color: #374151;
        }

        /* Empty State Enhancement */
        .empty-state-pegawai {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }

        .empty-state-pegawai i {
            font-size: 80px;
            color: #d1d5db;
            margin-bottom: 25px;
            opacity: 0.7;
        }

        .empty-state-pegawai h4 {
            color: #374151;
            font-size: 20px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .empty-state-pegawai p {
            color: #9ca3af;
            font-size: 15px;
            margin-bottom: 25px;
        }

        .empty-state-pegawai .btn {
            margin-top: 10px;
        }
        

        /* Table Responsive Mobile */
        @media (max-width: 768px) {
            .table-pegawai {
                font-size: 11px;
            }
            
            .table-pegawai thead th,
            .table-pegawai tbody td {
                padding: 8px 6px;
            }
            
            .table-responsive {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-pegawai {
                min-width: 1200px;
            }

            .btn-action {
                padding: 5px 8px;
                font-size: 11px;
            }
            
            .badge-custom {
                font-size: 9px;
                padding: 3px 7px;
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
            <h1><i class="fas fa-users-cog me-2"></i>Administrasi Kepegawaian</h1>
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
                <div class="content-card">
                    <div class="card-header-custom">
                        <div>
                            <h3 class="card-title-custom">
                                <i class="fas fa-address-book"></i>
                                Data Pegawai
                            </h3>
                            <p class="card-description">Kelola informasi lengkap pegawai Anda</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline-custom" onclick="refreshDataPegawai()">
                                <i class="fas fa-sync-alt"></i>
                                Refresh
                            </button>
                            <button class="btn btn-primary-custom" onclick="tambahPegawai()">
                                <i class="fas fa-plus"></i>
                                Tambah Pegawai
                            </button>
                        </div>
                    </div>
                    
                    <!-- Search & Filter -->
                    <div class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" id="searchPegawai" 
                                        placeholder="Cari nama pegawai..." 
                                        onkeyup="filterPegawai()">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="filterJenisPegawai" onchange="filterPegawai()">
                                    <option value="">Semua Jenis Pegawai</option>
                                    <option value="dosen">Dosen</option>
                                    <option value="staff">Staff</option>
                                    <option value="tendik">Tendik</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="filterJenisKepegawaian" onchange="filterPegawai()">
                                    <option value="">Semua Pegawai</option>
                                    <option value="tetap">Tetap</option>
                                    <option value="kontrak">Kontrak</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" id="filterStatusAktif" onchange="filterPegawai()">
                                    <option value="">Semua Status</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="tidak_aktif">Tidak Aktif</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="sortBy" onchange="sortPegawai()">
                                    <option value="nama_asc">Nama (A-Z)</option>
                                    <option value="nama_desc">Nama (Z-A)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Pegawai Container -->
                    <div id="data-pegawai-container">
                        <div class="spinner-container">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Memuat data pegawai...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Struktur Organisasi -->
            <div class="tab-pane fade" id="struktur-organisasi" role="tabpanel">
                <div class="content-card">
                    <div class="card-header-custom">
                        <div>
                            <h3 class="card-title-custom">
                                <i class="fas fa-sitemap"></i>
                                Struktur Organisasi
                            </h3>
                            <p class="card-description">Kelola struktur kepemimpinan dan anggota untuk ditampilkan di website publik</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline-custom" onclick="tampilkanPreview()">
                                <i class="fas fa-eye"></i>
                                Preview
                            </button>
                            <button class="btn btn-primary-custom" onclick="tampilkanFormTambah()">
                                <i class="fas fa-user-plus"></i>
                                Tambah Anggota
                            </button>
                        </div>
                    </div>

                    <!-- Level Tabs -->
                    <div class="level-tabs">
                        <button class="level-tab active" onclick="gantiLevel(1)" data-level="1">
                            <i class=""></i> Level 1 - Pimpinan Tertinggi
                        </button>
                        <button class="level-tab" onclick="gantiLevel(2)" data-level="2">
                            <i class=""></i> Level 2 - Kepala Unit
                        </button>
                        <button class="level-tab" onclick="gantiLevel(3)" data-level="3">
                            <i class=""></i> Level 3 - Anggota
                        </button>
                    </div>

                    <!-- Level Content Container -->
                    <div id="level-content-container">
                        <div class="spinner-container">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Memuat data dari database...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit Anggota -->
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

        // GLOBAL VARIABLES
        let currentLevel = 1;
        let modalAnggota;

        // INISIALISASI
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi modal
            modalAnggota = new bootstrap.Modal(document.getElementById('modalAnggota'));
            
            // Load data pertama kali
            loadAnggotaByLevel(1);
            
            // Load pegawai list untuk dropdown
            loadPegawaiList();
            
            // Load parent list untuk dropdown
            loadParentList();

            // Auto load data pegawai saat halaman dibuka
            loadDataPegawai();

            // Cek jika ada parameter success dari redirect edit
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.get('success') === '1') {
                // Aktifkan tab Data Pegawai
                const dataPegawaiTab = new bootstrap.Tab(document.getElementById('data-pegawai-tab'));
                dataPegawaiTab.show();
                
                // Reload data pegawai
                loadDataPegawai();
                
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

            // Event listener untuk tab Data Pegawai
            const dataPegawaiTab = document.getElementById('data-pegawai-tab');
            if(dataPegawaiTab) {
                dataPegawaiTab.addEventListener('shown.bs.tab', function() {
                    loadDataPegawai();
                });
            }
            
            // Jika tab Data Pegawai sudah aktif saat load halaman
            const activeTab = document.querySelector('#kepegawaianTab .nav-link.active');
            if(activeTab && activeTab.id === 'data-pegawai-tab') {
                loadDataPegawai();
            }
        });

        // FUNGSI GANTI LEVEL
        function gantiLevel(level) {
            currentLevel = level;
            
            // Update active state pada tab
            document.querySelectorAll('.level-tab').forEach(tab => {
                tab.classList.remove('active');
                if(tab.getAttribute('data-level') == level) {
                    tab.classList.add('active');
                }
            });
            
            // Load data level
            loadAnggotaByLevel(level);
        }

        // FUNGSI LOAD DATA DARI DATABASE
        async function loadAnggotaByLevel(level) {
            const container = document.getElementById('level-content-container');
            
            // Show loading
            container.innerHTML = `
                <div class="spinner-container">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data dari database...</p>
                </div>
            `;
            
            try {
                const response = await fetch(`?action=get_by_level&level=${level}`);
                const result = await response.json();
                
                console.log('Response:', result);
                
                if(result.success && result.data.length > 0) {
                    displayAnggota(result.data);
                } else {
                    displayEmptyState();
                }
            } catch(error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memuat Data',
                    html: `
                        <p>Terjadi kesalahan saat memuat data.</p>
                        <p class="text-muted small">Error: ${error.message}</p>
                    `,
                    confirmButtonColor: '#2563eb'
                });
                displayEmptyState();
            }
        }

        // FUNGSI DISPLAY ANGGOTA
        function displayAnggota(data) {
            const container = document.getElementById('level-content-container');
            
            if(data.length === 0) {
                displayEmptyState();
                return;
            }
            
            let html = '';
            data.forEach(anggota => {
                const initials = getInitials(anggota.nama_lengkap);
                
                html += `
                    <div class="member-card" data-id="${anggota.struktur_id}">
                        <div class="avatar">
                            ${initials}
                        </div>
                        <div class="member-info">
                            <div class="member-name">${anggota.nama_lengkap}</div>
                            <div class="member-position">
                                <i class="fas fa-briefcase"></i> ${anggota.jabatan_struktur}
                            </div>
                            <div class="member-department">
                                <i class="fas fa-building"></i> ${anggota.unit_kerja || '-'}
                            </div>
                        </div>
                        <div class="member-actions">
                            <button class="btn-edit" onclick="editAnggota(${anggota.struktur_id})" title="Edit Anggota">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-delete" onclick="hapusAnggota(${anggota.struktur_id})" title="Hapus Anggota">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }

        // FUNGSI DISPLAY EMPTY STATE
        function displayEmptyState() {
            const container = document.getElementById('level-content-container');
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h4>Belum Ada Anggota</h4>
                    <p>Klik tombol "Tambah Anggota" untuk menambahkan anggota baru di level ini</p>
                </div>
            `;
        }

        // FUNGSI GET INITIALS
        function getInitials(name) {
            const words = name.trim().split(' ').filter(word => word.length > 0);
            if(words.length >= 2) {
                return (words[0][0] + words[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        }

        // FUNGSI LOAD PEGAWAI LIST
        async function loadPegawaiList() {
            try {
                const response = await fetch('?action=get_pegawai_list');
                const result = await response.json();
                
                const select = document.getElementById('pegawai_id');
                select.innerHTML = '<option value="">-- Pilih Pegawai --</option>';
                
                if(result.success && result.data.length > 0) {
                    result.data.forEach(pegawai => {
                        const option = document.createElement('option');
                        option.value = pegawai.pegawai_id;
                        option.textContent = `${pegawai.nama_lengkap} - ${pegawai.jabatan || 'Pegawai'}`;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">-- Semua pegawai sudah terdaftar --</option>';
                }
            } catch(error) {
                console.error('Error loading pegawai:', error);
            }
        }

        // FUNGSI LOAD PARENT LIST
        async function loadParentList() {
            try {
                const response = await fetch('?action=get_parent_list');
                const result = await response.json();
                
                const select = document.getElementById('parent_id');
                select.innerHTML = '<option value="">-- Tidak ada atasan --</option>';
                
                if(result.success && result.data.length > 0) {
                    result.data.forEach(parent => {
                        const option = document.createElement('option');
                        option.value = parent.struktur_id;
                        option.textContent = `${parent.nama_lengkap} - ${parent.jabatan_struktur} (Level ${parent.level_struktur})`;
                        select.appendChild(option);
                    });
                }
            } catch(error) {
                console.error('Error loading parent:', error);
            }
        }

        // FUNGSI TAMPILKAN FORM TAMBAH
        function tampilkanFormTambah() {
            // Reset form
            document.getElementById('formAnggota').reset();
            document.getElementById('mode').value = 'add';
            document.getElementById('struktur_id').value = '';
            document.getElementById('modalTitleText').textContent = 'Tambah Anggota Baru';
            
            // Set level sesuai tab yang aktif
            document.getElementById('level_struktur').value = currentLevel;
            
            // Enable pegawai select
            document.getElementById('pegawai_id').disabled = false;
            
            // Reload pegawai list dan parent list
            loadPegawaiList();
            loadParentList();
            
            // Show modal
            modalAnggota.show();
        }

        // FUNGSI EDIT ANGGOTA
        async function editAnggota(id) {
            try {
                const response = await fetch(`?action=get_by_id&id=${id}`);
                const result = await response.json();
                
                if(result.success) {
                    const data = result.data;
                    
                    // Set mode edit
                    document.getElementById('mode').value = 'edit';
                    document.getElementById('struktur_id').value = data.struktur_id;
                    document.getElementById('modalTitleText').textContent = 'Edit Anggota';
                    
                    // Fill form
                    document.getElementById('pegawai_id').value = data.pegawai_id;
                    document.getElementById('jabatan_struktur').value = data.jabatan_struktur;
                    document.getElementById('level_struktur').value = data.level_struktur;
                    
                    // Load parent list dan set value
                    await loadParentList();
                    if(data.parent_id) {
                        document.getElementById('parent_id').value = data.parent_id;
                    }
                    
                    // Disable pegawai select saat edit
                    document.getElementById('pegawai_id').disabled = true;
                    
                    // Show modal
                    modalAnggota.show();
                }
            } catch(error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Terjadi kesalahan saat mengambil data',
                    confirmButtonColor: '#2563eb'
                });
            }
        }

        // FUNGSI SIMPAN ANGGOTA
        async function simpanAnggota() {
            const form = document.getElementById('formAnggota');
            
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const mode = document.getElementById('mode').value;
            const data = {
                pegawai_id: document.getElementById('pegawai_id').value,
                jabatan_struktur: document.getElementById('jabatan_struktur').value,
                level_struktur: document.getElementById('level_struktur').value,
                parent_id: document.getElementById('parent_id').value || null
            };
            
            if(mode === 'edit') {
                data.struktur_id = document.getElementById('struktur_id').value;
            }
            
            const action = mode === 'edit' ? 'update' : 'add';
            
            try {
                const response = await fetch(`?action=${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if(result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: result.message,
                        confirmButtonColor: '#2563eb',
                        timer: 2000
                    });
                    
                    // Close modal
                    modalAnggota.hide();
                    
                    // Reload data
                    loadAnggotaByLevel(currentLevel);
                    
                    // Reload pegawai list
                    loadPegawaiList();
                    
                    // Enable pegawai select
                    document.getElementById('pegawai_id').disabled = false;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: result.message,
                        confirmButtonColor: '#2563eb'
                    });
                }
            } catch(error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan saat menyimpan data',
                    confirmButtonColor: '#2563eb'
                });
            }
        }

        // FUNGSI HAPUS ANGGOTA
        async function hapusAnggota(id) {
            const result = await Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data yang sudah dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            });
            
            if(result.isConfirmed) {
                try {
                    const response = await fetch(`?action=delete&id=${id}`);
                    const data = await response.json();
                    
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Terhapus!',
                            text: data.message,
                            confirmButtonColor: '#2563eb',
                            timer: 2000
                        });
                        
                        // Reload data
                        loadAnggotaByLevel(currentLevel);
                        
                        // Reload pegawai list
                        loadPegawaiList();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: data.message,
                            confirmButtonColor: '#2563eb'
                        });
                    }
                } catch(error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan saat menghapus data',
                        confirmButtonColor: '#2563eb'
                    });
                }
            }
        }

        // FUNGSI PREVIEW
        function tampilkanPreview() {
            Swal.fire({
                icon: 'info',
                title: 'Preview',
                text: 'Halaman preview struktur organisasi untuk tampilan public akan segera tersedia',
                confirmButtonColor: '#2563eb'
            });
        }

        // FUNGSI DATA PEGAWAI - FIXED VERSION

        // Load Data Pegawai
        async function loadDataPegawai() {
            const container = document.getElementById('data-pegawai-container');
            
            container.innerHTML = `
                <div class="spinner-container">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Memuat data pegawai dari database...</p>
                </div>
            `;
            
            try {
                const response = await fetch('?action=get_all_pegawai');
                const result = await response.json();
                
                console.log('Data Pegawai:', result);
                
                if(result.success && result.data.length > 0) {
                    displayDataPegawai(result.data);
                } else {
                    displayEmptyStatePegawai();
                }
            } catch(error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Memuat Data',
                    text: 'Terjadi kesalahan saat memuat data pegawai',
                    confirmButtonColor: '#2563eb'
                });
                displayEmptyStatePegawai();
            }
        }

        // Helper function untuk menghitung sisa kontrak
        function hitungSisaKontrak(sisaHari) {
            if (!sisaHari || sisaHari <= 0) {
                return {
                    text: '-',
                    isWarning: false
                };
            }
            
            const bulan = Math.floor(sisaHari / 30);
            const hari = sisaHari % 30;
            
            let text = '';
            if (bulan > 0 && hari > 0) {
                text = `${bulan} Bulan ${hari} Hari`;
            } else if (bulan > 0) {
                text = `${bulan} Bulan`;
            } else {
                text = `${hari} Hari`;
            }
            
            return {
                text: text,
                isWarning: sisaHari < 30 
            };
        }

        // Display Data Pegawai dalam Tabel 
        function displayDataPegawai(data) {
            const container = document.getElementById('data-pegawai-container');
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-pegawai">
                        <thead>
                            <tr>
                                <th style="width: 40px;">NO</th>
                                <th style="width: 200px;">NAMA LENGKAP</th>
                                <th style="width: 150px;">JABATAN</th>
                                <th style="width: 100px;">PEGAWAI</th>
                                <th style="width: 100px;">STATUS</th>
                                <th style="width: 130px;">UNIT KERJA</th>
                                <th style="width: 120px;">SISA KONTRAK</th>
                                <th style="width: 140px;">AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-pegawai">
            `;
            
            data.forEach((pegawai, index) => {
                // Badge Jenis Kepegawaian (Kontrak/Tetap)
                let jenisKepegawaianBadge = '';
                const jenisKepegawaian = (pegawai.jenis_kepegawaian || 'tetap').toLowerCase();
                if(jenisKepegawaian === 'kontrak') {
                    jenisKepegawaianBadge = '<span class="badge-custom badge-kontrak">Kontrak</span>';
                } else {
                    jenisKepegawaianBadge = '<span class="badge-custom badge-tetap">Tetap</span>';
                }
                
                // Badge Status Aktif
                let statusAktifBadge = '';
                const statusAktif = (pegawai.status_aktif || 'aktif').toLowerCase();
                if(statusAktif === 'aktif') {
                    statusAktifBadge = '<span class="badge-custom badge-aktif">Aktif</span>';
                } else {
                    statusAktifBadge = '<span class="badge-custom badge-tidak-aktif">Tidak Aktif</span>';
                }
                
                // Sisa Kontrak (hanya untuk pegawai kontrak)
                let sisaKontrak = '-';
                let sisaKontrakClass = 'sisa-kontrak-normal';
                
                if(jenisKepegawaian === 'kontrak' && pegawai.sisa_hari_kontrak) {
                    const kontrakInfo = hitungSisaKontrak(pegawai.sisa_hari_kontrak);
                    sisaKontrak = kontrakInfo.text;
                    sisaKontrakClass = kontrakInfo.isWarning ? 'sisa-kontrak-warning' : 'sisa-kontrak-normal';
                }
                
                html += `
                    <tr data-id="${pegawai.pegawai_id}" 
                        data-jenis-pegawai="${(pegawai.jenis_pegawai || '').toLowerCase()}"
                        data-jenis-kepegawaian="${jenisKepegawaian}" 
                        data-status-aktif="${statusAktif}">
                        <td class="text-center" style="font-weight: 600; color: #6b7280;">${index + 1}</td>
                        <td>
                            <div class="nama-pegawai">${pegawai.nama_lengkap}</div>
                        </td>
                        <td style="font-size: 12px;">${pegawai.jabatan || '-'}</td>
                        <td class="text-center">${jenisKepegawaianBadge}</td>
                        <td class="text-center">${statusAktifBadge}</td>
                        <td style="font-size: 12px;">${pegawai.unit_kerja || '-'}</td>
                        <td class="text-center" style="font-size: 12px;">
                            <span class="${sisaKontrakClass}">${sisaKontrak}</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-view" 
                                        onclick="lihatDetailPegawai(${pegawai.pegawai_id})" 
                                        title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action btn-edit-table" 
                                        onclick="editPegawai(${pegawai.pegawai_id})" 
                                        title="Edit Data">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-action btn-delete-table" 
                                        onclick="hapusPegawai(${pegawai.pegawai_id}, '${pegawai.nama_lengkap.replace(/'/g, "\\'")}');" 
                                        title="Hapus Data">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Menampilkan <strong>${data.length}</strong> pegawai
                </div>
            `;
            
            container.innerHTML = html;
        }

        // Display Empty State Pegawai
        function displayEmptyStatePegawai() {
            const container = document.getElementById('data-pegawai-container');
            container.innerHTML = `
                <div class="empty-state-pegawai">
                    <i class="fas fa-users-slash"></i>
                    <h4>Belum Ada Data Pegawai</h4>
                    <p>Silakan tambahkan data pegawai untuk memulai</p>
                    <button class="btn btn-primary-custom" onclick="tambahPegawai()">
                        <i class="fas fa-plus me-2"></i>
                        Tambah Pegawai Sekarang
                    </button>
                </div>
            `;
        }

        // Redirect ke Halaman Tambah Pegawai
        function tambahPegawai() {
            window.location.href = '../administrasi/tambah_pegawai.php';
        }

        // Redirect ke Halaman Edit Pegawai
        function editPegawai(id) {
            window.location.href = `../administrasi/edit_pegawai.php?id=${id}`;
        }

        // Redirect ke Halaman Detail Pegawai
        function lihatDetailPegawai(id) {
            window.location.href = `../administrasi/detail_pegawai.php?id=${id}`;
        }

        // Hapus Pegawai dengan Konfirmasi
        async function hapusPegawai(id, nama) {
            const result = await Swal.fire({
                title: 'Konfirmasi Hapus',
                html: `
                    <p>Apakah Anda yakin ingin menghapus pegawai:</p>
                    <p class="fw-bold text-danger">${nama}</p>
                    <p class="text-muted small">Data yang sudah dihapus tidak dapat dikembalikan!</p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Ya, Hapus!',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Batal',
                reverseButtons: true
            });
            
            if(result.isConfirmed) {
                try {
                    Swal.fire({
                        title: 'Menghapus Data...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const response = await fetch(`../administrasi/hapus_pegawai.php?id=${id}`);
                    const data = await response.json();
                    
                    if(data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil Dihapus!',
                            text: 'Data pegawai berhasil dihapus dari sistem',
                            confirmButtonColor: '#2563eb',
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            loadDataPegawai();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal Menghapus!',
                            text: data.message || 'Terjadi kesalahan saat menghapus data',
                            confirmButtonColor: '#2563eb'
                        });
                    }
                } catch(error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menghapus!',
                        text: 'Terjadi kesalahan pada server',
                        confirmButtonColor: '#2563eb'
                    });
                }
            }
        }

        // Refresh Data Pegawai
        function refreshDataPegawai() {
            loadDataPegawai();
            
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            });
            
            Toast.fire({
                icon: 'success',
                title: 'Data berhasil dimuat ulang'
            });
        }

        // Filter Pegawai - FIXED VERSION WITH JENIS PEGAWAI
        function filterPegawai() {
            const searchValue = document.getElementById('searchPegawai').value.toLowerCase();
            const jenisPegawaiValue = document.getElementById('filterJenisPegawai').value.toLowerCase();
            const jenisKepegawaianValue = document.getElementById('filterJenisKepegawaian').value.toLowerCase();
            const statusAktifValue = document.getElementById('filterStatusAktif').value.toLowerCase();
            
            const rows = document.querySelectorAll('#tbody-pegawai tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const namaLengkap = row.cells[1].querySelector('.nama-pegawai').textContent.toLowerCase();
                const jenisPegawai = row.getAttribute('data-jenis-pegawai').toLowerCase();
                const jenisKepegawaian = row.getAttribute('data-jenis-kepegawaian').toLowerCase();
                const statusAktif = row.getAttribute('data-status-aktif').toLowerCase();
                
                const matchSearch = namaLengkap.includes(searchValue);
                const matchJenisPegawai = !jenisPegawaiValue || jenisPegawai === jenisPegawaiValue;
                const matchJenisKepegawaian = !jenisKepegawaianValue || jenisKepegawaian === jenisKepegawaianValue;
                const matchStatusAktif = !statusAktifValue || statusAktif === statusAktifValue;
                
                if (matchSearch && matchJenisPegawai && matchJenisKepegawaian && matchStatusAktif) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            updatePegawaiCounter();
        }

        // Sort Pegawai
        function sortPegawai() {
            const sortValue = document.getElementById('sortBy').value;
            const tbody = document.getElementById('tbody-pegawai');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                let aValue, bValue;
                
                switch(sortValue) {
                    case 'nama_asc':
                        aValue = a.cells[1].querySelector('.nama-pegawai').textContent.toLowerCase();
                        bValue = b.cells[1].querySelector('.nama-pegawai').textContent.toLowerCase();
                        return aValue.localeCompare(bValue);
                    
                    case 'nama_desc':
                        aValue = a.cells[1].querySelector('.nama-pegawai').textContent.toLowerCase();
                        bValue = b.cells[1].querySelector('.nama-pegawai').textContent.toLowerCase();
                        return bValue.localeCompare(aValue);
                    
                    default:
                        return 0;
                }
            });
            
            rows.forEach((row, index) => {
                row.cells[0].textContent = index + 1;
                tbody.appendChild(row);
            });
            
            updatePegawaiCounter();
        }

        // Helper function untuk update counter
        function updatePegawaiCounter() {
            const rows = document.querySelectorAll('#tbody-pegawai tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    visibleCount++;
                }
            });
            
            const container = document.getElementById('data-pegawai-container');
            const counter = container.querySelector('.text-muted.small');
            if(counter) {
                counter.innerHTML = `<i class="fas fa-info-circle me-1"></i>Menampilkan <strong>${visibleCount}</strong> pegawai`;
            }
        }

        // EVENT LISTENER - MODAL HIDDEN
        document.getElementById('modalAnggota').addEventListener('hidden.bs.modal', function() {
            document.getElementById('pegawai_id').disabled = false;
        });

    </script>
</body>
</html>