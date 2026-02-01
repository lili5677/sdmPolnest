<style>
    /* STYLING UNTUK STRUKTUR ORGANISASI */
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

    /* ===== PERBAIKAN Z-INDEX UNTUK MODAL ===== */
    .modal-backdrop {
        z-index: 9998 !important;
    }

    .modal {
        z-index: 9999 !important;
    }

    #modalAnggota {
        z-index: 9999 !important;
    }

    #modalAnggota .modal-dialog {
        z-index: 10000 !important;
    }

    #modalAnggota .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        position: relative;
        z-index: 10001 !important;
    }

    .modal-header {
        background: #f9fafb;
        border-radius: 12px 12px 0 0;
        padding: 20px 30px;
        border-bottom: 1px solid #e5e7eb;
    }

    .modal-header .modal-title {
        font-weight: 600;
        font-size: 20px;
    }

    .modal-body {
        padding: 30px;
    }

    .form-label {
        font-weight: 500;
        color: #374151;
        margin-bottom: 8px;
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

    <!-- Level Tabs -->
    <div class="level-tabs">
        <button class="level-tab active" onclick="gantiLevel(1)" data-level="1">
            <i class=""></i> Level 1 - Pimpinan Tertinggi
        </button>
        <button class="level-tab" onclick="gantiLevel(2)" data-level="2">
            <i class=""></i> Level 2 - Kepala Unit
        </button>
        <button class="level-tab" onclick="gantiLevel(3)" data-level="3">
            <i class=""></i> Level 3 - Anggota
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
    let allDataStruktur = [];        // semua data untuk level aktif
    let currentPageStruktur = 1;
    const PAGE_SIZE_STRUKTUR = 10;

    // ===== INISIALISASI =====
    document.addEventListener('DOMContentLoaded', function() {
        modalAnggota = new bootstrap.Modal(document.getElementById('modalAnggota'));
        loadAnggotaByLevel(1);
        loadPegawaiList();
        loadParentList();

        if (window.location.hash === '#struktur-organisasi') {
            const strukturTab = document.querySelector('#struktur-tab');
            if (strukturTab) {
                const tab = new bootstrap.Tab(strukturTab);
                tab.show();
            }
        }
    });

    // ===== GANTI LEVEL =====
    function gantiLevel(level) {
        currentLevel = level;
        currentPageStruktur = 1; // reset halaman saat ganti level

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

    // ===== RENDER CARDS + PAGINATION =====
    function renderCardsStruktur() {
        const container = document.getElementById('level-content-container');
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

            html += `
                <div class="member-card" data-id="${anggota.struktur_id}">
                    <div class="avatar">${initials}</div>
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

        // Pagination info + controls — pagination hanya muncul kalau > 10
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

    // ===== RENDER TOMBOL PAGINATION (Struktur) =====
    function renderPaginationButtonsStruktur(containerId, currentPage, totalPages) {
        const container = document.getElementById(containerId);
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

    // ===== LOAD PEGAWAI LIST =====
    async function loadPegawaiList() {
        try {
            const response = await fetch('?action=get_pegawai_list');
            const result = await response.json();

            const select = document.getElementById('pegawai_id');
            select.innerHTML = '<option value="">-- Pilih Pegawai --</option>';

            if(result.success && result.data.length > 0) {
                result.data.forEach(pegawai => {
                    const option = document.createElement('option');
                    option.value = pegawai.pegawai_id;
                    option.textContent = `${pegawai.nama_lengkap} - ${pegawai.jabatan || 'Pegawai'}`;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">-- Semua pegawai sudah terdaftar --</option>';
            }
        } catch(error) {
            console.error('Error loading pegawai:', error);
        }
    }

    // ===== LOAD PARENT LIST =====
    async function loadParentList() {
        try {
            const response = await fetch('?action=get_parent_list');
            const result = await response.json();

            const select = document.getElementById('parent_id');
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
        document.getElementById('formAnggota').reset();
        document.getElementById('mode').value = 'add';
        document.getElementById('struktur_id').value = '';
        document.getElementById('modalTitleText').textContent = 'Tambah Anggota Baru';
        document.getElementById('level_struktur').value = currentLevel;
        document.getElementById('pegawai_id').disabled = false;

        loadPegawaiList();
        loadParentList();

        modalAnggota.show();
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

                document.getElementById('pegawai_id').disabled = true;

                modalAnggota.show();
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

        if(!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const mode = document.getElementById('mode').value;
        const data = {
            pegawai_id: document.getElementById('pegawai_id').value,
            jabatan_struktur: document.getElementById('jabatan_struktur').value,
            level_struktur: document.getElementById('level_struktur').value,
            parent_id: document.getElementById('parent_id').value || null
        };

        if(mode === 'edit') {
            data.struktur_id = document.getElementById('struktur_id').value;
        }

        const action = mode === 'edit' ? 'update' : 'add';

        try {
            const response = await fetch(`?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if(result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: result.message,
                    confirmButtonColor: '#2563eb',
                    timer: 2000
                });

                modalAnggota.hide();
                loadAnggotaByLevel(currentLevel);
                loadPegawaiList();
                document.getElementById('pegawai_id').disabled = false;
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
        window.open('preview.php', '_blank');
    }

    // ===== EVENT LISTENERS MODAL =====
    document.getElementById('modalAnggota').addEventListener('hidden.bs.modal', function() {
        document.getElementById('pegawai_id').disabled = false;
    });

    document.getElementById('modalAnggota').addEventListener('show.bs.modal', function(e) {
        setTimeout(() => {
            const backdrop = document.querySelector('.modal-backdrop');
            if(backdrop) backdrop.style.zIndex = '9998';

            const modal = document.getElementById('modalAnggota');
            if(modal) modal.style.zIndex = '9999';
        }, 10);
    });

    document.getElementById('modalAnggota').addEventListener('shown.bs.modal', function(e) {
        const backdrop = document.querySelector('.modal-backdrop');
        if(backdrop) backdrop.style.zIndex = '9998';

        const modal = document.getElementById('modalAnggota');
        if(modal) modal.style.zIndex = '9999';
    });
</script>