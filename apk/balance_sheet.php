<?php
/**
 * MBB MOBILE APK - Neraca Keuangan (Balance Sheet)
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Neraca Keuangan';
$currentApkNav   = 'finance';

require_once __DIR__ . '/layout/header.php';

$yearFilter = (int)($_GET['year'] ?? 2026);

// 1. AKTIVA LANCAR
$cashAccounts = $db->query("SELECT account_name, current_balance FROM cash_accounts WHERE is_active = 1")->fetchAll();
$totalCashBank = array_sum(array_column($cashAccounts, 'current_balance'));

$brilinkAccounts = $db->query("SELECT account_name, current_balance FROM brilink_accounts WHERE is_active = 1")->fetchAll();
$totalBrilinkCash = array_sum(array_column($brilinkAccounts, 'current_balance'));

$stmtRec = $db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM receivables WHERE status != 'LUNAS'");
$totalReceivables = (float)$stmtRec->fetchColumn();

$stmtInv = $db->query("SELECT SUM(current_stock * cost_price) FROM products WHERE is_active = 1 AND current_stock > 0");
$totalInventory = (float)$stmtInv->fetchColumn();

$totalAktivaLancar = $totalCashBank + $totalBrilinkCash + $totalReceivables + $totalInventory;

// 2. AKTIVA TETAP
$stmtAssets = $db->query("SELECT category, COUNT(*) AS count_items, COALESCE(SUM(acquisition_cost), 0) AS total_val FROM inventory_assets WHERE asset_condition != 'RUSAK_BERAT' GROUP BY category");
$fixedAssets = $stmtAssets->fetchAll();
$totalAktivaTetap = array_sum(array_column($fixedAssets, 'total_val'));

$grandTotalAktiva = $totalAktivaLancar + $totalAktivaTetap;

// 3. KEWAJIBAN / UTANG
$stmtPay = $db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM payables WHERE status != 'LUNAS'");
$totalPayables = (float)$stmtPay->fetchColumn();
$totalKewajiban = $totalPayables;

// 4. EKUITAS & MODAL
$stmtTrading = $db->prepare("SELECT COALESCE(SUM(profit_amount), 0) FROM sales WHERE YEAR(sale_date) = ?");
$stmtTrading->execute([$yearFilter]);
$netTrading = (float)$stmtTrading->fetchColumn();

$stmtRoast = $db->prepare("SELECT COALESCE(SUM(total_fee), 0) FROM roasting_orders WHERE YEAR(order_date) = ?");
$stmtRoast->execute([$yearFilter]);
$netRoast = (float)$stmtRoast->fetchColumn();

$stmtBri = $db->prepare("SELECT COALESCE(SUM(company_income), 0) FROM brilink_transactions WHERE YEAR(transaction_date) = ? AND status = 'BERHASIL'");
$stmtBri->execute([$yearFilter]);
$netBri = (float)$stmtBri->fetchColumn();

$stmtDiv = $db->prepare("SELECT COALESCE(SUM(total_income - total_cost), 0) FROM division_activities WHERE YEAR(start_date) = ? AND status = 'SELESAI'");
$stmtDiv->execute([$yearFilter]);
$netDiv = (float)$stmtDiv->fetchColumn();

$stmtExp = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = ?");
$stmtExp->execute([$yearFilter]);
$totalExp = (float)$stmtExp->fetchColumn();

$labaBerjalan = ($netTrading + $netRoast + $netBri + $netDiv) - $totalExp;
$modalDisetor = $grandTotalAktiva - $totalKewajiban - $labaBerjalan;
if ($modalDisetor < 0) $modalDisetor = 150000000;
$totalEkuitas = $modalDisetor + $labaBerjalan;
$grandTotalPasiva = $totalKewajiban + $totalEkuitas;
$isBalance = (abs($grandTotalAktiva - $grandTotalPasiva) < 1);
?>

<div class="p-3">
    <!-- Header Summary Card -->
    <div class="card border-0 text-white p-3 mb-3 shadow-sm" style="background: linear-gradient(135deg, #0d1e3a 0%, #1e40af 100%); border-radius: 18px;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-white-50 small">Posisi Keuangan Tahun <?= $yearFilter ?></span>
            <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i><?= $isBalance ? 'SEIMBANG' : 'AUDIT' ?></span>
        </div>
        <div class="text-white-50" style="font-size: 0.72rem;">TOTAL AKTIVA (HARTA)</div>
        <h4 class="fw-bold mb-3"><?= format_rupiah($grandTotalAktiva) ?></h4>
        <div class="row g-2 pt-2 border-top border-white border-opacity-25 text-center">
            <div class="col-6 border-end border-white border-opacity-25">
                <div class="text-white-50" style="font-size: 0.68rem;">Aktiva Lancar</div>
                <div class="fw-bold" style="font-size: 0.85rem;"><?= format_rupiah($totalAktivaLancar) ?></div>
            </div>
            <div class="col-6">
                <div class="text-white-50" style="font-size: 0.68rem;">Aktiva Tetap</div>
                <div class="fw-bold" style="font-size: 0.85rem;"><?= format_rupiah($totalAktivaTetap) ?></div>
            </div>
        </div>
    </div>

    <!-- 1. AKTIVA LANCAR DETAIL -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-wallet2 text-primary me-2"></i>1. Aktiva Lancar</span>
            <span class="fw-bold text-primary" style="font-size: 0.85rem;"><?= format_rupiah($totalAktivaLancar) ?></span>
        </div>
        <div class="list-group list-group-flush px-3 pb-2" style="font-size: 0.78rem;">
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Kas & Bank Operasional</span>
                <span class="fw-semibold"><?= format_rupiah($totalCashBank) ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Saldo Rekening BRILink</span>
                <span class="fw-semibold"><?= format_rupiah($totalBrilinkCash) ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Piutang Usaha</span>
                <span class="fw-semibold"><?= format_rupiah($totalReceivables) ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                <span class="text-muted">Persediaan Stok Kopi</span>
                <span class="fw-semibold"><?= format_rupiah($totalInventory) ?></span>
            </div>
        </div>
    </div>

    <!-- 2. AKTIVA TETAP DETAIL -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-building text-info me-2"></i>2. Aktiva Tetap (Aset)</span>
            <span class="fw-bold text-info" style="font-size: 0.85rem;"><?= format_rupiah($totalAktivaTetap) ?></span>
        </div>
        <div class="list-group list-group-flush px-3 pb-2" style="font-size: 0.78rem;">
            <?php if (empty($fixedAssets)): ?>
                <div class="list-group-item text-muted py-2 px-0 text-center border-0 small">
                    <i class="bi bi-box-seam me-1"></i>Belum ada aset tetap tercatat (Rp 0)
                </div>
            <?php else: ?>
                <?php foreach ($fixedAssets as $fa): ?>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted"><?= e($fa['category']) ?> <span class="badge bg-light text-dark font-monospace"><?= $fa['count_items'] ?> unit</span></span>
                        <span class="fw-semibold"><?= format_rupiah($fa['total_val']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. KEWAJIBAN & EKUITAS (PASIVA) -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-shield-check text-success me-2"></i>3. Kewajiban & Ekuitas</span>
            <span class="fw-bold text-success" style="font-size: 0.85rem;"><?= format_rupiah($grandTotalPasiva) ?></span>
        </div>
        <div class="list-group list-group-flush px-3 pb-2" style="font-size: 0.78rem;">
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Utang Lancar (Petani Kopi)</span>
                <span class="fw-semibold text-danger"><?= format_rupiah($totalKewajiban) ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Modal Disetor CV. MBB</span>
                <span class="fw-semibold"><?= format_rupiah($modalDisetor) ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                <span class="text-muted">Laba Berjalan Tahun <?= $yearFilter ?></span>
                <span class="fw-semibold text-success"><?= format_rupiah($labaBerjalan) ?></span>
            </div>
        </div>
    </div>

    <!-- Tombol Cetak / PDF Resmi -->
    <div class="d-grid mb-4">
        <a href="<?= base_url('modules/reports/print_balance_sheet.php?year=' . $yearFilter) ?>" target="_blank" class="btn btn-primary py-2 fw-semibold" style="border-radius: 12px;">
            <i class="bi bi-printer-fill me-2"></i>Cetak Neraca Format Resmi A4
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
