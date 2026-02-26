<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';

// Cek apakah ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: pengembangan-sdm.php?status=error&message=' . urlencode('ID pengajuan tidak valid'));
    exit();
}

$pengajuan_id = (int)$_GET['id'];

// Ambil data pengajuan lengkap
$query = "SELECT 
    ps.*,
    p.email,
    p.nik,
    p.no_telepon,
    p.alamat_domisili AS alamat,
    sk.jabatan,
    sk.unit_kerja,
    sk.status_aktif,
    sk.tanggal_mulai_kerja
FROM pengajuan_studi ps
JOIN pegawai p ON ps.pegawai_id = p.pegawai_id
LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
WHERE ps.pengajuan_id = :pengajuan_id";

$stmt = $conn->prepare($query);
$stmt->execute([':pengajuan_id' => $pengajuan_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Cek apakah data ditemukan
if (!$data) {
    header('Location: pengembangan-sdm.php?status=error&message=' . urlencode('Data pengajuan tidak ditemukan'));
    exit();
}

// Format tanggal
$tanggal_mulai = $data['tanggal_mulai_studi'] ? date('d F Y', strtotime($data['tanggal_mulai_studi'])) : '-';
$created_at = date('d F Y H:i', strtotime($data['created_at']));
$updated_at = $data['updated_at'] ? date('d F Y H:i', strtotime($data['updated_at'])) : '-';

$status_class = '';
$status_icon = '';
$status_text = '';

switch ($data['status_pengajuan']) {
    case 'diajukan':
        $status_class = 'pending';
        $status_icon = 'fa-file-alt';
        $status_text = 'Dokumen Diajukan';
        break;
    case 'ditinjau':
        $status_class = 'pending';
        $status_icon = 'fa-search';
        $status_text = 'Sedang Ditinjau HRD';
        break;
    case 'menunggu_persetujuan':
        $status_class = 'pending';
        $status_icon = 'fa-clock';
        $status_text = 'Menunggu Persetujuan';
        break;
    case 'disetujui':
        $status_class = 'approved';
        $status_icon = 'fa-check-circle';
        $status_text = 'Disetujui';
        break;
    case 'ditolak':
        $status_class = 'rejected';
        $status_icon = 'fa-times-circle';
        $status_text = 'Ditolak';
        break;
    default:
        $status_class = 'pending';
        $status_icon = 'fa-clock';
        $status_text = 'Menunggu Review';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengajuan - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; overflow-x: hidden; }
        .main-content { margin-left: 280px; padding: 32px; min-height: 100vh; transition: all 0.3s ease; background: #f8fafc; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .header-left { flex: 1; }
        .page-title { font-size: 28px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .page-subtitle { font-size: 15px; color: #64748b; font-weight: 400; }
        .btn-back { padding: 12px 24px; background: #f1f5f9; color: #475569; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }
        .detail-container { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .detail-card { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0; }
        .card-title { font-size: 20px; font-weight: 700; color: #1e293b; }
        .status-badge { padding: 8px 20px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.approved { background: #d1fae5; color: #065f46; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .info-section { margin-bottom: 28px; }
        .section-title { font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: #3b82f6; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .info-item { display: flex; flex-direction: column; gap: 6px; }
        .info-label { font-size: 13px; color: #64748b; font-weight: 500; }
        .info-value { font-size: 15px; color: #1e293b; font-weight: 600; }
        .info-item.full-width { grid-column: 1 / -1; }
        .divider { height: 1px; background: #e2e8f0; margin: 24px 0; }
        .action-panel { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06); position: sticky; top: 32px; }
        .action-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 20px; }
        .action-buttons { display: flex; flex-direction: column; gap: 12px; }
        .btn-action { padding: 14px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-approve { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3); }
        .btn-reject { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .btn-reject:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3); }
        .info-box { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin-top: 20px; }
        .info-box p { font-size: 13px; color: #475569; margin: 0; line-height: 1.6; }
        .timeline { margin-top: 20px; }
        .timeline-item { display: flex; gap: 16px; padding: 12px 0; }
        .timeline-icon { width: 32px; height: 32px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        .timeline-content { flex: 1; }
        .timeline-title { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
        .timeline-date { font-size: 12px; color: #64748b; }
       /* Responsive untuk sidebar */
        @media (max-width: 968px) {
            .main-content { 
                margin-left: 80px !important; 
                padding: 24px; 
            }
            
            .detail-container { 
                grid-template-columns: 1fr; 
            }
            
            .action-panel { 
                position: relative; 
                top: 0; 
            }
            
            .info-grid { 
                grid-template-columns: 1fr; 
            }
            
            .page-title {
                font-size: 24px;
            }
        }

        @media (max-width: 768px) {
            .main-content { 
                margin-left: 70px !important; 
                padding: 20px; 
            }
            
            .page-title { 
                font-size: 22px; 
            }
            
            .page-subtitle {
                font-size: 14px;
            }
            
            .detail-card {
                padding: 20px;
            }
            
            .action-panel {
                padding: 20px;
            }
            
            .card-title {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 70px !important;
                padding: 16px;
            }
            
            .page-title {
                font-size: 20px;
            }
            
            .page-subtitle {
                font-size: 13px;
            }
            
            .detail-card {
                padding: 16px;
            }
            
            .action-panel {
                padding: 16px;
            }
            
            .btn-action {
                padding: 12px 20px;
                font-size: 13px;
            }
            
            .status-badge {
                padding: 6px 16px;
                font-size: 12px;
            }
            
            .info-value {
                font-size: 14px;
            }
            
            .section-title {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">Detail Pengajuan</h1>
                <p class="page-subtitle">Informasi lengkap pengajuan izin belajar</p>
            </div>
            <a href="pengembangan-sdm.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <div class="detail-container">
            <div>
                <div class="detail-card">
                    <div class="card-header">
                        <h2 class="card-title">Informasi Pengajuan</h2>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <i class="fas <?php echo $status_icon; ?>"></i>
                            <?php echo $status_text; ?>
                        </span>
                    </div>

                    <div class="info-section">
                        <div class="section-title">
                            <i class="fas fa-user"></i>
                            Data Pegawai
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nama Lengkap</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['nama_lengkap']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">NIK</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['nik']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">No. Telepon</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['no_telepon'] ?? '-'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Jabatan</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['jabatan'] ?? '-'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Unit Kerja</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['unit_kerja'] ?? '-'); ?></div>
                            </div>
                            <div class="info-item full-width">
                                <div class="info-label">Alamat</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['alamat'] ?? '-'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="info-section">
                        <div class="section-title">
                            <i class="fas fa-graduation-cap"></i>
                            Data Studi
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Jenjang Pendidikan</div>
                                <div class="info-value"><?php echo strtoupper(htmlspecialchars($data['jenjang_pendidikan'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Nama Institusi</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['nama_institusi']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Program Studi</div>
                                <div class="info-value"><?php echo htmlspecialchars($data['program_studi']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tanggal Mulai</div>
                                <div class="info-value"><?php echo $tanggal_mulai; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="info-section">
                        <div class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Dokumen Pendukung
                        </div>
                        <div class="info-grid">
                            <?php if (!empty($data['surat_permohonan_path'])) { ?>
                            <div class="info-item">
                                <div class="info-label">Surat Permohonan</div>
                                <div class="info-value">
                                    <a href="<?php echo '../../' . ltrim(htmlspecialchars($data['surat_permohonan_path']), './'); ?>" target="_blank" style="color: #3b82f6; text-decoration: none;">
                                        <i class="fas fa-download"></i> Lihat Dokumen
                                    </a>
                                </div>
                            </div>
                            <?php } ?>
                            
                            <?php if (!empty($data['surat_penerimaan_path'])) { ?>
                            <div class="info-item">
                                <div class="info-label">Surat Penerimaan</div>
                                <div class="info-value">
                                    <a href="<?php echo '../../' . ltrim(htmlspecialchars($data['surat_penerimaan_path']), './'); ?>" target="_blank" style="color: #3b82f6; text-decoration: none;">
                                        <i class="fas fa-download"></i> Lihat Dokumen
                                    </a>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if (!empty($data['catatan_admin']) && $data['status_pengajuan'] == 'ditolak') { ?>
                    <div class="divider"></div>

                    <div class="info-section">
                        <div class="section-title">
                            <i class="fas fa-exclamation-circle"></i>
                            Alasan Penolakan
                        </div>
                        <div class="info-box" style="border-left-color: #ef4444; background: #fef2f2;">
                            <p><?php echo nl2br(htmlspecialchars($data['catatan_admin'])); ?></p>
                        </div>
                    </div>
                    <?php } ?>

                    <div class="divider"></div>

                    <div class="info-section">
                        <div class="section-title">
                            <i class="fas fa-history"></i>
                            Riwayat Pengajuan
                        </div>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-plus"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Pengajuan Dibuat</div>
                                    <div class="timeline-date"><?php echo $created_at; ?></div>
                                </div>
                            </div>
                            <?php if ($data['updated_at']) { ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Status Terakhir Diperbarui</div>
                                    <div class="timeline-date"><?php echo $updated_at; ?></div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="action-panel">
                    <h3 class="action-title">Aksi Pengajuan</h3>
                    
                    <?php if (in_array($data['status_pengajuan'], ['diajukan', 'ditinjau', 'menunggu_persetujuan'])) { ?>
                    <div class="action-buttons">
                        <button class="btn-action btn-approve" onclick="approveRequest(<?php echo $pengajuan_id; ?>)">
                            <i class="fas fa-check-circle"></i>
                            Setujui Pengajuan
                        </button>
                        <button class="btn-action btn-reject" onclick="rejectRequest(<?php echo $pengajuan_id; ?>)">
                            <i class="fas fa-times-circle"></i>
                            Tolak Pengajuan
                        </button>
                    </div>

                    <div class="info-box">
                        <p><i class="fas fa-info-circle"></i> Pastikan semua dokumen telah diperiksa sebelum menyetujui atau menolak pengajuan.</p>
                    </div>
                    <?php } else { ?>
                    <div class="info-box" style="<?php echo $data['status_pengajuan'] == 'ditolak' ? 'border-left-color: #ef4444; background: #fef2f2;' : 'border-left-color: #10b981; background: #d1fae5;'; ?>">
                        <p><i class="fas <?php echo $data['status_pengajuan'] == 'ditolak' ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i> Pengajuan ini sudah diproses dengan status: <strong><?php echo $status_text; ?></strong></p>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const pengajuanId = <?php echo $pengajuan_id; ?>;

        function approveRequest(id) {
            Swal.fire({
                title: 'Setujui Pengajuan?',
                text: 'Apakah Anda yakin ingin menyetujui pengajuan ini?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-check"></i> Ya, Setujui',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'proses_pengajuan.php?action=approve&id=' + id;
                }
            });
        }

        function rejectRequest(id) {
            Swal.fire({
                title: 'Tolak Pengajuan',
                text: 'Masukkan alasan penolakan (minimal 10 karakter):',
                input: 'textarea',
                inputPlaceholder: 'Tulis alasan penolakan di sini...',
                inputAttributes: {
                    'aria-label': 'Alasan penolakan',
                    'style': 'min-height: 100px;'
                },
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-times"></i> Tolak',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Alasan penolakan harus diisi!'
                    }
                    if (value.trim().length < 10) {
                        return 'Alasan penolakan minimal 10 karakter!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'proses_pengajuan.php?action=reject&id=' + id + '&reason=' + encodeURIComponent(result.value);
                }
            });
        }
        
        // Show notification dari URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            
            if (status && message) {
                const icon = status === 'success' ? 'success' : 'error';
                const title = status === 'success' ? 'Berhasil!' : 'Gagal!';
                
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: decodeURIComponent(message),
                    confirmButtonColor: '#3b82f6',
                    timer: 3000,
                    timerProgressBar: true
                }).then(() => {
                    window.history.replaceState({}, document.title, 'detail_pengajuan.php?id=' + pengajuanId);
                });
            }
        });
    </script>
</body>
</html>