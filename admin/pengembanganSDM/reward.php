<?php

$query_pegawai = "SELECT pegawai_id, nama_lengkap, nik FROM pegawai ORDER BY nama_lengkap ASC";
$stmt_pegawai = $conn->prepare($query_pegawai);
$stmt_pegawai->execute();
$pegawai_list = $stmt_pegawai->fetchAll(PDO::FETCH_ASSOC);

$query_rewards = "SELECT r.*, p.nama_lengkap, p.nik 
                  FROM reward_pegawai r
                  LEFT JOIN pegawai p ON r.pegawai_id = p.pegawai_id
                  ORDER BY r.created_at DESC";
$stmt_rewards = $conn->prepare($query_rewards);
$stmt_rewards->execute();
$reward_data = $stmt_rewards->fetchAll(PDO::FETCH_ASSOC);

$edit_mode = false;
$edit_data = null;

if (isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_mode = true;
    $edit_id = (int)$_GET['id'];

    $stmt_edit = $conn->prepare("SELECT * FROM reward_pegawai WHERE reward_id=:id");
    $stmt_edit->execute([':id'=>$edit_id]);
    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>

<style>
    .reward-container {
        width: 100%;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
    }

    .form-container {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        display: none;
        transition: all 0.3s ease;
    }

    .form-container.show {
        display: block;
        animation: slideDown 0.3s ease;
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

    .form-title {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e2e8f0;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group-full {
        grid-column: 1 / -1;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-label {
        font-size: 14px;
        font-weight: 600;
        color: #475569;
    }

    .form-label .required {
        color: #ef4444;
        margin-left: 2px;
    }

    .form-input,
    .form-select,
    .form-textarea {
        padding: 12px 16px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
        background: white;
    }

    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-select {
        cursor: pointer;
    }

    .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-submit {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        padding: 12px 32px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
    }

    .btn-cancel {
        background: #64748b;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-cancel:hover {
        background: #475569;
    }

    .table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .data-table thead th {
        padding: 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.5px;
    }

    .data-table tbody tr {
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.2s;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .data-table tbody td {
        padding: 16px;
        font-size: 14px;
        color: #334155;
    }

    .btn-action {
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
        margin-right: 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-view {
        background: #3b82f6;
        color: white;
    }

    .btn-view:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }

    .btn-edit {
        background: #10b981;
        color: white;
    }

    .btn-edit:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    .btn-delete {
        background: #ef4444;
        color: white;
    }

    .btn-delete:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state p {
        font-size: 16px;
        margin: 0;
    }

    .modal-detail {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .modal-detail.show {
        display: flex;
    }

    .modal-content-detail {
        background: white;
        border-radius: 12px;
        max-width: 900px;
        width: 95%;
        max-height: 95vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .modal-content-detail::-webkit-scrollbar {
        width: 8px;
    }

    .modal-content-detail::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 0 12px 12px 0;
    }

    .modal-content-detail::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .modal-content-detail::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .modal-header-detail {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 2px solid #e2e8f0;
    }

    .modal-header-detail h3 {
        font-size: 20px;
        color: #1e293b;
        margin: 0;
    }

    .btn-close-modal {
        background: #ef4444;
        color: white;
        border: none;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-body {
        padding: 20px;
    }

    .detail-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #e2e8f0;
        flex-direction: column;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-weight: 600;
        color: #64748b;
        margin-bottom: 6px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        color: #1e293b;
        flex: 1;
        font-size: 14px;
        line-height: 1.6;
    }
    
    
    @media (max-width: 968px) {
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .section-title {
            font-size: 18px;
        }
        
        .btn-primary {
            width: 100%;
            justify-content: center;
            padding: 10px 20px;
            font-size: 13px;
        }
        
        .form-container {
            padding: 20px 16px;
        }
        
        .form-title {
            font-size: 16px;
        }
        
        .form-grid {
            grid-template-columns: 1fr !important;
            gap: 16px;
        }
        
        .form-group label {
            font-size: 13px;
        }
        
        .form-control, .form-select, .form-textarea {
            font-size: 14px;
            padding: 10px 12px;
        }
        
        .form-actions {
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-submit, .btn-cancel {
            width: 100%;
            justify-content: center;
            padding: 10px 20px;
        }
        
        .table-container {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            display: block !important;
        }
        
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        
        .data-table {
            min-width: 800px !important;
            width: 100%;
        }
        
        .data-table thead th {
            padding: 12px;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .data-table tbody td {
            padding: 12px;
            font-size: 13px;
            white-space: nowrap;
        }
        
        .btn-action {
            padding: 6px 10px;
            font-size: 12px;
            margin-right: 4px;
        }
        
        /* Modal */
        .modal-content-detail {
            width: 95%;
            max-width: 100%;
            margin: 10px;
        }
        
        .modal-header-detail {
            padding: 16px;
        }
        
        .modal-header-detail h3 {
            font-size: 16px;
        }
        
        .modal-body {
            padding: 16px;
        }
        
        .detail-row {
            flex-direction: column;
            gap: 8px;
            padding: 12px 0;
        }
        
        .detail-label {
            font-size: 12px;
        }
        
        .detail-value {
            font-size: 13px;
        }
    }
    
    @media (max-width: 480px) {
        .section-title {
            font-size: 16px;
        }
        
        .btn-primary {
            font-size: 12px;
            padding: 8px 16px;
        }
        
        .form-container {
            padding: 16px 12px;
            border-radius: 8px;
        }
        
        .form-title {
            font-size: 15px;
        }
        
        .form-group label {
            font-size: 12px;
        }
        
        .form-control, .form-select, .form-textarea {
            font-size: 13px;
            padding: 8px 10px;
        }
        
        .btn-submit, .btn-cancel {
            font-size: 12px;
            padding: 8px 16px;
        }
        
        .data-table {
            min-width: 700px !important;
        }
        
        .data-table thead th {
            padding: 10px 8px;
            font-size: 11px;
        }
        
        .data-table tbody td {
            padding: 10px 8px;
            font-size: 12px;
        }
        
        .btn-action {
            padding: 5px 8px;
            font-size: 11px;
            margin-right: 3px;
        }
        
        .btn-action i {
            font-size: 12px;
        }
        
        .modal-content-detail {
            margin: 5px;
            border-radius: 8px;
        }
        
        .modal-header-detail {
            padding: 12px;
        }
        
        .modal-header-detail h3 {
            font-size: 15px;
        }
        
        .modal-body {
            padding: 12px;
        }
        
        .detail-row {
            padding: 10px 0;
        }
        
        .detail-label {
            font-size: 11px;
        }
        
        .detail-value {
            font-size: 12px;
        }

        .empty-state {
            padding: 40px 15px;
        }
        
        .empty-state i {
            font-size: 48px;
        }
        
        .empty-state p {
            font-size: 14px;
        }
    }
    
    @media (max-width: 375px) {
        .section-title {
            font-size: 15px;
        }
        
        .form-container {
            padding: 14px 10px;
        }
        
        .data-table {
            min-width: 650px !important;
        }
        
        .data-table thead th,
        .data-table tbody td {
            padding: 8px 6px;
            font-size: 11px;
        }
        
        .btn-action {
            padding: 4px 6px;
            font-size: 10px;
        }
    }
</style>

<div class="reward-container">
    <!-- Header -->
    <div class="section-header">
        <div>
            <h2 class="section-title">Manajemen Reward Pegawai</h2>
            <p style="color: #64748b; font-size: 14px;">Kelola data reward dan penghargaan pegawai</p>
        </div>
        <button class="btn-primary" onclick="toggleFormReward()" id="btnToggleForm">
            <i class="fas fa-plus-circle"></i>
            <span id="btnText">Tambah Reward</span>
        </button>
    </div>

    <!-- Form Tambah/Edit -->
    <div class="form-container <?php echo $edit_mode ? 'show' : ''; ?>" id="formReward">
        <h3 class="form-title">
            <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Reward' : 'Tambah Reward Baru'; ?>
        </h3>

        <form method="POST" action="prosesreward.php" id="rewardForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'tambah'; ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="reward_id" value="<?php echo $edit_data['reward_id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <!-- Pegawai -->
                <div class="form-group form-group-full">
                    <label class="form-label">
                        Pegawai <span class="required">*</span>
                    </label>
                    <select name="pegawai_id" class="form-select" required>
                        <option value="">-- Pilih Pegawai --</option>
                        <?php foreach ($pegawai_list as $pegawai): ?>
                            <option value="<?php echo $pegawai['pegawai_id']; ?>"
                                    <?php echo ($edit_mode && $edit_data['pegawai_id'] == $pegawai['pegawai_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pegawai['nama_lengkap']) . ' - ' . $pegawai['nik']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Judul Reward -->
                <div class="form-group form-group-full">
                    <label class="form-label">
                        Judul Reward <span class="required">*</span>
                    </label>
                    <input type="text" name="judul_reward" class="form-input" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['judul_reward']) : ''; ?>" 
                           placeholder="Contoh: Pegawai Terbaik 2024" required>
                </div>

                <!-- Kategori -->
                <div class="form-group">
                    <label class="form-label">
                        Kategori <span class="required">*</span>
                    </label>
                    <select name="kategori" class="form-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <option value="Prestasi Kerja" <?php echo ($edit_mode && $edit_data['kategori'] == 'Prestasi Kerja') ? 'selected' : ''; ?>>Prestasi Kerja</option>
                        <option value="Inovasi" <?php echo ($edit_mode && $edit_data['kategori'] == 'Inovasi') ? 'selected' : ''; ?>>Inovasi</option>
                        <option value="Dedikasi" <?php echo ($edit_mode && $edit_data['kategori'] == 'Dedikasi') ? 'selected' : ''; ?>>Dedikasi</option>
                        <option value="Kehadiran" <?php echo ($edit_mode && $edit_data['kategori'] == 'Kehadiran') ? 'selected' : ''; ?>>Kehadiran</option>
                        <option value="Lainnya" <?php echo ($edit_mode && $edit_data['kategori'] == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                    </select>
                </div>

                <!-- Tanggal Reward -->
                <div class="form-group">
                    <label class="form-label">
                        Tanggal Reward <span class="required">*</span>
                    </label>
                    <input type="date" name="tanggal_reward" class="form-input" 
                           value="<?php echo $edit_mode ? $edit_data['tanggal_reward'] : date('Y-m-d'); ?>" required>
                </div>

                <!-- Deskripsi -->
                <div class="form-group form-group-full">
                    <label class="form-label">
                        Deskripsi
                    </label>
                    <textarea name="deskripsi" class="form-textarea" 
                              placeholder="Deskripsi reward..."><?php echo $edit_mode ? htmlspecialchars($edit_data['deskripsi']) : ''; ?></textarea>
                </div>

                <!-- Upload Bukti Reward -->
                <div class="form-group form-group-full">
                    <label class="form-label">
                        Upload Bukti (Gambar/PDF) - Max 5MB
                    </label>

                    <?php if ($edit_mode && !empty($edit_data['file_bukti'])): ?>
                        <div style="margin-bottom:12px;padding:12px;background:#f1f5f9;border-radius:8px;border:1px solid #e2e8f0;">
                            <p style="margin:0 0 8px 0;font-size:13px;font-weight:600;color:#475569;">
                                <i class="fas fa-file"></i> File saat ini:
                            </p>
                            <?php 
                            $current_file = $edit_data['file_bukti'];
                            $file_ext = strtolower(pathinfo($current_file, PATHINFO_EXTENSION));
                            $file_path = '/uploads/reward/' . $current_file;
                            ?>
                            
                            <?php if ($file_ext === 'pdf'): ?>
                                <!-- Preview PDF -->
                                <div style="display:flex;gap:8px;margin-bottom:8px;">
                                    <a href="<?php echo $file_path; ?>" target="_blank" class="btn-action btn-view" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:12px;">
                                        <i class="fas fa-file-pdf"></i> Lihat PDF
                                    </a>
                                    <a href="<?php echo $file_path; ?>" download class="btn-action btn-edit" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:12px;">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                                <embed src="<?php echo $file_path; ?>" type="application/pdf" width="100%" height="300px" style="border-radius:6px;border:1px solid #cbd5e1;">
                            <?php else: ?>
                                <!-- Preview Gambar -->
                                <div style="display:flex;gap:8px;margin-bottom:8px;">
                                    <a href="<?php echo $file_path; ?>" target="_blank" class="btn-action btn-view" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:12px;">
                                        <i class="fas fa-external-link-alt"></i> Buka
                                    </a>
                                    <a href="<?php echo $file_path; ?>" download class="btn-action btn-edit" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;font-size:12px;">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                                <img src="<?php echo $file_path; ?>" alt="Preview" style="max-width:100%;height:auto;border-radius:6px;border:1px solid #cbd5e1;cursor:pointer;" onclick="window.open('<?php echo $file_path; ?>', '_blank')">
                            <?php endif; ?>
                            
                            <p style="margin:8px 0 0 0;font-size:12px;color:#64748b;">
                                <i class="fas fa-info-circle"></i> Upload file baru untuk mengganti file yang ada
                            </p>
                        </div>
                    <?php endif; ?>

                    <input 
                        type="file" 
                        name="file_bukti" 
                        id="fileBuktiInput"
                        class="form-input"
                        accept=".jpg,.jpeg,.png,.pdf"
                        onchange="previewFile(this)"
                    >

                    <small style="color:#64748b;font-size:12px;">
                        Format: JPG, PNG, PDF. Maksimal 5MB
                    </small>
                    
                    <!-- Preview Area untuk file baru -->
                    <div id="previewArea" style="display:none;margin-top:12px;padding:12px;background:#f8fafc;border-radius:8px;border:2px dashed #3b82f6;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <p style="margin:0;font-size:13px;font-weight:600;color:#1e293b;">
                                <i class="fas fa-eye"></i> Preview File Baru:
                            </p>
                            <button type="button" onclick="clearFilePreview()" class="btn-action btn-delete" style="font-size:11px;padding:4px 8px;">
                                <i class="fas fa-times"></i> Hapus
                            </button>
                        </div>
                        <div id="previewContent"></div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="cancelForm()">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> 
                    <?php echo $edit_mode ? 'Update Reward' : 'Simpan Reward'; ?>
                </button>
            </div>
            
        </form>
    </div>

    <div class="table-container">
        <?php if (count($reward_data) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Nama Pegawai</th>
                    <th>Judul Reward</th>
                    <th>Kategori</th>
                    <th>Tanggal</th>
                    <th style="width: 180px; text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                foreach ($reward_data as $row): 
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><strong><?php echo htmlspecialchars($row['nama_lengkap'] ?? 'N/A'); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['judul_reward']); ?></td>
                    <td><?php echo htmlspecialchars($row['kategori'] ?? '-'); ?></td>
                    <td><?php echo $row['tanggal_reward'] ? date('d/m/Y', strtotime($row['tanggal_reward'])) : '-'; ?></td>
                    <td style="text-align: center;">
                        <button class="btn-action btn-view" 
                                onclick='viewReward(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' 
                                title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-action btn-edit" 
                                onclick="editReward(<?php echo $row['reward_id']; ?>)" 
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" 
                                onclick="deleteReward(<?php echo $row['reward_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['judul_reward'])); ?>')" 
                                title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-award"></i>
            <p>Belum ada data reward</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-detail" id="modalDetail">
    <div class="modal-content-detail">
        <div class="modal-header-detail">
            <h3>Detail Reward</h3>
            <button class="btn-close-modal" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<script>
    function toggleFormReward() {
        const form = document.getElementById('formReward');
        const btnText = document.getElementById('btnText');
        
        if (form.classList.contains('show')) {
            form.classList.remove('show');
            btnText.textContent = 'Tambah Reward';
        } else {
            form.classList.add('show');
            btnText.textContent = 'Tutup Form';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Cancel Form 
    function cancelForm() {
        Swal.fire({
            title: 'Batalkan Pengisian?',
            text: 'Data yang telah diisi akan hilang',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Batalkan',
            cancelButtonText: '<i class="fas fa-times"></i> Tidak',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'pengembangan-sdm.php?tab=reward';
            }
        });
    }

    // View Detail
    function viewReward(data) {
        console.log('=== DEBUG VIEW REWARD ===');
        console.log('Full data:', data);
        console.log('file_bukti value:', data.file_bukti);
        console.log('file_bukti type:', typeof data.file_bukti);
        console.log('file_bukti exists:', data.file_bukti ? 'YES' : 'NO');
        console.log('========================');
        
        // Cek apakah ada file bukti
        let fileBuktiHtml = '';
        if (data.file_bukti && data.file_bukti.trim() !== '' && data.file_bukti !== 'null') {
            const fileExt = data.file_bukti.split('.').pop().toLowerCase();
            const filePath = '/sdmPolnest/uploads/reward/' + data.file_bukti;
            const fileName = data.file_bukti;
            
            console.log('File detected!');
            console.log('Extension:', fileExt);
            console.log('Full path:', filePath);
            console.log('File name:', fileName);
            
            if (fileExt === 'pdf') {
                // Jika PDF
                fileBuktiHtml = `
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-file-pdf"></i> Bukti Dokumen
                        </div>
                        <div class="detail-value">
                            <div style="background: #fef2f2; border: 2px solid #fecaca; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <i class="fas fa-file-pdf" style="font-size: 32px; color: #ef4444;"></i>
                                    <div style="flex: 1;">
                                        <p style="margin: 0; font-weight: 600; color: #1e293b; font-size: 14px;">${fileName}</p>
                                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">Dokumen PDF</p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="${filePath}" target="_blank" style="flex: 1; text-decoration: none;">
                                        <button class="btn-action btn-view" style="width: 100%; justify-content: center;">
                                            <i class="fas fa-external-link-alt"></i> Buka di Tab Baru
                                        </button>
                                    </a>
                                    <a href="${filePath}" download="${fileName}" style="flex: 1; text-decoration: none;">
                                        <button class="btn-action btn-edit" style="width: 100%; justify-content: center;">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                    </a>
                                </div>
                            </div>
                            <iframe src="${filePath}" width="100%" height="500px" style="border: 1px solid #e2e8f0; border-radius: 8px;"></iframe>
                        </div>
                    </div>
                `;
            } else if (fileExt === 'png' || fileExt === 'jpg' || fileExt === 'jpeg' || fileExt === 'gif') {
                // Jika gambar
                fileBuktiHtml = `
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-image"></i> Bukti Dokumen
                        </div>
                        <div class="detail-value">
                            <div style="background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <i class="fas fa-image" style="font-size: 32px; color: #10b981;"></i>
                                    <div style="flex: 1;">
                                        <p style="margin: 0; font-weight: 600; color: #1e293b; font-size: 14px;">${fileName}</p>
                                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">File Gambar (${fileExt.toUpperCase()})</p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="${filePath}" target="_blank" style="flex: 1; text-decoration: none;">
                                        <button class="btn-action btn-view" style="width: 100%; justify-content: center;">
                                            <i class="fas fa-external-link-alt"></i> Buka di Tab Baru
                                        </button>
                                    </a>
                                    <a href="${filePath}" download="${fileName}" style="flex: 1; text-decoration: none;">
                                        <button class="btn-action btn-edit" style="width: 100%; justify-content: center;">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                    </a>
                                </div>
                            </div>
                            <div style="text-align: center; background: #f8fafc; padding: 8px; border-radius: 8px;">
                                <img src="${filePath}" 
                                     alt="Bukti Reward" 
                                     style="max-width: 100%; height: auto; border-radius: 8px; border: 2px solid #e2e8f0; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1);" 
                                     onclick="window.open('${filePath}', '_blank')"
                                     onerror="this.parentElement.innerHTML='<div style=\\'padding:40px;background:#fee;border-radius:8px;\\'><i class=\\'fas fa-exclamation-triangle\\'style=\\'color:#ef4444;font-size:48px;\\'></i><p style=\\'color:#ef4444;margin-top:12px;\\'><strong>Gambar tidak dapat dimuat</strong></p><p style=\\'color:#64748b;font-size:12px;margin-top:8px;\\'>Path: ${filePath}</p><p style=\\'color:#64748b;font-size:11px;\\'>Pastikan file ada di folder uploads/reward/</p></div>'">
                                <p style="margin: 8px 0 0 0; font-size: 12px; color: #64748b;">
                                    <i class="fas fa-info-circle"></i> Klik gambar untuk memperbesar
                                </p>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Format file tidak dikenali
                fileBuktiHtml = `
                    <div class="detail-row">
                        <div class="detail-label">
                            <i class="fas fa-file"></i> Bukti Dokumen
                        </div>
                        <div class="detail-value">
                            <div style="background: #fef9c3; border: 2px solid #fde047; border-radius: 8px; padding: 16px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <i class="fas fa-file" style="font-size: 32px; color: #eab308;"></i>
                                    <div style="flex: 1;">
                                        <p style="margin: 0; font-weight: 600; color: #1e293b; font-size: 14px;">${fileName}</p>
                                        <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">Format: ${fileExt.toUpperCase()}</p>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <a href="${filePath}" target="_blank" style="flex: 1; text-decoration: none;">
                                        <button class="btn-action btn-view" style="width: 100%; justify-content: center;">
                                            <i class="fas fa-external-link-alt"></i> Buka File
                                        </button>
                                    </a>
                                    <a href="${filePath}" download="${fileName}" style="flex: 1; text-decoration: none;">
                                        <button class="btn-action btn-edit" style="width: 100%; justify-content: center;">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
        } else {
            console.log('No file detected or file is null/empty');
            fileBuktiHtml = `
                <div class="detail-row">
                    <div class="detail-label">
                        <i class="fas fa-file"></i> Bukti Dokumen
                    </div>
                    <div class="detail-value">
                        <div style="text-align: center; padding: 40px; background: #f8fafc; border-radius: 8px; border: 2px dashed #cbd5e1;">
                            <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e1; margin-bottom: 12px;"></i>
                            <p style="margin: 0; color: #94a3b8; font-style: italic;">Tidak ada dokumen bukti</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        let html = `
            <div class="detail-row">
                <div class="detail-label">
                    <i class="fas fa-user"></i> Nama Pegawai
                </div>
                <div class="detail-value"><strong>${data.nama_lengkap || 'N/A'}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">
                    <i class="fas fa-id-card"></i> NIK
                </div>
                <div class="detail-value">${data.nik || '-'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">
                    <i class="fas fa-award"></i> Judul Reward
                </div>
                <div class="detail-value"><strong style="font-size: 16px; color: #1e293b;">${data.judul_reward}</strong></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">
                    <i class="fas fa-tag"></i> Kategori
                </div>
                <div class="detail-value">
                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 6px 16px; border-radius: 16px; font-size: 13px; font-weight: 600; display: inline-block;">
                        ${data.kategori || '-'}
                    </span>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">
                    <i class="fas fa-calendar"></i> Tanggal
                </div>
                <div class="detail-value">${data.tanggal_reward ? new Date(data.tanggal_reward).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-'}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">
                    <i class="fas fa-align-left"></i> Deskripsi
                </div>
                <div class="detail-value" style="line-height: 1.6;">${data.deskripsi || '<em style="color: #94a3b8;">Tidak ada deskripsi</em>'}</div>
            </div>
            ${fileBuktiHtml}
        `;
        
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('modalDetail').classList.add('show');
    }

    document.getElementById("rewardForm").addEventListener("submit", function(e) {
        const fileInput = document.querySelector("input[name='file_bukti']");
        
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB

            if (file.size > maxSize) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'File Terlalu Besar!',
                    text: 'Maksimal ukuran file adalah 5MB',
                    confirmButtonColor: '#3b82f6',
                    confirmButtonText: '<i class="fas fa-check"></i> OK'
                });
            }
        }
    });

    function closeModal() {
        document.getElementById('modalDetail').classList.remove('show');
    }

    // Edit Reward
    function editReward(id) {
        window.location.href = 'pengembangan-sdm.php?tab=reward&edit=1&id=' + id;
    }

    // Delete Reward 
    function deleteReward(id, judul) {
        Swal.fire({
            title: 'Hapus Reward?',
            html: `Apakah Anda yakin ingin menghapus reward:<br><strong>"${judul}"</strong><br><br>Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus!',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            reverseButtons: true,
            focusCancel: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                window.location.href = 'prosesreward.php?action=hapus&id=' + id;
            }
        });
    }

    <?php if ($edit_mode): ?>
    window.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnText').textContent = 'Tutup Form';
        document.getElementById('formReward').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    <?php endif; ?>

    document.getElementById('modalDetail')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

   function previewFile(input) {
    const previewArea = document.getElementById('previewArea');
    const previewContent = document.getElementById('previewContent');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; 

        // Validasi size
        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File Terlalu Besar!',
                text: 'Maksimal 5MB',
            });
            input.value = '';
            previewArea.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        const ext = file.name.split('.').pop().toLowerCase();

        reader.onload = function(e) {

            if (ext === 'pdf') {
                previewContent.innerHTML = `
                    <p style="font-weight:600">${file.name}</p>
                    <iframe src="${e.target.result}" width="100%" height="300"></iframe>
                `;
            } else {
                previewContent.innerHTML = `
                    <p style="font-weight:600">${file.name}</p>
                    <img src="${e.target.result}" style="max-width:100%;border-radius:8px">
                `;
            }

            previewArea.style.display = 'block';
        };

        reader.readAsDataURL(file);
    }
}

function clearFilePreview() {
    document.getElementById('fileBuktiInput').value = '';
    document.getElementById('previewArea').style.display = 'none';
}

    
    function clearFilePreview() {
        Swal.fire({
            title: 'Hapus File?',
            text: 'File yang dipilih akan dihapus',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-check"></i> Ya, Hapus',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('fileBuktiInput').value = '';
                document.getElementById('previewArea').style.display = 'none';
                document.getElementById('previewContent').innerHTML = '';
                
                Swal.fire({
                    icon: 'success',
                    title: 'File Dihapus',
                    text: 'Preview file telah dihapus',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        });
    }
</script>