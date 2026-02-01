<?php
// Ambil data template surat untuk pengembangan SDM
$query_template = "SELECT * FROM template_surat 
WHERE jenis_template IN ('izin_belajar', 'pernyataan_kerja', 'studi_lanjut')
ORDER BY created_at DESC";

$stmt_template = $conn->prepare($query_template);
$stmt_template->execute();
$template_data = $stmt_template->fetchAll(PDO::FETCH_ASSOC);

// Konstanta untuk upload
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB dalam bytes
define('ALLOWED_EXTENSIONS', ['doc', 'docx', 'pdf']);
?>

<style>
    /* ===== TEMPLATE SECTION ===== */
    .upload-section {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 2px dashed #3b82f6;
        border-radius: 12px;
        padding: 32px;
        margin-bottom: 24px;
        text-align: center;
    }

    .upload-icon {
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

    .upload-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 16px;
    }

    .upload-form {
        display: flex;
        gap: 12px;
        max-width: 800px;
        margin: 0 auto;
        flex-wrap: wrap;
        justify-content: center;
        align-items: end;
    }

    .form-group {
        flex: 1;
        min-width: 200px;
        text-align: left;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #1e40af;
        margin-bottom: 6px;
    }

    .form-group input[type="text"],
    .form-group select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #93c5fd;
        border-radius: 10px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
    }

    .form-group input[type="text"]:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3b82f6;
    }

    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        left: -9999px;
    }

    .file-input-label {
        padding: 12px 24px;
        background: white;
        border: 2px solid #3b82f6;
        color: #3b82f6;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .file-input-label:hover {
        background: #eff6ff;
    }

    .btn-upload {
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

    .btn-upload:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3);
    }

    .section-subtitle {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 20px;
    }

    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 16px;
    }

    .template-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        transition: all 0.3s ease;
        display: flex;
        gap: 16px;
        align-items: start;
    }

    .template-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .template-icon {
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

    .template-info {
        flex: 1;
    }

    .template-name {
        font-size: 15px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .template-meta {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 12px;
    }

    .template-actions {
        display: flex;
        gap: 8px;
    }

    .btn-download {
        padding: 8px 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        color: white;
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

    @media (max-width: 768px) {
        .upload-form {
            flex-direction: column;
        }

        .form-group {
            width: 100%;
        }

        .template-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-card">
    <!-- Upload Section -->
    <div class="upload-section">
        <div class="upload-icon">
            <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="upload-title">Upload Template Baru</div>
        
        <form class="upload-form" id="uploadTemplateForm" action="upload.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nama Template</label>
                <input type="text" name="nama_template" placeholder="Contoh: Template Surat Permohonan Izin Belajar" required>
            </div>
            <div class="file-input-wrapper">
                <input type="file" name="file_template" id="templateFile" accept=".doc,.docx,.pdf" required>
                <label for="templateFile" class="file-input-label">
                    <i class="fas fa-file"></i>
                    <span id="fileName">Pilih File</span>
                </label>
            </div>
            <button type="submit" class="btn-upload">
                <i class="fas fa-upload"></i> Upload
            </button>
        </form>
        <div style="text-align: center; margin-top: 12px;">
            <small style="color: #1e40af; font-size: 12px;">
                <i class="fas fa-info-circle"></i> 
                Format: .doc, .docx, .pdf | Maksimal ukuran file: 2MB
            </small>
        </div>
    </div>

    <!-- Template List - HANYA MUNCUL JIKA ADA DATA -->
    <?php 
    // Cek apakah ada data template
    if (!empty($template_data) && count($template_data) > 0) {
    ?>
    
    <h3 class="section-subtitle" style="margin-top: 40px;">Template Tersedia</h3>

    <div class="template-grid">
        <?php 
            foreach ($template_data as $template) {
                $created_date = date('d M Y', strtotime($template['created_at']));
                $file_name = basename($template['path_file']);
        ?>
        <div class="template-card">
            <div class="template-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="template-info">
                <div class="template-name"><?php echo htmlspecialchars($template['nama_template']); ?></div>
                <div class="template-meta">Upload: <?php echo $created_date; ?></div>
                <div class="template-actions">
                    <a href="../../<?php echo htmlspecialchars($template['path_file']); ?>" class="btn-download" target="_blank">
                        <i class="fas fa-eye"></i> Lihat
                    </a>
                    <button class="btn-delete" onclick="deleteTemplate(<?php echo $template['template_id']; ?>, '<?php echo addslashes(htmlspecialchars($template['nama_template'])); ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php 
            }
        ?>
    </div>
    
    <?php 
    } 
    // Jika tidak ada data, tidak menampilkan apa-apa (tidak ada else)
    ?>
</div>

<script>
    // Validasi file upload
    document.getElementById('uploadTemplateForm')?.addEventListener('submit', function(e) {
        const fileInput = document.getElementById('templateFile');
        const file = fileInput.files[0];
        
        if (file) {
            // Validasi ukuran file (2MB = 2 * 1024 * 1024 bytes)
            const maxSize = 2 * 1024 * 1024;
            if (file.size > maxSize) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'File Terlalu Besar!',
                    text: 'Ukuran file maksimal adalah 2MB',
                    confirmButtonColor: '#3b82f6'
                });
                return false;
            }
            
            // Validasi ekstensi file
            const allowedExtensions = ['doc', 'docx', 'pdf'];
            const fileName = file.name.toLowerCase();
            const fileExtension = fileName.split('.').pop();
            
            if (!allowedExtensions.includes(fileExtension)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Format File Tidak Valid!',
                    text: 'Hanya file .doc, .docx, dan .pdf yang diperbolehkan',
                    confirmButtonColor: '#3b82f6'
                });
                return false;
            }
        }
    });

    // Update file name display
    document.getElementById('templateFile')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const fileName = file.name;
            const fileSize = (file.size / 1024).toFixed(2); // Convert to KB
            document.getElementById('fileName').textContent = `${fileName} (${fileSize} KB)`;
        }
    });

    // Delete template
    function deleteTemplate(id, nama) {
        Swal.fire({
            title: 'Hapus Template?',
            html: `Apakah Anda yakin ingin menghapus template:<br><strong>${nama}</strong>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'delete.php?id=' + id;
            }
        });
    }
</script>