<?php
/**
 * MBB MOBILE APK - Jasa Roasting Kopi
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Jasa Roasting';
$currentApkNav   = 'coffee';

$mobileHeaderAction = '
    <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="modal" data-bs-target="#modalAddRoast" title="Order Baru">
        <i class="bi bi-plus-lg text-primary fw-bold"></i>
    </button>
';

require_once __DIR__ . '/layout/header.php';

// Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_roast') {
    csrf_validate();

    $orderNumber  = 'ROAST/' . date('Ym') . '/' . rand(1000, 9999);
    $orderDate    = sanitize($_POST['order_date'] ?? date('Y-m-d'));
    $customerName = sanitize($_POST['customer_name'] ?? 'Pelanggan');
    $phone        = sanitize($_POST['customer_phone'] ?? '');
    $profile      = sanitize($_POST['roast_profile'] ?? 'MEDIUM');
    $weight       = (float)($_POST['raw_weight_kg'] ?? 0);
    $feePerKg     = (float)($_POST['fee_per_kg'] ?? 15000);
    $totalFee     = $weight * $feePerKg;
    $notes        = sanitize($_POST['notes'] ?? '');

    if ($weight > 0) {
        $cStmt = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
        $cStmt->execute([$customerName]);
        $customerId = $cStmt->fetchColumn();
        if (!$customerId) {
            $db->prepare("INSERT INTO customers (customer_code, name, phone) VALUES (?, ?, ?)")->execute(['CUST-' . strtoupper(substr(uniqid(), -4)), $customerName, $phone]);
            $customerId = $db->lastInsertId();
        }

        $stmt = $db->prepare("
            INSERT INTO roasting_orders (
                order_number, order_date, customer_id, raw_weight_kg, roast_profile,
                fee_per_kg, total_fee, work_status, payment_status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ANTREAN', 'LUNAS', ?, ?)
        ");
        $stmt->execute([
            $orderNumber, $orderDate, $customerId, $weight, $profile,
            $feePerKg, $totalFee, $notes, Auth::id()
        ]);

        log_audit('CREATE_ROAST_ORDER_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat jasa roasting {$orderNumber}");
        flash_set('success', "Order roasting {$orderNumber} berhasil dicatat!");
        redirect('apk/roasting.php');
    }
}

// Queries
$orders = $db->query("
    SELECT r.*, c.name AS customer_name, c.phone AS customer_phone 
    FROM roasting_orders r 
    LEFT JOIN customers c ON r.customer_id = c.id 
    ORDER BY r.id DESC 
    LIMIT 20
")->fetchAll();

$kpi = $db->query("
    SELECT COUNT(*) AS total_orders, COALESCE(SUM(raw_weight_kg),0) AS total_kg, COALESCE(SUM(total_fee),0) AS total_rev,
           COALESCE(SUM(CASE WHEN work_status IN ('ANTREAN','ROASTING') THEN 1 ELSE 0 END),0) AS active_q
    FROM roasting_orders
")->fetch();
?>

<!-- KPI CARD RINGKAS MOBILE -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(217, 119, 6, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-white-50 fw-semibold">Jasa Roasting Kopi Pelanggan</span>
        <span class="badge bg-white text-dark small px-2 py-1 font-monospace"><?= number_format($kpi['active_q']) ?> Antrean Oven</span>
    </div>
    <h3 class="fw-bold mb-2 text-white"><?= format_rupiah($kpi['total_rev']) ?></h3>
    <div class="d-flex justify-content-between small pt-2 border-top border-white border-opacity-25 mt-2 opacity-95">
        <span>Volume Kopi: <strong class="text-white"><?= number_format($kpi['total_kg'], 1, ',', '.') ?> Kg</strong></span>
        <span>Total: <strong class="text-white"><?= number_format($kpi['total_orders']) ?> Order</strong></span>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    Daftar Antrean & Status Pengerjaan Sangrai
</div>

<!-- DAFTAR KARTU JASA ROASTING -->
<?php if (empty($orders)): ?>
    <div class="mobile-card text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        Belum ada pesanan jasa roasting.
    </div>
<?php else: ?>
    <?php foreach ($orders as $o): ?>
        <div class="mobile-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 1rem; flex-shrink: 0;">
                        <i class="bi bi-fire"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                            <?= e($o['customer_name'] ?? 'Pelanggan Umum') ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            <?= e($o['order_number']) ?> &bull; <?= date('d M Y', strtotime($o['order_date'])) ?>
                        </div>
                    </div>
                </div>
                <?php
                $badge = match($o['work_status']) {
                    'ANTREAN' => 'bg-warning text-dark',
                    'ROASTING' => 'bg-danger text-white',
                    'SIAP_DIAMBIL' => 'bg-primary text-white',
                    'SELESAI' => 'bg-success text-white',
                    default => 'bg-secondary text-white'
                };
                ?>
                <span class="badge <?= $badge ?> px-2 py-1" style="font-size: 0.68rem;"><?= e($o['work_status']) ?></span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.8rem;">
                <div>
                    <span class="text-muted">Bahan:</span>
                    <strong class="text-dark"><?= number_format($o['raw_weight_kg'], 1) ?> Kg</strong>
                    <span class="badge bg-light text-dark border ms-1 font-monospace"><?= e($o['roast_profile']) ?></span>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-primary fs-6"><?= format_rupiah($o['total_fee']) ?></div>
                    <div class="small fw-semibold <?= $o['payment_status'] === 'LUNAS' ? 'text-success' : 'text-danger' ?>">
                        <?= e($o['payment_status']) ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- FAB -->
<button type="button" class="mobile-fab" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);" data-bs-toggle="modal" data-bs-target="#modalAddRoast" title="Terima Order Roasting">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- MODAL ROASTING ORDER MOBILE -->
<div class="modal fade" id="modalAddRoast" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_roast">
            <div class="modal-header bg-warning text-dark py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-fire me-2"></i>Terima Jasa Roasting</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Pemilik Kopi / Klien</label>
                    <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Cth: Kedai Kopi Liwa" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. WhatsApp Klien</label>
                        <input type="text" name="customer_phone" class="form-control form-control-sm" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Tanggal Terima</label>
                        <input type="date" name="order_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Berat Mentah (Kg)</label>
                        <input type="number" step="0.5" name="raw_weight_kg" id="r_weight" class="form-control form-control-sm" placeholder="Kg" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Profil Sangrai</label>
                        <select name="roast_profile" class="form-select form-select-sm" required>
                            <option value="MEDIUM">MEDIUM (Filter / Tubruk)</option>
                            <option value="DARK">DARK (Espresso / Kental)</option>
                            <option value="LIGHT">LIGHT (Specialty)</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Ongkos Sangrai per Kg (Rp)</label>
                    <input type="number" name="fee_per_kg" id="r_fee" class="form-control form-control-sm" value="15000" required>
                </div>
                <div class="p-2 bg-light rounded-3 mb-2 text-center">
                    <span class="text-muted small d-block">Estimasi Total Ongkos Roasting:</span>
                    <h5 class="fw-bold text-primary mb-0" id="r_total">Rp 0</h5>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Catatan Tambahan (Misal: Giling Kasar / Halus)</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Bubuk halus, kemas standing pouch MBB">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-warning px-3 fw-bold">Simpan Order Roasting</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const w = document.getElementById('r_weight');
    const f = document.getElementById('r_fee');
    const totEl = document.getElementById('r_total');

    function recalc() {
        const weight = parseFloat(w.value) || 0;
        const fee    = parseFloat(f.value) || 0;
        const total  = weight * fee;
        totEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    if (w && f) {
        w.addEventListener('input', recalc);
        f.addEventListener('input', recalc);
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
