<?php
/**
 * MANAJEMEN LOWONGAN KERJA - UPDATED VERSION
 * File: users/pegawai/loker.php
 * 
 * UPDATE:
 * - Auto-update status lowongan
 * - Toggle aktif/nonaktif manual
 * - Show expired badge
 * - Flash message support
 */

// Koneksi Database
require_once '../../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    header('Location: ' . BASE_URL . 'auth/login_pegawai.php');
    exit();
}

// HANDLE TOGGLE ACTION (dari button toggle)
if (isset($_GET['toggle_id']) && isset($_GET['action'])) {
    $toggle_id = intval($_GET['toggle_id']);
    $action = $_GET['action']; // 'activate' or 'deactivate'
    
    if ($action === 'activate') {
        $conn->prepare("UPDATE lowongan_pekerjaan SET is_active = 1 WHERE lowongan_id = ?")->execute([$toggle_id]);
        $_SESSION['flash_message'] = 'Lowongan berhasil diaktifkan';
    } else {
        $conn->prepare("UPDATE lowongan_pekerjaan SET is_active = 0 WHERE lowongan_id = ?")->execute([$toggle_id]);
        $_SESSION['flash_message'] = 'Lowongan berhasil dinonaktifkan';
    }
    $_SESSION['flash_type'] = 'success';
    header('Location: index.php');
    exit();
}

// Auto-update jumlah_diterima & status
// ⚠️ PENTING: 
// - Hitung HANYA yang status_lamaran = 'diterima'
// - UPDATE kolom jumlah_diterima, BUKAN kolom formasi!
// - Kolom formasi TIDAK BOLEH BERUBAH (formasi = target rekrutmen)
try {
    // Update jumlah_diterima berdasarkan yang sudah diterima
    $conn->exec("
        UPDATE lowongan_pekerjaan lp
        LEFT JOIN (
            SELECT lowongan_id, COUNT(*) as total
            FROM lamaran
            WHERE status_lamaran = 'diterima'
            GROUP BY lowongan_id
        ) l ON lp.lowongan_id = l.lowongan_id
        SET lp.jumlah_diterima = COALESCE(l.total, 0)
    ");
    
    // Auto-close yang expired/penuh
    // Penuh = jumlah_diterima >= formasi (bukan jumlah pendaftar!)
    $conn->exec("
        UPDATE lowongan_pekerjaan
        SET status = 'ditutup'
        WHERE status = 'aktif'
        AND (
            deadline_lamaran < CURDATE()
            OR jumlah_diterima >= formasi
            OR is_active = 0
        )
    ");
    
    // Re-activate jika kondisi terpenuhi
    $conn->exec("
        UPDATE lowongan_pekerjaan
        SET status = 'aktif'
        WHERE status = 'ditutup'
        AND is_active = 1
        AND deadline_lamaran >= CURDATE()
        AND jumlah_diterima < formasi
    ");
} catch (Exception $e) {
    // Silent fail
}

// Query untuk mendapatkan data lowongan pekerjaan dengan info tambahan
$query = "SELECT *, 
          (jumlah_diterima >= formasi) as is_full,
          (deadline_lamaran < CURDATE()) as is_expired,
          DATEDIFF(deadline_lamaran, CURDATE()) as days_remaining
          FROM lowongan_pekerjaan 
          ORDER BY created_at DESC";
$stmt = $conn->query($query);
$data_lowongan = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query untuk menghitung jumlah pendaftar per lowongan
$query_pendaftar = "SELECT lowongan_id, COUNT(*) as jumlah FROM lamaran GROUP BY lowongan_id";
$stmt_pendaftar = $conn->query($query_pendaftar);
$data_pendaftar = [];
while ($row = $stmt_pendaftar->fetch(PDO::FETCH_ASSOC)) {
    $data_pendaftar[$row['lowongan_id']] = $row['jumlah'];
}

// Get flash message
$flash_message = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flash_type = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Lowongan Kerja - Sistem SDM Polnest</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f5f7fa; color: #333; }
        .app-container { display: flex; min-height: 100vh; }

        .main-content { margin-left: 280px; padding: 30px; flex: 1; width: calc(100% - 280px); }

        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-start; }
        .header-left h1 { font-size: 28px; font-weight: 700; color: #1a1a1a; margin-bottom: 5px; }
        .header-left p { font-size: 14px; color: #666; }

        .btn-primary {
            background: #3b82f6; color: white; border: none; padding: 12px 24px;
            border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: background 0.3s; text-decoration: none;
        }
        .btn-primary:hover { background: #2563eb; }

        .table-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

        table { width: 100%; border-collapse: collapse; }
        thead { background-color: #f8fafc; }
        thead th {
            padding: 14px 16px; text-align: left; font-size: 13px; font-weight: 600;
            color: #475569; text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        tbody tr { border-bottom: 1px solid #e2e8f0; transition: background 0.2s; }
        tbody tr:hover { background-color: #f8fafc; }
        tbody td { padding: 16px; font-size: 14px; color: #334155; }

        .badge {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 6px 12px; border-radius: 6px; font-size: 12px;
            font-weight: 600; min-width: 32px; gap: 6px;
        }
        .badge-primary { background-color: #dbeafe; color: #1e40af; }
        .badge-gray { background-color: #f1f5f9; color: #475569; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }

        .action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-icon {
            width: 32px; height: 32px; border-radius: 6px; border: none;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; font-size: 14px;
        }
        .btn-view { background-color: #dbeafe; color: #1e40af; }
        .btn-view:hover { background-color: #bfdbfe; }
        .btn-edit { background-color: #d1fae5; color: #065f46; }
        .btn-edit:hover { background-color: #a7f3d0; }
        .btn-delete { background-color: #fee2e2; color: #991b1b; }
        .btn-delete:hover { background-color: #fecaca; }
        .btn-toggle { background-color: #fef3c7; color: #92400e; }
        .btn-toggle:hover { background-color: #fde68a; }
        .btn-toggle.active { background-color: #d1fae5; color: #065f46; }
        .btn-toggle.active:hover { background-color: #a7f3d0; }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
        .empty-state h3 { font-size: 18px; color: #475569; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; color: #94a3b8; margin-bottom: 24px; }

        .status-aktif { background-color: #d1fae5; color: #065f46; }
        .status-ditutup { background-color: #fee2e2; color: #991b1b; }
        .status-draft { background-color: #f1f5f9; color: #475569; }

        .status-wrapper { display: flex; flex-direction: column; gap: 4px; }
        .status-reason { font-size: 11px; color: #94a3b8; font-style: italic; }

        .text-muted { color: #94a3b8; font-style: italic; }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; width: 100%; }
            .page-header { flex-direction: column; gap: 16px; }
        }
        @media (max-width: 768px) {
            .table-card { overflow-x: auto; }
            table { min-width: 1000px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../sidebar/sidebar.php'; ?>

        <main class="main-content">
            <!-- Flash Message -->
            <?php if ($flash_message): ?>
                <script>
                    Swal.fire({
                        icon: '<?= $flash_type === 'success' ? 'success' : ($flash_type === 'error' ? 'error' : 'info') ?>',
                        title: '<?= $flash_type === 'success' ? 'Berhasil!' : ($flash_type === 'error' ? 'Gagal!' : 'Informasi') ?>',
                        text: '<?= addslashes($flash_message) ?>',
                        showConfirmButton: true,
                        timer: 3000,
                        timerProgressBar: true,
                        toast: true,
                        position: 'top-end'
                    });
                </script>
            <?php endif; ?>

            <div class="page-header">
                <div class="header-left">
                    <h1>Manajemen Lowongan Kerja</h1>
                    <p>Kelola posting lowongan pekerjaan</p>
                </div>
                <a href="createloker.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Tambah Lowongan
                </a>
            </div>

            <div class="table-card">
                <?php if (empty($data_lowongan)): ?>
                    <div class="empty-state">
                        <i class="fas fa-briefcase"></i>
                        <h3>Belum Ada Lowongan</h3>
                        <p>Mulai tambahkan lowongan pekerjaan dengan klik tombol "Tambah Lowongan"</p>
                        <a href="createloker.php" class="btn-primary">
                            <i class="fas fa-plus"></i> Tambah Lowongan
                        </a>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Posisi</th>
                                <th>Kualifikasi</th>
                                <th>Formasi</th>
                                <th>Pendaftar</th>
                                <th>Batas Waktu</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data_lowongan as $index => $lowongan): ?>
                                <?php
                                    $jumlah_pendaftar = $data_pendaftar[$lowongan['lowongan_id']] ?? 0;
                                    $status_class = 'status-' . strtolower($lowongan['status']);
                                    
                                    // Determine status reason
                                    $status_reason = '';
                                    if ($lowongan['status'] === 'ditutup') {
                                        if ($lowongan['is_active'] == 0) {
                                            $status_reason = 'Dinonaktifkan manual';
                                        } elseif ($lowongan['is_full']) {
                                            $status_reason = 'Formasi penuh (' . $lowongan['jumlah_diterima'] . '/' . $lowongan['formasi'] . ')';
                                        } elseif ($lowongan['is_expired']) {
                                            $status_reason = 'Deadline lewat';
                                        }
                                    }

                                    // FIX: guard NULL deadline_lamaran
                                    $deadline = !empty($lowongan['deadline_lamaran'])
                                        ? date('d/m/Y', strtotime($lowongan['deadline_lamaran']))
                                        : null;
                                    
                                    // Days remaining
                                    $days_text = '';
                                    if ($lowongan['days_remaining'] !== null) {
                                        if ($lowongan['days_remaining'] < 0) {
                                            $days_text = '<span style="color: #991b1b; font-weight: 600;">Lewat ' . abs($lowongan['days_remaining']) . ' hari</span>';
                                        } elseif ($lowongan['days_remaining'] <= 3) {
                                            $days_text = '<span style="color: #f59e0b; font-weight: 600;">' . $lowongan['days_remaining'] . ' hari lagi</span>';
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($lowongan['posisi']) ?></strong></td>
                                    <td><?= htmlspecialchars(substr($lowongan['kualifikasi'] ?? '', 0, 50)) ?>...</td>
                                    <td>
                                        <span class="badge <?= $lowongan['is_full'] ? 'badge-danger' : 'badge-primary' ?>">
                                            <?= $lowongan['jumlah_diterima'] ?>/<?= $lowongan['formasi'] ?>
                                        </span>
                                        <?php if ($lowongan['is_full']): ?>
                                            <div style="font-size: 11px; color: #991b1b; margin-top: 4px;">
                                                <i class="fas fa-check-circle"></i> Penuh
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-gray">
                                            <i class="fas fa-users"></i> <?= $jumlah_pendaftar ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $deadline ?? '<span class="text-muted">Belum ditentukan</span>' ?>
                                        <?php if ($days_text): ?>
                                            <div style="font-size: 11px; margin-top: 4px;">
                                                <?= $days_text ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="status-wrapper">
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($lowongan['status']) ?>
                                            </span>
                                            <?php if ($status_reason): ?>
                                                <span class="status-reason"><?= $status_reason ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-view" onclick="viewDetail(<?= $lowongan['lowongan_id'] ?>)" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon btn-edit" onclick="editLowongan(<?= $lowongan['lowongan_id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($lowongan['is_active'] == 1): ?>
                                                <button class="btn-icon btn-toggle" onclick="toggleStatus(<?= $lowongan['lowongan_id'] ?>, 'deactivate')" title="Nonaktifkan">
                                                    <i class="fas fa-toggle-on"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-icon btn-toggle active" onclick="toggleStatus(<?= $lowongan['lowongan_id'] ?>, 'activate')" title="Aktifkan">
                                                    <i class="fas fa-toggle-off"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn-icon btn-delete" onclick="deleteLowongan(<?= $lowongan['lowongan_id'] ?>)" title="Hapus Permanen">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function viewDetail(id) {
            window.location.href = 'detailloker.php?id=' + id;
        }
        
        function editLowongan(id) {
            window.location.href = 'editloker.php?id=' + id;
        }
        
        function toggleStatus(id, action) {
            const actionText = action === 'activate' ? 'mengaktifkan' : 'menonaktifkan';
            const actionColor = action === 'activate' ? '#10b981' : '#f59e0b';
            
            Swal.fire({
                title: 'Konfirmasi',
                text: `Apakah Anda yakin ingin ${actionText} lowongan ini?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: '#6b7280',
                confirmButtonText: `Ya, ${actionText}!`,
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `loker.php?toggle_id=${id}&action=${action}`;
                }
            });
        }
        
        function deleteLowongan(id) {
            Swal.fire({
                title: 'Hapus Permanen?',
                html: 'Apakah Anda yakin ingin menghapus lowongan ini secara <strong>PERMANEN</strong>?<br><br><small class="text-muted">Tindakan ini tidak dapat dibatalkan!</small>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                showLoaderOnConfirm: true,
                preConfirm: () => {
                    return Swal.fire({
                        title: 'Konfirmasi Akhir',
                        text: 'Ini adalah konfirmasi terakhir. Hapus lowongan?',
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'HAPUS SEKARANG',
                        cancelButtonText: 'Batalkan',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'deleteloker.php?id=' + id;
                        }
                        return false;
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            });
        }
    </script>
</body>
</html>