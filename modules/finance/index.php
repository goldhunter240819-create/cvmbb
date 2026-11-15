<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Kas & Keuangan Terpadu CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('finance.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// POST HANDLER: TRANSAKSI KEUANGAN & KAS
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_transaction') {
        Permission::require('finance.manage');
        csrf_validate();

        $trxCode      = 'TRX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $trxDate      = sanitize($_POST['trx_date'] ?? date('Y-m-d'));
        $accountId    = (int)($_POST['cash_account_id'] ?? 0);
        $direction    = sanitize($_POST['trx_direction'] ?? 'IN');
        $businessLine = sanitize($_POST['business_line'] ?? 'MANAGEMENT');
        $category     = sanitize($_POST['category'] ?? 'Operasional');
        $amount       = (float)($_POST['amount'] ?? 0);
        $description  = sanitize($_POST['description'] ?? '');

        if ($accountId <= 0 || $amount <= 0 || $description === '') {
            flash_set('danger', 'Harap isi akun kas, nominal transaksi, dan deskripsi dengan benar.');
            redirect('modules/finance/index.php');
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO cash_transactions (
                    trx_code, trx_date, cash_account_id, trx_direction,
                    business_line, category, amount, description, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $trxCode, $trxDate, $accountId, $direction,
                $businessLine, $category, $amount, $description, Auth::id()
            ]);
            $trxId = $db->lastInsertId();

            // Update Saldo Kas
            if ($direction === 'IN') {
                $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$amount, $accountId]);
            } else {
                $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$amount, $accountId]);
            }

            $db->commit();
            log_audit('CREATE_CASH_TRANSACTION', 'finance', $trxId, "Transaksi kas {$trxCode} ({$direction}) Rp " . number_format($amount));
            flash_set('success', "Transaksi kas '{$trxCode}' berhasil dicatat.");
        } catch (Exception $e) {
            $db->rollBack();
            flash_set('danger', 'Gagal mencatat transaksi: ' . $e->getMessage());
        }

        redirect('modules/finance/index.php');
    }

    if ($action === 'transfer_cash') {
        Permission::require('finance.manage');
        csrf_validate();

        $fromAccId = (int)($_POST['from_account_id'] ?? 0);
        $toAccId   = (int)($_POST['to_account_id'] ?? 0);
        $amount    = (float)($_POST['transfer_amount'] ?? 0);
        $notes     = sanitize($_POST['transfer_notes'] ?? 'Mutasi internal kas');

        if ($fromAccId <= 0 || $toAccId <= 0 || $fromAccId === $toAccId || $amount <= 0) {
            flash_set('danger', 'Akun sumber dan tujuan tidak boleh sama, dan nominal harus lebih dari 0.');
            redirect('modules/finance/index.php');
        }

        try {
            $db->beginTransaction();

            $trxCodeOut = 'TRF-OUT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $trxCodeIn  = 'TRF-IN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            // Kas Keluar dari akun sumber
            $stmtOut = $db->prepare("
                INSERT INTO cash_transactions (
                    trx_code, trx_date, cash_account_id, trx_direction,
                    business_line, category, amount, description, created_by
                ) VALUES (?, CURDATE(), ?, 'OUT', 'MANAGEMENT', 'Mutasi Antar-Kas', ?, ?, ?)
            ");
            $stmtOut->execute([$trxCodeOut, $fromAccId, $amount, "Transfer keluar: {$notes}", Auth::id()]);
            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $fromAccId]);

            // Kas Masuk ke akun tujuan
            $stmtIn = $db->prepare("
                INSERT INTO cash_transactions (
                    trx_code, trx_date, cash_account_id, trx_direction,
                    business_line, category, amount, description, created_by
                ) VALUES (?, CURDATE(), ?, 'IN', 'MANAGEMENT', 'Mutasi Antar-Kas', ?, ?, ?)
            ");
            $stmtIn->execute([$trxCodeIn, $toAccId, $amount, "Transfer masuk: {$notes}", Auth::id()]);
            $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $toAccId]);

            $db->commit();
            log_audit('TRANSFER_CASH', 'finance', 0, "Transfer kas Rp " . number_format($amount) . " dari ID {$fromAccId} ke {$toAccId}");
            flash_set('success', "Mutasi antar-kas senilai " . format_rupiah($amount) . " berhasil.");
        } catch (Exception $e) {
            $db->rollBack();
            flash_set('danger', 'Gagal memproses mutasi kas: ' . $e->getMessage());
        }

        redirect('modules/finance/index.php');
    }

    if ($action === 'delete_transaction') {
        Permission::require('finance.manage');
        csrf_validate();

        $trxId = (int)($_POST['trx_id'] ?? 0);
        $curr = $db->prepare("SELECT cash_account_id, trx_direction, amount FROM cash_transactions WHERE id = ?");
        $curr->execute([$trxId]);
        $row = $curr->fetch();

        if ($row) {
            // Revert balance
            if ($row['trx_direction'] === 'IN') {
                $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$row['amount'], $row['cash_account_id']]);
            } else {
                $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$row['amount'], $row['cash_account_id']]);
            }
            $db->prepare("DELETE FROM cash_transactions WHERE id = ?")->execute([$trxId]);

            log_audit('DELETE_CASH_TRANSACTION', 'finance', $trxId, "Menghapus transaksi kas ID {$trxId}");
            flash_set('success', 'Transaksi kas berhasil dihapus dan saldo kas dikembalikan.');
        }

        redirect('modules/finance/index.php');
    }
}

// -------------------------------------------------------------
// FILTER & QUERY JURNAL KAS
// -------------------------------------------------------------
$search     = trim($_GET['q'] ?? '');
$accFilter  = (int)($_GET['account'] ?? 0);
$dirFilter  = trim($_GET['dir'] ?? '');
$lineFilter = trim($_GET['line'] ?? '');

$query = "
    SELECT t.*, a.account_name, a.bank_name, u.full_name AS creator_name
    FROM cash_transactions t
    LEFT JOIN cash_accounts a ON t.cash_account_id = a.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (t.trx_code LIKE ? OR t.description LIKE ? OR t.category LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($accFilter > 0) {
    $query .= " AND t.cash_account_id = ?";
    $params[] = $accFilter;
}

if ($dirFilter !== '') {
    $query .= " AND t.trx_direction = ?";
    $params[] = $dirFilter;
}

if ($lineFilter !== '') {
    $query .= " AND t.business_line = ?";
    $params[] = $lineFilter;
}

$query .= " ORDER BY t.trx_date DESC, t.id DESC LIMIT 100";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// KPI Metrics Keuangan
$cashAccounts = $db->query("SELECT * FROM cash_accounts WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$totalLiquidBalance = array_sum(array_column($cashAccounts, 'current_balance'));

$monthKpi = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN trx_direction = 'IN' AND category != 'Mutasi Antar-Kas' THEN amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN trx_direction = 'OUT' AND category != 'Mutasi Antar-Kas' THEN amount ELSE 0 END), 0) AS total_out
    FROM cash_transactions
    WHERE MONTH(trx_date) = MONTH(CURDATE()) AND YEAR(trx_date) = YEAR(CURDATE())
")->fetch();

$pageTitle = 'Kas & Keuangan Terpadu';
$currentModule = 'finance';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Keuangan' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. KARTU SALDO AKUN KAS MBB -->
<div class="row g-3 mb-4">
    <?php foreach ($cashAccounts as $acc): ?>
        <div class="col-md-3 col-sm-6">
            <div class="card-mbb p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase"><?= e($acc['account_name']) ?></div>
                        <h4 class="fw-bold text-primary mb-0 mt-1"><?= format_rupiah($acc['current_balance']) ?></h4>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary p-3">
                        <i class="bi <?= $acc['account_type'] === 'BANK_ACCOUNT' ? 'bi-bank' : 'bi-cash' ?> fs-4"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    <?= e($acc['bank_name'] ?: 'Kas Fisik') ?> &bull; No: <strong><?= e($acc['account_number'] ?: '-') ?></strong>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- TOTAL KAS LIKUID PERUSAHAAN -->
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Kas Likuid Perusahaan</div>
                    <h4 class="fw-bold text-success mb-0 mt-1"><?= format_rupiah($totalLiquidBalance) ?></h4>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-wallet-fill fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Konsolidasi seluruh rekening kas</div>
        </div>
    </div>
</div>

<!-- 2. TOOLBAR & FILTER -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-8">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari transaksi, ket...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="account" class="form-select form-select-sm">
                            <option value="0">-- Semua Rekening Kas --</option>
                            <?php foreach ($cashAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>" <?= $accFilter === (int)$acc['id'] ? 'selected' : '' ?>><?= e($acc['account_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="dir" class="form-select form-select-sm">
                            <option value="">-- Arah --</option>
                            <option value="IN" <?= $dirFilter === 'IN' ? 'selected' : '' ?>>Kas Masuk (+)</option>
                            <option value="OUT" <?= $dirFilter === 'OUT' ? 'selected' : '' ?>>Kas Keluar (-)</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($search !== '' || $accFilter > 0 || $dirFilter !== '' || $lineFilter !== ''): ?>
                            <a href="<?= base_url('modules/finance/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-md-auto text-end d-flex gap-2">
                <?php if (Permission::can('finance.manage')): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTransferCash">
                        <i class="bi bi-arrow-left-right me-1"></i> Mutasi Antar-Kas
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTrx">
                        <i class="bi bi-plus-circle me-1"></i> Catat Kas Masuk / Keluar
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. TABEL JURNAL KAS BUKU BESAR -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-journal-text"></i> Buku Jurnal Kas & Mutasi Keuangan MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($transactions) ?> Baris Jurnal</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Bukti / Reff</th>
                        <th>Tanggal</th>
                        <th>Akun Kas</th>
                        <th>Lini Usaha</th>
                        <th>Kategori</th>
                        <th>Deskripsi / Catatan</th>
                        <th class="text-end">Kas Masuk (Debit)</th>
                        <th class="text-end">Kas Keluar (Kredit)</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada jurnal transaksi kas pada filter ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="ps-4 fw-bold font-monospace text-dark">
                                    <?= e($t['trx_code']) ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($t['trx_date'])) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($t['account_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= e(str_replace('_', ' ', $t['business_line'])) ?>
                                    </span>
                                </td>
                                <td><?= e($t['category']) ?></td>
                                <td>
                                    <div class="text-dark"><?= e($t['description']) ?></div>
                                    <div class="small text-muted">Dicatat: <?= e($t['creator_name'] ?? 'Sistem') ?></div>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?= $t['trx_direction'] === 'IN' ? format_rupiah($t['amount']) : '-' ?>
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    <?= $t['trx_direction'] === 'OUT' ? format_rupiah($t['amount']) : '-' ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (Permission::can('finance.manage')): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_transaction">
                                            <input type="hidden" name="trx_id" value="<?= $t['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus transaksi '<?= e($t['trx_code']) ?>'? Saldo kas terkait akan dikembalikan." title="Hapus Jurnal">
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

<!-- 4. MODAL CATAT KAS MASUK / KELUAR -->
<div class="modal fade" id="modalCreateTrx" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_transaction">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Catat Transaksi Kas Masuk / Kas Keluar</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Jenis Aliran Kas <span class="text-danger">*</span></label>
                        <select name="trx_direction" class="form-select fw-bold" required>
                            <option value="IN" class="text-success">&#8600; Kas Masuk (Pemasukan / Pendapatan)</option>
                            <option value="OUT" class="text-danger">&#8599; Kas Keluar (Pengeluaran / Biaya Operasional)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Pilih Akun Rekening Kas <span class="text-danger">*</span></label>
                        <select name="cash_account_id" class="form-select" required>
                            <?php foreach ($cashAccounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tanggal Transaksi</label>
                        <input type="date" name="trx_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Lini Usaha / Divisi</label>
                        <select name="business_line" class="form-select">
                            <option value="MANAGEMENT">Manajemen & Kantor Pusat</option>
                            <option value="COFFEE_TRADING">Coffee Trading (Kopi Biji)</option>
                            <option value="COFFEE_PRODUCTION">Coffee Production (Bubuk & Roasting Internal)</option>
                            <option value="ROASTING_SERVICE">Roasting Service (Maklon)</option>
                            <option value="BRILINK">Agen BRILink</option>
                            <option value="DECORATION">Divisi Dekoration</option>
                            <option value="SOUND_SYSTEM">Divisi Sound Sistem</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kategori Transaksi</label>
                        <input type="text" name="category" class="form-control" placeholder="Cth: Listrik/BBM/Konsumsi/Gaji/Pendapatan Jasa" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nominal Transaksi (Rp) <span class="text-danger">*</span></label>
                        <input type="number" step="1000" name="amount" class="form-control form-control-lg fw-bold text-primary" placeholder="0" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Deskripsi / Keterangan Transaksi <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Cth: Pembayaran tagihan listrik kantor pusat & gudang roasting bulan ini" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Transaksi Kas</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL TRANSFER ANTAR-KAS -->
<div class="modal fade" id="modalTransferCash" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="transfer_cash">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Mutasi Pindah Dana Antar-Kas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i> <strong>Catatan:</strong> Mutasi antar-kas tidak dihitung sebagai pendapatan maupun pengeluaran operasional perusahaan.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Dari Akun Kas (Sumber)</label>
                    <select name="from_account_id" class="form-select" required>
                        <?php foreach ($cashAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Ke Akun Kas (Tujuan)</label>
                    <select name="to_account_id" class="form-select" required>
                        <?php foreach ($cashAccounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nominal yang Dipindahkan (Rp)</label>
                    <input type="number" step="1000" name="transfer_amount" class="form-control fw-bold" placeholder="Rp" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Keterangan / Alasan Transfer</label>
                    <input type="text" name="transfer_notes" class="form-control" placeholder="Cth: Setor hasil laci ke rekening bank BRI">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i> Proses Mutasi Dana</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
