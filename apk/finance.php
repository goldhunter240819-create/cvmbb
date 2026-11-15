<?php
/**
 * MBB MOBILE APK - Keuangan & Multi-Kas
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Keuangan & Kas';
$currentApkNav   = 'finance';

$mobileHeaderAction = '
    <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="modal" data-bs-target="#modalAddCash" title="Catat Kas">
        <i class="bi bi-plus-lg text-primary fw-bold"></i>
    </button>
';

require_once __DIR__ . '/layout/header.php';

// Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_cash') {
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

// Queries
$accounts = $db->query("SELECT * FROM cash_accounts WHERE is_active = 1 ORDER BY id ASC")->fetchAll();
$totalLiquid = array_sum(array_column($accounts, 'current_balance'));

$transactions = $db->query("
    SELECT t.*, a.account_name 
    FROM cash_transactions t 
    LEFT JOIN cash_accounts a ON t.cash_account_id = a.id 
    ORDER BY t.trx_date DESC, t.id DESC 
    LIMIT 20
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
        <button type="button" class="btn btn-sm btn-light text-primary fw-bold px-3 py-1 rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#modalDetailKas">
            <i class="bi bi-eye-fill me-1"></i> Rincian Kas
        </button>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    Mutasi Buku Kas Besar Terakhir
</div>

<!-- DAFTAR MUTASI KAS -->
<?php if (empty($transactions)): ?>
    <div class="mobile-card text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        Belum ada mutasi kas tercatat.
    </div>
<?php else: ?>
    <?php foreach ($transactions as $t): ?>
        <div class="mobile-card">
            <div class="d-flex justify-content-between align-items-center mb-1">
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
                    <select name="trx_direction" class="form-select form-select-sm fw-bold" required>
                        <option value="IN" class="text-success">&#8600; Kas Masuk (Pemasukan)</option>
                        <option value="OUT" class="text-danger">&#8599; Kas Keluar (Pengeluaran / Biaya)</option>
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
                        <label class="form-label small fw-bold mb-1">Kategori</label>
                        <select name="category" class="form-select form-select-sm" required>
                            <option value="Operasional">Operasional Kantor</option>
                            <option value="Penjualan Kopi">Penjualan Kopi</option>
                            <option value="Jasa Roasting">Jasa Roasting</option>
                            <option value="BRILink Fee">Fee BRILink</option>
                            <option value="Dekorasi & Sound">Dekorasi & Sound</option>
                            <option value="BBM & Transport">BBM & Transport</option>
                            <option value="Gaji & Upah">Gaji & Upah</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Tanggal Transaksi</label>
                        <input type="date" name="trx_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nominal (Rp)</label>
                    <input type="number" step="1000" name="amount" class="form-control form-control-sm" placeholder="Contoh: 1500000" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Deskripsi / Keperluan</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="Cth: Bayar BBM mobil pick up kirim kopi" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-dark px-3 fw-bold">Simpan Mutasi Kas</button>
            </div>
        </form>
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
                                    <div class="text-muted small" style="font-size: 0.72rem;">
                                        <?= e($acc['bank_name'] ?: 'Kas Fisik Tunai') ?>
                                        <?= !empty($acc['account_number']) ? ' &bull; ' . e($acc['account_number']) : '' ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-white text-dark border font-monospace fw-bold" style="font-size: 0.82rem;">
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

<?php require_once __DIR__ . '/layout/footer.php'; ?>
