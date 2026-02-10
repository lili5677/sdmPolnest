<?php

if (isset($_GET['get']) && $_GET['get'] === 'css') {
    header('Content-Type: text/css');
    ?>
/* ===== NOTIFICATION BADGE ===== */
.notification-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #ef4444;
    color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 3px 7px;
    border-radius: 10px;
    min-width: 20px;
    height: 20px;
    display: none;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    z-index: 10;
}

.notification-badge.pulse {
    animation: pulse 1s ease-in-out;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.2); }
}

/* ===== NOTIFICATION DROPDOWN - WRAPPER ===== */
.notification-dropdown {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    width: 390px;
    display: none;
    z-index: 9999;
    overflow: hidden;
}

.notification-dropdown.show {
    display: block;
}

/* INNER LIST - YANG SCROLL */
.notification-list {
    max-height: 500px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-bottom: 8px;
}

.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-track {
    background: transparent;
}

.notification-list::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.notification-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.notification-list {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 transparent;
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #999;
}

.notification-empty i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 10px;
}

.notification-empty p {
    font-size: 14px;
    margin: 0;
}

/* ===== NOTIFICATION ITEM ===== */
.notification-item {
    display: block;
    text-decoration: none;
    color: inherit;
    background: white;
    transition: background 0.2s;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item:active {
    background: #e5e7eb;
}

.notification-item:last-child {
    border-bottom: none;
}

/* Inner wrapper untuk flex layout */
.notification-item > div {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.notif-danger .notification-icon {
    background: #fee2e2;
    color: #ef4444;
}

.notif-warning .notification-icon {
    background: #fef3c7;
    color: #f59e0b;
}

.notif-info .notification-icon {
    background: #dbeafe;
    color: #3b82f6;
}

.notif-success .notification-icon {
    background: #d4f4dd;
    color: #22c55e;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-content h4 {
    font-size: 14px;
    font-weight: 200;
    color: #1a1a1a;
    margin: 0 0 4px 0;
}

.notification-content p {
    font-size: 12px;
    color: #666;
    margin: 0 0 4px 0;
    line-height: 1.4;
}

.notification-content small {
    font-size: 11px;
    color: #999;
}

.notification-count {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.notif-danger .notification-count {
    background: #ef4444;
    color: white;
}

.notif-warning .notification-count {
    background: #f59e0b;
    color: white;
}

.notif-info .notification-count {
    background: #3b82f6;
    color: white;
}

.notif-success .notification-count {
    background: #22c55e;
    color: white;
}

/* ===== TOAST NOTIFICATION ===== */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    max-width: 380px;
    z-index: 9999;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
}

.notification-toast.show {
    transform: translateX(0);
    opacity: 1;
}

.toast-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.toast-danger .toast-icon {
    background: #fee2e2;
    color: #ef4444;
}

.toast-warning .toast-icon {
    background: #fef3c7;
    color: #f59e0b;
}

.toast-info .toast-icon {
    background: #dbeafe;
    color: #3b82f6;
}

.toast-success .toast-icon {
    background: #d4f4dd;
    color: #22c55e;
}

.toast-content {
    flex: 1;
    min-width: 0;
}

.toast-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 4px 0;
}

.toast-content p {
    font-size: 13px;
    color: #666;
    margin: 0;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    cursor: pointer;
    transition: all 0.2s;
    flex-shrink: 0;
}

.toast-close:hover {
    background: #f3f4f6;
    color: #4b5563;
}
    <?php
    exit;
}

// Jika dipanggil untuk get JS
if (isset($_GET['get']) && $_GET['get'] === 'js') {
    header('Content-Type: application/javascript');
    ?>
class NotificationSystem {
    constructor(options = {}) {
        this.apiUrl = options.apiUrl || 'api/notifications.php';
        this.interval = options.interval || 30000;
        this.enableSound = options.enableSound || false;
        this.enableToast = options.enableToast || true;
        this.lastCheck = null;
        this.dropdown = null;
        this.notificationList = null;
        this.badge = null;
        
        this.init();
    }
    
    init() {
        this.createDropdown();
        this.attachEventListeners();
        this.fetchNotifications();
        
        if (this.interval > 0) {
            setInterval(() => this.fetchNotifications(), this.interval);
        }
    }
    
    createDropdown() {
        const bellIcon = document.querySelector('.notification-bell');
        if (!bellIcon) return;
        
        const parent = bellIcon.parentElement;
        parent.style.position = 'relative';
        
        this.badge = document.createElement('div');
        this.badge.className = 'notification-badge';
        parent.appendChild(this.badge);
        
        // Wrapper dropdown (tidak scroll)
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'notification-dropdown';
        
        // Inner list (yang scroll)
        this.notificationList = document.createElement('div');
        this.notificationList.className = 'notification-list';
        
        // FIX: Stop propagation hanya untuk wheel event, biarkan click event
        this.notificationList.addEventListener('wheel', (e) => {
            const atTop = this.notificationList.scrollTop === 0;
            const atBottom = this.notificationList.scrollTop + this.notificationList.clientHeight >= this.notificationList.scrollHeight - 1;
            
            // Hanya stop propagation jika scroll benar-benar terjadi di dalam list
            if ((e.deltaY < 0 && atTop) || (e.deltaY > 0 && atBottom)) {
                // Biarkan scroll propagate ke parent
                return;
            }
            // Stop propagation untuk scroll di dalam list
            e.stopPropagation();
        }, { passive: false });
        
        this.dropdown.appendChild(this.notificationList);
        parent.appendChild(this.dropdown);
    }
    
    attachEventListeners() {
        const bellIcon = document.querySelector('.notification-bell');
        if (!bellIcon) return;
        
        bellIcon.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggleDropdown();
        });
        
        // SIMPLE: Biarkan <a> tag bekerja secara native
        // Hanya close dropdown saat link diklik
        this.notificationList.addEventListener('click', (e) => {
            const notifItem = e.target.closest('.notification-item');
            if (notifItem && notifItem.getAttribute('href')) {
                // Biarkan browser handle link navigation secara natural
                // Hanya close dropdown
                this.hideDropdown();
            }
        });
        
        // Close dropdown saat klik di luar
        document.addEventListener('click', (e) => {
            if (!this.dropdown.contains(e.target) && e.target !== bellIcon) {
                this.hideDropdown();
            }
        });
    }
    
    toggleDropdown() {
        this.dropdown.classList.toggle('show');
        if (this.dropdown.classList.contains('show')) {
            this.fetchNotifications();
        }
    }
    
    hideDropdown() {
        this.dropdown.classList.remove('show');
    }
    
    async fetchNotifications() {
        try {
            const url = new URL(this.apiUrl, window.location.origin);
            url.searchParams.append('action', 'get');
            if (this.lastCheck) {
                url.searchParams.append('last_check', this.lastCheck);
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.total);
                this.renderNotifications(data.notifications);
                
                if (data.new_count > 0 && this.lastCheck && this.enableToast) {
                    this.showToast(data.notifications[0]);
                }
                
                this.lastCheck = data.timestamp;
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }
    
    updateBadge(count) {
        if (count > 0) {
            this.badge.textContent = count > 99 ? '99+' : count;
            this.badge.style.display = 'flex';
            this.badge.classList.add('pulse');
            setTimeout(() => this.badge.classList.remove('pulse'), 1000);
        } else {
            this.badge.style.display = 'none';
        }
    }
    
    renderNotifications(notifications) {
        if (!notifications || notifications.length === 0) {
            this.notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>Tidak ada notifikasi</p>
                </div>
            `;
            return;
        }
        
        const html = notifications.map(notif => `
            <a href="${notif.url}" class="notification-item ${this.getColorClass(notif.priority)}">
                <div>
                    <div class="notification-icon">
                        <i class="fas ${this.getIconClass(notif.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <h4>${notif.title}</h4>
                        <p>${notif.message}</p>
                        <small>${this.timeAgo(notif.created_at)}</small>
                    </div>
                    <div class="notification-count">
                        ${notif.count}
                    </div>
                </div>
            </a>
        `).join('');
        
        this.notificationList.innerHTML = html;
    }
    
    showToast(notif) {
        const toast = document.createElement('div');
        toast.className = `notification-toast toast-${notif.priority}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${this.getIconClass(notif.type)}"></i>
            </div>
            <div class="toast-content">
                <h4>${notif.title}</h4>
                <p>${notif.message}</p>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    getIconClass(type) {
        const icons = {
            'lamaran': 'fa-envelope',
            'verifikasi_pegawai': 'fa-user-check',
            'kontrak': 'fa-file-contract',
            'kontrak_habis': 'fa-file-contract',
            'studi': 'fa-graduation-cap',
            'pengajuan_studi': 'fa-graduation-cap',
            'sertifikasi': 'fa-certificate',
            'sertifikasi_dosen': 'fa-certificate',
            'sertifikasi_habis': 'fa-certificate',
            'password': 'fa-key',
            'dokumen': 'fa-file-alt',
            'kinerja': 'fa-chart-line'
        };
        return icons[type] || 'fa-bell';
    }
    
    getColorClass(priority) {
        const colors = {
            'danger': 'notif-danger',
            'warning': 'notif-warning',
            'info': 'notif-info',
            'success': 'notif-success'
        };
        return colors[priority] || 'notif-info';
    }
    
    timeAgo(datetime) {
        const now = new Date();
        const past = new Date(datetime);
        const diff = Math.floor((now - past) / 1000);
        
        if (diff < 60) return 'Baru saja';
        if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
        if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
        if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
        return past.toLocaleDateString('id-ID');
    }
}

// Auto-initialize
document.addEventListener('DOMContentLoaded', function() {
    window.notificationSystem = new NotificationSystem({
        apiUrl: 'api/notifications.php',
        interval: 30000,
        enableSound: false,
        enableToast: true
    });
});
    <?php
    exit;
}

// ===== API ENDPOINT =====
require_once '../../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

try {
    $action = $_GET['action'] ?? 'get';
    $last_check = $_GET['last_check'] ?? null;
    
    if ($action === 'get') {
        $total_notifikasi = 0;
        $notifikasi_baru = 0;
        $notifications = [];
   
        $notif_types_from_table = [];
        
        // STEP 1: Ambil notifikasi dari tabel (yang sudah di-generate)
        $query_from_table = "
            SELECT 
                jenis_notifikasi,
                judul,
                deskripsi,
                jumlah_item,
                created_at
            FROM notifikasi_admin
            WHERE is_read = 0
            ORDER BY created_at DESC
        ";
        
        $stmt = $conn->query($query_from_table);
        $table_notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapping jenis_notifikasi ke type dan URL
        $type_mapping = [
            'verifikasi_pegawai' => [
                'type' => 'verifikasi_pegawai',
                'priority' => 'warning',
                'url' => '/sdmPolnest/admin/manajemenrec/manajemenrec.php'
            ],
            'pengajuan_studi' => [
                'type' => 'pengajuan_studi',
                'priority' => 'info',
                'url' => '/sdmPolnest/admin/pengembanganSdm/indexpengembangan-sdm.php'
            ],
            'sertifikasi_dosen' => [
                'type' => 'sertifikasi_dosen',
                'priority' => 'info',
                'url' => '/sdmPolnest/admin/sertifikasi/sertifikasi-dosen.php'
            ],
            'kontrak_habis' => [
                'type' => 'kontrak_habis',
                'priority' => 'danger',
                'url' => '/sdmPolnest/admin/administrasi/administrasiKepegawaian.php'
            ]
        ];
        
        foreach ($table_notifs as $row) {
            $jenis = $row['jenis_notifikasi'];
            
            if (isset($type_mapping[$jenis])) {
                $mapping = $type_mapping[$jenis];
                
                $notifications[] = [
                    'type' => $mapping['type'],
                    'priority' => $mapping['priority'],
                    'count' => (int)$row['jumlah_item'],
                    'title' => $row['judul'],
                    'message' => $row['deskripsi'],
                    'created_at' => $row['created_at'],
                    'url' => $mapping['url'],
                    'source' => 'table' // Marker untuk tracking
                ];
                
                $total_notifikasi += (int)$row['jumlah_item'];
                $notif_types_from_table[] = $jenis; // Track yang sudah ada
                
                if ($last_check && strtotime($row['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += (int)$row['jumlah_item'];
                }
            }
        }
        
        // STEP 2: Query real-time untuk notifikasi yang TIDAK ada di tabel
        // Ini sebagai fallback jika stored procedure belum dijalankan
        
        // 1. LAMARAN BARU (jika belum ada di tabel)
        if (!in_array('verifikasi_pegawai', $notif_types_from_table)) {
            $query_lamaran = "
                SELECT 
                    'lamaran' as type,
                    'warning' as priority,
                    COUNT(*) as count,
                    'Lamaran Menunggu Verifikasi' as title,
                    CONCAT(COUNT(*), ' lamaran baru perlu diverifikasi') as message,
                    MAX(l.tanggal_daftar) as created_at,
                    '/sdmPolnest/admin/manajemenrec/manajemenrec.php' as url
                FROM lamaran l
                WHERE l.status_lamaran IN ('dikirim', 'seleksi_administrasi')
                HAVING COUNT(*) > 0
            ";
            $stmt = $conn->query($query_lamaran);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($notif) {
                $notif['source'] = 'realtime';
                $notifications[] = $notif;
                $total_notifikasi += $notif['count'];
                if ($last_check && strtotime($notif['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += $notif['count'];
                }
            }
        }
        
        // 2. KONTRAK AKAN HABIS
        if (!in_array('kontrak_habis', $notif_types_from_table)) {
            $query_kontrak = "
                SELECT 
                    'kontrak' as type,
                    'danger' as priority,
                    COUNT(DISTINCT kontrak_info.pegawai_id) as count,
                    'Kontrak Akan Berakhir' as title,
                    CONCAT(COUNT(DISTINCT kontrak_info.pegawai_id), ' kontrak akan habis dalam 30 hari') as message,
                    MAX(kontrak_info.updated_at) as created_at,
                    '/sdmPolnest/admin/administrasi/administrasiKepegawaian.php' as url
                FROM (
                    SELECT 
                        sk.pegawai_id,
                        sk.masa_kontrak_selesai,
                        sk.updated_at,
                        DATEDIFF(sk.masa_kontrak_selesai, CURDATE()) as sisa_hari
                    FROM status_kepegawaian sk
                    INNER JOIN pegawai p ON sk.pegawai_id = p.pegawai_id
                    WHERE sk.status_aktif = 'aktif'
                    AND LOWER(sk.jenis_kepegawaian) = 'kontrak'
                    AND sk.masa_kontrak_selesai IS NOT NULL
                    AND DATEDIFF(sk.masa_kontrak_selesai, CURDATE()) BETWEEN 0 AND 30
                ) kontrak_info
                HAVING COUNT(DISTINCT kontrak_info.pegawai_id) > 0
            ";
            $stmt = $conn->query($query_kontrak);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($notif) {
                $notif['source'] = 'realtime';
                $notifications[] = $notif;
                $total_notifikasi += $notif['count'];
            }
        }
        
        // 3. PENGAJUAN STUDI LANJUT 
        if (!in_array('pengajuan_studi', $notif_types_from_table)) {
            $query_studi = "
                SELECT 
                    'studi' as type,
                    'info' as priority,
                    COUNT(*) as count,
                    'Pengajuan Studi Lanjut' as title,
                    CONCAT(COUNT(*), ' pengajuan studi perlu disetujui') as message,
                    MAX(created_at) as created_at,
                    '/sdmPolnest/admin/pengembanganSdm/indexpengembangan-sdm.php' as url
                FROM pengajuan_studi
                WHERE status_pengajuan IN ('diajukan', 'ditinjau')
                HAVING COUNT(*) > 0
            ";
            $stmt = $conn->query($query_studi);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($notif) {
                $notif['source'] = 'realtime';
                $notifications[] = $notif;
                $total_notifikasi += $notif['count'];
                if ($last_check && strtotime($notif['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += $notif['count'];
                }
            }
        }
        
        // 4. SERTIFIKASI PENDING VALIDASI 
        if (!in_array('sertifikasi_dosen', $notif_types_from_table)) {
            $query_sertif = "
                SELECT 
                    'sertifikasi' as type,
                    'info' as priority,
                    COUNT(*) as count,
                    'Validasi Sertifikasi Dosen' as title,
                    CONCAT(COUNT(*), ' sertifikasi perlu divalidasi') as message,
                    MAX(created_at) as created_at,
                    '/sdmPolnest/admin/sertifikasi/sertifikasi-dosen.php' as url
                FROM sertifikasi_dosen
                WHERE status_validasi = 'pending'
                HAVING COUNT(*) > 0
            ";
            $stmt = $conn->query($query_sertif);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($notif) {
                $notif['source'] = 'realtime';
                $notifications[] = $notif;
                $total_notifikasi += $notif['count'];
                if ($last_check && strtotime($notif['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += $notif['count'];
                }
            }
        }
        
        // 5. SERTIFIKASI AKAN HABIS 
        $query_sertif_habis = "
            SELECT 
                'sertifikasi_habis' as type,
                'warning' as priority,
                COUNT(*) as count,
                'Sertifikasi Akan Habis' as title,
                CONCAT(COUNT(*), ' sertifikasi akan habis dalam 6 bulan') as message,
                MAX(s.created_at) as created_at,
                '/sdmPolnest/admin/sertifikasi/sertifikasi-dosen.php' as url
            FROM sertifikasi_dosen s
            INNER JOIN pegawai p ON s.pegawai_id = p.pegawai_id
            LEFT JOIN (
                SELECT sk1.*
                FROM status_kepegawaian sk1
                INNER JOIN (
                    SELECT pegawai_id, MAX(created_at) as max_created
                    FROM status_kepegawaian
                    GROUP BY pegawai_id
                ) sk2 ON sk1.pegawai_id = sk2.pegawai_id 
                     AND sk1.created_at = sk2.max_created
            ) latest_sk ON p.pegawai_id = latest_sk.pegawai_id
            WHERE COALESCE(latest_sk.status_aktif, 'aktif') = 'aktif'
            AND s.tahun_masa_berlaku IS NOT NULL
            AND s.tahun_masa_berlaku <= YEAR(DATE_ADD(CURDATE(), INTERVAL 6 MONTH))
            AND s.status_validasi = 'tervalidasi'
            HAVING COUNT(*) > 0
        ";
        $stmt = $conn->query($query_sertif_habis);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($notif) {
            $notif['source'] = 'realtime';
            $notifications[] = $notif;
            $total_notifikasi += $notif['count'];
        }
        
        // 6. RESET PASSWORD REQUEST 
        $query_reset = "
            SELECT 
                'password' as type,
                'info' as priority,
                COUNT(*) as count,
                'Request Reset Password' as title,
                CONCAT(COUNT(*), ' permintaan reset password aktif') as message,
                MAX(updated_at) as created_at,
                '/sdmPolnest/admin/index.php' as url
            FROM users
            WHERE reset_token IS NOT NULL 
            AND reset_token_expires > NOW()
            HAVING COUNT(*) > 0
        ";
        $stmt = $conn->query($query_reset);
        $notif = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($notif) {
            $notif['source'] = 'realtime';
            $notifications[] = $notif;
            $total_notifikasi += $notif['count'];
            if ($last_check && strtotime($notif['created_at']) > strtotime($last_check)) {
                $notifikasi_baru += $notif['count'];
            }
        }
        
        // Sort berdasarkan prioritas
        usort($notifications, function($a, $b) {
            $priority_order = ['danger' => 1, 'warning' => 2, 'info' => 3, 'success' => 4];
            $a_priority = $priority_order[$a['priority']] ?? 5;
            $b_priority = $priority_order[$b['priority']] ?? 5;
            
            if ($a_priority === $b_priority) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            }
            return $a_priority - $b_priority;
        });
        
        echo json_encode([
            'success' => true,
            'total' => $total_notifikasi,
            'new_count' => $notifikasi_baru,
            'notifications' => $notifications,
            'timestamp' => date('Y-m-d H:i:s'),
            'debug' => [
                'from_table' => count($table_notifs),
                'from_realtime' => count($notifications) - count($table_notifs)
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
    }
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>