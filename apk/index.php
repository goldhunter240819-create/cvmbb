<?php
/**
 * MBB MOBILE APK - Beranda Aplikasi Mobile
 * CV. MAJU BERKAH BERSAMA
 */

$isHome = true;
$mobilePageTitle = 'Beranda';
$currentApkNav = 'home';

require_once __DIR__ . '/layout/header.php';

// Ambil Metrik Ringkas Real-time
$stmtCash = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM cash_accounts WHERE is_active = 1");
$totalCash = (float)$stmtCash->fetchColumn();

// Metrik Hutang & Piutang
$quickPiu = (float)$db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM receivables WHERE status != 'LUNAS'")->fetchColumn();
$quickHut = (float)$db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM payables WHERE status != 'LUNAS'")->fetchColumn();

// Ambil Semua Transaksi Lintas Divisi untuk Feed Aktivitas Terkini
$recentBuys = $db->query("
    SELECT 
        'BUY_COFFEE' as type,
        p.id,
        p.invoice_number as code,
        p.purchase_date as tdate,
        p.created_at,
        p.grand_total as amount,
        CONCAT('Beli Kopi - ', COALESCE(f.name, 'Petani')) as descr,
        CONCAT(FORMAT(p.net_weight, 1), ' Kg @ Rp ', FORMAT(p.price_per_kg, 0)) as sub_descr,
        p.payment_status as status,
        'OUT' as flow
    FROM purchases p
    LEFT JOIN farmers f ON p.farmer_id = f.id
    ORDER BY p.id DESC LIMIT 30
")->fetchAll();

$recentSales = $db->query("
    SELECT 
        'SELL_COFFEE' as type,
        s.id,
        s.invoice_number as code,
        s.sale_date as tdate,
        s.created_at,
        s.grand_total as amount,
        CONCAT('Jual Kopi - ', COALESCE(c.name, 'Pembeli')) as descr,
        CONCAT(FORMAT(s.total_weight_kg, 0), ' Kg') as sub_descr,
        s.payment_status as status,
        'IN' as flow
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    ORDER BY s.id DESC LIMIT 30
")->fetchAll();

$recentCash = $db->query("
    SELECT 
        'CASH' as type,
        t.id,
        t.trx_code as code,
        t.trx_date as tdate,
        t.created_at,
        t.amount,
        COALESCE(t.category, 'Mutasi Kas') as descr,
        COALESCE(t.description, a.account_name) as sub_descr,
        'LUNAS' as status,
        t.trx_direction as flow
    FROM cash_transactions t
    LEFT JOIN cash_accounts a ON t.cash_account_id = a.id
    ORDER BY t.id DESC LIMIT 30
")->fetchAll();

$recentBri = $db->query("
    SELECT 
        'BRILINK' as type,
        b.id,
        b.reference_no as code,
        b.transaction_date as tdate,
        b.created_at,
        b.nominal_amount as amount,
        CONCAT('BRILink - ', b.trx_type) as descr,
        COALESCE(b.customer_name, b.trx_type) as sub_descr,
        b.status,
        'NEUTRAL' as flow
    FROM brilink_transactions b
    ORDER BY b.id DESC LIMIT 30
")->fetchAll();

$recentRoast = $db->query("
    SELECT 
        'ROASTING' as type,
        r.id,
        r.order_number as code,
        r.order_date as tdate,
        r.created_at,
        r.total_fee as amount,
        CONCAT('Roasting - ', COALESCE(c.name, 'Pelanggan')) as descr,
        CONCAT(FORMAT(r.raw_weight_kg, 1), ' Kg Raw') as sub_descr,
        r.payment_status as status,
        'IN' as flow
    FROM roasting_orders r
    LEFT JOIN customers c ON r.customer_id = c.id
    ORDER BY r.id DESC LIMIT 30
")->fetchAll();

// Gabungkan Semua & Urutkan Berdasarkan Waktu Terkini
$allTransactions = array_merge($recentBuys, $recentSales, $recentCash, $recentBri, $recentRoast);
usort($allTransactions, function($a, $b) {
    $timeA = !empty($a['created_at']) ? strtotime($a['created_at']) : strtotime($a['tdate']);
    $timeB = !empty($b['created_at']) ? strtotime($b['created_at']) : strtotime($b['tdate']);
    return $timeB - $timeA;
});

// Ambil 5 Teratas untuk Ditampilkan di Beranda
$displayFeed = array_slice($allTransactions, 0, 5);
?>

<style>
/* Mobile Header */
.mobile-header {
    background: linear-gradient(180deg, var(--mbb-navy) 0%, #152c52 100%);
    color: #ffffff;
    padding: 16px 20px 48px 20px;
    border-bottom-left-radius: 26px;
    border-bottom-right-radius: 26px;
    position: relative;
}
.avatar-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #1a56db;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border: 2px solid rgba(255,255,255,0.2);
}

/* Saldo Wallet Card */
.wallet-card {
    margin: -36px 16px 16px 16px;
    background: #ffffff;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 10px 25px rgba(13, 30, 58, 0.08);
    position: relative;
    z-index: 10;
}
.wallet-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #334155;
    font-size: 0.72rem;
    font-weight: 600;
}
.wallet-action-btn .btn-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 6px;
}

/* Grid Icons Lini Usaha */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px 6px;
    padding: 16px 8px 14px 8px;
    background: #ffffff;
    margin: 0 16px 16px 16px;
    border-radius: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.grid-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-decoration: none;
    color: #1e293b;
    text-align: center;
    padding: 2px 2px 4px 2px;
}
.grid-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.28rem;
    margin-bottom: 5px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.05);
    transition: transform 0.15s ease;
}
.grid-item:active .grid-icon-box {
    transform: scale(0.92);
}
.grid-label {
    font-size: 0.66rem;
    font-weight: 600;
    line-height: 1.24;
    color: #334155;
    text-align: center;
    word-break: break-word;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 28px;
    padding: 0 1px;
}

/* Banner Promo */
.promo-banner {
    margin: 0 16px 16px 16px;
    background: linear-gradient(135deg, #1e3a8a 0%, #0284c7 100%);
    border-radius: 16px;
    padding: 14px 18px;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
    padding: 0 20px 8px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.trx-feed-card {
    background: #ffffff;
    margin: 0 16px 8px 16px;
    border-radius: 14px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
    border: 1px solid #f1f5f9;
}
</style>

<!-- HEADER APP -->
<div class="mobile-header">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])): ?>
                <img src="<?= base_url($user['avatar']) ?>?v=<?= time() ?>" alt="Avatar" class="avatar-circle" style="object-fit: cover;">
            <?php else: ?>
                <div class="avatar-circle">
                    <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) ?>
                </div>
            <?php endif; ?>
            <div>
                <div class="text-white-50" style="font-size: 0.72rem;">Selamat datang,</div>
                <div class="fw-bold text-white" style="font-size: 0.95rem; line-height: 1.1;">
                    <?= e($user['full_name'] ?: $user['username']) ?>
                </div>
                <span class="badge bg-white text-dark mt-1" style="font-size: 0.6rem;"><?= e($companyShort) ?> MOBILE</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('apk/profile.php') ?>" class="btn btn-sm btn-outline-light rounded-circle p-2" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-person-fill"></i>
            </a>
        </div>
    </div>
</div>

<!-- WALLET KAS PERUSAHAAN (ALA BRIMO / DANA) -->
<div class="wallet-card">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="text-muted small fw-semibold">Saldo Kas Likuid MBB</span>
        <a href="<?= base_url('apk/finance.php') ?>" class="badge bg-light text-primary border text-decoration-none" style="font-size: 0.68rem;">
            Buku Kas &raquo;
        </a>
    </div>
    <h4 class="fw-bold text-primary mb-2"><?= format_rupiah($totalCash) ?></h4>

    <!-- Ringkasan Hutang & Piutang Cepat -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <a href="<?= base_url('apk/debts.php?tab=piutang') ?>" class="d-block p-2 rounded-3 text-decoration-none bg-primary-subtle border border-primary border-opacity-25" style="font-size: 0.72rem;">
                <div class="text-muted d-flex justify-content-between align-items-center">
                    <span>Piutang (Tagihan)</span>
                    <i class="bi bi-arrow-down-left text-primary"></i>
                </div>
                <strong class="text-primary fs-6"><?= format_rupiah($quickPiu) ?></strong>
            </a>
        </div>
        <div class="col-6">
            <a href="<?= base_url('apk/debts.php?tab=hutang') ?>" class="d-block p-2 rounded-3 text-decoration-none bg-danger-subtle border border-danger border-opacity-25" style="font-size: 0.72rem;">
                <div class="text-muted d-flex justify-content-between align-items-center">
                    <span>Hutang Usaha</span>
                    <i class="bi bi-arrow-up-right text-danger"></i>
                </div>
                <strong class="text-danger fs-6"><?= format_rupiah($quickHut) ?></strong>
            </a>
        </div>
    </div>

    <div class="d-flex justify-content-around border-top pt-3">
        <a href="<?= base_url('apk/finance.php') ?>" class="wallet-action-btn">
            <div class="btn-icon bg-success-subtle text-success">
                <i class="bi bi-arrow-down-left-circle-fill"></i>
            </div>
            <span>Kas Masuk</span>
        </a>
        <a href="<?= base_url('apk/finance.php') ?>" class="wallet-action-btn">
            <div class="btn-icon bg-danger-subtle text-danger">
                <i class="bi bi-arrow-up-right-circle-fill"></i>
            </div>
            <span>Kas Keluar</span>
        </a>
        <a href="<?= base_url('apk/finance.php') ?>" class="wallet-action-btn">
            <div class="btn-icon bg-info-subtle text-info">
                <i class="bi bi-arrow-left-right"></i>
            </div>
            <span>Mutasi</span>
        </a>
        <a href="<?= base_url('apk/balance_sheet.php') ?>" class="wallet-action-btn">
            <div class="btn-icon bg-primary-subtle text-primary">
                <i class="bi bi-bank"></i>
            </div>
            <span>Neraca</span>
        </a>
    </div>
</div>

<!-- PROMO BANNER / STATUS LAPANGAN -->
<div class="promo-banner">
    <div>
        <div class="fw-bold" style="font-size: 0.82rem;">CV. MAJU BERKAH BERSAMA</div>
        <div style="font-size: 0.72rem; opacity: 0.9;">Gudang & Operasional Air Hitam Siaga Penuh</div>
    </div>
    <span class="badge bg-white text-dark font-monospace" style="font-size: 0.65rem;">ONLINE</span>
</div>

<!-- MENU GRID LAYANAN (ALA MTSRs, AULA CELL, MIDMIFHDA) -->
<div class="section-title">
    <span>Layanan & Lini Usaha</span>
    <span class="text-muted small" style="font-size: 0.7rem;">Operasional MBB</span>
</div>

<div class="menu-grid">
    <!-- 1. Beli Kopi -->
    <a href="<?= base_url('apk/coffee_buy.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #ecfdf5; color: #059669;">
            <i class="bi bi-cart-plus-fill"></i>
        </div>
        <span class="grid-label">Beli Kopi</span>
    </a>

    <!-- 2. Jual Kopi -->
    <a href="<?= base_url('apk/coffee_sell.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #eff6ff; color: #1d4ed8;">
            <i class="bi bi-truck"></i>
        </div>
        <span class="grid-label">Jual Kopi</span>
    </a>

    <!-- 3. Buku Hutang & Piutang -->
    <a href="<?= base_url('apk/debts.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fef3c7; color: #b45309;">
            <i class="bi bi-journal-bookmark-fill"></i>
        </div>
        <span class="grid-label">Hutang Piutang</span>
    </a>

    <!-- 4. Buku Pelanggan & Mitra -->
    <a href="<?= base_url('apk/customers.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #e0e7ff; color: #4338ca;">
            <i class="bi bi-person-lines-fill"></i>
        </div>
        <span class="grid-label">Buku Kontak</span>
    </a>

    <!-- 4. Jasa Roasting -->
    <a href="<?= base_url('apk/roasting.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fffbeb; color: #d97706;">
            <i class="bi bi-fire"></i>
        </div>
        <span class="grid-label">Jasa Roasting</span>
    </a>

    <!-- 5. Produksi Kopi -->
    <a href="<?= base_url('apk/production.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fdf2f8; color: #db2777;">
            <i class="bi bi-gear-wide-connected"></i>
        </div>
        <span class="grid-label">Produksi Bubuk</span>
    </a>

    <!-- 6. Agen BRILink -->
    <a href="<?= base_url('apk/brilink.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #f0fdf4; color: #16a34a;">
            <i class="bi bi-credit-card-2-front-fill"></i>
        </div>
        <span class="grid-label">Agen BRILink</span>
    </a>

    <!-- 7. Dekorasi -->
    <a href="<?= base_url('apk/decoration.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #faf5ff; color: #9333ea;">
            <i class="bi bi-flower1"></i>
        </div>
        <span class="grid-label">Divisi Dekor</span>
    </a>

    <!-- 8. Sound System -->
    <a href="<?= base_url('apk/sound_system.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fff1f2; color: #e11d48;">
            <i class="bi bi-speaker-fill"></i>
        </div>
        <span class="grid-label">Sound Sistem</span>
    </a>

    <!-- 9. Persuratan -->
    <a href="<?= base_url('apk/letters.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #f1f5f9; color: #475569;">
            <i class="bi bi-file-earmark-text-fill"></i>
        </div>
        <span class="grid-label">Surat Resmi</span>
    </a>

    <!-- 10. Laba Rugi -->
    <a href="<?= base_url('apk/reports.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fefce8; color: #ca8a04;">
            <i class="bi bi-graph-up-arrow"></i>
        </div>
        <span class="grid-label">Laba Rugi</span>
    </a>

    <!-- 11. Neraca Keuangan -->
    <a href="<?= base_url('apk/balance_sheet.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #e0f2fe; color: #0284c7;">
            <i class="bi bi-bank"></i>
        </div>
        <span class="grid-label">Neraca Kas</span>
    </a>

    <!-- 12. Inventaris Aset -->
    <a href="<?= base_url('apk/inventory.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #f8fafc; color: #334155; border: 1px solid #e2e8f0;">
            <i class="bi bi-boxes"></i>
        </div>
        <span class="grid-label">Aset Mesin</span>
    </a>
</div>

<!-- AKTIVITAS & TRANSAKSI TERAKHIR -->
<div class="section-title mt-2">
    <span>Transaksi Terkini (<?= count($displayFeed) ?> Terakhir)</span>
    <a href="javascript:void(0)" onclick="openAllTrxModal()" class="text-primary text-decoration-none small fw-bold" style="font-size: 0.74rem;">
        Semua (<?= count($allTransactions) ?>) &raquo;
    </a>
</div>

<?php if (empty($displayFeed)): ?>
    <div class="mobile-card text-center py-4 text-muted mb-4 border-0 shadow-sm">
        <i class="bi bi-clock-history fs-2 d-block mb-1 text-primary opacity-50"></i>
        <div class="small fw-bold text-dark">Belum Ada Riwayat Transaksi</div>
        <div class="text-muted" style="font-size: 0.72rem;">Mulai catat transaksi melalui menu Beli Kopi, Jual Kopi, atau Kas di atas.</div>
    </div>
<?php else: ?>
    <?php foreach ($displayFeed as $rf): ?>
        <div class="trx-feed-card">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 rounded-circle <?php
                    if ($rf['type'] === 'BUY_COFFEE') echo 'bg-success-subtle text-success';
                    elseif ($rf['type'] === 'SELL_COFFEE') echo 'bg-primary-subtle text-primary';
                    elseif ($rf['type'] === 'BRILINK') echo 'bg-warning-subtle text-warning-emphasis';
                    elseif ($rf['type'] === 'ROASTING') echo 'bg-secondary-subtle text-secondary';
                    else echo ($rf['flow'] === 'IN') ? 'bg-info-subtle text-info' : 'bg-danger-subtle text-danger';
                ?>" style="width: 38px; height: 38px; display:flex; align-items:center; justify-content:center; flex-shrink: 0;">
                    <?php if ($rf['type'] === 'BUY_COFFEE'): ?>
                        <i class="bi bi-cart-plus-fill"></i>
                    <?php elseif ($rf['type'] === 'SELL_COFFEE'): ?>
                        <i class="bi bi-truck"></i>
                    <?php elseif ($rf['type'] === 'BRILINK'): ?>
                        <i class="bi bi-credit-card-2-front-fill"></i>
                    <?php elseif ($rf['type'] === 'ROASTING'): ?>
                        <i class="bi bi-fire"></i>
                    <?php else: ?>
                        <i class="bi <?= $rf['flow'] === 'IN' ? 'bi-arrow-down-left-circle-fill' : 'bi-arrow-up-right-circle-fill' ?>"></i>
                    <?php endif; ?>
                </div>
                <div style="line-height: 1.2;">
                    <div class="fw-bold text-dark" style="font-size: 0.82rem;"><?= e($rf['descr']) ?></div>
                    <div class="text-muted" style="font-size: 0.68rem;"><?= e($rf['code']) ?> &bull; <?= date('d/m/Y', strtotime($rf['tdate'])) ?></div>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold <?php
                    if ($rf['flow'] === 'IN') echo 'text-success';
                    elseif ($rf['flow'] === 'OUT') echo 'text-danger';
                    else echo 'text-primary';
                ?>" style="font-size: 0.82rem;">
                    <?= ($rf['flow'] === 'IN' ? '+' : ($rf['flow'] === 'OUT' ? '-' : '')) . ' ' . format_rupiah($rf['amount']) ?>
                </div>
                <span class="badge <?php
                    if (($rf['status'] ?? '') === 'LUNAS' || ($rf['status'] ?? '') === 'BERHASIL') echo 'bg-success-subtle text-success border border-success border-opacity-25';
                    elseif (($rf['status'] ?? '') === 'SEBAGIAN') echo 'bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25';
                    else echo 'bg-danger-subtle text-danger border border-danger border-opacity-25';
                ?>" style="font-size: 0.6rem;">
                    <?= e($rf['status'] ?: 'Sukses') ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="px-3 mb-4 mt-2">
        <button type="button" class="btn btn-sm btn-light border w-100 rounded-pill text-primary fw-semibold py-2 shadow-sm" onclick="openAllTrxModal()">
            <i class="bi bi-view-list me-1"></i> Buka Semua Riwayat Transaksi (<?= count($allTransactions) ?>)
        </button>
    </div>
<?php endif; ?>

<!-- MODAL SEMUA RIWAYAT TRANSAKSI -->
<div class="modal fade" id="modalAllTransactions" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Semua Riwayat Transaksi MBB</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <!-- Search Box -->
                <div class="input-group input-group-sm mb-3 rounded-pill overflow-hidden bg-white border shadow-sm">
                    <span class="input-group-text bg-white border-0 ps-3 pe-2 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="allTrxSearchInput" class="form-control border-0 ps-1" placeholder="Cari nota, mitra, kategori..." style="box-shadow: none;" oninput="applyTrxFilter()">
                </div>

                <!-- Tabs Filter -->
                <div class="d-flex gap-1 overflow-x-auto pb-2 mb-2" style="white-space: nowrap;">
                    <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-semibold filter-tab-btn active" onclick="filterTrxModal('ALL', this)" style="font-size: 0.72rem;">Semua (<?= count($allTransactions) ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-tab-btn" onclick="filterTrxModal('BUY_COFFEE', this)" style="font-size: 0.72rem;">Beli Kopi (<?= count($recentBuys) ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-tab-btn" onclick="filterTrxModal('SELL_COFFEE', this)" style="font-size: 0.72rem;">Jual Kopi (<?= count($recentSales) ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-tab-btn" onclick="filterTrxModal('CASH', this)" style="font-size: 0.72rem;">Mutasi Kas (<?= count($recentCash) ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-tab-btn" onclick="filterTrxModal('BRILINK', this)" style="font-size: 0.72rem;">BRILink (<?= count($recentBri) ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 fw-semibold filter-tab-btn" onclick="filterTrxModal('ROASTING', this)" style="font-size: 0.72rem;">Roasting (<?= count($recentRoast) ?>)</button>
                </div>

                <!-- Daftar Transaksi Modal -->
                <div id="allTrxListContainer" class="d-flex flex-column gap-2" style="max-height: 420px; overflow-y: auto;">
                    <?php if (empty($allTransactions)): ?>
                        <div class="text-center py-4 text-muted small">
                            <i class="bi bi-inbox fs-2 d-block mb-1 opacity-50"></i>
                            Belum ada riwayat transaksi yang tercatat.
                        </div>
                    <?php else: ?>
                        <?php foreach ($allTransactions as $at): ?>
                            <?php
                            $filterStr = strtolower($at['type'] . ' ' . $at['code'] . ' ' . $at['descr'] . ' ' . ($at['sub_descr'] ?? '') . ' ' . ($at['status'] ?? ''));
                            ?>
                            <div class="all-trx-item p-2 rounded-3 border bg-white d-flex align-items-center justify-content-between" 
                                 data-type="<?= e($at['type']) ?>" 
                                 data-filter="<?= e($filterStr) ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="p-2 rounded-circle <?php
                                        if ($at['type'] === 'BUY_COFFEE') echo 'bg-success-subtle text-success';
                                        elseif ($at['type'] === 'SELL_COFFEE') echo 'bg-primary-subtle text-primary';
                                        elseif ($at['type'] === 'BRILINK') echo 'bg-warning-subtle text-warning-emphasis';
                                        elseif ($at['type'] === 'ROASTING') echo 'bg-secondary-subtle text-secondary';
                                        else echo ($at['flow'] === 'IN') ? 'bg-info-subtle text-info' : 'bg-danger-subtle text-danger';
                                    ?>" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center; flex-shrink: 0;">
                                        <?php if ($at['type'] === 'BUY_COFFEE'): ?>
                                            <i class="bi bi-cart-plus-fill"></i>
                                        <?php elseif ($at['type'] === 'SELL_COFFEE'): ?>
                                            <i class="bi bi-truck"></i>
                                        <?php elseif ($at['type'] === 'BRILINK'): ?>
                                            <i class="bi bi-credit-card-2-front-fill"></i>
                                        <?php elseif ($at['type'] === 'ROASTING'): ?>
                                            <i class="bi bi-fire"></i>
                                        <?php else: ?>
                                            <i class="bi <?= $at['flow'] === 'IN' ? 'bi-arrow-down-left-circle-fill' : 'bi-arrow-up-right-circle-fill' ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="line-height: 1.2;">
                                        <div class="fw-bold text-dark" style="font-size: 0.8rem;"><?= e($at['descr']) ?></div>
                                        <div class="text-muted" style="font-size: 0.68rem;">
                                            <?= e($at['code']) ?> &bull; <?= date('d/m/Y', strtotime($at['tdate'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold <?php
                                        if ($at['flow'] === 'IN') echo 'text-success';
                                        elseif ($at['flow'] === 'OUT') echo 'text-danger';
                                        else echo 'text-primary';
                                    ?>" style="font-size: 0.8rem;">
                                        <?= ($at['flow'] === 'IN' ? '+' : ($at['flow'] === 'OUT' ? '-' : '')) . ' ' . format_rupiah($at['amount']) ?>
                                    </div>
                                    <span class="badge <?php
                                        if (($at['status'] ?? '') === 'LUNAS' || ($at['status'] ?? '') === 'BERHASIL') echo 'bg-success-subtle text-success border border-success border-opacity-25';
                                        elseif (($at['status'] ?? '') === 'SEBAGIAN') echo 'bg-warning-subtle text-warning-emphasis border border-warning border-opacity-25';
                                        else echo 'bg-danger-subtle text-danger border border-danger border-opacity-25';
                                    ?>" style="font-size: 0.6rem;">
                                        <?= e($at['status'] ?: 'Sukses') ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer border-0 p-2">
                <button type="button" class="btn btn-sm btn-light w-100 text-muted fw-semibold py-2" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
let allTrxModalInstance = null;
let currentTrxTypeFilter = 'ALL';

function getAllTrxModal() {
    if (!allTrxModalInstance) {
        allTrxModalInstance = new bootstrap.Modal(document.getElementById('modalAllTransactions'));
    }
    return allTrxModalInstance;
}

function openAllTrxModal() {
    getAllTrxModal().show();
    setTimeout(() => {
        document.getElementById('allTrxSearchInput')?.focus();
    }, 300);
}

function filterTrxModal(type, btn) {
    currentTrxTypeFilter = type;
    document.querySelectorAll('.filter-tab-btn').forEach(b => {
        b.classList.remove('btn-primary', 'active');
        b.classList.add('btn-outline-secondary');
    });
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-primary', 'active');
    applyTrxFilter();
}

function applyTrxFilter() {
    const q = (document.getElementById('allTrxSearchInput')?.value || '').toLowerCase().trim();
    const items = document.querySelectorAll('.all-trx-item');
    items.forEach(el => {
        const itemType = el.getAttribute('data-type') || '';
        const itemText = el.getAttribute('data-filter') || '';
        const matchType = (currentTrxTypeFilter === 'ALL' || itemType === currentTrxTypeFilter);
        const matchSearch = (!q || itemText.includes(q));
        el.style.display = (matchType && matchSearch) ? 'flex' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
