<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Coffee Roasting Service (Jasa Maklon Roasting Kopi Pelanggan)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('roasting_service.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// POST HANDLER: ORDER JASA ROASTING
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_order') {
        Permission::require('roasting_service.create');
        csrf_validate();

        $orderNumber = 'RST-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $orderDate   = sanitize($_POST['order_date'] ?? date('Y-m-d'));
        $customerId  = (int)($_POST['customer_id'] ?? 0);
        $newCustName = sanitize($_POST['new_customer_name'] ?? '');
        $newCustPhone= sanitize($_POST['new_customer_phone'] ?? '');

        // Jika pelanggan baru diinput
        if ($customerId <= 0 && $newCustName !== '') {
            $code = 'CST-' . strtoupper(substr(uniqid(), -5));
            $stmtC = $db->prepare("INSERT INTO customers (code, name, phone) VALUES (?, ?, ?)");
            $stmtC->execute([$code, $newCustName, $newCustPhone]);
            $customerId = $db->lastInsertId();
        }

        $rawWeightKg      = (float)($_POST['raw_weight_kg'] ?? 0);
        $roastProfile     = sanitize($_POST['roast_profile'] ?? 'MEDIUM');
        $roastedWeightKg  = (float)($_POST['roasted_weight_kg'] ?? 0);
        $serviceFeePerKg  = (float)($_POST['service_fee_per_kg'] ?? 15000);
        $paidAmount       = (float)($_POST['paid_amount'] ?? 0);
        $workStatus       = sanitize($_POST['work_status'] ?? 'ANTREAN');
        $notes            = sanitize($_POST['notes'] ?? '');

        if ($customerId <= 0 || $rawWeightKg <= 0) {
            flash_set('danger', 'Harap lengkapi nama pelanggan dan berat kopi sebelum roasting.');
            redirect('modules/roasting-service/index.php');
        }

        $totalFee = $rawWeightKg * $serviceFeePerKg;
        $paymentStatus = ($paidAmount >= $totalFee) ? 'LUNAS' : 'BELUM_BAYAR';

        $shrinkagePercent = 0;
        if ($roastedWeightKg > 0 && $rawWeightKg > 0) {
            $shrinkagePercent = (($rawWeightKg - $roastedWeightKg) / $rawWeightKg) * 100;
        }

        $stmt = $db->prepare("
            INSERT INTO roasting_orders (
                order_number, customer_id, order_date, raw_weight_kg, roast_profile,
                roasted_weight_kg, shrinkage_percent, service_fee_per_kg, total_fee,
                paid_amount, payment_status, work_status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderNumber, $customerId, $orderDate, $rawWeightKg, $roastProfile,
            $roastedWeightKg, $shrinkagePercent, $serviceFeePerKg, $totalFee,
            $paidAmount, $paymentStatus, $workStatus, $notes, Auth::id()
        ]);
        $orderId = $db->lastInsertId();

        log_audit('CREATE_ROASTING_ORDER', 'roasting-service', $orderId, "Order jasa roasting {$orderNumber} ({$rawWeightKg} Kg) senilai Rp " . number_format($totalFee));
        flash_set('success', "Order jasa roasting {$orderNumber} berhasil dibuat.");
        redirect('modules/roasting-service/index.php');
    }

    if ($action === 'update_status') {
        Permission::require('roasting_service.manage');
        csrf_validate();

        $orderId         = (int)($_POST['order_id'] ?? 0);
        $workStatus      = sanitize($_POST['work_status'] ?? 'ANTREAN');
        $roastedWeightKg = (float)($_POST['roasted_weight_kg'] ?? 0);
        $paidAmount      = (float)($_POST['paid_amount'] ?? 0);
        $handoverDate    = ($workStatus === 'SELESAI') ? date('Y-m-d') : null;

        $curr = $db->prepare("SELECT raw_weight_kg, total_fee FROM roasting_orders WHERE id = ?");
        $curr->execute([$orderId]);
        $currData = $curr->fetch();

        if ($currData) {
            $shrinkPct = 0;
            if ($roastedWeightKg > 0 && $currData['raw_weight_kg'] > 0) {
                $shrinkPct = (($currData['raw_weight_kg'] - $roastedWeightKg) / $currData['raw_weight_kg']) * 100;
            }

            $paymentStatus = ($paidAmount >= $currData['total_fee']) ? 'LUNAS' : 'BELUM_BAYAR';

            $stmt = $db->prepare("
                UPDATE roasting_orders SET
                    work_status = ?,
                    roasted_weight_kg = ?,
                    shrinkage_percent = ?,
                    paid_amount = ?,
                    payment_status = ?,
                    handover_date = COALESCE(?, handover_date)
                WHERE id = ?
            ");
            $stmt->execute([$workStatus, $roastedWeightKg, $shrinkPct, $paidAmount, $paymentStatus, $handoverDate, $orderId]);

            log_audit('UPDATE_ROASTING_STATUS', 'roasting-service', $orderId, "Update status order roasting #{$orderId} menjadi {$workStatus}");
            flash_set('success', 'Status pengerjaan berhasil diperbarui.');
        }

        redirect('modules/roasting-service/index.php');
    }

    if ($action === 'delete_order') {
        Permission::require('roasting_service.delete');
        csrf_validate();

        $orderId = (int)($_POST['order_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM roasting_orders WHERE id = ?");
        $stmt->execute([$orderId]);

        log_audit('DELETE_ROASTING_ORDER', 'roasting-service', $orderId, "Menghapus order jasa roasting ID {$orderId}");
        flash_set('success', 'Order jasa roasting berhasil dihapus.');
        redirect('modules/roasting-service/index.php');
    }
}

// -------------------------------------------------------------
// FILTER & QUERY
// -------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$query = "
    SELECT r.*, c.name AS customer_name, c.phone AS customer_phone
    FROM roasting_orders r
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (r.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '') {
    $query .= " AND r.work_status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY r.order_date DESC, r.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// KPI Metrics
$kpi = $db->query("
    SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(raw_weight_kg), 0) AS total_raw,
        COALESCE(SUM(total_fee), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN work_status IN ('ANTREAN', 'ROASTING', 'COOLING_RESTING', 'PACKAGING') THEN 1 ELSE 0 END), 0) AS active_queue
    FROM roasting_orders
")->fetch();

$customers = $db->query("SELECT id, name, phone FROM customers ORDER BY name ASC")->fetchAll();

$pageTitle = 'Coffee Roasting Service (Maklon)';
$currentModule = 'roasting-service';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Roasting Service' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. KPI SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Antrean & Proses Aktif</div>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($kpi['active_queue']) ?> <small class="fs-6 text-muted">Pesanan</small></h3>
                </div>
                <div class="rounded-circle bg-warning-subtle text-warning p-3">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Kopi dalam antrean / oven</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Volume Maklon</div>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= number_format($kpi['total_raw'], 1, ',', '.') ?> <small class="fs-6 text-muted">Kg</small></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3">
                    <i class="bi bi-box-seam fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Kopi milik pelanggan disangrai</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Pendapatan Jasa</div>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= format_rupiah($kpi['total_revenue']) ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Pendapatan jasa murni MBB</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Order Masuk</div>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($kpi['total_orders']) ?></h3>
                </div>
                <div class="rounded-circle bg-info-subtle text-info p-3">
                    <i class="bi bi-receipt fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Pelanggan kafe & penikmat kopi</div>
        </div>
    </div>
</div>

<!-- 2. TOOLBAR & FILTER -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-7">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari no order, nama pelanggan, no telp...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="ANTREAN" <?= $statusFilter === 'ANTREAN' ? 'selected' : '' ?>>Antrean</option>
                            <option value="ROASTING" <?= $statusFilter === 'ROASTING' ? 'selected' : '' ?>>Proses Roasting</option>
                            <option value="COOLING_RESTING" <?= $statusFilter === 'COOLING_RESTING' ? 'selected' : '' ?>>Cooling & Resting</option>
                            <option value="PACKAGING" <?= $statusFilter === 'PACKAGING' ? 'selected' : '' ?>>Packaging</option>
                            <option value="SIAP_DIAMBIL" <?= $statusFilter === 'SIAP_DIAMBIL' ? 'selected' : '' ?>>Siap Diambil</option>
                            <option value="SELESAI" <?= $statusFilter === 'SELESAI' ? 'selected' : '' ?>>Selesai Diserahkan</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($search !== '' || $statusFilter !== ''): ?>
                            <a href="<?= base_url('modules/roasting-service/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-md-auto text-end">
                <?php if (Permission::can('roasting_service.create')): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateOrder">
                        <i class="bi bi-plus-circle me-1"></i> Terima Kopi Pelanggan Baru
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. TABEL DATA ORDER JASA ROASTING -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-cup-straw"></i> Daftar Antrean & Pengerjaan Jasa Roasting Kopi
        </h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($orders) ?> Pesanan</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Order</th>
                        <th>Tanggal</th>
                        <th>Pelanggan</th>
                        <th>Profil</th>
                        <th>Berat Mentah</th>
                        <th>Hasil Roasting</th>
                        <th>Tarif / Biaya</th>
                        <th>Status Pengerjaan</th>
                        <th>Bayar</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada pesanan jasa roasting. Klik tombol <strong>Terima Kopi Pelanggan</strong> untuk mencatat pesanan baru.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark">
                                    <?= e($o['order_number']) ?>
                                </td>
                                <td><?= date('d/m/Y', strtotime($o['order_date'])) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($o['customer_name'] ?? 'Pelanggan Umum') ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone"></i> <?= e($o['customer_phone'] ?? '-') ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <?= e($o['roast_profile']) ?>
                                    </span>
                                </td>
                                <td class="fw-semibold"><?= number_format($o['raw_weight_kg'], 1, ',', '.') ?> Kg</td>
                                <td>
                                    <?php if ($o['roasted_weight_kg'] > 0): ?>
                                        <span class="fw-bold text-success"><?= number_format($o['roasted_weight_kg'], 1, ',', '.') ?> Kg</span>
                                        <div class="small text-danger">Susut: <?= number_format($o['shrinkage_percent'], 1) ?>%</div>
                                    <?php else: ?>
                                        <span class="text-muted small"><em>Belum ditimbang</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-primary"><?= format_rupiah($o['total_fee']) ?></div>
                                    <div class="small text-muted"><?= format_rupiah($o['service_fee_per_kg']) ?>/Kg</div>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($o['work_status']) {
                                        'ANTREAN' => 'bg-warning text-dark',
                                        'ROASTING' => 'bg-danger text-white',
                                        'COOLING_RESTING' => 'bg-info text-dark',
                                        'PACKAGING' => 'bg-primary text-white',
                                        'SIAP_DIAMBIL' => 'bg-success text-white',
                                        'SELESAI' => 'bg-secondary text-white',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= e($o['work_status']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $o['payment_status'] === 'LUNAS' ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger' ?>">
                                        <?= e($o['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-update-status"
                                                data-id="<?= $o['id'] ?>"
                                                data-number="<?= e($o['order_number']) ?>"
                                                data-status="<?= e($o['work_status']) ?>"
                                                data-roasted="<?= $o['roasted_weight_kg'] ?>"
                                                data-paid="<?= $o['paid_amount'] ?>"
                                                data-fee="<?= $o['total_fee'] ?>"
                                                title="Update Status / Timbang Hasil">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="<?= base_url('modules/roasting-service/print_order.php?id=' . $o['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Nota / SPK">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (Permission::can('roasting_service.delete')): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_order">
                                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                        data-confirm="Hapus pesanan '<?= e($o['order_number']) ?>'?" title="Hapus">
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

<!-- 4. MODAL TERIMA KOPI PELANGGAN BARU -->
<div class="modal fade" id="modalCreateOrder" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_order">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-cup-straw me-2"></i>Penerimaan Kopi Jasa Roasting (Maklon)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Tanggal Masuk <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Profil Roasting yang Diminta</label>
                        <select name="roast_profile" class="form-select">
                            <option value="MEDIUM" selected>Medium Roast (Rekomendasi Es Kopi / Filter)</option>
                            <option value="DARK">Dark Roast (Kopi Tubruk & Tradisional)</option>
                            <option value="LIGHT">Light Roast (Seduh Manual / Acidic)</option>
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-person"></i> Data Pelanggan / Pemilik Kopi</h6>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pilih dari Database Pelanggan</label>
                        <select name="customer_id" id="select_customer" class="form-select">
                            <option value="0">-- Input Pelanggan Baru di Bawah --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> (<?= e($c['phone']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Nama Pelanggan Baru</label>
                        <input type="text" name="new_customer_name" id="new_cust_name" class="form-control" placeholder="Nama lengkap">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">No. WhatsApp / HP</label>
                        <input type="text" name="new_customer_phone" id="new_cust_phone" class="form-control" placeholder="08...">
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-calculator"></i> Berat & Tarif Jasa Roasting</h6>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Berat Kopi Masuk (Kg) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" name="raw_weight_kg" id="order_raw_weight" class="form-control" placeholder="cth: 10" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tarif Jasa per Kg (Rp)</label>
                        <input type="number" name="service_fee_per_kg" id="order_fee_per_kg" class="form-control" value="15000" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Total Biaya Jasa</label>
                        <div class="form-control fw-bold text-primary bg-light" id="calc_order_total">Rp 0</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pembayaran Diterima di Awal (Rp)</label>
                        <input type="number" name="paid_amount" id="order_paid_amount" class="form-control" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status Awal</label>
                        <select name="work_status" class="form-select">
                            <option value="ANTREAN" selected>ANTREAN (Dalam Daftar Antre)</option>
                            <option value="ROASTING">LANGSUNG PROSES ROASTING</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Catatan Permintaan Khusus</label>
                        <input type="text" name="notes" class="form-control" placeholder="Cth: Titip digiling kasar untuk cold brew / packing 250gr">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Buat Order Roasting</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL UPDATE STATUS & PENIMBANGAN HASIL -->
<div class="modal fade" id="modalUpdateStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="edit_order_id">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="edit_modal_title">Update Status Pekerjaan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Status Pengerjaan</label>
                    <select name="work_status" id="edit_work_status" class="form-select">
                        <option value="ANTREAN">ANTREAN</option>
                        <option value="ROASTING">SEDANG ROASTING</option>
                        <option value="COOLING_RESTING">COOLING & RESTING</option>
                        <option value="PACKAGING">PACKAGING / PENGEMASAN</option>
                        <option value="SIAP_DIAMBIL">SIAP DIAMBIL (Beritahu Pelanggan)</option>
                        <option value="SELESAI">SELESAI (Sudah Diserahkan ke Pelanggan)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Berat Hasil Timbang Roasting (Kg)</label>
                    <input type="number" step="0.05" name="roasted_weight_kg" id="edit_roasted_weight" class="form-control" placeholder="Timbang setelah sangrai">
                    <div class="form-text small">Diisi saat proses roasting selesai untuk mencatat penyusutan berat.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Total Pembayaran Diterima (Rp)</label>
                    <input type="number" name="paid_amount" id="edit_paid_amount" class="form-control">
                    <div class="form-text small" id="edit_fee_hint">Total tagihan: Rp 0</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rawWeight = document.getElementById('order_raw_weight');
    const feePerKg = document.getElementById('order_fee_per_kg');
    const totalDisplay = document.getElementById('calc_order_total');

    function calcTotal() {
        const rw = parseFloat(rawWeight.value) || 0;
        const fee = parseFloat(feePerKg.value) || 0;
        const total = rw * fee;
        totalDisplay.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    rawWeight.addEventListener('input', calcTotal);
    feePerKg.addEventListener('input', calcTotal);

    // Update Status Modal Handler
    document.querySelectorAll('.btn-update-status').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const num = this.getAttribute('data-number');
            const st = this.getAttribute('data-status');
            const roasted = this.getAttribute('data-roasted');
            const paid = this.getAttribute('data-paid');
            const fee = this.getAttribute('data-fee');

            document.getElementById('edit_order_id').value = id;
            document.getElementById('edit_modal_title').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Update Order ' + num;
            document.getElementById('edit_work_status').value = st;
            document.getElementById('edit_roasted_weight').value = roasted > 0 ? roasted : '';
            document.getElementById('edit_paid_amount').value = paid;
            document.getElementById('edit_fee_hint').textContent = 'Total tagihan jasa: Rp ' + parseFloat(fee).toLocaleString('id-ID');

            const modal = new bootstrap.Modal(document.getElementById('modalUpdateStatus'));
            modal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
