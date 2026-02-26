<?php
// File: api/notifications.php
// Notifikasi sebagai halaman biasa (bukan API)

require_once '../../config/database.php';


if (isset($_GET['get']) && $_GET['get'] === 'css') {
    header('Content-Type: text/css');
    ?>
/* ===== NOTIFICATION BELL WRAPPER ===== */
.notification-wrapper {
    position: relative;
}

.notification-bell {
    position: relative;
    width: 56px;
    height: 56px;
    background: white;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: all 0.25s ease;
}

.notification-bell i {
    font-size: 22px;
    color: #4b5563;
}

.notification-bell:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    background: #f9fafb;
}

.notification-bell:hover i {
    color: #667eea;
}

/* ===== NOTIFICATION BADGE ===== */
.notification-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 11px;
    font-weight: 700;
    padding: 0 6px;
    min-width: 20px;
    height: 20px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}


.notification-badge.show {
    display: flex;
}

.notification-badge.pulse {
    animation: pulse 1s ease-in-out;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
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
    max-width: 90vw;
    display: none;
    z-index: 9999;
    overflow: hidden;
    border: 1px solid #e5e7eb;
}

.notification-dropdown.show {
    display: block;
    animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ===== NOTIFICATION HEADER ===== */
.notification-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f0;
    background: #fafafa;
}

.notification-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0;
}

/* INNER LIST - YANG SCROLL */
.notification-list {
    max-height: 450px;
    overflow-y: auto;
    overflow-x: hidden;
}

.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-track {
    background: #f5f5f5;
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
    scrollbar-color: #cbd5e0 #f5f5f5;
}

.notification-empty {
    padding: 50px 20px;
    text-align: center;
    color: #999;
}

.notification-empty i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 15px;
    display: block;
}

.notification-empty p {
    font-size: 14px;
    margin: 0;
    color: #999;
}

/* ===== NOTIFICATION ITEM ===== */
.notification-item {
    display: block;
    text-decoration: none;
    color: inherit;
    background: white;
    transition: all 0.2s ease;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    position: relative;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item:active {
    background: #e5e7eb;
    transform: scale(0.99);
}

.notification-item:last-child {
    border-bottom: none;
}

/* Inner wrapper untuk flex layout */
.notification-item-inner {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
}

.notification-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: transform 0.2s;
}

.notification-item:hover .notification-icon {
    transform: scale(1.05);
}

.notif-danger .notification-icon {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
}

.notif-warning .notification-icon {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #d97706;
}

.notif-info .notification-icon {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: #2563eb;
}

.notif-success .notification-icon {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

.notification-content {
    flex: 1;
    min-width: 0;
    padding-right: 8px;
}

.notification-content h4 {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0 0 6px 0;
    line-height: 1.3;
}

.notification-content p {
    font-size: 13px;
    color: #666;
    margin: 0 0 6px 0;
    line-height: 1.4;
}

.notification-content small {
    font-size: 11px;
    color: #999;
    font-weight: 500;
}

.notification-count {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.notif-danger .notification-count {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.notif-warning .notification-count {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.notif-info .notification-count {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.notif-success .notification-count {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}
    <?php
    exit();
}


if (isset($_GET['get']) && $_GET['get'] === 'js') {
    header('Content-Type: application/javascript');
    ?>
// Toggle Dropdown
document.addEventListener('DOMContentLoaded', function() {
    const bell = document.getElementById('notification-bell');
    const dropdown = document.getElementById('notification-dropdown');
    
    bell.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });
    
    document.addEventListener('click', function(e) {
        if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
    
    // Load notifications on page load
    loadNotifications();
    
    // Auto-refresh every 30 seconds
    setInterval(loadNotifications, 30000);
});

let lastCheck = null;

async function loadNotifications() {
    try {
        const url = 'api/notifications.php?action=load' + (lastCheck ? '&last_check=' + lastCheck : '');
        const response = await fetch(url);
        const html = await response.text();
        
        // Parse HTML response
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update badge
        const badgeData = doc.querySelector('[data-badge]');
        if (badgeData) {
            const count = parseInt(badgeData.getAttribute('data-badge'));
            updateBadge(count);
        }
        
        // Update dropdown list
        const listContent = doc.querySelector('[data-list]');
        if (listContent) {
            document.getElementById('notification-list').innerHTML = listContent.innerHTML;
        }
        
        // Update timestamp
        const timestamp = doc.querySelector('[data-timestamp]');
        if (timestamp) {
            lastCheck = timestamp.getAttribute('data-timestamp');
        }
        
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

function updateBadge(count) {
    const badge = document.getElementById('notification-badge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.add('show');
        badge.classList.add('pulse');
        setTimeout(() => badge.classList.remove('pulse'), 1000);
    } else {
        badge.classList.remove('show');
    }
}

function getIconClass(type) {
    const icons = {
        'lamaran': 'fa-envelope',
        'kontrak': 'fa-file-contract',
        'studi': 'fa-graduation-cap',
        'sertifikasi': 'fa-certificate',
        'sertifikasi_habis': 'fa-certificate',
        'password': 'fa-key',
        'dokumen': 'fa-file-alt',
        'kinerja': 'fa-chart-line'
    };
    return icons[type] || 'fa-bell';
}

function getColorClass(priority) {
    const colors = {
        'danger': 'notif-danger',
        'warning': 'notif-warning',
        'info': 'notif-info',
        'success': 'notif-success'
    };
    return colors[priority] || 'notif-info';
}

function timeAgo(datetime) {
    const now = new Date();
    const past = new Date(datetime);
    const diff = Math.floor((now - past) / 1000);
    
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    if (diff < 604800) return Math.floor(diff / 86400) + ' hari lalu';
    return past.toLocaleDateString('id-ID');
}
    <?php
    exit();
}

//  LOAD NOTIFICATIONS 
if (isset($_GET['action']) && $_GET['action'] === 'load') {
    try {
        $last_check = isset($_GET['last_check']) ? $_GET['last_check'] : null;
        
        // Array untuk menampung notifikasi
        $notifications = [];
        $total_notifikasi = 0;
        $notifikasi_baru = 0;
        $notif_types_from_table = [];
        
        //Ambil dari tabel notifikasi_admin 
        $query_table = "SELECT * FROM notifikasi_admin WHERE is_read = 0 ORDER BY created_at DESC";
        $stmt = $conn->query($query_table);
        $table_notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapping jenis notifikasi ke tipe dan URL
        $type_mapping = [
            'verifikasi_pegawai' => [
                'type' => 'lamaran',
                'priority' => 'warning',
                'url' => '/sdmPolnest/admin/manajemenrec/manajemenrec.php'
            ],
            'kontrak_habis' => [
                'type' => 'kontrak',
                'priority' => 'danger',
                'url' => '/sdmPolnest/admin/administrasi/administrasiKepegawaian.php'
            ],
            'pengajuan_studi' => [
                'type' => 'studi',
                'priority' => 'info',
                'url' => '/sdmPolnest/admin/pengembanganSdm/indexpengembangan-sdm.php'
            ],
            'sertifikasi_dosen' => [
                'type' => 'sertifikasi',
                'priority' => 'info',
                'url' => '/sdmPolnest/admin/sertifikasi/sertifikasi-dosen.php'
            ],
            'dokumen_pegawai' => [
                'type' => 'dokumen',
                'priority' => 'warning',
                'url' => '/sdmPolnest/admin/administrasi/administrasiKepegawaian.php'
            ],
            'penilaian_kinerja' => [
                'type' => 'kinerja',
                'priority' => 'info',
                'url' => '/sdmPolnest/admin/penilaian/penilaianKinerja.php'
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
                    'source' => 'table'
                ];
                
                $total_notifikasi += (int)$row['jumlah_item'];
                $notif_types_from_table[] = $jenis;
                
                if ($last_check && strtotime($row['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += (int)$row['jumlah_item'];
                }
            }
        }
        
        
        //  LAMARAN BARU
        if (!in_array('verifikasi_pegawai', $notif_types_from_table)) {
            $query_lamaran = "
                SELECT 
                    COUNT(*) as count,
                    MAX(l.tanggal_daftar) as created_at
                FROM lamaran l
                WHERE l.status_lamaran IN ('dikirim', 'seleksi_administrasi')
                HAVING COUNT(*) > 0
            ";
            $stmt = $conn->query($query_lamaran);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $notifications[] = [
                    'type' => 'lamaran',
                    'priority' => 'warning',
                    'count' => (int)$result['count'],
                    'title' => 'Lamaran Menunggu Verifikasi',
                    'message' => $result['count'] . ' lamaran baru perlu diverifikasi',
                    'created_at' => $result['created_at'],
                    'url' => '/sdmPolnest/admin/manajemenrec/manajemenrec.php',
                    'source' => 'realtime'
                ];
                $total_notifikasi += (int)$result['count'];
                if ($last_check && strtotime($result['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += (int)$result['count'];
                }
            }
        }
        
        // KONTRAK AKAN HABIS
        if (!in_array('kontrak_habis', $notif_types_from_table)) {
            $query_kontrak = "
                SELECT 
                    COUNT(DISTINCT kontrak_info.pegawai_id) as count,
                    MAX(kontrak_info.updated_at) as created_at
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
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $notifications[] = [
                    'type' => 'kontrak',
                    'priority' => 'danger',
                    'count' => (int)$result['count'],
                    'title' => 'Kontrak Akan Berakhir',
                    'message' => $result['count'] . ' kontrak akan habis dalam 30 hari',
                    'created_at' => $result['created_at'],
                    'url' => '/sdmPolnest/admin/administrasiKepegawaian/administrasiKepegawaian.php',
                    'source' => 'realtime'
                ];
                $total_notifikasi += (int)$result['count'];
            }
        }
        
        // PENGAJUAN STUDI LANJUT
        if (!in_array('pengajuan_studi', $notif_types_from_table)) {
            $query_studi = "
                SELECT 
                    COUNT(*) as count,
                    MAX(created_at) as created_at
                FROM pengajuan_studi
                WHERE status_pengajuan IN ('diajukan', 'ditinjau')
                HAVING COUNT(*) > 0
            ";
            $stmt = $conn->query($query_studi);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $notifications[] = [
                    'type' => 'studi',
                    'priority' => 'info',
                    'count' => (int)$result['count'],
                    'title' => 'Pengajuan Studi Lanjut',
                    'message' => $result['count'] . ' pengajuan studi perlu disetujui',
                    'created_at' => $result['created_at'],
                    'url' => '/sdmPolnest/admin/pengembanganSdm/indexpengembangan-sdm.php',
                    'source' => 'realtime'
                ];
                $total_notifikasi += (int)$result['count'];
                if ($last_check && strtotime($result['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += (int)$result['count'];
                }
            }
        }
        
        //  SERTIFIKASI PENDING VALIDASI
        if (!in_array('sertifikasi_dosen', $notif_types_from_table)) {
            $query_sertif = "
                SELECT 
                    COUNT(*) as count,
                    MAX(created_at) as created_at
                FROM sertifikasi_dosen
                WHERE status_validasi = 'pending'
                HAVING COUNT(*) > 0
            ";
            $stmt = $conn->query($query_sertif);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $notifications[] = [
                    'type' => 'sertifikasi',
                    'priority' => 'info',
                    'count' => (int)$result['count'],
                    'title' => 'Validasi Sertifikasi Dosen',
                    'message' => $result['count'] . ' sertifikasi perlu divalidasi',
                    'created_at' => $result['created_at'],
                    'url' => '/sdmPolnest/admin/sertifikasi/sertifikasi-dosen.php',
                    'source' => 'realtime'
                ];
                $total_notifikasi += (int)$result['count'];
                if ($last_check && strtotime($result['created_at']) > strtotime($last_check)) {
                    $notifikasi_baru += (int)$result['count'];
                }
            }
        }
        
        // SERTIFIKASI AKAN HABIS 
        $query_sertif_habis = "
            SELECT 
                COUNT(*) as count,
                MAX(s.created_at) as created_at
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
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $notifications[] = [
                'type' => 'sertifikasi_habis',
                'priority' => 'warning',
                'count' => (int)$result['count'],
                'title' => 'Sertifikasi Akan Habis',
                'message' => $result['count'] . ' sertifikasi akan habis dalam 6 bulan',
                'created_at' => $result['created_at'],
                'url' => '/sdmPolnest/admin/sertifikasi/sertifikasi-dosen.php',
                'source' => 'realtime'
            ];
            $total_notifikasi += (int)$result['count'];
        }
        
        // RESET PASSWORD REQUEST 
        $query_reset = "
            SELECT 
                COUNT(*) as count,
                MAX(updated_at) as created_at
            FROM users
            WHERE reset_token IS NOT NULL 
            AND reset_token_expires > NOW()
            HAVING COUNT(*) > 0
        ";
        $stmt = $conn->query($query_reset);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $notifications[] = [
                'type' => 'password',
                'priority' => 'info',
                'count' => (int)$result['count'],
                'title' => 'Request Reset Password',
                'message' => $result['count'] . ' permintaan reset password aktif',
                'created_at' => $result['created_at'],
                'url' => '/sdmPolnest/admin/index.php',
                'source' => 'realtime'
            ];
            $total_notifikasi += (int)$result['count'];
            if ($last_check && strtotime($result['created_at']) > strtotime($last_check)) {
                $notifikasi_baru += (int)$result['count'];
            }
        }
        
        // Sort berdasarkan prioritas
        usort($notifications, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        
        // Helper function untuk icon
        function getIconClass($type) {
            $icons = [
                'lamaran' => 'fa-envelope',
                'kontrak' => 'fa-file-contract',
                'studi' => 'fa-graduation-cap',
                'sertifikasi' => 'fa-certificate',
                'sertifikasi_habis' => 'fa-certificate',
                'password' => 'fa-key',
                'dokumen' => 'fa-file-alt',
                'kinerja' => 'fa-chart-line'
            ];
            return $icons[$type] ?? 'fa-bell';
        }
        
        // Helper function untuk warna
        function getColorClass($priority) {
            $colors = [
                'danger' => 'notif-danger',
                'warning' => 'notif-warning',
                'info' => 'notif-info',
                'success' => 'notif-success'
            ];
            return $colors[$priority] ?? 'notif-info';
        }
        
        // Helper function untuk time ago
        function timeAgo($datetime) {
            $now = new DateTime();
            $past = new DateTime($datetime);
            $diff = $now->getTimestamp() - $past->getTimestamp();
            
            if ($diff < 60) return 'Baru saja';
            if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
            if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
            if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
            return $past->format('d M Y');
        }
        
        ?>
        <div data-badge="<?php echo $total_notifikasi; ?>" style="display:none;"></div>
        <div data-timestamp="<?php echo date('Y-m-d H:i:s'); ?>" style="display:none;"></div>
        
        <!-- Notification List -->
        <div data-list>
            <?php if (empty($notifications)): ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>Tidak ada notifikasi</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?php echo htmlspecialchars($notif['url']); ?>" class="notification-item <?php echo getColorClass($notif['priority']); ?>">
                        <div class="notification-item-inner">
                            <div class="notification-icon">
                                <i class="fas <?php echo getIconClass($notif['type']); ?>"></i>
                            </div>
                            <div class="notification-content">
                                <h4><?php echo htmlspecialchars($notif['title']); ?></h4>
                                <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                <small><i class="far fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?></small>
                            </div>
                            <div class="notification-count"><?php echo $notif['count']; ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        
    } catch(PDOException $e) {
        echo '<div class="notification-empty">';
        echo '<i class="fas fa-exclamation-triangle"></i>';
        echo '<p>Error memuat notifikasi</p>';
        echo '</div>';
    }
    exit();
}
?>