<?php
/**
 * MBB MOBILE APK - Produksi Kopi Bubuk
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Produksi Bubuk';
$currentApkNav   = 'coffee';

require_once __DIR__ . '/layout/header.php';

// Handle Tambah Batch Produksi dari Mobile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_production') {
    $batchNo    = 'PRD-' . date('Ymd') . '-' . rand(100, 999);
    $prodDate   = $_POST['prod_date'] ?? date('Y-m-d');
    $rawProdId  = (int)($_POST['raw_product_id'] ?? 1);
    $outProdId  = (int)($_POST['output_product_id'] ?? 2);
    $rawWeight  = (float)($_POST['raw_weight'] ?? 0);
    $outWeight  = (float)($_POST['output_weight'] ?? 0);
    $rawCost    = 60000; // Standar HPP Green Bean
    $totalRaw   = $rawWeight * $rawCost;
    $opsCost    = (float)($_POST['ops_cost'] ?? 0);
    $totalHpp   = $totalRaw + $opsCost;
    $hppPerKg   = $outWeight > 0 ? ($totalHpp / $outWeight) : 0;
    $roastLevel = $_POST['roast_level'] ?? 'BUBUK';
    $notes      = trim($_POST['notes'] ?? '');

    try {
        $stmtIns = $db->prepare("
            INSERT INTO production_orders 
            (batch_number, production_date, operator_id, raw_product_id, raw_weight_kg, raw_cost_per_kg, total_raw_cost, operational_cost, output_product_id, output_weight_kg, total_hpp, hpp_per_kg, roast_level, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SELESAI', ?)
        ");
        $stmtIns->execute([$batchNo, $prodDate, $user['id'], $rawProdId, $rawWeight, $rawCost, $totalRaw, $opsCost, $outProdId, $outWeight, $totalHpp, $hppPerKg, $roastLevel, $notes]);
        flash_set('success', 'Batch produksi ' . $batchNo . ' berhasil disimpan!');
        redirect('apk/production.php');
    } catch (Exception $e) {
        $errorMsg = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

// Ambil List Batch Produksi
$batches = $db->query("
    SELECT po.*, 
           rp.product_name AS raw_name, 
           op.product_name AS output_name
    FROM production_orders po
    LEFT JOIN products rp ON po.raw_product_id = rp.id
    LEFT JOIN products op ON po.output_product_id = op.id
    ORDER BY po.id DESC LIMIT 15
")->fetchAll();

$products = $db->query("SELECT id, product_name, product_code FROM products WHERE is_active = 1 ORDER BY product_name ASC")->fetchAll();
?>

<div class="p-3">
    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="fw-bold text-dark" style="font-size: 0.95rem;">Lini Produksi Kopi</div>
            <div class="text-muted small" style="font-size: 0.72rem;">Pengolahan Green Bean ke Bubuk Kemasan</div>
        </div>
        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddBatch">
            <i class="bi bi-plus-circle me-1"></i>Batch Baru
        </button>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Batch Cards -->
    <?php if (empty($batches)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-gear-wide-connected display-4 d-block mb-2 text-secondary opacity-50"></i>
            <p class="small">Belum ada batch produksi tercatat.</p>
        </div>
    <?php else: ?>
        <?php foreach ($batches as $b): ?>
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge bg-primary-subtle text-primary font-monospace"><?= e($b['batch_number']) ?></span>
                        <span class="badge <?= $b['status'] === 'SELESAI' ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size: 0.65rem;">
                            <?= e($b['status']) ?>
                        </span>
                    </div>

                    <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">
                        <?= e($b['output_name'] ?: 'Kopi Bubuk Robusta MBB') ?>
                    </div>
                    <div class="text-muted small mb-2" style="font-size: 0.7rem;">
                        Tgl: <?= date('d M Y', strtotime($b['production_date'])) ?> &bull; Profil: <span class="fw-semibold text-dark"><?= e($b['roast_level']) ?></span>
                    </div>

                    <div class="row g-2 p-2 bg-light rounded-3 mb-2 text-center" style="font-size: 0.72rem;">
                        <div class="col-4 border-end">
                            <span class="text-muted d-block">Bahan Baku</span>
                            <span class="fw-bold text-dark"><?= number_format($b['raw_weight_kg'], 1) ?> Kg</span>
                        </div>
                        <div class="col-4 border-end">
                            <span class="text-muted d-block">Hasil Jadi</span>
                            <span class="fw-bold text-success"><?= number_format($b['output_weight_kg'], 1) ?> Kg</span>
                        </div>
                        <div class="col-4">
                            <span class="text-muted d-block">Susut</span>
                            <span class="fw-bold text-danger"><?= number_format($b['shrinkage_percent'] ?? 0, 1) ?>%</span>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-1" style="font-size: 0.75rem;">
                        <span class="text-muted">HPP per Kg:</span>
                        <span class="fw-bold text-primary"><?= format_rupiah($b['hpp_per_kg']) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Batch Baru -->
<div class="modal fade" id="modalAddBatch" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <input type="hidden" name="action" value="add_production">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-gear-fill text-primary me-2"></i>Catat Produksi Kopi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Tanggal Produksi</label>
                    <input type="date" name="prod_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Bahan Baku (Green Bean)</label>
                    <select name="raw_product_id" class="form-select form-select-sm">
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Berat Mentah (Kg)</label>
                        <input type="number" step="0.1" name="raw_weight" class="form-control form-control-sm" placeholder="Contoh: 100" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Hasil Jadi (Kg)</label>
                        <input type="number" step="0.1" name="output_weight" class="form-control form-control-sm" placeholder="Contoh: 82" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Profil Roasting</label>
                        <select name="roast_level" class="form-select form-select-sm">
                            <option value="BUBUK">BUBUK HALUS</option>
                            <option value="DARK">DARK ROAST</option>
                            <option value="MEDIUM">MEDIUM ROAST</option>
                            <option value="LIGHT">LIGHT ROAST</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Biaya Ops (Rp)</label>
                        <input type="number" name="ops_cost" class="form-control form-control-sm" value="50000">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Catatan / No. Mesin</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Mesin Roaster MBB 15kg Air Hitam">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary px-3">Simpan Batch</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
