<?php
// Ambil data pengajuan dengan join ke tabel pegawai dan status_kepegawaian
$query_pengajuan = "SELECT 
    ps.*,
    p.email,
    p.nik,
    p.nama_lengkap,
    sk.jabatan,
    sk.unit_kerja
FROM pengajuan_studi ps
JOIN pegawai p ON ps.pegawai_id = p.pegawai_id
LEFT JOIN status_kepegawaian sk ON p.pegawai_id = sk.pegawai_id
ORDER BY ps.created_at DESC";

$stmt_pengajuan = $conn->prepare($query_pengajuan);
$stmt_pengajuan->execute();
?>

<style>
    /* ===== SEARCH & FILTER ===== */
    .controls-row {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .search-box i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 16px;
    }

    .filter-select {
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        font-family: 'Poppins', sans-serif;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 180px;
    }

    .filter-select:focus {
        outline: none;
        border-color: #3b82f6;
    }

    /* ===== PENGAJUAN CARDS ===== */
    .pengajuan-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .pengajuan-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 24px;
        transition: all 0.3s ease;
    }

    .pengajuan-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .pengajuan-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
        gap: 16px;
        flex-wrap: wrap;
    }

    .pengajuan-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .pengajuan-id {
        font-size: 13px;
        color: #64748b;
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-badge.approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .pengajuan-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-bottom: 16px;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .detail-label {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }

    .detail-value {
        font-size: 14px;
        color: #1e293b;
        font-weight: 600;
    }

    .pengajuan-tags {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .tag {
        padding: 4px 12px;
        background: #e0e7ff;
        color: #3730a3;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    .pengajuan-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-custom {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-detail {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-detail:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .btn-approve {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .btn-approve:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }

    .btn-reject {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    .btn-reject:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
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
</style>

<div class="content-card">
    <!-- Search & Filter -->
    <div class="controls-row">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Cari nama atau NIK pegawai..." id="searchInput">
        </div>
        <select class="filter-select" id="filterStatus">
            <option value="">Semua Status</option>
            <option value="diajukan">Diajukan</option>
            <option value="ditinjau">Ditinjau</option>
            <option value="menunggu_persetujuan">Menunggu Persetujuan</option>
            <option value="disetujui">Disetujui</option>
            <option value="ditolak">Ditolak</option>
        </select>
    </div>

    <!-- Pengajuan List -->
    <div class="pengajuan-list">
        <?php 
        $pengajuan_data = $stmt_pengajuan->fetchAll(PDO::FETCH_ASSOC);
        if (count($pengajuan_data) > 0) {
            foreach ($pengajuan_data as $row) {
                // Tentukan status badge
                $status_class = '';
                $status_icon = '';
                $status_text = '';
                
                switch ($row['status_pengajuan']) {
                    case 'diajukan':
                    case 'ditinjau':
                    case 'menunggu_persetujuan':
                        $status_class = 'pending';
                        $status_icon = 'fa-clock';
                        $status_text = 'Menunggu';
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
                }
                
                // Format tanggal
                $tanggal_mulai = $row['tanggal_mulai_studi'] ? date('d/m/Y', strtotime($row['tanggal_mulai_studi'])) : '-';
                $created_at = date('d/m/Y', strtotime($row['created_at']));
        ?>
        <div class="pengajuan-card" data-status="<?php echo $row['status_pengajuan']; ?>">
            <div class="pengajuan-header">
                <div>
                    <div class="pengajuan-title"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                    <div class="pengajuan-id">NIK: <?php echo htmlspecialchars($row['nik']); ?></div>
                </div>
                <span class="status-badge <?php echo $status_class; ?>">
                    <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                </span>
            </div>

            <div class="pengajuan-details">
                <div class="detail-item">
                    <div class="detail-label">Jabatan</div>
                    <div class="detail-value"><?php echo htmlspecialchars($row['jabatan'] ?? '-'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Jenjang</div>
                    <div class="detail-value"><?php echo htmlspecialchars($row['jenjang_pendidikan']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Institusi</div>
                    <div class="detail-value"><?php echo htmlspecialchars($row['nama_institusi']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Program Studi</div>
                    <div class="detail-value"><?php echo htmlspecialchars($row['program_studi']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Mulai Studi</div>
                    <div class="detail-value"><?php echo $tanggal_mulai; ?></div>
                </div>
            </div>

            <div class="pengajuan-tags">
                <span class="tag"><i class="fas fa-graduation-cap"></i> <?php echo strtoupper($row['jenjang_pendidikan']); ?></span>
                <span class="tag"><i class="fas fa-calendar"></i> <?php echo $created_at; ?></span>
            </div>

            <div class="pengajuan-actions">
                <button class="btn-custom btn-detail" onclick="viewDetail(<?php echo $row['pengajuan_id']; ?>)">
                    <i class="fas fa-eye"></i> Detail
                </button>
                <?php if (in_array($row['status_pengajuan'], ['diajukan', 'ditinjau', 'menunggu_persetujuan'])) { ?>
                <button class="btn-custom btn-approve" onclick="approveRequest(<?php echo $row['pengajuan_id']; ?>)">
                    <i class="fas fa-check"></i> Setujui
                </button>
                <button class="btn-custom btn-reject" onclick="rejectRequest(<?php echo $row['pengajuan_id']; ?>)">
                    <i class="fas fa-times"></i> Tolak
                </button>
                <?php } ?>
            </div>
        </div>
        <?php 
            }
        } else { 
        ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Belum ada pengajuan studi lanjut</p>
        </div>
        <?php } ?>
    </div>
</div>

<script>
    // Search functionality
    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.pengajuan-card');
        
        cards.forEach(card => {
            const name = card.querySelector('.pengajuan-title').textContent.toLowerCase();
            const nik = card.querySelector('.pengajuan-id').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || nik.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Filter by status
    document.getElementById('filterStatus')?.addEventListener('change', function(e) {
        const status = e.target.value;
        const cards = document.querySelectorAll('.pengajuan-card');
        
        cards.forEach(card => {
            if (status === '') {
                card.style.display = 'block';
            } else {
                const cardStatus = card.getAttribute('data-status');
                if (cardStatus === status) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            }
        });
    });

    // View detail
    function viewDetail(id) {
        window.location.href = 'detail_pengajuan.php?id=' + id;
    }

    // Approve request
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

    // Reject request
    function rejectRequest(id) {
        Swal.fire({
            title: 'Tolak Pengajuan',
            text: 'Masukkan alasan penolakan:',
            input: 'textarea',
            inputPlaceholder: 'Tulis alasan penolakan di sini...',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-times"></i> Tolak',
            cancelButtonText: 'Batal',
            inputValidator: (value) => {
                if (!value) {
                    return 'Alasan penolakan harus diisi!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'proses_pengajuan.php?action=reject&id=' + id + '&reason=' + encodeURIComponent(result.value);
            }
        });
    }
</script>