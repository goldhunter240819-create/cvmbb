<?php
/**
 * MBB MOBILE APK - Inventaris & Aset Perusahaan
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Aset Mesin';
$currentApkNav   = 'home';

require_once __DIR__ . '/layout/header.php';

// Handle Tambah Aset dari Mobile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_asset') {
    $astCode   = 'AST-' . date('Ym') . '-' . rand(100, 999);
    $astName   = trim($_POST['asset_name'] ?? '');
    $category  = trim($_POST['category'] ?? 'Mesin & Peralatan');
    $cost      = (float)($_POST['cost'] ?? 0);
    $condition = $_POST['condition'] ?? 'BAIK';
    $location  = trim($_POST['location'] ?? 'Gudang Air Hitam');
    $notes     = trim($_POST['notes'] ?? '');

    try {
        $stmtIns = $db->prepare("
            INSERT INTO inventory_assets 
            (asset_code, asset_name, category, purchase_date, acquisition_cost, asset_condition, location, notes)
            VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)
        ");
        $stmtIns->execute([$astCode, $astName, $category, $cost, $condition, $location, $notes]);
        flash_set('success', 'Aset baru ' . $astName . ' berhasil didaftarkan!');
        redirect('apk/inventory.php');
    } catch (Exception $e) {
        $errorMsg = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

// Ambil List Aset
$assets = $db->query("SELECT * FROM inventory_assets ORDER BY acquisition_cost DESC")->fetchAll();
$totalAssetVal = array_sum(array_column($assets, 'acquisition_cost'));
?>

<div class="p-3">
    <!-- Summary Header -->
    <div class="card border-0 text-white p-3 mb-3 shadow-sm" style="background: linear-gradient(135deg, #334155 0%, #475569 100%); border-radius: 18px;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-white-50 small">Total Aset & Inventaris Mesin</span>
            <span class="badge bg-white text-dark"><?= count($assets) ?> Unit</span>
        </div>
        <div class="text-white-50" style="font-size: 0.72rem;">NILAI PEROLEHAN BUKU</div>
        <h4 class="fw-bold mb-1"><?= format_rupiah($totalAssetVal) ?></h4>
        <div class="text-white-50 small" style="font-size: 0.72rem;">Lokasi Gudang & Lini Usaha Air Hitam</div>
    </div>

    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-bold text-dark" style="font-size: 0.88rem;">Daftar Peralatan & Mesin</span>
        <button class="btn btn-sm btn-dark rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddAsset">
            <i class="bi bi-plus-circle me-1"></i>Aset Baru
        </button>
    </div>

    <!-- Assets Cards -->
    <?php foreach ($assets as $ast): ?>
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-secondary-subtle text-secondary font-monospace" style="font-size: 0.68rem;"><?= e($ast['asset_code']) ?></span>
                    <span class="badge <?= $ast['asset_condition'] === 'BAIK' ? 'bg-success' : 'bg-warning text-dark' ?>" style="font-size: 0.65rem;">
                        <?= e($ast['asset_condition']) ?>
                    </span>
                </div>
                <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">
                    <?= e($ast['asset_name']) ?>
                </div>
                <div class="text-muted small mb-2" style="font-size: 0.72rem;">
                    <i class="bi bi-geo-alt-fill text-muted me-1"></i><?= e($ast['location'] ?: 'Gudang Air Hitam') ?> &bull; Kategori: <b><?= e($ast['category']) ?></b>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.75rem;">
                    <span class="text-muted">Nilai Beli / Aset:</span>
                    <span class="fw-bold text-primary"><?= format_rupiah($ast['acquisition_cost']) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Tambah Aset Baru -->
<div class="modal fade" id="modalAddAsset" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <input type="hidden" name="action" value="add_asset">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-box-seam-fill text-dark me-2"></i>Daftarkan Aset Baru</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Nama Barang / Mesin</label>
                    <input type="text" name="asset_name" class="form-control form-control-sm" placeholder="Contoh: Mesin Grinder Mahlkönig EK43" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Kategori</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="Mesin & Peralatan">Mesin & Peralatan</option>
                            <option value="Elektronik & Audio">Elektronik & Audio</option>
                            <option value="Peralatan Dekorasi">Peralatan Dekorasi</option>
                            <option value="Perlengkapan Kantor">Perlengkapan Kantor</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Kondisi</label>
                        <select name="condition" class="form-select form-select-sm">
                            <option value="BAIK">BAIK (Siap Pakai)</option>
                            <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                            <option value="RUSAK_BERAT">RUSAK BERAT</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Nilai Beli (Rp)</label>
                        <input type="number" name="cost" class="form-control form-control-sm" placeholder="15000000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Lokasi Penempatan</label>
                        <input type="text" name="location" class="form-control form-control-sm" value="Gudang Roasting Air Hitam" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Keterangan / Nomor Seri</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="No. Seri / Garansi / Manual">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-dark px-3">Simpan Aset</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
