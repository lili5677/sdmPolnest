<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit();
}

// ==========================================
// HANDLE ACTIONS
// ==========================================

// CREATE TEMPLATE
if (isset($_POST['action']) && $_POST['action'] === 'create_template') {
    $nama = trim($_POST['nama_template']);
    $periode = $_POST['periode'] . '-01';
    
    try {
        $stmt = $conn->prepare("INSERT INTO penilaian_template (nama_template, periode, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $periode, $_SESSION['user_id']]);
        $_SESSION['success'] = "Template berhasil dibuat!";
        header("Location: template.php?id=" . $conn->lastInsertId());
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal membuat template: " . $e->getMessage();
    }
}

// UPDATE TEMPLATE
if (isset($_POST['action']) && $_POST['action'] === 'update_template') {
    $template_id = (int)$_POST['template_id'];
    $nama = trim($_POST['nama_template']);
    $periode = $_POST['periode'] . '-01';
    
    try {
        $stmt = $conn->prepare("UPDATE penilaian_template SET nama_template = ?, periode = ? WHERE template_id = ?");
        $stmt->execute([$nama, $periode, $template_id]);
        $_SESSION['success'] = "Template berhasil diupdate!";
        header("Location: template.php?id=" . $template_id);
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal update template: " . $e->getMessage();
    }
}

// DELETE TEMPLATE
if (isset($_POST['action']) && $_POST['action'] === 'delete_template') {
    $template_id = (int)$_POST['template_id'];
    
    try {
        // Check if has penilaian
        $stmt = $conn->prepare("SELECT COUNT(*) as jml FROM penilaian_kinerja WHERE template_id = ?");
        $stmt->execute([$template_id]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['jml'];
        
        if ($count > 0) {
            $_SESSION['error'] = "Template tidak bisa dihapus karena sudah ada penilaian yang menggunakannya!";
        } else {
            $stmt = $conn->prepare("DELETE FROM penilaian_template WHERE template_id = ?");
            $stmt->execute([$template_id]);
            $_SESSION['success'] = "Template berhasil dihapus!";
        }
        header("Location: template.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menghapus template: " . $e->getMessage();
        header("Location: template.php");
        exit();
    }
}

// ADD INDIKATOR
if (isset($_POST['action']) && $_POST['action'] === 'add_indikator') {
    $template_id = (int)$_POST['template_id'];
    $nama = trim($_POST['nama_indikator']);
    $keterangan = trim($_POST['keterangan']);
    
    try {
        // Get max urutan
        $stmt = $conn->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM penilaian_indikator WHERE template_id = ?");
        $stmt->execute([$template_id]);
        $next_urutan = $stmt->fetch(PDO::FETCH_ASSOC)['next_urutan'];
        
        $stmt = $conn->prepare("INSERT INTO penilaian_indikator (template_id, nama_indikator, keterangan, urutan) VALUES (?, ?, ?, ?)");
        $stmt->execute([$template_id, $nama, $keterangan, $next_urutan]);
        $_SESSION['success'] = "Indikator berhasil ditambahkan!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menambah indikator: " . $e->getMessage();
    }
    header("Location: template.php?id=" . $template_id);
    exit();
}

// UPDATE INDIKATOR
if (isset($_POST['action']) && $_POST['action'] === 'update_indikator') {
    $indikator_id = (int)$_POST['indikator_id'];
    $template_id = (int)$_POST['template_id'];
    $nama = trim($_POST['nama_indikator']);
    $keterangan = trim($_POST['keterangan']);
    
    try {
        $stmt = $conn->prepare("UPDATE penilaian_indikator SET nama_indikator = ?, keterangan = ? WHERE indikator_id = ?");
        $stmt->execute([$nama, $keterangan, $indikator_id]);
        $_SESSION['success'] = "Indikator berhasil diupdate!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal update indikator: " . $e->getMessage();
    }
    header("Location: template.php?id=" . $template_id);
    exit();
}

// DELETE INDIKATOR
if (isset($_POST['action']) && $_POST['action'] === 'delete_indikator') {
    $indikator_id = (int)$_POST['indikator_id'];
    $template_id = (int)$_POST['template_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM penilaian_indikator WHERE indikator_id = ?");
        $stmt->execute([$indikator_id]);
        $_SESSION['success'] = "Indikator berhasil dihapus!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Gagal menghapus indikator: " . $e->getMessage();
    }
    header("Location: template.php?id=" . $template_id);
    exit();
}

// ==========================================
// GET DATA
// ==========================================

// Get template ID
$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_indikator_id = isset($_GET['edit_ind']) ? (int)$_GET['edit_ind'] : 0;

// Get all templates
$stmt = $conn->query("SELECT pt.*, 
                             COUNT(DISTINCT pi.indikator_id) as jml_indikator,
                             COUNT(DISTINCT pk.penilaian_id) as jml_penilaian,
                             u.email as created_by_email
                      FROM penilaian_template pt
                      LEFT JOIN penilaian_indikator pi ON pt.template_id = pi.template_id
                      LEFT JOIN penilaian_kinerja pk ON pt.template_id = pk.template_id
                      LEFT JOIN users u ON pt.created_by = u.user_id
                      GROUP BY pt.template_id
                      ORDER BY pt.periode DESC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected template
$template = null;
$indikator_list = [];
if ($template_id > 0) {
    foreach ($templates as $t) {
        if ($t['template_id'] == $template_id) {
            $template = $t;
            break;
        }
    }
    
    if ($template) {
        $stmt = $conn->prepare("SELECT * FROM penilaian_indikator WHERE template_id = ? ORDER BY urutan ASC");
        $stmt->execute([$template_id]);
        $indikator_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get edit indikator
$edit_indikator = null;
if ($edit_indikator_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM penilaian_indikator WHERE indikator_id = ?");
    $stmt->execute([$edit_indikator_id]);
    $edit_indikator = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Template Penilaian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <style>
        .main-content { margin-left: 250px; padding: 20px; background: #f8f9fa; min-height: 100vh; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .template-item { 
            cursor: pointer; 
            transition: all 0.3s; 
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .template-item:hover { background: #e7f3ff; border-color: #007bff; }
        .template-item.active { background: #e7f3ff; border-color: #007bff; border-width: 3px; }
        .indikator-item { 
            background: white; 
            border: 2px solid #dee2e6; 
            padding: 15px; 
            margin-bottom: 10px; 
            border-radius: 8px; 
            cursor: move;
            transition: all 0.3s;
        }
        .indikator-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .preview-box {
            background: #fff;
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .radio-demo { display: inline-block; padding: 6px 12px; border: 2px solid #dee2e6; border-radius: 6px; margin-right: 5px; font-size: 0.9rem; }
        .radio-demo.sb { background: #28a745; color: white; border-color: #28a745; }
        .radio-demo.b { background: #17a2b8; color: white; border-color: #17a2b8; }
        .radio-demo.c { background: #ffc107; color: #000; border-color: #ffc107; }
        .radio-demo.k { background: #dc3545; color: white; border-color: #dc3545; }
        .info-field { 
            background: #f8f9fa; 
            border: 2px solid #007bff; 
            border-radius: 8px; 
            padding: 15px; 
            margin-bottom: 20px;
        }
        .info-field-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        .info-field-value {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include '../sidebar/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Manajemen Template Penilaian</h2>
                    <p class="text-muted">Kelola template dan indikator penilaian kinerja</p>
                </div>
                <div>
                    <a href="penilaianKinerja.php" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTemplate">
                        <i class="bi bi-plus-circle"></i> Buat Template Baru
                    </button>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Sidebar: Daftar Template -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> Daftar Template</h5>
                        </div>
                        <div class="card-body p-3">
                            <?php if (count($templates) > 0): ?>
                                <?php foreach ($templates as $t): ?>
                                    <div class="template-item <?= $template && $t['template_id'] == $template['template_id'] ? 'active' : '' ?>"
                                         onclick="window.location='template.php?id=<?= $t['template_id'] ?>'">
                                        <h6 class="mb-1"><?= htmlspecialchars($t['nama_template']) ?></h6>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> <?= date('M Y', strtotime($t['periode'])) ?> • 
                                            <i class="bi bi-list-check"></i> <?= $t['jml_indikator'] ?> indikator • 
                                            <i class="bi bi-people"></i> <?= $t['jml_penilaian'] ?> penilaian
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                    <p class="text-muted mt-2">Belum ada template</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Main: Detail Template -->
                <div class="col-md-8">
                    <?php if ($template): ?>
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?= htmlspecialchars($template['nama_template']) ?></h5>
                                    <small class="text-muted">
                                        Periode: <?= date('F Y', strtotime($template['periode'])) ?> • 
                                        Dibuat oleh: <?= htmlspecialchars($template['created_by_email']) ?>
                                    </small>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditTemplate">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <?php if ($template['jml_penilaian'] == 0): ?>
                                        <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('Yakin hapus template ini?')) document.getElementById('delTemplateForm').submit();">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                        <form id="delTemplateForm" method="POST" style="display:none;">
                                            <input type="hidden" name="action" value="delete_template">
                                            <input type="hidden" name="template_id" value="<?= $template_id ?>">
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Form Tambah Indikator -->
                                <form method="POST" class="mb-4 p-3 bg-light rounded">
                                    <input type="hidden" name="action" value="add_indikator">
                                    <input type="hidden" name="template_id" value="<?= $template_id ?>">
                                    <h6 class="mb-3"><i class="bi bi-plus-circle"></i> Tambah Indikator Baru</h6>
                                    <div class="row">
                                        <div class="col-md-5 mb-2">
                                            <input type="text" name="nama_indikator" class="form-control" 
                                                   placeholder="Nama Indikator *" required>
                                        </div>
                                        <div class="col-md-5 mb-2">
                                            <input type="text" name="keterangan" class="form-control" 
                                                   placeholder="Keterangan (opsional)">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="bi bi-plus"></i> Tambah
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <!-- Daftar Indikator -->
                                <h6 class="mb-3"><i class="bi bi-list-check"></i> Daftar Indikator (<?= count($indikator_list) ?>)</h6>
                                
                                <?php if (count($indikator_list) > 0): ?>
                                    <div id="sortableIndikator">
                                        <?php foreach ($indikator_list as $ind): ?>
                                            <div class="indikator-item" data-id="<?= $ind['indikator_id'] ?>">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-grip-vertical text-muted me-3 fs-4"></i>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0"><?= htmlspecialchars($ind['nama_indikator']) ?></h6>
                                                        <?php if ($ind['keterangan']): ?>
                                                            <small class="text-muted"><?= htmlspecialchars($ind['keterangan']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?id=<?= $template_id ?>&edit_ind=<?= $ind['indikator_id'] ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button class="btn btn-outline-danger" 
                                                                onclick="if(confirm('Hapus indikator ini?')) document.getElementById('delInd<?= $ind['indikator_id'] ?>').submit();">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <form id="delInd<?= $ind['indikator_id'] ?>" method="POST" style="display:none;">
                                                    <input type="hidden" name="action" value="delete_indikator">
                                                    <input type="hidden" name="indikator_id" value="<?= $ind['indikator_id'] ?>">
                                                    <input type="hidden" name="template_id" value="<?= $template_id ?>">
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> Drag & drop untuk mengubah urutan indikator
                                    </small>

                                    <!-- Preview -->
                                    <div class="preview-box mt-4">
                                        <h6 class="mb-3"><i class="bi bi-eye"></i> Preview Tampilan User</h6>
                                        
                                        <!-- Field Informasi Karyawan (Paten) -->
                                        <div class="alert alert-info mb-3">
                                            <i class="bi bi-info-circle"></i> <strong>Informasi Berikut Akan Otomatis Terisi dari Data Karyawan yang Dinilai</strong>
                                        </div>
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <div class="info-field">
                                                    <div class="info-field-label">
                                                        <i class="bi bi-person"></i> Nama Lengkap
                                                    </div>
                                                    <div class="info-field-value">
                                                        [Akan terisi otomatis]
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="info-field">
                                                    <div class="info-field-label">
                                                        <i class="bi bi-briefcase"></i> Jabatan/Posisi
                                                    </div>
                                                    <div class="info-field-value">
                                                        [Akan terisi otomatis]
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="info-field">
                                                    <div class="info-field-label">
                                                        <i class="bi bi-building"></i> Unit Kerja
                                                    </div>
                                                    <div class="info-field-value">
                                                        [Akan terisi otomatis]
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <!-- Kriteria Penilaian -->
                                        <h6 class="mb-3"><i class="bi bi-clipboard-check"></i> Kriteria Penilaian</h6>
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="45%">Kriteria Penilaian</th>
                                                        <th width="55%">Penilaian</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($indikator_list as $ind): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($ind['nama_indikator']) ?></strong>
                                                                <?php if ($ind['keterangan']): ?>
                                                                    <br><small class="text-muted"><?= htmlspecialchars($ind['keterangan']) ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <span class="radio-demo sb">Sangat Baik</span>
                                                                <span class="radio-demo b">Baik</span>
                                                                <span class="radio-demo c">Cukup</span>
                                                                <span class="radio-demo k">Kurang</span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="alert alert-warning mt-3">
                                            <i class="bi bi-lightbulb"></i> <strong>Catatan:</strong> Field Nama Lengkap, Jabatan/Posisi, dan Unit Kerja akan otomatis terisi dari data karyawan yang dipilih saat membuat penilaian.
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                        <h5 class="text-muted mt-3">Belum Ada Indikator</h5>
                                        <p class="text-muted">Silakan tambah indikator menggunakan form di atas</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-arrow-left-circle" style="font-size: 5rem; color: #ccc;"></i>
                                <h5 class="text-muted mt-3">Pilih Template</h5>
                                <p class="text-muted">Pilih template dari sidebar untuk mengelola indikator</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Create Template -->
    <div class="modal fade" id="modalCreateTemplate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_template">
                    <div class="modal-header">
                        <h5 class="modal-title">Buat Template Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Template *</label>
                            <input type="text" name="nama_template" class="form-control" 
                                   placeholder="Contoh: Penilaian Kinerja Semester 1 2026" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Periode *</label>
                            <input type="month" name="periode" class="form-control" required>
                            <small class="text-muted">Pilih bulan dan tahun periode penilaian</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Buat Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Template -->
    <?php if ($template): ?>
    <div class="modal fade" id="modalEditTemplate" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_template">
                    <input type="hidden" name="template_id" value="<?= $template_id ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Template</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Template *</label>
                            <input type="text" name="nama_template" class="form-control" 
                                   value="<?= htmlspecialchars($template['nama_template']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Periode *</label>
                            <input type="month" name="periode" class="form-control" 
                                   value="<?= date('Y-m', strtotime($template['periode'])) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Edit Indikator -->
    <?php if ($edit_indikator): ?>
    <div class="modal fade show" id="modalEditIndikator" tabindex="-1" 
         style="display: block;" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_indikator">
                    <input type="hidden" name="indikator_id" value="<?= $edit_indikator['indikator_id'] ?>">
                    <input type="hidden" name="template_id" value="<?= $edit_indikator['template_id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Indikator</h5>
                        <button type="button" class="btn-close" 
                                onclick="window.location='?id=<?= $edit_indikator['template_id'] ?>'"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Indikator *</label>
                            <input type="text" name="nama_indikator" class="form-control" 
                                   value="<?= htmlspecialchars($edit_indikator['nama_indikator']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($edit_indikator['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" 
                                onclick="window.location='?id=<?= $edit_indikator['template_id'] ?>'">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sortable untuk drag & drop
        $(function() {
            $("#sortableIndikator").sortable({
                handle: ".bi-grip-vertical",
                update: function() {
                    const order = $(this).sortable('toArray', { attribute: 'data-id' });
                    $.post('ajax.php', {
                        action: 'update_urutan',
                        template_id: <?= $template_id ?>,
                        order: order
                    });
                }
            });
        });
    </script>
</body>
</html>