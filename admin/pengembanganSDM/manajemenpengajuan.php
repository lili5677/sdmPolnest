<?php
// ===== PAGINATION CONFIGURATION =====
$items_per_page = 5; // Jumlah data per halaman
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Minimal halaman 1
$offset = ($current_page - 1) * $items_per_page;

// Hitung total data
$query_count = "SELECT COUNT(*) as total FROM pengajuan_studi ps
                JOIN pegawai p ON ps.pegawai_id = p.pegawai_id";
$stmt_count = $conn->prepare($query_count);
$stmt_count->execute();
$total_items = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_items / $items_per_page);

// Ambil data pengajuan dengan pagination
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
ORDER BY ps.created_at DESC
LIMIT :limit OFFSET :offset";

$stmt_pengajuan = $conn->prepare($query_pengajuan);
$stmt_pengajuan->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt_pengajuan->bindValue(':offset', $offset, PDO::PARAM_INT);
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

    /* ===== PENGAJUAN CARDS - GRID LAYOUT ===== */
    .pengajuan-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .pengajuan-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
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
        margin-bottom: 12px;
        gap: 12px;
        flex-wrap: wrap;
    }

    .pengajuan-title {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .pengajuan-id {
        font-size: 12px;
        color: #64748b;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
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
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .detail-label {
        font-size: 11px;
        color: #64748b;
        font-weight: 500;
    }

    .detail-value {
        font-size: 13px;
        color: #1e293b;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pengajuan-tags {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 12px;
    }

    .tag {
        padding: 3px 10px;
        background: #e0e7ff;
        color: #3730a3;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
    }

    .pengajuan-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-custom {
        padding: 8px 14px;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
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
        grid-column: 1 / -1;
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

    /* ===== PAGINATION ===== */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 2px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 16px;
    }

    .pagination-info {
        font-size: 14px;
        color: #64748b;
    }

    .pagination {
        display: flex;
        gap: 8px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .pagination li a {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        color: #475569;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .pagination li a:hover {
        border-color: #3b82f6;
        background: #eff6ff;
        color: #3b82f6;
    }

    .pagination li.active a {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .pagination li.disabled a {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1024px) {
        .pengajuan-list {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .pengajuan-details {
            grid-template-columns: 1fr;
        }
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
                        $status_class = 'pending';
                        $status_icon = 'fa-clock';
                        $status_text = 'Diajukan';
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

            <div class="detail-item">
                <div class="detail-label">Jabatan</div>
                <div class="detail-value"><?php echo htmlspecialchars($row['jabatan'] ?: '-'); ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Jenjang</div>
                <div class="detail-value"><?php echo htmlspecialchars($row['jenjang_pendidikan'] ?: '-'); ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Institusi</div>
                <div class="detail-value"><?php echo htmlspecialchars($row['nama_institusi'] ?: '-'); ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Program Studi</div>
                <div class="detail-value"><?php echo htmlspecialchars($row['program_studi'] ?: '-'); ?></div>
            </div>

            <div class="pengajuan-tags">
                <span class="tag"><i class="fas fa-graduation-cap"></i> <?php echo strtoupper($row['jenjang_pendidikan']); ?></span>
                <span class="tag"><i class="fas fa-calendar"></i> <?php echo $created_at; ?></span>
            </div>

            <div class="pengajuan-actions">
                <button class="btn-custom btn-detail" onclick="viewDetail(<?php echo $row['pengajuan_id']; ?>)">
                    <i class="fas fa-eye"></i> Detail
                </button>
                <?php if ($row['status_pengajuan'] == 'diajukan') { ?>
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

    <!-- Pagination -->
    <?php if ($total_pages > 1) { ?>
    <div class="pagination-wrapper">
        <div class="pagination-info">
            Menampilkan <?php echo $offset + 1; ?> - <?php echo min($offset + $items_per_page, $total_items); ?> dari <?php echo $total_items; ?> data
        </div>
        <ul class="pagination">
            <!-- Previous Button -->
            <li class="<?php echo $current_page == 1 ? 'disabled' : ''; ?>">
                <a href="?tab=pengajuan&page=<?php echo $current_page - 1; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>

            <!-- Page Numbers -->
            <?php
            $start_page = max(1, $current_page - 2);
            $end_page = min($total_pages, $current_page + 2);

            if ($start_page > 1) {
                echo '<li><a href="?tab=pengajuan&page=1">1</a></li>';
                if ($start_page > 2) {
                    echo '<li class="disabled"><a href="#">...</a></li>';
                }
            }

            for ($i = $start_page; $i <= $end_page; $i++) {
                $active = $i == $current_page ? 'active' : '';
                echo '<li class="' . $active . '"><a href="?tab=pengajuan&page=' . $i . '">' . $i . '</a></li>';
            }

            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1) {
                    echo '<li class="disabled"><a href="#">...</a></li>';
                }
                echo '<li><a href="?tab=pengajuan&page=' . $total_pages . '">' . $total_pages . '</a></li>';
            }
            ?>

            <!-- Next Button -->
            <li class="<?php echo $current_page == $total_pages ? 'disabled' : ''; ?>">
                <a href="?tab=pengajuan&page=<?php echo $current_page + 1; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </div>
    <?php } ?>
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