<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Laporan Manajemen & Laba Rugi Konsolidasi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('reports.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// FILTER PERIODE
// -------------------------------------------------------------
$yearFilter  = (int)($_GET['year'] ?? 2026);
$monthFilter = trim($_GET['month'] ?? '');

$dateConditionSales = "YEAR(sale_date) = :year";
$dateConditionRoast = "YEAR(order_date) = :year";
$dateConditionBri   = "YEAR(transaction_date) = :year";
$dateConditionAct   = "YEAR(start_date) = :year";
$dateConditionCash  = "YEAR(trx_date) = :year";

$sqlParams = [':year' => $yearFilter];

if ($monthFilter !== '') {
    $dateConditionSales .= " AND MONTH(sale_date) = :month";
    $dateConditionRoast .= " AND MONTH(order_date) = :month";
    $dateConditionBri   .= " AND MONTH(transaction_date) = :month";
    $dateConditionAct   .= " AND MONTH(start_date) = :month";
    $dateConditionCash  .= " AND MONTH(trx_date) = :month";
    $sqlParams[':month'] = (int)$monthFilter;
}

// 1. LAPORAN COFFEE TRADING
$stmtTrading = $db->prepare("
    SELECT 
        COUNT(*) AS total_shipments,
        COALESCE(SUM(total_weight_kg), 0) AS total_kg,
        COALESCE(SUM(capital_cost), 0) AS total_capital,
        COALESCE(SUM(grand_total), 0) AS total_revenue,
        COALESCE(SUM(langsir_fee), 0) AS total_langsir,
        COALESCE(SUM(worker_fee), 0) AS total_worker,
        COALESCE(SUM(operational_cost), 0) AS total_op,
        COALESCE(SUM(profit_amount), 0) AS net_profit
    FROM sales
    WHERE {$dateConditionSales}
");
$stmtTrading->execute($sqlParams);
$trading = $stmtTrading->fetch();

// 2. LAPORAN ROASTING SERVICE (MAKLON)
$stmtRoast = $db->prepare("
    SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(raw_weight_kg), 0) AS total_raw,
        COALESCE(SUM(total_fee), 0) AS total_fee,
        COALESCE(SUM(paid_amount), 0) AS total_paid
    FROM roasting_orders
    WHERE {$dateConditionRoast}
");
$stmtRoast->execute($sqlParams);
$roasting = $stmtRoast->fetch();

// 3. LAPORAN BRILINK
$stmtBri = $db->prepare("
    SELECT 
        COUNT(*) AS total_trx,
        COALESCE(SUM(nominal_amount), 0) AS total_volume,
        COALESCE(SUM(admin_fee_charged), 0) AS total_admin,
        COALESCE(SUM(bank_fee), 0) AS total_bank_fee,
        COALESCE(SUM(company_income), 0) AS net_income
    FROM brilink_transactions
    WHERE {$dateConditionBri} AND status = 'BERHASIL'
");
$stmtBri->execute($sqlParams);
$brilink = $stmtBri->fetch();

// 4. LAPORAN DIVISI DEKORATION (ID: 5)
$stmtDekor = $db->prepare("
    SELECT 
        COUNT(*) AS total_jobs,
        COALESCE(SUM(total_income), 0) AS total_income,
        COALESCE(SUM(total_cost), 0) AS total_cost
    FROM division_activities
    WHERE division_id = 5 AND {$dateConditionAct} AND status = 'SELESAI'
");
$stmtDekor->execute($sqlParams);
$dekor = $stmtDekor->fetch();
$dekorProfit = $dekor['total_income'] - $dekor['total_cost'];

// 5. LAPORAN DIVISI SOUND SISTEM (ID: 6)
$stmtSound = $db->prepare("
    SELECT 
        COUNT(*) AS total_jobs,
        COALESCE(SUM(total_income), 0) AS total_income,
        COALESCE(SUM(total_cost), 0) AS total_cost
    FROM division_activities
    WHERE division_id = 6 AND {$dateConditionAct} AND status = 'SELESAI'
");
$stmtSound->execute($sqlParams);
$sound = $stmtSound->fetch();
$soundProfit = $sound['total_income'] - $sound['total_cost'];

// 6. BEBAN OPERASIONAL KANTOR / UMUM (DARI BUKU KAS)
$stmtGeneralExp = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_gen_exp
    FROM cash_transactions
    WHERE trx_direction = 'OUT' AND business_line = 'MANAGEMENT' AND category != 'Mutasi Antar-Kas' AND {$dateConditionCash}
");
$stmtGeneralExp->execute($sqlParams);
$generalExpense = (float)$stmtGeneralExp->fetchColumn();

// KONSOLIDASI LABA BERSIH
$totalConsolidatedProfit = (
    $trading['net_profit'] +
    $roasting['total_fee'] +
    $brilink['net_income'] +
    $dekorProfit +
    $soundProfit -
    $generalExpense
);

$pageTitle = 'Laporan Manajemen & Laba Rugi Konsolidasi';
$currentModule = 'reports';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Laporan Manajemen' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. NAVIGATION TABS -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group" role="group">
        <a href="<?= base_url('modules/reports/index.php') ?>" class="btn btn-sm btn-primary active">
            <i class="bi bi-graph-up me-1"></i> Laba Rugi Konsolidasi
        </a>
        <a href="<?= base_url('modules/reports/balance_sheet.php') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-bank me-1"></i> Neraca Keuangan (Balance Sheet)
        </a>
    </div>
</div>

<!-- 2. FILTER PERIODE LAPORAN -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center justify-content-between">
            <div class="col-md-6 d-flex align-items-center gap-2">
                <div class="fw-bold text-dark"><i class="bi bi-funnel me-1"></i> Periode Laporan:</div>
                <select name="year" class="form-select form-select-sm w-auto">
                    <option value="2026" <?= $yearFilter === 2026 ? 'selected' : '' ?>>Tahun 2026</option>
                    <option value="2025" <?= $yearFilter === 2025 ? 'selected' : '' ?>>Tahun 2025</option>
                </select>
                <select name="month" class="form-select form-select-sm w-auto">
                    <option value="">-- Seluruh Bulan --</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <?php $mVal = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= $mVal ?>" <?= $monthFilter === $mVal ? 'selected' : '' ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
            </div>
            <div class="col-md-auto text-end d-flex gap-2">
                <a href="<?= base_url('modules/reports/export_excel.php?year=' . $yearFilter . '&month=' . $monthFilter) ?>" class="btn btn-sm btn-success text-white">
                    <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel / CSV
                </a>
                <a href="<?= base_url('modules/reports/print_financial.php?year=' . $yearFilter . '&month=' . $monthFilter) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-printer me-1"></i> Cetak Laporan Keuangan Resmi
                </a>
            </div>
        </form>
    </div>
</div>

<!-- 2. KARTU BESAR KONSOLIDASI LABA BERSIH PERUSAHAAN -->
<div class="card-mbb mb-4 border-start border-4 border-primary">
    <div class="card-mbb-body p-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="text-uppercase fw-bold text-muted small">Konsolidasi Laba Bersih Perusahaan CV. MAJU BERKAH BERSAMA</div>
                <h1 class="fw-extrabold text-primary my-2" style="font-size: 2.3rem;">
                    <?= format_rupiah($totalConsolidatedProfit) ?>
                </h1>
                <p class="text-muted mb-0 small">
                    Periode: <strong><?= $monthFilter ? date('F', mktime(0,0,0,(int)$monthFilter, 10)) . ' ' : 'Tahun ' ?><?= $yearFilter ?></strong> &bull; Total akumulasi laba bersih dari seluruh 5 lini usaha setelah dipotong beban operasional kantor.
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="p-3 bg-light rounded border text-start">
                    <div class="small text-muted">Beban Kantor / Pusat:</div>
                    <div class="fw-bold text-danger"><?= format_rupiah($generalExpense) ?></div>
                    <div class="small text-muted mt-2">Unit Usaha Aktif:</div>
                    <div class="fw-bold text-dark">5 Divisi Operasional</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. RINCIAN KINERJA PER LINI USAHA -->
<div class="row g-4 mb-4">
    <!-- COFFEE TRADING -->
    <div class="col-md-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header d-flex justify-content-between align-items-center">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-cup-hot-fill"></i> 1. Divisi Coffee Trading (Kopi Biji)</h6>
                <span class="badge bg-primary-subtle text-primary border"><?= $trading['total_shipments'] ?> Pengiriman</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4">Volume Kopi Terjual</td>
                        <td class="text-end pe-4 fw-bold"><?= number_format($trading['total_kg'], 0, ',', '.') ?> Kg</td>
                    </tr>
                    <tr>
                        <td class="ps-4">Omzet Penjualan Kopi</td>
                        <td class="text-end pe-4 fw-bold text-success"><?= format_rupiah($trading['total_revenue']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Modal Pembelian (HPP Kopi)</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($trading['total_capital']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Biaya Langsir Kopi (Rp 100/Kg)</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($trading['total_langsir']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Biaya Upah Pekerja (Rp 150/Kg)</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($trading['total_worker']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Biaya Operasional Armada</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($trading['total_op']) ?></td>
                    </tr>
                    <tr style="background-color: #f8fafc;" class="fw-bold">
                        <td class="ps-4 text-primary">Laba Bersih Coffee Trading</td>
                        <td class="text-end pe-4 text-primary fs-6"><?= format_rupiah($trading['net_profit']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- ROASTING SERVICE (MAKLON) -->
    <div class="col-md-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header d-flex justify-content-between align-items-center">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-fire"></i> 2. Coffee Roasting Service (Maklon)</h6>
                <span class="badge bg-primary-subtle text-primary border"><?= $roasting['total_orders'] ?> Order</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4">Total Volume Sangrai Pelanggan</td>
                        <td class="text-end pe-4 fw-bold"><?= number_format($roasting['total_raw'], 1, ',', '.') ?> Kg</td>
                    </tr>
                    <tr>
                        <td class="ps-4">Total Nilai Tagihan Jasa</td>
                        <td class="text-end pe-4 fw-bold text-success"><?= format_rupiah($roasting['total_fee']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Pembayaran Diterima (Kas Masuk)</td>
                        <td class="text-end pe-4 text-dark"><?= format_rupiah($roasting['total_paid']) ?></td>
                    </tr>
                    <tr style="background-color: #f8fafc;" class="fw-bold">
                        <td class="ps-4 text-primary">Pendapatan Jasa Roasting</td>
                        <td class="text-end pe-4 text-primary fs-6"><?= format_rupiah($roasting['total_fee']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- BRILINK -->
    <div class="col-md-4">
        <div class="card-mbb h-100">
            <div class="card-mbb-header d-flex justify-content-between align-items-center">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-credit-card-2-front"></i> 3. Agen BRILink</h6>
                <span class="badge bg-primary-subtle text-primary border"><?= $brilink['total_trx'] ?> Trx</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4">Volume Mutasi</td>
                        <td class="text-end pe-4 fw-semibold"><?= format_rupiah($brilink['total_volume']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Admin Diterima</td>
                        <td class="text-end pe-4 text-success"><?= format_rupiah($brilink['total_admin']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Potongan Bank</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($brilink['total_bank_fee']) ?></td>
                    </tr>
                    <tr style="background-color: #f8fafc;" class="fw-bold">
                        <td class="ps-4 text-primary">Margin Fee Jasa MBB</td>
                        <td class="text-end pe-4 text-primary"><?= format_rupiah($brilink['net_income']) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- DIVISI DEKORATION -->
    <div class="col-md-4">
        <div class="card-mbb h-100">
            <div class="card-mbb-header d-flex justify-content-between align-items-center">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-flower1"></i> 4. Divisi Dekoration</h6>
                <span class="badge bg-primary-subtle text-primary border"><?= $dekor['total_jobs'] ?> Job Selesai</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4">Pendapatan Kontrak</td>
                        <td class="text-end pe-4 text-success fw-semibold"><?= format_rupiah($dekor['total_income']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Biaya Crew & Alat</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($dekor['total_cost']) ?></td>
                    </tr>
                    <tr style="background-color: #f8fafc;" class="fw-bold">
                        <td class="ps-4 text-primary">Laba Bersih Divisi</td>
                        <td class="text-end pe-4 text-primary"><?= format_rupiah($dekorProfit) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- DIVISI SOUND SISTEM -->
    <div class="col-md-4">
        <div class="card-mbb h-100">
            <div class="card-mbb-header d-flex justify-content-between align-items-center">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-speaker-fill"></i> 5. Divisi Sound Sistem</h6>
                <span class="badge bg-primary-subtle text-primary border"><?= $sound['total_jobs'] ?> Job Selesai</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4">Pendapatan Sewa</td>
                        <td class="text-end pe-4 text-success fw-semibold"><?= format_rupiah($sound['total_income']) ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Biaya Operator & BBM</td>
                        <td class="text-end pe-4 text-danger">- <?= format_rupiah($sound['total_cost']) ?></td>
                    </tr>
                    <tr style="background-color: #f8fafc;" class="fw-bold">
                        <td class="ps-4 text-primary">Laba Bersih Divisi</td>
                        <td class="text-end pe-4 text-primary"><?= format_rupiah($soundProfit) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
