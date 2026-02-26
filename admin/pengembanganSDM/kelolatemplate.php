<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

$query_template = "SELECT * FROM template_surat 
WHERE jenis_template IN ('izin_belajar', 'pernyataan_kerja', 'studi_lanjut')
ORDER BY created_at DESC";

$stmt_template = $conn->prepare($query_template);
$stmt_template->execute();
$template_data = $stmt_template->fetchAll(PDO::FETCH_ASSOC);

define('MAX_FILE_SIZE', 2 * 1024 * 1024); 
define('ALLOWED_EXTENSIONS', ['doc', 'docx', 'pdf']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Template - Pengembangan SDM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
        }

        .main-content {
            padding: 30px;
            margin-left: 290px;
            transition: margin-left 0.3s ease;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .page-header p {
            font-size: 14px;
            color: #666;
            margin-bottom: 16px;
        }

        .btn-back {
            padding: 10px 20px;
            background: white;
            color: #64748b;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }

        .content-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
        }

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

        .form-group { flex: 1; min-width: 200px; text-align: left; }

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

        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; }
        .file-input-wrapper input[type="file"] { position: absolute; left: -9999px; }

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
        .file-input-label:hover { background: #eff6ff; }

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
        .btn-upload:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(59,130,246,0.3); }

        .section-subtitle {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
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
        .template-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

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

        .template-info { flex: 1; }
        .template-name { font-size: 15px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
        .template-meta { font-size: 12px; color: #666; margin-bottom: 12px; }
        .template-actions { display: flex; gap: 8px; }

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
        .btn-download:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); color: white; }

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
        .btn-delete:hover { background: #fecaca; }

        @media (max-width: 968px) {
            .main-content { margin-left: 80px; padding: 24px; }
        }
        @media (max-width: 768px) {
            .upload-form { flex-direction: column; }
            .form-group { width: 100%; }
            .template-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .main-content { margin-left: 70px; padding: 16px; }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <h1>Kelola Template Pengajuan</h1>
            <p>Upload dan kelola template surat pengajuan studi lanjut</p>
            <a href="pengembangan-sdm.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Pengembangan SDM
            </a>
        </div>

        <div class="content-card">
            <?php if (!empty($template_data) && count($template_data) > 0): ?>
            <div class="upload-section" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-color: #f59e0b;">
                <div class="upload-icon" style="background: white;">
                    <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
                </div>
                <div class="upload-title" style="color: #92400e;">Template Sudah Tersedia</div>
                <p style="color: #92400e; font-size: 14px; margin: 0;">
                    Anda hanya dapat mengupload 1 template. Untuk mengupload template baru, hapus template yang ada terlebih dahulu.
                </p>
            </div>
            <?php else: ?>
            <div class="upload-section">
                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
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
                    <button type="submit" class="btn-upload"><i class="fas fa-upload"></i> Upload</button>
                </form>
                <div style="text-align:center;margin-top:12px;">
                    <small style="color:#1e40af;font-size:12px;">
                        <i class="fas fa-info-circle"></i> Format: .doc, .docx, .pdf | Maksimal ukuran file: 2MB
                    </small>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($template_data) && count($template_data) > 0): ?>
            <h3 class="section-subtitle" style="margin-top:40px;">Template Tersedia</h3>
            <div class="template-grid">
                <?php foreach ($template_data as $template):
                    $created_date = date('d M Y', strtotime($template['created_at']));
                ?>
                <div class="template-card">
                    <div class="template-icon"><i class="fas fa-file-alt"></i></div>
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
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('uploadTemplateForm')?.addEventListener('submit', function(e) {
            const file = document.getElementById('templateFile').files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    e.preventDefault();
                    Swal.fire({ icon:'error', title:'File Terlalu Besar!', text:'Ukuran file maksimal adalah 2MB', confirmButtonColor:'#3b82f6' });
                    return false;
                }
                const ext = file.name.toLowerCase().split('.').pop();
                if (!['doc','docx','pdf'].includes(ext)) {
                    e.preventDefault();
                    Swal.fire({ icon:'error', title:'Format File Tidak Valid!', text:'Hanya file .doc, .docx, dan .pdf yang diperbolehkan', confirmButtonColor:'#3b82f6' });
                    return false;
                }
            }
        });

        document.getElementById('templateFile')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) document.getElementById('fileName').textContent = `${file.name} (${(file.size/1024).toFixed(2)} KB)`;
        });

        function deleteTemplate(id, nama) {
            Swal.fire({
                title: 'Hapus Template?',
                html: `Apakah Anda yakin ingin menghapus template:<br><strong>${nama}</strong>`,
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash"></i> Ya, Hapus', cancelButtonText: 'Batal'
            }).then((result) => { if (result.isConfirmed) window.location.href = 'delete.php?id=' + id; });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const p = new URLSearchParams(window.location.search);
            if (p.get('status') && p.get('message')) {
                Swal.fire({
                    icon: p.get('status') === 'success' ? 'success' : 'error',
                    title: p.get('status') === 'success' ? 'Berhasil!' : 'Gagal!',
                    text: decodeURIComponent(p.get('message')),
                    confirmButtonColor: '#3b82f6', timer: 3000, timerProgressBar: true
                }).then(() => { window.location.href = 'kelolatemplate.php'; });
            }
        });
    </script>
</body>
</html>