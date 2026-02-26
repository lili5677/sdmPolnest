<?php
session_start();
require_once '../../config/database.php';

// Cek login admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

date_default_timezone_set('Asia/Jakarta');

$success = '';
$error = '';

try {
    // Cleanup riwayat lama
    $conn->exec("
        DELETE FROM password_reset_requests
        WHERE status IN ('completed', 'rejected')
        AND approved_at < DATE_SUB(NOW(), INTERVAL 3 DAY)
    ");
    
    // User gagal verifikasi token yang expired
    $stmt = $conn->prepare("
        UPDATE password_reset_requests r
        JOIN users u ON r.user_id = u.user_id
        SET r.status = 'rejected'
        WHERE r.status = 'approved' 
          AND u.reset_token_expires < NOW()
    ");
    $stmt->execute();
    
    // Hapus token expired dari tabel users
    $stmt = $conn->prepare("
        UPDATE users u
        JOIN password_reset_requests r ON u.user_id = r.user_id
        SET u.reset_token = NULL,
            u.reset_token_expires = NULL
        WHERE r.status = 'rejected'
          AND u.reset_token_expires < NOW()
    ");
    $stmt->execute();
    
} catch (Exception $e) {
    error_log("Cleanup error: " . $e->getMessage());
}

// generate token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    
    $request_id = (int)$_POST['request_id'];

    // Get request detail
    $stmt = $conn->prepare("
        SELECT r.*, 
               COALESCE(p.nama_lengkap, pel.nama_lengkap) as nama_lengkap,
               COALESCE(p.email, pel.email_aktif) as user_email,
               u.reset_token, 
               u.reset_token_expires,
               u.user_type
        FROM password_reset_requests r
        JOIN users u ON r.user_id = u.user_id
        LEFT JOIN pegawai p ON u.user_id = p.user_id
        LEFT JOIN pelamar pel ON u.user_id = pel.user_id
        WHERE r.request_id = ? AND r.status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        $_SESSION['error'] = "Request tidak ditemukan atau sudah diproses.";
    } else {
        
        // Generate token 6 karakter (huruf & angka uppercase)
        $token = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        //$expires = date('Y-m-d H:i:s', time() + 60); // 1 mnt
        $expires = date('Y-m-d H:i:s', time() + 86400); // 24 jam

        
        try {
            $conn->beginTransaction();
            
            // Update users table dengan token
            $stmt = $conn->prepare("
                UPDATE users
                SET reset_token = ?,
                    reset_token_expires = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$token, $expires, $request['user_id']]);
            
            // Update request status
            $stmt = $conn->prepare("
                UPDATE password_reset_requests
                SET status = 'approved',
                    token = ?,
                    approved_at = NOW(),
                    approved_by = ?,
                    expires_at = ?
                WHERE request_id = ?
            ");
            $stmt->execute([
                $token, 
                $_SESSION['user_id'], 
                $expires, 
                $request_id
            ]);
            
            $conn->commit();
            
            $_SESSION['success'] = "Token berhasil digenerate!";
            $_SESSION['generated_token'] = $token;
            $_SESSION['user_nama'] = $request['nama_lengkap'];
            $_SESSION['user_type_label'] = strtoupper($request['user_type']);
            
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Gagal generate token: " . $e->getMessage();
        }
    }
    
    header("Location: reset-password-requests.php");
    exit();
}

/* REJECT REQUEST */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_request'])) {
    
    $request_id = (int)$_POST['request_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get user_id dari request
        $stmt = $conn->prepare("SELECT user_id FROM password_reset_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $user_id = $stmt->fetchColumn();
        
        // Update status request
        $stmt = $conn->prepare("
            UPDATE password_reset_requests
            SET status = 'rejected',
                approved_at = NOW(),
                approved_by = ?
            WHERE request_id = ? AND status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        
        // Hapus token jika ada
        if ($user_id) {
            $stmt = $conn->prepare("
                UPDATE users
                SET reset_token = NULL,
                    reset_token_expires = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
        }
        
        $conn->commit();
        $_SESSION['success'] = "Request berhasil ditolak.";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal menolak request: " . $e->getMessage();
    }
    
    header("Location: reset-password-requests.php");
    exit();
}

/* REVOKE TOKEN */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_token'])) {
    
    $request_id = (int)$_POST['request_id'];
    
    try {
        $conn->beginTransaction();
        
        // Get user_id dari request
        $stmt = $conn->prepare("SELECT user_id FROM password_reset_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $user_id = $stmt->fetchColumn();
        
        // Update status request menjadi rejected
        $stmt = $conn->prepare("
            UPDATE password_reset_requests
            SET status = 'rejected'
            WHERE request_id = ? AND status = 'approved'
        ");
        $stmt->execute([$request_id]);
        
        // Hapus token dari users
        if ($user_id) {
            $stmt = $conn->prepare("
                UPDATE users
                SET reset_token = NULL,
                    reset_token_expires = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
        }
        
        $conn->commit();
        $_SESSION['success'] = "Token berhasil dibatalkan.";
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Gagal membatalkan token: " . $e->getMessage();
    }
    
    header("Location: reset-password-requests.php");
    exit();
}

/*  PENDING REQUESTS + PELAMAR */
$stmt = $conn->query("
    SELECT r.*, 
           COALESCE(p.nama_lengkap, pel.nama_lengkap) as nama_lengkap,
           COALESCE(p.email, pel.email_aktif) as user_email,
           u.user_type
    FROM password_reset_requests r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN pegawai p ON u.user_id = p.user_id
    LEFT JOIN pelamar pel ON u.user_id = pel.user_id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at DESC
");
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* GET APPROVED REQUESTS + PELAMAR */
$stmt = $conn->query("
    SELECT r.*, 
           COALESCE(p.nama_lengkap, pel.nama_lengkap) as nama_lengkap,
           COALESCE(p.email, pel.email_aktif) as user_email,
           u.reset_token_expires,
           u.user_type,
           CASE 
               WHEN u.reset_token_expires < NOW() THEN 'expired'
               ELSE 'valid'
           END as token_status,
           TIMESTAMPDIFF(MINUTE, NOW(), u.reset_token_expires) as minutes_remaining
    FROM password_reset_requests r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN pegawai p ON u.user_id = p.user_id
    LEFT JOIN pelamar pel ON u.user_id = pel.user_id
    WHERE r.status = 'approved'
    ORDER BY r.approved_at DESC
");
$approved_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* GET RECENT HISTORY + PELAMAR */
$stmt = $conn->query("
    SELECT r.*, 
           COALESCE(p.nama_lengkap, pel.nama_lengkap) as nama_lengkap,
           COALESCE(p.email, pel.email_aktif) as user_email,
           u.user_type,
           a.email as admin_email
    FROM password_reset_requests r
    JOIN users u ON r.user_id = u.user_id
    LEFT JOIN pegawai p ON u.user_id = p.user_id
    LEFT JOIN pelamar pel ON u.user_id = pel.user_id
    LEFT JOIN users a ON r.approved_by = a.user_id
    WHERE r.status IN ('completed', 'rejected')
    ORDER BY r.approved_at DESC
    LIMIT 20
");
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* statistik */
$stats_pending = $conn->query("
    SELECT COUNT(*) as total FROM password_reset_requests WHERE status = 'pending'
")->fetch()['total'];

$stats_approved = $conn->query("
    SELECT COUNT(*) as total FROM password_reset_requests WHERE status = 'approved'
")->fetch()['total'];

$stats_today = $conn->query("
    SELECT COUNT(*) as total FROM password_reset_requests 
    WHERE DATE(requested_at) = CURDATE()
")->fetch()['total'];

$page_title = 'Kelola Reset Password - Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - POLNEST</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>users/assets/logo.png">
    <style>
        :root {
            --primary-blue: #1e40af;
            --secondary-blue: #3b82f6;
            --light-blue: #dbeafe;
            --dark-blue: #1e3a8a;
        }

        body {
            background-color: #f8fafc;
            font-family: 'Poppins', sans-serif;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%);
            padding: 28px 32px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(21, 101, 192, 0.3);
        }

        .page-header h2 { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .page-header p  { font-size: 14px; opacity: 0.88; }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }

        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .stats-label {
            color: #6b7280;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .table {
            margin-bottom: 0;
            font-size: 0.85rem;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px 12px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.85rem;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge-warning {
            background: #ffc107;
            color: #856404;
        }

        .badge-success {
            background: #10b981;
            color: white;
        }

        .badge-info {
            background: #3b82f6;
            color: white;
        }

        .badge-danger {
            background: #ef4444;
            color: white;
        }

        .badge-secondary {
            background: #6b7280;
            color: white;
        }

        .badge-primary {
            background: #8b5cf6;
            color: white;
        }

        .btn-primary {
            background: var(--primary-blue);
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .btn-primary:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .btn-danger {
            background: #ef4444;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
        }

        .btn-warning {
            background: #f59e0b;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            color: white;
        }

        .token-display {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 2px;
            cursor: pointer;
            user-select: all;
            display: inline-block;
        }

        .token-display:hover {
            background: #e5e7eb;
        }

        .token-display.expired {
            background: #fee2e2;
            color: #dc2626;
            text-decoration: line-through;
        }

        .token-success-box {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .token-success-box h4 {
            color: #155724;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .token-success-box .token-display {
            background: white;
            border: 2px dashed #28a745;
            padding: 15px 20px;
            font-size: 1.3rem;
        }

        .manual-instruction {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.85rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
            font-size: 0.9rem;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .copy-btn {
            background: transparent;
            border: none;
            color: var(--secondary-blue);
            cursor: pointer;
            padding: 5px 10px;
            font-size: 1.2rem;
            transition: all 0.3s;
        }

        .copy-btn:hover {
            color: var(--dark-blue);
            transform: scale(1.2);
        }

        .time-badge {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .expired-warning {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.75rem;
            color: #991b1b;
            margin-top: 5px;
        }

        .custom-tabs {
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 30px;
        }

        .custom-tabs .nav-link {
            color: #6b7280;
            font-weight: 500;
            padding: 12px 24px;
            border: none;
            border-bottom: 3px solid transparent;
            background: none;
            transition: all 0.3s;
            text-decoration: none;
        }

        .custom-tabs .nav-link:hover {
            color: #1f2937;
            border-bottom-color: #d1d5db;
            background: none;
        }

        .custom-tabs .nav-link.active {
            color: #1f2937;
            font-weight: 600;
            border-bottom-color: #2563eb;
            background: none;
        }

        .custom-tabs .nav-link i {
            margin-right: 8px;
        }

        .custom-tabs .nav-link .badge {
            font-size: 0.65rem;
            padding: 3px 7px;
            vertical-align: middle;
        }

        /* CUSTOM CONFIRMATION MODAL */
        .custom-confirm-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }

        .custom-confirm-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .custom-confirm-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 90%;
            overflow: hidden;
            animation: slideDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .custom-confirm-header {
            padding: 24px 24px 16px;
            text-align: center;
        }

        .custom-confirm-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        .custom-confirm-icon.warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #f59e0b;
        }

        .custom-confirm-icon.danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #dc2626;
        }

        .custom-confirm-icon.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #059669;
        }

        .custom-confirm-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .custom-confirm-body {
            padding: 0 24px 24px;
            text-align: center;
        }

        .custom-confirm-message {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .custom-confirm-message strong {
            color: #1e293b;
            font-weight: 600;
        }

        .custom-confirm-buttons {
            display: flex;
            gap: 10px;
        }

        .custom-confirm-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }

        .custom-confirm-btn-confirm {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .custom-confirm-btn-confirm:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }

        .custom-confirm-btn-confirm.danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .custom-confirm-btn-confirm.danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }

        .custom-confirm-btn-cancel {
            background: #e2e8f0;
            color: #475569;
        }

        .custom-confirm-btn-cancel:hover {
            background: #cbd5e1;
        }

        .custom-confirm-icon i {
            animation: iconBounce 0.6s ease-out 0.2s both;
        }

        @keyframes iconBounce {
            0% { transform: scale(0) rotate(-180deg); }
            50% { transform: scale(1.2) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }

        .user-type-badge {
            display: inline-block;
            font-size: 0.7rem;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2><i class=""></i>Kelola Reset Password</h2>
            <p>Manage permintaan reset password dari pegawai dan pelamar</p>
        </div>

        <ul class="nav custom-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="manajemen-pegawai.php">
                    <i class="fas fa-users me-2"></i>Daftar Pegawai
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link active" href="reset-password-requests.php">
                    <i class="fas fa-key me-2"></i>Reset Password
                    <?php if ($stats_pending > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $stats_pending; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['generated_token'])): ?>
            <div class="token-success-box">
                <h4><i class="fas fa-check-circle"></i> Token Berhasil Digenerate!</h4>
                <p><strong><?php echo htmlspecialchars($_SESSION['user_type_label'] ?? 'User'); ?>:</strong> <?php echo htmlspecialchars($_SESSION['user_nama']); ?></p>
                <div class="mb-3">
                    <span class="token-display" onclick="copyToken('<?php echo $_SESSION['generated_token']; ?>')">
                        <?php echo $_SESSION['generated_token']; ?>
                    </span>
                    <button class="copy-btn" onclick="copyToken('<?php echo $_SESSION['generated_token']; ?>')">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <div class="manual-instruction">
                    <i class="fab fa-whatsapp"></i> 
                    <strong>Langkah Selanjutnya:</strong><br>
                    1. Copy token di atas (klik token atau icon copy)<br>
                    2. Kirim token ini ke WhatsApp/Email user secara manual<br>
                    3. User akan input token di halaman lupa password
                </div>
                <small class="text-muted d-block mt-2">
                    <i class="fas fa-clock"></i> Token berlaku selama 24 jam
                </small>
            </div>
            <?php 
            unset($_SESSION['generated_token']); 
            unset($_SESSION['user_nama']);
            unset($_SESSION['user_type_label']); 
            ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats_pending; ?></div>
                    <div class="stats-label"><i class="fas fa-clock me-1"></i> Request Pending</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="border-left-color: #3b82f6;">
                    <div class="stats-number" style="color: #3b82f6;"><?php echo $stats_approved; ?></div>
                    <div class="stats-label"><i class="fas fa-check me-1"></i> Token Aktif</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="border-left-color: #10b981;">
                    <div class="stats-number" style="color: #10b981;"><?php echo $stats_today; ?></div>
                    <div class="stats-label"><i class="fas fa-calendar-day me-1"></i> Request Hari Ini</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-hourglass-half me-2"></i>
                Permintaan Pending (<?php echo count($pending_requests); ?>)
            </div>
            <div class="card-body">
                <?php if (empty($pending_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada permintaan reset password yang menunggu approval</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama & Tipe User</th>
                                    <th>Email</th>
                                    <th>Waktu Request</th>
                                    <th>IP Address</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($req['nama_lengkap']); ?></strong>
                                            <br>
                                            <span class="badge user-type-badge badge-<?php echo $req['user_type'] === 'pelamar' ? 'primary' : 'secondary'; ?>">
                                                <?php echo strtoupper($req['user_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($req['user_email']); ?></td>
                                        <td>
                                            <span class="time-badge">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php echo date('d M Y, H:i', strtotime($req['requested_at'])); ?>
                                            </span>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($req['ip_address']); ?></small></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" class="approve-form" style="display:inline;">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                                    <input type="hidden" name="user_nama" value="<?php echo htmlspecialchars($req['nama_lengkap']); ?>">
                                                    <button type="button" name="approve_request" class="btn btn-success btn-sm btn-approve">
                                                        <i class="fas fa-check"></i> Generate Token
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" class="reject-form" style="display:inline;">
                                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                                    <button type="button" name="reject_request" class="btn btn-danger btn-sm btn-reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($approved_requests)): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-check-circle me-2"></i>
                Token yang Sudah Di-approve (<?php echo count($approved_requests); ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama & Tipe User</th>
                                <th>Email</th>
                                <th>Token</th>
                                <th>Status</th>
                                <th>Waktu Approve</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approved_requests as $a): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($a['nama_lengkap']); ?>
                                        <br>
                                        <span class="badge user-type-badge badge-<?php echo $a['user_type'] === 'pelamar' ? 'primary' : 'secondary'; ?>">
                                            <?php echo strtoupper($a['user_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['user_email']); ?></td>
                                    <td>
                                        <span class="token-display <?php echo $a['token_status'] === 'expired' ? 'expired' : ''; ?>" 
                                              style="font-size: 0.85rem; padding: 5px 8px;">
                                            <?php echo htmlspecialchars($a['token']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($a['token_status'] === 'expired'): ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times-circle me-1"></i>EXPIRED
                                            </span>
                                            <div class="expired-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                Token sudah kadaluarsa. Batalkan dan generate ulang jika diperlukan.
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle me-1"></i>VALID
                                            </span>
                                            <div class="time-badge mt-1">
                                                <i class="fas fa-hourglass-half me-1"></i>
                                                Sisa: <?php echo $a['minutes_remaining']; ?> menit
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('d M Y, H:i', strtotime($a['approved_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <form method="POST" class="revoke-form" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $a['request_id']; ?>">
                                            <button type="button" name="revoke_token" class="btn btn-warning btn-sm btn-revoke">
                                                <i class="fas fa-ban"></i> Batalkan
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-history me-2"></i>
                Riwayat Request (20 Terakhir)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama & Tipe User</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Token</th>
                                <th>Waktu Proses</th>
                                <th>Diproses Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Belum ada riwayat
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($h['nama_lengkap']); ?>
                                            <br>
                                            <span class="badge user-type-badge badge-<?php echo $h['user_type'] === 'pelamar' ? 'primary' : 'secondary'; ?>">
                                                <?php echo strtoupper($h['user_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($h['user_email']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'secondary';
                                            $icon = 'circle';
                                            if ($h['status'] === 'completed') {
                                                $badge_class = 'success';
                                                $icon = 'check-circle';
                                            } elseif ($h['status'] === 'rejected') {
                                                $badge_class = 'danger';
                                                $icon = 'times-circle';
                                            }
                                            ?>
                                            <span class="badge badge-<?php echo $badge_class; ?>">
                                                <i class="fas fa-<?php echo $icon; ?> me-1"></i>
                                                <?php echo strtoupper($h['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($h['token'])): ?>
                                                <span class="token-display" style="font-size: 0.85rem; padding: 5px 8px;">
                                                    <?php echo htmlspecialchars($h['token']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo $h['approved_at'] ? date('d M Y, H:i', strtotime($h['approved_at'])) : '-'; ?>
                                            </small>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($h['admin_email'] ?? '-'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- modal konfirmasi -->
    <div class="custom-confirm-overlay" id="customConfirm">
        <div class="custom-confirm-box">
            <div class="custom-confirm-header">
                <div class="custom-confirm-icon" id="confirmIcon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h3 class="custom-confirm-title" id="confirmTitle">Konfirmasi</h3>
            </div>
            <div class="custom-confirm-body">
                <div class="custom-confirm-message" id="confirmMessage">
                    Apakah Anda yakin?
                </div>
                <div class="custom-confirm-buttons">
                    <button class="custom-confirm-btn custom-confirm-btn-cancel" id="confirmCancelBtn">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button class="custom-confirm-btn custom-confirm-btn-confirm" id="confirmOkBtn">
                        <i class="fas fa-check me-1"></i> Ya, Lanjutkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        // dialog konfirm
        function showCustomConfirm(options) {
            return new Promise((resolve) => {
                const overlay = document.getElementById('customConfirm');
                const icon = document.getElementById('confirmIcon');
                const iconElement = icon.querySelector('i');
                const title = document.getElementById('confirmTitle');
                const message = document.getElementById('confirmMessage');
                const okBtn = document.getElementById('confirmOkBtn');
                const cancelBtn = document.getElementById('confirmCancelBtn');

                const type = options.type || 'warning';
                icon.className = 'custom-confirm-icon ' + type;

                const icons = {
                    warning: 'fa-exclamation-triangle',
                    danger: 'fa-times-circle',
                    success: 'fa-check-circle'
                };
                iconElement.className = 'fas ' + icons[type];

                title.textContent = options.title || 'Konfirmasi';
                message.innerHTML = options.message || 'Apakah Anda yakin?';

                okBtn.className = 'custom-confirm-btn custom-confirm-btn-confirm';
                if (type === 'danger') {
                    okBtn.classList.add('danger');
                }

                const btnText = options.confirmText || 'Ya, Lanjutkan';
                okBtn.innerHTML = `<i class="fas fa-check me-1"></i> ${btnText}`;

                overlay.classList.add('active');

                okBtn.onclick = () => {
                    overlay.classList.remove('active');
                    resolve(true);
                };

                cancelBtn.onclick = () => {
                    overlay.classList.remove('active');
                    resolve(false);
                };

                overlay.onclick = (e) => {
                    if (e.target === overlay) {
                        overlay.classList.remove('active');
                        resolve(false);
                    }
                };
            });
        }

        // APPROVE REQUEST
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const nama = form.querySelector('input[name="user_nama"]').value;
                
                const confirmed = await showCustomConfirm({
                    type: 'success',
                    title: 'Generate Token',
                    message: `Apakah Anda yakin ingin generate token untuk <strong>${nama}</strong>?<br><br>Token akan berlaku selama <strong>24 jam</strong> dan akan dikirim ke user.`,
                    confirmText: 'Ya, Generate'
                });
                
                if (confirmed) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'approve_request';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        });

        // REJECT REQUEST
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const form = this.closest('form');
                
                const confirmed = await showCustomConfirm({
                    type: 'danger',
                    title: 'Tolak Request',
                    message: 'Apakah Anda yakin ingin <strong>menolak</strong> permintaan reset password ini?<br><br>User harus mengajukan request baru jika masih membutuhkan reset password.',
                    confirmText: 'Ya, Tolak'
                });
                
                if (confirmed) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'reject_request';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        });

        // REVOKE TOKEN
        document.querySelectorAll('.btn-revoke').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                const form = this.closest('form');
                
                const confirmed = await showCustomConfirm({
                    type: 'warning',
                    title: 'Batalkan Token',
                    message: 'Apakah Anda yakin ingin <strong>membatalkan</strong> token ini?<br><br>Token yang sudah dibatalkan tidak dapat digunakan lagi dan user harus request ulang.',
                    confirmText: 'Ya, Batalkan'
                });
                
                if (confirmed) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'revoke_token';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        });

        // COPY TOKEN FUNCTION
        function copyToken(token) {
            const textarea = document.createElement('textarea');
            textarea.value = token;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            Toast.fire({
                icon: 'success',
                title: 'Token berhasil disalin!',
                text: 'Kirim ke WhatsApp/Email user.'
            });
        }

        // AUTO-DISMISS ALERTS
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>