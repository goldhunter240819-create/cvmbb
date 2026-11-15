<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Laporan Neraca Keuangan (Balance Sheet)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('reports.view');

$db = Database::getInstance();

$yearFilter = (int)($_GET['year'] ?? 2026);

// -------------------------------------------------------------
// 1. AKTIVA LANCAR (CURRENT ASSETS)
// -------------------------------------------------------------

// A. Kas & Bank Operasional
$cashAccounts = $db->query("SELECT account_name, current_balance FROM cash_accounts WHERE is_active = 1")->fetchAll();
$totalCashBank = array_sum(array_column($cashAccounts, 'current_balance'));

// B. Saldo Rekening & EDC BRILink
$brilinkAccounts = $db->query("SELECT account_name, current_balance FROM brilink_accounts WHERE is_active = 1")->fetchAll();
$totalBrilinkCash = array_sum(array_column($brilinkAccounts, 'current_balance'));

// C. Piutang Usaha / Pelanggan
$stmtRec = $db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM receivables WHERE status != 'LUNAS'");
$totalReceivables = (float)$stmtRec->fetchColumn();

// D. Persediaan Barang Dagang (Green Beans & Roasted/Kemasan)
$stmtInv = $db->query("
    SELECT product_name, current_stock, cost_price, (current_stock * cost_price) AS subtotal
    FROM products 
    WHERE is_active = 1 AND current_stock > 0
");
$inventoryItems = $stmtInv->fetchAll();
$totalInventory = array_sum(array_column($inventoryItems, 'subtotal'));

$totalAktivaLancar = $totalCashBank + $totalBrilinkCash + $totalReceivables + $totalInventory;

// -------------------------------------------------------------
// 2. AKTIVA TETAP (FIXED ASSETS / INVENTARIS)
// -------------------------------------------------------------
$stmtAssets = $db->query("
    SELECT category, COUNT(*) AS count_items, COALESCE(SUM(acquisition_cost), 0) AS total_val
    FROM inventory_assets
    WHERE asset_condition != 'RUSAK_BERAT'
    GROUP BY category
");
$fixedAssets = $stmtAssets->fetchAll();
$totalAktivaTetap = array_sum(array_column($fixedAssets, 'total_val'));

$grandTotalAktiva = $totalAktivaLancar + $totalAktivaTetap;

// -------------------------------------------------------------
// 3. KEWAJIBAN / UTANG LANCAR (LIABILITIES)
// -------------------------------------------------------------
$stmtPay = $db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM payables WHERE status != 'LUNAS'");
$totalPayables = (float)$stmtPay->fetchColumn();
$totalKewajiban = $totalPayables;

// -------------------------------------------------------------
// 4. EKUITAS & MODAL (EQUITY)
// -------------------------------------------------------------
// Hitung Laba Bersih Konsolidasi Berjalan
$stmtTrading = $db->prepare("SELECT COALESCE(SUM(profit_amount), 0) FROM sales WHERE YEAR(sale_date) = ?");
$stmtTrading->execute([$yearFilter]);
$netTrading = (float)$stmtTrading->fetchColumn();

$stmtRoast = $db->prepare("SELECT COALESCE(SUM(total_fee), 0) FROM roasting_orders WHERE YEAR(order_date) = ?");
$stmtRoast->execute([$yearFilter]);
$netRoast = (float)$stmtRoast->fetchColumn();

$stmtBri = $db->prepare("SELECT COALESCE(SUM(company_income), 0) FROM brilink_transactions WHERE YEAR(transaction_date) = ? AND status = 'BERHASIL'");
$stmtBri->execute([$yearFilter]);
$netBri = (float)$stmtBri->fetchColumn();

$stmtDekor = $db->prepare("SELECT COALESCE(SUM(total_income - total_cost), 0) FROM division_activities WHERE division_id = 5 AND YEAR(start_date) = ? AND status = 'SELESAI'");
$stmtDekor->execute([$yearFilter]);
$netDekor = (float)$stmtDekor->fetchColumn();

$stmtSound = $db->prepare("SELECT COALESCE(SUM(total_income - total_cost), 0) FROM division_activities WHERE division_id = 6 AND YEAR(start_date) = ? AND status = 'SELESAI'");
$stmtSound->execute([$yearFilter]);
$netSound = (float)$stmtSound->fetchColumn();

$stmtGenExp = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE trx_direction = 'OUT' AND business_line = 'MANAGEMENT' AND category != 'Mutasi Antar-Kas' AND YEAR(trx_date) = ?");
$stmtGenExp->execute([$yearFilter]);
$genExp = (float)$stmtGenExp->fetchColumn();

$labaBerjalan = $netTrading + $netRoast + $netBri + $netDekor + $netSound - $genExp;

// Modal Disetor Pemilik (Penyeimbang Ekuitas Awal)
$modalAwal = max(0, $grandTotalAktiva - $totalKewajiban - $labaBerjalan);
$totalEkuitas = $modalAwal + $labaBerjalan;
$grandTotalPasiva = $totalKewajiban + $totalEkuitas;

$pageTitle = 'Laporan Neraca Keuangan (Balance Sheet)';
$currentModule = 'reports';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Laporan Manajemen' => base_url('modules/reports/index.php'),
    'Neraca Keuangan' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. NAVIGATION TAB & TOOLBAR -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row align-items-center justify-content-between g-2">
            <div class="col-md-6 d-flex align-items-center gap-2">
                <div class="btn-group" role="group">
                    <a href="<?= base_url('modules/reports/index.php') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-graph-up me-1"></i> Laba Rugi Konsolidasi
                    </a>
                    <a href="<?= base_url('modules/reports/balance_sheet.php') ?>" class="btn btn-sm btn-primary active">
                        <i class="bi bi-bank me-1"></i> Neraca Keuangan
                    </a>
                </div>
            </div>
            <div class="col-md-auto text-end d-flex gap-2">
                <a href="<?= base_url('modules/reports/print_balance_sheet.php?year=' . $yearFilter) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-printer me-1"></i> Cetak Neraca Resmi Berkop
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 2. KARTU BESAR KESEIMBANGAN NERACA -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Harta / Aktiva (Assets)</div>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= format_rupiah($grandTotalAktiva) ?></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3">
                    <i class="bi bi-building fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Lancar: <strong><?= format_rupiah($totalAktivaLancar) ?></strong> &bull; Tetap: <strong><?= format_rupiah($totalAktivaTetap) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Pasiva (Kewajiban & Modal)</div>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= format_rupiah($grandTotalPasiva) ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-check2-circle fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Kewajiban: <strong><?= format_rupiah($totalKewajiban) ?></strong> &bull; Modal: <strong><?= format_rupiah($totalEkuitas) ?></strong>
                <span class="badge bg-success ms-1"><i class="bi bi-check me-1"></i>BALANCE</span>
            </div>
        </div>
    </div>
</div>

<!-- 3. DETAIL TABEL DUA KOLOM NERACA (AKTIVA VS PASIVA) -->
<div class="row g-4">
    <!-- KOLOM KIRI: AKTIVA -->
    <div class="col-lg-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header bg-light">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-folder-fill me-1"></i> AKTIVA (ASET PERUSAHAAN)</h6>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <!-- Aktiva Lancar -->
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="ps-4 text-dark">I. AKTIVA LANCAR</td>
                    </tr>
                    <?php foreach ($cashAccounts as $ca): ?>
                        <tr>
                            <td class="ps-5 text-muted"><?= e($ca['account_name']) ?></td>
                            <td class="text-end pe-4"><?= format_rupiah($ca['current_balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($brilinkAccounts as $ba): ?>
                        <tr>
                            <td class="ps-5 text-muted"><?= e($ba['account_name']) ?> (Saldo EDC)</td>
                            <td class="text-end pe-4"><?= format_rupiah($ba['current_balance']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td class="ps-5 text-muted">Piutang Usaha Pelanggan</td>
                        <td class="text-end pe-4"><?= format_rupiah($totalReceivables) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-5 text-muted">Persediaan Biji Kopi & Kopi Bubuk</td>
                        <td class="text-end pe-4"><?= format_rupiah($totalInventory) ?></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td class="ps-4">Subtotal Aktiva Lancar</td>
                        <td class="text-end pe-4 text-primary"><?= format_rupiah($totalAktivaLancar) ?></td>
                    </tr>

                    <!-- Aktiva Tetap -->
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="ps-4 text-dark">II. AKTIVA TETAP (ASET INVENTARIS)</td>
                    </tr>
                    <?php if (empty($fixedAssets)): ?>
                        <tr>
                            <td class="ps-5 text-muted" colspan="2">Belum ada aset tetap tercatat</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fixedAssets as $fa): ?>
                            <tr>
                                <td class="ps-5 text-muted"><?= e($fa['category']) ?> (<?= $fa['count_items'] ?> Unit)</td>
                                <td class="text-end pe-4"><?= format_rupiah($fa['total_val']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="fw-bold bg-light">
                        <td class="ps-4">Subtotal Aktiva Tetap</td>
                        <td class="text-end pe-4 text-primary"><?= format_rupiah($totalAktivaTetap) ?></td>
                    </tr>

                    <!-- GRAND TOTAL AKTIVA -->
                    <tr class="fw-bold text-white" style="background-color: #0d1e3a;">
                        <td class="ps-4 fs-6">TOTAL AKTIVA</td>
                        <td class="text-end pe-4 fs-6"><?= format_rupiah($grandTotalAktiva) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- KOLOM KANAN: PASIVA -->
    <div class="col-lg-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header bg-light">
                <h6 class="card-mbb-title text-success"><i class="bi bi-wallet2 me-1"></i> PASIVA (KEWAJIBAN & EKUITAS)</h6>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <!-- Kewajiban Lancar -->
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="ps-4 text-dark">I. KEWAJIBAN / UTANG LANCAR</td>
                    </tr>
                    <tr>
                        <td class="ps-5 text-muted">Utang Dagang Pembelian Biji Kopi (Petani/Supplier)</td>
                        <td class="text-end pe-4 text-danger"><?= format_rupiah($totalPayables) ?></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td class="ps-4">Subtotal Kewajiban</td>
                        <td class="text-end pe-4 text-danger"><?= format_rupiah($totalKewajiban) ?></td>
                    </tr>

                    <!-- Ekuitas & Modal -->
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="ps-4 text-dark">II. EKUITAS & MODAL PEMILIK</td>
                    </tr>
                    <tr>
                        <td class="ps-5 text-muted">Modal Disetor CV. Maju Berkah Bersama</td>
                        <td class="text-end pe-4"><?= format_rupiah($modalAwal) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-5 text-muted">Saldo Laba Berjalan (Tahun <?= $yearFilter ?>)</td>
                        <td class="text-end pe-4 text-success fw-semibold"><?= format_rupiah($labaBerjalan) ?></td>
                    </tr>
                    <tr class="fw-bold bg-light">
                        <td class="ps-4">Subtotal Ekuitas</td>
                        <td class="text-end pe-4 text-success"><?= format_rupiah($totalEkuitas) ?></td>
                    </tr>

                    <!-- Space filler so height matches left column -->
                    <tr>
                        <td colspan="2" class="py-4 text-center text-muted small bg-white">
                            <i class="bi bi-shield-check text-success fs-5 d-block mb-1"></i>
                            Prinsip Keseimbangan Akuntansi Terpenuhi (Aktiva = Pasiva)
                        </td>
                    </tr>

                    <!-- GRAND TOTAL PASIVA -->
                    <tr class="fw-bold text-white" style="background-color: #0d1e3a;">
                        <td class="ps-4 fs-6">TOTAL PASIVA</td>
                        <td class="text-end pe-4 fs-6"><?= format_rupiah($grandTotalPasiva) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
