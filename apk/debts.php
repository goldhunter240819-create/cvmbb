<?php
/**
 * MBB MOBILE APK - Buku Hutang & Piutang Usaha
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Buku Hutang & Piutang';
$currentApkNav   = 'debts';

$mobileHeaderAction = '
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" data-bs-toggle="dropdown" aria-expanded="false" title="Tambah Catatan">
            <i class="bi bi-plus-lg text-primary fw-bold"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3" style="font-size: 0.85rem;">
            <li><a class="dropdown-item py-2 fw-semibold text-success" href="#" data-bs-toggle="modal" data-bs-target="#modalAddReceivable"><i class="bi bi-arrow-down-left-circle-fill me-2"></i>Catat Piutang (Tagihan)</a></li>
            <li><a class="dropdown-item py-2 fw-semibold text-danger" href="#" data-bs-toggle="modal" data-bs-target="#modalAddPayable"><i class="bi bi-arrow-up-right-circle-fill me-2"></i>Catat Hutang (Kewajiban)</a></li>
        </ul>
    </div>
';

require_once __DIR__ . '/layout/header.php';

// Form Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Tambah Piutang Baru (Receivable)
    if ($action === 'create_receivable') {
        csrf_validate();

        $refNo        = sanitize($_POST['reference_number'] ?? ('PIU/' . date('Ym') . '/' . strtoupper(substr(uniqid(), -4))));
        $businessLine = sanitize($_POST['business_line'] ?? 'Umum');
        $debtorName   = sanitize($_POST['debtor_name'] ?? '');
        $debtorPhone  = sanitize($_POST['debtor_phone'] ?? '');
        $dueDate      = !empty($_POST['due_date']) ? sanitize($_POST['due_date']) : null;
        $totalAmount  = (float)str_replace(['.', ','], ['', '.'], $_POST['total_amount'] ?? '0');
        $paidAmount   = (float)str_replace(['.', ','], ['', '.'], $_POST['paid_amount'] ?? '0');
        $notes        = sanitize($_POST['notes'] ?? '');

        if ($debtorName && $totalAmount > 0) {
            $paidAmount = min($paidAmount, $totalAmount);
            $status = ($paidAmount >= $totalAmount) ? 'LUNAS' : (($paidAmount > 0) ? 'SEBAGIAN' : 'BELUM_LUNAS');

            $stmt = $db->prepare("
                INSERT INTO receivables (
                    reference_number, business_line, debtor_name, debtor_phone,
                    due_date, total_amount, paid_amount, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $refNo, $businessLine, $debtorName, $debtorPhone,
                $dueDate, $totalAmount, $paidAmount, $status, $notes
            ]);

            log_audit('CREATE_RECEIVABLE_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat piutang {$refNo} debitur {$debtorName} senilai Rp {$totalAmount}");
            flash_set('success', "Piutang {$debtorName} berhasil dicatat!");
            redirect('apk/debts.php?tab=piutang');
        }
    }

    // 2. Tambah Hutang Baru (Payable)
    elseif ($action === 'create_payable') {
        csrf_validate();

        $refNo        = sanitize($_POST['reference_number'] ?? ('HUT/' . date('Ym') . '/' . strtoupper(substr(uniqid(), -4))));
        $businessLine = sanitize($_POST['business_line'] ?? 'Kopi Petani');
        $creditorName = sanitize($_POST['creditor_name'] ?? '');
        $creditorPhone= sanitize($_POST['creditor_phone'] ?? '');
        $dueDate      = !empty($_POST['due_date']) ? sanitize($_POST['due_date']) : null;
        $totalAmount  = (float)str_replace(['.', ','], ['', '.'], $_POST['total_amount'] ?? '0');
        $paidAmount   = (float)str_replace(['.', ','], ['', '.'], $_POST['paid_amount'] ?? '0');
        $notes        = sanitize($_POST['notes'] ?? '');

        if ($creditorName && $totalAmount > 0) {
            $paidAmount = min($paidAmount, $totalAmount);
            $status = ($paidAmount >= $totalAmount) ? 'LUNAS' : (($paidAmount > 0) ? 'SEBAGIAN' : 'BELUM_LUNAS');

            $stmt = $db->prepare("
                INSERT INTO payables (
                    reference_number, business_line, creditor_name, creditor_phone,
                    due_date, total_amount, paid_amount, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $refNo, $businessLine, $creditorName, $creditorPhone,
                $dueDate, $totalAmount, $paidAmount, $status, $notes
            ]);

            log_audit('CREATE_PAYABLE_APK', 'apk', $db->lastInsertId(), "Mobile APK mencatat hutang {$refNo} kreditur {$creditorName} senilai Rp {$totalAmount}");
            flash_set('success', "Hutang ke {$creditorName} berhasil dicatat!");
            redirect('apk/debts.php?tab=hutang');
        }
    }

    // 3. Bayar / Angsur Hutang atau Terima Pembayaran Piutang
    elseif ($action === 'record_payment') {
        csrf_validate();

        $debtType  = sanitize($_POST['debt_type'] ?? 'receivable'); // receivable atau payable
        $debtId    = (int)($_POST['debt_id'] ?? 0);
        $payAmount = (float)str_replace(['.', ','], ['', '.'], $_POST['payment_amount'] ?? '0');
        $accountId = (int)($_POST['cash_account_id'] ?? 0);
        $payNotes  = sanitize($_POST['payment_notes'] ?? '');

        if ($debtId > 0 && $payAmount > 0) {
            if ($debtType === 'receivable') {
                $item = $db->query("SELECT * FROM receivables WHERE id = {$debtId}")->fetch();
                if ($item) {
                    $newPaid = (float)$item['paid_amount'] + $payAmount;
                    $total   = (float)$item['total_amount'];
                    if ($newPaid > $total) $newPaid = $total;
                    $newStatus = ($newPaid >= $total) ? 'LUNAS' : 'SEBAGIAN';

                    $db->prepare("UPDATE receivables SET paid_amount = ?, status = ? WHERE id = ?")
                       ->execute([$newPaid, $newStatus, $debtId]);

                    // Catat ke kas masuk jika akun kas dipilih
                    if ($accountId > 0) {
                        $trxNo = 'KAS-IN/' . date('Ymd') . '/' . rand(100, 999);
                        $db->prepare("
                            INSERT INTO cash_transactions (
                                trx_code, cash_account_id, trx_date, trx_direction,
                                business_line, category, amount, reference_type, reference_id, description, created_by
                            ) VALUES (?, ?, ?, 'IN', 'MANAGEMENT', 'Pelunasan Piutang', ?, 'RECEIVABLE', ?, ?, ?)
                        ")->execute([
                            $trxNo, $accountId, date('Y-m-d'), $payAmount,
                            $item['reference_number'], "Terima pelunasan/angsuran piutang dari {$item['debtor_name']}" . ($payNotes ? " ({$payNotes})" : ""), Auth::id()
                        ]);
                        $db->prepare("UPDATE cash_accounts SET current_balance = current_balance + ? WHERE id = ?")
                           ->execute([$payAmount, $accountId]);
                    }

                    log_audit('PAY_RECEIVABLE_APK', 'apk', $debtId, "Terima cicilan piutang Rp {$payAmount} dari {$item['debtor_name']}");
                    flash_set('success', "Pembayaran piutang Rp " . number_format($payAmount, 0, ',', '.') . " dari {$item['debtor_name']} berhasil dicatat!");
                    redirect('apk/debts.php?tab=piutang');
                }
            } else {
                $item = $db->query("SELECT * FROM payables WHERE id = {$debtId}")->fetch();
                if ($item) {
                    $newPaid = (float)$item['paid_amount'] + $payAmount;
                    $total   = (float)$item['total_amount'];
                    if ($newPaid > $total) $newPaid = $total;
                    $newStatus = ($newPaid >= $total) ? 'LUNAS' : 'SEBAGIAN';

                    $db->prepare("UPDATE payables SET paid_amount = ?, status = ? WHERE id = ?")
                       ->execute([$newPaid, $newStatus, $debtId]);

                    // Catat ke kas keluar jika akun kas dipilih
                    if ($accountId > 0) {
                        $trxNo = 'KAS-OUT/' . date('Ymd') . '/' . rand(100, 999);
                        $db->prepare("
                            INSERT INTO cash_transactions (
                                trx_code, cash_account_id, trx_date, trx_direction,
                                business_line, category, amount, reference_type, reference_id, description, created_by
                            ) VALUES (?, ?, ?, 'OUT', 'MANAGEMENT', 'Pembayaran Hutang', ?, 'PAYABLE', ?, ?, ?)
                        ")->execute([
                            $trxNo, $accountId, date('Y-m-d'), $payAmount,
                            $item['reference_number'], "Bayar hutang ke {$item['creditor_name']}" . ($payNotes ? " ({$payNotes})" : ""), Auth::id()
                        ]);
                        $db->prepare("UPDATE cash_accounts SET current_balance = current_balance - ? WHERE id = ?")
                           ->execute([$payAmount, $accountId]);
                    }

                    log_audit('PAY_PAYABLE_APK', 'apk', $debtId, "Bayar cicilan hutang Rp {$payAmount} ke {$item['creditor_name']}");
                    flash_set('success', "Pembayaran hutang Rp " . number_format($payAmount, 0, ',', '.') . " ke {$item['creditor_name']} berhasil dicatat!");
                    redirect('apk/debts.php?tab=hutang');
                }
            }
        }
    }

    // 4. Hapus Catatan
    elseif ($action === 'delete_record') {
        csrf_validate();
        $debtType = sanitize($_POST['debt_type'] ?? '');
        $debtId   = (int)($_POST['debt_id'] ?? 0);

        if ($debtId > 0) {
            if ($debtType === 'receivable') {
                $db->prepare("DELETE FROM receivables WHERE id = ?")->execute([$debtId]);
                flash_set('success', "Data piutang berhasil dihapus!");
                redirect('apk/debts.php?tab=piutang');
            } elseif ($debtType === 'payable') {
                $db->prepare("DELETE FROM payables WHERE id = ?")->execute([$debtId]);
                flash_set('success', "Data hutang berhasil dihapus!");
                redirect('apk/debts.php?tab=hutang');
            }
        }
    }
}

// Queries
$activeTab = $_GET['tab'] ?? 'piutang';

// Ringkasan KPI
$kpiReceivable = $db->query("
    SELECT 
        COUNT(*) AS total_tx,
        COALESCE(SUM(total_amount), 0) AS total_nominal,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(remaining_amount), 0) AS total_remaining,
        SUM(CASE WHEN status != 'LUNAS' THEN remaining_amount ELSE 0 END) AS active_remaining
    FROM receivables
")->fetch();

$kpiPayable = $db->query("
    SELECT 
        COUNT(*) AS total_tx,
        COALESCE(SUM(total_amount), 0) AS total_nominal,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(remaining_amount), 0) AS total_remaining,
        SUM(CASE WHEN status != 'LUNAS' THEN remaining_amount ELSE 0 END) AS active_remaining
    FROM payables
")->fetch();

// Daftar Akun Kas untuk Pilihan Pembayaran
$cashAccounts = $db->query("SELECT id, account_name, current_balance FROM cash_accounts WHERE is_active = 1 ORDER BY id ASC")->fetchAll();

// Daftar Piutang
$receivables = $db->query("SELECT * FROM receivables ORDER BY status ASC, created_at DESC LIMIT 50")->fetchAll();

// Daftar Hutang
$payables = $db->query("SELECT * FROM payables ORDER BY status ASC, created_at DESC LIMIT 50")->fetchAll();
?>

<!-- KPI RINGKASAN HUTANG & PIUTANG -->
<div class="px-3 pt-3">
    <div class="row g-2 mb-3">
        <!-- Piutang (Hak Tagih Kita) -->
        <div class="col-6">
            <div class="p-3 rounded-4 shadow-sm h-100" style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); color: #ffffff;">
                <div class="d-flex align-items-center justify-content-between mb-1 opacity-75 small">
                    <span style="font-size: 0.72rem;">Piutang Usaha</span>
                    <i class="bi bi-arrow-down-left-circle-fill"></i>
                </div>
                <div class="fw-bold" style="font-size: 1.05rem; line-height: 1.2;">
                    <?= format_rupiah($kpiReceivable['active_remaining'] ?? 0) ?>
                </div>
                <div class="small opacity-75 mt-1" style="font-size: 0.68rem;">
                    Hak Tagih MBB (Di Luar)
                </div>
            </div>
        </div>

        <!-- Hutang (Kewajiban Kita) -->
        <div class="col-6">
            <div class="p-3 rounded-4 shadow-sm h-100" style="background: linear-gradient(135deg, #e11d48 0%, #be123c 100%); color: #ffffff;">
                <div class="d-flex align-items-center justify-content-between mb-1 opacity-75 small">
                    <span style="font-size: 0.72rem;">Hutang Usaha</span>
                    <i class="bi bi-arrow-up-right-circle-fill"></i>
                </div>
                <div class="fw-bold" style="font-size: 1.05rem; line-height: 1.2;">
                    <?= format_rupiah($kpiPayable['active_remaining'] ?? 0) ?>
                </div>
                <div class="small opacity-75 mt-1" style="font-size: 0.68rem;">
                    Kewajiban Harus Dibayar
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SUB-NAV TOGGLE (PIUTANG VS HUTANG) -->
<div class="px-3 mb-3">
    <div class="d-flex p-1 bg-white rounded-pill border shadow-sm">
        <a href="?tab=piutang" class="btn btn-sm <?= $activeTab === 'piutang' ? 'btn-primary fw-bold text-white shadow-sm' : 'btn-light text-muted' ?> rounded-pill flex-fill py-2">
            <i class="bi bi-person-check me-1"></i> Piutang (<?= count($receivables) ?>)
        </a>
        <a href="?tab=hutang" class="btn btn-sm <?= $activeTab === 'hutang' ? 'btn-danger fw-bold text-white shadow-sm' : 'btn-light text-muted' ?> rounded-pill flex-fill py-2">
            <i class="bi bi-credit-card me-1"></i> Hutang (<?= count($payables) ?>)
        </a>
    </div>
</div>

<!-- SEARCH / FILTER BOX -->
<div class="px-3 mb-2">
    <div class="input-group input-group-sm rounded-pill overflow-hidden bg-white border shadow-sm">
        <span class="input-group-text bg-white border-0 ps-3 pe-2 text-muted">
            <i class="bi bi-search"></i>
        </span>
        <input type="text" id="debtSearchInput" class="form-control border-0 ps-1" placeholder="Cari nama mitra, nota, status..." style="box-shadow: none;">
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    <span><?= $activeTab === 'piutang' ? 'Buku Piutang Usaha (Tagihan)' : 'Buku Hutang Usaha (Kewajiban)' ?></span>
    <span class="text-primary" style="cursor: pointer;" onclick="openAddModalByTab()">+ Tambah Data</span>
</div>

<!-- ==================== TAB 1: PIUTANG USAHA ==================== -->
<?php if ($activeTab === 'piutang'): ?>
    <?php if (empty($receivables)): ?>
        <div class="mobile-card text-center py-5 text-muted">
            <i class="bi bi-journal-check fs-1 d-block mb-2 text-primary opacity-50"></i>
            Tidak ada catatan piutang aktif.<br>
            <button type="button" class="btn btn-sm btn-primary rounded-pill mt-3 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddReceivable">
                <i class="bi bi-plus-lg me-1"></i>Catat Piutang Baru
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($receivables as $r): ?>
            <?php
            $rem = (float)($r['remaining_amount'] ?? ($r['total_amount'] - $r['paid_amount']));
            $isLunas = ($r['status'] === 'LUNAS' || $rem <= 0);
            $filterText = strtolower($r['debtor_name'] . ' ' . $r['reference_number'] . ' ' . ($r['business_line'] ?? '') . ' ' . $r['status']);
            ?>
            <div class="mobile-card debt-card-item mb-2" data-filter="<?= e($filterText) ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold text-dark fs-6" style="line-height: 1.2;">
                            <?= e($r['debtor_name']) ?>
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            <?= e($r['reference_number']) ?> &bull; Lini: <strong class="text-dark"><?= e($r['business_line']) ?></strong>
                        </div>
                    </div>
                    <?php if ($isLunas): ?>
                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                            <i class="bi bi-check-circle-fill me-1"></i>Lunas
                        </span>
                    <?php elseif ($r['status'] === 'SEBAGIAN'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                            <i class="bi bi-pie-chart-fill me-1"></i>Dicicil
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                            <i class="bi bi-clock-history me-1"></i>Belum Bayar
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Rincian Nominal -->
                <div class="p-2 rounded-3 bg-light border mb-2" style="font-size: 0.76rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Tagihan:</span>
                        <strong class="text-dark"><?= format_rupiah($r['total_amount']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-success"><i class="bi bi-check2-circle me-1"></i>Sudah Diterima:</span>
                        <strong class="text-success"><?= format_rupiah($r['paid_amount']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-1 border-top">
                        <span class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sisa Piutang:</span>
                        <strong class="text-danger fs-6"><?= format_rupiah($rem) ?></strong>
                    </div>
                </div>

                <?php if (!empty($r['notes']) || !empty($r['due_date'])): ?>
                    <div class="text-muted small mb-2 d-flex justify-content-between" style="font-size: 0.7rem;">
                        <span><?= e($r['notes'] ?: '-') ?></span>
                        <?php if (!empty($r['due_date'])): ?>
                            <span>Jatuh Tempo: <strong class="text-dark"><?= date('d/m/Y', strtotime($r['due_date'])) ?></strong></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Tombol Aksi -->
                <div class="d-flex gap-2 pt-2 border-top">
                    <?php if (!$isLunas): ?>
                        <button type="button" class="btn btn-sm btn-primary rounded-pill flex-fill fw-semibold py-1" style="font-size: 0.75rem;" 
                                onclick='openPaymentModal("receivable", <?= $r["id"] ?>, "<?= e(addslashes($r["debtor_name"])) ?>", <?= (float)$r["total_amount"] ?>, <?= (float)$r["paid_amount"] ?>, <?= $rem ?>)'>
                            <i class="bi bi-cash-stack me-1"></i>Terima Pembayaran
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-semibold py-1 px-3" style="font-size: 0.75rem;" 
                            onclick='sendDebtWa("receivable", <?= json_encode([
                                "name" => $r["debtor_name"],
                                "phone" => $r["debtor_phone"] ?? "",
                                "ref" => $r["reference_number"],
                                "line" => $r["business_line"],
                                "total" => (float)$r["total_amount"],
                                "paid" => (float)$r["paid_amount"],
                                "rem" => $rem,
                                "due" => $r["due_date"] ? date("d/m/Y", strtotime($r["due_date"])) : "-",
                                "status" => $r["status"]
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        <i class="bi bi-whatsapp"></i> WA
                    </button>
                    <form method="POST" action="" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_record">
                        <input type="hidden" name="debt_type" value="receivable">
                        <input type="hidden" name="debt_id" value="<?= $r['id'] ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1 btn-confirm-delete" data-confirm="Hapus catatan piutang ini?" style="width: 28px; height: 28px;" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<!-- ==================== TAB 2: HUTANG USAHA ==================== -->
<?php else: ?>
    <?php if (empty($payables)): ?>
        <div class="mobile-card text-center py-5 text-muted">
            <i class="bi bi-wallet2 fs-1 d-block mb-2 text-danger opacity-50"></i>
            Tidak ada catatan hutang usaha aktif.<br>
            <button type="button" class="btn btn-sm btn-danger rounded-pill mt-3 px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddPayable">
                <i class="bi bi-plus-lg me-1"></i>Catat Hutang Baru
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($payables as $p): ?>
            <?php
            $rem = (float)($p['remaining_amount'] ?? ($p['total_amount'] - $p['paid_amount']));
            $isLunas = ($p['status'] === 'LUNAS' || $rem <= 0);
            $filterText = strtolower($p['creditor_name'] . ' ' . $p['reference_number'] . ' ' . ($p['business_line'] ?? '') . ' ' . $p['status']);
            ?>
            <div class="mobile-card debt-card-item mb-2" data-filter="<?= e($filterText) ?>">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-bold text-dark fs-6" style="line-height: 1.2;">
                            <?= e($p['creditor_name']) ?>
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            <?= e($p['reference_number']) ?> &bull; Lini: <strong class="text-dark"><?= e($p['business_line']) ?></strong>
                        </div>
                    </div>
                    <?php if ($isLunas): ?>
                        <span class="badge bg-success-subtle text-success border border-success border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                            <i class="bi bi-check-circle-fill me-1"></i>Lunas
                        </span>
                    <?php elseif ($p['status'] === 'SEBAGIAN'): ?>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                            <i class="bi bi-pie-chart-fill me-1"></i>DP / Dicicil
                        </span>
                    <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger border-opacity-25 px-2 py-1" style="font-size: 0.68rem;">
                            <i class="bi bi-clock-history me-1"></i>Belum Lunas
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Rincian Nominal -->
                <div class="p-2 rounded-3 bg-light border mb-2" style="font-size: 0.76rem;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Hutang:</span>
                        <strong class="text-dark"><?= format_rupiah($p['total_amount']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-success"><i class="bi bi-cash-stack me-1"></i>Sudah Dibayarkan:</span>
                        <strong class="text-success"><?= format_rupiah($p['paid_amount']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between pt-1 border-top">
                        <span class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill me-1"></i>Sisa Hutang:</span>
                        <strong class="text-danger fs-6"><?= format_rupiah($rem) ?></strong>
                    </div>
                </div>

                <?php if (!empty($p['notes']) || !empty($p['due_date'])): ?>
                    <div class="text-muted small mb-2 d-flex justify-content-between" style="font-size: 0.7rem;">
                        <span><?= e($p['notes'] ?: '-') ?></span>
                        <?php if (!empty($p['due_date'])): ?>
                            <span>Jatuh Tempo: <strong class="text-dark"><?= date('d/m/Y', strtotime($p['due_date'])) ?></strong></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Tombol Aksi -->
                <div class="d-flex gap-2 pt-2 border-top">
                    <?php if (!$isLunas): ?>
                        <button type="button" class="btn btn-sm btn-danger rounded-pill flex-fill fw-semibold py-1" style="font-size: 0.75rem;" 
                                onclick='openPaymentModal("payable", <?= $p["id"] ?>, "<?= e(addslashes($p["creditor_name"])) ?>", <?= (float)$p["total_amount"] ?>, <?= (float)$p["paid_amount"] ?>, <?= $rem ?>)'>
                            <i class="bi bi-credit-card me-1"></i>Bayar Cicilan / Lunas
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-semibold py-1 px-3" style="font-size: 0.75rem;" 
                            onclick='sendDebtWa("payable", <?= json_encode([
                                "name" => $p["creditor_name"],
                                "phone" => $p["creditor_phone"] ?? "",
                                "ref" => $p["reference_number"],
                                "line" => $p["business_line"],
                                "total" => (float)$p["total_amount"],
                                "paid" => (float)$p["paid_amount"],
                                "rem" => $rem,
                                "due" => $p["due_date"] ? date("d/m/Y", strtotime($p["due_date"])) : "-",
                                "status" => $p["status"]
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                        <i class="bi bi-whatsapp"></i> WA
                    </button>
                    <form method="POST" action="" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_record">
                        <input type="hidden" name="debt_type" value="payable">
                        <input type="hidden" name="debt_id" value="<?= $p['id'] ?>">
                        <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1 btn-confirm-delete" data-confirm="Hapus catatan hutang ini?" style="width: 28px; height: 28px;" title="Hapus">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- MODAL TAMBAH PIUTANG BARU -->
<div class="modal fade" id="modalAddReceivable" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_receivable">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2"></i>Catat Piutang Usaha Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Debitur / Pelanggan <span class="text-danger">*</span></label>
                    <input type="text" name="debtor_name" class="form-control form-control-sm" placeholder="Nama pihak yang berhutang ke kita..." required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. WhatsApp</label>
                        <input type="tel" name="debtor_phone" class="form-control form-control-sm" placeholder="08...">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Lini Usaha</label>
                        <select name="business_line" class="form-select form-select-sm">
                            <option value="Kopi Trading">Kopi Trading</option>
                            <option value="Produksi Bubuk">Produksi Bubuk</option>
                            <option value="Jasa Roasting">Jasa Roasting</option>
                            <option value="Divisi Dekor">Divisi Dekor</option>
                            <option value="Sound Sistem">Sound Sistem</option>
                            <option value="BRILink">Agen BRILink</option>
                            <option value="Pinjaman Karyawan">Pinjaman Karyawan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Total Piutang (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="total_amount" class="form-control form-control-sm fw-bold text-primary" placeholder="0" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Sudah Diterima (DP)</label>
                        <input type="number" name="paid_amount" class="form-control form-control-sm" placeholder="0" min="0" value="0">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. Referensi / Nota</label>
                        <input type="text" name="reference_number" class="form-control form-control-sm" value="PIU/<?= date('Ym') ?>/<?= strtoupper(substr(uniqid(), -4)) ?>">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Keterangan / Catatan</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Cth: Penjualan kopi 500 kg tempo 14 hari">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold shadow-sm">Simpan Piutang</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL TAMBAH HUTANG BARU -->
<div class="modal fade" id="modalAddPayable" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_payable">
            <div class="modal-header bg-danger text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Catat Hutang Usaha Baru</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Nama Kreditur / Pemasok <span class="text-danger">*</span></label>
                    <input type="text" name="creditor_name" class="form-control form-control-sm" placeholder="Nama pihak yang kita hutangi (petani/supplier)..." required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. WhatsApp</label>
                        <input type="tel" name="creditor_phone" class="form-control form-control-sm" placeholder="08...">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Lini Usaha</label>
                        <select name="business_line" class="form-select form-select-sm">
                            <option value="Kopi Petani" selected>Kopi Petani</option>
                            <option value="Bahan Baku Bubuk">Bahan Baku Bubuk</option>
                            <option value="Perlengkapan Dekor">Perlengkapan Dekor</option>
                            <option value="Peralatan Sound">Peralatan Sound</option>
                            <option value="Operasional Kantor">Operasional Kantor</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Total Hutang (Rp) <span class="text-danger">*</span></label>
                        <input type="number" name="total_amount" class="form-control form-control-sm fw-bold text-danger" placeholder="0" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Sudah Dibayar (DP)</label>
                        <input type="number" name="paid_amount" class="form-control form-control-sm" placeholder="0" min="0" value="0">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold mb-1">No. Referensi / Nota</label>
                        <input type="text" name="reference_number" class="form-control form-control-sm" value="HUT/<?= date('Ym') ?>/<?= strtoupper(substr(uniqid(), -4)) ?>">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Keterangan / Catatan</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Cth: Pembelian kopi petani tempo 7 hari">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="submit" class="btn btn-sm btn-danger w-100 fw-bold shadow-sm">Simpan Hutang</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL BAYAR / CICIL HUTANG ATAU PIUTANG -->
<div class="modal fade" id="modalRecordPayment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content rounded-4 border-0 shadow">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="debt_type" id="pay_debt_type" value="receivable">
            <input type="hidden" name="debt_id" id="pay_debt_id" value="0">

            <div class="modal-header py-3 text-white" id="pay_modal_header" style="background: #0284c7;">
                <h6 class="modal-title fw-bold" id="pay_modal_title">Pembayaran</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="p-2 mb-3 bg-light rounded-3 border" style="font-size: 0.8rem;">
                    <div class="text-muted small">Mitra / Debitur:</div>
                    <div class="fw-bold fs-6 text-dark" id="pay_target_name">-</div>
                    <div class="d-flex justify-content-between mt-2 pt-1 border-top">
                        <span class="text-muted">Sisa Tagihan Saat Ini:</span>
                        <strong class="text-danger fs-6" id="pay_current_rem">Rp 0</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Nominal Pembayaran (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-white fw-bold">Rp</span>
                        <input type="number" name="payment_amount" id="pay_amount_input" class="form-control form-control-lg fw-bold text-success" placeholder="0" min="1" required>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-0 px-2" style="font-size: 0.72rem;" onclick="setPayAll()">
                            Bayar Lunas Penuh
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold mb-1">Catat ke Akun Kas/Bank</label>
                    <select name="cash_account_id" class="form-select form-select-sm">
                        <option value="0">-- Hanya Catat Pembukuan (Tanpa Mutasi Kas) --</option>
                        <?php foreach ($cashAccounts as $ca): ?>
                            <option value="<?= $ca['id'] ?>">
                                <?= e($ca['account_name']) ?> (Saldo: <?= format_rupiah($ca['current_balance']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text small" style="font-size: 0.7rem;">Pilih akun jika uang tunai/transfer langsung masuk/keluar ke kas perusahaan.</div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold mb-1">Catatan Transaksi</label>
                    <input type="text" name="payment_notes" class="form-control form-control-sm" placeholder="Cth: Transfer via BRI / Bayar Tunai Titip Supir">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 pb-3 px-3">
                <div class="row g-2 w-100">
                    <div class="col-4">
                        <button type="button" class="btn btn-sm btn-light w-100 text-muted" data-bs-dismiss="modal">Batal</button>
                    </div>
                    <div class="col-8">
                        <button type="submit" class="btn btn-sm btn-success w-100 fw-bold py-2 shadow-sm" id="pay_submit_btn">
                            <i class="bi bi-check-lg me-1"></i>Simpan Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let payModalInstance = null;
let currentPayRemaining = 0;

function getPayModal() {
    if (!payModalInstance) {
        payModalInstance = new bootstrap.Modal(document.getElementById('modalRecordPayment'));
    }
    return payModalInstance;
}

function openAddModalByTab() {
    const activeTab = '<?= $activeTab ?>';
    if (activeTab === 'piutang') {
        new bootstrap.Modal(document.getElementById('modalAddReceivable')).show();
    } else {
        new bootstrap.Modal(document.getElementById('modalAddPayable')).show();
    }
}

function openPaymentModal(type, id, name, total, paid, rem) {
    currentPayRemaining = rem;
    document.getElementById('pay_debt_type').value = type;
    document.getElementById('pay_debt_id').value = id;
    document.getElementById('pay_target_name').textContent = name;
    document.getElementById('pay_current_rem').textContent = 'Rp ' + Number(rem).toLocaleString('id-ID');
    document.getElementById('pay_amount_input').value = rem;
    document.getElementById('pay_amount_input').max = rem;

    const header = document.getElementById('pay_modal_header');
    const title  = document.getElementById('pay_modal_title');
    const btn    = document.getElementById('pay_submit_btn');

    if (type === 'receivable') {
        header.style.background = '#0284c7';
        title.innerHTML = '<i class="bi bi-arrow-down-left-circle-fill me-2"></i>Terima Pembayaran Piutang';
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Terima Pembayaran';
    } else {
        header.style.background = '#dc2626';
        title.innerHTML = '<i class="bi bi-arrow-up-right-circle-fill me-2"></i>Bayar Cicilan / Hutang';
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Simpan Pembayaran';
    }

    getPayModal().show();
    setTimeout(() => {
        document.getElementById('pay_amount_input')?.select();
    }, 200);
}

function setPayAll() {
    document.getElementById('pay_amount_input').value = currentPayRemaining;
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

function sendDebtWa(type, data) {
    const formatRp = (num) => 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    let phone = cleanPhoneForWa(data.phone);

    if (!phone) {
        phone = prompt('Nomor WhatsApp belum tersimpan. Masukkan nomor WhatsApp mitra (08...):');
        if (!phone) return;
        phone = cleanPhoneForWa(phone);
    }

    let text = '';
    if (type === 'receivable') {
        text = `*PEMBERITAHUAN TAGIHAN PIUTANG*\n*CV. MAJU BERKAH BERSAMA*\n━━━━━━━━━━━━━━━━━━━\n` +
               `Halo Yth. *${data.name}*,\n` +
               `Berikut rincian saldo tagihan yang tercatat di sistem MBB:\n\n` +
               `• No. Referensi : *${data.ref}*\n` +
               `• Lini Usaha    : ${data.line}\n` +
               `• Total Tagihan : ${formatRp(data.total)}\n` +
               `• Sudah Dibayar : ${formatRp(data.paid)}\n` +
               `• *SISA TAGIHAN : ${formatRp(data.rem)}*\n` +
               `• Jatuh Tempo   : ${data.due}\n` +
               `━━━━━━━━━━━━━━━━━━━\n` +
               `Mohon untuk konfirmasi atau melakukan pelunasan ke rekening resmi CV. Maju Berkah Bersama.\n` +
               `_Terima kasih atas kerja samanya._`;
    } else {
        text = `*KONFIRMASI HUTANG USAHA*\n*CV. MAJU BERKAH BERSAMA*\n━━━━━━━━━━━━━━━━━━━\n` +
               `Yth. Mitra *${data.name}*,\n` +
               `Berikut catatan pembukuan kewajiban/hutang kami:\n\n` +
               `• No. Referensi : *${data.ref}*\n` +
               `• Keterangan    : ${data.line}\n` +
               `• Total Nota    : ${formatRp(data.total)}\n` +
               `• Sudah Dibayar : ${formatRp(data.paid)}\n` +
               `• *SISA HUTANG  : ${formatRp(data.rem)}*\n` +
               `• Status        : ${data.status}\n` +
               `━━━━━━━━━━━━━━━━━━━\n` +
               `Catatan ini resmi tercatat dalam sistem operasional MBB Field System.\n` +
               `_Terima kasih._`;
    }

    const waUrl = `https://api.whatsapp.com/send?phone=${phone}&text=${encodeURIComponent(text)}`;
    window.open(waUrl, '_blank');
}

// Live Search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('debtSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.debt-card-item');
            items.forEach(el => {
                const text = el.getAttribute('data-filter') || '';
                el.style.display = text.includes(q) ? 'block' : 'none';
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
