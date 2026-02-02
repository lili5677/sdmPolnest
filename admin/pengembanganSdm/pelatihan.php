<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah') {
    try {
        $judul_pelatihan = $_POST['judul_pelatihan'];
        $deskripsi = $_POST['deskripsi'] ?? null;
        $tanggal_mulai = $_POST['tanggal_mulai'];
        $tanggal_selesai = $_POST['tanggal_selesai'];
        $lokasi = $_POST['lokasi'];
        $instruktur = $_POST['instruktur'] ?? null;
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

        // Upload Flyer
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

        // Upload Undangan
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

            $undangan_name = 'undangan_' . time() . '_' . uniqid() . '.pdf';
            $undangan_path = $upload_dir . $undangan_name;

            if (move_uploaded_file($undangan['tmp_name'], $undangan_path)) {
                $path_undangan = 'uploads/pelatihan/' . $undangan_name;
            }
        }

        $metadata = json_encode([
            'deskripsi' => $deskripsi,
            'path_flyer' => $path_flyer,
            'path_undangan' => $path_undangan
        ]);

        $query = "INSERT INTO pelatihan (judul_pelatihan, deskripsi, tanggal_mulai, tanggal_selesai, lokasi, instruktur, created_by) 
                  VALUES (:judul, :deskripsi, :mulai, :selesai, :lokasi, :instruktur, :created_by)";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':judul' => $judul_pelatihan,
            ':deskripsi' => $metadata,
            ':mulai' => $tanggal_mulai,
            ':selesai' => $tanggal_selesai,
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

        if (strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
            header('Location: index.php?tab=pelatihan&status=error&message=' . urlencode('Tanggal selesai tidak boleh lebih awal dari tanggal mulai'));
            exit();
        }

        // Ambil data lama
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

        // Upload Flyer baru jika ada
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

            // Hapus file lama
            if ($path_flyer && file_exists('../../' . $path_flyer)) {
                unlink('../../' . $path_flyer);
            }

            $flyer_name = 'flyer_' . time() . '_' . uniqid() . '.' . $flyer_ext;
            $flyer_path = $upload_dir . $flyer_name;

            if (move_uploaded_file($flyer['tmp_name'], $flyer_path)) {
                $path_flyer = 'uploads/pelatihan/' . $flyer_name;
            }
        }

        // Upload Undangan baru jika ada
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

            // Hapus file lama
            if ($path_undangan && file_exists('../../' . $path_undangan)) {
                unlink('../../' . $path_undangan);
            }

            $undangan_name = 'undangan_' . time() . '_' . uniqid() . '.pdf';
            $undangan_path = $upload_dir . $undangan_name;

            if (move_uploaded_file($undangan['tmp_name'], $undangan_path)) {
                $path_undangan = 'uploads/pelatihan/' . $undangan_name;
            }
        }

        $metadata = json_encode([
            'deskripsi' => $deskripsi_text,
            'path_flyer' => $path_flyer,
            'path_undangan' => $path_undangan
        ]);

        $query = "UPDATE pelatihan SET 
            judul_pelatihan = :judul,
            deskripsi = :deskripsi,
            tanggal_mulai = :mulai,
            tanggal_selesai = :selesai,
            lokasi = :lokasi,
            instruktur = :instruktur
        WHERE pelatihan_id = :id";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':judul' => $judul_pelatihan,
            ':deskripsi' => $metadata,
            ':mulai' => $tanggal_mulai,
            ':selesai' => $tanggal_selesai,
            ':lokasi' => $lokasi,
            ':instruktur' => $instruktur,
            ':id' => $pelatihan_id
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
            
            if (isset($metadata['path_flyer']) && $metadata['path_flyer'] && file_exists('../../' . $metadata['path_flyer'])) {
                unlink('../../' . $metadata['path_flyer']);
            }
            
            if (isset($metadata['path_undangan']) && $metadata['path_undangan'] && file_exists('../../' . $metadata['path_undangan'])) {
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
// QUERY DATA
// =====================================================

$query_pelatihan = "SELECT * FROM pelatihan ORDER BY created_at DESC";
$stmt_pelatihan = $conn->prepare($query_pelatihan);
$stmt_pelatihan->execute();
$pelatihan_data = $stmt_pelatihan->fetchAll(PDO::FETCH_ASSOC);

// Cek mode edit
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
        }
    }
}
?>

<style>
    /* Page Header */
    .page-header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-header-section h2 {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 4px 0;
    }

    .page-header-section p {
        font-size: 14px;
        color: #64748b;
        margin: 0;
    }

    /* Tombol Tambah (Pojok Kanan) */
    .btn-tambah-pelatihan {
        padding: 12px 24px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        transition: all 0.3s ease;
    }

    .btn-tambah-pelatihan:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    /* Form Section (Tersembunyi) */
    .form-section-pelatihan {
        display: none;
        background: white;
        border-radius: 12px;
        padding: 32px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 2px solid #3b82f6;
        animation: slideDown 0.3s ease-out;
    }

    .form-section-pelatihan.show {
        display: block;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-section-pelatihan h3 {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 24px;
        text-align: center;
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
        color: #1e293b;
        margin-bottom: 6px;
    }

    .form-group label .required {
        color: #dc2626;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
    }

    .form-group textarea {
        min-height: 80px;
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
        color: #64748b;
        margin-top: 4px;
    }

    .form-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
        margin-top: 24px;
    }

    .btn-submit {
        padding: 10px 28px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-cancel {
        padding: 10px 28px;
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-cancel:hover {
        background: #e2e8f0;
    }

    /* Table Pelatihan */
    .table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .table-pelatihan {
        width: 100%;
        border-collapse: collapse;
    }

    .table-pelatihan thead {
        background: #f8fafc;
    }

    .table-pelatihan th {
        padding: 14px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .table-pelatihan td {
        padding: 16px;
        font-size: 14px;
        color: #1e293b;
        border-bottom: 1px solid #f1f5f9;
    }

    .table-pelatihan tbody tr:hover {
        background: #f8fafc;
    }

    .table-pelatihan tbody tr:last-child td {
        border-bottom: none;
    }

    /* Action Buttons */
    .btn-action {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-right: 6px;
    }

    .btn-view {
        background: #dbeafe;
        color: #1e40af;
    }

    .btn-view:hover {
        background: #bfdbfe;
    }

    .btn-edit {
        background: #fef3c7;
        color: #92400e;
    }

    .btn-edit:hover {
        background: #fde68a;
    }

    .btn-delete {
        background: #fee2e2;
        color: #991b1b;
    }

    .btn-delete:hover {
        background: #fecaca;
    }

    /* Empty State */
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

    /* Modal Detail */
    .modal-detail {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s ease;
    }

    .modal-detail.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content-detail {
        background-color: white;
        padding: 32px;
        border-radius: 12px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header-detail {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e2e8f0;
    }

    .modal-header-detail h3 {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .btn-close-modal {
        background: #fee2e2;
        color: #991b1b;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .btn-close-modal:hover {
        background: #fecaca;
    }

    .detail-row {
        margin-bottom: 16px;
    }

    .detail-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .detail-value {
        font-size: 14px;
        color: #1e293b;
        font-weight: 500;
    }
</style>

<div class="content-card">
    <!-- Page Header dengan Tombol di Kanan -->
    <div class="page-header-section">
        <div>
            <h2>Manajemen Pelatihan</h2>
            <p>Kelola data pelatihan pegawai</p>
        </div>
        <button class="btn-tambah-pelatihan" onclick="toggleFormPelatihan()" id="btnToggleForm">
            <i class="fas fa-plus-circle"></i>
            <span id="btnText">Tambah Pelatihan</span>
        </button>
    </div>

    <!-- Form Tambah/Edit Pelatihan (Tersembunyi) -->
    <div class="form-section-pelatihan <?php echo $edit_mode ? 'show' : ''; ?>" id="formPelatihan">
        <h3><?php echo $edit_mode ? 'Edit Pelatihan' : 'Form Tambah Pelatihan Baru'; ?></h3>
        <form method="POST" enctype="multipart/form-data" id="pelatihanForm">
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
                    <textarea name="deskripsi" placeholder="Deskripsi singkat tentang pelatihan..."><?php echo $edit_mode ? htmlspecialchars($edit_data['deskripsi_text'] ?? '') : ''; ?></textarea>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Flyer Pelatihan (JPG/PNG, Max 3MB)</label>
                    <input type="file" name="flyer" accept=".jpg,.jpeg,.png">
                    <small><?php echo $edit_mode && !empty($edit_data['path_flyer']) ? 'File saat ini: ' . basename($edit_data['path_flyer']) : 'Opsional - Upload flyer pelatihan'; ?></small>
                </div>
                <div class="form-group">
                    <label>Undangan PDF (Max 5MB)</label>
                    <input type="file" name="undangan" accept=".pdf">
                    <small><?php echo $edit_mode && !empty($edit_data['path_undangan']) ? 'File saat ini: ' . basename($edit_data['path_undangan']) : 'Opsional - Upload surat undangan'; ?></small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Update' : 'Simpan'; ?>
                </button>
                <button type="button" class="btn-cancel" onclick="cancelForm()">
                    <i class="fas fa-times"></i> Batal
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel Pelatihan -->
    <div class="table-container">
        <?php if (count($pelatihan_data) > 0): ?>
        <table class="table-pelatihan">
            <thead>
                <tr>
                    <th>NO</th>
                    <th>JUDUL PELATIHAN</th>
                    <th>TANGGAL</th>
                    <th>TEMPAT</th>
                    <th>INSTRUKTUR</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($pelatihan_data as $row): 
                    $tanggal_mulai = date('d/m/Y', strtotime($row['tanggal_mulai']));
                    $tanggal_selesai = date('d/m/Y', strtotime($row['tanggal_selesai']));
                    $metadata = json_decode($row['deskripsi'], true);
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['judul_pelatihan']); ?></strong></td>
                    <td><?php echo $tanggal_mulai . ' - ' . $tanggal_selesai; ?></td>
                    <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                    <td><?php echo htmlspecialchars($row['instruktur'] ?? '-'); ?></td>
                    <td>
                        <button class="btn-action btn-view" onclick='viewPelatihan(<?php echo json_encode($row); ?>, <?php echo json_encode($metadata); ?>)' title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-action btn-edit" onclick="editPelatihan(<?php echo $row['pelatihan_id']; ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" onclick="deletePelatihan(<?php echo $row['pelatihan_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['judul_pelatihan'])); ?>')" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <p>Belum ada data pelatihan</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Detail -->
<div class="modal-detail" id="modalDetail">
    <div class="modal-content-detail">
        <div class="modal-header-detail">
            <h3>Detail Pelatihan</h3>
            <button class="btn-close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<script>
    // Toggle Form
    function toggleFormPelatihan() {
        const form = document.getElementById('formPelatihan');
        const btnText = document.getElementById('btnText');
        
        if (form.classList.contains('show')) {
            form.classList.remove('show');
            btnText.textContent = 'Tambah Pelatihan';
        } else {
            form.classList.add('show');
            btnText.textContent = 'Tutup Form';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Cancel Form
    function cancelForm() {
        if (confirm('Batalkan pengisian form?')) {
            window.location.href = 'index.php?tab=pelatihan';
        }
    }

    // View Detail
    function viewPelatihan(data, metadata) {
        const tanggalMulai = new Date(data.tanggal_mulai).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
        const tanggalSelesai = new Date(data.tanggal_selesai).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
        
        let html = `
            <div class="detail-row">
                <div class="detail-label">Judul Pelatihan</div>
                <div class="detail-value">${data.judul_pelatihan}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Tanggal Pelaksanaan</div>
                <div class="detail-value">${tanggalMulai} - ${tanggalSelesai}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Tempat</div>
                <div class="detail-value">${data.lokasi}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Instruktur</div>
                <div class="detail-value">${data.instruktur || '-'}</div>
            </div>
        `;
        
        if (metadata && metadata.deskripsi) {
            html += `
                <div class="detail-row">
                    <div class="detail-label">Deskripsi</div>
                    <div class="detail-value">${metadata.deskripsi}</div>
                </div>
            `;
        }
        
        if (metadata && metadata.path_flyer) {
            html += `
                <div class="detail-row">
                    <div class="detail-label">Flyer</div>
                    <div class="detail-value"><a href="../../${metadata.path_flyer}" target="_blank">Lihat Flyer</a></div>
                </div>
            `;
        }
        
        if (metadata && metadata.path_undangan) {
            html += `
                <div class="detail-row">
                    <div class="detail-label">Undangan</div>
                    <div class="detail-value"><a href="../../${metadata.path_undangan}" target="_blank">Lihat Undangan PDF</a></div>
                </div>
            `;
        }
        
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('modalDetail').classList.add('show');
    }

    // Close Modal
    function closeModal() {
        document.getElementById('modalDetail').classList.remove('show');
    }

    // Edit Pelatihan
    function editPelatihan(id) {
        window.location.href = 'index.php?tab=pelatihan&edit=1&id=' + id;
    }

    // Delete Pelatihan
    function deletePelatihan(id, judul) {
        Swal.fire({
            title: 'Hapus Pelatihan?',
            html: `Apakah Anda yakin ingin menghapus:<br><strong>${judul}</strong>`,
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

    // Validasi Form
    document.getElementById('pelatihanForm')?.addEventListener('submit', function(e) {
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

    // Auto show form dan ubah text tombol jika edit mode
    <?php if ($edit_mode): ?>
    window.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnText').textContent = 'Tutup Form';
        document.getElementById('formPelatihan').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    <?php endif; ?>

    // Close modal when clicking outside
    document.getElementById('modalDetail')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>