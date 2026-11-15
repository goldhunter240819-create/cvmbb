<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Mitra Petani & Supplier Kopi
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
// PROSES AKSI FORM
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('coffee_trading.manage');

    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/coffee-trading/partners.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_farmer') {
        $fId     = !empty($_POST['farmer_id']) ? (int)$_POST['farmer_id'] : null;
        $code    = trim($_POST['code'] ?? '');
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $village = trim($_POST['village'] ?? 'Air Hitam');
        $group   = trim($_POST['farmer_group'] ?? '');
        $bank    = trim($_POST['bank_account'] ?? '');
        $notes   = trim($_POST['notes'] ?? '');

        if (empty($code) || empty($name)) {
            flash_set('danger', 'Kode dan Nama Petani wajib diisi.');
        } else {
            if ($fId) {
                $stmt = $db->prepare("
                    UPDATE farmers 
                    SET code = ?, name = ?, phone = ?, village = ?, farmer_group = ?, bank_account = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $name, $phone, $village, $group, $bank, $notes, $fId]);
                log_audit('UPDATE_FARMER', 'coffee-trading', $fId, "Memperbarui data petani: {$name}");
                flash_set('success', "Data mitra petani '{$name}' berhasil diperbarui.");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO farmers (code, name, phone, village, farmer_group, bank_account, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $name, $phone, $village, $group, $bank, $notes]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_FARMER', 'coffee-trading', $newId, "Menambahkan mitra petani baru: {$name}");
                flash_set('success', "Mitra petani baru '{$name}' berhasil didaftarkan.");
            }
        }
        redirect('modules/coffee-trading/partners.php');
    }

    if ($action === 'delete_farmer') {
        $fId = (int)($_POST['farmer_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM farmers WHERE id = ?");
        $stmt->execute([$fId]);
        log_audit('DELETE_FARMER', 'coffee-trading', $fId, "Menghapus mitra petani ID {$fId}");
        flash_set('success', 'Data mitra petani berhasil dihapus.');
        redirect('modules/coffee-trading/partners.php');
    }
}

// Search & Query
$search = trim($_GET['q'] ?? '');

$query = "
    SELECT f.*, 
           COUNT(p.id) AS total_deliveries,
           COALESCE(SUM(p.net_weight), 0) AS total_supplied_kg,
           COALESCE(SUM(p.grand_total), 0) AS total_paid
    FROM farmers f
    LEFT JOIN purchases p ON p.farmer_id = f.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (f.code LIKE ? OR f.name LIKE ? OR f.village LIKE ? OR f.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " GROUP BY f.id ORDER BY total_supplied_kg DESC, f.name ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$farmers = $stmt->fetchAll();

$maxIdStmt = $db->query("SELECT MAX(id) AS max_id FROM farmers");
$nextId = (int)$maxIdStmt->fetch()['max_id'] + 1;
$suggestedCode = 'PET-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

$pageTitle = 'Mitra Petani Kopi Binaan';
$currentModule = 'coffee-trading';
$pageCategory = 'DIVISI COFFEE TRADING & LOGISTIK';
$pageDesc = 'Direktori petani kopi Robusta, kelompok tani, desa penghasil, dan riwayat volume suplai biji kopi ke CV. MAJU BERKAH BERSAMA.';
$pageBreadcrumbs = [
    'Coffee Trading' => base_url('modules/coffee-trading/index.php'),
    'Mitra Petani' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/coffee-trading/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Penjualan Kopi
        </a>
        <a href="' . base_url('modules/coffee-trading/purchases.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-basket3 me-1"></i> Pembelian Kopi
        </a>' . 
        (Permission::can('coffee_trading.manage') ? '
        <button type="button" class="btn btn-primary text-white btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddFarmer">
            <i class="bi bi-person-plus-fill"></i> Tambah Mitra Petani
        </button>' : '') . '
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- FILTER & PENCARIAN -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari kode petani, nama, desa, nomor HP...">
                </div>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= base_url('modules/coffee-trading/partners.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL MITRA PETANI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-people-fill"></i> Direktori Mitra Petani Kopi MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($farmers) ?> Petani Terdaftar</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Kode</th>
                        <th>Nama Petani</th>
                        <th>Desa & Kelompok Tani</th>
                        <th>Kontak Telepon</th>
                        <th>Total Pasokan (Kg)</th>
                        <th>Total Nilai Pasokan</th>
                        <th>Transaksi</th>
                        <?php if (Permission::can('coffee_trading.manage')): ?>
                        <th class="text-end pe-4">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($farmers)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">Belum ada mitra petani terdaftar.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($farmers as $f): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= e($f['code']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($f['name']) ?></div>
                                    <small class="text-muted"><?= e($f['bank_account'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <div class="text-dark"><?= e($f['village'] ?: 'Air Hitam') ?></div>
                                    <small class="text-muted"><?= e($f['farmer_group'] ?: 'Mandiri') ?></small>
                                </td>
                                <td><?= e($f['phone'] ?: '-') ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border fs-6">
                                        <?= number_format($f['total_supplied_kg'], 1, ',', '.') ?> Kg
                                    </span>
                                </td>
                                <td class="fw-bold text-success"><?= format_rupiah($f['total_paid']) ?></td>
                                <td>
                                    <span class="badge bg-info-subtle text-info border"><?= $f['total_deliveries'] ?> Kali</span>
                                </td>
                                <?php if (Permission::can('coffee_trading.manage')): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-edit-farmer"
                                                data-id="<?= $f['id'] ?>"
                                                data-code="<?= e($f['code']) ?>"
                                                data-name="<?= e($f['name']) ?>"
                                                data-phone="<?= e($f['phone']) ?>"
                                                data-village="<?= e($f['village']) ?>"
                                                data-group="<?= e($f['farmer_group']) ?>"
                                                data-bank="<?= e($f['bank_account']) ?>"
                                                data-notes="<?= e($f['notes']) ?>"
                                                title="Edit Petani">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_farmer">
                                            <input type="hidden" name="farmer_id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus data mitra '<?= e($f['name']) ?>'?" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL TAMBAH / EDIT PETANI
     ======================================================== -->
<div class="modal fade" id="modalAddFarmer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_farmer">
                <input type="hidden" name="farmer_id" id="farmer_edit_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="farmerModalTitle">
                        <i class="bi bi-person-plus-fill me-1"></i> Form Data Mitra Petani
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Kode Petani <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="f_code" class="form-control" value="<?= e($suggestedCode) ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nama Petani / Kelompok <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f_name" class="form-control" placeholder="Nama petani..." required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Desa / Pekon</label>
                            <input type="text" name="village" id="f_village" class="form-control" value="Air Hitam">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Kelompok Tani</label>
                            <input type="text" name="farmer_group" id="f_group" class="form-control" placeholder="Contoh: Maju Lancar / Mandiri">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Nomor Telepon / WA</label>
                            <input type="text" name="phone" id="f_phone" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Rekening Bank (Untuk Pembayaran)</label>
                            <input type="text" name="bank_account" id="f_bank" class="form-control" placeholder="BRI / Mandiri No. Rek">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Catatan Kemitraan</label>
                        <textarea name="notes" id="f_notes" class="form-control form-control-sm" rows="2" placeholder="Luas kebun, jenis panen, dsb..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Simpan Mitra Petani</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fModal = new bootstrap.Modal(document.getElementById('modalAddFarmer'));

    document.querySelectorAll('.btn-edit-farmer').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('farmerModalTitle').innerText = 'Edit Petani: ' + this.dataset.name;
            document.getElementById('farmer_edit_id').value = this.dataset.id;
            document.getElementById('f_code').value = this.dataset.code;
            document.getElementById('f_name').value = this.dataset.name;
            document.getElementById('f_phone').value = this.dataset.phone || '';
            document.getElementById('f_village').value = this.dataset.village || 'Air Hitam';
            document.getElementById('f_group').value = this.dataset.group || '';
            document.getElementById('f_bank').value = this.dataset.bank || '';
            document.getElementById('f_notes').value = this.dataset.notes || '';
            fModal.show();
        });
    });

    document.getElementById('modalAddFarmer').addEventListener('hidden.bs.modal', function () {
        document.getElementById('farmerModalTitle').innerText = 'Form Data Mitra Petani';
        document.getElementById('farmer_edit_id').value = '';
        document.getElementById('f_code').value = '<?= e($suggestedCode) ?>';
        document.getElementById('f_name').value = '';
        document.getElementById('f_phone').value = '';
        document.getElementById('f_village').value = 'Air Hitam';
        document.getElementById('f_group').value = '';
        document.getElementById('f_bank').value = '';
        document.getElementById('f_notes').value = '';
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
