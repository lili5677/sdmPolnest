<?php
// pagination
$items_per_page = 9; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); 
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
    .content-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid #f1f5f9;
        gap: 12px;
    }

    .content-card-title {
        font-size: 17px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        flex: 1;
        min-width: 0;
    }

    .btn-kelola-template {
        padding: 9px 16px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
        white-space: nowrap;
        flex-shrink: 0;
    }

    .btn-kelola-template:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(59, 130, 246, 0.35);
        color: white;
    }

    /* search dan filter  */
    .controls-row {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .search-box {
        flex: 1;
        min-width: 220px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 10px 14px 10px 38px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
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
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
    }

    .filter-select {
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13px;
        font-family: 'Poppins', sans-serif;
        color: #475569;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 160px;
    }

    .filter-select:focus {
        outline: none;
        border-color: #3b82f6;
    }

    /* card pengajuan */
    .pengajuan-list {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .pengajuan-card {
        background: white;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
    }

    .pengajuan-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
        transform: translateY(-2px);
    }

    .pengajuan-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
        gap: 8px;
    }

    .pengajuan-info {
        flex: 1;
        min-width: 0;
    }

    .pengajuan-title {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 2px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pengajuan-id {
        font-size: 10px;
        color: #64748b;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 9px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        flex-shrink: 0;
    }

    .status-badge i {
        font-size: 8px;
    }

    .status-badge.pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
        border: 1px solid #fcd34d;
    }

    .status-badge.approved {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border: 1px solid #6ee7b7;
    }

    .status-badge.rejected {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #991b1b;
        border: 1px solid #fca5a5;
    }

    .pengajuan-details {
        margin-bottom: 10px;
        flex: 1;
    }

    .detail-item {
        display: flex;
        align-items: baseline;
        gap: 4px;
        margin-bottom: 6px;
    }

    .detail-item:last-child {
        margin-bottom: 0;
    }

    .detail-label {
        font-size: 9px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        flex-shrink: 0;
        min-width: 50px;
    }

    .detail-value {
        font-size: 11px;
        color: #1e293b;
        font-weight: 500;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        flex: 1;
    }

    .pengajuan-meta {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 10px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
    }

    .meta-tag {
        padding: 3px 6px;
        background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
        color: #3730a3;
        border-radius: 4px;
        font-size: 8px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 3px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .meta-tag i {
        font-size: 7px;
    }

    .pengajuan-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .btn-custom {
        padding: 6px 10px;
        border: none;
        border-radius: 6px;
        font-size: 9px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: 'Poppins', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        flex: 1;
        justify-content: center;
        min-width: 0;
    }

    .btn-custom i {
        font-size: 8px;
    }

    .btn-detail {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        color: #1e40af;
        border: 1px solid #bfdbfe;
        flex: 1 1 100%;
    }

    .btn-detail:hover {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
    }

    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 50px 20px;
        color: #94a3b8;
    }

    .empty-state i {
        font-size: 56px;
        margin-bottom: 12px;
        opacity: 0.3;
    }

    .empty-state p {
        font-size: 14px;
        font-weight: 500;
    }

    /* paginasi */
    .pagination-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #e2e8f0;
        flex-wrap: wrap;
        gap: 12px;
    }

    .pagination-info {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }

    .pagination {
        display: flex;
        gap: 4px;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .pagination li a {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        color: #475569;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .pagination li a:hover {
        border-color: #3b82f6;
        background: #eff6ff;
        color: #3b82f6;
        transform: translateY(-1px);
    }

    .pagination li.active a {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-color: #3b82f6;
        color: white;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
    }

    .pagination li.active a:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    }

    .pagination li.disabled a {
        opacity: 0.4;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* responsif*/
    @media (max-width: 1440px) {
        .pengajuan-list {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 1200px) {
        .pengajuan-list {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .pengajuan-list {
            grid-template-columns: 1fr;
        }

        .content-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .btn-kelola-template {
            width: 100%;
            justify-content: center;
        }

        .btn-custom {
            font-size: 10px;
            padding: 7px 11px;
        }

        .detail-label {
            min-width: 60px;
        }
    }

    @media (max-width: 480px) {
        .detail-item {
            flex-direction: column;
            gap: 2px;
        }

        .detail-label {
            min-width: auto;
        }
    }
</style>

<div class="content-card">
    <div class="content-card-header">
        <h3 class="content-card-title">
            Data Pengajuan Studi Lanjut
        </h3>
        <a href="kelolatemplate.php" class="btn-kelola-template">
            Kelola Template
        </a>
    </div>

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
                <div class="pengajuan-info">
                    <div class="pengajuan-title" title="<?php echo htmlspecialchars($row['nama_lengkap']); ?>">
                        <?php echo htmlspecialchars($row['nama_lengkap']); ?>
                    </div>
                    <div class="pengajuan-id">NIK: <?php echo htmlspecialchars($row['nik']); ?></div>
                </div>
                <span class="status-badge <?php echo $status_class; ?>">
                    <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                </span>
            </div>

            <div class="pengajuan-details">
                <div class="detail-item">
                    <div class="detail-label">Jabatan:</div>
                    <div class="detail-value" title="<?php echo htmlspecialchars($row['jabatan'] ?? '-'); ?>">
                        <?php echo htmlspecialchars($row['jabatan'] ?? '-'); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Jenjang:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($row['jenjang_pendidikan']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Institusi:</div>
                    <div class="detail-value" title="<?php echo htmlspecialchars($row['nama_institusi']); ?>">
                        <?php echo htmlspecialchars($row['nama_institusi']); ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Prodi:</div>
                    <div class="detail-value" title="<?php echo htmlspecialchars($row['program_studi']); ?>">
                        <?php echo htmlspecialchars($row['program_studi']); ?>
                    </div>
                </div>
            </div>

            <div class="pengajuan-meta">
                <span class="meta-tag">
                    <i class="fas fa-graduation-cap"></i> <?php echo strtoupper($row['jenjang_pendidikan']); ?>
                </span>
                <span class="meta-tag">
                    <i class="fas fa-calendar"></i> <?php echo $created_at; ?>
                </span>
            </div>

            <div class="pengajuan-actions">
                <button class="btn-custom btn-detail" onclick="viewDetail(<?php echo $row['pengajuan_id']; ?>)">
                    <i class="fas fa-eye"></i> Detail
                </button>
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
    // fungsi search 
    document.getElementById('searchInput')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const cards = document.querySelectorAll('.pengajuan-card');
        
        cards.forEach(card => {
            const name = card.querySelector('.pengajuan-title').textContent.toLowerCase();
            const nik = card.querySelector('.pengajuan-id').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || nik.includes(searchTerm)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Filter dnegan status
    document.getElementById('filterStatus')?.addEventListener('change', function(e) {
        const status = e.target.value;
        const cards = document.querySelectorAll('.pengajuan-card');
        
        cards.forEach(card => {
            if (status === '') {
                card.style.display = 'flex';
            } else {
                const cardStatus = card.getAttribute('data-status');
                if (cardStatus === status) {
                    card.style.display = 'flex';
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
</script>