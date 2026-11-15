<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Administrasi Agen BRILink CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('brilink.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// POST HANDLER: TRANSAKSI BRILINK
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_trx') {
        Permission::require('brilink.create');
        csrf_validate();

        $referenceNo      = 'BRL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $transactionDate  = sanitize($_POST['transaction_date'] ?? date('Y-m-d H:i:s'));
        $accountId        = (int)($_POST['brilink_account_id'] ?? 1);
        $trxType          = sanitize($_POST['trx_type'] ?? 'TRANSFER');
        $customerName     = sanitize($_POST['customer_name'] ?? 'Nasabah Umum');
        $customerPhone    = sanitize($_POST['customer_phone'] ?? '');
        $nominalAmount    = (float)($_POST['nominal_amount'] ?? 0);
        $adminFeeCharged  = (float)($_POST['admin_fee_charged'] ?? 5000);
        $bankFee          = (float)($_POST['bank_fee'] ?? 2000);
        $cashReceived     = (float)($_POST['cash_received'] ?? 0);
        $status           = sanitize($_POST['status'] ?? 'BERHASIL');
        $notes            = sanitize($_POST['notes'] ?? '');

        if ($nominalAmount <= 0) {
            flash_set('danger', 'Nominal transaksi harus lebih dari 0.');
            redirect('modules/brilink/index.php');
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("
                INSERT INTO brilink_transactions (
                    reference_no, transaction_date, brilink_account_id, trx_type,
                    customer_name, customer_phone, nominal_amount, admin_fee_charged,
                    bank_fee, cash_received, status, operator_id, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $referenceNo, $transactionDate, $accountId, $trxType,
                $customerName, $customerPhone, $nominalAmount, $adminFeeCharged,
                $bankFee, $cashReceived, $status, Auth::id(), $notes
            ]);
            $trxId = $db->lastInsertId();

            // Pembaruan Saldo EDC / Rekening Bank jika status BERHASIL
            if ($status === 'BERHASIL') {
                if ($trxType === 'TARIK_TUNAI') {
                    // Tarik tunai: Saldo rekening BRI bertambah (ditransfer nasabah), Kas laci tunai keluar
                    $db->prepare("UPDATE brilink_accounts SET current_balance = current_balance + ? WHERE id = ?")
                       ->execute([$nominalAmount, $accountId]);
                } elseif (in_array($trxType, ['TRANSFER', 'SETOR_TUNAI', 'BAYAR_TAGIHAN', 'PULSA_DIGITAL'])) {
                    // Transfer/Setor: Saldo rekening BRI terpotong, Kas laci tunai masuk dari nasabah
                    $db->prepare("UPDATE brilink_accounts SET current_balance = current_balance - ? WHERE id = ?")
                       ->execute([$nominalAmount, $accountId]);
                }
            }

            $db->commit();
            log_audit('CREATE_BRILINK_TRX', 'brilink', $trxId, "Transaksi BRILink {$referenceNo} ({$trxType}) nominal Rp " . number_format($nominalAmount));
            flash_set('success', "Transaksi BRILink {$referenceNo} berhasil dicatat.");
        } catch (Exception $e) {
            $db->rollBack();
            flash_set('danger', 'Gagal mencatat transaksi: ' . $e->getMessage());
        }

        redirect('modules/brilink/index.php');
    }

    if ($action === 'delete_trx') {
        Permission::require('brilink.delete');
        csrf_validate();

        $trxId = (int)($_POST['trx_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM brilink_transactions WHERE id = ?");
        $stmt->execute([$trxId]);

        log_audit('DELETE_BRILINK_TRX', 'brilink', $trxId, "Menghapus transaksi BRILink ID {$trxId}");
        flash_set('success', 'Transaksi BRILink berhasil dihapus.');
        redirect('modules/brilink/index.php');
    }
}

// -------------------------------------------------------------
// FILTER & QUERY TRANSAKSI
// -------------------------------------------------------------
$search     = trim($_GET['q'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$dateFilter = trim($_GET['date'] ?? date('Y-m-d'));

$query = "
    SELECT t.*, a.account_name, a.device_edc_id, u.full_name AS operator_name
    FROM brilink_transactions t
    LEFT JOIN brilink_accounts a ON t.brilink_account_id = a.id
    LEFT JOIN users u ON t.operator_id = u.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (t.reference_no LIKE ? OR t.customer_name LIKE ? OR t.customer_phone LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($typeFilter !== '') {
    $query .= " AND t.trx_type = ?";
    $params[] = $typeFilter;
}

if ($dateFilter !== '') {
    $query .= " AND DATE(t.transaction_date) = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY t.transaction_date DESC, t.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// KPI Metrics BRILink
$kpiToday = $db->prepare("
    SELECT 
        COUNT(*) AS total_trx,
        COALESCE(SUM(nominal_amount), 0) AS total_volume,
        COALESCE(SUM(admin_fee_charged), 0) AS total_admin,
        COALESCE(SUM(company_income), 0) AS total_income
    FROM brilink_transactions
    WHERE DATE(transaction_date) = ? AND status = 'BERHASIL'
");
$kpiToday->execute([$dateFilter !== '' ? $dateFilter : date('Y-m-d')]);
$kpi = $kpiToday->fetch();

$accounts = $db->query("SELECT * FROM brilink_accounts WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

$pageTitle = 'Administrasi Agen BRILink MBB';
$currentModule = 'brilink';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Agen BRILink' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. SALDO AKUN EDC & REKENING OPERASIONAL -->
<div class="row g-3 mb-4">
    <?php foreach ($accounts as $acc): ?>
        <div class="col-md-4 col-sm-6">
            <div class="card-mbb p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase"><?= e($acc['account_name']) ?></div>
                        <h4 class="fw-bold text-primary mb-0 mt-1"><?= format_rupiah($acc['current_balance']) ?></h4>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary p-3">
                        <i class="bi bi-credit-card-2-front fs-4"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    Rek: <strong><?= e($acc['account_number']) ?></strong> &bull; EDC: <?= e($acc['device_edc_id']) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- KPI PENDAPATAN JASA MBB -->
    <div class="col-md-4 col-sm-12">
        <div class="card-mbb p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Pendapatan Jasa MBB (<?= $dateFilter ? date('d/m/Y', strtotime($dateFilter)) : 'Hari Ini' ?>)</div>
                    <h4 class="fw-bold text-success mb-0 mt-1"><?= format_rupiah($kpi['total_income']) ?></h4>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-cash-coin fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">
                <?= number_format($kpi['total_trx']) ?> Transaksi &bull; Volume: <?= format_rupiah($kpi['total_volume']) ?>
            </div>
        </div>
    </div>
</div>

<!-- 2. TOOLBAR & FILTER -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-8">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari ref, nasabah...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date" value="<?= e($dateFilter) ?>" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">-- Semua Jenis --</option>
                            <option value="TRANSFER" <?= $typeFilter === 'TRANSFER' ? 'selected' : '' ?>>Transfer Bank</option>
                            <option value="TARIK_TUNAI" <?= $typeFilter === 'TARIK_TUNAI' ? 'selected' : '' ?>>Tarik Tunai</option>
                            <option value="SETOR_TUNAI" <?= $typeFilter === 'SETOR_TUNAI' ? 'selected' : '' ?>>Setor Tunai</option>
                            <option value="BAYAR_TAGIHAN" <?= $typeFilter === 'BAYAR_TAGIHAN' ? 'selected' : '' ?>>Bayar Tagihan / Listrik</option>
                            <option value="PULSA_DIGITAL" <?= $typeFilter === 'PULSA_DIGITAL' ? 'selected' : '' ?>>Pulsa & Paket Data</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($search !== '' || $typeFilter !== '' || $dateFilter !== date('Y-m-d')): ?>
                            <a href="<?= base_url('modules/brilink/index.php') ?>" class="btn btn-sm btn-outline-secondary">Hari Ini</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-md-auto text-end">
                <?php if (Permission::can('brilink.create')): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateTrx">
                        <i class="bi bi-plus-circle me-1"></i> Catat Transaksi BRILink
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. TABEL DATA TRANSAKSI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-clock-history"></i> Rekap Mutasi Transaksi BRILink MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($transactions) ?> Transaksi</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Reff</th>
                        <th>Waktu</th>
                        <th>Jenis Transaksi</th>
                        <th>Nasabah</th>
                        <th>Nominal</th>
                        <th>Biaya Admin</th>
                        <th>Biaya Bank</th>
                        <th>Fee MBB</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada transaksi BRILink pada periode ini. Klik <strong>Catat Transaksi BRILink</strong> untuk mencatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                            <tr>
                                <td class="ps-4 fw-bold font-monospace text-dark">
                                    <?= e($t['reference_no']) ?>
                                    <div class="small text-muted fw-normal"><?= e($t['account_name'] ?? 'EDC') ?></div>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($t['transaction_date'])) ?>
                                    <div class="small text-muted"><?= date('H:i', strtotime($t['transaction_date'])) ?> WIB</div>
                                </td>
                                <td>
                                    <?php
                                    $badge = match($t['trx_type']) {
                                        'TRANSFER' => 'bg-info-subtle text-info border border-info',
                                        'TARIK_TUNAI' => 'bg-success-subtle text-success border border-success',
                                        'SETOR_TUNAI' => 'bg-primary-subtle text-primary border border-primary',
                                        'BAYAR_TAGIHAN' => 'bg-warning-subtle text-warning border border-warning',
                                        'PULSA_DIGITAL' => 'bg-secondary-subtle text-secondary border border-secondary',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= e($t['trx_type']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($t['customer_name'] ?? 'Umum') ?></div>
                                    <div class="small text-muted"><?= e($t['customer_phone'] ?? '-') ?></div>
                                </td>
                                <td class="fw-bold text-dark"><?= format_rupiah($t['nominal_amount']) ?></td>
                                <td><?= format_rupiah($t['admin_fee_charged']) ?></td>
                                <td class="text-muted small"><?= format_rupiah($t['bank_fee']) ?></td>
                                <td class="fw-bold text-success"><?= format_rupiah($t['company_income']) ?></td>
                                <td>
                                    <span class="badge <?= $t['status'] === 'BERHASIL' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= e($t['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/brilink/print_receipt.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Struk">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (Permission::can('brilink.delete')): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_trx">
                                                <input type="hidden" name="trx_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                        data-confirm="Hapus transaksi ref '<?= e($t['reference_no']) ?>'?" title="Hapus">
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

<!-- 4. MODAL CATAT TRANSAKSI BRILINK -->
<div class="modal fade" id="modalCreateTrx" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_trx">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-credit-card-2-front me-2"></i>Catat Transaksi Agen BRILink Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Akun Perangkat / Rekening EDC</label>
                        <select name="brilink_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>"><?= e($acc['account_name']) ?> (Saldo: <?= format_rupiah($acc['current_balance']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Jenis Layanan BRILink <span class="text-danger">*</span></label>
                        <select name="trx_type" id="trx_type" class="form-select" required>
                            <option value="TRANSFER">Transfer Antar-Bank / Sesama BRI</option>
                            <option value="TARIK_TUNAI">Tarik Tunai (Nasabah Gesek/Debit)</option>
                            <option value="SETOR_TUNAI">Setor Tunai ke Rekening</option>
                            <option value="BAYAR_TAGIHAN">Pembayaran Tagihan (Listrik / PDAM / BPJS)</option>
                            <option value="PULSA_DIGITAL">Pulsa / Token Listrik / Top Up E-Wallet</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nama Nasabah</label>
                        <input type="text" name="customer_name" class="form-control" placeholder="Cth: Bpk. Suwandi" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">No. HP / WA Nasabah</label>
                        <input type="text" name="customer_phone" class="form-control" placeholder="08...">
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-cash-stack"></i> Nominal & Biaya Administrasi</h6>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nominal Transaksi (Rp) <span class="text-danger">*</span></label>
                        <input type="number" step="1000" name="nominal_amount" id="trx_nominal" class="form-control" placeholder="cth: 500000" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Admin Dikenakan ke Nasabah (Rp)</label>
                        <input type="number" name="admin_fee_charged" id="trx_admin_fee" class="form-control" value="5000" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Potongan Biaya Bank (Rp)</label>
                        <input type="number" name="bank_fee" id="trx_bank_fee" class="form-control" value="2000" required>
                    </div>

                    <!-- PREVIEW KALKULASI FEE MBB -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded border">
                            <div class="row text-center">
                                <div class="col-md-6">
                                    <div class="text-muted small">Total Tagihan Nasabah (Nominal + Admin)</div>
                                    <div class="fw-bold text-dark fs-5" id="calc_cust_total">Rp 0</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Pendapatan Bersih Jasa MBB (Margin Fee)</div>
                                    <div class="fw-bold text-success fs-5" id="calc_company_income">Rp 3.000</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Uang Tunai Diterima dari Nasabah (Rp)</label>
                        <input type="number" name="cash_received" id="trx_cash_received" class="form-control" placeholder="Uang fisik">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status Transaksi</label>
                        <select name="status" class="form-select">
                            <option value="BERHASIL" selected>BERHASIL (Update Saldo Rekening)</option>
                            <option value="PENDING">PENDING</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Catatan Transaksi / No Rek Tujuan</label>
                        <input type="text" name="notes" class="form-control" placeholder="Cth: Transfer ke BRI 0123-xxx an Bambang">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Transaksi BRILink</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nominal = document.getElementById('trx_nominal');
    const adminFee = document.getElementById('trx_admin_fee');
    const bankFee = document.getElementById('trx_bank_fee');
    const custTotal = document.getElementById('calc_cust_total');
    const incomeTotal = document.getElementById('calc_company_income');

    function calcBrilink() {
        const nom = parseFloat(nominal.value) || 0;
        const adm = parseFloat(adminFee.value) || 0;
        const bnk = parseFloat(bankFee.value) || 0;

        const totalPay = nom + adm;
        const feeIncome = Math.max(0, adm - bnk);

        custTotal.textContent = 'Rp ' + totalPay.toLocaleString('id-ID');
        incomeTotal.textContent = 'Rp ' + feeIncome.toLocaleString('id-ID');
    }

    nominal.addEventListener('input', calcBrilink);
    adminFee.addEventListener('input', calcBrilink);
    bankFee.addEventListener('input', calcBrilink);
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
