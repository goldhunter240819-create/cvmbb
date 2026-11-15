<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Executive Dashboard
 */

require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Permission.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/helpers/utils.php';

Auth::requireLogin();
Permission::require('dashboard.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// FILTER PERIODE WAKTU
// -------------------------------------------------------------
$period = $_GET['period'] ?? 'this_month';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$now = new DateTime();
switch ($period) {
    case 'today':
        $startDate = $now->format('Y-m-d');
        $endDate = $now->format('Y-m-d');
        $periodLabel = 'Hari Ini (' . format_tanggal($startDate) . ')';
        break;
    case 'this_week':
        $startDate = (clone $now)->modify('monday this week')->format('Y-m-d');
        $endDate = (clone $now)->modify('sunday this week')->format('Y-m-d');
        $periodLabel = 'Minggu Ini (' . format_tanggal($startDate) . ' s/d ' . format_tanggal($endDate) . ')';
        break;
    case 'this_year':
        $startDate = $now->format('Y-01-01');
        $endDate = $now->format('Y-12-31');
        $periodLabel = 'Tahun ' . $now->format('Y');
        break;
    case 'custom':
        if (empty($startDate)) $startDate = $now->format('Y-m-01');
        if (empty($endDate)) $endDate = $now->format('Y-m-d');
        $periodLabel = format_tanggal($startDate) . ' s/d ' . format_tanggal($endDate);
        break;
    case 'this_month':
    default:
        $period = 'this_month';
        $startDate = $now->format('Y-m-01');
        $endDate = $now->format('Y-m-t');
        $periodLabel = 'Bulan Ini (' . format_tanggal($startDate) . ' s/d ' . format_tanggal($endDate) . ')';
        break;
}

// -------------------------------------------------------------
// REAL QUERY METRICS DARI DATABASE
// -------------------------------------------------------------

// 1. Total Penjualan Kopi
$stmt = $db->prepare("
    SELECT COALESCE(SUM(grand_total), 0) AS total_sales, COUNT(id) AS count_sales
    FROM sales 
    WHERE sale_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$coffeeSales = $stmt->fetch();
$totalCoffeeSales = floatval($coffeeSales['total_sales']);
$countCoffeeSales = intval($coffeeSales['count_sales']);

// 2. Total Pembelian Kopi
$stmt = $db->prepare("
    SELECT COALESCE(SUM(grand_total), 0) AS total_purchases, COALESCE(SUM(net_weight), 0) AS total_weight_kg, COUNT(id) AS count_purchases
    FROM purchases 
    WHERE purchase_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$coffeePurchases = $stmt->fetch();
$totalCoffeePurchases = floatval($coffeePurchases['total_purchases']);
$totalPurchasedKg = floatval($coffeePurchases['total_weight_kg']);
$countPurchases = intval($coffeePurchases['count_purchases']);

// 3. Pendapatan Jasa Roasting (Maklon)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(paid_amount), 0) AS total_fee, COUNT(id) AS count_roast
    FROM roasting_orders 
    WHERE order_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$roastingService = $stmt->fetch();
$totalRoastingIncome = floatval($roastingService['total_fee']);
$countRoasting = intval($roastingService['count_roast']);

// 4. Pendapatan Jasa BRILink (Company Income)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(company_income), 0) AS total_income, COUNT(id) AS count_trx
    FROM brilink_transactions 
    WHERE DATE(transaction_date) BETWEEN ? AND ? AND status = 'BERHASIL'
");
$stmt->execute([$startDate, $endDate]);
$brilink = $stmt->fetch();
$totalBrilinkIncome = floatval($brilink['total_income']);
$countBrilink = intval($brilink['count_trx']);

// 4b. Pendapatan Divisi Dekoration & Sound Sistem
$stmtDekor = $db->prepare("SELECT COALESCE(SUM(total_income), 0) AS total_income FROM division_activities WHERE division_id = 5 AND start_date BETWEEN ? AND ?");
$stmtDekor->execute([$startDate, $endDate]);
$totalDekorIncome = floatval($stmtDekor->fetchColumn());

$stmtSound = $db->prepare("SELECT COALESCE(SUM(total_income), 0) AS total_income FROM division_activities WHERE division_id = 6 AND start_date BETWEEN ? AND ?");
$stmtSound->execute([$startDate, $endDate]);
$totalSoundIncome = floatval($stmtSound->fetchColumn());

// 5. Total Pendapatan Operasional Perusahaan (Sales + Roasting + BRILink Fee + Dekor + Sound)
$totalRevenue = $totalCoffeeSales + $totalRoastingIncome + $totalBrilinkIncome + $totalDekorIncome + $totalSoundIncome;

// 6. Total Pengeluaran Beban (Expenses + Purchases)
$stmt = $db->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$expensesData = $stmt->fetch();
$operationalExpenses = floatval($expensesData['total_expenses']);
$totalExpenses = $operationalExpenses + $totalCoffeePurchases;

// 7. Estimasi Laba Kotor / Keuntungan Bersih Operasional
$estimatedProfit = $totalRevenue - $totalExpenses;

// 8. Total Persediaan Barang (Nilai Aset Stok)
$stmt = $db->query("
    SELECT COALESCE(SUM(current_stock * cost_price), 0) AS total_inventory_value,
           COALESCE(SUM(current_stock), 0) AS total_inventory_qty
    FROM products 
    WHERE is_active = 1
");
$inventoryData = $stmt->fetch();
$totalInventoryValue = floatval($inventoryData['total_inventory_value']);
$totalInventoryQty = floatval($inventoryData['total_inventory_qty']);

// 9. Total Piutang & Utang
$stmt = $db->query("SELECT COALESCE(SUM(remaining_amount), 0) AS total_receivable FROM receivables WHERE status != 'LUNAS'");
$totalReceivables = floatval($stmt->fetch()['total_receivable']);

$stmt = $db->query("SELECT COALESCE(SUM(remaining_amount), 0) AS total_payable FROM payables WHERE status != 'LUNAS'");
$totalPayables = floatval($stmt->fetch()['total_payable']);

// 10. Saldo Kas & Bank Keseluruhan
$stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) AS total_cash FROM cash_accounts WHERE is_active = 1");
$totalCashBalance = floatval($stmt->fetch()['total_cash']);

// 11. Aktivitas Terkini (Audit Logs)
$stmt = $db->query("
    SELECT al.*, u.username, r.role_title
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY al.created_at DESC 
    LIMIT 6
");
$recentActivities = $stmt->fetchAll();

// 12. Ringkasan Kegiatan Divisi Aktif
$stmt = $db->query("
    SELECT a.*, d.division_name
    FROM division_activities a
    LEFT JOIN divisions d ON a.division_id = d.id
    ORDER BY a.start_date DESC
    LIMIT 4
");
$recentActivitiesDiv = $stmt->fetchAll();

$pageTitle = 'Dashboard Eksekutif';
$currentModule = 'dashboard';
$pageBreadcrumbs = [];

$pageActionBtn = '
    <button onclick="window.print()" class="btn btn-light btn-sm fw-bold px-3 py-2 shadow-sm d-flex align-items-center gap-2">
        <i class="bi bi-printer text-primary"></i> Cetak Ringkasan
    </button>
';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
require_once __DIR__ . '/components/topbar.php';
?>

<!-- FILTER PERIODE WAKTU -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="<?= base_url('index.php') ?>" class="row g-2 align-items-center">
            <div class="col-md-auto d-flex align-items-center">
                <i class="bi bi-funnel-fill text-primary me-2 fs-5"></i>
                <span class="fw-bold text-dark me-3">Filter Periode:</span>
            </div>
            
            <div class="col-md-auto">
                <div class="btn-group" role="group">
                    <a href="?period=today" class="btn btn-sm <?= $period === 'today' ? 'btn-primary' : 'btn-outline-secondary' ?>">Hari Ini</a>
                    <a href="?period=this_week" class="btn btn-sm <?= $period === 'this_week' ? 'btn-primary' : 'btn-outline-secondary' ?>">Minggu Ini</a>
                    <a href="?period=this_month" class="btn btn-sm <?= $period === 'this_month' ? 'btn-primary' : 'btn-outline-secondary' ?>">Bulan Ini</a>
                    <a href="?period=this_year" class="btn btn-sm <?= $period === 'this_year' ? 'btn-primary' : 'btn-outline-secondary' ?>">Tahun Ini</a>
                </div>
            </div>

            <div class="col-md-auto ms-md-auto d-flex align-items-center gap-2">
                <input type="hidden" name="period" value="custom">
                <span class="small text-muted">Kustom:</span>
                <input type="date" name="start_date" value="<?= e($startDate) ?>" class="form-control form-control-sm" style="width: 140px;">
                <span class="small text-muted">s/d</span>
                <input type="date" name="end_date" value="<?= e($endDate) ?>" class="form-control form-control-sm" style="width: 140px;">
                <button type="submit" class="btn btn-sm btn-primary px-3">
                    <i class="bi bi-search me-1"></i> Terapkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- INFORMASI PERIODE AKTIF -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h5 class="fw-bold mb-0 text-dark">Ringkasan Kinerja Multi-Unit Perusahaan</h5>
        <small class="text-muted"><i class="bi bi-clock-history me-1"></i>Periode aktif: <span class="fw-semibold text-primary"><?= e($periodLabel) ?></span></small>
    </div>
</div>

<!-- 1. KEY KPI CARDS -->
<div class="row g-3 mb-4">
    <!-- Total Pendapatan -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Pendapatan</div>
                    <div class="stat-value text-primary"><?= format_rupiah($totalRevenue) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <span class="text-success fw-bold"><i class="bi bi-check2-circle"></i> Omzet Masuk</span> dari seluruh unit usaha
            </div>
        </div>
    </div>

    <!-- Total Pengeluaran -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card danger">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Pengeluaran</div>
                    <div class="stat-value text-danger"><?= format_rupiah($totalExpenses) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-graph-down-arrow"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Beli Kopi: <strong><?= format_rupiah($totalCoffeePurchases) ?></strong>
            </div>
        </div>
    </div>

    <!-- Estimasi Keuntungan -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card <?= $estimatedProfit >= 0 ? 'success' : 'warning' ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Estimasi Keuntungan</div>
                    <div class="stat-value <?= $estimatedProfit >= 0 ? 'text-success' : 'text-warning' ?>">
                        <?= format_rupiah($estimatedProfit) ?>
                    </div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Laba operasional bersih terhitung
            </div>
        </div>
    </div>

    <!-- Saldo Kas & Bank -->
    <div class="col-xl-3 col-md-6">
        <div class="stat-card navy">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Kas & Bank Tersedia</div>
                    <div class="stat-value text-dark"><?= format_rupiah($totalCashBalance) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-bank2"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Likuiditas kas perusahaan saat ini
            </div>
        </div>
    </div>
</div>

<!-- 2. SECONDARY METRICS (PER LINI BISNIS) -->
<div class="row g-3 mb-4">
    <!-- Coffee Trading -->
    <div class="col-xl-3 col-sm-6">
        <div class="card-mbb p-3 h-100">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 me-3">
                    <i class="bi bi-cup-hot fs-4"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Trading Biji Kopi</small>
                    <div class="fw-bold text-dark fs-6"><?= format_rupiah($totalCoffeeSales) ?></div>
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span>Pembelian: <?= number_format($totalPurchasedKg, 1) ?> Kg</span>
                <span><?= $countCoffeeSales ?> Penjualan</span>
            </div>
        </div>
    </div>

    <!-- Roasting Service -->
    <div class="col-xl-3 col-sm-6">
        <div class="card-mbb p-3 h-100">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 bg-warning-subtle text-warning rounded-3 me-3">
                    <i class="bi bi-fire fs-4"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Jasa Roasting</small>
                    <div class="fw-bold text-dark fs-6"><?= format_rupiah($totalRoastingIncome) ?></div>
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span>Total Order: <?= $countRoasting ?> Maklon</span>
                <span class="text-success">Aktif</span>
            </div>
        </div>
    </div>

    <!-- Agen BRILink -->
    <div class="col-xl-3 col-sm-6">
        <div class="card-mbb p-3 h-100">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 bg-info-subtle text-info rounded-3 me-3">
                    <i class="bi bi-credit-card fs-4"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Pendapatan BRILink</small>
                    <div class="fw-bold text-dark fs-6"><?= format_rupiah($totalBrilinkIncome) ?></div>
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span><?= $countBrilink ?> Transaksi</span>
                <span class="text-info">Fee Layanan</span>
            </div>
        </div>
    </div>

    <!-- Persediaan & Piutang -->
    <div class="col-xl-3 col-sm-6">
        <div class="card-mbb p-3 h-100">
            <div class="d-flex align-items-center mb-2">
                <div class="p-2 bg-success-subtle text-success rounded-3 me-3">
                    <i class="bi bi-boxes fs-4"></i>
                </div>
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Nilai Persediaan</small>
                    <div class="fw-bold text-dark fs-6"><?= format_rupiah($totalInventoryValue) ?></div>
                </div>
            </div>
            <div class="d-flex justify-content-between small text-muted border-top pt-2">
                <span class="text-danger">Utang: <?= format_rupiah($totalPayables) ?></span>
                <span class="text-primary">Piutang: <?= format_rupiah($totalReceivables) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 3. GRAFIK KINERJA DENGAN CHART.JS -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title">
                    <i class="bi bi-bar-chart-fill text-primary"></i> Tren Arus Pendapatan & Pengeluaran
                </h6>
                <span class="badge bg-light text-muted border"><?= e($periodLabel) ?></span>
            </div>
            <div class="card-mbb-body">
                <canvas id="revenueExpensesChart" style="max-height: 320px;"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title">
                    <i class="bi bi-pie-chart-fill text-info"></i> Kontribusi Lini Usaha
                </h6>
            </div>
            <div class="card-mbb-body d-flex flex-column justify-content-center">
                <canvas id="businessLineChart" style="max-height: 260px;"></canvas>
                <div class="mt-3 text-center small text-muted">
                    Proporsi kontribusi pendapatan 5 unit usaha: Coffee Trading, Roasting, BRILink, Dekoration & Sound Sistem
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. AKTIVITAS TERBARU & RINGKASAN KEGIATAN DIVISI -->
<div class="row g-4">
    <!-- Audit Trail Aktivitas -->
    <div class="col-lg-7">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title">
                    <i class="bi bi-activity text-warning"></i> Aktivitas Sistem & Pengguna Terkini
                </h6>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Audit Trail</span>
            </div>
            <div class="card-mbb-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Waktu</th>
                                <th>Pengguna</th>
                                <th>Aksi</th>
                                <th>Modul</th>
                                <th class="pe-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentActivities)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada aktivitas tercatat.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentActivities as $act): ?>
                                    <tr>
                                        <td class="ps-3 text-nowrap text-muted"><?= date('d/m/Y H:i', strtotime($act['created_at'])) ?></td>
                                        <td class="fw-semibold text-dark text-nowrap"><?= e($act['username'] ?? 'System') ?></td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border"><?= e($act['action']) ?></span>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= e($act['module']) ?></span></td>
                                        <td class="pe-3 text-muted"><?= e($act['description']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Ringkasan Kegiatan Divisi (Dekorasi, Sound Sistem, dll) -->
    <div class="col-lg-5">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title">
                    <i class="bi bi-calendar-event text-success"></i> Kegiatan & Proyek Divisi
                </h6>
                <a href="<?= base_url('modules/decoration/index.php') ?>" class="btn btn-sm btn-link text-decoration-none">Kelola <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-mbb-body p-0">
                <?php if (empty($recentActivitiesDiv)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-clipboard2-check fs-1 text-light-emphasis d-block mb-2"></i>
                        Belum ada kegiatan divisi yang terdaftar.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentActivitiesDiv as $act): ?>
                            <li class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;"><?= e($act['activity_name']) ?></h6>
                                    <span class="badge bg-primary-subtle text-primary"><?= e($act['division_name'] ?? 'Divisi') ?></span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-geo-alt me-1"></i><?= e($act['location'] ?: 'Lokasi Acara') ?> &bull; 
                                    <i class="bi bi-person me-1"></i><?= e($act['person_in_charge']) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small fw-semibold text-secondary">Mulai: <?= format_tanggal($act['start_date']) ?></span>
                                    <span class="badge bg-warning-subtle text-warning border"><?= e($act['status']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPT INISIALISASI CHART.JS DENGAN DATA NYATA -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Chart Arus Kas & Pengeluaran
    const ctxBar = document.getElementById('revenueExpensesChart').getContext('2d');
    new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: ['Pendapatan Kopi', 'Jasa Roasting', 'Agen BRILink', 'Beban Kopi', 'Beban Operasional'],
            datasets: [{
                label: 'Nominal (Rp)',
                data: [
                    <?= $totalCoffeeSales ?>,
                    <?= $totalRoastingIncome ?>,
                    <?= $totalBrilinkIncome ?>,
                    <?= $totalCoffeePurchases ?>,
                    <?= $operationalExpenses ?>
                ],
                backgroundColor: [
                    'rgba(26, 86, 219, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(6, 182, 212, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(100, 116, 139, 0.8)'
                ],
                borderColor: [
                    '#1a56db',
                    '#f59e0b',
                    '#06b6d4',
                    '#ef4444',
                    '#64748b'
                ],
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + formatRupiahJS(context.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) return 'Rp ' + (value / 1000000) + ' Jt';
                            if (value >= 1000) return 'Rp ' + (value / 1000) + ' Rb';
                            return 'Rp ' + value;
                        }
                    }
                }
            }
        }
    });

    // 2. Chart Proporsi Lini Usaha (Donut)
    const ctxPie = document.getElementById('businessLineChart').getContext('2d');
    const coffeeVal = <?= $totalCoffeeSales ?>;
    const roastVal = <?= $totalRoastingIncome ?>;
    const brilinkVal = <?= $totalBrilinkIncome ?>;
    const dekorVal = <?= $totalDekorIncome ?>;
    const soundVal = <?= $totalSoundIncome ?>;
    const isZero = (coffeeVal + roastVal + brilinkVal + dekorVal + soundVal) === 0;

    new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: isZero ? ['Belum ada transaksi'] : ['Coffee Trading', 'Jasa Roasting', 'BRILink', 'Dekoration', 'Sound Sistem'],
            datasets: [{
                data: isZero ? [1] : [coffeeVal, roastVal, brilinkVal, dekorVal, soundVal],
                backgroundColor: isZero ? ['#e2e8f0'] : ['#1a56db', '#f59e0b', '#06b6d4', '#ec4899', '#8b5cf6'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (isZero) return ' Belum ada data';
                            return ' ' + context.label + ': ' + formatRupiahJS(context.raw);
                        }
                    }
                }
            },
            cutout: '68%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
