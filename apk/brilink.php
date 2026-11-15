<?php
/**
 * MBB MOBILE APK - Agen BRILink
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Agen BRILink MBB';
$currentApkNav   = 'brilink';

$mobileHeaderAction = '
    <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="modal" data-bs-target="#modalAddBri" title="Transaksi Baru">
        <i class="bi bi-plus-lg text-primary fw-bold"></i>
    </button>
';

require_once __DIR__ . '/layout/header.php';

// Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_bri') {
    csrf_validate();

    $trxType     = sanitize($_POST['trx_type'] ?? 'TARIK_TUNAI');
    $customer    = sanitize($_POST['customer_name'] ?? 'Pelanggan');
    $bank        = sanitize($_POST['bank_destination'] ?? 'BRI');
    $nominal     = (float)($_POST['nominal_amount'] ?? 0);
    $feeCust     = (float)($_POST['admin_fee_customer'] ?? 0);
    $feeBank     = (float)($_POST['bank_share'] ?? 0);
    $notes       = sanitize($_POST['notes'] ?? '');

    $income = max(0, $feeCust - $feeBank);
    $refNo  = 'BRI/' . date('Ymd') . '/' . rand(1000, 9999);

    if ($nominal > 0) {
        $accId = $db->query("SELECT id FROM brilink_accounts WHERE is_active = 1 LIMIT 1")->fetchColumn() ?: 1;

        $stmt = $db->prepare("
            INSERT INTO brilink_transactions (
                reference_no, brilink_account_id, trx_type, customer_name, bank_destination,
                nominal_amount, admin_fee_customer, bank_share, company_income, status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'BERHASIL', ?, ?)
        ");
        $stmt->execute([
            $refNo, $accId, $trxType, $customer, $bank,
            $nominal, $feeCust, $feeBank, $income, $notes, Auth::id()
        ]);

        if ($trxType === 'TARIK_TUNAI') {
            $db->prepare("UPDATE brilink_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$nominal, $accId]);
        } elseif ($trxType === 'TRANSFER' || $trxType === 'SETOR_TUNAI') {
            $db->prepare("UPDATE brilink_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$nominal, $accId]);
        }

        log_audit('CREATE_BRILINK_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat transaksi {$trxType} Rp {$nominal} oleh {$customer}");
        flash_set('success', "Transaksi BRILink {$refNo} sukses!");
        redirect('apk/brilink.php');
    }
}

// Queries
$accounts = $db->query("SELECT * FROM brilink_accounts WHERE is_active = 1 LIMIT 2")->fetchAll();
$totalBriBalance = array_sum(array_column($accounts, 'current_balance'));

$transactions = $db->query("SELECT * FROM brilink_transactions ORDER BY id DESC LIMIT 20")->fetchAll();

$kpiToday = $db->query("
    SELECT COUNT(*) AS total_tx, COALESCE(SUM(nominal_amount),0) AS total_vol, COALESCE(SUM(company_income),0) AS total_fee
    FROM brilink_transactions 
    WHERE DATE(transaction_date) = CURDATE() AND status = 'BERHASIL'
")->fetch();
?>

<!-- KARTU SALDO EDC BRILINK MOBILE -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(2, 132, 199, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="small text-white-50 fw-semibold">Saldo Mesin EDC & Kas Loket</span>
        <span class="badge bg-white text-dark small px-2 py-1 font-monospace">EDC-001 AKTIF</span>
    </div>
    <h3 class="fw-bold mb-2 text-white"><?= format_rupiah($totalBriBalance) ?></h3>
    <div class="d-flex justify-content-between small pt-2 border-top border-white border-opacity-25 mt-2 opacity-95">
        <span>Hari Ini: <strong class="text-white"><?= number_format($kpiToday['total_tx']) ?> Transaksi</strong></span>
        <span>Fee MBB: <strong class="text-warning fw-bold"><?= format_rupiah($kpiToday['total_fee']) ?></strong></span>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    Mutasi Transaksi EDC Terbaru
</div>

<!-- DAFTAR KARTU MUTASI BRILINK -->
<?php if (empty($transactions)): ?>
    <div class="mobile-card text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        Belum ada transaksi BRILink.
    </div>
<?php else: ?>
    <?php foreach ($transactions as $t): ?>
        <div class="mobile-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 1rem; flex-shrink: 0;">
                        <i class="bi bi-credit-card-2-front-fill"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                            <?= e($t['customer_name'] ?? 'Pelanggan Umum') ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            <?= e($t['reference_no']) ?> &bull; <?= date('d M Y H:i', strtotime($t['transaction_date'])) ?>
                        </div>
                    </div>
                </div>
                <?php
                $badge = match($t['trx_type']) {
                    'TARIK_TUNAI' => 'bg-success-subtle text-success border border-success border-opacity-25',
                    'TRANSFER' => 'bg-info-subtle text-info border border-info border-opacity-25',
                    'SETOR_TUNAI' => 'bg-primary-subtle text-primary border border-primary border-opacity-25',
                    default => 'bg-warning-subtle text-warning border border-warning border-opacity-25'
                };
                ?>
                <span class="badge <?= $badge ?> px-2 py-1" style="font-size: 0.68rem;"><?= e($t['trx_type']) ?></span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.8rem;">
                <div>
                    <span class="text-muted">Nominal:</span>
                    <strong class="text-dark"><?= format_rupiah($t['nominal_amount']) ?></strong>
                </div>
                <div class="text-end">
                    <div class="text-success small fw-bold">+<?= format_rupiah($t['company_income']) ?> Fee</div>
                    <span class="badge bg-success text-white" style="font-size: 0.6rem;">BERHASIL</span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- FAB -->
<button type="button" class="mobile-fab" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);" data-bs-toggle="modal" data-bs-target="#modalAddBri" title="Catat Transaksi BRILink">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- MODAL CATAT TRANSAKSI BRILINK MOBILE -->
<div class="modal fade" id="modalAddBri" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_bri">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-credit-card-2-front me-2"></i>Transaksi BRILink Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Jenis Transaksi</label>
                    <select name="trx_type" class="form-select form-select-sm" required>
                        <option value="TARIK_TUNAI">TARIK TUNAI (Pelanggan Ambil Uang)</option>
                        <option value="TRANSFER">TRANSFER SESAMA / ANTAR BANK</option>
                        <option value="SETOR_TUNAI">SETOR TUNAI (Pelanggan Titip Uang)</option>
                        <option value="PEMBAYARAN_TAGIHAN">BAYAR TAGIHAN / TOKEN LISTRIK</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Nasabah / Pelanggan</label>
                    <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Cth: Siti Aminah" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Bank Tujuan</label>
                        <input type="text" name="bank_destination" class="form-control form-control-sm" value="BRI" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Nominal Transaksi (Rp)</label>
                        <input type="number" step="500" name="nominal_amount" class="form-control form-control-sm" placeholder="500000" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Biaya Admin Pelanggan (Rp)</label>
                        <input type="number" step="500" name="admin_fee_customer" class="form-control form-control-sm" value="5000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Potongan BRI Pusat (Rp)</label>
                        <input type="number" step="500" name="bank_share" class="form-control form-control-sm" value="2000" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">No. Rekening / Keterangan</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="No. Rekening / No. Pelanggan">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">Simpan Transaksi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
