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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_buy') {
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
    } elseif ($action === 'quick_add_farmer') {
        csrf_validate();
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $village = sanitize($_POST['village'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        if ($name) {
            $code = 'FRM-' . strtoupper(substr(uniqid(), -4));
            $db->prepare("INSERT INTO farmers (code, name, phone, village, notes) VALUES (?, ?, ?, ?, ?)")
               ->execute([$code, $name, $phone, $village, $notes]);
            log_audit('CREATE_FARMER_APK', 'apk', $db->lastInsertId(), "Tambah mitra petani {$name}");
            flash_set('success', "Mitra Petani {$name} berhasil didaftarkan ke Buku Kontak!");
            redirect('apk/coffee_buy.php?farmer_name=' . urlencode($name) . '&open_add=1');
        }
    }
}

// Queries
$farmersList = $db->query("SELECT id, code, name, phone, village, farmer_group FROM farmers ORDER BY name ASC")->fetchAll();

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
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Nama Petani / Pemasok</label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-success text-decoration-none fw-bold" style="font-size: 0.74rem;" onclick="openFarmerPicker()">
                            <i class="bi bi-person-lines-fill me-1"></i>Pilih dari Buku Mitra &raquo;
                        </button>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" name="farmer_name" id="buy_farmer_name" list="farmerList" class="form-control" placeholder="Ketik nama atau pilih dari buku..." value="<?= e($_GET['farmer_name'] ?? '') ?>" required autocomplete="off">
                        <button type="button" class="btn btn-outline-success" onclick="openFarmerPicker()" title="Buka Buku Kontak Petani">
                            <i class="bi bi-book"></i>
                        </button>
                    </div>
                    <datalist id="farmerList">
                        <?php foreach ($farmersList as $fl): ?>
                            <option value="<?= e($fl['name']) ?>"><?= e($fl['village'] ? "Desa {$fl['village']}" : '') ?> <?= e($fl['phone'] ? "({$fl['phone']})" : '') ?></option>
                        <?php endforeach; ?>
                    </datalist>
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

<!-- MODAL BUKU KONTAK PETANI (QUICK PICKER) -->
<div class="modal fade" id="modalPickFarmer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Buku Mitra Petani</h6>
                <button type="button" class="btn-close btn-close-white" onclick="closeFarmerPicker()"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Search Box -->
                <div class="position-relative mb-3">
                    <input type="text" id="searchFarmerInput" class="form-control form-control-sm ps-5 rounded-pill" placeholder="Cari nama, desa, no hp...">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Pilih Mitra (<?= count($farmersList) ?> Kontak)</span>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="showAddFarmerForm()">
                        <i class="bi bi-plus-lg me-1"></i>+ Mitra Baru
                    </button>
                </div>

                <!-- Form Tambah Cepat Mitra Baru -->
                <div id="quickAddFarmerBox" class="p-3 bg-light rounded-3 mb-3 border border-success border-opacity-25" style="display: none;">
                    <div class="fw-bold small text-success mb-2"><i class="bi bi-person-plus-fill me-1"></i>Daftarkan Petani Baru</div>
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="quick_add_farmer">
                        <div class="mb-2">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Nama Lengkap Petani *" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <input type="text" name="village" class="form-control form-control-sm" placeholder="Desa / Dusun">
                            </div>
                            <div class="col-6">
                                <input type="tel" name="phone" class="form-control form-control-sm" placeholder="No HP / WhatsApp">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-sm btn-light" onclick="hideAddFarmerForm()">Batal</button>
                            <button type="submit" class="btn btn-sm btn-success fw-bold">Simpan Mitra</button>
                        </div>
                    </form>
                </div>

                <!-- Daftar Kartu Petani -->
                <div id="farmerItemsList" class="d-flex flex-column gap-2" style="max-height: 320px; overflow-y: auto;">
                    <?php if (empty($farmersList)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-journal-x fs-2 d-block mb-1 opacity-50"></i>
                            Belum ada mitra petani terdaftar.<br>
                            <button type="button" class="btn btn-sm btn-success rounded-pill mt-2" onclick="showAddFarmerForm()">+ Daftarkan Petani Sekarang</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($farmersList as $fl): ?>
                            <div class="farmer-card-item p-2 rounded-3 border bg-white d-flex align-items-center justify-content-between" 
                                 style="cursor: pointer; transition: all 0.15s ease;"
                                 data-filter="<?= strtolower(e($fl['name'] . ' ' . ($fl['village'] ?? '') . ' ' . ($fl['phone'] ?? ''))) ?>"
                                 onclick="selectFarmer('<?= e(addslashes($fl['name'])) ?>')">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-success-subtle text-success fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0;">
                                        <?= strtoupper(substr($fl['name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.86rem; line-height: 1.2;">
                                            <?= e($fl['name']) ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.72rem;">
                                            <i class="bi bi-geo-alt me-1"></i><?= e($fl['village'] ?: 'Air Hitam') ?>
                                            <?php if (!empty($fl['phone'])): ?>
                                                &bull; <i class="bi bi-telephone me-1"></i><?= e($fl['phone']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge bg-success-subtle text-success border border-success border-opacity-25" style="font-size: 0.65rem;">
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
let addBuyModalInstance = null;
let pickFarmerModalInstance = null;

function getBuyModal() {
    if (!addBuyModalInstance) {
        addBuyModalInstance = new bootstrap.Modal(document.getElementById('modalAddBuy'));
    }
    return addBuyModalInstance;
}

function getPickModal() {
    if (!pickFarmerModalInstance) {
        pickFarmerModalInstance = new bootstrap.Modal(document.getElementById('modalPickFarmer'));
    }
    return pickFarmerModalInstance;
}

function openFarmerPicker() {
    const buyEl = document.getElementById('modalAddBuy');
    const modal = bootstrap.Modal.getInstance(buyEl);
    if (modal) {
        modal.hide();
    }
    setTimeout(() => {
        getPickModal().show();
        setTimeout(() => {
            document.getElementById('searchFarmerInput')?.focus();
        }, 300);
    }, 200);
}

function closeFarmerPicker() {
    getPickModal().hide();
    setTimeout(() => {
        getBuyModal().show();
    }, 200);
}

function selectFarmer(name) {
    document.getElementById('buy_farmer_name').value = name;
    closeFarmerPicker();
}

function showAddFarmerForm() {
    document.getElementById('quickAddFarmerBox').style.display = 'block';
}

function hideAddFarmerForm() {
    document.getElementById('quickAddFarmerBox').style.display = 'none';
}

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

    // Live search filter
    const searchInput = document.getElementById('searchFarmerInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.farmer-card-item');
            items.forEach(el => {
                const text = el.getAttribute('data-filter') || '';
                el.style.display = text.includes(q) ? 'flex' : 'none';
            });
        });
    }

    // Auto open add buy modal if redirected with open_add=1
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_add') === '1' || urlParams.get('farmer_name')) {
        getBuyModal().show();
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
