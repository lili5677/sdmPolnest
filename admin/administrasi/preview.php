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
                so.path_gambar,
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
$level1 = getDataByLevel($conn, 1); // Direktur
$level2 = getDataByLevel($conn, 2); // Kepala Unit
$level3 = getDataByLevel($conn, 3); // Laboran
$level4 = getDataByLevel($conn, 4); // Tendik

// Fungsi untuk mendapatkan warna avatar berdasarkan level
function getAvatarColor($level) {
    switch($level) {
        case 1: return '#105666'; 
        case 2: return '#E59D2C'; 
        case 3: return '#E38792'; 
        case 4: return '#F3D58D'; 
        default: return '#6b7280';
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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: #1e293b;
        }

        .preview-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 30px 80px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            border: 1px solid #e2e8f0;
            color: #475569;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            margin-bottom: 50px;
        }

        .back-button:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #1e293b;
            transform: translateX(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .preview-header {
            text-align: center;
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .preview-header h1 {
            font-size: 36px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .preview-header p {
            color: #64748b;
            font-size: 16px;
            line-height: 1.7;
            font-weight: 400;
        }

        .level-section {
            margin-bottom: 50px;
        }

        .level-title {
            font-size: 13px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 24px;
            text-align: center;
        }

        .members-grid {
            display: grid;
            gap: 24px;
            margin-bottom: 30px;
        }

        .level-1-grid {
            grid-template-columns: 1fr;
            justify-items: center;
            max-width: 360px;
            margin: 0 auto;
        }

        .level-2-grid, .level-3-grid, .level-4-grid {
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            max-width: 1200px;
            margin: 0 auto;
        }

        .member-card-preview {
            background: white;
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .member-card-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #105666, #E38792);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .level-1-grid .member-card-preview::before {
            background: linear-gradient(90deg, #105666, #105666);
        }

        .level-2-grid .member-card-preview::before {
            background: linear-gradient(90deg, #E59D2C, #E59D2C);
        }

        .level-3-grid .member-card-preview::before {
            background: linear-gradient(90deg, #E38792, #E38792);
        }

        .level-4-grid .member-card-preview::before {
            background: linear-gradient(90deg, #F3D58D, #F3D58D);
        }

        .member-card-preview:hover::before {
            opacity: 1;
        }

        .level-1-grid .member-card-preview {
            max-width: 360px;
            padding: 40px 32px;
        }

        .member-card-preview:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
            border-color: #e2e8f0;
        }

        .avatar-container {
            width: 140px;
            height: 140px;
            position: relative;
            overflow: hidden;
            border-radius: 50%;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border: 4px solid white;
        }

        .level-1-grid .avatar-container {
            width: 180px;
            height: 180px;
            margin-bottom: 24px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
            border-width: 5px;
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
            font-size: 42px;
            font-weight: 700;
            color: white;
        }

        .level-1-grid .avatar-default {
            font-size: 56px;
        }

        .member-name-preview {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
            line-height: 1.4;
            letter-spacing: -0.01em;
        }

        .level-1-grid .member-name-preview {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .member-position-preview {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            line-height: 1.5;
        }

        .level-1-grid .member-position-preview {
            font-size: 15px;
            color: #475569;
        }

        .empty-section {
            text-align: center;
            padding: 80px 30px;
            background: white;
            border-radius: 24px;
            max-width: 600px;
            margin: 60px auto;
            border: 1px solid #f1f5f9;
        }

        .empty-section i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 24px;
        }

        .empty-section h4 {
            color: #1e293b;
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 20px;
        }

        .empty-section p {
            color: #64748b;
            font-size: 15px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .level-2-grid, .level-3-grid, .level-4-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .preview-header h1 {
                font-size: 28px;
            }

            .preview-header p {
                font-size: 15px;
            }

            .level-2-grid, .level-3-grid, .level-4-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }

            .avatar-container {
                width: 120px;
                height: 120px;
            }

            .level-1-grid .avatar-container {
                width: 160px;
                height: 160px;
            }

            .avatar-default {
                font-size: 36px;
            }

            .level-1-grid .avatar-default {
                font-size: 48px;
            }

            .member-card-preview {
                padding: 24px 20px;
            }

            .level-1-grid .member-card-preview {
                max-width: 300px;
                padding: 32px 24px;
            }
        }

        @media (max-width: 480px) {
            .preview-container {
                padding: 30px 20px 60px;
            }

            .level-2-grid, .level-3-grid, .level-4-grid {
                grid-template-columns: 1fr;
                max-width: 300px;
                margin: 0 auto;
            }

            .avatar-container {
                width: 110px;
                height: 110px;
            }

            .level-1-grid .avatar-container {
                width: 140px;
                height: 140px;
            }

            .avatar-default {
                font-size: 32px;
            }

            .level-1-grid .avatar-default {
                font-size: 42px;
            }

            .member-name-preview {
                font-size: 15px;
            }

            .level-1-grid .member-name-preview {
                font-size: 18px;
            }

            .member-position-preview {
                font-size: 12px;
            }

            .level-1-grid .member-position-preview {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <a href="/sdmPolnest/admin/administrasi/administrasiKepegawaian.php#struktur-organisasi" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Dashboard
        </a>

        <div class="preview-header">
            <h1>Staf Berpengalaman</h1>
            <p>Staf kami berpengalaman dalam bidang masing-masing seperti kepegawaian, keuangan, organisasi, peraturan perundang-undangan dan teknologi informasi.</p>
        </div>

        <?php if(!empty($level1)): ?>
        <div class="level-section">
            <div class="level-title">Direktur</div>
            <div class="members-grid level-1-grid">
                <?php foreach($level1 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(1); ?>">
                        <?php if(!empty($member['path_gambar'])): ?>
                            <img src="<?php echo htmlspecialchars($member['path_gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['nama_lengkap']); ?>" 
                                 class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-default"><?php echo getInitials($member['nama_lengkap']); ?></div>
                        <?php endif; ?>
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

        <?php if(!empty($level2)): ?>
        <div class="level-section">
            <div class="level-title">Kepala Unit</div>
            <div class="members-grid level-2-grid">
                <?php foreach($level2 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(2); ?>">
                        <?php if(!empty($member['path_gambar'])): ?>
                            <img src="<?php echo htmlspecialchars($member['path_gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['nama_lengkap']); ?>" 
                                 class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-default"><?php echo getInitials($member['nama_lengkap']); ?></div>
                        <?php endif; ?>
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

        <?php if(!empty($level3)): ?>
        <div class="level-section">
            <div class="level-title">Laboran</div>
            <div class="members-grid level-3-grid">
                <?php foreach($level3 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(3); ?>">
                        <?php if(!empty($member['path_gambar'])): ?>
                            <img src="<?php echo htmlspecialchars($member['path_gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['nama_lengkap']); ?>" 
                                 class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-default"><?php echo getInitials($member['nama_lengkap']); ?></div>
                        <?php endif; ?>
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

        <?php if(!empty($level4)): ?>
        <div class="level-section">
            <div class="level-title">Tenaga Kependidikan</div>
            <div class="members-grid level-4-grid">
                <?php foreach($level4 as $member): ?>
                <div class="member-card-preview">
                    <div class="avatar-container" style="background: <?php echo getAvatarColor(4); ?>">
                        <?php if(!empty($member['path_gambar'])): ?>
                            <img src="<?php echo htmlspecialchars($member['path_gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($member['nama_lengkap']); ?>" 
                                 class="avatar-img">
                        <?php else: ?>
                            <div class="avatar-default"><?php echo getInitials($member['nama_lengkap']); ?></div>
                        <?php endif; ?>
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

        <?php if(empty($level1) && empty($level2) && empty($level3) && empty($level4)): ?>
        <div class="empty-section">
            <i class="fas fa-users-slash"></i>
            <h4>Belum Ada Data Struktur Organisasi</h4>
            <p>Silakan tambahkan anggota struktur organisasi terlebih dahulu</p>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../../users/partials/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>