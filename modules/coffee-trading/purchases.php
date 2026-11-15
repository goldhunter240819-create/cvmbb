<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Pembelian Biji Kopi dari Petani & Supplier
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
// PROSES FORM PEMBELIAN
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('coffee_trading.manage');

    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/coffee-trading/purchases.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_purchase') {
        $farmerName   = trim($_POST['farmer_name'] ?? '');
        $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');
        $grossWeight  = floatval($_POST['gross_weight'] ?? 0);
        $deduction    = floatval($_POST['deduction_kg'] ?? 0);
        $netWeight    = $grossWeight - $deduction;
        $pricePerKg   = floatval($_POST['price_per_kg'] ?? 0);
        $grandTotal   = $netWeight * $pricePerKg;
        $notes        = trim($_POST['notes'] ?? '');

        if (empty($farmerName) || $netWeight <= 0 || $pricePerKg <= 0) {
            flash_set('danger', 'Nama Petani, Berat Timbangan, dan Harga per Kg wajib diisi.');
        } else {
            // Cek atau simpan Petani
            $fStmt = $db->prepare("SELECT id FROM farmers WHERE name = ? LIMIT 1");
            $fStmt->execute([$farmerName]);
            $farmerId = $fStmt->fetchColumn();
            if (!$farmerId) {
                $code = 'PET-' . date('YmdHis');
                $insF = $db->prepare("INSERT INTO farmers (code, name, village, sub_district) VALUES (?, ?, 'Air Hitam', 'Lampung Barat')");
                $insF->execute([$code, $farmerName]);
                $farmerId = $db->lastInsertId();
            }

            // Nomor Nota Timbangan Beli
            $countStmt = $db->query("SELECT COUNT(id) FROM purchases");
            $nextNo = (int)$countStmt->fetchColumn() + 1;
            $invNumber = 'PB-MBB-' . date('Ymd', strtotime($purchaseDate)) . '-' . str_pad($nextNo, 3, '0', STR_PAD_LEFT);

            $stmt = $db->prepare("
                INSERT INTO purchases 
                (invoice_number, purchase_date, partner_type, farmer_id, warehouse_id, gross_weight, tare_weight, net_weight, price_per_kg, grand_total, paid_amount, payment_status, notes, created_by)
                VALUES (?, ?, 'FARMER', ?, 1, ?, ?, ?, ?, ?, ?, 'LUNAS', ?, ?)
            ");
            $stmt->execute([$invNumber, $purchaseDate, $farmerId, $grossWeight, $deduction, $netWeight, $pricePerKg, $grandTotal, $grandTotal, $notes, Auth::id()]);
            $purId = $db->lastInsertId();

            // Update stok produk kopi
            $updStock = $db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = 1");
            $updStock->execute([$netWeight]);

            log_audit('CREATE_COFFEE_PURCHASE', 'coffee-trading', $purId, "Mencatat pembelian kopi {$invNumber} dari petani {$farmerName}: {$netWeight} Kg @Rp " . number_format($pricePerKg) . " = Rp " . number_format($grandTotal));
            flash_set('success', "Nota pembelian {$invNumber} dari {$farmerName} senilai " . format_rupiah($grandTotal) . " berhasil dicatat.");
        }
        redirect('modules/coffee-trading/purchases.php');
    }

    if ($action === 'delete_purchase') {
        $purId = (int)($_POST['purchase_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM purchases WHERE id = ?");
        $stmt->execute([$purId]);
        log_audit('DELETE_COFFEE_PURCHASE', 'coffee-trading', $purId, "Menghapus nota pembelian kopi ID {$purId}");
        flash_set('success', 'Nota pembelian berhasil dihapus.');
        redirect('modules/coffee-trading/purchases.php');
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');

$query = "
    SELECT p.*, f.name AS farmer_name, f.village
    FROM purchases p
    LEFT JOIN farmers f ON p.farmer_id = f.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (p.invoice_number LIKE ? OR f.name LIKE ? OR p.notes LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " ORDER BY p.purchase_date DESC, p.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

// Total Summary
$sumPurchases = $db->query("
    SELECT 
        COALESCE(SUM(net_weight), 0) AS total_kg,
        COALESCE(SUM(grand_total), 0) AS total_amount,
        COUNT(id) AS total_trx
    FROM purchases
")->fetch();

$farmers = $db->query("SELECT name FROM farmers ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Pembelian Kopi dari Petani';
$currentModule = 'coffee-trading';
$pageCategory = 'DIVISI COFFEE TRADING & LOGISTIK';
$pageDesc = 'Pencatatan pembelian harian biji kopi mentah hasil panen petani Lampung Barat, timbangan basah/kering, dan total pencairan dana.';
$pageBreadcrumbs = [
    'Coffee Trading' => base_url('modules/coffee-trading/index.php'),
    'Pembelian Petani' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/coffee-trading/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Penjualan Kopi
        </a>
        <a href="' . base_url('modules/coffee-trading/partners.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-people me-1"></i> Data Petani
        </a>' . 
        (Permission::can('coffee_trading.manage') ? '
        <button type="button" class="btn btn-primary text-white btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddPurchase">
            <i class="bi bi-plus-circle-fill"></i> Timbang Pembelian Baru
        </button>' : '') . '
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card primary">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Kopi Masuk</div>
                    <div class="stat-value text-primary"><?= number_format($sumPurchases['total_kg'], 1, ',', '.') ?> <span class="fs-6 text-muted">Kg</span></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-basket3-fill"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Hasil pembelian dari mitra petani lokal
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card danger">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Total Dana Pembelian</div>
                    <div class="stat-value text-danger"><?= format_rupiah($sumPurchases['total_amount']) ?></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Total modal terserap untuk beli biji kopi
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stat-label">Rata-rata Harga Beli</div>
                    <?php 
                    $avgPrice = $sumPurchases['total_kg'] > 0 ? ($sumPurchases['total_amount'] / $sumPurchases['total_kg']) : 0;
                    ?>
                    <div class="stat-value text-success"><?= format_rupiah($avgPrice) ?> <span class="fs-6 text-muted">/Kg</span></div>
                </div>
                <div class="stat-card-icon">
                    <i class="bi bi-tags-fill"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                Berdasarkan <?= number_format($sumPurchases['total_trx']) ?> kali transaksi timbang
            </div>
        </div>
    </div>
</div>

<!-- 2. FILTER & PENCARIAN -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari nomor nota, nama petani...">
                </div>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= base_url('modules/coffee-trading/purchases.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- 3. TABEL DATA PEMBELIAN PETANI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-basket3-fill"></i> Buku Timbangan & Nota Pembelian Kopi
        </h6>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($purchases) ?> Transaksi</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Nota</th>
                        <th>Tanggal Beli</th>
                        <th>Nama Petani</th>
                        <th>Desa / Lokasi</th>
                        <th>Timbangan Bersih</th>
                        <th>Harga per Kg</th>
                        <th>Total Dibayarkan</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">Belum ada nota pembelian tercatat.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= e($p['invoice_number']) ?></td>
                                <td><?= format_tanggal($p['purchase_date']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($p['farmer_name'] ?: 'Petani') ?></div>
                                    <small class="text-muted"><?= e($p['notes'] ?: '-') ?></small>
                                </td>
                                <td><small class="text-muted"><?= e($p['village'] ?: 'Air Hitam') ?></small></td>
                                <td>
                                    <span class="badge bg-light text-dark border fs-6">
                                        <?= number_format($p['net_weight'], 1, ',', '.') ?> Kg
                                    </span>
                                </td>
                                <td class="fw-semibold text-dark"><?= format_rupiah($p['price_per_kg']) ?></td>
                                <td class="fw-bold text-danger"><?= format_rupiah($p['grand_total']) ?></td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <?= e($p['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (Permission::can('coffee_trading.manage')): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_purchase">
                                            <input type="hidden" name="purchase_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus nota pembelian '<?= e($p['invoice_number']) ?>'?" title="Hapus">
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
     MODAL TIMBANG PEMBELIAN KOPI DARI PETANI
     ======================================================== -->
<div class="modal fade" id="modalAddPurchase" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_purchase">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-basket3-fill me-1"></i> Form Timbangan Pembelian Petani
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Petani / Penjual <span class="text-danger">*</span></label>
                        <input type="text" name="farmer_name" class="form-control" list="farmerList" placeholder="Ketik nama petani (Budi Santoso, Agus, dll)..." required>
                        <datalist id="farmerList">
                            <?php foreach ($farmers as $f): ?>
                                <option value="<?= e($f) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Pembelian <span class="text-danger">*</span></label>
                            <input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Harga per Kg (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="price_per_kg" id="pur_price" class="form-control" value="54000" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Timbangan Kotor (Kg) <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" name="gross_weight" id="pur_gross" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Potongan Tara / Sampah (Kg)</label>
                            <input type="number" step="0.1" name="deduction_kg" id="pur_deduct" value="0" class="form-control">
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded border mb-3 text-center">
                        <small class="text-muted text-uppercase fw-bold">Estimasi Total Pembayaran:</small>
                        <h4 class="fw-bold text-success my-1" id="pur_total_preview">Rp 0</h4>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold">Catatan Timbangan</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Kondisi kadar air, karung kemasan, dsb...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Simpan & Bayar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const grossIn = document.getElementById('pur_gross');
    const deductIn = document.getElementById('pur_deduct');
    const priceIn = document.getElementById('pur_price');
    const preview = document.getElementById('pur_total_preview');

    function updateCalc() {
        const gross = parseFloat(grossIn.value) || 0;
        const deduct = parseFloat(deductIn.value) || 0;
        const price = parseFloat(priceIn.value) || 0;
        const net = Math.max(0, gross - deduct);
        const total = net * price;
        preview.innerText = formatRupiahJS(total);
    }

    if (grossIn && priceIn) {
        grossIn.addEventListener('input', updateCalc);
        deductIn.addEventListener('input', updateCalc);
        priceIn.addEventListener('input', updateCalc);
    }
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
