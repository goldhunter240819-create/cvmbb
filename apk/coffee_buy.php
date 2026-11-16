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

        $paymentStatus = sanitize($_POST['payment_status'] ?? 'LUNAS');
        if (!in_array($paymentStatus, ['LUNAS', 'BELUM_BAYAR', 'SEBAGIAN'])) {
            $paymentStatus = 'LUNAS';
        }

        if ($paymentStatus === 'LUNAS') {
            $paidAmount = $grandTotal;
            $remaining = 0;
        } elseif ($paymentStatus === 'SEBAGIAN') {
            $inputPaid = (float)str_replace(['.', ','], ['', '.'], $_POST['paid_amount'] ?? '0');
            if ($inputPaid >= $grandTotal) {
                $paymentStatus = 'LUNAS';
                $paidAmount = $grandTotal;
                $remaining = 0;
            } elseif ($inputPaid <= 0) {
                $paymentStatus = 'BELUM_BAYAR';
                $paidAmount = 0;
                $remaining = $grandTotal;
            } else {
                $paidAmount = $inputPaid;
                $remaining = max(0, $grandTotal - $paidAmount);
            }
        } else {
            // BELUM_BAYAR (TEMPO / HUTANG)
            $paidAmount = 0;
            $remaining = $grandTotal;
        }

        if ($netWeight > 0 && $pricePerKg > 0) {
            $fStmt = $db->prepare("SELECT id, phone FROM farmers WHERE name = ? LIMIT 1");
            $fStmt->execute([$farmerName]);
            $farmerRow = $fStmt->fetch();
            $farmerId = $farmerRow['id'] ?? null;
            $farmerPhone = $farmerRow['phone'] ?? null;

            if (!$farmerId) {
                $db->prepare("INSERT INTO farmers (code, name) VALUES (?, ?)")->execute(['FRM-' . strtoupper(substr(uniqid(), -4)), $farmerName]);
                $farmerId = $db->lastInsertId();
            }

            $stmt = $db->prepare("
                INSERT INTO purchases (
                    invoice_number, purchase_date, partner_type, farmer_id, gross_weight, tare_weight, net_weight,
                    price_per_kg, grand_total, paid_amount, payment_status, notes, created_by
                ) VALUES (?, ?, 'FARMER', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceNumber, $purchaseDate, $farmerId, $grossWeight, $tareWeight, $netWeight,
                $pricePerKg, $grandTotal, $paidAmount, $paymentStatus, $notes, Auth::id()
            ]);
            $newPurchaseId = $db->lastInsertId();

            if ($remaining > 0) {
                $payStatus = ($paidAmount > 0) ? 'SEBAGIAN' : 'BELUM_LUNAS';
                $db->prepare("
                    INSERT INTO payables (reference_number, business_line, creditor_name, creditor_phone, total_amount, paid_amount, status, notes)
                    VALUES (?, 'Kopi Petani', ?, ?, ?, ?, ?, ?)
                ")->execute([$invoiceNumber, $farmerName, $farmerPhone, $grandTotal, $paidAmount, $payStatus, $notes]);
            }

            log_audit('CREATE_COFFEE_PURCHASE_APK', 'apk', $newPurchaseId, "Mobile APK mencatat pembelian {$invoiceNumber} dari {$farmerName} ({$paymentStatus})");
            flash_set('success', "Pembelian dari {$farmerName} berhasil dicatat!");
            redirect('apk/coffee_buy.php?just_added=' . $newPurchaseId);
        }
    } elseif ($action === 'update_farmer_phone') {
        header('Content-Type: application/json');
        $farmerId = (int)($_POST['farmer_id'] ?? 0);
        $phone = sanitize($_POST['phone'] ?? '');
        if ($farmerId > 0 && !empty($phone)) {
            $db->prepare("UPDATE farmers SET phone = ? WHERE id = ?")->execute([$phone, $farmerId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
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
    SELECT p.*, f.name AS farmer_name, f.phone AS farmer_phone, f.id AS farmer_id 
    FROM purchases p 
    LEFT JOIN farmers f ON p.farmer_id = f.id 
    ORDER BY p.purchase_date DESC, p.id DESC 
    LIMIT 30
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
        <span>Sistem: <strong class="text-white"><i class="bi bi-shield-check me-1"></i>MBB Field</strong></span>
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
        <?php
        $payStatus = $p['payment_status'] ?? 'LUNAS';
        $grandTotal = (float)($p['grand_total'] ?? 0);
        $paidAmount = (float)($p['paid_amount'] ?? 0);
        $remaining  = max(0, $grandTotal - $paidAmount);
        ?>
        <div class="mobile-card mb-2">
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

                <!-- Status Badge -->
                <?php if ($payStatus === 'LUNAS'): ?>
                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                        <i class="bi bi-check-circle-fill me-1"></i>Lunas
                    </span>
                <?php elseif ($payStatus === 'SEBAGIAN'): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                        <i class="bi bi-pie-chart-fill me-1"></i>DP / Panjar
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                        <i class="bi bi-clock-history me-1"></i>Tempo / Hutang
                    </span>
                <?php endif; ?>
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

            <?php if ($payStatus === 'SEBAGIAN'): ?>
                <div class="mt-2 p-2 rounded-3 bg-warning-subtle border border-warning border-opacity-25" style="font-size: 0.76rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted"><i class="bi bi-cash-stack text-success me-1"></i>Sudah Dikasih (DP):</span>
                        <strong class="text-success fw-bold"><?= format_rupiah($paidAmount) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle-fill me-1"></i>Sisa Hutang Petani:</span>
                        <strong class="text-danger fw-bold fs-6"><?= format_rupiah($remaining) ?></strong>
                    </div>
                </div>
            <?php elseif ($payStatus === 'BELUM_BAYAR'): ?>
                <div class="mt-2 p-2 rounded-3 bg-danger-subtle border border-danger border-opacity-25 d-flex justify-content-between align-items-center" style="font-size: 0.76rem;">
                    <span class="text-danger fw-semibold"><i class="bi bi-clock-history me-1"></i>Hutang Belum Dibayar:</span>
                    <strong class="text-danger fw-bold fs-6"><?= format_rupiah($grandTotal) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Tombol Kirim Struk WA -->
            <div class="pt-2 mt-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill w-100 fw-semibold d-flex align-items-center justify-content-center gap-2 py-1" 
                        onclick='prepareWaReceipt(<?= json_encode([
                            "id"           => (int)$p["id"],
                            "farmer_id"    => (int)($p["farmer_id"] ?? 0),
                            "farmer_name"  => $p["farmer_name"] ?? "Mitra Petani",
                            "farmer_phone" => $p["farmer_phone"] ?? "",
                            "invoice"      => $p["invoice_number"],
                            "date"         => date("d/m/Y", strtotime($p["purchase_date"])),
                            "gross"        => (float)$p["gross_weight"],
                            "tare"         => (float)$p["tare_weight"],
                            "net"          => (float)$p["net_weight"],
                            "price"        => (float)$p["price_per_kg"],
                            "total"        => (float)$p["grand_total"],
                            "paid"         => (float)$p["paid_amount"],
                            "status"       => $payStatus,
                            "notes"        => $p["notes"] ?? ""
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                    <i class="bi bi-whatsapp fs-6"></i>
                    <span style="font-size: 0.78rem;">Kirim Struk ke WA</span>
                </button>
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
                    <label class="form-label small fw-bold mb-1 d-block">Nama Petani / Pemasok</label>
                    <div class="input-group">
                        <input type="text" name="farmer_name" id="buy_farmer_name" list="farmerList" class="form-control form-control-sm" placeholder="Ketik atau pilih petani..." value="<?= e($_GET['farmer_name'] ?? '') ?>" required autocomplete="off">
                        <button type="button" class="btn btn-sm btn-success px-2 fw-semibold d-flex align-items-center gap-1" onclick="openFarmerPicker()" title="Buka Buku Kontak Petani">
                            <i class="bi bi-person-lines-fill"></i>
                            <span style="font-size: 0.72rem;">Buku</span>
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
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Status Pembayaran</label>
                        <select name="payment_status" id="b_payment_status" class="form-select form-select-sm fw-bold text-success" onchange="toggleBuyPaymentFields()">
                            <option value="LUNAS" selected>Lunas / Cash (Tunai)</option>
                            <option value="SEBAGIAN">DP / Panjar (Sebagian)</option>
                            <option value="BELUM_BAYAR">Tempo / Hutang (Belum Bayar)</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Catatan Mutu / KA</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Cth: KA 13%, Asalan">
                    </div>
                </div>

                <!-- CONTAINER INPUT DP (Jika Pembayaran Sebagian / DP) -->
                <div id="box_dp_payment" class="p-3 mb-2 rounded-3 border border-warning bg-warning bg-opacity-10" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-warning border-opacity-25">
                        <span class="small fw-bold text-dark"><i class="bi bi-cash-coin text-warning me-1"></i>Rincian DP (Uang Diberikan)</span>
                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.68rem;">Pembayaran DP</span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1 text-dark" style="font-size: 0.78rem;">
                            Nominal DP yang Sudah Dikasih (Rp) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white fw-bold text-muted">Rp</span>
                            <input type="number" name="paid_amount" id="b_paid_amount" class="form-control form-control-sm fw-bold text-success fs-6" placeholder="0" min="0" oninput="recalcPayment()">
                        </div>
                        <div class="form-text small" style="font-size: 0.7rem;">Ketik jumlah uang muka/panjar yang sudah diserahkan ke petani.</div>
                    </div>
                    <div class="p-2 bg-white rounded-2 border d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted d-block" style="font-size: 0.7rem;">Sisa Hutang ke Petani:</span>
                            <strong class="text-danger fs-6" id="b_remaining_disp">Rp 0</strong>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25" style="font-size: 0.68rem;">
                            Otomatis Masuk Hutang
                        </span>
                    </div>
                </div>

                <div class="p-2 bg-light rounded-3 mb-2 text-center">
                    <span class="text-muted small d-block">Estimasi Total Pembelian:</span>
                    <h5 class="fw-bold text-success mb-0" id="b_total">Rp 0</h5>
                </div>
            </div>
            <div class="modal-footer border-0 pt-1 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted fw-semibold py-2" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="submit" class="btn btn-sm btn-success w-100 fw-bold py-2 shadow-sm">
                            <i class="bi bi-check-lg me-1"></i>Simpan Pembelian
                        </button>
                    </div>
                </div>
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
                <div class="input-group input-group-sm mb-3 rounded-pill overflow-hidden bg-white border shadow-sm">
                    <span class="input-group-text bg-white border-0 ps-3 pe-2 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="searchFarmerInput" class="form-control border-0 ps-1" placeholder="Cari nama, desa, no hp..." style="box-shadow: none;">
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">Pilih Mitra (<?= count($farmersList) ?> Kontak)</span>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.72rem;" onclick="showAddFarmerForm()">
                        <i class="bi bi-plus-lg me-1"></i>Mitra Baru
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
                                    Pilih &raquo;
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL INPUT NOMOR WHATSAPP PETANI -->
<div class="modal fade" id="modalInputWaPhone" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-whatsapp me-2"></i>Kirim Struk WhatsApp</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-3">
                    Nomor WhatsApp mitra <strong id="wa_prompt_farmer_name" class="text-dark">Petani</strong> belum tersimpan di kontak. Masukkan nomor WhatsApp untuk mengirimkan nota timbangan:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Nomor WhatsApp Petani</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-whatsapp text-success"></i></span>
                        <input type="tel" id="wa_prompt_phone_input" class="form-control" placeholder="Contoh: 08123456789 atau 628..." autocomplete="tel">
                    </div>
                    <div class="form-text small" style="font-size: 0.72rem;">Nomor bisa diawali dengan 08 atau 62.</div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="wa_prompt_save_contact" checked>
                    <label class="form-check-label small" for="wa_prompt_save_contact">
                        Simpan nomor ini ke data mitra petani
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted fw-semibold py-2" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="button" class="btn btn-sm btn-success w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-1" onclick="confirmAndSendWa()">
                            <i class="bi bi-eye-fill me-1"></i>Lihat & Kirim Struk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW STRUK DIGITAL ALA GOPAY / E-WALLET -->
<div class="modal fade" id="modalReceiptPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white py-2 px-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt text-success fs-5"></i>
                    <span class="small fw-bold text-white">Struk Transaksi Digital (GoPay Style)</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-3 bg-light">
                <!-- STRUK CARD TO BE CONVERTED TO IMAGE -->
                <div id="receiptCardToCapture" class="p-4 bg-white rounded-4 shadow-sm mx-auto position-relative" style="max-width: 350px; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; border: 1px solid #e2e8f0;">
                    <!-- Header Struk -->
                    <div class="text-center pb-3 border-bottom border-dashed">
                        <div class="d-inline-flex p-2 rounded-circle bg-success bg-opacity-10 text-success mb-2">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                        </div>
                        <div class="badge bg-success text-white px-3 py-1 rounded-pill small fw-bold mb-1">
                            TIMBANGAN BERHASIL
                        </div>
                        <h3 class="fw-bold text-dark mb-0 mt-1" id="rc_grand_total">Rp 0</h3>
                        <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                            CV. MAJU BERKAH BERSAMA
                        </div>
                    </div>

                    <!-- Detail Transaksi Grid -->
                    <div class="py-3 border-bottom border-dashed" style="font-size: 0.8rem;">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">No. Nota</span>
                            <strong class="font-monospace text-dark" id="rc_invoice">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tanggal</span>
                            <span class="fw-semibold text-dark" id="rc_date">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Mitra Petani</span>
                            <strong class="text-dark" id="rc_farmer_name">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Bruto / Tarra</span>
                            <span class="text-dark" id="rc_bruto_tarra">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-success bg-opacity-10 rounded-2">
                            <span class="fw-bold text-success">BERAT NETTO</span>
                            <strong class="fw-bold text-success fs-6" id="rc_net">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Harga Beli / Kg</span>
                            <strong class="text-dark" id="rc_price">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Status Pembayaran</span>
                            <span id="rc_status_badge" class="badge bg-success-subtle text-success">Lunas</span>
                        </div>
                        <div id="rc_dp_box" style="display: none;" class="p-2 rounded-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 mt-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Uang Muka (DP):</span>
                                <strong class="text-dark" id="rc_paid_disp">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between" style="font-size: 0.75rem;">
                                <span class="text-danger fw-semibold">Sisa Tagihan:</span>
                                <strong class="text-danger" id="rc_remaining_disp">Rp 0</strong>
                            </div>
                        </div>
                        <div id="rc_notes_container" class="mt-2 text-muted" style="font-size: 0.72rem; display: none;">
                            Catatan: <span id="rc_notes_text">-</span>
                        </div>
                    </div>

                    <!-- Footer Struk -->
                    <div class="pt-3 text-center" style="font-size: 0.68rem; color: #94a3b8;">
                        <i class="bi bi-shield-check text-success me-1"></i>Struk Digital Sah & Resmi MBB Field System<br>
                        Gudang Air Hitam, Lampung Barat
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="modal-footer border-0 p-3 bg-white">
                <div class="row g-2 w-100">
                    <div class="col-12">
                        <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnShareWaReceipt" onclick="executeShareReceiptImage()">
                            <i class="bi bi-whatsapp fs-5"></i>
                            <span>Kirim Gambar & Teks ke WA</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 py-2 fw-semibold" onclick="downloadReceiptPngOnly()">
                            <i class="bi bi-download me-1"></i>Unduh Gambar
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-sm btn-outline-success w-100 py-2 fw-semibold" onclick="sendWaTextDirectOnly()">
                            <i class="bi bi-chat-text me-1"></i>Kirim Teks Saja
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let addBuyModalInstance = null;
let pickFarmerModalInstance = null;
let inputWaModalInstance = null;
let receiptPreviewModalInstance = null;
let currentWaReceiptData = null;

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

function getInputWaModal() {
    if (!inputWaModalInstance) {
        inputWaModalInstance = new bootstrap.Modal(document.getElementById('modalInputWaPhone'));
    }
    return inputWaModalInstance;
}

function getReceiptPreviewModal() {
    if (!receiptPreviewModalInstance) {
        receiptPreviewModalInstance = new bootstrap.Modal(document.getElementById('modalReceiptPreview'));
    }
    return receiptPreviewModalInstance;
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

function toggleBuyPaymentFields() {
    const sel = document.getElementById('b_payment_status');
    const box = document.getElementById('box_dp_payment');
    if (sel && box) {
        if (sel.value === 'SEBAGIAN') {
            box.style.display = 'block';
            document.getElementById('b_paid_amount')?.focus();
            recalcPayment();
        } else {
            box.style.display = 'none';
        }
    }
}

function recalcPayment() {
    const g = parseFloat(document.getElementById('b_gross')?.value) || 0;
    const t = parseFloat(document.getElementById('b_tare')?.value) || 0;
    const p = parseFloat(document.getElementById('b_price')?.value) || 0;
    const net = Math.max(0, g - t);
    const grandTotal = net * p;

    const paidInput = document.getElementById('b_paid_amount');
    const paid = parseFloat(paidInput?.value) || 0;
    const remaining = Math.max(0, grandTotal - paid);

    const remDisp = document.getElementById('b_remaining_disp');
    if (remDisp) {
        remDisp.textContent = 'Rp ' + remaining.toLocaleString('id-ID');
    }
}

function cleanPhoneForWa(phone) {
    if (!phone) return '';
    let cleaned = phone.replace(/[^0-9]/g, '');
    if (cleaned.startsWith('0')) {
        cleaned = '62' + cleaned.substring(1);
    } else if (cleaned.startsWith('8')) {
        cleaned = '62' + cleaned;
    }
    return cleaned;
}

function buildWaReceiptText(data) {
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    const netKg = Number(data.net || 0).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1});
    const grossKg = Number(data.gross || 0).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1});
    const tareKg = Number(data.tare || 0).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1});

    let statusText = 'LUNAS (TUNAI)';
    let paymentDetail = '';
    if (data.status === 'SEBAGIAN') {
        const remaining = Math.max(0, Number(data.total || 0) - Number(data.paid || 0));
        statusText = 'DP / SEBAGIAN';
        paymentDetail = `\n💵 *Uang Muka / DP : ${formatRp(data.paid)}*\n⏳ *Sisa Tagihan : ${formatRp(remaining)}*`;
    } else if (data.status === 'BELUM_BAYAR') {
        statusText = 'TEMPO / HUTANG';
    }

    let noteText = data.notes ? `\n📝 *Catatan :* ${data.notes}` : '';

    return `🧾 *NOTA TIMBANGAN KOPI*\n*CV. MAJU BERKAH BERSAMA*\n━━━━━━━━━━━━━━━━━━━\n` +
           `No. Nota : *${data.invoice}*\n` +
           `Tanggal  : ${data.date}\n` +
           `Mitra    : *${data.farmer_name}*\n` +
           `━━━━━━━━━━━━━━━━━━━\n` +
           `• Bruto : ${grossKg} Kg\n` +
           `• Tarra : ${tareKg} Kg\n` +
           `• *NETTO : ${netKg} Kg*\n` +
           `• Harga : ${formatRp(data.price)} / Kg\n` +
           `━━━━━━━━━━━━━━━━━━━\n` +
           `💰 *TOTAL : ${formatRp(data.total)}*\n` +
           `💳 *STATUS : ${statusText}*` +
           paymentDetail +
           noteText +
           `\n━━━━━━━━━━━━━━━━━━━\n` +
           `_Terima kasih atas kerja samanya._\n_Struk digital sah MBB Field System._`;
}

function populateReceiptCard(data) {
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    const netKg = Number(data.net || 0).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1});
    const grossKg = Number(data.gross || 0).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1});
    const tareKg = Number(data.tare || 0).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1});

    document.getElementById('rc_grand_total').textContent = formatRp(data.total);
    document.getElementById('rc_invoice').textContent = data.invoice;
    document.getElementById('rc_date').textContent = data.date;
    document.getElementById('rc_farmer_name').textContent = data.farmer_name;
    document.getElementById('rc_bruto_tarra').textContent = `${grossKg} Kg (Tarra: ${tareKg} Kg)`;
    document.getElementById('rc_net').textContent = `${netKg} Kg`;
    document.getElementById('rc_price').textContent = `${formatRp(data.price)} / Kg`;

    const badge = document.getElementById('rc_status_badge');
    const dpBox = document.getElementById('rc_dp_box');

    if (data.status === 'LUNAS') {
        badge.className = 'badge bg-success-subtle text-success border border-success border-opacity-25';
        badge.textContent = 'Lunas / Tunai';
        dpBox.style.display = 'none';
    } else if (data.status === 'SEBAGIAN') {
        badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25';
        badge.textContent = 'DP / Sebagian';
        dpBox.style.display = 'block';
        document.getElementById('rc_paid_disp').textContent = formatRp(data.paid);
        const rem = Math.max(0, Number(data.total || 0) - Number(data.paid || 0));
        document.getElementById('rc_remaining_disp').textContent = formatRp(rem);
    } else {
        badge.className = 'badge bg-danger-subtle text-danger border border-danger border-opacity-25';
        badge.textContent = 'Tempo / Hutang';
        dpBox.style.display = 'none';
    }

    const notesContainer = document.getElementById('rc_notes_container');
    if (data.notes) {
        notesContainer.style.display = 'block';
        document.getElementById('rc_notes_text').textContent = data.notes;
    } else {
        notesContainer.style.display = 'none';
    }
}

function prepareWaReceipt(data) {
    currentWaReceiptData = data;
    populateReceiptCard(data);

    const phone = (data.farmer_phone || '').trim();
    if (!phone) {
        document.getElementById('wa_prompt_farmer_name').textContent = data.farmer_name || 'Petani';
        document.getElementById('wa_prompt_phone_input').value = '';
        getInputWaModal().show();
        setTimeout(() => {
            document.getElementById('wa_prompt_phone_input')?.focus();
        }, 300);
    } else {
        getReceiptPreviewModal().show();
    }
}

function confirmAndSendWa() {
    if (!currentWaReceiptData) return;
    const phoneInput = document.getElementById('wa_prompt_phone_input');
    const phoneVal = (phoneInput.value || '').trim();
    if (!phoneVal) {
        Swal.fire({
            title: 'Nomor WA Kosong',
            text: 'Silakan ketik nomor WhatsApp mitra petani terlebih dahulu untuk mengirimkan struk.',
            icon: 'warning',
            confirmButtonText: 'Siap, Ketik Nomor',
            confirmButtonColor: '#059669'
        }).then(() => {
            phoneInput.focus();
        });
        return;
    }

    const shouldSave = document.getElementById('wa_prompt_save_contact').checked;
    if (shouldSave && currentWaReceiptData.farmer_id) {
        const fd = new FormData();
        fd.append('action', 'update_farmer_phone');
        fd.append('farmer_id', currentWaReceiptData.farmer_id);
        fd.append('phone', phoneVal);

        fetch(window.location.href, {
            method: 'POST',
            body: fd
        }).then(r => r.json()).catch(() => {});
    }

    currentWaReceiptData.farmer_phone = phoneVal;
    getInputWaModal().hide();

    // Langsung buka preview struk bergaya GoPay
    setTimeout(() => {
        getReceiptPreviewModal().show();
    }, 300);
}

async function executeShareReceiptImage() {
    if (!currentWaReceiptData) return;
    const btn = document.getElementById('btnShareWaReceipt');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyiapkan Gambar...';
    btn.disabled = true;

    try {
        const receiptCard = document.getElementById('receiptCardToCapture');
        const canvas = await html2canvas(receiptCard, {
            scale: 2.5,
            useCORS: true,
            backgroundColor: '#ffffff'
        });

        canvas.toBlob(async (blob) => {
            const fileName = `Struk_MBB_${currentWaReceiptData.invoice.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
            const file = new File([blob], fileName, { type: 'image/png' });
            const caption = buildWaReceiptText(currentWaReceiptData);

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'Struk Timbangan Kopi MBB',
                        text: caption
                    });
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    return;
                } catch (shareErr) {
                    console.log('User cancelled share', shareErr);
                }
            }

            // Fallback download PNG & open WA
            const link = document.createElement('a');
            link.download = fileName;
            link.href = canvas.toDataURL('image/png');
            link.click();

            const phone = cleanPhoneForWa(currentWaReceiptData.farmer_phone);
            const waUrl = phone 
                ? `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(caption)}`
                : `https://api.whatsapp.com/send?text=${encodeURIComponent(caption)}`;
            window.open(waUrl, '_blank');

            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 'image/png');
    } catch (err) {
        console.error(err);
        btn.innerHTML = originalText;
        btn.disabled = false;
        Swal.fire({
            title: 'Gagal Membuat Gambar',
            text: 'Terjadi kendala saat generate gambar struk. Mengalihkan ke teks WA biasa...',
            icon: 'info',
            confirmButtonColor: '#059669',
            timer: 2000,
            showConfirmButton: false
        });
        sendWaTextDirectOnly();
    }
}

async function downloadReceiptPngOnly() {
    const receiptCard = document.getElementById('receiptCardToCapture');
    const canvas = await html2canvas(receiptCard, { scale: 2.5, useCORS: true, backgroundColor: '#ffffff' });
    const link = document.createElement('a');
    link.download = `Struk_MBB_${(currentWaReceiptData?.invoice || 'nota').replace(/[^a-zA-Z0-9]/g, '_')}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function sendWaTextDirectOnly() {
    if (!currentWaReceiptData) return;
    const phone = cleanPhoneForWa(currentWaReceiptData.farmer_phone);
    const caption = buildWaReceiptText(currentWaReceiptData);
    const waUrl = phone 
        ? `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(caption)}`
        : `https://api.whatsapp.com/send?text=${encodeURIComponent(caption)}`;
    window.open(waUrl, '_blank');
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

        recalcPayment();
    }

    if (g && t && p) {
        g.addEventListener('input', recalc);
        t.addEventListener('input', recalc);
        p.addEventListener('input', recalc);
    }

    const payStatusSel = document.getElementById('b_payment_status');
    if (payStatusSel) {
        payStatusSel.addEventListener('change', toggleBuyPaymentFields);
    }
    const buyModalEl = document.getElementById('modalAddBuy');
    if (buyModalEl) {
        buyModalEl.addEventListener('shown.bs.modal', function() {
            toggleBuyPaymentFields();
        });
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
