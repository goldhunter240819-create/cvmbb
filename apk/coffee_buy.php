<?php
/**
 * MBB MOBILE APK - Pembelian Kopi dari Petani
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Pembelian Kopi Petani';
$currentApkNav   = 'coffee';

$mobileHeaderAction = '
    <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="modal" data-bs-target="#modalAddBuy" title="Timbang Baru">
        <i class="bi bi-plus-lg text-primary fw-bold"></i>
    </button>
';

require_once __DIR__ . '/layout/header.php';

// Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_buy') {
    csrf_validate();

    $invoiceNumber = 'NOT-BELI/' . date('Ym') . '/' . strtoupper(substr(uniqid(), -4));
    $purchaseDate  = sanitize($_POST['purchase_date'] ?? date('Y-m-d'));
    $farmerName    = sanitize($_POST['farmer_name'] ?? 'Petani');
    $grossWeight   = (float)($_POST['gross_weight'] ?? 0);
    $tareWeight    = (float)($_POST['tare_weight'] ?? 0);
    $pricePerKg    = (float)($_POST['price_per_kg'] ?? 0);
    $notes         = sanitize($_POST['notes'] ?? '');

    $netWeight = max(0, $grossWeight - $tareWeight);
    $grandTotal = $netWeight * $pricePerKg;

    if ($netWeight > 0 && $pricePerKg > 0) {
        $fStmt = $db->prepare("SELECT id FROM farmers WHERE name = ? LIMIT 1");
        $fStmt->execute([$farmerName]);
        $farmerId = $fStmt->fetchColumn();
        if (!$farmerId) {
            $db->prepare("INSERT INTO farmers (code, name) VALUES (?, ?)")->execute(['FRM-' . strtoupper(substr(uniqid(), -4)), $farmerName]);
            $farmerId = $db->lastInsertId();
        }

        $stmt = $db->prepare("
            INSERT INTO purchases (
                invoice_number, purchase_date, farmer_id, gross_weight, tare_weight, net_weight,
                price_per_kg, total_amount, grand_total, paid_amount, payment_status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'LUNAS', ?, ?)
        ");
        $stmt->execute([
            $invoiceNumber, $purchaseDate, $farmerId, $grossWeight, $tareWeight, $netWeight,
            $pricePerKg, $grandTotal, $grandTotal, $grandTotal, $notes, Auth::id()
        ]);

        log_audit('CREATE_COFFEE_PURCHASE_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat pembelian {$invoiceNumber} dari {$farmerName}");
        flash_set('success', "Pembelian dari {$farmerName} berhasil dicatat!");
        redirect('apk/coffee_buy.php');
    }
}

// Queries
$purchases = $db->query("
    SELECT p.*, f.name AS farmer_name 
    FROM purchases p 
    LEFT JOIN farmers f ON p.farmer_id = f.id 
    ORDER BY p.purchase_date DESC, p.id DESC 
    LIMIT 25
")->fetchAll();

$kpi = $db->query("
    SELECT COUNT(*) AS total_tx, COALESCE(SUM(net_weight),0) AS total_kg, COALESCE(SUM(grand_total),0) AS total_spent
    FROM purchases
")->fetch();
?>

<!-- KPI CARD RINGKAS MOBILE -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(5, 150, 105, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-white-50 fw-semibold">Pembelian Kopi dari Petani</span>
        <span class="badge bg-white text-dark small px-2 py-1 font-monospace"><?= number_format($kpi['total_tx']) ?> Transaksi</span>
    </div>
    <h3 class="fw-bold mb-2 text-white"><?= format_rupiah($kpi['total_spent']) ?></h3>
    <div class="d-flex justify-content-between small pt-2 border-top border-white border-opacity-25 mt-2 opacity-95">
        <span>Volume Kopi: <strong class="text-white"><?= number_format($kpi['total_kg'], 1, ',', '.') ?> Kg</strong></span>
        <span>Status: <strong class="text-white"><i class="bi bi-check-circle-fill me-1"></i>Lunas Tunai</strong></span>
    </div>
</div>

<!-- SUB-NAV TOGGLE (JUAL KOPI VS BELI KOPI) -->
<div class="px-3 mb-3">
    <div class="d-flex p-1 bg-white rounded-pill border shadow-sm">
        <a href="<?= base_url('apk/coffee_sell.php') ?>" class="btn btn-sm btn-light rounded-pill flex-fill text-muted py-2 fw-semibold">
            <i class="bi bi-truck me-1"></i> Penjualan (Trading)
        </a>
        <a href="<?= base_url('apk/coffee_buy.php') ?>" class="btn btn-sm btn-success rounded-pill flex-fill fw-bold text-white shadow-sm py-2">
            <i class="bi bi-cart-plus me-1"></i> Pembelian (Petani)
        </a>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    Riwayat Timbangan Petani Terbaru
</div>

<!-- DAFTAR KARTU PEMBELIAN -->
<?php if (empty($purchases)): ?>
    <div class="mobile-card text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        Belum ada pembelian dari petani.
    </div>
<?php else: ?>
    <?php foreach ($purchases as $p): ?>
        <div class="mobile-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 1rem; flex-shrink: 0;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                            <?= e($p['farmer_name'] ?? 'Petani Umum') ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            <?= e($p['invoice_number']) ?> &bull; <?= date('d M Y', strtotime($p['purchase_date'])) ?>
                        </div>
                    </div>
                </div>
                <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">LUNAS</span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.8rem;">
                <div>
                    <span class="text-muted">Netto:</span>
                    <strong class="text-dark"><?= number_format($p['net_weight'], 1, ',', '.') ?> Kg</strong>
                    <div class="text-muted small">@ <?= format_rupiah($p['price_per_kg']) ?></div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-success fs-6"><?= format_rupiah($p['grand_total']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- FAB TAMBAH TIMBANGAN -->
<button type="button" class="mobile-fab" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);" data-bs-toggle="modal" data-bs-target="#modalAddBuy" title="Timbang Pembelian Petani">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- MODAL PEMBELIAN PETANI MOBILE -->
<div class="modal fade" id="modalAddBuy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_buy">
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-cart-plus me-2"></i>Timbang Kopi Petani</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Petani / Pemasok</label>
                    <input type="text" name="farmer_name" class="form-control form-control-sm" placeholder="Cth: Budi Santoso" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Tanggal Pembelian</label>
                    <input type="date" name="purchase_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Berat Kotor (Kg)</label>
                        <input type="number" step="0.1" name="gross_weight" id="b_gross" class="form-control form-control-sm" placeholder="Bruto" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Potongan Tarra (Kg)</label>
                        <input type="number" step="0.1" name="tare_weight" id="b_tare" class="form-control form-control-sm" placeholder="Tarra" value="1.0" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Berat Bersih / Netto</label>
                        <input type="text" id="b_net" class="form-control form-control-sm bg-light fw-bold text-success" readonly value="0.0 Kg">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Harga Beli per Kg</label>
                        <input type="number" name="price_per_kg" id="b_price" class="form-control form-control-sm" placeholder="Rp" required>
                    </div>
                </div>
                <div class="p-2 bg-light rounded-3 mb-2 text-center">
                    <span class="text-muted small d-block">Estimasi Total Bayar Tunai:</span>
                    <h5 class="fw-bold text-success mb-0" id="b_total">Rp 0</h5>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Catatan Mutu / Kadar Air</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Cth: KA 13%, Asalan Basah, Petik Merah">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-success px-3 fw-bold">Simpan & Cetak Nota</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const g = document.getElementById('b_gross');
    const t = document.getElementById('b_tare');
    const p = document.getElementById('b_price');
    const netEl = document.getElementById('b_net');
    const totEl = document.getElementById('b_total');

    function recalc() {
        const gross = parseFloat(g.value) || 0;
        const tare  = parseFloat(t.value) || 0;
        const price = parseFloat(p.value) || 0;
        const net   = Math.max(0, gross - tare);
        const total = net * price;

        netEl.value = net.toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' Kg';
        totEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    if (g && t && p) {
        g.addEventListener('input', recalc);
        t.addEventListener('input', recalc);
        p.addEventListener('input', recalc);
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
