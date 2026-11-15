<?php
/**
 * MBB MOBILE APK - Laporan Laba Rugi Konsolidasi
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Laba Rugi Konsolidasi';
$currentApkNav   = 'finance';

require_once __DIR__ . '/layout/header.php';

$yearFilter = date('Y');

// Laba rugi per lini
$trading = (float)$db->query("SELECT COALESCE(SUM(profit_amount),0) FROM sales WHERE YEAR(sale_date) = {$yearFilter}")->fetchColumn();
$roast   = (float)$db->query("SELECT COALESCE(SUM(total_fee),0) FROM roasting_orders WHERE YEAR(order_date) = {$yearFilter}")->fetchColumn();
$bri     = (float)$db->query("SELECT COALESCE(SUM(company_income),0) FROM brilink_transactions WHERE YEAR(transaction_date) = {$yearFilter} AND status = 'BERHASIL'")->fetchColumn();
$dekor   = (float)$db->query("SELECT COALESCE(SUM(total_income - total_cost),0) FROM division_activities WHERE division_id = 5 AND YEAR(start_date) = {$yearFilter} AND status = 'SELESAI'")->fetchColumn();
$sound   = (float)$db->query("SELECT COALESCE(SUM(total_income - total_cost),0) FROM division_activities WHERE division_id = 6 AND YEAR(start_date) = {$yearFilter} AND status = 'SELESAI'")->fetchColumn();
$genExp  = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = {$yearFilter}")->fetchColumn();

$totalNetProfit = $trading + $roast + $bri + $dekor + $sound - $genExp;
?>

<!-- KPI CARD LABA BERSIH KONSOLIDASI -->
<div class="mobile-card mt-3" style="background: linear-gradient(135deg, #0d1e3a 0%, #1e3a8a 100%); color: #ffffff; border: none; box-shadow: 0 10px 25px rgba(13, 30, 58, 0.25);">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="small text-white-50 fw-semibold">Konsolidasi Laba Bersih Perusahaan</span>
        <span class="badge bg-white text-dark small px-2 py-1 font-monospace">TAHUN <?= $yearFilter ?></span>
    </div>
    <h3 class="fw-bold mb-2 text-warning"><?= format_rupiah($totalNetProfit) ?></h3>
    <div class="small pt-2 border-top border-white border-opacity-25 mt-2 opacity-95">
        Akumulasi bersih dari seluruh 5 unit usaha operasional CV. MBB.
    </div>
</div>

<!-- SUB-NAV TOGGLE (LABA RUGI VS NERACA) -->
<div class="px-3 mb-3">
    <div class="d-flex p-1 bg-white rounded-pill border shadow-sm">
        <a href="<?= base_url('apk/reports.php') ?>" class="btn btn-sm btn-primary rounded-pill flex-fill fw-bold text-white shadow-sm py-2">
            <i class="bi bi-graph-up me-1"></i> Laba Rugi
        </a>
        <a href="<?= base_url('apk/balance_sheet.php') ?>" class="btn btn-sm btn-light rounded-pill flex-fill text-muted py-2 fw-semibold">
            <i class="bi bi-bank me-1"></i> Neraca Keuangan
        </a>
    </div>
</div>

<div class="px-3 pb-2 text-muted small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.72rem;">
    Kontribusi Laba Tiap Lini Usaha MBB
</div>

<!-- 1. Coffee Trading -->
<div class="mobile-card">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.2rem;">
                <i class="bi bi-cup-hot-fill"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size: 0.9rem;">1. Coffee Trading</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Perdagangan biji kopi ekspor/lokal</span>
            </div>
        </div>
        <div class="text-end fw-bold text-success fs-6"><?= format_rupiah($trading) ?></div>
    </div>
</div>

<!-- 2. Roasting Service -->
<div class="mobile-card">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.2rem;">
                <i class="bi bi-fire"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size: 0.9rem;">2. Jasa Roasting</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Maklon sangrai kopi pelanggan</span>
            </div>
        </div>
        <div class="text-end fw-bold text-success fs-6"><?= format_rupiah($roast) ?></div>
    </div>
</div>

<!-- 3. Agen BRILink -->
<div class="mobile-card">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.2rem;">
                <i class="bi bi-credit-card-2-front-fill"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size: 0.9rem;">3. Agen BRILink</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Pendapatan margin fee transaksi</span>
            </div>
        </div>
        <div class="text-end fw-bold text-success fs-6"><?= format_rupiah($bri) ?></div>
    </div>
</div>

<!-- 4. Divisi Dekorasi -->
<div class="mobile-card">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.2rem;">
                <i class="bi bi-flower1"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size: 0.9rem;">4. Divisi Dekoration</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Laba bersih job pesta & pelaminan</span>
            </div>
        </div>
        <div class="text-end fw-bold text-success fs-6"><?= format_rupiah($dekor) ?></div>
    </div>
</div>

<!-- 5. Divisi Sound Sistem -->
<div class="mobile-card">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-secondary-subtle text-dark d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; font-size: 1.2rem;">
                <i class="bi bi-speaker-fill"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size: 0.9rem;">5. Divisi Sound Sistem</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Laba sewa audio line-array & genset</span>
            </div>
        </div>
        <div class="text-end fw-bold text-success fs-6"><?= format_rupiah($sound) ?></div>
    </div>
</div>

<!-- 6. Beban Operasional Umum -->
<div class="mobile-card">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-light text-muted d-flex align-items-center justify-content-center border" style="width: 42px; height: 42px; font-size: 1.2rem;">
                <i class="bi bi-dash-circle"></i>
            </div>
            <div>
                <strong class="d-block text-dark" style="font-size: 0.9rem;">6. Beban Usaha & Kantor</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Biaya listrik, kantor, administrasi</span>
            </div>
        </div>
        <div class="text-end fw-bold text-danger fs-6">- <?= format_rupiah($genExp) ?></div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
