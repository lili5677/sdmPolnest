<?php
// =====================================================
// REWARD - DISPLAY ONLY (NO HANDLERS)
// =====================================================

// AMBIL DATA REWARD
$query_rewards = "SELECT * FROM reward_pegawai ORDER BY created_at DESC";
$stmt_rewards = $conn->prepare($query_rewards);
$stmt_rewards->execute();
$reward_data = $stmt_rewards->fetchAll(PDO::FETCH_ASSOC);

// CEK EDIT MODE
$edit_mode = false;
$edit_data = null;

if (isset($_GET['edit']) && isset($_GET['id'])) {
    $edit_mode = true;
    $edit_id = (int)$_GET['id'];
    
    $query_edit = "SELECT * FROM reward_pegawai WHERE reward_id = :id";
    $stmt_edit = $conn->prepare($query_edit);
    $stmt_edit->execute([':id' => $edit_id]);
    $edit_data = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>

<style>
    /* ===== CONTAINER ===== */
    .reward-container {
        width: 100%;
    }

    /* ===== HEADER ===== */
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

    /* ===== FORM ===== */
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
    .form-textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .char-counter {
        font-size: 12px;
        color: #64748b;
        text-align: right;
        margin-top: 4px;
    }

    .char-counter.warning {
        color: #f59e0b;
        font-weight: 600;
    }

    .char-counter.danger {
        color: #ef4444;
        font-weight: 600;
    }

    .file-upload-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
        width: 100%;
    }

    .file-upload-input {
        position: absolute;
        left: -9999px;
    }

    .file-upload-label {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border: 2px dashed #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #f8fafc;
    }

    .file-upload-label:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .file-upload-icon {
        font-size: 24px;
        color: #3b82f6;
    }

    .file-upload-text {
        flex: 1;
    }

    .file-upload-text strong {
        display: block;
        color: #1e293b;
        font-size: 14px;
        margin-bottom: 2px;
    }

    .file-upload-text span {
        display: block;
        color: #64748b;
        font-size: 12px;
    }

    .file-preview {
        margin-top: 12px;
        display: none;
    }

    .file-preview.show {
        display: block;
    }

    .file-preview img {
        max-width: 200px;
        max-height: 200px;
        border-radius: 8px;
        border: 2px solid #e2e8f0;
    }

    .current-image {
        margin-top: 8px;
    }

    .current-image img {
        max-width: 150px;
        max-height: 150px;
        border-radius: 8px;
        border: 2px solid #e2e8f0;
    }

    .current-image-label {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 8px;
        display: block;
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
        font-size: 14px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3);
    }

    .btn-cancel {
        background: #f1f5f9;
        color: #64748b;
        border: none;
        padding: 12px 32px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .btn-cancel:hover {
        background: #e2e8f0;
        color: #475569;
    }

    /* ===== TABLE ===== */
    .table-container {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow-x: auto;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: #f8fafc;
    }

    .data-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }

    .data-table td {
        padding: 14px 16px;
        font-size: 14px;
        color: #1e293b;
        border-bottom: 1px solid #f1f5f9;
    }

    .data-table tbody tr {
        transition: all 0.2s ease;
    }

    .data-table tbody tr:hover {
        background: #f8fafc;
    }

    .reward-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #e2e8f0;
    }

    .keterangan-cell {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* ===== BUTTONS ===== */
    .btn-action {
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 0 4px;
        font-size: 13px;
    }

    .btn-view {
        background: #eff6ff;
        color: #3b82f6;
    }

    .btn-view:hover {
        background: #3b82f6;
        color: white;
        transform: translateY(-2px);
    }

    .btn-edit {
        background: #fef3c7;
        color: #f59e0b;
    }

    .btn-edit:hover {
        background: #f59e0b;
        color: white;
        transform: translateY(-2px);
    }

    .btn-delete {
        background: #fee2e2;
        color: #ef4444;
    }

    .btn-delete:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-2px);
    }

    /* ===== EMPTY STATE ===== */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 16px;
        opacity: 0.3;
        color: #cbd5e1;
    }

    .empty-state p {
        font-size: 15px;
        color: #64748b;
    }

    /* ===== MODAL ===== */
    .modal-detail {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-detail.show {
        display: flex;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    .modal-content-detail {
        background: white;
        border-radius: 16px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header-detail {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
        border-bottom: 2px solid #e2e8f0;
    }

    .modal-header-detail h3 {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .btn-close-modal {
        background: #f1f5f9;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 18px;
    }

    .btn-close-modal:hover {
        background: #e2e8f0;
        color: #1e293b;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 24px;
    }

    .detail-image {
        text-align: center;
        margin-bottom: 24px;
    }

    .detail-image img {
        max-width: 100%;
        max-height: 400px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
    }

    .detail-row {
        margin-bottom: 20px;
    }

    .detail-label {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .detail-value {
        font-size: 15px;
        color: #1e293b;
        font-weight: 500;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .section-header {
            flex-direction: column;
            gap: 16px;
            align-items: flex-start;
        }

        .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="reward-container">
    <!-- Header -->
    <div class="section-header">
        <h2 class="section-title">Manajemen Reward</h2>
        <button class="btn-primary" onclick="toggleFormReward()">
            <i class="fas fa-plus"></i>
            <span id="btnText"><?php echo $edit_mode ? 'Tutup Form' : 'Tambah Reward'; ?></span>
        </button>
    </div>

    <!-- Form Tambah/Edit -->
    <div class="form-container <?php echo $edit_mode ? 'show' : ''; ?>" id="formReward">
        <h3 class="form-title">
            <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-plus-circle'; ?>"></i>
            <?php echo $edit_mode ? 'Edit Reward' : 'Tambah Reward Baru'; ?>
        </h3>

        <form method="POST" action="prosesreward.php" enctype="multipart/form-data" id="rewardForm">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'edit' : 'tambah'; ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="reward_id" value="<?php echo $edit_data['reward_id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <!-- Nama -->
                <div class="form-group">
                    <label class="form-label">
                        Nama <span class="required">*</span>
                    </label>
                    <input type="text" name="nama" class="form-input" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['nama']) : ''; ?>" 
                           placeholder="Masukkan nama penerima reward" required>
                </div>

                <!-- Jabatan -->
                <div class="form-group">
                    <label class="form-label">
                        Jabatan <span class="required">*</span>
                    </label>
                    <input type="text" name="jabatan" class="form-input" 
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['jabatan']) : ''; ?>" 
                           placeholder="Contoh: Dosen, Staff, Mahasiswa" required>
                </div>

                <!-- Keterangan -->
                <div class="form-group form-group-full">
                    <label class="form-label">
                        Keterangan <span class="required">*</span>
                    </label>
                    <textarea name="keterangan" class="form-textarea" id="keteranganInput" 
                              placeholder="Deskripsi singkat reward (maksimal 100 karakter)" 
                              maxlength="100" required><?php echo $edit_mode ? htmlspecialchars($edit_data['keterangan']) : ''; ?></textarea>
                    <div class="char-counter" id="charCounter">
                        <span id="charCount">0</span> / 100 karakter
                    </div>
                </div>

                <!-- Gambar -->
                <div class="form-group form-group-full">
                    <label class="form-label">
                        Gambar <span class="required">*</span>
                    </label>
                    <div class="file-upload-wrapper">
                        <input type="file" name="gambar" id="gambarInput" class="file-upload-input" 
                               accept="image/jpeg,image/jpg,image/png" 
                               <?php echo !$edit_mode ? 'required' : ''; ?>>
                        <label for="gambarInput" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                            <div class="file-upload-text">
                                <strong>Pilih Gambar</strong>
                                <span>JPG, JPEG, atau PNG (Maksimal 5MB)</span>
                            </div>
                        </label>
                    </div>
                    <div class="file-preview" id="filePreview">
                        <img id="previewImage" src="" alt="Preview">
                    </div>
                    <?php if ($edit_mode && $edit_data['gambar']): ?>
                        <div class="current-image">
                            <span class="current-image-label">Gambar saat ini:</span>
                            <img src="../../<?php echo $edit_data['gambar']; ?>" alt="Current">
                        </div>
                    <?php endif; ?>
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

    <!-- Tabel Data -->
    <div class="table-container">
        <?php if (count($reward_data) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th style="width: 100px;">Gambar</th>
                    <th>Nama</th>
                    <th>Jabatan</th>
                    <th>Keterangan</th>
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
                    <td>
                        <img src="../../<?php echo $row['gambar']; ?>" 
                             alt="<?php echo htmlspecialchars($row['nama']); ?>" 
                             class="reward-image">
                    </td>
                    <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['jabatan']); ?></td>
                    <td class="keterangan-cell" title="<?php echo htmlspecialchars($row['keterangan']); ?>">
                        <?php echo htmlspecialchars($row['keterangan']); ?>
                    </td>
                    <td style="text-align: center;">
                        <button class="btn-action btn-view" 
                                onclick='viewReward(<?php echo json_encode($row); ?>)' 
                                title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-action btn-edit" 
                                onclick="editReward(<?php echo $row['reward_id']; ?>)" 
                                title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-delete" 
                                onclick="deleteReward(<?php echo $row['reward_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['nama'])); ?>')" 
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

<!-- Modal Detail -->
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
    // Character Counter
    const keteranganInput = document.getElementById('keteranganInput');
    const charCounter = document.getElementById('charCounter');
    const charCount = document.getElementById('charCount');

    if (keteranganInput) {
        charCount.textContent = keteranganInput.value.length;
        updateCharCounterColor(keteranganInput.value.length);

        keteranganInput.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;
            updateCharCounterColor(length);
        });
    }

    function updateCharCounterColor(length) {
        charCounter.classList.remove('warning', 'danger');
        if (length >= 90) {
            charCounter.classList.add('danger');
        } else if (length >= 70) {
            charCounter.classList.add('warning');
        }
    }

    // Image Preview
    const gambarInput = document.getElementById('gambarInput');
    const filePreview = document.getElementById('filePreview');
    const previewImage = document.getElementById('previewImage');

    if (gambarInput) {
        gambarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar!',
                        text: 'Ukuran gambar maksimal 5MB',
                        confirmButtonColor: '#3b82f6'
                    });
                    gambarInput.value = '';
                    filePreview.classList.remove('show');
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format Tidak Valid!',
                        text: 'Hanya file JPG, JPEG, atau PNG yang diperbolehkan',
                        confirmButtonColor: '#3b82f6'
                    });
                    gambarInput.value = '';
                    filePreview.classList.remove('show');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    filePreview.classList.add('show');
                }
                reader.readAsDataURL(file);
            } else {
                filePreview.classList.remove('show');
            }
        });
    }

    // Toggle Form
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
        if (confirm('Batalkan pengisian form?')) {
            window.location.href = 'index.php?tab=reward';
        }
    }

    // View Detail
    function viewReward(data) {
        let html = `
            <div class="detail-image">
                <img src="../../${data.gambar}" alt="${data.nama}">
            </div>
            <div class="detail-row">
                <div class="detail-label">Nama Penerima</div>
                <div class="detail-value">${data.nama}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Jabatan</div>
                <div class="detail-value">${data.jabatan}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Keterangan</div>
                <div class="detail-value">${data.keterangan}</div>
            </div>
        `;
        
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('modalDetail').classList.add('show');
    }

    // Close Modal
    function closeModal() {
        document.getElementById('modalDetail').classList.remove('show');
    }

    // Edit Reward
    function editReward(id) {
        window.location.href = 'index.php?tab=reward&edit=1&id=' + id;
    }

    // Delete Reward
    function deleteReward(id, nama) {
        Swal.fire({
            title: 'Hapus Reward?',
            html: `Apakah Anda yakin ingin menghapus reward untuk:<br><strong>${nama}</strong>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'prosesreward.php?action=hapus&id=' + id;
            }
        });
    }

    // Auto show form jika edit mode
    <?php if ($edit_mode): ?>
    window.addEventListener('DOMContentLoaded', function() {
        document.getElementById('btnText').textContent = 'Tutup Form';
        document.getElementById('formReward').scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    <?php endif; ?>

    // Close modal when clicking outside
    document.getElementById('modalDetail')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Validasi Form
    document.getElementById('rewardForm')?.addEventListener('submit', function(e) {
        const keterangan = document.querySelector('textarea[name="keterangan"]').value;
        
        if (keterangan.length > 100) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Keterangan Terlalu Panjang!',
                text: 'Keterangan maksimal 100 karakter',
                confirmButtonColor: '#3b82f6'
            });
            return false;
        }
    });
</script>