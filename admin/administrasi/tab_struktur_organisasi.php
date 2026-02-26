<style>
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

    /* Info Alert*/
    .info-alert {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border: 1px solid #93c5fd;
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
    }

    .info-alert .icon-container {
        background: #2563eb;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .info-alert .content {
        flex: 1;
    }

    .info-alert .content p {
        font-size: 13px;
        color: #1e40af;
        margin: 0;
        line-height: 1.6;
    }

    .info-alert .content p strong {
        color: #1e3a8a;
        font-weight: 700;
    }

    /* Level Tabs */
    .level-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        overflow-x: auto;
        overflow-y: hidden;
        flex-wrap: nowrap;
        padding-bottom: 10px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: #d1d5db #f3f4f6;
    }

    .level-tabs::-webkit-scrollbar {
        height: 6px;
    }

    .level-tabs::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 10px;
    }

    .level-tabs::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 10px;
    }

    .level-tabs::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .level-tab {
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        background: white;
        color: #6b7280;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        transition: all 0.3s;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .level-tab:hover {
        border-color: #2563eb;
        color: #2563eb;
    }

    .level-tab.active {
        background: #2563eb;
        color: white;
        border-color: #2563eb;
    }

    /* Member Card */
    .member-card {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        margin-bottom: 15px;
        transition: all 0.3s;
        background: white;
    }

    .member-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #d1d5db;
    }

    .avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: white;
        flex-shrink: 0;
        font-weight: 600;
        overflow: hidden;
    }

    .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .member-info {
        flex: 1;
    }

    .member-name {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 6px;
        font-size: 16px;
    }

    .member-position {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .member-department {
        color: #9ca3af;
        font-size: 13px;
    }

    .member-department i {
        margin-right: 5px;
    }

    .member-actions {
        display: flex;
        gap: 8px;
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

    .btn-edit {
        background: none;
        border: none;
        color: #2563eb;
        cursor: pointer;
        padding: 8px 12px;
        font-size: 18px;
        transition: all 0.3s;
        border-radius: 6px;
    }

    .btn-edit:hover {
        background: #eff6ff;
    }

    .btn-delete {
        background: none;
        border: none;
        color: #ef4444;
        cursor: pointer;
        padding: 8px 12px;
        font-size: 18px;
        transition: all 0.3s;
        border-radius: 6px;
    }

    .btn-delete:hover {
        background: #fef2f2;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }

    .empty-state i {
        font-size: 64px;
        color: #d1d5db;
        margin-bottom: 20px;
    }

    .empty-state h4 {
        color: #6b7280;
        font-size: 18px;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #9ca3af;
        font-size: 13px;
    }

    /* Loading */
    .spinner-container {
        text-align: center;
        padding: 40px;
    }

    .spinner-border {
        color: #2563eb;
    }

    /* Pagination Struktur */
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

    @media (max-width: 768px) {
        .card-header-custom {
            flex-direction: column;
        }

        .member-card {
            flex-direction: column;
            text-align: center;
        }

        .member-actions {
            justify-content: center;
        }

        .level-tabs {
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: 12px;
            gap: 8px;
        }

        .level-tab {
            white-space: nowrap;
            flex-shrink: 0;
            font-size: 12px;
            padding: 8px 14px;
        }

        .pagination-container {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .info-alert {
            flex-direction: column;
            text-align: center;
        }

        .info-alert .icon-container {
            margin: 0 auto;
        }
    }

    @media (max-width: 480px) {
        .content-card {
            padding: 20px;
        }

        .level-tabs {
            gap: 6px;
            padding-bottom: 12px;
        }

        .level-tab {
            font-size: 11px;
            padding: 7px 12px;
        }
    }
</style>

<div class="content-card">
    <div class="card-header-custom">
        <div>
            <h3 class="card-title-custom">
                <i class="fas fa-sitemap"></i>
                Struktur Organisasi
            </h3>
            <p class="card-description">Kelola struktur kepemimpinan dan anggota untuk ditampilkan di website publik</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-custom" onclick="tampilkanPreview()">
                <i class="fas fa-eye"></i>
                Preview
            </button>
            <button class="btn btn-primary-custom" onclick="tampilkanFormTambah()">
                <i class="fas fa-user-plus"></i>
                Tambah Anggota
            </button>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="info-alert">
        <div class="icon-container">
            <i class="fas fa-info-circle"></i>
        </div>
        <div class="content">
            <p>
                <strong>Syarat Masuk Struktur Organisasi:</strong> Pegawai harus berstatus <strong>Aktif</strong> dan memiliki data kepegawaian yang lengkap 
            </p>
        </div>
    </div>

    <!-- Level Tabs -->
    <div class="level-tabs">
        <button class="level-tab active" onclick="gantiLevel(1)" data-level="1">
            Level 1 - Direktur
        </button>
        <button class="level-tab" onclick="gantiLevel(2)" data-level="2">
            Level 2 - Wakil Direktur
        </button>
        <button class="level-tab" onclick="gantiLevel(3)" data-level="3">
            Level 3 - Kaprodi
        </button>
        <button class="level-tab" onclick="gantiLevel(4)" data-level="4">
            Level 4 - Kepala Unit
        </button>
        <button class="level-tab" onclick="gantiLevel(5)" data-level="5">
            Level 5 - Laboran
        </button>
        <button class="level-tab" onclick="gantiLevel(6)" data-level="6">
            Level 6 - Tendik
        </button>
        <button class="level-tab" onclick="gantiLevel(7)" data-level="7">
            Level 7 - Staff
        </button>
    </div>

    <!-- Level Content Container -->
    <div id="level-content-container">
        <div class="spinner-container">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Memuat data dari database...</p>
        </div>
    </div>
</div>

<script>
    // ===== VARIABEL GLOBAL STRUKTUR =====
    let currentLevel = 1;
    let modalAnggota;
    let allDataStruktur = [];
    let currentPageStruktur = 1;
    const PAGE_SIZE_STRUKTUR = 10;
    let strukturInitialized = false;

    // ===== INISIALISASI STRUKTUR =====
    function initializeStrukturOrganisasi() {
        if (strukturInitialized) return;
        
        const modalElement = document.getElementById('modalAnggota');
        if (!modalElement) {
            console.warn('Modal element not found, skipping initialization');
            return;
        }
        
        modalAnggota = new bootstrap.Modal(modalElement);
        loadAnggotaByLevel(1);
        loadPegawaiList();
        loadParentList();
        setupModalEventListeners();
        
        strukturInitialized = true;
    }

    // ===== SETUP MODAL EVENT LISTENERS =====
    function setupModalEventListeners() {
        const modalElement = document.getElementById('modalAnggota');
        if (!modalElement) return;

        modalElement.addEventListener('hidden.bs.modal', function() {
            const pegawaiSelect = document.getElementById('pegawai_id');
            const fotoInput = document.getElementById('foto');
            const previewDiv = document.getElementById('current-foto-preview');
            
            if (pegawaiSelect) pegawaiSelect.disabled = false;
            if (fotoInput) fotoInput.value = '';
            if (previewDiv) previewDiv.style.display = 'none';
        });

        modalElement.addEventListener('show.bs.modal', function(e) {
            setTimeout(() => {
                const backdrop = document.querySelector('.modal-backdrop');
                if(backdrop) backdrop.style.zIndex = '9998';
                const modal = document.getElementById('modalAnggota');
                if(modal) modal.style.zIndex = '9999';
            }, 10);
        });

        modalElement.addEventListener('shown.bs.modal', function(e) {
            const backdrop = document.querySelector('.modal-backdrop');
            if(backdrop) backdrop.style.zIndex = '9998';
            const modal = document.getElementById('modalAnggota');
            if(modal) modal.style.zIndex = '9999';
        });
    }

    // Event listener untuk tab Struktur Organisasi
    const strukturTab = document.getElementById('struktur-tab');
    if(strukturTab) {
        strukturTab.addEventListener('shown.bs.tab', function() {
            initializeStrukturOrganisasi();
        });
    }

    // Auto-initialize jika hash mengarah ke struktur
    document.addEventListener('DOMContentLoaded', function() {
        if (window.location.hash === '#struktur-organisasi') {
            initializeStrukturOrganisasi();
        }
    });

    // ===== GANTI LEVEL =====
    function gantiLevel(level) {
        currentLevel = level;
        currentPageStruktur = 1;

        document.querySelectorAll('.level-tab').forEach(tab => {
            tab.classList.remove('active');
            if(tab.getAttribute('data-level') == level) {
                tab.classList.add('active');
            }
        });

        loadAnggotaByLevel(level);
    }

    // ===== LOAD DATA PER LEVEL =====
    async function loadAnggotaByLevel(level) {
        const container = document.getElementById('level-content-container');
        if (!container) return;

        container.innerHTML = `
            <div class="spinner-container">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Memuat data dari database...</p>
            </div>
        `;

        try {
            const response = await fetch(`?action=get_by_level&level=${level}`);
            const result = await response.json();

            if(result.success && result.data.length > 0) {
                allDataStruktur = result.data;
                currentPageStruktur = 1;
                renderCardsStruktur();
            } else {
                allDataStruktur = [];
                displayEmptyState();
            }
        } catch(error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memuat Data',
                html: `<p>Terjadi kesalahan saat memuat data.</p><p class="text-muted small">Error: ${error.message}</p>`,
                confirmButtonColor: '#2563eb'
            });
            displayEmptyState();
        }
    }

    function renderCardsStruktur() {
        const container = document.getElementById('level-content-container');
        if (!container) return;
        
        const totalPages = Math.ceil(allDataStruktur.length / PAGE_SIZE_STRUKTUR);

        if (allDataStruktur.length === 0) {
            displayEmptyState();
            return;
        }

        if (currentPageStruktur > totalPages) {
            currentPageStruktur = 1;
        }

        const startIndex = (currentPageStruktur - 1) * PAGE_SIZE_STRUKTUR;
        const endIndex = startIndex + PAGE_SIZE_STRUKTUR;
        const pageData = allDataStruktur.slice(startIndex, endIndex);

        let html = '';

        pageData.forEach(anggota => {
            const initials = getInitials(anggota.nama_lengkap);
            let avatarContent = '';
            
            if(anggota.path_gambar) {
                avatarContent = `<img src="${anggota.path_gambar}" alt="${anggota.nama_lengkap}">`;
            } else {
                avatarContent = initials;
            }

            html += `
                <div class="member-card" data-id="${anggota.struktur_id}">
                    <div class="avatar">${avatarContent}</div>
                    <div class="member-info">
                        <div class="member-name">${anggota.nama_lengkap}</div>
                        <div class="member-position">
                            <i class="fas fa-briefcase"></i> ${anggota.jabatan_struktur}
                        </div>
                        <div class="member-department">
                            <i class="fas fa-building"></i> ${anggota.unit_kerja || '-'}
                        </div>
                    </div>
                    <div class="member-actions">
                        <button class="btn-edit" onclick="editAnggota(${anggota.struktur_id})" title="Edit Anggota">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-delete" onclick="hapusAnggota(${anggota.struktur_id})" title="Hapus Anggota">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        html += `<div class="pagination-container">`;
        html += `<div class="pagination-info">
                    Menampilkan <strong>${startIndex + 1}–${Math.min(endIndex, allDataStruktur.length)}</strong> 
                    dari <strong>${allDataStruktur.length}</strong> anggota
                 </div>`;

        if (totalPages > 1) {
            html += `<div class="pagination-controls" id="pagination-struktur"></div>`;
        }

        html += `</div>`;

        container.innerHTML = html;

        if (totalPages > 1) {
            renderPaginationButtonsStruktur('pagination-struktur', currentPageStruktur, totalPages);
        }
    }

    // ===== RENDER TOMBOL PAGINATION =====
    function renderPaginationButtonsStruktur(containerId, currentPage, totalPages) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        let html = '';

        html += `<button class="btn-page-arrow" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPageStruktur(${currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                 </button>`;

        const pages = getPageNumbersStruktur(currentPage, totalPages);
        pages.forEach(item => {
            if (item === '...') {
                html += `<span class="page-dots">...</span>`;
            } else {
                html += `<button class="btn-page ${item === currentPage ? 'active' : ''}" onclick="goToPageStruktur(${item})">${item}</button>`;
            }
        });

        html += `<button class="btn-page-arrow" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPageStruktur(${currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                 </button>`;

        container.innerHTML = html;
    }

    // ===== HELPER: Nomor halaman untuk struktur =====
    function getPageNumbersStruktur(current, total) {
        const pages = [];

        if (total <= 7) {
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }

        pages.push(1);
        if (current > 3) pages.push('...');

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);
        for (let i = start; i <= end; i++) pages.push(i);

        if (current < total - 2) pages.push('...');
        pages.push(total);

        return pages;
    }

    // ===== NAVIGASI HALAMAN (Struktur) =====
    function goToPageStruktur(page) {
        const totalPages = Math.ceil(allDataStruktur.length / PAGE_SIZE_STRUKTUR);
        if (page < 1 || page > totalPages) return;
        currentPageStruktur = page;
        renderCardsStruktur();
        document.querySelector('.level-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ===== DISPLAY EMPTY STATE =====
    function displayEmptyState() {
        const container = document.getElementById('level-content-container');
        if (!container) return;
        
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h4>Belum Ada Anggota</h4>
                <p>Klik tombol "Tambah Anggota" untuk menambahkan anggota baru di level ini</p>
            </div>
        `;
    }

    // ===== HELPER: Get Initials =====
    function getInitials(name) {
        const words = name.trim().split(' ').filter(word => word.length > 0);
        if(words.length >= 2) {
            return (words[0][0] + words[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    // ===== LOAD PEGAWAI LIST (DENGAN FILTER LENGKAP + PTKP) =====
    async function loadPegawaiList() {
        try {
            const response = await fetch('?action=get_pegawai_list');
            const result = await response.json();

            const select = document.getElementById('pegawai_id');
            if (!select) return;

            select.innerHTML = '<option value="">-- Pilih Pegawai --</option>';

            if (!result.success || !Array.isArray(result.data) || result.data.length === 0) {
                select.innerHTML = '<option value="">-- Tidak ada pegawai tersedia --</option>';
                return;
            }

            // 🔥 FILTER LENGKAP - Semua field wajib terisi termasuk PTKP
            const eligiblePegawai = result.data.filter(p => {
                // 1. Validasi field umum wajib
                const jabatanValid = p.jabatan && p.jabatan.trim() !== '';
                const jenisValid = p.jenis_kepegawaian && p.jenis_kepegawaian.trim() !== '';
                const unitValid = p.unit_kerja && p.unit_kerja.trim() !== '';
                const tanggalValid = p.tanggal_mulai_kerja && p.tanggal_mulai_kerja !== null;
                
                // 🔥 PTKP WAJIB TERISI & TIDAK BOLEH KOSONG
                const ptkpValid = p.ptkp && p.ptkp.toString().trim() !== '';

                // Status harus aktif
                const statusValid = p.status_aktif && 
                    p.status_aktif.toString().trim().toLowerCase() === 'aktif';

                // 2. Validasi khusus untuk pegawai kontrak
                let kontrakValid = true;
                if (p.jenis_kepegawaian?.toLowerCase() === 'kontrak') {
                    kontrakValid = 
                        p.masa_kontrak_mulai && 
                        p.masa_kontrak_selesai;
                }

                // 3. Return TRUE hanya jika SEMUA validasi lolos
                return (
                    jabatanValid &&
                    jenisValid &&
                    unitValid &&
                    tanggalValid &&
                    ptkpValid &&      // 🔥 WAJIB
                    statusValid &&
                    kontrakValid
                );
            });

            if (eligiblePegawai.length === 0) {
                select.innerHTML = '<option value="">-- Tidak ada pegawai memenuhi syarat --</option>';
                return;
            }

            eligiblePegawai.forEach(pegawai => {
                const option = document.createElement('option');
                option.value = pegawai.pegawai_id;
                option.textContent = `${pegawai.nama_lengkap} - ${pegawai.jabatan}`;
                select.appendChild(option);
            });

        } catch (error) {
            console.error('Error loading pegawai:', error);
            const select = document.getElementById('pegawai_id');
            if (select) {
                select.innerHTML = '<option value="">-- Error memuat data --</option>';
            }
        }
    }

    // ===== LOAD PARENT LIST =====
    async function loadParentList() {
        try {
            const response = await fetch('?action=get_parent_list');
            const result = await response.json();

            const select = document.getElementById('parent_id');
            if (!select) return;
            
            select.innerHTML = '<option value="">-- Tidak ada atasan --</option>';

            if(result.success && result.data.length > 0) {
                result.data.forEach(parent => {
                    const option = document.createElement('option');
                    option.value = parent.struktur_id;
                    option.textContent = `${parent.nama_lengkap} - ${parent.jabatan_struktur} (Level ${parent.level_struktur})`;
                    select.appendChild(option);
                });
            }
        } catch(error) {
            console.error('Error loading parent:', error);
        }
    }

    // ===== TAMPILKAN FORM TAMBAH =====
    function tampilkanFormTambah() {
        const form = document.getElementById('formAnggota');
        if (!form) return;
        
        form.reset();
        document.getElementById('mode').value = 'add';
        document.getElementById('struktur_id').value = '';
        document.getElementById('modalTitleText').textContent = 'Tambah Anggota Baru';
        document.getElementById('level_struktur').value = currentLevel;
        
        const pegawaiSelect = document.getElementById('pegawai_id');
        if (pegawaiSelect) pegawaiSelect.disabled = false;
        
        const previewDiv = document.getElementById('current-foto-preview');
        if (previewDiv) previewDiv.style.display = 'none';

        loadPegawaiList();
        loadParentList();

        if (modalAnggota) modalAnggota.show();
    }

    // ===== EDIT ANGGOTA =====
    async function editAnggota(id) {
        try {
            const response = await fetch(`?action=get_by_id&id=${id}`);
            const result = await response.json();

            if(result.success) {
                const data = result.data;

                document.getElementById('mode').value = 'edit';
                document.getElementById('struktur_id').value = data.struktur_id;
                document.getElementById('modalTitleText').textContent = 'Edit Anggota';
                document.getElementById('pegawai_id').value = data.pegawai_id;
                document.getElementById('jabatan_struktur').value = data.jabatan_struktur;
                document.getElementById('level_struktur').value = data.level_struktur;

                await loadParentList();
                if(data.parent_id) {
                    document.getElementById('parent_id').value = data.parent_id;
                }

                const previewDiv = document.getElementById('current-foto-preview');
                const previewImg = document.getElementById('preview-img');
                if(data.path_gambar && previewDiv && previewImg) {
                    previewDiv.style.display = 'block';
                    previewImg.src = data.path_gambar;
                } else if (previewDiv) {
                    previewDiv.style.display = 'none';
                }
                
                const fotoInput = document.getElementById('foto');
                if (fotoInput) fotoInput.value = '';

                const pegawaiSelect = document.getElementById('pegawai_id');
                if (pegawaiSelect) pegawaiSelect.disabled = true;

                if (modalAnggota) modalAnggota.show();
            }
        } catch(error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Terjadi kesalahan saat mengambil data',
                confirmButtonColor: '#2563eb'
            });
        }
    }

    // ===== SIMPAN ANGGOTA =====
    async function simpanAnggota() {
        const form = document.getElementById('formAnggota');
        if (!form) return;

        if(!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const mode = document.getElementById('mode').value;
        const formData = new FormData(form);
        const action = mode === 'edit' ? 'update' : 'add';

        try {
            const response = await fetch(`?action=${action}`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if(result.success) {
                if (modalAnggota) modalAnggota.hide();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: result.message,
                    confirmButtonColor: '#2563eb',
                    timer: 2000
                });

                loadAnggotaByLevel(currentLevel);
                loadPegawaiList();
                
                const pegawaiSelect = document.getElementById('pegawai_id');
                if (pegawaiSelect) pegawaiSelect.disabled = false;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: result.message,
                    confirmButtonColor: '#2563eb'
                });
            }
        } catch(error) {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Terjadi kesalahan saat menyimpan data',
                confirmButtonColor: '#2563eb'
            });
        }
    }

    // ===== HAPUS ANGGOTA =====
    async function hapusAnggota(id) {
        const result = await Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Data yang sudah dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        });

        if(result.isConfirmed) {
            try {
                const response = await fetch(`?action=delete&id=${id}`);
                const data = await response.json();

                if(data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus!',
                        text: data.message,
                        confirmButtonColor: '#2563eb',
                        timer: 2000
                    });

                    loadAnggotaByLevel(currentLevel);
                    loadPegawaiList();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: data.message,
                        confirmButtonColor: '#2563eb'
                    });
                }
            } catch(error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Terjadi kesalahan saat menghapus data',
                    confirmButtonColor: '#2563eb'
                });
            }
        }
    }

    // ===== PREVIEW =====
    function tampilkanPreview() {
        window.location.href = 'preview.php';
    }
</script>