<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Pengaturan Kategori Kas & Keuangan CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('finance.view');

$db = Database::getInstance();

// Auto Ensure Table Exists
$db->exec("
    CREATE TABLE IF NOT EXISTS `finance_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_type` ENUM('IN', 'OUT') NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// -------------------------------------------------------------
// POST HANDLER: TAMBAH & HAPUS KATEGORI KEUANGAN
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('finance.manage');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_category') {
        csrf_validate();
        $catType = sanitize($_POST['category_type'] ?? 'OUT');
        $name    = sanitize($_POST['name'] ?? '');

        if (!empty($name)) {
            $stmt = $db->prepare("INSERT INTO finance_categories (category_type, name) VALUES (?, ?)");
            $stmt->execute([$catType, $name]);
            log_audit('CREATE_FINANCE_CATEGORY', 'finance', $db->lastInsertId(), "Menambah kategori {$catType}: {$name}");
            flash_set('success', "Kategori '{$name}' berhasil ditambahkan!");
            redirect('modules/finance/categories.php');
        } else {
            flash_set('danger', "Nama kategori tidak boleh kosong!");
            redirect('modules/finance/categories.php');
        }
    }

    elseif ($action === 'delete_category') {
        csrf_validate();
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            $db->prepare("DELETE FROM finance_categories WHERE id = ?")->execute([$catId]);
            log_audit('DELETE_FINANCE_CATEGORY', 'finance', $catId, "Menghapus kategori keuangan ID: {$catId}");
            flash_set('success', "Kategori berhasil dihapus!");
            redirect('modules/finance/categories.php');
        }
    }
}

// Queries
$filterType = sanitize($_GET['type'] ?? '');
$search     = sanitize($_GET['q'] ?? '');

$sql = "SELECT * FROM finance_categories WHERE 1=1";
$params = [];

if ($filterType !== '') {
    $sql .= " AND category_type = ?";
    $params[] = $filterType;
}

if ($search !== '') {
    $sql .= " AND name LIKE ?";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY category_type ASC, name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$pageTitle = 'Pengaturan Kategori Kas & Keuangan';
$currentModule = 'finance';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Keuangan' => base_url('modules/finance/index.php'),
    'Kategori' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-tags text-primary me-2"></i>Pengaturan Kategori Kas</h4>
            <p class="text-muted small mb-0">Kelola master kategori untuk transaksi Kas Masuk (Pemasukan) dan Kas Keluar (Pengeluaran)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('modules/finance/index.php') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Buku Kas
            </a>
            <?php if (Permission::can('finance.manage')): ?>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCatWeb">
                    <i class="bi bi-plus-circle me-1"></i> Tambah Kategori Baru
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FILTER & PENCARIAN -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari nama kategori...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">-- Semua Jenis Kategori --</option>
                    <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>Kas Masuk (Pemasukan)</option>
                    <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>Kas Keluar (Pengeluaran)</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <?php if ($search !== '' || $filterType !== ''): ?>
                    <a href="<?= base_url('modules/finance/categories.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL DAFTAR KATEGORI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary"><i class="bi bi-list-check"></i> Master Data Kategori Transaksi</h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($categories) ?> Kategori Tersedia</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 60px;">No</th>
                        <th>Jenis Kategori</th>
                        <th>Nama Kategori</th>
                        <th>Status</th>
                        <th class="text-end pe-4" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                Belum ada kategori yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($categories as $c): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $no++ ?></td>
                                <td>
                                    <?php if ($c['category_type'] === 'IN'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1">
                                            <i class="bi bi-arrow-down-left-circle me-1"></i>Kas Masuk (Pemasukan)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 px-2 py-1">
                                            <i class="bi bi-arrow-up-right-circle me-1"></i>Kas Keluar (Pengeluaran)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold text-dark fs-6">
                                    <?= e($c['name']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-success border"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (Permission::can('finance.manage')): ?>
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori <?= e(addslashes($c['name'])) ?>?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Kategori">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH KATEGORI WEB -->
<div class="modal fade" id="modalAddCatWeb" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_category">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Kategori Kas Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Jenis Kategori <span class="text-danger">*</span></label>
                    <select name="category_type" class="form-select" required>
                        <option value="IN">Kas Masuk (Pemasukan)</option>
                        <option value="OUT" selected>Kas Keluar (Pengeluaran / Biaya)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Pajak & Retribusi / Sewa Gudang / Konsumsi" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
