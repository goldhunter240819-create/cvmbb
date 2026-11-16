<?php
/**
 * MBB MOBILE APK - Keuangan & Multi-Kas
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Permission.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../helpers/utils.php';

Auth::requireLogin('apk/login.php');
$user = Auth::user();
$db = Database::getInstance();

// Pastikan Tabel finance_categories Ada & Terisi Secara Otomatis & Aman
$db->exec("
    CREATE TABLE IF NOT EXISTS `finance_categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `category_type` ENUM('IN', 'OUT') NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_type (category_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Catat Kas Masuk / Keluar
    if ($action === 'create_cash') {
        csrf_validate();

        $direction   = sanitize($_POST['trx_direction'] ?? 'IN');
        $accountId   = (int)($_POST['cash_account_id'] ?? 1);
        $category    = sanitize($_POST['category'] ?? 'Operasional');
        $amount      = (float)($_POST['amount'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $trxDate     = sanitize($_POST['trx_date'] ?? date('Y-m-d'));

        if ($amount > 0) {
            $trxNo = 'KAS/' . date('Ymd') . '/' . rand(100, 999);

            $stmt = $db->prepare("
                INSERT INTO cash_transactions (
                    trx_code, cash_account_id, trx_date, trx_direction,
                    category, amount, description, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $trxNo, $accountId, $trxDate, $direction,
                $category, $amount, $description, Auth::id()
            ]);

            if ($direction === 'IN') {
                $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $accountId]);
            } else {
                $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $accountId]);
            }

            log_audit('CREATE_CASH_TRX_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat mutasi kas {$direction} Rp {$amount} ({$description})");
            flash_set('success', "Mutasi kas {$trxNo} berhasil disimpan!");
            redirect('apk/finance.php');
        }
    }

    // 2. Tambah Kategori Baru
    elseif ($action === 'add_category') {
        csrf_validate();
        $catType = sanitize($_POST['category_type'] ?? 'OUT');
        $catName = sanitize($_POST['category_name'] ?? '');

        if (!empty($catName)) {
            $db->prepare("INSERT INTO finance_categories (category_type, name) VALUES (?, ?)")->execute([$catType, $catName]);
            flash_set('success', "Kategori '{$catName}' berhasil ditambahkan!");
            redirect('apk/finance.php');
        }
    }

    // 3. Hapus Kategori
    elseif ($action === 'delete_category') {
        csrf_validate();
        $catId = (int)($_POST['category_id'] ?? 0);
        if ($catId > 0) {
            $db->prepare("DELETE FROM finance_categories WHERE id = ?")->execute([$catId]);
            flash_set('success', "Kategori berhasil dihapus!");
            redirect('apk/finance.php');
        }
    }
}

$mobilePageTitle = 'Keuangan & Kas';
$currentApkNav   = 'finance';

$mobileHeaderAction = '
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="dropdown" title="Menu Kas">
            <i class="bi bi-three-dots-vertical text-primary fw-bold"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3" style="font-size: 0.85rem;">
            <li><a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#modalAddCash"><i class="bi bi-plus-circle text-primary me-2"></i>Catat Mutasi Kas</a></li>
            <li><a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#modalManageCategories"><i class="bi bi-tags text-warning me-2"></i>Kelola Kategori Kas</a></li>
            <li><a class="dropdown-item py-2 fw-semibold" href="#" data-bs-toggle="modal" data-bs-target="#modalDetailKas"><i class="bi bi-wallet2 text-info me-2"></i>Rincian Rekening Kas</a></li>
        </ul>
    </div>
';

require_once __DIR__ . '/layout/header.php';

// Queries
$accounts = $db->query("SELECT * FROM cash_accounts WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$totalLiquid = array_sum(array_column($accounts, 'current_balance'));

try {
    $inCategories = $db->query("SELECT id, name FROM finance_categories WHERE category_type = 'IN' AND is_active = 1 ORDER BY name ASC")->fetchAll();
    $outCategories = $db->query("SELECT id, name FROM finance_categories WHERE category_type = 'OUT' AND is_active = 1 ORDER BY name ASC")->fetchAll();

    if (empty($inCategories) && empty($outCategories)) {
        $defaultIn = [
            'Penjualan Kopi Trading',
            'Penjualan Kopi Bubuk',
            'Fee Jasa Roasting',
            'Fee Agen BRILink',
            'Jasa Sewa Sound Sistem',
            'Jasa Sewa Dekorasi',
            'Pelunasan Piutang Usaha',
            'Modal Masuk / Investasi',
            'Pemasukan Lain-lain'
        ];
        $defaultOut = [
            'Pembelian Kopi Petani',
            'Operasional Kantor',
            'Gaji & Upah Karyawan',
            'BBM & Transportasi Pengiriman',
            'Listrik, Air & Internet',
            'Pembelian Karung & Kemasan',
            'Konsumsi & Dapur Lapangan',
            'Perawatan Mesin & Aset',
            'Pembayaran Hutang Usaha',
            'Pengeluaran Lain-lain'
        ];

        $insStmt = $db->prepare("INSERT INTO finance_categories (category_type, name) VALUES (?, ?)");
        foreach ($defaultIn as $c) { $insStmt->execute(['IN', $c]); }
        foreach ($defaultOut as $c) { $insStmt->execute(['OUT', $c]); }

        $inCategories = $db->query("SELECT id, name FROM finance_categories WHERE category_type = 'IN' AND is_active = 1 ORDER BY name ASC")->fetchAll();
        $outCategories = $db->query("SELECT id, name FROM finance_categories WHERE category_type = 'OUT' AND is_active = 1 ORDER BY name ASC")->fetchAll();
    }
} catch (Exception $e) {
    $inCategories = [
        ['id' => 1, 'name' => 'Penjualan Kopi Trading'],
        ['id' => 2, 'name' => 'Fee Agen BRILink'],
        ['id' => 3, 'name' => 'Fee Jasa Roasting'],
        ['id' => 4, 'name' => 'Pemasukan Lain-lain']
    ];
    $outCategories = [
        ['id' => 5, 'name' => 'Operasional Kantor'],
        ['id' => 6, 'name' => 'BBM & Transportasi Pengiriman'],
        ['id' => 7, 'name' => 'Gaji & Upah Karyawan'],
        ['id' => 8, 'name' => 'Pengeluaran Lain-lain']
    ];
}

$transactions = $db->query("
    SELECT t.*, a.account_name 
    FROM cash_transactions t 
    LEFT JOIN cash_accounts a ON t.cash_account_id = a.id 
    ORDER BY t.trx_date DESC, t.id DESC 
    LIMIT 30
")->fetchAll();
?>

<!-- SUMMARY HEADER TOTAL KAS LIKUID -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #0d1e3a 0%, #1e40af 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(13, 30, 58, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="small text-white-50 fw-semibold">Total Kas Likuid Operasional</span>
        <a href="<?= base_url('apk/balance_sheet.php') ?>" class="badge bg-white text-dark small text-decoration-none px-2 py-1 font-monospace">Lihat Neraca &raquo;</a>
    </div>
    <h3 class="fw-bold mb-2 text-white"><?= format_rupiah($totalLiquid) ?></h3>
    <div class="d-flex justify-content-between align-items-center pt-2 border-top border-white border-opacity-15">
        <div class="text-white-50 small" style="font-size: 0.72rem;">
            Tersebar di <?= count($accounts) ?> Rekening Bank & Kas Tunai
        </div>
        <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-light px-2 py-0 fw-semibold rounded-pill" style="font-size: 0.7rem;" data-bs-toggle="modal" data-bs-target="#modalManageCategories">
                <i class="bi bi-tags me-1"></i>Kategori
            </button>
            <button type="button" class="btn btn-sm btn-light text-primary fw-bold px-2 py-0 rounded-pill shadow-sm" style="font-size: 0.7rem;" data-bs-toggle="modal" data-bs-target="#modalDetailKas">
                <i class="bi bi-eye-fill me-1"></i>Rincian
            </button>
        </div>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    <span>Mutasi Buku Kas Besar Terakhir</span>
    <span class="badge bg-light text-dark border"><?= count($transactions) ?> Transaksi</span>
</div>

<!-- DAFTAR MUTASI TRANSAKSI -->
<?php if (empty($transactions)): ?>
    <div class="mobile-card text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        Belum ada transaksi kas yang dicatat.
    </div>
<?php else: ?>
    <?php foreach ($transactions as $t): ?>
        <div class="mobile-card mb-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle <?= $t['trx_direction'] === 'IN' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 1rem; flex-shrink: 0;">
                        <i class="bi <?= $t['trx_direction'] === 'IN' ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                            <?= e($t['description']) ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            <?= e($t['account_name']) ?> &bull; <?= date('d M Y', strtotime($t['trx_date'])) ?>
                        </div>
                    </div>
                </div>
                <span class="badge <?= $t['trx_direction'] === 'IN' ? 'bg-success-subtle text-success border border-success border-opacity-25' : 'bg-danger-subtle text-danger border border-danger border-opacity-25' ?> px-2 py-1" style="font-size: 0.68rem;">
                    <?= $t['trx_direction'] === 'IN' ? '+ MASUK' : '- KELUAR' ?>
                </span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.8rem;">
                <span class="badge bg-light text-secondary border"><?= e($t['category']) ?></span>
                <div class="fw-bold fs-6 <?= $t['trx_direction'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                    <?= $t['trx_direction'] === 'IN' ? '+' : '-' ?> <?= format_rupiah($t['amount']) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- FAB -->
<button type="button" class="mobile-fab" style="background: linear-gradient(135deg, #0d1e3a 0%, #1e40af 100%);" data-bs-toggle="modal" data-bs-target="#modalAddCash" title="Catat Kas Baru">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- MODAL CATAT KAS MOBILE -->
<div class="modal fade" id="modalAddCash" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_cash">
            <div class="modal-header bg-dark text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Catat Mutasi Kas Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Arah Kas</label>
                    <select name="trx_direction" id="trx_direction_select" class="form-select form-select-sm fw-bold" required onchange="updateCategoryOptions()">
                        <option value="IN" class="text-success">&#8600; Kas Masuk (Pemasukan)</option>
                        <option value="OUT" class="text-danger" selected>&#8599; Kas Keluar (Pengeluaran / Biaya)</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Pilih Rekening / Buku Kas</label>
                    <select name="cash_account_id" class="form-select form-select-sm" required>
                        <?php foreach ($accounts as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= e($a['account_name']) ?> (Saldo: <?= format_rupiah($a['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0">Kategori</label>
                            <a href="#" class="text-primary text-decoration-none" style="font-size: 0.68rem;" onclick="openManageFromAddModal()">+ Kelola</a>
                        </div>
                        <select name="category" id="trx_category_select" class="form-select form-select-sm" required>
                            <!-- Diisi secara dinamis melalui JavaScript sesuai Arah Kas -->
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Tanggal Transaksi</label>
                        <input type="date" name="trx_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nominal (Rp)</label>
                    <input type="number" step="1000" name="amount" class="form-control form-control-sm fw-bold" placeholder="Contoh: 1500000" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Deskripsi / Keperluan</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Cth: Bayar BBM pick up / Penjualan kopi" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-dark px-3 fw-bold">Simpan Mutasi Kas</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL KELOLA KATEGORI KAS (TAMBAH & HAPUS) -->
<div class="modal fade" id="modalManageCategories" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-tags me-2"></i>Pengaturan Kategori Kas</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Form Tambah Kategori Baru -->
                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="small fw-bold text-dark mb-2"><i class="bi bi-plus-circle me-1 text-primary"></i>Tambah Kategori Baru</div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_category">
                        <div class="row g-2 mb-2">
                            <div class="col-5">
                                <select name="category_type" class="form-select form-select-sm fw-semibold" required>
                                    <option value="IN" class="text-success">Kas Masuk</option>
                                    <option value="OUT" class="text-danger" selected>Kas Keluar</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <input type="text" name="category_name" class="form-control form-control-sm" placeholder="Nama Kategori..." required>
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 py-1" style="font-size: 0.78rem;">
                                + Tambah Kategori
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabs Filter Kategori -->
                <ul class="nav nav-pills nav-fill mb-2 bg-light p-1 rounded-pill" id="catTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active rounded-pill py-1 small fw-bold text-danger" id="tab-out-btn" data-bs-toggle="pill" data-bs-target="#tab-out" type="button">
                            Pengeluaran (<?= count($outCategories) ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill py-1 small fw-bold text-success" id="tab-in-btn" data-bs-toggle="pill" data-bs-target="#tab-in" type="button">
                            Pemasukan (<?= count($inCategories) ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-2">
                    <!-- Tab Kategori Pengeluaran -->
                    <div class="tab-pane fade show active" id="tab-out">
                        <div class="d-flex flex-column gap-1" style="max-height: 260px; overflow-y: auto;">
                            <?php foreach ($outCategories as $oc): ?>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded-2 bg-white border">
                                    <span class="small fw-semibold text-dark"><?= e($oc['name']) ?></span>
                                    <form method="POST" action="">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $oc['id'] ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger p-0 px-1 border-0 btn-confirm-delete" data-confirm="Hapus kategori '<?= e(addslashes($oc['name'])) ?>'?" title="Hapus Kategori">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Tab Kategori Pemasukan -->
                    <div class="tab-pane fade" id="tab-in">
                        <div class="d-flex flex-column gap-1" style="max-height: 260px; overflow-y: auto;">
                            <?php foreach ($inCategories as $ic): ?>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded-2 bg-white border">
                                    <span class="small fw-semibold text-dark"><?= e($ic['name']) ?></span>
                                    <form method="POST" action="">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?= $ic['id'] ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger p-0 px-1 border-0 btn-confirm-delete" data-confirm="Hapus kategori '<?= e(addslashes($ic['name'])) ?>'?" title="Hapus Kategori">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-2">
                <button type="button" class="btn btn-sm btn-secondary w-100 py-2 rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETAIL RINCIAN BUKU KAS -->
<div class="modal fade" id="modalDetailKas" tabindex="-1" aria-labelledby="modalDetailKasLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white border-0 py-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-25 text-info p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-wallet2 fs-5"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0" id="modalDetailKasLabel">Rincian Saldo Kas & Rekening</h6>
                        <small class="text-white-50" style="font-size: 0.72rem;">Daftar Akun Kas CV. MBB Terdaftar</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="list-group list-group-flush gap-2">
                    <?php foreach ($accounts as $acc): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center p-3 rounded-3 border bg-light">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle <?= empty($acc['bank_name']) ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary' ?> d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; flex-shrink: 0;">
                                    <i class="bi <?= empty($acc['bank_name']) ? 'bi-cash-coin' : 'bi-bank2' ?> fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.85rem; line-height: 1.2;">
                                        <?= e($acc['account_name']) ?>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">
                                        <?= e($acc['account_number'] ?: 'Kas Tunai Lapangan') ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                                    <?= format_rupiah($acc['current_balance']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3 p-3 rounded-3 bg-primary-subtle border border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small fw-semibold text-primary">Total Likuiditas Kas</div>
                        <div class="text-muted" style="font-size: 0.7rem;"><?= count($accounts) ?> Akun Terdaftar</div>
                    </div>
                    <div class="fw-bold text-primary fs-5">
                        <?= format_rupiah($totalLiquid) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 pt-0">
                <button type="button" class="btn btn-secondary w-100 py-2 rounded-3" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
const incomeCategories = <?= json_encode(array_column($inCategories, 'name')) ?>;
const expenseCategories = <?= json_encode(array_column($outCategories, 'name')) ?>;

function updateCategoryOptions() {
    const dir = document.getElementById('trx_direction_select')?.value || 'OUT';
    const select = document.getElementById('trx_category_select');
    if (!select) return;

    const list = (dir === 'IN') ? incomeCategories : expenseCategories;
    select.innerHTML = '';

    list.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item;
        opt.textContent = item;
        select.appendChild(opt);
    });
}

function openManageFromAddModal() {
    const addEl = document.getElementById('modalAddCash');
    const modal = bootstrap.Modal.getInstance(addEl);
    if (modal) modal.hide();

    setTimeout(() => {
        new bootstrap.Modal(document.getElementById('modalManageCategories')).show();
    }, 200);
}

document.addEventListener('DOMContentLoaded', function() {
    updateCategoryOptions();

    const addCashEl = document.getElementById('modalAddCash');
    if (addCashEl) {
        addCashEl.addEventListener('shown.bs.modal', function() {
            updateCategoryOptions();
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
