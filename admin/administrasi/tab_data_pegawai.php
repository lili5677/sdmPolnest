<style>
    /* STYLING UNTUK DATA PEGAWAI */
    /* Input Group Custom */
    .input-group-text {
        border-right: 0;
    }

    .input-group .form-control:focus {
        border-left: 1px solid #2563eb;
    }

    /* Card Styling */
    .content-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 30px;
        margin-bottom: 20px;
    }

    .card-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .card-title-custom {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 5px;
    }

    .card-title-custom i {
        margin-right: 10px;
        color: #2563eb;
    }

    .card-description {
        color: #6b7280;
        font-size: 14px;
        margin: 0;
    }

    /* Buttons */
    .btn-custom {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 13px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-custom i {
        font-size: 16px;
    }

    .btn-primary-custom {
        background: #1f2937;
        color: white;
        border: none;
    }

    .btn-primary-custom:hover {
        background: #374151;
        color: white;
    }

    .btn-outline-custom {
        background: white;
        border: 1px solid #d1d5db;
        color: #374151;
    }

    .btn-outline-custom:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }

    /* Table Responsive */
    .table-responsive {
        border-radius: 8px;
        overflow-x: auto;
        overflow-y: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    .table-pegawai {
        margin-bottom: 0;
        font-size: 13px;
        width: 100%;
        table-layout: fixed;
    }

    .table-pegawai thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table-pegawai thead th {
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        font-weight: 700;
        color: white !important;
        border: none;
        padding: 14px 10px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
        vertical-align: middle;
    }

    .table-pegawai tbody td {
        padding: 12px 10px;
        vertical-align: middle;
        border-bottom: 1px solid #f3f4f6;
        color: #374151;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .table-pegawai tbody tr {
        transition: all 0.2s;
    }

    .table-pegawai tbody tr:hover {
        background: #f9fafb;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    /* Badge Styling */
    .badge-custom {
        padding: 5px 12px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        white-space: nowrap;
        display: inline-block;
    }

    .badge-kontrak {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .badge-tetap {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .badge-aktif {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .badge-tidak-aktif {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 4px;
        justify-content: center;
    }

    .btn-action {
        padding: 6px 10px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-view {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
    }

    .btn-view:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
    }

    .btn-edit-table {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.3);
    }

    .btn-edit-table:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-delete-table {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
    }

    .btn-delete-table:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    /* Nama Pegawai Bold */
    .nama-pegawai {
        font-weight: 600;
        color: #1f2937;
        display: block;
        margin-bottom: 0;
    }

    /* Sisa Kontrak Warning */
    .sisa-kontrak-warning {
        color: #dc2626 !important;
        font-weight: 700 !important;
        background: #fee2e2;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    .sisa-kontrak-normal {
        color: #374151;
    }

    /* Loading */
    .spinner-container {
        text-align: center;
        padding: 40px;
    }

    .spinner-border {
        color: #2563eb;
    }

    /* Empty State Enhancement */
    .empty-state-pegawai {
        text-align: center;
        padding: 80px 20px;
        color: #6b7280;
    }

    .empty-state-pegawai i {
        font-size: 80px;
        color: #d1d5db;
        margin-bottom: 25px;
        opacity: 0.7;
    }

    .empty-state-pegawai h4 {
        color: #374151;
        font-size: 20px;
        margin-bottom: 12px;
        font-weight: 600;
    }

    .empty-state-pegawai p {
        color: #9ca3af;
        font-size: 15px;
        margin-bottom: 25px;
    }

    .empty-state-pegawai .btn {
        margin-top: 10px;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 8px 12px;
        font-size: 12px;
    }

    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    /* Pagination Pegawai */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pagination-info {
        font-size: 13px;
        color: #6b7280;
    }

    .pagination-info strong {
        color: #1f2937;
    }

    .pagination-controls {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
    }

    .btn-page {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-page:hover {
        border-color: #2563eb;
        color: #2563eb;
        background: #eff6ff;
    }

    .btn-page.active {
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        border-color: #1e40af;
        color: white;
    }

    .btn-page.active:hover {
        background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        color: white;
    }

    .btn-page:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .btn-page:disabled:hover {
        border-color: #d1d5db;
        color: #374151;
        background: white;
    }

    .btn-page-arrow {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        font-size: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-page-arrow:hover {
        border-color: #2563eb;
        color: #2563eb;
        background: #eff6ff;
    }

    .btn-page-arrow:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .btn-page-arrow:disabled:hover {
        border-color: #d1d5db;
        color: #374151;
        background: white;
    }

    .page-dots {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #9ca3af;
        font-size: 14px;
        font-weight: 600;
    }

    /* Table Responsive Mobile */
    @media (max-width: 768px) {
        .table-pegawai {
            font-size: 10px;
        }
        
        .table-pegawai thead th,
        .table-pegawai tbody td {
            padding: 8px 6px;
        }
        
        .form-control, .form-select {
            font-size: 10px; 
            padding: 7px 10px;
        }
        
        .input-group-text {
            font-size: 10px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table-pegawai {
            min-width: 1200px;
        }

        .btn-action {
            padding: 5px 8px;
            font-size: 11px;
        }
        
        .badge-custom {
            font-size: 9px;
            padding: 3px 7px;
        }

        .pagination-container {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
    }
</style>

<div class="content-card">
    <div class="card-header-custom">
        <div>
            <h3 class="card-title-custom">
                <i class="fas fa-address-book"></i>
                Data Pegawai
            </h3>
            <p class="card-description">Kelola informasi lengkap pegawai Anda</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-custom" onclick="refreshDataPegawai()">
                <i class="fas fa-sync-alt"></i>
                Refresh
            </button>
            <button class="btn btn-primary-custom" onclick="tambahPegawai()">
                <i class="fas fa-plus"></i>
                Tambah Pegawai
            </button>
        </div>
    </div>
    
    <!-- Search & Filter -->
    <div class="mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0" id="searchPegawai" 
                        placeholder="Cari nama pegawai..." 
                        onkeyup="filterPegawai()">
                </div>
            </div>
            <div class="col-md-4">
                <select class="form-select" id="filterJenisPegawai" onchange="filterPegawai()">
                    <option value="">Semua Jenis Pegawai</option>
                    <option value="dosen">Dosen</option>
                    <option value="staff">Staff</option>
                    <option value="tendik">Tendik</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="filterJenisKepegawaian" onchange="filterPegawai()">
                    <option value="">Semua Pegawai</option>
                    <option value="tetap">Tetap</option>
                    <option value="kontrak">Kontrak</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="filterStatusAktif" onchange="filterPegawai()">
                    <option value="">Status</option>
                    <option value="aktif">Aktif</option>
                    <option value="tidak_aktif">Tidak Aktif</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Data Pegawai Container -->
    <div id="data-pegawai-container">
        <div class="spinner-container">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Memuat data pegawai...</p>
        </div>
    </div>
</div>

<script>
    // ===== VARIABEL GLOBAL PEGAWAI =====
    let allDataPegawai = [];         // semua data dari server
    let filteredDataPegawai = [];    // setelah filter diterapkan
    let currentPagePegawai = 1;
    const PAGE_SIZE_PEGAWAI = 10;

    // ===== LOAD DATA DARI SERVER =====
    async function loadDataPegawai() {
        const container = document.getElementById('data-pegawai-container');
        
        container.innerHTML = `
            <div class="spinner-container">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Memuat data pegawai dari database...</p>
            </div>
        `;
        
        try {
            const response = await fetch('?action=get_all_pegawai');
            const result = await response.json();
            
            if(result.success && result.data.length > 0) {
                allDataPegawai = result.data;
                filteredDataPegawai = [...allDataPegawai];
                currentPagePegawai = 1;
                renderTablePegawai();
            } else {
                allDataPegawai = [];
                filteredDataPegawai = [];
                displayEmptyStatePegawai();
            }
        } catch(error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memuat Data',
                text: 'Terjadi kesalahan saat memuat data pegawai',
                confirmButtonColor: '#2563eb'
            });
            displayEmptyStatePegawai();
        }
    }

    // ===== HELPER: Hitung Sisa Kontrak =====
    function hitungSisaKontrak(sisaHari) {
        if (!sisaHari || sisaHari <= 0) {
            return { text: '-', isWarning: false };
        }
        
        const bulan = Math.floor(sisaHari / 30);
        const hari = sisaHari % 30;
        
        let text = '';
        if (bulan > 0 && hari > 0) {
            text = `${bulan} Bulan ${hari} Hari`;
        } else if (bulan > 0) {
            text = `${bulan} Bulan`;
        } else {
            text = `${hari} Hari`;
        }
        
        return { text: text, isWarning: sisaHari < 30 };
    }

    // ===== RENDER TABEL + PAGINATION =====
    function renderTablePegawai() {
        const container = document.getElementById('data-pegawai-container');
        const totalPages = Math.ceil(filteredDataPegawai.length / PAGE_SIZE_PEGAWAI);

        // Kalau tidak ada data setelah filter
        if (filteredDataPegawai.length === 0) {
            container.innerHTML = `
                <div class="empty-state-pegawai">
                    <i class="fas fa-search"></i>
                    <h4>Tidak Ditemukan</h4>
                    <p>Tidak ada data pegawai yang sesuai dengan filter</p>
                </div>
            `;
            return;
        }

        // Kalau page out of range, reset ke 1
        if (currentPagePegawai > totalPages) {
            currentPagePegawai = 1;
        }

        // Slice data untuk halaman sekarang
        const startIndex = (currentPagePegawai - 1) * PAGE_SIZE_PEGAWAI;
        const endIndex = startIndex + PAGE_SIZE_PEGAWAI;
        const pageData = filteredDataPegawai.slice(startIndex, endIndex);

        // Build tabel
        let html = `
            <div class="table-responsive">
                <table class="table table-pegawai">
                    <thead>
                        <tr>
                            <th style="width: 40px;">NO</th>
                            <th style="width: 200px;">NAMA LENGKAP</th>
                            <th style="width: 150px;">JABATAN</th>
                            <th style="width: 100px;">PEGAWAI</th>
                            <th style="width: 100px;">STATUS</th>
                            <th style="width: 130px;">UNIT KERJA</th>
                            <th style="width: 120px;">SISA KONTRAK</th>
                            <th style="width: 140px;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-pegawai">
        `;

        pageData.forEach((pegawai, index) => {
            const jenisKepegawaian = (pegawai.jenis_kepegawaian || 'tetap').toLowerCase();
            const statusAktif = (pegawai.status_aktif || 'aktif').toLowerCase();

            // Badge Jenis Kepegawaian
            const jenisKepegawaianBadge = jenisKepegawaian === 'kontrak'
                ? '<span class="badge-custom badge-kontrak">Kontrak</span>'
                : '<span class="badge-custom badge-tetap">Tetap</span>';

            // Badge Status
            const statusAktifBadge = statusAktif === 'aktif'
                ? '<span class="badge-custom badge-aktif">Aktif</span>'
                : '<span class="badge-custom badge-tidak-aktif">Tidak Aktif</span>';

            // Sisa Kontrak
            let sisaKontrak = '-';
            let sisaKontrakClass = 'sisa-kontrak-normal';
            if (jenisKepegawaian === 'kontrak' && pegawai.sisa_hari_kontrak) {
                const kontrakInfo = hitungSisaKontrak(pegawai.sisa_hari_kontrak);
                sisaKontrak = kontrakInfo.text;
                sisaKontrakClass = kontrakInfo.isWarning ? 'sisa-kontrak-warning' : 'sisa-kontrak-normal';
            }

            // Nomor urut global (bukan reset per halaman)
            const nomor = startIndex + index + 1;

            html += `
                <tr>
                    <td class="text-center" style="font-weight: 600; color: #6b7280;">${nomor}</td>
                    <td>
                        <div class="nama-pegawai">${pegawai.nama_lengkap}</div>
                    </td>
                    <td style="font-size: 12px;">${pegawai.jabatan || '-'}</td>
                    <td class="text-center">${jenisKepegawaianBadge}</td>
                    <td class="text-center">${statusAktifBadge}</td>
                    <td style="font-size: 12px;">${pegawai.unit_kerja || '-'}</td>
                    <td class="text-center" style="font-size: 12px;">
                        <span class="${sisaKontrakClass}">${sisaKontrak}</span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-action btn-view" 
                                    onclick="lihatDetailPegawai(${pegawai.pegawai_id})" 
                                    title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-action btn-edit-table" 
                                    onclick="editPegawai(${pegawai.pegawai_id})" 
                                    title="Edit Data">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action btn-delete-table" 
                                    onclick="hapusPegawai(${pegawai.pegawai_id}, '${pegawai.nama_lengkap.replace(/'/g, "\\'")}')" 
                                    title="Hapus Data">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        // Pagination — hanya tampilkan kalau total data > 10
        html += `<div class="pagination-container">`;
        html += `<div class="pagination-info">
                    Menampilkan <strong>${startIndex + 1}–${Math.min(endIndex, filteredDataPegawai.length)}</strong> 
                    dari <strong>${filteredDataPegawai.length}</strong> pegawai
                 </div>`;

        if (totalPages > 1) {
            html += `<div class="pagination-controls" id="pagination-pegawai"></div>`;
        }

        html += `</div>`;

        container.innerHTML = html;

        // Render tombol pagination kalau > 1 halaman
        if (totalPages > 1) {
            renderPaginationButtons('pagination-pegawai', currentPagePegawai, totalPages, goToPagePegawai);
        }
    }

    // ===== RENDER TOMBOL PAGINATION (reusable) =====
    function renderPaginationButtons(containerId, currentPage, totalPages, callback) {
        const container = document.getElementById(containerId);
        let html = '';

        // Tombol "Sebelumnya"
        html += `<button class="btn-page-arrow" ${currentPage === 1 ? 'disabled' : ''} onclick="${callback.name}(${currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                 </button>`;

        // Logika tampil nomor halaman dengan dots
        const pages = getPageNumbers(currentPage, totalPages);
        pages.forEach(item => {
            if (item === '...') {
                html += `<span class="page-dots">...</span>`;
            } else {
                html += `<button class="btn-page ${item === currentPage ? 'active' : ''}" onclick="${callback.name}(${item})">${item}</button>`;
            }
        });

        // Tombol "Berikutnya"
        html += `<button class="btn-page-arrow" ${currentPage === totalPages ? 'disabled' : ''} onclick="${callback.name}(${currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                 </button>`;

        container.innerHTML = html;
    }

    // ===== HELPER: Tentukan nomor halaman yang ditampilkan =====
    // Logika: selalu tampil halaman 1 dan terakhir, dots kalau ada gap
    function getPageNumbers(current, total) {
        const pages = [];

        if (total <= 7) {
            // Kalau total halaman <= 7, tampilkan semua
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }

        // Selalu tampil halaman 1
        pages.push(1);

        if (current > 3) {
            pages.push('...');
        }

        // Tampilkan range di sekitar halaman sekarang
        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (current < total - 2) {
            pages.push('...');
        }

        // Selalu tampil halaman terakhir
        pages.push(total);

        return pages;
    }

    // ===== NAVIGASI HALAMAN (Data Pegawai) =====
    function goToPagePegawai(page) {
        const totalPages = Math.ceil(filteredDataPegawai.length / PAGE_SIZE_PEGAWAI);
        if (page < 1 || page > totalPages) return;
        currentPagePegawai = page;
        renderTablePegawai();
        // Scroll ke atas tabel
        document.querySelector('.table-pegawai')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ===== FILTER PEGAWAI =====
    function filterPegawai() {
        const searchValue = document.getElementById('searchPegawai').value.toLowerCase();
        const jenisPegawaiValue = document.getElementById('filterJenisPegawai').value.toLowerCase();
        const jenisKepegawaianValue = document.getElementById('filterJenisKepegawaian').value.toLowerCase();
        const statusAktifValue = document.getElementById('filterStatusAktif').value.toLowerCase();

        filteredDataPegawai = allDataPegawai.filter(pegawai => {
            const namaLengkap = (pegawai.nama_lengkap || '').toLowerCase();
            const jenisPegawai = (pegawai.jenis_pegawai || '').toLowerCase();
            const jenisKepegawaian = (pegawai.jenis_kepegawaian || 'tetap').toLowerCase();
            const statusAktif = (pegawai.status_aktif || 'aktif').toLowerCase();

            const matchSearch = namaLengkap.includes(searchValue);
            const matchJenisPegawai = !jenisPegawaiValue || jenisPegawai === jenisPegawaiValue;
            const matchJenisKepegawaian = !jenisKepegawaianValue || jenisKepegawaian === jenisKepegawaianValue;
            const matchStatusAktif = !statusAktifValue || statusAktif === statusAktifValue;

            return matchSearch && matchJenisPegawai && matchJenisKepegawaian && matchStatusAktif;
        });

        // Reset ke halaman 1 setiap kali filter berubah
        currentPagePegawai = 1;
        renderTablePegawai();
    }

    // ===== REDIRECT FUNCTIONS =====
    function tambahPegawai() {
        window.location.href = '../administrasi/tambah_pegawai.php';
    }

    function editPegawai(id) {
        window.location.href = `../administrasi/edit_pegawai.php?id=${id}`;
    }

    function lihatDetailPegawai(id) {
        window.location.href = `../administrasi/detail_pegawai.php?id=${id}`;
    }

    // ===== HAPUS PEGAWAI =====
    async function hapusPegawai(id, nama) {
        const result = await Swal.fire({
            title: 'Konfirmasi Hapus',
            html: `
                <p>Apakah Anda yakin ingin menghapus pegawai:</p>
                <p class="fw-bold text-danger">${nama}</p>
                <p class="text-muted small">Data yang sudah dihapus tidak dapat dikembalikan!</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Ya, Hapus!',
            cancelButtonText: '<i class="fas fa-times me-1"></i> Batal',
            reverseButtons: true
        });
        
        if(result.isConfirmed) {
            try {
                Swal.fire({
                    title: 'Menghapus Data...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                const response = await fetch(`../administrasi/hapus_pegawai.php?id=${id}`);
                const data = await response.json();
                
                if(data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil Dihapus!',
                        text: 'Data pegawai berhasil dihapus dari sistem',
                        confirmButtonColor: '#2563eb',
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        loadDataPegawai();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal Menghapus!',
                        text: data.message || 'Terjadi kesalahan saat menghapus data',
                        confirmButtonColor: '#2563eb'
                    });
                }
            } catch(error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menghapus!',
                    text: 'Terjadi kesalahan pada server',
                    confirmButtonColor: '#2563eb'
                });
            }
        }
    }

    // ===== REFRESH =====
    function refreshDataPegawai() {
        loadDataPegawai();
        
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: 'success',
            title: 'Data berhasil dimuat ulang'
        });
    }

    // ===== AUTO LOAD =====
    document.addEventListener('DOMContentLoaded', function() {
        loadDataPegawai();
    });

    // Event listener untuk tab Data Pegawai
    const dataPegawaiTab = document.getElementById('data-pegawai-tab');
    if(dataPegawaiTab) {
        dataPegawaiTab.addEventListener('shown.bs.tab', function() {
            loadDataPegawai();
        });
    }
</script>