<?php
/**
 * MBB MOBILE APK - Penjualan Kopi (Trading)
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Penjualan Kopi Trading';
$currentApkNav   = 'coffee';

$mobileHeaderAction = '
    <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="modal" data-bs-target="#modalAddSale" title="Catat Penjualan">
        <i class="bi bi-plus-lg text-primary fw-bold"></i>
    </button>
';

require_once __DIR__ . '/layout/header.php';

// Handle Form Penjualan Kopi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_sale') {
    csrf_validate();

    $invoiceNumber = 'INV-KOP/' . date('Ym') . '/' . strtoupper(substr(uniqid(), -4));
    $saleDate      = sanitize($_POST['sale_date'] ?? date('Y-m-d'));
    $customerName  = sanitize($_POST['customer_name'] ?? 'Pembeli Umum');
    $weightKg      = (float)($_POST['weight_kg'] ?? 0);
    $pricePerKg    = (float)($_POST['price_per_kg'] ?? 0);
    $costPerKg     = (float)($_POST['cost_per_kg'] ?? 60000);
    $notes         = sanitize($_POST['notes'] ?? '');

    $grandTotal   = $weightKg * $pricePerKg;
    $totalCost    = $weightKg * $costPerKg;
    $profitAmount = $grandTotal - $totalCost;

    if ($weightKg > 0 && $pricePerKg > 0) {
        $cStmt = $db->prepare("SELECT id FROM customers WHERE name = ? LIMIT 1");
        $cStmt->execute([$customerName]);
        $customerId = $cStmt->fetchColumn();
        if (!$customerId) {
            $db->prepare("INSERT INTO customers (customer_code, name) VALUES (?, ?)")->execute(['CUST-' . strtoupper(substr(uniqid(), -4)), $customerName]);
            $customerId = $db->lastInsertId();
        }

        $stmt = $db->prepare("
            INSERT INTO sales (
                invoice_number, sale_date, customer_id, total_weight_kg, subtotal, 
                grand_total, paid_amount, profit_amount, payment_status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'LUNAS', ?, ?)
        ");
        $stmt->execute([
            $invoiceNumber, $saleDate, $customerId, $weightKg, $grandTotal,
            $grandTotal, $grandTotal, $profitAmount, $notes, Auth::id()
        ]);

        log_audit('CREATE_COFFEE_SALE_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat penjualan {$invoiceNumber} ke {$customerName}");
        flash_set('success', "Penjualan {$invoiceNumber} berhasil dicatat!");
        redirect('apk/coffee_sell.php');
    }
}

// Queries
$sales = $db->query("
    SELECT s.*, c.name AS customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.sale_date DESC, s.id DESC 
    LIMIT 25
")->fetchAll();

$kpi = $db->query("
    SELECT COUNT(*) AS total_shipments, COALESCE(SUM(total_weight_kg),0) AS total_kg, COALESCE(SUM(grand_total),0) AS total_rev, COALESCE(SUM(profit_amount),0) AS total_profit
    FROM sales
")->fetch();
?>

<!-- KPI CARD RINGKAS MOBILE -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #1e3a8a 0%, #1a56db 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(26, 86, 219, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-white-50 fw-semibold">Akumulasi Penjualan Biji Kopi</span>
        <span class="badge bg-white text-dark small px-2 py-1 font-monospace"><?= number_format($kpi['total_shipments']) ?> Kirim</span>
    </div>
    <h3 class="fw-bold mb-2 text-white"><?= format_rupiah($kpi['total_rev']) ?></h3>
    <div class="d-flex justify-content-between small pt-2 border-top border-white border-opacity-25 mt-2 opacity-95">
        <span>Volume: <strong class="text-white"><?= number_format($kpi['total_kg'], 0, ',', '.') ?> Kg</strong></span>
        <span>Laba: <strong class="text-warning fw-bold"><?= format_rupiah($kpi['total_profit']) ?></strong></span>
    </div>
</div>

<!-- SUB-NAV TOGGLE (JUAL KOPI VS BELI KOPI) -->
<div class="px-3 mb-3">
    <div class="d-flex p-1 bg-white rounded-pill border shadow-sm">
        <a href="<?= base_url('apk/coffee_sell.php') ?>" class="btn btn-sm btn-primary rounded-pill flex-fill fw-bold text-white shadow-sm py-2">
            <i class="bi bi-truck me-1"></i> Penjualan (Trading)
        </a>
        <a href="<?= base_url('apk/coffee_buy.php') ?>" class="btn btn-sm btn-light rounded-pill flex-fill text-muted py-2 fw-semibold">
            <i class="bi bi-cart-plus me-1"></i> Pembelian (Petani)
        </a>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    Riwayat Pengiriman Trading Terbaru
</div>

<!-- DAFTAR KARTU PENGIRIMAN MOBILE -->
<?php if (empty($sales)): ?>
    <div class="mobile-card text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        Belum ada data penjualan kopi.
    </div>
<?php else: ?>
    <?php foreach ($sales as $s): ?>
        <div class="mobile-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 1rem; flex-shrink: 0;">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                            <?= e($s['customer_name'] ?? 'Pelanggan Umum') ?>
                        </div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            <?= e($s['invoice_number']) ?> &bull; <?= date('d M Y', strtotime($s['sale_date'])) ?>
                        </div>
                    </div>
                </div>
                <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">LUNAS</span>
            </div>
            
            <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.8rem;">
                <div>
                    <span class="text-muted">Volume:</span>
                    <strong class="text-dark"><?= number_format($s['total_weight_kg'], 0, ',', '.') ?> Kg</strong>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-primary fs-6"><?= format_rupiah($s['grand_total']) ?></div>
                    <div class="text-success small fw-semibold">Laba: +<?= format_rupiah($s['profit_amount']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- FLOATING ACTION BUTTON (FAB) -->
<button type="button" class="mobile-fab" data-bs-toggle="modal" data-bs-target="#modalAddSale" title="Catat Penjualan Kopi">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- MODAL PENJUALAN KOPI MOBILE -->
<div class="modal fade" id="modalAddSale" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_sale">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Catat Penjualan Kopi Trading</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Pembeli / Eksportir / Mitra</label>
                    <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Cth: PT. Aneka Kopi Nusantara" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Tanggal Pengiriman</label>
                    <input type="date" name="sale_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Total Berat (Kg)</label>
                        <input type="number" step="1" name="weight_kg" id="s_weight" class="form-control form-control-sm" placeholder="Kg" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Harga Jual per Kg (Rp)</label>
                        <input type="number" name="price_per_kg" id="s_price" class="form-control form-control-sm" placeholder="Rp" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Estimasi HPP/Beli per Kg</label>
                        <input type="number" name="cost_per_kg" id="s_cost" class="form-control form-control-sm" value="62000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Status Pembayaran</label>
                        <input type="text" class="form-control form-control-sm bg-light text-success fw-bold" readonly value="LUNAS / CASH">
                    </div>
                </div>
                <div class="p-2 bg-light rounded-3 mb-2 text-center">
                    <span class="text-muted small d-block">Estimasi Nilai Kontrak Penjualan:</span>
                    <h5 class="fw-bold text-primary mb-0" id="s_total">Rp 0</h5>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Catatan Pengiriman / No. Truk</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Cth: Muat Truk Colt Diesel BE 8123 BA, Kirim ke Panjang">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">Simpan Faktur Penjualan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const w = document.getElementById('s_weight');
    const p = document.getElementById('s_price');
    const totEl = document.getElementById('s_total');

    function recalc() {
        const weight = parseFloat(w.value) || 0;
        const price  = parseFloat(p.value) || 0;
        const total  = weight * price;
        totEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    if (w && p) {
        w.addEventListener('input', recalc);
        p.addEventListener('input', recalc);
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
