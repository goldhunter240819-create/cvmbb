<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Coffee Production (Pengolahan, Roasting, Grinding & Packaging)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('production.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// POST HANDLER: TAMBAH / UPDATE BATCH PRODUKSI
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_batch') {
        Permission::require('production.create');
        csrf_validate();

        $batchNumber     = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $productionDate  = sanitize($_POST['production_date'] ?? date('Y-m-d'));
        $operatorId      = (int)($_POST['operator_id'] ?? Auth::id());
        $rawProductId    = (int)($_POST['raw_product_id'] ?? 0);
        $rawWeightKg     = (float)($_POST['raw_weight_kg'] ?? 0);
        $rawCostPerKg    = (float)($_POST['raw_cost_per_kg'] ?? 0);
        $operationalCost = (float)($_POST['operational_cost'] ?? 0);
        $outputProductId = (int)($_POST['output_product_id'] ?? 0);
        $outputWeightKg  = (float)($_POST['output_weight_kg'] ?? 0);
        $roastLevel      = sanitize($_POST['roast_level'] ?? 'MEDIUM');
        $status          = sanitize($_POST['status'] ?? 'SELESAI');
        $notes           = sanitize($_POST['notes'] ?? '');

        if ($rawWeightKg <= 0 || $outputWeightKg <= 0 || $rawProductId <= 0 || $outputProductId <= 0) {
            flash_set('danger', 'Harap isi berat bahan baku, berat hasil, dan pilih produk dengan benar.');
            redirect('modules/coffee-production/index.php');
        }

        $totalRawCost = $rawWeightKg * $rawCostPerKg;
        $totalHpp = $totalRawCost + $operationalCost;
        $hppPerKg = $outputWeightKg > 0 ? ($totalHpp / $outputWeightKg) : 0;

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO production_orders (
                    batch_number, production_date, operator_id,
                    raw_product_id, raw_weight_kg, raw_cost_per_kg, total_raw_cost, operational_cost,
                    output_product_id, output_weight_kg, total_hpp, hpp_per_kg,
                    roast_level, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $batchNumber, $productionDate, $operatorId,
                $rawProductId, $rawWeightKg, $rawCostPerKg, $totalRawCost, $operationalCost,
                $outputProductId, $outputWeightKg, $totalHpp, $hppPerKg,
                $roastLevel, $status, $notes
            ]);
            $orderId = $db->lastInsertId();

            // Jika status SELESAI, otomatis potong stok bahan baku & tambah stok produk jadi
            if ($status === 'SELESAI') {
                // Potong stok bahan baku
                $stmtReduce = $db->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
                $stmtReduce->execute([$rawWeightKg, $rawProductId]);

                // Tambah stok output barang jadi & perbarui cost_price (HPP)
                $stmtAdd = $db->prepare("UPDATE products SET current_stock = current_stock + ?, cost_price = ? WHERE id = ?");
                $stmtAdd->execute([$outputWeightKg, $hppPerKg, $outputProductId]);

                // Catat mutasi stok
                $stmtStock = $db->prepare("
                    INSERT INTO stock_movements (product_id, movement_type, quantity, reference_type, reference_id, notes, created_by)
                    VALUES (?, 'OUT', ?, 'PRODUCTION_IN', ?, 'Bahan Baku Produksi {$batchNumber}', ?),
                           (?, 'IN', ?, 'PRODUCTION_OUT', ?, 'Hasil Produksi {$batchNumber}', ?)
                ");
                $stmtStock->execute([
                    $rawProductId, $rawWeightKg, $orderId, Auth::id(),
                    $outputProductId, $outputWeightKg, $orderId, Auth::id()
                ]);
            }

            $db->commit();
            log_audit('CREATE_PRODUCTION_BATCH', 'coffee-production', $orderId, "Batch {$batchNumber} dibuat: {$rawWeightKg}kg -> {$outputWeightKg}kg HPP Rp " . number_format($hppPerKg));
            flash_set('success', "Batch produksi {$batchNumber} berhasil disimpan.");
        } catch (Exception $e) {
            $db->rollBack();
            flash_set('danger', 'Gagal menyimpan batch produksi: ' . $e->getMessage());
        }

        redirect('modules/coffee-production/index.php');
    }

    if ($action === 'delete_batch') {
        Permission::require('production.delete');
        csrf_validate();

        $orderId = (int)($_POST['order_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM production_orders WHERE id = ?");
        $stmt->execute([$orderId]);

        log_audit('DELETE_PRODUCTION_BATCH', 'coffee-production', $orderId, "Menghapus batch produksi ID {$orderId}");
        flash_set('success', 'Batch produksi berhasil dihapus.');
        redirect('modules/coffee-production/index.php');
    }
}

// -------------------------------------------------------------
// QUERY DATA PRODUKSI & RINGKASAN
// -------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$roastFilter = trim($_GET['roast'] ?? '');

$query = "
    SELECT p.*, 
           raw.product_name AS raw_name, raw.unit AS raw_unit,
           outp.product_name AS output_name, outp.unit AS output_unit,
           e.full_name AS operator_name
    FROM production_orders p
    LEFT JOIN products raw ON p.raw_product_id = raw.id
    LEFT JOIN products outp ON p.output_product_id = outp.id
    LEFT JOIN employees e ON p.operator_id = e.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (p.batch_number LIKE ? OR outp.product_name LIKE ? OR p.notes LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($roastFilter !== '') {
    $query .= " AND p.roast_level = ?";
    $params[] = $roastFilter;
}

$query .= " ORDER BY p.production_date DESC, p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$batches = $stmt->fetchAll();

// KPI Metrics
$kpi = $db->query("
    SELECT 
        COUNT(*) AS total_batches,
        COALESCE(SUM(raw_weight_kg), 0) AS total_raw,
        COALESCE(SUM(output_weight_kg), 0) AS total_output,
        COALESCE(SUM(total_hpp), 0) AS total_hpp_val
    FROM production_orders
    WHERE status = 'SELESAI'
")->fetch();

$avgShrinkage = ($kpi['total_raw'] > 0) 
    ? ((($kpi['total_raw'] - $kpi['total_output']) / $kpi['total_raw']) * 100) 
    : 0;

// Ambil daftar produk untuk dropdown form
$rawProducts = $db->query("SELECT id, sku, product_name, current_stock, cost_price, unit FROM products WHERE is_raw_material = 1 OR sku LIKE '%GB%' ORDER BY product_name ASC")->fetchAll();
$outputProducts = $db->query("SELECT id, sku, product_name, current_stock, cost_price, unit FROM products WHERE is_raw_material = 0 ORDER BY product_name ASC")->fetchAll();
$operators = $db->query("SELECT id, full_name FROM employees WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll();

$pageTitle = 'Coffee Production (Roasting & Packaging)';
$currentModule = 'coffee-production';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Coffee Production' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. KPI SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Batch Selesai</div>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($kpi['total_batches']) ?></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3">
                    <i class="bi bi-gear-wide-connected fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Proses roasting & packaging</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Bahan Baku Olah (Green)</div>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($kpi['total_raw'], 1, ',', '.') ?> <small class="fs-6 text-muted">Kg</small></h3>
                </div>
                <div class="rounded-circle bg-info-subtle text-info p-3">
                    <i class="bi bi-box-seam fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Biji kopi mentah diproses</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Hasil Produk Jadi</div>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($kpi['total_output'], 1, ',', '.') ?> <small class="fs-6 text-muted">Kg / Pk</small></h3>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-cup-hot-fill fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Roasted beans & bubuk kopi</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Rata-rata Susut (Shrinkage)</div>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($avgShrinkage, 1) ?>%</h3>
                </div>
                <div class="rounded-circle bg-warning-subtle text-warning p-3">
                    <i class="bi bi-percent fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Penyusutan massa roasting normal: 15-20%</div>
        </div>
    </div>
</div>

<!-- 2. TOOLBAR & FILTER -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-7">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari nomor batch, produk hasil...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="roast" class="form-select form-select-sm">
                            <option value="">-- Semua Profil --</option>
                            <option value="LIGHT" <?= $roastFilter === 'LIGHT' ? 'selected' : '' ?>>Light Roast</option>
                            <option value="MEDIUM" <?= $roastFilter === 'MEDIUM' ? 'selected' : '' ?>>Medium Roast</option>
                            <option value="DARK" <?= $roastFilter === 'DARK' ? 'selected' : '' ?>>Dark Roast</option>
                            <option value="BUBUK" <?= $roastFilter === 'BUBUK' ? 'selected' : '' ?>>Kopi Bubuk</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($search !== '' || $roastFilter !== ''): ?>
                            <a href="<?= base_url('modules/coffee-production/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-md-auto text-end">
                <?php if (Permission::can('production.create')): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateBatch">
                        <i class="bi bi-plus-circle me-1"></i> Catat Batch Roasting & Produksi
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. TABEL DATA BATCH PRODUKSI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-fire"></i> Riwayat Batch Roasting & Pengemasan Kopi MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($batches) ?> Batch</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Batch</th>
                        <th>Tanggal</th>
                        <th>Bahan Baku (Green)</th>
                        <th>Hasil Jadi</th>
                        <th>Profil Roasting</th>
                        <th>Susut (Shrinkage)</th>
                        <th>Total HPP</th>
                        <th>HPP / Satuan</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada data batch produksi kopi. Klik tombol <strong>Catat Batch Roasting</strong> untuk memulai.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark">
                                    <?= e($b['batch_number']) ?>
                                    <div class="small text-muted font-monospace">Op: <?= e($b['operator_name'] ?? 'M. Rozikin') ?></div>
                                </td>
                                <td><?= date('d/m/Y', strtotime($b['production_date'])) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= number_format($b['raw_weight_kg'], 1, ',', '.') ?> Kg</span>
                                    <div class="small text-muted"><?= e($b['raw_name'] ?? 'Green Beans Robusta') ?></div>
                                </td>
                                <td>
                                    <span class="fw-bold text-success"><?= number_format($b['output_weight_kg'], 1, ',', '.') ?> <?= e($b['output_unit']) ?></span>
                                    <div class="small text-muted"><?= e($b['output_name'] ?? 'Roasted Beans') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <?= e($b['roast_level']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $shrinkKg = (float)$b['shrinkage_kg'];
                                    $shrinkPct = (float)$b['shrinkage_percent'];
                                    ?>
                                    <span class="text-danger fw-semibold">-<?= number_format($shrinkKg, 1, ',', '.') ?> Kg</span>
                                    <div class="small text-muted">(<?= number_format($shrinkPct, 1) ?>%)</div>
                                </td>
                                <td class="fw-bold"><?= format_rupiah($b['total_hpp']) ?></td>
                                <td class="fw-bold text-primary"><?= format_rupiah($b['hpp_per_kg']) ?></td>
                                <td>
                                    <span class="badge <?= $b['status'] === 'SELESAI' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= e($b['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if (Permission::can('production.delete')): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_batch">
                                            <input type="hidden" name="order_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus batch '<?= e($b['batch_number']) ?>'?" title="Hapus Batch">
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

<!-- 4. MODAL TAMBAH BATCH ROASTING & PRODUKSI -->
<div class="modal fade" id="modalCreateBatch" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_batch">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-fire me-2"></i>Catat Batch Roasting & Produksi Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Tanggal Produksi <span class="text-danger">*</span></label>
                        <input type="date" name="production_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Operator / Roaster</label>
                        <select name="operator_id" class="form-select">
                            <?php foreach ($operators as $op): ?>
                                <option value="<?= $op['id'] ?>"><?= e($op['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Profil Roasting</label>
                        <select name="roast_level" class="form-select">
                            <option value="MEDIUM" selected>Medium Roast</option>
                            <option value="DARK">Dark Roast</option>
                            <option value="LIGHT">Light Roast</option>
                            <option value="BUBUK">Kopi Bubuk Halus</option>
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-box-arrow-in-down"></i> 1. Bahan Baku Kopi Mentah (Input)</h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Produk Bahan Baku</label>
                        <select name="raw_product_id" id="raw_product_id" class="form-select" required>
                            <?php foreach ($rawProducts as $rp): ?>
                                <option value="<?= $rp['id'] ?>" data-price="<?= $rp['cost_price'] ?>">
                                    <?= e($rp['product_name']) ?> (Stok: <?= number_format($rp['current_stock'], 1) ?> <?= e($rp['unit']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Berat Mentah (Kg) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" name="raw_weight_kg" id="raw_weight_kg" class="form-control" placeholder="cth: 50" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Harga Modal / Kg (Rp)</label>
                        <input type="number" name="raw_cost_per_kg" id="raw_cost_per_kg" class="form-control" value="54000" required>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <h6 class="fw-bold text-success mb-0"><i class="bi bi-box-arrow-up"></i> 2. Hasil Jadi Roasting & Kemasan (Output)</h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Produk Hasil Jadi</label>
                        <select name="output_product_id" id="output_product_id" class="form-select" required>
                            <?php foreach ($outputProducts as $op): ?>
                                <option value="<?= $op['id'] ?>"><?= e($op['product_name']) ?> (<?= e($op['unit']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Berat Jadi (Kg/Pack) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" name="output_weight_kg" id="output_weight_kg" class="form-control" placeholder="cth: 41.5" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Biaya Operasional (Gas/Kemasan)</label>
                        <input type="number" name="operational_cost" id="operational_cost" class="form-control" value="50000" placeholder="Rp">
                    </div>

                    <!-- KALKULASI OTOMATIS REAL-TIME -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="text-muted small">Estimasi Susut Berat</div>
                                    <div class="fw-bold text-danger fs-5" id="calc_shrinkage">0 Kg (0%)</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">Total HPP Produksi</div>
                                    <div class="fw-bold text-dark fs-5" id="calc_total_hpp">Rp 0</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted small">HPP Satuan Jadi</div>
                                    <div class="fw-bold text-primary fs-5" id="calc_hpp_per_unit">Rp 0</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Catatan Tambahan / Batch Info</label>
                        <input type="text" name="notes" class="form-control" placeholder="Cth: Roasting mesin 10kg kap. Suhu akhir 205C">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status Produksi</label>
                        <select name="status" class="form-select">
                            <option value="SELESAI" selected>SELESAI (Potong/Tambah Stok)</option>
                            <option value="DALAM_PROSES">DALAM PROSES</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Batch Produksi</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rawWeight = document.getElementById('raw_weight_kg');
    const rawCost = document.getElementById('raw_cost_per_kg');
    const outWeight = document.getElementById('output_weight_kg');
    const opCost = document.getElementById('operational_cost');

    function calculateProduction() {
        const rw = parseFloat(rawWeight.value) || 0;
        const rc = parseFloat(rawCost.value) || 0;
        const ow = parseFloat(outWeight.value) || 0;
        const oc = parseFloat(opCost.value) || 0;

        const shrinkKg = Math.max(0, rw - ow);
        const shrinkPct = rw > 0 ? ((shrinkKg / rw) * 100).toFixed(1) : 0;

        const totalCost = (rw * rc) + oc;
        const hppPerUnit = ow > 0 ? (totalCost / ow) : 0;

        document.getElementById('calc_shrinkage').textContent = shrinkKg.toFixed(1) + ' Kg (' + shrinkPct + '%)';
        document.getElementById('calc_total_hpp').textContent = 'Rp ' + totalCost.toLocaleString('id-ID');
        document.getElementById('calc_hpp_per_unit').textContent = 'Rp ' + Math.round(hppPerUnit).toLocaleString('id-ID');
    }

    [rawWeight, rawCost, outWeight, opCost].forEach(el => {
        el.addEventListener('input', calculateProduction);
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
