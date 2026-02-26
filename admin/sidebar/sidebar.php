
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- favicon -->
     <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            overflow-x: hidden;
        }

        .sidebar {
            width: 290px;
            height: 100vh; 
            max-height: 100vh; 
            background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 50%, #1e293b 100%);
            position: fixed;
            left: 0;
            top: 0;
            color: white;
            overflow-y: scroll !important;
            overflow-x: hidden !important;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            z-index: 1000;
            -ms-overflow-style: none !important;  
            scrollbar-width: none !important;  
        }

        .sidebar::-webkit-scrollbar {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
            background: transparent !important;
        }

        .sidebar::-webkit-scrollbar-button {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
        }

        .sidebar::-webkit-scrollbar-button:start:decrement,
        .sidebar::-webkit-scrollbar-button:end:increment {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
        }

        .sidebar::-webkit-scrollbar-track {
            display: none !important;
            background: transparent !important;
        }

        .sidebar::-webkit-scrollbar-track-piece {
            display: none !important;
            background: transparent !important;
        }

        .sidebar::-webkit-scrollbar-thumb {
            display: none !important;
            background: transparent !important;
        }

        .sidebar::-webkit-scrollbar-corner {
            display: none !important;
            background: transparent !important;
        }

        .sidebar::-webkit-resizer {
            display: none !important;
        }

        .main-content {
            margin-left: 280px;
            padding: 32px;
            min-height: 100vh;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        @media (max-width: 968px) {
            .main-content {
                margin-left: 80px; 
                padding: 24px;
            }

            .sidebar.expanded ~ .main-content {
                margin-left: 280px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                margin-left: 70px;
                padding: 16px;
            }

            .sidebar.expanded ~ .main-content {
                margin-left: 260px;
            }
        }

        .sidebar-header {
            padding: 30px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .logo {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            backdrop-filter: blur(10px);
            flex-shrink: 0;
        }

        .logo i {
            color: white;
        }

        .sidebar-text {
            transition: opacity 0.3s ease;
            overflow: hidden;
        }

        .sidebar-header h2 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.5px;
            white-space: nowrap;
        }

        .sidebar-subtitle {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 400;
            margin-top: 4px;
            white-space: nowrap;
        }

        .sidebar-menu {
            padding: 24px 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
            -ms-overflow-style: none !important;
            scrollbar-width: none !important;
        }

        .sidebar-menu::-webkit-scrollbar {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
        }

        .sidebar-menu::-webkit-scrollbar-button {
            display: none !important;
            width: 0px !important;
            height: 0px !important;
        }

        .sidebar-menu::-webkit-scrollbar-button:start:decrement,
        .sidebar-menu::-webkit-scrollbar-button:end:increment {
            display: none !important;
        }

        .sidebar-menu::-webkit-scrollbar-track {
            display: none !important;
        }

        .sidebar-menu::-webkit-scrollbar-thumb {
            display: none !important;
        }

        .sidebar-menu::-webkit-scrollbar-corner {
            display: none !important;
        }

        .menu-section {
            margin-bottom: 24px;
        }

        .menu-section-title {
            padding: 0 24px 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .menu-item {
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            font-weight: 500;
            font-size: 14px;
            max-width: 100%;
            overflow: hidden;
            margin: 4px 0;
        }

        .menu-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px 0 0 12px;
            margin-left: 12px;
        }

        .menu-item.active {
            background: white;
            color: #1e40af;
            font-weight: 600;
            border-radius: 40px 0 0 40px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            margin-left: 10px; 
        }

        .menu-item.active i {
            color: #1e40af;
        }

        .menu-item i {
            width: 24px;
            height: 24px;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            flex-shrink: 0;
        }

        .menu-item span {
            position: relative;
            z-index: 1;
            white-space: nowrap;
            transition: opacity 0.3s ease;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .admin-profile {
            padding: 20px 24px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
        }

        .profile-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            border: 3px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
        }

        .profile-avatar i {
            color: white;
        }

        .profile-details {
            flex: 1;
            transition: opacity 0.3s ease;
            overflow: hidden;
        }

        .profile-name {
            font-size: 15px;
            font-weight: 600;
            color: white;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-role {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 400;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            width: 100%;
            padding: 12px 16px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.25);
            border-color: rgba(239, 68, 68, 0.5);
            color: #fee2e2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .logout-btn i {
            font-size: 16px;
            flex-shrink: 0;
        }

        .logout-btn span {
            transition: opacity 0.3s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        @media (max-width: 968px) {
            .sidebar {
                width: 80px;
                transform: translateX(0);
                max-height: 100vh; 
            }

            .sidebar.expanded {
                width: 280px;
                z-index: 1000;
            }

            .sidebar-header {
                padding: 20px 12px;
                justify-content: center;
            }

            .logo-container {
                flex-direction: column;
                gap: 0;
                align-items: center;
            }

            .sidebar-text {
                opacity: 0;
                height: 0;
                overflow: hidden;
            }

            .sidebar.expanded .sidebar-text {
                opacity: 1;
                height: auto;
            }

            .menu-section-title {
                opacity: 0;
                padding: 0;
                height: 0;
            }

            .sidebar.expanded .menu-section-title {
                opacity: 1;
                height: auto;
                padding: 0 24px 12px;
            }

            .menu-item {
                padding: 16px 12px;
                justify-content: center;
                gap: 0;
                margin: 4px 8px;
            }

            .sidebar.expanded .menu-item {
                padding: 9px 16px 9px 8px;
                justify-content: flex-start;
                font-size: 12.5px; 
                gap: 9px;
                margin: 3px 0 3px 8px;
                min-height: 46px;
            }

            .menu-item span {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .sidebar.expanded .menu-item span {
                opacity: 1;
                width: auto;
            }

            .menu-item.active {
                margin: 4px 8px;
                border-radius: 12px; 
                white-space: normal; 
                line-height: 1.35;
                word-wrap: break-word;
                overflow-wrap: break-word;
                overflow: visible; 
            }

            .sidebar.expanded .menu-item.active {
                margin-left: 12px;
                margin-right: 0;
                border-radius: 12px 0 0 12px; 
            }

            .menu-item:hover {
                margin: 4px 8px;
                border-radius: 12px;
            }

            .sidebar.expanded .menu-item:hover {
                margin-left: 12px;
                margin-right: 0;
                border-radius: 12px 0 0 12px; 
            }

            .admin-profile {
                padding: 16px 12px;
            }

            .sidebar.expanded .admin-profile {
                padding: 20px 24px;
            }

            .profile-info {
                flex-direction: column;
                gap: 8px;
                align-items: center;
            }

            .sidebar.expanded .profile-info {
                flex-direction: row;
                gap: 14px;
                align-items: center;
            }

            .profile-details {
                opacity: 0;
                height: 0;
                overflow: hidden;
            }

            .sidebar.expanded .profile-details {
                opacity: 1;
                height: auto;
            }

            .logout-btn {
                padding: 12px 8px;
            }

            .sidebar.expanded .logout-btn {
                padding: 12px 16px;
            }

            .logout-btn span {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            .sidebar.expanded .logout-btn span {
                opacity: 1;
                width: auto;
            }

            .sidebar-overlay {
                display: block;
            }
        }
        
        @media (max-width: 480px) {
            .sidebar {
                width: 70px;
                max-height: 100vh; 
            }

            .sidebar.expanded {
                width: 245px;
                z-index: 1000;
            }

            .logo {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .profile-avatar {
                width: 42px;
                height: 42px;
                font-size: 16px;
            }

            .sidebar-header {
                padding: 16px 8px;
            }

            .menu-item {
                padding: 14px 8px;
                margin: 4px 6px;
            }

            .sidebar.expanded .menu-item {
                padding: 8px 12px 8px 6px; 
                font-size: 11.5px; 
                gap: 7px;
            }

            .menu-item.active {
                margin: 4px 6px;
            }

            .sidebar.expanded .menu-item.active {
                margin-left: 10px;
                margin-right: 0;
            }

            .sidebar.expanded .menu-item i {
                font-size: 15px; 
                width: 18px;
                height: 18px;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .menu-item {
            animation: slideIn 0.3s ease forwards;
        }

        .menu-item:nth-child(1) { animation-delay: 0.05s; }
        .menu-item:nth-child(2) { animation-delay: 0.1s; }
        .menu-item:nth-child(3) { animation-delay: 0.15s; }
        .menu-item:nth-child(4) { animation-delay: 0.2s; }
        .menu-item:nth-child(5) { animation-delay: 0.25s; }
        .menu-item:nth-child(6) { animation-delay: 0.3s; }
        .menu-item:nth-child(7) { animation-delay: 0.35s; }

        .logout-popup {
            font-family: 'Poppins', sans-serif !important;
        }

        .logout-confirm-btn,
        .logout-cancel-btn {
            font-family: 'Poppins', sans-serif !important;
            font-weight: 600 !important;
            padding: 10px 24px !important;
            border-radius: 8px !important;
            font-size: 14px !important;
        }

        .logout-confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4) !important;
        }

        .logout-cancel-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4) !important;
        }
</style>
</head>
<body>
    <!-- Sidebar Overlay untuk Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="tutupSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="sidebar-text">
                    <h2>SDM Panel</h2>
                    <div class="sidebar-subtitle">Admin Dashboard</div>
                </div>
            </div>
        </div>

        <div class="sidebar-menu">
            <?php 
                
                $halaman_sekarang = basename($_SERVER['PHP_SELF']);
                $current_dir = basename(dirname($_SERVER['PHP_SELF']));
                
                function isActive($files, $dirs = []) {
                    global $halaman_sekarang, $current_dir;

                    $files = (array)$files;
                    $dirs = (array)$dirs;
                    
                    if (in_array($halaman_sekarang, $files)) {
                        return true;
                    }
                    
                    if (!empty($dirs) && in_array($current_dir, $dirs)) {
                        return true;
                    }
                    
                    return false;
                }

                $base_url = '/sdmPolnest/admin';
            ?>
            <div class="menu-section">
                <div class="menu-section-title">Menu Utama</div>
                <a href="<?php echo $base_url; ?>/index.php" 
                   class="menu-item <?php echo isActive('index.php') ? 'active' : ''; ?>" 
                   title="Dashboard">
                    <i class="fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $base_url; ?>/manajemenloker/manajemen-loker.php" 
                class="menu-item <?php echo isActive(['manajemen-loker.php', 'createloker.php', 'editloker.php', 'detailloker.php', 'deleteloker.php'], 'manajemenloker') ? 'active' : ''; ?>" 
                title="Manajemen Loker">
                    <i class="fas fa-briefcase"></i>
                    <span>Manajemen Loker</span>
                </a>
               <a href="<?php echo $base_url; ?>/manajemenrec/manajemenrec.php" 
                   class="menu-item <?php echo isActive(['manajemenrec.php', 'detail_pelamar.php', 'generate_token_pegawai.php', 'jadwalkan_interview.php', 'jadwalkan_psikotes.php', 'template_surat.php', 'update_status_lamaran.php', 'upload_surat_resmi.php'], 'manajemenrec') ? 'active' : ''; ?>" 
                   title="Manajemen Recruitment">
                    <i class="fas fa-user-tie"></i>
                    <span>Manajemen Recruitment</span>
                </a>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">Manajemen SDM</div>
                <a href="<?php echo $base_url; ?>/administrasi/administrasiKepegawaian.php" 
                   class="menu-item <?php echo isActive('administrasiKepegawaian.php', 'administrasi') ? 'active' : ''; ?>" 
                   title="Administrasi Kepegawaian">
                    <i class="fas fa-file-alt"></i>
                    <span>Administrasi Kepegawaian</span>
                </a>
                <a href="<?php echo $base_url; ?>/pengembanganSDM/pengembangan-sdm.php" 
                   class="menu-item <?php echo isActive('pengembangan-sdm.php', 'pengembanganSDM') ? 'active' : ''; ?>" 
                   title="Pengembangan SDM">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Pengembangan SDM</span>
                </a>
                <a href="<?php echo $base_url; ?>/sertifikasi/sertifikasi-dosen.php" 
                   class="menu-item <?php echo isActive('sertifikasi-dosen.php', 'sertifikasi') ? 'active' : ''; ?>" 
                   title="Sertifikasi Dosen">
                    <i class="fas fa-certificate"></i>
                    <span>Sertifikasi Dosen</span>
                </a>
                <a href="<?php echo $base_url; ?>/penilaian/penilaianKinerja.php" 
                   class="menu-item <?php echo isActive(['penilaianKinerja.php', 'template.php', 'form.php', 'detail.php'], 'penilaian') ? 'active' : ''; ?>" 
                   title="Penilaian Kinerja">
                    <i class="fas fa-chart-bar"></i>
                    <span>Penilaian Kinerja</span>
                </a>
                <a href="<?php echo $base_url; ?>/pegawaiLama/manajemen-pegawai.php" 
                   class="menu-item <?php echo isActive('manajemen-pegawai.php', 'pegawaiLama') ? 'active' : ''; ?>" 
                   title="Manajemen Pegawai">
                    <i class="fas fa-user-cog"></i>
                    <span>Manajemen Pegawai</span>
                </a>
            </div>
        </div>

        <div class="admin-profile">
            <div class="profile-info">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-details">
                    <div class="profile-name">Admin Utama</div>
                    <div class="profile-role">Administrator</div>
                </div>
            </div>
            <button class="logout-btn" onclick="keluar(event)" title="Keluar">
                <i class="fas fa-sign-out-alt"></i>
                <span>Keluar</span>
            </button>
        </div>
    </div>

    <script>
        let sidebarExpanded = false;

    function toggleSidebar(event) {
        if (window.innerWidth <= 968) {
            
            if (event.target.closest('a') || event.target.closest('button')) {
                return;
            }

            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebarExpanded = !sidebarExpanded;
            
            if (sidebarExpanded) {
                sidebar.classList.add('expanded');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden'; 
            } else {
                sidebar.classList.remove('expanded');
                overlay.classList.remove('active');
                document.body.style.overflow = ''; 
            }
        }
    }

    function tutupSidebar() {
        if (window.innerWidth <= 968) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            sidebarExpanded = false;
            document.body.style.overflow = ''; 
        }
    }

    function keluar(event) {
        event.stopPropagation();
        
        Swal.fire({
            title: 'Konfirmasi Keluar',
            text: "Apakah Anda yakin ingin keluar dari sistem?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '<i class="fas fa-sign-out-alt"></i> Ya, Keluar',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            reverseButtons: true,
            customClass: {
                popup: 'logout-popup',
                confirmButton: 'logout-confirm-btn',
                cancelButton: 'logout-cancel-btn'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Keluar...',
                    text: 'Sedang memproses logout',
                    icon: 'info',
                    showConfirmButton: false,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                setTimeout(() => {
                    window.location.href = '/sdmPolnest/auth/logout_admin.php';
                }, 800);
            }
        });
    }
    

    const sidebar = document.getElementById('sidebar');
    sidebar.addEventListener('click', function(event) {
        if (event.target === sidebar || 
            event.target.classList.contains('sidebar-menu') ||
            event.target.classList.contains('sidebar-header')) {
            toggleSidebar(event);
        }
    });

    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (window.innerWidth > 968) {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            sidebarExpanded = false;
            document.body.style.overflow = '';
        }
    });

    document.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', function() {
            if (window.innerWidth <= 968) {
                tutupSidebar();
            }
        });
    });

    </script>
</body>
</html>