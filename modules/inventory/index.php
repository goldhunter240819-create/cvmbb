<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Inventaris & Manajemen Aset Perusahaan
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('inventory.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// POST HANDLER: INVENTARIS ASET
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_asset') {
        Permission::require('inventory.create');
        csrf_validate();

        $assetCode       = 'AST-' . date('Ym') . '-' . strtoupper(substr(uniqid(), -4));
        $assetName       = sanitize($_POST['asset_name'] ?? '');
        $divisionId      = (int)($_POST['division_id'] ?? 0);
        $category        = sanitize($_POST['category'] ?? 'Peralatan');
        $purchaseDate    = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
        $acquisitionCost = (float)($_POST['acquisition_cost'] ?? 0);
        $assetCondition  = sanitize($_POST['asset_condition'] ?? 'BAIK');
        $location        = sanitize($_POST['location'] ?? 'Kantor / Gudang MBB');
        $personInCharge  = sanitize($_POST['person_in_charge'] ?? '');
        $notes           = sanitize($_POST['notes'] ?? '');

        if ($assetName === '') {
            flash_set('danger', 'Nama barang inventaris wajib diisi.');
            redirect('modules/inventory/index.php');
        }

        $stmt = $db->prepare("
            INSERT INTO inventory_assets (
                asset_code, asset_name, division_id, category, purchase_date,
                acquisition_cost, asset_condition, location, person_in_charge, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $assetCode, $assetName, $divisionId > 0 ? $divisionId : null,
            $category, $purchaseDate, $acquisitionCost, $assetCondition,
            $location, $personInCharge, $notes
        ]);
        $assetId = $db->lastInsertId();

        log_audit('CREATE_INVENTORY_ASSET', 'inventory', $assetId, "Mencatat aset baru {$assetCode} - {$assetName} senilai Rp " . number_format($acquisitionCost));
        flash_set('success', "Aset '{$assetName}' berhasil ditambahkan ke inventaris.");
        redirect('modules/inventory/index.php');
    }

    if ($action === 'update_asset') {
        Permission::require('inventory.edit');
        csrf_validate();

        $assetId         = (int)($_POST['asset_id'] ?? 0);
        $assetName       = sanitize($_POST['asset_name'] ?? '');
        $divisionId      = (int)($_POST['division_id'] ?? 0);
        $category        = sanitize($_POST['category'] ?? 'Peralatan');
        $acquisitionCost = (float)($_POST['acquisition_cost'] ?? 0);
        $assetCondition  = sanitize($_POST['asset_condition'] ?? 'BAIK');
        $location        = sanitize($_POST['location'] ?? '');
        $personInCharge  = sanitize($_POST['person_in_charge'] ?? '');
        $notes           = sanitize($_POST['notes'] ?? '');

        $stmt = $db->prepare("
            UPDATE inventory_assets SET
                asset_name = ?,
                division_id = ?,
                category = ?,
                acquisition_cost = ?,
                asset_condition = ?,
                location = ?,
                person_in_charge = ?,
                notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $assetName, $divisionId > 0 ? $divisionId : null, $category,
            $acquisitionCost, $assetCondition, $location, $personInCharge, $notes, $assetId
        ]);

        log_audit('UPDATE_INVENTORY_ASSET', 'inventory', $assetId, "Update data aset inventaris ID {$assetId}");
        flash_set('success', 'Data aset berhasil diperbarui.');
        redirect('modules/inventory/index.php');
    }

    if ($action === 'delete_asset') {
        Permission::require('inventory.delete');
        csrf_validate();

        $assetId = (int)($_POST['asset_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM inventory_assets WHERE id = ?");
        $stmt->execute([$assetId]);

        log_audit('DELETE_INVENTORY_ASSET', 'inventory', $assetId, "Menghapus aset inventaris ID {$assetId}");
        flash_set('success', 'Aset inventaris berhasil dihapus.');
        redirect('modules/inventory/index.php');
    }
}

// -------------------------------------------------------------
// FILTER & QUERY ASET
// -------------------------------------------------------------
$search         = trim($_GET['q'] ?? '');
$divisionFilter = (int)($_GET['division'] ?? 0);
$condFilter     = trim($_GET['condition'] ?? '');

$query = "
    SELECT a.*, d.division_name
    FROM inventory_assets a
    LEFT JOIN divisions d ON a.division_id = d.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (a.asset_code LIKE ? OR a.asset_name LIKE ? OR a.location LIKE ? OR a.person_in_charge LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($divisionFilter > 0) {
    $query .= " AND a.division_id = ?";
    $params[] = $divisionFilter;
}

if ($condFilter !== '') {
    $query .= " AND a.asset_condition = ?";
    $params[] = $condFilter;
}

$query .= " ORDER BY a.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$assets = $stmt->fetchAll();

// KPI Metrics
$kpi = $db->query("
    SELECT 
        COUNT(*) AS total_items,
        COALESCE(SUM(acquisition_cost), 0) AS total_val,
        COALESCE(SUM(CASE WHEN asset_condition = 'BAIK' THEN 1 ELSE 0 END), 0) AS good_cond,
        COALESCE(SUM(CASE WHEN asset_condition IN ('RUSAK_RINGAN', 'RUSAK_BERAT') THEN 1 ELSE 0 END), 0) AS damaged_cond
    FROM inventory_assets
")->fetch();

$divisions = $db->query("SELECT id, division_name FROM divisions WHERE is_active = 1 ORDER BY division_name ASC")->fetchAll();

$pageTitle = 'Inventaris & Aset Perusahaan';
$currentModule = 'inventory';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Inventaris & Aset' => null
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
                    <div class="text-muted small fw-semibold text-uppercase">Total Unit Aset</div>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($kpi['total_items']) ?> <small class="fs-6 text-muted">Item</small></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3">
                    <i class="bi bi-boxes fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Aset terdaftar di seluruh divisi</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Nilai Perolehan</div>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= format_rupiah($kpi['total_val']) ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-currency-dollar fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Nilai buku aset modal MBB</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Kondisi Baik (Siap Pakai)</div>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($kpi['good_cond']) ?></h3>
                </div>
                <div class="rounded-circle bg-info-subtle text-info p-3">
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Mesin & perlengkapan operasional</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Perlu Perbaikan / Rusak</div>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($kpi['damaged_cond']) ?></h3>
                </div>
                <div class="rounded-circle bg-danger-subtle text-danger p-3">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Perlu servis atau penggantian</div>
        </div>
    </div>
</div>

<!-- 2. TOOLBAR & FILTER -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-8">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari kode, nama aset, penanggung jawab...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="division" class="form-select form-select-sm">
                            <option value="0">-- Semua Divisi --</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $divisionFilter === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['division_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="condition" class="form-select form-select-sm">
                            <option value="">-- Kondisi --</option>
                            <option value="BAIK" <?= $condFilter === 'BAIK' ? 'selected' : '' ?>>BAIK</option>
                            <option value="RUSAK_RINGAN" <?= $condFilter === 'RUSAK_RINGAN' ? 'selected' : '' ?>>Rusak Ringan</option>
                            <option value="RUSAK_BERAT" <?= $condFilter === 'RUSAK_BERAT' ? 'selected' : '' ?>>Rusak Berat</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($search !== '' || $divisionFilter > 0 || $condFilter !== ''): ?>
                            <a href="<?= base_url('modules/inventory/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-md-auto text-end d-flex gap-2">
                <a href="<?= base_url('modules/inventory/print_assets.php') ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i> Cetak Daftar Aset
                </a>
                <?php if (Permission::can('inventory.create')): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateAsset">
                        <i class="bi bi-plus-circle me-1"></i> Tambah Aset Baru
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. TABEL DATA INVENTARIS ASET -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-card-checklist"></i> Master Inventaris & Aset Operasional CV. MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($assets) ?> Item</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Kode Aset</th>
                        <th>Nama Barang & Kategori</th>
                        <th>Divisi Pemilik</th>
                        <th>Tgl Beli</th>
                        <th>Harga Perolehan</th>
                        <th>Kondisi</th>
                        <th>Lokasi & Penanggung Jawab</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada aset terdaftar. Klik tombol <strong>Tambah Aset Baru</strong> untuk mencatat aset perusahaan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assets as $a): ?>
                            <tr>
                                <td class="ps-4 fw-bold font-monospace text-dark">
                                    <?= e($a['asset_code']) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($a['asset_name']) ?></div>
                                    <div class="small text-muted"><?= e($a['category'] ?? 'Peralatan') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border">
                                        <?= e($a['division_name'] ?? 'Umum / Pusat') ?>
                                    </span>
                                </td>
                                <td><?= !empty($a['purchase_date']) ? date('d/m/Y', strtotime($a['purchase_date'])) : '-' ?></td>
                                <td class="fw-bold text-dark"><?= format_rupiah($a['acquisition_cost']) ?></td>
                                <td>
                                    <?php
                                    $cBadge = match($a['asset_condition']) {
                                        'BAIK' => 'bg-success-subtle text-success border border-success',
                                        'RUSAK_RINGAN' => 'bg-warning-subtle text-warning border border-warning',
                                        'RUSAK_BERAT' => 'bg-danger-subtle text-danger border border-danger',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $cBadge ?>"><?= e($a['asset_condition']) ?></span>
                                </td>
                                <td>
                                    <div class="text-dark small"><i class="bi bi-geo-alt me-1"></i><?= e($a['location'] ?? '-') ?></div>
                                    <div class="small text-muted"><i class="bi bi-person me-1"></i>PJ: <?= e($a['person_in_charge'] ?? '-') ?></div>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-edit-asset"
                                                data-id="<?= $a['id'] ?>"
                                                data-name="<?= e($a['asset_name']) ?>"
                                                data-div="<?= $a['division_id'] ?>"
                                                data-cat="<?= e($a['category']) ?>"
                                                data-cost="<?= $a['acquisition_cost'] ?>"
                                                data-cond="<?= e($a['asset_condition']) ?>"
                                                data-loc="<?= e($a['location']) ?>"
                                                data-pic="<?= e($a['person_in_charge']) ?>"
                                                data-notes="<?= e($a['notes'] ?? '') ?>"
                                                title="Edit Aset">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if (Permission::can('inventory.delete')): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_asset">
                                                <input type="hidden" name="asset_id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                        data-confirm="Hapus aset '<?= e($a['asset_name']) ?>'?" title="Hapus">
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

<!-- 4. MODAL TAMBAH ASET -->
<div class="modal fade" id="modalCreateAsset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_asset">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-boxes me-2"></i>Tambah Aset Inventaris Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Nama Barang / Aset <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" class="form-control" placeholder="Cth: Mesin Roasting Kopi Kapasitas 10 Kg" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kategori Aset</label>
                        <select name="category" class="form-select">
                            <option value="Mesin & Peralatan">Mesin & Peralatan</option>
                            <option value="Elektronik & Audio">Elektronik & Audio</option>
                            <option value="Peralatan Dekorasi">Peralatan Dekorasi</option>
                            <option value="Kendaraan / Armada">Kendaraan / Armada</option>
                            <option value="Perlengkapan Kantor">Perlengkapan Kantor</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Divisi Pengguna</label>
                        <select name="division_id" class="form-select">
                            <option value="0">-- Aset Umum Perusahaan --</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tanggal Perolehan / Pembelian</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harga Perolehan (Rp)</label>
                        <input type="number" name="acquisition_cost" class="form-control" value="0" placeholder="Rp">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Kondisi Aset</label>
                        <select name="asset_condition" class="form-select">
                            <option value="BAIK" selected>BAIK (Siap Digunakan)</option>
                            <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                            <option value="RUSAK_BERAT">RUSAK BERAT</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Lokasi Penyimpanan</label>
                        <input type="text" name="location" class="form-control" placeholder="Cth: Gudang Produksi Kopi / Studio Sound">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Penanggung Jawab Aset</label>
                        <input type="text" name="person_in_charge" class="form-control" placeholder="Cth: Ahmad Sobirin">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Catatan Tambahan / Nomor Seri / Spesifikasi</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Spesifikasi teknis, daya listrik, dll"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Aset</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL EDIT ASET -->
<div class="modal fade" id="modalEditAsset" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_asset">
            <input type="hidden" name="asset_id" id="edit_asset_id">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="edit_asset_title">Edit Data Aset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Nama Barang / Aset <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Kategori Aset</label>
                        <input type="text" name="category" id="edit_asset_cat" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Divisi Pengguna</label>
                        <select name="division_id" id="edit_asset_div" class="form-select">
                            <option value="0">-- Aset Umum Perusahaan --</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Harga Perolehan (Rp)</label>
                        <input type="number" name="acquisition_cost" id="edit_asset_cost" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Kondisi Aset</label>
                        <select name="asset_condition" id="edit_asset_cond" class="form-select">
                            <option value="BAIK">BAIK</option>
                            <option value="RUSAK_RINGAN">RUSAK RINGAN</option>
                            <option value="RUSAK_BERAT">RUSAK BERAT</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lokasi</label>
                        <input type="text" name="location" id="edit_asset_loc" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Penanggung Jawab</label>
                        <input type="text" name="person_in_charge" id="edit_asset_pic" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <textarea name="notes" id="edit_asset_notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-asset').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const div = this.getAttribute('data-div');
            const cat = this.getAttribute('data-cat');
            const cost = this.getAttribute('data-cost');
            const cond = this.getAttribute('data-cond');
            const loc = this.getAttribute('data-loc');
            const pic = this.getAttribute('data-pic');
            const notes = this.getAttribute('data-notes');

            document.getElementById('edit_asset_id').value = id;
            document.getElementById('edit_asset_title').textContent = 'Edit: ' + name;
            document.getElementById('edit_asset_name').value = name;
            document.getElementById('edit_asset_cat').value = cat;
            document.getElementById('edit_asset_div').value = div || 0;
            document.getElementById('edit_asset_cost').value = cost;
            document.getElementById('edit_asset_cond').value = cond;
            document.getElementById('edit_asset_loc').value = loc;
            document.getElementById('edit_asset_pic').value = pic;
            document.getElementById('edit_asset_notes').value = notes;

            const modal = new bootstrap.Modal(document.getElementById('modalEditAsset'));
            modal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
