<?php
/**
 * MBB MOBILE APK - Penjualan Kopi Trading
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

// Form Handler
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
            // BELUM_BAYAR
            $paidAmount = 0;
            $remaining = $grandTotal;
        }

        if ($weightKg > 0 && $pricePerKg > 0) {
            $cStmt = $db->prepare("SELECT id, phone FROM customers WHERE name = ? LIMIT 1");
            $cStmt->execute([$customerName]);
            $cRow = $cStmt->fetch();
            $customerId = $cRow['id'] ?? null;
            $customerPhone = $cRow['phone'] ?? null;

            if (!$customerId) {
                $db->prepare("INSERT INTO customers (code, name) VALUES (?, ?)")->execute(['CUST-' . strtoupper(substr(uniqid(), -4)), $customerName]);
                $customerId = $db->lastInsertId();
            }

            $stmt = $db->prepare("
                INSERT INTO sales (
                    invoice_number, sale_date, customer_id, total_weight_kg, subtotal, 
                    grand_total, paid_amount, profit_amount, payment_status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceNumber, $saleDate, $customerId, $weightKg, $grandTotal,
                $grandTotal, $paidAmount, $profitAmount, $paymentStatus, $notes, Auth::id()
            ]);
            $newSaleId = $db->lastInsertId();

            if ($remaining > 0) {
                $recStatus = ($paidAmount > 0) ? 'SEBAGIAN' : 'BELUM_LUNAS';
                $db->prepare("
                    INSERT INTO receivables (reference_number, business_line, debtor_name, debtor_phone, total_amount, paid_amount, status, notes)
                    VALUES (?, 'Kopi Trading', ?, ?, ?, ?, ?, ?)
                ")->execute([$invoiceNumber, $customerName, $customerPhone, $grandTotal, $paidAmount, $recStatus, $notes]);
            }

            log_audit('CREATE_COFFEE_SALE_APK', 'apk', $newSaleId, "Mobile APK mencatat penjualan {$invoiceNumber} ke {$customerName} ({$paymentStatus})");
            flash_set('success', "Penjualan {$invoiceNumber} berhasil dicatat!");
            redirect('apk/coffee_sell.php');
        }
    } elseif ($action === 'update_customer_phone') {
        header('Content-Type: application/json');
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $phone = sanitize($_POST['phone'] ?? '');
        if ($customerId > 0 && !empty($phone)) {
            $db->prepare("UPDATE customers SET phone = ? WHERE id = ?")->execute([$phone, $customerId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
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
    SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.id AS customer_id 
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
        <?php
        $payStatus = $s['payment_status'] ?? 'LUNAS';
        $grandTotal = (float)($s['grand_total'] ?? 0);
        $paidAmount = (float)($s['paid_amount'] ?? 0);
        $remaining  = max(0, $grandTotal - $paidAmount);
        ?>
        <div class="mobile-card mb-2">
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

                <!-- Status Badge -->
                <?php if ($payStatus === 'LUNAS'): ?>
                    <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                        <i class="bi bi-check-circle-fill me-1"></i>Lunas
                    </span>
                <?php elseif ($payStatus === 'SEBAGIAN'): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                        <i class="bi bi-pie-chart-fill me-1"></i>DP (Sebagian)
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                        <i class="bi bi-clock-history me-1"></i>Tempo / Piutang
                    </span>
                <?php endif; ?>
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

            <?php if ($payStatus === 'SEBAGIAN'): ?>
                <div class="mt-2 p-2 rounded-3 bg-warning-subtle border border-warning border-opacity-25" style="font-size: 0.76rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted"><i class="bi bi-cash-stack text-success me-1"></i>DP Diterima:</span>
                        <strong class="text-success fw-bold"><?= format_rupiah($paidAmount) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-danger fw-semibold"><i class="bi bi-exclamation-circle-fill me-1"></i>Sisa Piutang:</span>
                        <strong class="text-danger fw-bold fs-6"><?= format_rupiah($remaining) ?></strong>
                    </div>
                </div>
            <?php elseif ($payStatus === 'BELUM_BAYAR'): ?>
                <div class="mt-2 p-2 rounded-3 bg-danger-subtle border border-danger border-opacity-25 d-flex justify-content-between align-items-center" style="font-size: 0.76rem;">
                    <span class="text-danger fw-semibold"><i class="bi bi-clock-history me-1"></i>Piutang Belum Dibayar:</span>
                    <strong class="text-danger fw-bold fs-6"><?= format_rupiah($grandTotal) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Tombol Kirim Faktur WA -->
            <div class="pt-2 mt-2 border-top">
                <button type="button" class="btn btn-sm btn-outline-success rounded-pill w-100 fw-semibold d-flex align-items-center justify-content-center gap-2 py-1" 
                        onclick='prepareSellWaInvoice(<?= json_encode([
                            "id"             => (int)$s["id"],
                            "customer_id"    => (int)($s["customer_id"] ?? 0),
                            "customer_name"  => $s["customer_name"] ?? "Pembeli",
                            "customer_phone" => $s["customer_phone"] ?? "",
                            "invoice"        => $s["invoice_number"],
                            "date"           => date("d/m/Y", strtotime($s["sale_date"])),
                            "weight"         => (float)$s["total_weight_kg"],
                            "price"          => $s["total_weight_kg"] > 0 ? round($s["grand_total"] / $s["total_weight_kg"]) : 0,
                            "total"          => (float)$s["grand_total"],
                            "paid"           => (float)$s["paid_amount"],
                            "status"         => $payStatus,
                            "notes"          => $s["notes"] ?? ""
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                    <i class="bi bi-whatsapp fs-6"></i>
                    <span style="font-size: 0.78rem;">Kirim Faktur WA (Gambar & Teks)</span>
                </button>
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
                    <label class="form-label small fw-bold mb-1 d-block">Nama Pembeli / Eksportir</label>
                    <div class="input-group">
                        <input type="text" name="customer_name" id="sell_customer_name" list="customerList" class="form-control form-control-sm" placeholder="Ketik atau pilih pembeli..." value="<?= e($_GET['customer_name'] ?? '') ?>" required autocomplete="off">
                        <button type="button" class="btn btn-sm btn-primary px-2 fw-semibold d-flex align-items-center gap-1" onclick="openCustomerPicker()" title="Buka Buku Pelanggan">
                            <i class="bi bi-person-lines-fill"></i>
                            <span style="font-size: 0.72rem;">Buku</span>
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
                        <select name="payment_status" id="s_payment_status" class="form-select form-select-sm fw-bold text-success" onchange="toggleSellPaymentFields()">
                            <option value="LUNAS" selected>Lunas / Cash (Tunai)</option>
                            <option value="SEBAGIAN">DP / Panjar (Sebagian)</option>
                            <option value="BELUM_BAYAR">Tempo / Piutang</option>
                        </select>
                    </div>
                </div>

                <!-- CONTAINER INPUT DP (Jika Pembayaran Sebagian / DP) -->
                <div id="box_sell_dp_payment" class="p-3 mb-2 rounded-3 border border-warning bg-warning bg-opacity-10" style="display: none;">
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom border-warning border-opacity-25">
                        <span class="small fw-bold text-dark"><i class="bi bi-cash-coin text-warning me-1"></i>Rincian Uang Muka (DP)</span>
                        <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.68rem;">Pembayaran DP</span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1 text-dark" style="font-size: 0.78rem;">
                            Nominal DP yang Diterima (Rp) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white fw-bold text-muted">Rp</span>
                            <input type="number" name="paid_amount" id="s_paid_amount" class="form-control form-control-sm fw-bold text-success fs-6" placeholder="0" min="0" oninput="recalcSellPayment()">
                        </div>
                        <div class="form-text small" style="font-size: 0.7rem;">Ketik jumlah uang muka/DP yang sudah ditransfer/diterima dari pembeli.</div>
                    </div>
                    <div class="p-2 bg-white rounded-2 border d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted d-block" style="font-size: 0.7rem;">Sisa Piutang yang Harus Ditagih:</span>
                            <strong class="text-danger fs-6" id="s_remaining_disp">Rp 0</strong>
                        </div>
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25" style="font-size: 0.68rem;">
                            Otomatis Masuk Piutang
                        </span>
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
            <div class="modal-footer border-0 pt-1 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted fw-semibold py-2" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold py-2 shadow-sm">
                            <i class="bi bi-check-lg me-1"></i>Simpan Faktur
                        </button>
                    </div>
                </div>
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

<!-- MODAL INPUT NOMOR WA PELANGGAN -->
<div class="modal fade" id="modalInputSellWaPhone" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-whatsapp me-2"></i>Kirim Faktur WhatsApp</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <p class="small text-muted mb-3">
                    Nomor WhatsApp pembeli <strong id="wa_prompt_cust_name" class="text-dark">Pelanggan</strong> belum tersimpan di kontak. Masukkan nomor WhatsApp untuk mengirimkan faktur:
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Nomor WhatsApp Pembeli</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted"><i class="bi bi-whatsapp text-success"></i></span>
                        <input type="tel" id="wa_prompt_cust_phone_input" class="form-control" placeholder="Contoh: 08123456789 atau 628..." autocomplete="tel">
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="wa_prompt_cust_save" checked>
                    <label class="form-check-label small" for="wa_prompt_cust_save">
                        Simpan nomor ini ke data kontak pelanggan
                    </label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted fw-semibold py-2" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="button" class="btn btn-sm btn-primary w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-1" onclick="confirmAndSendSellWa()">
                            <i class="bi bi-receipt me-1"></i>Lanjut ke Struk WA
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW STRUK/FAKTUR DIGITAL ALA GOPAY / E-WALLET -->
<div class="modal fade" id="modalSellReceiptPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white py-2 px-3 border-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt text-primary fs-5"></i>
                    <span class="small fw-bold text-white">Faktur Penjualan Digital (GoPay Style)</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-3 bg-light">
                <!-- STRUK CARD TO BE CONVERTED TO IMAGE -->
                <div id="receiptSellCardToCapture" class="p-4 bg-white rounded-4 shadow-sm mx-auto position-relative" style="max-width: 350px; font-family: 'Plus Jakarta Sans', sans-serif; color: #1e293b; border: 1px solid #e2e8f0;">
                    <!-- Header Struk -->
                    <div class="text-center pb-3 border-bottom border-dashed">
                        <div class="d-inline-flex p-2 rounded-circle bg-primary bg-opacity-10 text-primary mb-2">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                        </div>
                        <div class="badge bg-primary text-white px-3 py-1 rounded-pill small fw-bold mb-1">
                            PENJUALAN BERHASIL
                        </div>
                        <h3 class="fw-bold text-dark mb-0 mt-1" id="rc_sell_grand_total">Rp 0</h3>
                        <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                            CV. MAJU BERKAH BERSAMA
                        </div>
                    </div>

                    <!-- Detail Transaksi Grid -->
                    <div class="py-3 border-bottom border-dashed" style="font-size: 0.8rem;">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">No. Faktur</span>
                            <strong class="font-monospace text-dark" id="rc_sell_invoice">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Tanggal</span>
                            <span class="fw-semibold text-dark" id="rc_sell_date">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Pembeli / Mitra</span>
                            <strong class="text-dark" id="rc_sell_customer_name">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2 p-2 bg-primary bg-opacity-10 rounded-2">
                            <span class="fw-bold text-primary">TOTAL VOLUME</span>
                            <strong class="fw-bold text-primary fs-6" id="rc_sell_net">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Harga Jual / Kg</span>
                            <strong class="text-dark" id="rc_sell_price">-</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Status Pembayaran</span>
                            <span id="rc_sell_status_badge" class="badge bg-success-subtle text-success">Lunas</span>
                        </div>
                        <div id="rc_sell_dp_box" style="display: none;" class="p-2 rounded-2 bg-warning bg-opacity-10 border border-warning border-opacity-25 mt-2">
                            <div class="d-flex justify-content-between mb-1" style="font-size: 0.75rem;">
                                <span class="text-muted">Uang Muka (DP):</span>
                                <strong class="text-success fw-bold" id="rc_sell_paid_disp">Rp 0</strong>
                            </div>
                            <div class="d-flex justify-content-between" style="font-size: 0.75rem;">
                                <span class="text-danger fw-semibold">Sisa Tagihan / Piutang:</span>
                                <strong class="text-danger" id="rc_sell_remaining_disp">Rp 0</strong>
                            </div>
                        </div>
                        <div id="rc_sell_notes_container" class="mt-2 text-muted" style="font-size: 0.72rem; display: none;">
                            Catatan: <span id="rc_sell_notes_text">-</span>
                        </div>
                    </div>

                    <!-- Footer Struk -->
                    <div class="pt-3 text-center" style="font-size: 0.68rem; color: #94a3b8;">
                        <i class="bi bi-shield-check text-primary me-1"></i>Faktur Digital Sah & Resmi MBB Field System<br>
                        Gudang Air Hitam, Lampung Barat
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="modal-footer border-0 p-3 bg-white">
                <div class="row g-2 w-100">
                    <div class="col-12">
                        <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow-sm d-flex align-items-center justify-content-center gap-2" id="btnShareSellWaReceipt" onclick="executeShareSellReceiptImage()">
                            <i class="bi bi-whatsapp fs-5"></i>
                            <span>Kirim Gambar & Teks ke WA</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 py-2 fw-semibold" onclick="downloadSellReceiptPngOnly()">
                            <i class="bi bi-download me-1"></i>Unduh Gambar
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="btn btn-sm btn-outline-success w-100 py-2 fw-semibold" onclick="sendSellWaTextDirectOnly()">
                            <i class="bi bi-chat-text me-1"></i>Kirim Teks Saja
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let addSaleModalInstance = null;
let pickCustomerModalInstance = null;
let inputSellWaModalInstance = null;
let receiptSellPreviewModalInstance = null;
let currentSellWaData = null;

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

function getInputSellWaModal() {
    if (!inputSellWaModalInstance) {
        inputSellWaModalInstance = new bootstrap.Modal(document.getElementById('modalInputSellWaPhone'));
    }
    return inputSellWaModalInstance;
}

function getSellReceiptPreviewModal() {
    if (!receiptSellPreviewModalInstance) {
        receiptSellPreviewModalInstance = new bootstrap.Modal(document.getElementById('modalSellReceiptPreview'));
    }
    return receiptSellPreviewModalInstance;
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

function toggleSellPaymentFields() {
    const sel = document.getElementById('s_payment_status');
    const box = document.getElementById('box_sell_dp_payment');
    if (sel && box) {
        if (sel.value === 'SEBAGIAN') {
            box.style.display = 'block';
            recalcSellPayment();
            setTimeout(() => {
                document.getElementById('s_paid_amount')?.focus();
            }, 100);
        } else {
            box.style.display = 'none';
        }
    }
}

function recalcSellPayment() {
    const weight = parseFloat(document.getElementById('s_weight')?.value) || 0;
    const price  = parseFloat(document.getElementById('s_price')?.value) || 0;
    const total  = weight * price;

    const paidInput = document.getElementById('s_paid_amount');
    const paid = parseFloat(paidInput?.value) || 0;
    const remaining = Math.max(0, total - paid);

    const remDisp = document.getElementById('s_remaining_disp');
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

function buildSellWaInvoiceText(data) {
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    const weightKg = Number(data.weight || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2});

    let statusText = 'LUNAS (CASH/TRANSFER)';
    let paymentDetail = '';
    if (data.status === 'SEBAGIAN') {
        const remaining = Math.max(0, Number(data.total || 0) - Number(data.paid || 0));
        statusText = 'DP / SEBAGIAN';
        paymentDetail = `\n💵 *DP Diterima   : ${formatRp(data.paid)}*\n⏳ *Sisa Tagihan  : ${formatRp(remaining)}*`;
    } else if (data.status === 'BELUM_BAYAR') {
        statusText = 'TEMPO / PIUTANG';
    }

    let noteText = data.notes ? `\n📝 *Catatan :* ${data.notes}` : '';

    return `📑 *FAKTUR PENJUALAN KOPI*\n*CV. MAJU BERKAH BERSAMA*\n━━━━━━━━━━━━━━━━━━━\n` +
           `No. Faktur : *${data.invoice}*\n` +
           `Tanggal    : ${data.date}\n` +
           `Pembeli    : *${data.customer_name}*\n` +
           `━━━━━━━━━━━━━━━━━━━\n` +
           `• Total Volume : *${weightKg} Kg*\n` +
           `━━━━━━━━━━━━━━━━━━━\n` +
           `💰 *TOTAL NILAI : ${formatRp(data.total)}*\n` +
           `💳 *STATUS      : ${statusText}*` +
           paymentDetail +
           noteText +
           `\n━━━━━━━━━━━━━━━━━━━\n` +
           `_Faktur digital resmi diterbitkan oleh MBB Field System._\n_Terima kasih atas kerja samanya._`;
}

function populateSellReceiptCard(data) {
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    const weightKg = Number(data.weight || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' Kg';

    document.getElementById('rc_sell_grand_total').textContent = formatRp(data.total);
    document.getElementById('rc_sell_invoice').textContent = data.invoice || '-';
    document.getElementById('rc_sell_date').textContent = data.date || '-';
    document.getElementById('rc_sell_customer_name').textContent = data.customer_name || 'Pembeli';
    document.getElementById('rc_sell_net').textContent = weightKg;
    
    const priceVal = data.price ? formatRp(data.price) : (data.weight > 0 ? formatRp(Math.round(data.total / data.weight)) : '-');
    document.getElementById('rc_sell_price').textContent = priceVal;

    const badge = document.getElementById('rc_sell_status_badge');
    const dpBox = document.getElementById('rc_sell_dp_box');

    if (data.status === 'SEBAGIAN') {
        badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25';
        badge.textContent = 'DP / Sebagian';
        dpBox.style.display = 'block';
        document.getElementById('rc_sell_paid_disp').textContent = formatRp(data.paid);
        const rem = Math.max(0, Number(data.total || 0) - Number(data.paid || 0));
        document.getElementById('rc_sell_remaining_disp').textContent = formatRp(rem);
    } else if (data.status === 'BELUM_BAYAR') {
        badge.className = 'badge bg-danger-subtle text-danger border border-danger border-opacity-25';
        badge.textContent = 'Tempo / Piutang';
        dpBox.style.display = 'none';
    } else {
        badge.className = 'badge bg-success-subtle text-success border border-success border-opacity-25';
        badge.textContent = 'Lunas';
        dpBox.style.display = 'none';
    }

    const notesContainer = document.getElementById('rc_sell_notes_container');
    if (data.notes && data.notes.trim() !== '') {
        notesContainer.style.display = 'block';
        document.getElementById('rc_sell_notes_text').textContent = data.notes;
    } else {
        notesContainer.style.display = 'none';
    }
}

function prepareSellWaInvoice(data) {
    currentSellWaData = data;
    const phone = (data.customer_phone || '').trim();
    if (phone) {
        populateSellReceiptCard(data);
        getSellReceiptPreviewModal().show();
    } else {
        document.getElementById('wa_prompt_cust_name').textContent = data.customer_name || 'Pembeli';
        document.getElementById('wa_prompt_cust_phone_input').value = '';
        getInputSellWaModal().show();
        setTimeout(() => {
            document.getElementById('wa_prompt_cust_phone_input')?.focus();
        }, 300);
    }
}

function confirmAndSendSellWa() {
    if (!currentSellWaData) return;
    const phoneInput = document.getElementById('wa_prompt_cust_phone_input');
    const phoneVal = (phoneInput.value || '').trim();
    if (!phoneVal) {
        alert('Silakan masukkan nomor WhatsApp pembeli.');
        phoneInput.focus();
        return;
    }

    const shouldSave = document.getElementById('wa_prompt_cust_save').checked;
    if (shouldSave && currentSellWaData.customer_id) {
        const fd = new FormData();
        fd.append('action', 'update_customer_phone');
        fd.append('customer_id', currentSellWaData.customer_id);
        fd.append('phone', phoneVal);

        fetch(window.location.href, {
            method: 'POST',
            body: fd
        }).then(r => r.json()).catch(() => {});
    }

    currentSellWaData.customer_phone = phoneVal;
    getInputSellWaModal().hide();

    setTimeout(() => {
        populateSellReceiptCard(currentSellWaData);
        getSellReceiptPreviewModal().show();
    }, 300);
}

async function executeShareSellReceiptImage() {
    if (!currentSellWaData) return;
    const btn = document.getElementById('btnShareSellWaReceipt');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyiapkan Gambar...';
    btn.disabled = true;

    try {
        const receiptCard = document.getElementById('receiptSellCardToCapture');
        const canvas = await html2canvas(receiptCard, {
            scale: 2.5,
            useCORS: true,
            backgroundColor: '#ffffff'
        });

        canvas.toBlob(async (blob) => {
            const fileName = `Faktur_MBB_${currentSellWaData.invoice.replace(/[^a-zA-Z0-9]/g, '_')}.png`;
            const file = new File([blob], fileName, { type: 'image/png' });
            const caption = buildSellWaInvoiceText(currentSellWaData);

            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'Faktur Penjualan Kopi MBB',
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

            const phone = cleanPhoneForWa(currentSellWaData.customer_phone);
            const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(caption)}`;
            window.open(waUrl, '_blank');

            btn.innerHTML = originalText;
            btn.disabled = false;
        }, 'image/png');

    } catch (err) {
        console.error('Error html2canvas:', err);
        alert('Gagal membuat gambar faktur. Mengalihkan ke teks...');
        sendSellWaTextDirectOnly();
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

async function downloadSellReceiptPngOnly() {
    const receiptCard = document.getElementById('receiptSellCardToCapture');
    const canvas = await html2canvas(receiptCard, {
        scale: 2.5,
        useCORS: true,
        backgroundColor: '#ffffff'
    });
    const link = document.createElement('a');
    link.download = `Faktur_MBB_${(currentSellWaData?.invoice || 'Jual').replace(/[^a-zA-Z0-9]/g, '_')}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

function sendSellWaTextDirectOnly() {
    if (!currentSellWaData) return;
    const phone = cleanPhoneForWa(currentSellWaData.customer_phone);
    const text = buildSellWaInvoiceText(currentSellWaData);
    const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(text)}`;
    window.open(waUrl, '_blank');
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
        recalcSellPayment();
    }

    if (w && p) {
        w.addEventListener('input', recalc);
        p.addEventListener('input', recalc);
    }

    const payStatusSel = document.getElementById('s_payment_status');
    if (payStatusSel) {
        payStatusSel.addEventListener('change', toggleSellPaymentFields);
    }
    const saleModalEl = document.getElementById('modalAddSale');
    if (saleModalEl) {
        saleModalEl.addEventListener('shown.bs.modal', function() {
            toggleSellPaymentFields();
        });
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
