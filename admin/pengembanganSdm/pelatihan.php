<?php
// =====================================================
// HANDLER CRUD PELATIHAN
// Handler ini hanya execute jika ada POST/GET request khusus
// =====================================================

// HANDLER: TAMBAH PELATIHAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    try {
        $judul_pelatihan = $_POST['judul_pelatihan'];
        $deskripsi = $_POST['deskripsi'] ?? null;
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $lokasi = $_POST['lokasi'];
        $instruktur = $_POST['instruktur'] ?? null;
        $anggota = $_POST['anggota'] ?? [];
        $created_by = $_SESSION['user_id'];

        if (strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Tanggal selesai tidak boleh lebih awal dari tanggal mulai'));
            exit();
        }

        $upload_dir = '../../uploads/pelatihan/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $path_flyer = null;
        $path_undangan = null;

        if (isset($_FILES['flyer']) && $_FILES['flyer']['error'] === UPLOAD_ERR_OK) {
            $flyer = $_FILES['flyer'];
            $flyer_ext = strtolower(pathinfo($flyer['name'], PATHINFO_EXTENSION));
            $allowed_image = ['jpg', 'jpeg', 'png'];

            if (!in_array($flyer_ext, $allowed_image)) {
                header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Format flyer tidak valid'));
                exit();
            }

            if ($flyer['size'] > 3 * 1024 * 1024) {
                header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file flyer maksimal 3MB'));
                exit();
            }

            $flyer_name = 'flyer_' . time() . '_' . uniqid() . '.' . $flyer_ext;
            $flyer_path = $upload_dir . $flyer_name;

            if (move_uploaded_file($flyer['tmp_name'], $flyer_path)) {
                $path_flyer = 'uploads/pelatihan/' . $flyer_name;
            }
        }

        if (!isset($_FILES['undangan']) || $_FILES['undangan']['error'] !== UPLOAD_ERR_OK) {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('File undangan PDF wajib diupload'));
            exit();
        }

        $undangan = $_FILES['undangan'];
        $undangan_ext = strtolower(pathinfo($undangan['name'], PATHINFO_EXTENSION));

        if ($undangan_ext !== 'pdf') {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Format undangan tidak valid. Hanya PDF yang diperbolehkan'));
            exit();
        }

        if ($undangan['size'] > 5 * 1024 * 1024) {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file undangan maksimal 5MB'));
            exit();
        }

        $undangan_name = 'undangan_' . time() . '_' . uniqid() . '.pdf';
        $undangan_path = $upload_dir . $undangan_name;

        if (!move_uploaded_file($undangan['tmp_name'], $undangan_path)) {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Gagal mengupload file undangan'));
            exit();
        }

        $path_undangan = 'uploads/pelatihan/' . $undangan_name;

        $metadata = [
            'deskripsi' => $deskripsi,
            'path_flyer' => $path_flyer,
            'path_undangan' => $path_undangan,
            'anggota' => $anggota
        ];
        $deskripsi_with_metadata = json_encode($metadata);

        $query = "INSERT INTO pelatihan (
            judul_pelatihan,
            deskripsi,
            tanggal_mulai,
            tanggal_selesai,
            lokasi,
            instruktur,
            created_by
        ) VALUES (
            :judul_pelatihan,
            :deskripsi,
            :tanggal_mulai,
            :tanggal_selesai,
            :lokasi,
            :instruktur,
            :created_by
        )";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':judul_pelatihan' => $judul_pelatihan,
            ':deskripsi' => $deskripsi_with_metadata,
            ':tanggal_mulai' => $tanggal_mulai,
            ':tanggal_selesai' => $tanggal_selesai,
            ':lokasi' => $lokasi,
            ':instruktur' => $instruktur,
            ':created_by' => $created_by
        ]);

        header('Location: index.php?tab=pelatihan&status=success&message=' . urlencode('Pelatihan berhasil ditambahkan'));
        exit();

    } catch (Exception $e) {
        error_log('Error tambah pelatihan: ' . $e->getMessage());
        header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Gagal menambahkan pelatihan'));
        exit();
    }
}

// HANDLER: EDIT PELATIHAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $pelatihan_id = (int)$_POST['pelatihan_id'];
        $judul_pelatihan = $_POST['judul_pelatihan'];
        $deskripsi_text = $_POST['deskripsi'] ?? null;
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $lokasi = $_POST['lokasi'];
        $instruktur = $_POST['instruktur'] ?? null;
        $anggota = $_POST['anggota'] ?? [];

        if (strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Tanggal selesai tidak boleh lebih awal dari tanggal mulai'));
            exit();
        }

        $query_old = "SELECT deskripsi FROM pelatihan WHERE pelatihan_id = :id";
        $stmt_old = $conn->prepare($query_old);
        $stmt_old->execute([':id' => $pelatihan_id]);
        $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
        
        $old_metadata = json_decode($old_data['deskripsi'], true);
        $path_flyer = $old_metadata['path_flyer'] ?? null;
        $path_undangan = $old_metadata['path_undangan'] ?? null;

        $upload_dir = '../../uploads/pelatihan/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (isset($_FILES['flyer']) && $_FILES['flyer']['error'] === UPLOAD_ERR_OK) {
            $flyer = $_FILES['flyer'];
            $flyer_ext = strtolower(pathinfo($flyer['name'], PATHINFO_EXTENSION));
            $allowed_image = ['jpg', 'jpeg', 'png'];

            if (!in_array($flyer_ext, $allowed_image)) {
                header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Format flyer tidak valid'));
                exit();
            }

            if ($flyer['size'] > 3 * 1024 * 1024) {
                header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file flyer maksimal 3MB'));
                exit();
            }

            if ($path_flyer && file_exists('../../' . $path_flyer)) {
                unlink('../../' . $path_flyer);
            }

            $flyer_name = 'flyer_' . time() . '_' . uniqid() . '.' . $flyer_ext;
            $flyer_path = $upload_dir . $flyer_name;

            if (move_uploaded_file($flyer['tmp_name'], $flyer_path)) {
                $path_flyer = 'uploads/pelatihan/' . $flyer_name;
            }
        }

        if (isset($_FILES['undangan']) && $_FILES['undangan']['error'] === UPLOAD_ERR_OK) {
            $undangan = $_FILES['undangan'];
            $undangan_ext = strtolower(pathinfo($undangan['name'], PATHINFO_EXTENSION));

            if ($undangan_ext !== 'pdf') {
                header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Format undangan tidak valid'));
                exit();
            }

            if ($undangan['size'] > 5 * 1024 * 1024) {
                header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Ukuran file undangan maksimal 5MB'));
                exit();
            }

            if ($path_undangan && file_exists('../../' . $path_undangan)) {
                unlink('../../' . $path_undangan);
            }

            $undangan_name = 'undangan_' . time() . '_' . uniqid() . '.pdf';
            $undangan_path = $upload_dir . $undangan_name;

            if (move_uploaded_file($undangan['tmp_name'], $undangan_path)) {
                $path_undangan = 'uploads/pelatihan/' . $undangan_name;
            }
        }

        $metadata = [
            'deskripsi' => $deskripsi_text,
            'path_flyer' => $path_flyer,
            'path_undangan' => $path_undangan,
            'anggota' => $anggota
        ];
        $deskripsi_with_metadata = json_encode($metadata);

        $query = "UPDATE pelatihan SET 
            judul_pelatihan = :judul_pelatihan,
            deskripsi = :deskripsi,
            tanggal_mulai = :tanggal_mulai,
            tanggal_selesai = :tanggal_selesai,
            lokasi = :lokasi,
            instruktur = :instruktur
        WHERE pelatihan_id = :pelatihan_id";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':judul_pelatihan' => $judul_pelatihan,
            ':deskripsi' => $deskripsi_with_metadata,
            ':tanggal_mulai' => $tanggal_mulai,
            ':tanggal_selesai' => $tanggal_selesai,
            ':lokasi' => $lokasi,
            ':instruktur' => $instruktur,
            ':pelatihan_id' => $pelatihan_id
        ]);

        header('Location: index.php?tab=pelatihan&status=success&message=' . urlencode('Pelatihan berhasil diupdate'));
        exit();

    } catch (Exception $e) {
        error_log('Error edit pelatihan: ' . $e->getMessage());
        header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Gagal mengupdate pelatihan'));
        exit();
    }
}

// HANDLER: HAPUS PELATIHAN
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    try {
        $pelatihan_id = (int)$_GET['id'];
        
        $query = "SELECT deskripsi FROM pelatihan WHERE pelatihan_id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $pelatihan_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $metadata = json_decode($data['deskripsi'], true);
            
            if (isset($metadata['path_flyer']) && file_exists('../../' . $metadata['path_flyer'])) {
                unlink('../../' . $metadata['path_flyer']);
            }
            
            if (isset($metadata['path_undangan']) && file_exists('../../' . $metadata['path_undangan'])) {
                unlink('../../' . $metadata['path_undangan']);
            }
            
            $query_delete = "DELETE FROM pelatihan WHERE pelatihan_id = :id";
            $stmt_delete = $conn->prepare($query_delete);
            $stmt_delete->execute([':id' => $pelatihan_id]);
            
            header('Location: index.php?tab=pelatihan&status=success&message=' . urlencode('Pelatihan berhasil dihapus'));
            exit();
        }
        
        header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Pelatihan tidak ditemukan'));
        exit();
        
    } catch (Exception $e) {
        error_log('Error hapus pelatihan: ' . $e->getMessage());
        header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Gagal menghapus pelatihan'));
        exit();
    }
}

// =====================================================
// QUERY DATA (Bagian ini SELALU dijalankan)
// =====================================================

$query_pelatihan = "SELECT * FROM pelatihan ORDER BY created_at DESC";
$stmt_pelatihan = $conn->prepare($query_pelatihan);
$stmt_pelatihan->execute();
$pelatihan_data = $stmt_pelatihan->fetchAll(PDO::FETCH_ASSOC);

$query_pegawai = "SELECT pegawai_id, nama_lengkap, nik FROM pegawai ORDER BY nama_lengkap ASC";
$stmt_pegawai = $conn->prepare($query_pegawai);
$stmt_pegawai->execute();
$pegawai_list = $stmt_pegawai->fetchAll(PDO::FETCH_ASSOC);

$edit_mode = false;
$edit_data = null;

if (isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_mode = true;
    $edit_id = (int)$_GET['id'];
    
    $query_edit = "SELECT * FROM pelatihan WHERE pelatihan_id = :id";
    $stmt_edit = $conn->prepare($query_edit);
    $stmt_edit->execute([':id' => $edit_id]);
    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data && $edit_data['deskripsi']) {
        $metadata = json_decode($edit_data['deskripsi'], true);
        if ($metadata) {
            $edit_data['deskripsi_text'] = $metadata['deskripsi'] ?? '';
            $edit_data['path_flyer'] = $metadata['path_flyer'] ?? null;
            $edit_data['path_undangan'] = $metadata['path_undangan'] ?? null;
            $edit_data['anggota'] = $metadata['anggota'] ?? [];
        }
    }
}
?>
<style>
    /* ===== FORM SECTION ===== */
    .form-section {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px dashed #3b82f6;
        border-radius: 12px;
        padding: 32px;
        margin-bottom: 24px;
    }

    .form-icon {
        width: 56px;
        height: 56px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 16px;
        font-size: 24px;
        color: #3b82f6;
    }

    .form-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 24px;
        text-align: center;
    }

    .pelatihan-form {
        max-width: 900px;
        margin: 0 auto;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 6px;
    }

    .form-group label .required {
        color: #dc2626;
    }

    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="file"],
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #93c5fd;
        border-radius: 10px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
    }

    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-group small {
        font-size: 11px;
        color: #1e40af;
        margin-top: 4px;
    }

    .form-group input[type="file"] {
        padding: 8px 12px;
        cursor: pointer;
    }

    .form-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-top: 24px;
    }

    .btn-submit {
        padding: 12px 32px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3);
    }

    .btn-reset {
        padding: 12px 32px;
        background: #f1f5f9;
        color: #475569;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
    }

    .btn-reset:hover {
        background: #e2e8f0;
    }

    /* ===== LIST SECTION ===== */
    .list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        margin-top: 40px;
    }

    .section-subtitle {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
    }

    .pelatihan-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }

    .pelatihan-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        transition: all 0.3s ease;
    }

    .pelatihan-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .pelatihan-header {
        display: flex;
        gap: 16px;
        margin-bottom: 16px;
    }

    .pelatihan-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        flex-shrink: 0;
    }

    .pelatihan-info {
        flex: 1;
    }

    .pelatihan-title {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .pelatihan-date {
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .pelatihan-details {
        margin-bottom: 16px;
    }

    .detail-row {
        display: flex;
        align-items: start;
        gap: 8px;
        margin-bottom: 8px;
        font-size: 13px;
    }

    .detail-row i {
        color: #3b82f6;
        width: 16px;
        margin-top: 2px;
    }

    .detail-row .detail-text {
        color: #475569;
        flex: 1;
    }

    .pelatihan-files {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .file-badge {
        padding: 6px 12px;
        background: #e0e7ff;
        color: #3730a3;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .file-badge.optional {
        background: #fef3c7;
        color: #92400e;
    }

    .pelatihan-actions {
        display: flex;
        gap: 8px;
    }

    .btn-edit {
        padding: 8px 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-delete {
        padding: 8px 16px;
        background: #fee2e2;
        color: #dc2626;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        background: #fecaca;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.3;
    }

    .empty-state p {
        font-size: 16px;
        font-weight: 500;
    }

    /* Select2-like styling for multiple select */
    select[multiple] {
        min-height: 120px;
    /* ===== BUTTON TAMBAH PELATIHAN ===== */
    .add-pelatihan-btn {
        padding: 14px 28px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 24px;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .add-pelatihan-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    <!-- Tombol Tambah Pelatihan -->
    <button class="add-pelatihan-btn" id="btnTambahPelatihan" onclick="toggleFormPelatihan()">
        <i class="fas fa-plus-circle"></i>
        Tambah Pelatihan
    </button>

    /* Form section tersembunyi secara default */
    .form-section {
        display: none;
        animation: slideDown 0.3s ease-out;
    }

    .form-section.show {
        display: block;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }

        .pelatihan-grid {
            grid-template-columns: 1fr;
        }

        .form-actions {
            flex-direction: column;
        }

        .btn-submit,
        .btn-reset {
            width: 100%;
        }
    }
</style>

<div class="content-card">
    <!-- Form Input Pelatihan -->
    <div class="form-section<?php echo $edit_mode ? ' show' : ''; ?>" id="formSection">
        <div class="form-icon">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="form-title"><?php echo $edit_mode ? 'Edit Pelatihan' : 'Tambah Pelatihan Baru'; ?></div>
        
        <form class="pelatihan-form" id="pelatihanForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'tambah'; ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="pelatihan_id" value="<?php echo $edit_data['pelatihan_id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Judul Pelatihan <span class="required">*</span></label>
                    <input type="text" name="judul_pelatihan" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['judul_pelatihan']) : ''; ?>" 
                           placeholder="Contoh: Pelatihan Pedagogik 2024" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tanggal Mulai <span class="required">*</span></label>
                    <input type="date" name="tanggal_mulai" 
                           value="<?php echo $edit_mode ? $edit_data['tanggal_mulai'] : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>Tanggal Selesai <span class="required">*</span></label>
                    <input type="date" name="tanggal_selesai" 
                           value="<?php echo $edit_mode ? $edit_data['tanggal_selesai'] : ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tempat <span class="required">*</span></label>
                    <input type="text" name="lokasi" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['lokasi']) : ''; ?>" 
                           placeholder="Contoh: Ruang Gelatik" required>
                </div>
                <div class="form-group">
                    <label>Instruktur</label>
                    <input type="text" name="instruktur" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['instruktur'] ?? '') : ''; ?>" 
                           placeholder="Nama instruktur/narasumber">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" placeholder="Deskripsi singkat tentang pelatihan..."><?php echo $edit_mode ? htmlspecialchars($edit_metadata['deskripsi'] ?? '') : ''; ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Anggota/Peserta</label>
                    <select name="anggota[]" multiple size="5">
                        <?php 
                        $selected_anggota = $edit_mode ? ($edit_metadata['anggota'] ?? []) : [];
                        foreach ($pegawai_data as $pegawai): 
                            $selected = in_array($pegawai['pegawai_id'], $selected_anggota) ? 'selected' : '';
                        ?>
                            <option value="<?php echo $pegawai['pegawai_id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($pegawai['nama_lengkap']); ?> 
                                - <?php echo htmlspecialchars($pegawai['jabatan'] ?? 'Staff'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Tekan Ctrl/Cmd + Click untuk memilih lebih dari satu pegawai</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Flyer Pelatihan <?php echo $edit_mode ? '' : '(Opsional)'; ?></label>
                    <input type="file" name="flyer" id="flyerInput" accept="image/*">
                    <?php if ($edit_mode && !empty($edit_metadata['path_flyer'])): ?>
                        <div style="margin-top: 8px; padding: 8px 12px; background: #f1f5f9; border-radius: 6px; font-size: 12px;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> 
                            File saat ini: <?php echo basename($edit_metadata['path_flyer']); ?>
                        </div>
                    <?php endif; ?>
                    <small><i class="fas fa-info-circle"></i> Format: JPG, PNG, JPEG | Max: 3MB <?php echo $edit_mode ? '(Upload baru untuk mengganti)' : ''; ?></small>
                </div>
                <div class="form-group">
                    <label>Undangan PDF <?php echo $edit_mode ? '' : '<span class="required">*</span>'; ?></label>
                    <input type="file" name="undangan" id="undanganInput" accept=".pdf" <?php echo $edit_mode ? '' : 'required'; ?>>
                    <?php if ($edit_mode && !empty($edit_metadata['path_undangan'])): ?>
                        <div style="margin-top: 8px; padding: 8px 12px; background: #f1f5f9; border-radius: 6px; font-size: 12px;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> 
                            File saat ini: <?php echo basename($edit_metadata['path_undangan']); ?>
                        </div>
                    <?php endif; ?>
                    <small><i class="fas fa-info-circle"></i> Format: PDF | Max: 5MB <?php echo $edit_mode ? '(Upload baru untuk mengganti)' : ''; ?></small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Update Pelatihan' : 'Simpan Pelatihan'; ?>
                </button>
                <?php if ($edit_mode): ?>
                    <a href="index.php?tab=pelatihan" class="btn-reset" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times"></i> Batal Edit
                    </a>
                <?php else: ?>
                    <button type="reset" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- List Pelatihan -->
    <div class="list-header">
        <h3 class="section-subtitle">Daftar Pelatihan</h3>
    </div>

    <?php if (count($pelatihan_data) > 0): ?>
        <div class="pelatihan-grid">
            <?php foreach ($pelatihan_data as $pelatihan): 
                $tanggal_mulai = date('d M Y', strtotime($pelatihan['tanggal_mulai']));
                $tanggal_selesai = date('d M Y', strtotime($pelatihan['tanggal_selesai']));
            ?>
            <div class="pelatihan-card">
                <div class="pelatihan-header">
                    <div class="pelatihan-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="pelatihan-info">
                        <div class="pelatihan-title"><?php echo htmlspecialchars($pelatihan['judul_pelatihan']); ?></div>
                        <div class="pelatihan-date">
                            <i class="fas fa-calendar"></i>
                            <?php echo $tanggal_mulai; ?> - <?php echo $tanggal_selesai; ?>
                        </div>
                    </div>
                </div>

                <div class="pelatihan-details">
                    <?php if ($pelatihan['lokasi']): ?>
                    <div class="detail-row">
                        <i class="fas fa-map-marker-alt"></i>
                        <span class="detail-text"><?php echo htmlspecialchars($pelatihan['lokasi']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($pelatihan['instruktur']): ?>
                    <div class="detail-row">
                        <i class="fas fa-user-tie"></i>
                        <span class="detail-text"><?php echo htmlspecialchars($pelatihan['instruktur']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($pelatihan['deskripsi']): ?>
                    <div class="detail-row">
                        <i class="fas fa-info-circle"></i>
                        <span class="detail-text"><?php echo htmlspecialchars(substr($pelatihan['deskripsi'], 0, 100)); ?><?php echo strlen($pelatihan['deskripsi']) > 100 ? '...' : ''; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="pelatihan-files">
                    <span class="file-badge">
                        <i class="fas fa-file-pdf"></i> Undangan
                    </span>
                    <span class="file-badge optional">
                        <i class="fas fa-image"></i> Flyer
                    </span>
                </div>

                <div class="pelatihan-actions">
                    <button class="btn-edit" onclick="editPelatihan(<?php echo $pelatihan['pelatihan_id']; ?>)">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn-delete" onclick="deletePelatihan(<?php echo $pelatihan['pelatihan_id']; ?>, '<?php echo addslashes(htmlspecialchars($pelatihan['judul_pelatihan'])); ?>')">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <p>Belum ada pelatihan yang ditambahkan</p>
        </div>
    <?php endif; ?>
</div>

<script>
    // Validasi form
    document.getElementById('pelatihanForm')?.addEventListener('submit', function(e) {
        const flyerInput = document.getElementById('flyerInput');
        const undanganInput = document.getElementById('undanganInput');
        const isEditMode = document.querySelector('input[name="action"]').value === 'edit';
        
        // Validasi flyer (opsional)
        if (flyerInput.files.length > 0) {
            const flyerFile = flyerInput.files[0];
            const maxFlyerSize = 3 * 1024 * 1024; // 3MB
            
            if (flyerFile.size > maxFlyerSize) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'File Flyer Terlalu Besar!',
                    text: 'Ukuran file flyer maksimal adalah 3MB',
                    confirmButtonColor: '#3b82f6'
                });
                return false;
            }

            // Validasi tipe file flyer
            const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedImageTypes.includes(flyerFile.type)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Format Flyer Tidak Valid!',
                    text: 'Hanya file JPG, JPEG, dan PNG yang diperbolehkan',
                    confirmButtonColor: '#3b82f6'
                });
                return false;
            }
        }
        
        // Validasi undangan (wajib untuk tambah, opsional untuk edit)
        if (undanganInput.files.length > 0) {
            const undanganFile = undanganInput.files[0];
            const maxUndanganSize = 5 * 1024 * 1024; // 5MB
            
            if (undanganFile.size > maxUndanganSize) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'File Undangan Terlalu Besar!',
                    text: 'Ukuran file undangan maksimal adalah 5MB',
                    confirmButtonColor: '#3b82f6'
                });
                return false;
            }

            // Validasi tipe file undangan
            if (undanganFile.type !== 'application/pdf') {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Format Undangan Tidak Valid!',
                    text: 'Hanya file PDF yang diperbolehkan',
                    confirmButtonColor: '#3b82f6'
                });
                return false;
            }
        }

        // Validasi tanggal
        const tanggalMulai = new Date(document.querySelector('input[name="tanggal_mulai"]').value);
        const tanggalSelesai = new Date(document.querySelector('input[name="tanggal_selesai"]').value);

        if (tanggalSelesai < tanggalMulai) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Tanggal Tidak Valid!',
                text: 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai',
                confirmButtonColor: '#3b82f6'
            });
            return false;
        }
    });

    // Edit pelatihan - redirect ke form dengan parameter
    function editPelatihan(id) {
        window.location.href = 'index.php?tab=pelatihan&edit=1&id=' + id;
        // Scroll to top untuk melihat form
        setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }), 100);
    }

    // Delete pelatihan
    function deletePelatihan(id, judul) {
        Swal.fire({
            title: 'Hapus Pelatihan?',
            html: `Apakah Anda yakin ingin menghapus pelatihan:<br><strong>${judul}</strong><br><br><small style="color: #dc2626;">File flyer dan undangan juga akan terhapus!</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php?tab=pelatihan&action=hapus&id=' + id;
            }
        });
    }

    // Scroll to form jika dalam mode edit
    <?php if ($edit_mode): ?>
        window.addEventListener('DOMContentLoaded', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    <?php endif; ?>

    // Toggle form pelatihan
    function toggleFormPelatihan() {
    const formSection = document.getElementById('formSection');
    const btn = document.getElementById('btnTambahPelatihan');

    if (formSection.classList.contains('show')) {
        formSection.classList.remove('show');
        btn.innerHTML = '<i class="fas fa-plus-circle"></i> Tambah Pelatihan';
    } else {
        formSection.classList.add('show');
        btn.innerHTML = '<i class="fas fa-times-circle"></i> Tutup Form';
        formSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}


    // Auto show form jika edit mode
    <?php if ($edit_mode): ?>
    window.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnTambahPelatihan').innerHTML = '<i class="fas fa-times-circle"></i> Tutup Form';
    });
    <?php endif; ?>
</script>