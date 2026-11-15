<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Coffee Trading (Penjualan & Pengiriman Kopi)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('coffee_trading.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// PROSES SIMPAN TRANSAKSI PENJUALAN KOPI BARU
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('coffee_trading.manage');

    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/coffee-trading/index.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_sale') {
        $buyerName   = trim($_POST['buyer_name'] ?? '');
        $saleDate    = $_POST['sale_date'] ?? date('Y-m-d');
        $weightKg    = floatval($_POST['total_weight_kg'] ?? 0);
        $capitalCost = floatval($_POST['capital_cost'] ?? 0);
        $grandTotal  = floatval($_POST['grand_total'] ?? 0);
        $langsirFee  = floatval($_POST['langsir_fee'] ?? ($weightKg * 100)); // Default Rp 100/kg
        $workerFee   = floatval($_POST['worker_fee'] ?? ($weightKg * 150));  // Default Rp 150/kg
        $opCost      = floatval($_POST['operational_cost'] ?? 0);
        $invsAmount  = floatval($_POST['invs_amount'] ?? ($weightKg * 100));
        $mbbAmount   = floatval($_POST['mbb_amount'] ?? ($weightKg * 75));
        $profit      = $grandTotal - $capitalCost - $langsirFee - $workerFee - $opCost;
        $notes       = trim($_POST['notes'] ?? '');

        // Distribusi Upah Pekerja (Birin, Huda, Sifa, dll)
        $workersDist = [];
        if (!empty($_POST['wage_birin'])) $workersDist['Birin (Ahmad Sobirin)'] = floatval($_POST['wage_birin']);
        if (!empty($_POST['wage_huda'])) $workersDist['Huda (Nuril Huda)'] = floatval($_POST['wage_huda']);
        if (!empty($_POST['wage_sifa'])) $workersDist['Sifa (M. Ilham Sifa)'] = floatval($_POST['wage_sifa']);
        if (!empty($_POST['wage_team'])) $workersDist['Kru Lapangan'] = floatval($_POST['wage_team']);
        $workersJson = !empty($workersDist) ? json_encode($workersDist, JSON_UNESCAPED_UNICODE) : null;

        if (empty($buyerName) || $weightKg <= 0 || $grandTotal <= 0) {
            flash_set('danger', 'Nama Pembeli, Jumlah Kg, dan Nilai Omzet Jual wajib diisi.');
        } else {
            // Cek atau buat customer
            $custStmt = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
            $custStmt->execute([$buyerName]);
            $cId = $custStmt->fetchColumn();
            if (!$cId) {
                $code = 'CUST-' . date('YmdHis');
                $insCust = $db->prepare("INSERT INTO customers (code, name, customer_type) VALUES (?, ?, 'WHOLESALER')");
                $insCust->execute([$code, $buyerName]);
                $cId = $db->lastInsertId();
            }

            // Nomor Surat Jalan Baru
            $countStmt = $db->query("SELECT COUNT(id) FROM sales");
            $nextNo = (int)$countStmt->fetchColumn() + 1;
            $invNumber = 'SJ-MBB-' . date('Ym', strtotime($saleDate)) . '-' . str_pad($nextNo, 3, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO sales 
                (invoice_number, sale_date, customer_id, warehouse_id, total_weight_kg, capital_cost, total_amount, grand_total,
                 langsir_fee, worker_fee, operational_cost, invs_amount, mbb_amount, profit_amount, worker_distribution, paid_amount, payment_status, notes, created_by)
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'LUNAS', ?, ?)
            ");
            $stmt->execute([
                $invNumber, $saleDate, $cId, $weightKg, $capitalCost, $grandTotal, $grandTotal,
                $langsirFee, $workerFee, $opCost, $invsAmount, $mbbAmount, $profit, $workersJson, $grandTotal, $notes, Auth::id()
            ]);
            $saleId = $db->lastInsertId();

            log_audit('CREATE_COFFEE_SALE', 'coffee-trading', $saleId, "Mencatat pengiriman kopi {$invNumber} ({$weightKg} Kg) kepada {$buyerName} senilai Rp " . number_format($grandTotal));
            flash_set('success', "Transaksi pengiriman kopi '{$invNumber}' kepada {$buyerName} berhasil dicatat.");
        }
        redirect('modules/coffee-trading/index.php');
    }

    if ($action === 'delete_sale') {
        $saleId = (int)($_POST['sale_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->execute([$saleId]);
        log_audit('DELETE_COFFEE_SALE', 'coffee-trading', $saleId, "Menghapus transaksi pengiriman kopi ID {$saleId}");
        flash_set('success', 'Transaksi berhasil dihapus.');
        redirect('modules/coffee-trading/index.php');
    }
}

// -------------------------------------------------------------
// FILTER & QUERY DATA PENJUALAN
// -------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$monthFilter = trim($_GET['month'] ?? '');

$query = "
    SELECT s.*, c.name AS customer_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (s.invoice_number LIKE ? OR c.name LIKE ? OR s.notes LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($monthFilter !== '') {
    $query .= " AND DATE_FORMAT(s.sale_date, '%Y-%m') = ?";
    $params[] = $monthFilter;
}

$query .= " ORDER BY s.sale_date DESC, s.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Total Summary Metrics
$sumStmt = $db->query("
    SELECT 
        COALESCE(SUM(total_weight_kg), 0) AS total_kg,
        COALESCE(SUM(capital_cost), 0) AS total_capital,
        COALESCE(SUM(grand_total), 0) AS total_omset,
        COALESCE(SUM(profit_amount), 0) AS total_profit,
        COALESCE(SUM(langsir_fee), 0) AS total_langsir,
        COALESCE(SUM(worker_fee), 0) AS total_worker,
        COALESCE(SUM(mbb_amount), 0) AS total_mbb
    FROM sales
");
$summary = $sumStmt->fetch();

$customers = $db->query("SELECT name FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Coffee Trading (Perdagangan Kopi)';
$currentModule = 'coffee-trading';
$pageCategory = 'DIVISI COFFEE TRADING & LOGISTIK';
$pageDesc = 'Pengelolaan distribusi perdagangan biji kopi Robusta Lampung Barat, rekap omzet, HPP modal, biaya langsir, upah pekerja, dan profit bersih.';
$pageBreadcrumbs = [
    'Coffee Trading' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/coffee-trading/purchases.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-basket3 me-1"></i> Beli dari Petani
        </a>
        <a href="' . base_url('modules/coffee-trading/partners.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-people me-1"></i> Data Petani & Rekanan
        </a>' . 
        (Permission::can('coffee_trading.manage') ? '
        <button type="button" class="btn btn-warning text-dark btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddSale">
            <i class="bi bi-plus-circle-fill"></i> Catat Pengiriman Kopi
        </button>' : '') . '
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. KPI CARDS COFFEE TRADING MBB (DATA REAL DARI EXCEL) -->
<div class="row g-3 mb-4">
    <!-- Total Volume Kg -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Volume Terjual</div>
                    <div class="stat-value text-primary"><?= number_format($summary['total_kg'], 1, ',', '.') ?> <span class="fs-6 text-muted">Kg</span></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-box-seam-fill"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Setara dengan <strong><?= number_format($summary['total_kg'] / 1000, 2, ',', '.') ?> Ton</strong> Biji Kopi
            </div>
        </div>
    </div>

    <!-- Total Omzet Penjualan -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Omzet Penjualan</div>
                    <div class="stat-value text-success"><?= format_rupiah($summary['total_omset']) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Modal Beli: <strong><?= format_rupiah($summary['total_capital']) ?></strong>
            </div>
        </div>
    </div>

    <!-- Total Profit Trading -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Keuntungan Bersih</div>
                    <div class="stat-value text-warning"><?= format_rupiah($summary['total_profit']) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Bagian MBB: <strong><?= format_rupiah($summary['total_mbb']) ?></strong>
            </div>
        </div>
    </div>

    <!-- Biaya Langsir & Pekerja -->
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card cyan">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Langsir & Upah Pekerja</div>
                    <div class="stat-value text-info"><?= format_rupiah($summary['total_langsir'] + $summary['total_worker']) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-truck"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Pekerja: <?= format_rupiah($summary['total_worker']) ?> &bull; Langsir: <?= format_rupiah($summary['total_langsir']) ?>
            </div>
        </div>
    </div>
</div>

<!-- 2. FILTER & PENCARIAN -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari no surat jalan, nama pembeli...">
                </div>
            </div>
            <div class="col-md-4">
                <select name="month" class="form-select form-select-sm">
                    <option value="">-- Semua Bulan Pengiriman --</option>
                    <option value="2026-05" <?= $monthFilter === '2026-05' ? 'selected' : '' ?>>Mei 2026</option>
                    <option value="2026-06" <?= $monthFilter === '2026-06' ? 'selected' : '' ?>>Juni 2026</option>
                    <option value="2026-07" <?= $monthFilter === '2026-07' ? 'selected' : '' ?>>Juli 2026</option>
                    <option value="2026-08" <?= $monthFilter === '2026-08' ? 'selected' : '' ?>>Agustus 2026</option>
                    <option value="2026-09" <?= $monthFilter === '2026-09' ? 'selected' : '' ?>>September 2026</option>
                    <option value="2026-10" <?= $monthFilter === '2026-10' ? 'selected' : '' ?>>Oktober 2026</option>
                    <option value="2026-11" <?= $monthFilter === '2026-11' ? 'selected' : '' ?>>November 2026</option>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <a href="<?= base_url('modules/coffee-trading/export_excel.php?month=' . urlencode($monthFilter)) ?>" class="btn btn-sm btn-success text-white" title="Unduh format Excel / CSV">
                    <i class="bi bi-file-earmark-excel me-1"></i> Ekspor Excel
                </a>
                <?php if ($search !== '' || $monthFilter !== ''): ?>
                    <a href="<?= base_url('modules/coffee-trading/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 3. TABEL DATA PENGIRIMAN & PENJUALAN KOPI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-cup-hot-fill"></i> Riwayat Pengiriman & Penjualan Biji Kopi MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($sales) ?> Pengiriman</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Surat Jalan</th>
                        <th>Tanggal Kirim</th>
                        <th>Pembeli / Rekanan</th>
                        <th>Volume (Kg)</th>
                        <th>Modal HPP</th>
                        <th>Omzet Penjualan</th>
                        <th>Langsir (100)</th>
                        <th>Pekerja (150)</th>
                        <th>Profit Bersih</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Belum ada data pengiriman kopi yang cocok.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= e($s['invoice_number']) ?></td>
                                <td><?= format_tanggal($s['sale_date']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($s['customer_name'] ?: 'Pelanggan') ?></div>
                                    <small class="text-muted"><?= e($s['notes'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border fs-6">
                                        <?= number_format($s['total_weight_kg'], 1, ',', '.') ?> Kg
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= format_rupiah($s['capital_cost']) ?></small></td>
                                <td class="fw-bold text-success"><?= format_rupiah($s['grand_total']) ?></td>
                                <td><small class="text-muted"><?= format_rupiah($s['langsir_fee']) ?></small></td>
                                <td><small class="text-muted"><?= format_rupiah($s['worker_fee']) ?></small></td>
                                <td>
                                    <?php $isProfit = $s['profit_amount'] >= 0; ?>
                                    <span class="badge <?= $isProfit ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> border">
                                        <?= format_rupiah($s['profit_amount']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/coffee-trading/print_delivery.php?id=' . $s['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Surat Jalan / Faktur">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (Permission::can('coffee_trading.manage')): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_sale">
                                            <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus data penjualan '<?= e($s['invoice_number']) ?>'?" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL CATAT PENJUALAN KOPI BARU
     ======================================================== -->
<div class="modal fade" id="modalAddSale" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_sale">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-cup-hot-fill me-1"></i> Form Pengiriman / Penjualan Biji Kopi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nama Pembeli / Rekanan <span class="text-danger">*</span></label>
                            <input type="text" name="buyer_name" id="calc_buyer" class="form-control" list="buyerList" placeholder="Misal: Mispan / Hendri / Bandung..." required>
                            <datalist id="buyerList">
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= e($c) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Pengiriman <span class="text-danger">*</span></label>
                            <input type="date" name="sale_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Volume Kopi (Kg) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="total_weight_kg" id="calc_kg" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Modal Pembelian (HPP Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="capital_cost" id="calc_modal" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Total Omzet Penjualan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="grand_total" id="calc_omset" class="form-control" placeholder="0" required>
                        </div>
                    </div>

                    <!-- Potongan & Biaya Otomatis Khas MBB -->
                    <div class="p-3 bg-light rounded border mb-3">
                        <h6 class="fw-bold text-dark mb-2" style="font-size: 0.85rem;"><i class="bi bi-calculator me-1"></i> Biaya & Pemotongan Standar MBB</h6>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small" style="font-size: 0.75rem;">Langsir (Rp 100/Kg)</label>
                                <input type="number" name="langsir_fee" id="calc_langsir" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" style="font-size: 0.75rem;">Upah Pekerja (Rp 150/Kg)</label>
                                <input type="number" name="worker_fee" id="calc_pekerja" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" style="font-size: 0.75rem;">Biaya Operasional Armada</label>
                                <input type="number" name="operational_cost" id="calc_op" value="0" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" style="font-size: 0.75rem;">Alokasi MBB (Rp 75/Kg)</label>
                                <input type="number" name="mbb_amount" id="calc_mbb" class="form-control form-control-sm" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Pembagian Upah Tim Lapangan -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold"><i class="bi bi-people me-1"></i> Pembagian Upah Tenaga Kerja Lapangan (Opsional)</label>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <input type="number" name="wage_birin" class="form-control form-control-sm" placeholder="Birin (Rp)">
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="wage_huda" class="form-control form-control-sm" placeholder="Huda (Rp)">
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="wage_sifa" class="form-control form-control-sm" placeholder="Sifa (Rp)">
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="wage_team" class="form-control form-control-sm" placeholder="Kru Lapangan (Rp)">
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Catatan Pengiriman</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Nomor polisi armada truk, kualitas biji kopi, tujuan gudang...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4 fw-bold">Simpan Transaksi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const kgInput = document.getElementById('calc_kg');
    const langsirInput = document.getElementById('calc_langsir');
    const pekerjaInput = document.getElementById('calc_pekerja');
    const mbbInput = document.getElementById('calc_mbb');

    if (kgInput) {
        kgInput.addEventListener('input', function () {
            const kg = parseFloat(this.value) || 0;
            langsirInput.value = Math.round(kg * 100);
            pekerjaInput.value = Math.round(kg * 150);
            mbbInput.value = Math.round(kg * 75);
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
