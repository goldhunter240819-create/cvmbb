<?php
/**
 * MBB MOBILE APK - Buku Pelanggan & Mitra Petani
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Buku Kontak & Mitra';
$currentApkNav   = 'coffee';

$mobileHeaderAction = '
    <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" onclick="openAddContactModal()" title="Tambah Kontak Baru">
        <i class="bi bi-person-plus-fill text-primary fw-bold"></i>
    </button>
';

require_once __DIR__ . '/layout/header.php';

// Handle POST Form (Tambah / Edit / Hapus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = $_POST['action'] ?? '';

    // TAMBAH MITRA PETANI
    if ($action === 'create_farmer') {
        $name    = sanitize($_POST['name'] ?? '');
        $phone   = sanitize($_POST['phone'] ?? '');
        $village = sanitize($_POST['village'] ?? '');
        $group   = sanitize($_POST['farmer_group'] ?? '');
        $bank    = sanitize($_POST['bank_account'] ?? '');
        $notes   = sanitize($_POST['notes'] ?? '');

        if ($name) {
            $code = 'FRM-' . strtoupper(substr(uniqid(), -4));
            $stmt = $db->prepare("INSERT INTO farmers (code, name, phone, village, farmer_group, bank_account, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $phone, $village, $group, $bank, $notes]);
            log_audit('CREATE_FARMER_APK', 'apk', $db->lastInsertId(), "Tambah mitra petani {$name}");
            flash_set('success', "Mitra Petani {$name} berhasil ditambahkan!");
            redirect('apk/customers.php?tab=farmers');
        }
    }

    // TAMBAH PELANGGAN
    elseif ($action === 'create_customer') {
        $name    = sanitize($_POST['name'] ?? '');
        $type    = sanitize($_POST['customer_type'] ?? 'Eksportir');
        $phone   = sanitize($_POST['phone'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $notes   = sanitize($_POST['notes'] ?? '');

        if ($name) {
            $code = 'CUST-' . strtoupper(substr(uniqid(), -4));
            $stmt = $db->prepare("INSERT INTO customers (code, name, customer_type, phone, email, address, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $type, $phone, $email, $address, $notes]);
            log_audit('CREATE_CUSTOMER_APK', 'apk', $db->lastInsertId(), "Tambah pelanggan {$name}");
            flash_set('success', "Pelanggan {$name} berhasil ditambahkan!");
            redirect('apk/customers.php?tab=customers');
        }
    }

    // HAPUS MITRA PETANI
    elseif ($action === 'delete_farmer') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM farmers WHERE id = ?")->execute([$id]);
            log_audit('DELETE_FARMER_APK', 'apk', $id, "Hapus mitra petani ID {$id}");
            flash_set('success', "Mitra petani berhasil dihapus.");
            redirect('apk/customers.php?tab=farmers');
        }
    }

    // HAPUS PELANGGAN
    elseif ($action === 'delete_customer') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
            log_audit('DELETE_CUSTOMER_APK', 'apk', $id, "Hapus pelanggan ID {$id}");
            flash_set('success', "Pelanggan berhasil dihapus.");
            redirect('apk/customers.php?tab=customers');
        }
    }
}

// Queries
$tab = $_GET['tab'] ?? 'farmers';
$farmers = $db->query("SELECT * FROM farmers ORDER BY name ASC")->fetchAll();
$customers = $db->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
?>

<!-- BANNER RINGKAS BUKU KONTAK -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #312e81 0%, #4338ca 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(67, 56, 202, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="small text-white-50 fw-semibold">Buku Pelanggan & Mitra Petani</span>
        <span class="badge bg-white text-dark small px-2 py-1 font-monospace"><?= count($farmers) + count($customers) ?> Kontak</span>
    </div>
    <h3 class="fw-bold mb-1 text-white">Direktori Kontak</h3>
    <p class="text-white-50 small mb-0">Tersinkronisasi otomatis dengan form timbang beli & penjualan kopi MBB.</p>
</div>

<!-- SUB-NAV TOGGLE (MITRA PETANI VS PELANGGAN) -->
<div class="px-3 mb-3">
    <div class="d-flex p-1 bg-white rounded-pill border shadow-sm">
        <button type="button" id="tabBtnFarmers" class="btn btn-sm rounded-pill flex-fill fw-bold py-2 <?= $tab === 'farmers' ? 'btn-success text-white shadow-sm' : 'btn-light text-muted' ?>" onclick="switchTab('farmers')">
            <i class="bi bi-person-fill me-1"></i> Mitra Petani (<?= count($farmers) ?>)
        </button>
        <button type="button" id="tabBtnCustomers" class="btn btn-sm rounded-pill flex-fill fw-bold py-2 <?= $tab === 'customers' ? 'btn-success text-white shadow-sm' : 'btn-light text-muted' ?>" onclick="switchTab('customers')">
            <i class="bi bi-building me-1"></i> Pelanggan / Buyer (<?= count($customers) ?>)
        </button>
    </div>
</div>

<!-- SEARCH BAR INSTAN -->
<div class="px-3 mb-3">
    <div class="input-group shadow-sm rounded-pill overflow-hidden bg-white border" style="height: 42px;">
        <span class="input-group-text bg-white border-0 ps-3 pe-2 text-muted" style="display:flex; align-items:center;">
            <i class="bi bi-search" style="font-size: 0.95rem;"></i>
        </span>
        <input type="text" id="contactSearchInput" class="form-control border-0 ps-1" placeholder="Ketik nama, telepon, atau desa/kota..." style="font-size: 0.82rem; box-shadow: none;">
    </div>
</div>

<!-- TAB 1: MITRA PETANI -->
<div id="sectionFarmers" style="<?= $tab === 'farmers' ? '' : 'display: none;' ?>">
    <div class="px-3 pb-2 text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center" style="letter-spacing: 0.5px; font-size: 0.72rem;">
        <span>Daftar Mitra Petani Kopi</span>
        <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.7rem;" data-bs-toggle="modal" data-bs-target="#modalAddFarmer">
            <i class="bi bi-plus-lg me-1"></i>+ Tambah Petani
        </button>
    </div>

    <?php if (empty($farmers)): ?>
        <div class="mobile-card text-center py-5 text-muted">
            <i class="bi bi-person-x fs-1 d-block mb-2 opacity-50"></i>
            Belum ada mitra petani tersimpan.<br>
            <button type="button" class="btn btn-sm btn-success rounded-pill mt-3 px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddFarmer">
                <i class="bi bi-plus-lg me-1"></i>Tambah Mitra Pertama
            </button>
        </div>
    <?php else: ?>
        <div id="farmersListContainer">
            <?php foreach ($farmers as $f): ?>
                <div class="mobile-card contact-card-item mb-2" data-search="<?= strtolower(e($f['name'] . ' ' . ($f['village'] ?? '') . ' ' . ($f['phone'] ?? '') . ' ' . ($f['farmer_group'] ?? ''))) ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-success-subtle text-success fw-bold d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1rem; flex-shrink: 0;">
                                <?= strtoupper(substr($f['name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                                    <?= e($f['name']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    <span class="badge bg-light text-success border" style="font-size: 0.62rem;"><?= e($f['code']) ?></span>
                                    <?php if (!empty($f['village'])): ?>
                                        &bull; <i class="bi bi-geo-alt me-1"></i>Desa <?= e($f['village']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light rounded-circle p-0" style="width: 28px; height: 28px;" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="font-size: 0.8rem;">
                                <li>
                                    <a class="dropdown-item text-success fw-semibold" href="<?= base_url('apk/coffee_buy.php?farmer_name=' . urlencode($f['name']) . '&open_add=1') ?>">
                                        <i class="bi bi-cart-plus me-2"></i>Timbang Kopi
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_farmer">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button type="button" class="dropdown-item text-danger btn-confirm-delete" data-confirm="Hapus data mitra petani <?= e($f['name']) ?>?">
                                            <i class="bi bi-trash me-2"></i>Hapus Mitra
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.78rem;">
                        <div class="text-muted">
                            <?php if (!empty($f['phone'])): ?>
                                <i class="bi bi-telephone me-1"></i><?= e($f['phone']) ?>
                            <?php else: ?>
                                <span>No HP belum diisi</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if (!empty($f['phone'])): ?>
                                <?php 
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $f['phone']);
                                    if (substr($cleanPhone, 0, 1) === '0') $cleanPhone = '62' . substr($cleanPhone, 1);
                                ?>
                                <a href="https://wa.me/<?= $cleanPhone ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 fw-semibold" style="font-size: 0.72rem;">
                                    <i class="bi bi-whatsapp me-1"></i>WA
                                </a>
                                <a href="tel:<?= e($f['phone']) ?>" class="btn btn-sm btn-light rounded-pill px-2 py-0 fw-semibold border" style="font-size: 0.72rem;">
                                    <i class="bi bi-telephone-fill me-1"></i>Telepon
                                </a>
                            <?php endif; ?>
                            <a href="<?= base_url('apk/coffee_buy.php?farmer_name=' . urlencode($f['name']) . '&open_add=1') ?>" class="btn btn-sm btn-success rounded-pill px-2 py-0 fw-bold" style="font-size: 0.72rem;">
                                <i class="bi bi-cart-plus me-1"></i>Beli
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- TAB 2: PELANGGAN / BUYER -->
<div id="sectionCustomers" style="<?= $tab === 'customers' ? '' : 'display: none;' ?>">
    <div class="px-3 pb-2 text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center" style="letter-spacing: 0.5px; font-size: 0.72rem;">
        <span>Daftar Pelanggan & Perusahaan Buyer</span>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.7rem;" data-bs-toggle="modal" data-bs-target="#modalAddCustomer">
            <i class="bi bi-plus-lg me-1"></i>+ Tambah Pelanggan
        </button>
    </div>

    <?php if (empty($customers)): ?>
        <div class="mobile-card text-center py-5 text-muted">
            <i class="bi bi-building-x fs-1 d-block mb-2 opacity-50"></i>
            Belum ada pelanggan tersimpan.<br>
            <button type="button" class="btn btn-sm btn-primary rounded-pill mt-3 px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalAddCustomer">
                <i class="bi bi-plus-lg me-1"></i>Tambah Pelanggan Pertama
            </button>
        </div>
    <?php else: ?>
        <div id="customersListContainer">
            <?php foreach ($customers as $c): ?>
                <div class="mobile-card contact-card-item mb-2" data-search="<?= strtolower(e($c['name'] . ' ' . ($c['customer_type'] ?? '') . ' ' . ($c['phone'] ?? '') . ' ' . ($c['address'] ?? ''))) ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-size: 1rem; flex-shrink: 0;">
                                <?= strtoupper(substr($c['name'], 0, 2)) ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark" style="font-size: 0.92rem; line-height: 1.2;">
                                    <?= e($c['name']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    <span class="badge bg-light text-primary border" style="font-size: 0.62rem;"><?= e($c['customer_type'] ?: 'Buyer') ?></span>
                                    <?php if (!empty($c['address'])): ?>
                                        &bull; <i class="bi bi-geo-alt me-1"></i><?= e($c['address']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light rounded-circle p-0" style="width: 28px; height: 28px;" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="font-size: 0.8rem;">
                                <li>
                                    <a class="dropdown-item text-primary fw-semibold" href="<?= base_url('apk/coffee_sell.php?customer_name=' . urlencode($c['name']) . '&open_add=1') ?>">
                                        <i class="bi bi-truck me-2"></i>Faktur Penjualan
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_customer">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="button" class="dropdown-item text-danger btn-confirm-delete" data-confirm="Hapus data pelanggan <?= e($c['name']) ?>?">
                                            <i class="bi bi-trash me-2"></i>Hapus Pelanggan
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.78rem;">
                        <div class="text-muted">
                            <?php if (!empty($c['phone'])): ?>
                                <i class="bi bi-telephone me-1"></i><?= e($c['phone']) ?>
                            <?php else: ?>
                                <span>No kontak belum diisi</span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if (!empty($c['phone'])): ?>
                                <?php 
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $c['phone']);
                                    if (substr($cleanPhone, 0, 1) === '0') $cleanPhone = '62' . substr($cleanPhone, 1);
                                ?>
                                <a href="https://wa.me/<?= $cleanPhone ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0 fw-semibold" style="font-size: 0.72rem;">
                                    <i class="bi bi-whatsapp me-1"></i>WA
                                </a>
                                <a href="tel:<?= e($c['phone']) ?>" class="btn btn-sm btn-light rounded-pill px-2 py-0 fw-semibold border" style="font-size: 0.72rem;">
                                    <i class="bi bi-telephone-fill me-1"></i>Telepon
                                </a>
                            <?php endif; ?>
                            <a href="<?= base_url('apk/coffee_sell.php?customer_name=' . urlencode($c['name']) . '&open_add=1') ?>" class="btn btn-sm btn-primary rounded-pill px-2 py-0 fw-bold" style="font-size: 0.72rem;">
                                <i class="bi bi-truck me-1"></i>Jual
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- FLOATING ACTION BUTTON (FAB) -->
<button type="button" class="mobile-fab" onclick="openAddContactModal()" title="Tambah Kontak Baru">
    <i class="bi bi-plus-lg"></i>
</button>

<!-- MODAL TAMBAH MITRA PETANI -->
<div class="modal fade" id="modalAddFarmer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_farmer">
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Daftar Mitra Petani Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Lengkap Petani *</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Cth: Pak Slamet / Mas Joko" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Desa / Wilayah</label>
                        <input type="text" name="village" class="form-control form-control-sm" placeholder="Cth: Gunung Terang">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. HP / WA</label>
                        <input type="tel" name="phone" class="form-control form-control-sm" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Kelompok Tani</label>
                        <input type="text" name="farmer_group" class="form-control form-control-sm" placeholder="Cth: KT Maju Makmur">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Rekening / Bank</label>
                        <input type="text" name="bank_account" class="form-control form-control-sm" placeholder="Cth: BRI 123456789">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Catatan Tambahan</label>
                    <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Cth: Spesialis petik merah robusta / langganan"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-success px-3 fw-bold">Simpan Mitra Petani</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH PELANGGAN / BUYER -->
<div class="modal fade" id="modalAddCustomer" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_customer">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-building-add me-2"></i>Daftar Pelanggan Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Perusahaan / Buyer *</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Cth: PT. Aneka Kopi Nusantara" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Kategori Pelanggan</label>
                        <select name="customer_type" class="form-select form-select-sm">
                            <option value="Eksportir">Eksportir</option>
                            <option value="Pabrik">Pabrik</option>
                            <option value="Pengepul">Pengepul Besar</option>
                            <option value="Toko">Toko / Roastery</option>
                            <option value="Umum">Umum / Perorangan</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. Kontak / WA</label>
                        <input type="tel" name="phone" class="form-control form-control-sm" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Kota / Alamat Kantor</label>
                    <input type="text" name="address" class="form-control form-control-sm" placeholder="Cth: Panjang, Bandar Lampung">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Email / Kontak Sales</label>
                    <input type="email" name="email" class="form-control form-control-sm" placeholder="purchasing@perusahaan.com">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Catatan Tambahan</label>
                    <textarea name="notes" rows="2" class="form-control form-control-sm" placeholder="Cth: Termin bayar cash / tempo 3 hari"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">Simpan Pelanggan</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentActiveTab = '<?= $tab ?>';

function switchTab(tabName) {
    currentActiveTab = tabName;
    const secFarmers = document.getElementById('sectionFarmers');
    const secCustomers = document.getElementById('sectionCustomers');
    const btnFarmers = document.getElementById('tabBtnFarmers');
    const btnCustomers = document.getElementById('tabBtnCustomers');

    if (tabName === 'farmers') {
        secFarmers.style.display = 'block';
        secCustomers.style.display = 'none';
        btnFarmers.className = 'btn btn-sm rounded-pill flex-fill fw-bold py-2 btn-success text-white shadow-sm';
        btnCustomers.className = 'btn btn-sm rounded-pill flex-fill fw-bold py-2 btn-light text-muted';
        window.history.replaceState(null, null, '?tab=farmers');
    } else {
        secFarmers.style.display = 'none';
        secCustomers.style.display = 'block';
        btnFarmers.className = 'btn btn-sm rounded-pill flex-fill fw-bold py-2 btn-light text-muted';
        btnCustomers.className = 'btn btn-sm rounded-pill flex-fill fw-bold py-2 btn-success text-white shadow-sm';
        window.history.replaceState(null, null, '?tab=customers');
    }
}

function openAddContactModal() {
    if (currentActiveTab === 'farmers') {
        new bootstrap.Modal(document.getElementById('modalAddFarmer')).show();
    } else {
        new bootstrap.Modal(document.getElementById('modalAddCustomer')).show();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('contactSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.contact-card-item');
            items.forEach(el => {
                const text = el.getAttribute('data-search') || '';
                el.style.display = text.includes(q) ? 'block' : 'none';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
