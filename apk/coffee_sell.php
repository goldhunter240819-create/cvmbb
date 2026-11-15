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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_sale') {
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
                $db->prepare("INSERT INTO customers (code, name) VALUES (?, ?)")->execute(['CUST-' . strtoupper(substr(uniqid(), -4)), $customerName]);
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
    } elseif ($action === 'quick_add_customer') {
        csrf_validate();
        $name = sanitize($_POST['name'] ?? '');
        $customerType = sanitize($_POST['customer_type'] ?? 'Eksportir');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        if ($name) {
            $code = 'CUST-' . strtoupper(substr(uniqid(), -4));
            $db->prepare("INSERT INTO customers (code, name, customer_type, phone, address, notes) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$code, $name, $customerType, $phone, $address, $notes]);
            log_audit('CREATE_CUSTOMER_APK', 'apk', $db->lastInsertId(), "Tambah pelanggan {$name}");
            flash_set('success', "Pelanggan {$name} berhasil didaftarkan ke Buku Kontak!");
            redirect('apk/coffee_sell.php?customer_name=' . urlencode($name) . '&open_add=1');
        }
    }
}

// Queries
$customersList = $db->query("SELECT id, code, name, customer_type, phone, address FROM customers ORDER BY name ASC")->fetchAll();

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
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Nama Pembeli / Eksportir</label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-primary text-decoration-none fw-bold" style="font-size: 0.74rem;" onclick="openCustomerPicker()">
                            <i class="bi bi-person-lines-fill me-1"></i>Pilih dari Buku Pelanggan &raquo;
                        </button>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" name="customer_name" id="sell_customer_name" list="customerList" class="form-control" placeholder="Ketik nama atau pilih pelanggan..." value="<?= e($_GET['customer_name'] ?? '') ?>" required autocomplete="off">
                        <button type="button" class="btn btn-outline-primary" onclick="openCustomerPicker()" title="Buka Buku Pelanggan">
                            <i class="bi bi-book"></i>
                        </button>
                    </div>
                    <datalist id="customerList">
                        <?php foreach ($customersList as $cl): ?>
                            <option value="<?= e($cl['name']) ?>"><?= e($cl['customer_type'] ?: 'Pembeli') ?> <?= e($cl['phone'] ? "({$cl['phone']})" : '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
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

<!-- MODAL BUKU PELANGGAN (QUICK PICKER) -->
<div class="modal fade" id="modalPickCustomer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Buku Pelanggan & Buyer</h6>
                <button type="button" class="btn-close btn-close-white" onclick="closeCustomerPicker()"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Search Box -->
                <div class="input-group input-group-sm mb-3 rounded-pill overflow-hidden bg-white border shadow-sm">
                    <span class="input-group-text bg-white border-0 ps-3 pe-2 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="searchCustomerInput" class="form-control border-0 ps-1" placeholder="Cari nama pelanggan, jenis, no hp..." style="box-shadow: none;">
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Pilih Pelanggan (<?= count($customersList) ?> Kontak)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="showAddCustomerForm()">
                        <i class="bi bi-plus-lg me-1"></i>Pelanggan Baru
                    </button>
                </div>

                <!-- Form Tambah Cepat Pelanggan Baru -->
                <div id="quickAddCustomerBox" class="p-3 bg-light rounded-3 mb-3 border border-primary border-opacity-25" style="display: none;">
                    <div class="fw-bold small text-primary mb-2"><i class="bi bi-person-plus-fill me-1"></i>Daftarkan Pelanggan Baru</div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="quick_add_customer">
                        <div class="mb-2">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Nama Perusahaan / Pembeli *" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <select name="customer_type" class="form-select form-select-sm">
                                    <option value="Eksportir">Eksportir</option>
                                    <option value="Pabrik">Pabrik</option>
                                    <option value="Pengepul">Pengepul</option>
                                    <option value="Toko">Toko / Roastery</option>
                                    <option value="Umum">Umum / Perorangan</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <input type="tel" name="phone" class="form-control form-control-sm" placeholder="No Telepon / WA">
                            </div>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="address" class="form-control form-control-sm" placeholder="Kota / Alamat (Cth: Bandar Lampung)">
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="hideAddCustomerForm()">Batal</button>
                            <button type="submit" class="btn btn-sm btn-primary fw-bold">Simpan Pelanggan</button>
                        </div>
                    </form>
                </div>

                <!-- Daftar Kartu Pelanggan -->
                <div id="customerItemsList" class="d-flex flex-column gap-2" style="max-height: 320px; overflow-y: auto;">
                    <?php if (empty($customersList)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-journal-x fs-2 d-block mb-1 opacity-50"></i>
                            Belum ada data pelanggan terdaftar.<br>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill mt-2" onclick="showAddCustomerForm()">+ Daftarkan Pelanggan Sekarang</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($customersList as $cl): ?>
                            <div class="customer-card-item p-2 rounded-3 border bg-white d-flex align-items-center justify-content-between" 
                                 style="cursor: pointer; transition: all 0.15s ease;"
                                 data-filter="<?= strtolower(e($cl['name'] . ' ' . ($cl['customer_type'] ?? '') . ' ' . ($cl['phone'] ?? '') . ' ' . ($cl['address'] ?? ''))) ?>"
                                 onclick="selectCustomer('<?= e(addslashes($cl['name'])) ?>')">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0;">
                                        <?= strtoupper(substr($cl['name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.86rem; line-height: 1.2;">
                                            <?= e($cl['name']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            <span class="badge bg-light text-primary border" style="font-size: 0.62rem;"><?= e($cl['customer_type'] ?: 'Pembeli') ?></span>
                                            <?php if (!empty($cl['address'])): ?>
                                                &bull; <?= e($cl['address']) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($cl['phone'])): ?>
                                                &bull; <?= e($cl['phone']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary border-opacity-25" style="font-size: 0.65rem;">
                                    Tarik &raquo;
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let addSaleModalInstance = null;
let pickCustomerModalInstance = null;

function getSaleModal() {
    if (!addSaleModalInstance) {
        addSaleModalInstance = new bootstrap.Modal(document.getElementById('modalAddSale'));
    }
    return addSaleModalInstance;
}

function getCustomerPickModal() {
    if (!pickCustomerModalInstance) {
        pickCustomerModalInstance = new bootstrap.Modal(document.getElementById('modalPickCustomer'));
    }
    return pickCustomerModalInstance;
}

function openCustomerPicker() {
    const saleEl = document.getElementById('modalAddSale');
    const modal = bootstrap.Modal.getInstance(saleEl);
    if (modal) {
        modal.hide();
    }
    setTimeout(() => {
        getCustomerPickModal().show();
        setTimeout(() => {
            document.getElementById('searchCustomerInput')?.focus();
        }, 300);
    }, 200);
}

function closeCustomerPicker() {
    getCustomerPickModal().hide();
    setTimeout(() => {
        getSaleModal().show();
    }, 200);
}

function selectCustomer(name) {
    document.getElementById('sell_customer_name').value = name;
    closeCustomerPicker();
}

function showAddCustomerForm() {
    document.getElementById('quickAddCustomerBox').style.display = 'block';
}

function hideAddCustomerForm() {
    document.getElementById('quickAddCustomerBox').style.display = 'none';
}

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

    // Live search filter
    const searchInput = document.getElementById('searchCustomerInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.customer-card-item');
            items.forEach(el => {
                const text = el.getAttribute('data-filter') || '';
                el.style.display = text.includes(q) ? 'flex' : 'none';
            });
        });
    }

    // Auto open modal if requested via URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_add') === '1' || urlParams.get('customer_name')) {
        getSaleModal().show();
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
