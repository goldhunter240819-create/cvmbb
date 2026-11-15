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

// Transaksi Terkini
$recentSales = $db->query("
    SELECT 'COFFEE' as type, invoice_number as code, sale_date as tdate, grand_total as amount, 'Penjualan Kopi' as descr 
    FROM sales ORDER BY id DESC LIMIT 3
")->fetchAll();

$recentBri = $db->query("
    SELECT 'BRILINK' as type, reference_no as code, transaction_date as tdate, nominal_amount as amount, trx_type as descr 
    FROM brilink_transactions ORDER BY id DESC LIMIT 3
")->fetchAll();

$feed = array_merge($recentSales, $recentBri);
usort($feed, fn($a, $b) => strcmp($b['tdate'], $a['tdate']));
$recentFeed = array_slice($feed, 0, 4);
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
    gap: 16px 8px;
    padding: 14px 12px;
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
}
.grid-icon-box {
    width: 52px;
    height: 52px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    margin-bottom: 6px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.06);
    transition: transform 0.15s ease;
}
.grid-item:active .grid-icon-box {
    transform: scale(0.9);
}
.grid-label {
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1.2;
    color: #334155;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 24px;
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
            <div class="avatar-circle">
                <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) ?>
            </div>
            <div>
                <div class="text-white-50" style="font-size: 0.72rem;">Selamat datang,</div>
                <div class="fw-bold text-white" style="font-size: 0.95rem; line-height: 1.1;">
                    <?= e($user['full_name'] ?: $user['username']) ?>
                </div>
                <span class="badge bg-white text-dark mt-1" style="font-size: 0.6rem;"><?= e($companyShort) ?> MOBILE</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= base_url('index.php') ?>" class="btn btn-sm btn-outline-light rounded-pill px-2 py-1 text-decoration-none fw-semibold" style="font-size: 0.72rem;" title="Buka Versi Desktop Web">
                <i class="bi bi-display me-1"></i>Web
            </a>
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
    <h4 class="fw-bold text-primary mb-3"><?= format_rupiah($totalCash) ?></h4>

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
    <span class="text-muted small" style="font-size: 0.7rem;">12 Fitur MBB</span>
</div>

<div class="menu-grid">
    <!-- 1. Beli Kopi -->
    <a href="<?= base_url('apk/coffee_buy.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #ecfdf5; color: #059669;">
            <i class="bi bi-cart-plus-fill"></i>
        </div>
        <span class="grid-label">Beli Kopi Petani</span>
    </a>

    <!-- 2. Jual Kopi -->
    <a href="<?= base_url('apk/coffee_sell.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #eff6ff; color: #1d4ed8;">
            <i class="bi bi-truck"></i>
        </div>
        <span class="grid-label">Jual Kopi Trading</span>
    </a>

    <!-- 3. Jasa Roasting -->
    <a href="<?= base_url('apk/roasting.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fffbeb; color: #d97706;">
            <i class="bi bi-fire"></i>
        </div>
        <span class="grid-label">Jasa Roasting</span>
    </a>

    <!-- 4. Produksi Kopi -->
    <a href="<?= base_url('apk/production.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fdf2f8; color: #db2777;">
            <i class="bi bi-gear-wide-connected"></i>
        </div>
        <span class="grid-label">Produksi Bubuk</span>
    </a>

    <!-- 5. Agen BRILink -->
    <a href="<?= base_url('apk/brilink.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #f0fdf4; color: #16a34a;">
            <i class="bi bi-credit-card-2-front-fill"></i>
        </div>
        <span class="grid-label">Agen BRILink</span>
    </a>

    <!-- 6. Dekorasi -->
    <a href="<?= base_url('apk/decoration.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #faf5ff; color: #9333ea;">
            <i class="bi bi-flower1"></i>
        </div>
        <span class="grid-label">Divisi Dekor</span>
    </a>

    <!-- 7. Sound System -->
    <a href="<?= base_url('apk/sound_system.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #fff1f2; color: #e11d48;">
            <i class="bi bi-speaker-fill"></i>
        </div>
        <span class="grid-label">Sound Sistem</span>
    </a>

    <!-- 8. Persuratan -->
    <a href="<?= base_url('apk/letters.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #f1f5f9; color: #475569;">
            <i class="bi bi-file-earmark-text-fill"></i>
        </div>
        <span class="grid-label">Surat Resmi</span>
    </a>

    <!-- 9. Struktur MBB -->
    <a href="<?= base_url('apk/organization.php') ?>" class="grid-item">
        <div class="grid-icon-box" style="background: #f0fdfa; color: #0d9488;">
            <i class="bi bi-diagram-3-fill"></i>
        </div>
        <span class="grid-label">Bagan Pejabat</span>
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
    <span>Transaksi Terkini</span>
    <a href="<?= base_url('apk/finance.php') ?>" class="text-primary text-decoration-none small" style="font-size: 0.72rem;">Semua</a>
</div>

<?php foreach ($recentFeed as $rf): ?>
    <div class="trx-feed-card">
        <div class="d-flex align-items-center gap-2">
            <div class="p-2 rounded-circle <?= $rf['type'] === 'COFFEE' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' ?>" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                <i class="bi <?= $rf['type'] === 'COFFEE' ? 'bi-cup-hot-fill' : 'bi-credit-card-2-front-fill' ?>"></i>
            </div>
            <div>
                <div class="fw-bold" style="font-size: 0.78rem;"><?= e($rf['descr']) ?></div>
                <div class="text-muted" style="font-size: 0.68rem;"><?= e($rf['code']) ?> &bull; <?= date('d/m/Y', strtotime($rf['tdate'])) ?></div>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-bold text-success" style="font-size: 0.8rem;"><?= format_rupiah($rf['amount']) ?></div>
            <span class="badge bg-success-subtle text-success" style="font-size: 0.6rem;">Berhasil</span>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
