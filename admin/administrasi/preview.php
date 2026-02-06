<?php
// Koneksi Database
require_once '../../config/database.php';

// Fungsi untuk mengambil data berdasarkan level
function getDataByLevel($conn, $level) {
    $query = "SELECT 
                so.struktur_id,
                so.pegawai_id,
                so.jabatan_struktur,
                so.level_struktur,
                so.parent_id,
                p.nama_lengkap
            FROM struktur_organisasi so
            INNER JOIN pegawai p ON so.pegawai_id = p.pegawai_id
            WHERE so.level_struktur = :level
            ORDER BY so.created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':level', $level);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ambil data untuk setiap level
$level1 = getDataByLevel($conn, 1); // Pimpinan Tertinggi
$level2 = getDataByLevel($conn, 2); // Kepala Unit
$level3 = getDataByLevel($conn, 3); // Staff/Anggota

// Fungsi untuk mendapatkan warna avatar berdasarkan level
function getAvatarColor($level) {
    switch($level) {
        case 1: return '#6b7280'; // Gray
        case 2: return '#ffb4c8'; // Pink
        case 3: return '#ffcc99'; // Orange
        default: return '#d1d5db';
    }
}

// Fungsi untuk mendapatkan inisial nama
function getInitials($nama) {
    $words = explode(' ', trim($nama));
    if(count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($nama, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview - Struktur Organisasi</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5dc;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }

        .preview-container {
            max-width: 1200px;
            margin: 0 auto;
            background: transparent;
            padding: 40px 30px 60px;
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 2px solid #e5e7eb;
            color: #374151;
            padding: 10px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        .back-button:hover {
            background: #1f2937;
            border-color: #1f2937;
            color: white;
            transform: translateX(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .back-button i {
            font-size: 14px;
        }

        /* Header */
        .preview-header {
            text-align: center;
            margin-bottom: 50px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .preview-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
            font-family: 'Poppins', sans-serif;
        }

        .preview-header p {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            font-family: 'Poppins', sans-serif;
        }

        /* Section Level */
        .level-section {
            margin-bottom: 40px;
        }

        /* Member Grid */
        .members-grid {
            display: grid;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Level 1  */
        .level-1-grid {
            grid-template-columns: 1fr;
            max-width: 320px;
            margin: 0 auto;
        }

        /* Level 2  */
        .level-2-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            justify-items: center;
        }

        /* Level 3  */
        .level-3-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            justify-items: center;
        }

        /* Member Card  */
        .member-card-preview {
            background: white;
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 280px;
        }

        .level-1-grid .member-card-preview {
            max-width: 320px;
        }

        .member-card-preview:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            border-color: #d1d5db;
        }

        /* Avatar Container */
        .avatar-container {
            width: 150px;
            height: 150px;
            position: relative;
            overflow: hidden;
            border-radius: 50%;
            margin: 0 auto 18px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .level-1-grid .avatar-container {
            width: 180px;
            height: 180px;
        }

        .avatar-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-default {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        .level-1-grid .avatar-default {
            font-size: 60px;
        }

        /* Member Info */
        .member-info-preview {
            padding: 0;
        }

        .member-name-preview {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
            line-height: 1.3;
            font-family: 'Poppins', sans-serif;
        }

        .level-1-grid .member-name-preview {
            font-size: 17px;
            font-weight: 700;
        }

        .member-position-preview {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
            line-height: 1.5;
            font-family: 'Poppins', sans-serif;
        }

        .level-1-grid .member-position-preview {
            font-size: 13px;
        }

        /* Empty State */
        .empty-section {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            max-width: 600px;
            margin: 0 auto;
        }

        .empty-section i {
            font-size: 56px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-section h4 {
            color: #374151;
            font-size: 17px;
            margin-bottom: 10px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .empty-section p {
            color: #9ca3af;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
        }

        /* Responsive - Tablet */
        @media (max-width: 1024px) {
            .preview-container {
                padding: 35px 25px 50px;
            }

            .level-2-grid,
            .level-3-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }

            .member-card-preview {
                max-width: 260px;
            }
        }

        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .preview-container {
                padding: 30px 20px 40px;
            }

            .preview-header {
                margin-bottom: 35px;
            }

            .preview-header h1 {
                font-size: 22px;
            }

            .preview-header p {
                font-size: 13px;
            }

            .level-2-grid,
            .level-3-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 18px;
            }

            .back-button {
                width: 100%;
                justify-content: center;
                margin-bottom: 30px;
                font-size: 12px;
                padding: 9px 20px;
            }

            .avatar-container {
                width: 130px;
                height: 130px;
            }

            .level-1-grid .avatar-container {
                width: 160px;
                height: 160px;
            }

            .member-card-preview {
                padding: 20px 16px;
                max-width: 100%;
            }

            .level-1-grid .member-card-preview {
                max-width: 100%;
            }

            .member-name-preview {
                font-size: 13px;
            }

            .level-1-grid .member-name-preview {
                font-size: 15px;
            }

            .member-position-preview {
                font-size: 11px;
            }

            .level-1-grid .member-position-preview {
                font-size: 12px;
            }

            .avatar-default {
                font-size: 40px;
            }

            .level-1-grid .avatar-default {
                font-size: 52px;
            }

            .level-section {
                margin-bottom: 30px;
            }
        }

        /* Responsive - Small Mobile */
        @media (max-width: 480px) {
            .preview-container {
                padding: 25px 16px 35px;
            }

            .preview-header {
                margin-bottom: 30px;
            }

            .preview-header h1 {
                font-size: 20px;
            }

            .preview-header p {
                font-size: 12px;
            }

            .level-section {
                margin-bottom: 25px;
            }

            .level-2-grid,
            .level-3-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }

            .member-card-preview {
                padding: 18px 14px;
            }

            .member-name-preview {
                font-size: 12px;
            }

            .level-1-grid .member-name-preview {
                font-size: 14px;
            }

            .member-position-preview {
                font-size: 10px;
            }

            .level-1-grid .member-position-preview {
                font-size: 11px;
            }

            .avatar-container {
                width: 110px;
                height: 110px;
                margin-bottom: 14px;
            }

            .level-1-grid .avatar-container {
                width: 140px;
                height: 140px;
            }

            .avatar-default {
                font-size: 36px;
            }

            .level-1-grid .avatar-default {
                font-size: 46px;
            }

            .back-button {
                font-size: 11px;
                padding: 8px 18px;
            }

            .empty-section {
                padding: 50px 16px;
            }

            .empty-section i {
                font-size: 48px;
            }

            .empty-section h4 {
                font-size: 15px;
            }

            .empty-section p {
                font-size: 12px;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
            }

            .back-button {
                display: none;
            }

            .preview-container {
                padding: 20px;
            }

            .member-card-preview {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
</style>

</head>
<body>
    <div class="preview-container">
        <!-- Back Button -->
        <a href="/sdmPolnest/admin/administrasi/administrasiKepegawaian.php#struktur-organisasi" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Dashboard
        </a>

        <!-- Header -->
        <div class="preview-header">
            <h1>Staf Berpengalaman</h1>
            <p>Staf kami berpengalaman dalam bidang masing-masing seperti kepegawaian, keuangan, organisasi, peraturan perundang-undangan dan teknologi informasi.</p>
        </div>

        <!-- Level 1 - Pimpinan Tertinggi -->
        <?php if(!empty($level1)): ?>
        <div class="level-section">
            <div class="members-grid level-1-grid">
                <?php foreach($level1 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(1); ?>">
                        <div class="avatar-default">
                            <?php echo getInitials($member['nama_lengkap']); ?>
                        </div>
                    </div>
                    <div class="member-info-preview">
                        <div class="member-name-preview"><?php echo htmlspecialchars($member['nama_lengkap']); ?></div>
                        <div class="member-position-preview"><?php echo htmlspecialchars($member['jabatan_struktur']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Level 2 - Kepala Unit -->
        <?php if(!empty($level2)): ?>
        <div class="level-section">
            <div class="members-grid level-2-grid">
                <?php foreach($level2 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(2); ?>">
                        <div class="avatar-default">
                            <?php echo getInitials($member['nama_lengkap']); ?>
                        </div>
                    </div>
                    <div class="member-info-preview">
                        <div class="member-name-preview"><?php echo htmlspecialchars($member['nama_lengkap']); ?></div>
                        <div class="member-position-preview"><?php echo htmlspecialchars($member['jabatan_struktur']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Level 3 - Staff/Anggota -->
        <?php if(!empty($level3)): ?>
        <div class="level-section">
            <div class="members-grid level-3-grid">
                <?php foreach($level3 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(3); ?>">
                        <div class="avatar-default">
                            <?php echo getInitials($member['nama_lengkap']); ?>
                        </div>
                    </div>
                    <div class="member-info-preview">
                        <div class="member-name-preview"><?php echo htmlspecialchars($member['nama_lengkap']); ?></div>
                        <div class="member-position-preview"><?php echo htmlspecialchars($member['jabatan_struktur']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Empty State jika tidak ada data sama sekali -->
        <?php if(empty($level1) && empty($level2) && empty($level3)): ?>
        <div class="empty-section">
            <i class="fas fa-users-slash"></i>
            <h4>Belum Ada Data Struktur Organisasi</h4>
            <p>Silakan tambahkan anggota struktur organisasi terlebih dahulu</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include '../../users/partials/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto activate tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if coming from preview page
            if (document.referrer.includes('preview.php')) {
                // Find and activate the struktur tab
                const strukturTab = document.querySelector('#struktur-tab');
                if (strukturTab) {
                    const tab = new bootstrap.Tab(strukturTab);
                    tab.show();
                }
            }
        });
    </script>
</body>
</html>