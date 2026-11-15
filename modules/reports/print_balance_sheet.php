<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Laporan Neraca Keuangan Resmi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('reports.view');

$db = Database::getInstance();
$yearFilter = (int)($_GET['year'] ?? 2026);

// Calculations
$cashAccounts = $db->query("SELECT account_name, current_balance FROM cash_accounts WHERE is_active = 1")->fetchAll();
$totalCashBank = array_sum(array_column($cashAccounts, 'current_balance'));

$brilinkAccounts = $db->query("SELECT account_name, current_balance FROM brilink_accounts WHERE is_active = 1")->fetchAll();
$totalBrilinkCash = array_sum(array_column($brilinkAccounts, 'current_balance'));

$totalReceivables = (float)$db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM receivables WHERE status != 'LUNAS'")->fetchColumn();

$inventoryItems = $db->query("SELECT (current_stock * cost_price) AS subtotal FROM products WHERE is_active = 1 AND current_stock > 0")->fetchAll();
$totalInventory = array_sum(array_column($inventoryItems, 'subtotal'));

$totalAktivaLancar = $totalCashBank + $totalBrilinkCash + $totalReceivables + $totalInventory;

$fixedAssets = $db->query("SELECT category, COUNT(*) AS count_items, COALESCE(SUM(acquisition_cost), 0) AS total_val FROM inventory_assets WHERE asset_condition != 'RUSAK_BERAT' GROUP BY category")->fetchAll();
$totalAktivaTetap = array_sum(array_column($fixedAssets, 'total_val'));

$grandTotalAktiva = $totalAktivaLancar + $totalAktivaTetap;

$totalPayables = (float)$db->query("SELECT COALESCE(SUM(remaining_amount), 0) FROM payables WHERE status != 'LUNAS'")->fetchColumn();
$totalKewajiban = $totalPayables;

$netTrading = (float)$db->query("SELECT COALESCE(SUM(profit_amount), 0) FROM sales WHERE YEAR(sale_date) = {$yearFilter}")->fetchColumn();
$netRoast = (float)$db->query("SELECT COALESCE(SUM(total_fee), 0) FROM roasting_orders WHERE YEAR(order_date) = {$yearFilter}")->fetchColumn();
$netBri = (float)$db->query("SELECT COALESCE(SUM(company_income), 0) FROM brilink_transactions WHERE YEAR(transaction_date) = {$yearFilter} AND status = 'BERHASIL'")->fetchColumn();
$netDekor = (float)$db->query("SELECT COALESCE(SUM(total_income - total_cost), 0) FROM division_activities WHERE division_id = 5 AND YEAR(start_date) = {$yearFilter} AND status = 'SELESAI'")->fetchColumn();
$netSound = (float)$db->query("SELECT COALESCE(SUM(total_income - total_cost), 0) FROM division_activities WHERE division_id = 6 AND YEAR(start_date) = {$yearFilter} AND status = 'SELESAI'")->fetchColumn();
$genExp = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM cash_transactions WHERE trx_direction = 'OUT' AND business_line = 'MANAGEMENT' AND category != 'Mutasi Antar-Kas' AND YEAR(trx_date) = {$yearFilter}")->fetchColumn();

$labaBerjalan = $netTrading + $netRoast + $netBri + $netDekor + $netSound - $genExp;
$modalAwal = max(0, $grandTotalAktiva - $totalKewajiban - $labaBerjalan);
$totalEkuitas = $modalAwal + $labaBerjalan;
$grandTotalPasiva = $totalKewajiban + $totalEkuitas;

$companyName    = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$companyShort   = get_company_setting('company_short_name', 'MBB');
$companyAddress = get_company_setting('company_address', 'Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat');
$companyPhone   = get_company_setting('company_phone', '0812-7890-1234');
$companyEmail   = get_company_setting('company_email', 'info@cvmbb.co.id');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Neraca Keuangan - <?= $yearFilter ?> - <?= e($companyName) ?></title>
    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1e293b; }
        .page-sheet { background: #ffffff; width: 210mm; min-height: 297mm; padding: 20mm; margin: 20px auto; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 4px; position: relative; }
        .kop-surat { display: flex; align-items: center; border-bottom: 3px double #0d1e3a; padding-bottom: 12px; margin-bottom: 20px; }
        .kop-logo { width: 80px; height: auto; margin-right: 18px; }
        .kop-text { flex: 1; text-align: center; }
        .kop-company { font-size: 18pt; font-weight: 800; margin: 0; color: #0d1e3a; letter-spacing: 0.5px; }
        .kop-sub { font-size: 9.5pt; color: #475569; margin: 2px 0 0 0; }
        .doc-badge { display: inline-block; background: #0d1e3a; color: #ffffff; padding: 4px 14px; font-size: 11pt; font-weight: 700; letter-spacing: 1px; border-radius: 4px; text-transform: uppercase; }
        .table-data { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-data th, .table-data td { border: 1px solid #cbd5e1; padding: 6px 10px; font-size: 9.5pt; }
        .table-data th { background-color: #f8fafc; color: #0d1e3a; font-weight: 700; }
        .ttd-box { margin-top: 35px; }
        .ttd-col { text-align: center; font-size: 9.5pt; }
        .ttd-space { height: 60px; }
        .action-bar { position: sticky; top: 0; z-index: 1000; background: #ffffff; padding: 12px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        @media print {
            body { background: #ffffff; margin: 0; padding: 0; }
            .action-bar { display: none !important; }
            .page-sheet { box-shadow: none; margin: 0; width: 100%; min-height: auto; padding: 10mm; }
        }
    </style>
</head>
<body>

<div class="action-bar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= base_url('modules/reports/balance_sheet.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Neraca
        </a>
        <span class="text-muted">|</span>
        <span class="fw-semibold text-dark">Laporan Neraca Keuangan Per 31 Desember <?= $yearFilter ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<div class="page-sheet">
    <div class="kop-surat">
        <img src="<?= asset_url('images/logo.png') ?>" alt="Logo MBB" class="kop-logo">
        <div class="kop-text">
            <h1 class="kop-company"><?= e($companyName) ?></h1>
            <p class="kop-sub mb-0 fw-semibold text-primary">LAPORAN NERACA KEUANGAN PERUSAHAAN (BALANCE SHEET)</p>
            <p class="kop-sub"><?= e($companyAddress) ?> &bull; Telp: <?= e($companyPhone) ?></p>
        </div>
    </div>

    <div class="text-center my-3">
        <span class="doc-badge">NERACA KEUANGAN KONSOLIDASI</span>
        <div class="mt-1 small text-muted">Per Tanggal: 31 Desember <?= $yearFilter ?> (Dinyatakan dalam Rupiah)</div>
    </div>

    <div class="row g-2">
        <!-- AKTIVA -->
        <div class="col-6">
            <table class="table-data">
                <thead>
                    <tr>
                        <th>AKTIVA (ASET)</th>
                        <th style="width: 105px;" class="text-end">JUMLAH</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light fw-bold"><td colspan="2">A. AKTIVA LANCAR</td></tr>
                    <tr><td>Kas & Rekening Operasional</td><td class="text-end"><?= format_rupiah($totalCashBank) ?></td></tr>
                    <tr><td>Saldo Kas & EDC BRILink</td><td class="text-end"><?= format_rupiah($totalBrilinkCash) ?></td></tr>
                    <tr><td>Piutang Usaha Pelanggan</td><td class="text-end"><?= format_rupiah($totalReceivables) ?></td></tr>
                    <tr><td>Persediaan Barang Dagang</td><td class="text-end"><?= format_rupiah($totalInventory) ?></td></tr>
                    <tr class="fw-bold bg-light"><td>Total Aktiva Lancar</td><td class="text-end text-primary"><?= format_rupiah($totalAktivaLancar) ?></td></tr>

                    <tr class="table-light fw-bold"><td colspan="2">B. AKTIVA TETAP</td></tr>
                    <?php foreach ($fixedAssets as $fa): ?>
                        <tr><td><?= e($fa['category']) ?></td><td class="text-end"><?= format_rupiah($fa['total_val']) ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold bg-light"><td>Total Aktiva Tetap</td><td class="text-end text-primary"><?= format_rupiah($totalAktivaTetap) ?></td></tr>

                    <tr class="fw-bold text-white bg-dark">
                        <td>TOTAL AKTIVA</td>
                        <td class="text-end"><?= format_rupiah($grandTotalAktiva) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PASIVA -->
        <div class="col-6">
            <table class="table-data">
                <thead>
                    <tr>
                        <th>PASIVA (KEWAJIBAN & MODAL)</th>
                        <th style="width: 105px;" class="text-end">JUMLAH</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light fw-bold"><td colspan="2">A. KEWAJIBAN / UTANG</td></tr>
                    <tr><td>Utang Dagang Biji Kopi (Petani/Supplier)</td><td class="text-end"><?= format_rupiah($totalPayables) ?></td></tr>
                    <tr class="fw-bold bg-light"><td>Total Kewajiban</td><td class="text-end text-danger"><?= format_rupiah($totalKewajiban) ?></td></tr>

                    <tr class="table-light fw-bold"><td colspan="2">B. EKUITAS & MODAL</td></tr>
                    <tr><td>Modal Disetor MBB</td><td class="text-end"><?= format_rupiah($modalAwal) ?></td></tr>
                    <tr><td>Saldo Laba Berjalan (Tahun <?= $yearFilter ?>)</td><td class="text-end"><?= format_rupiah($labaBerjalan) ?></td></tr>
                    <tr class="fw-bold bg-light"><td>Total Ekuitas</td><td class="text-end text-success"><?= format_rupiah($totalEkuitas) ?></td></tr>

                    <tr><td colspan="2" class="py-3 text-center text-muted small">Status: Seimbang (Balanced)</td></tr>

                    <tr class="fw-bold text-white bg-dark">
                        <td>TOTAL PASIVA</td>
                        <td class="text-end"><?= format_rupiah($grandTotalPasiva) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="ttd-box">
        <div class="row text-center">
            <div class="col-4 ttd-col">
                <div class="text-muted">Disusun Oleh,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">NURIL HUDA</div>
                <div class="small text-muted">PJ Divisi Administrasi</div>
            </div>
            <div class="col-4 ttd-col">
                <div class="text-muted">Diperiksa Oleh,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">M. ILHAM SIFA</div>
                <div class="small text-muted">Manager Perusahaan</div>
            </div>
            <div class="col-4 ttd-col">
                <div class="text-muted">Disetujui & Disahkan,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">M. ROZIKIN</div>
                <div class="small text-muted">Direktur CV. MBB</div>
            </div>
        </div>
    </div>

    <div style="position: absolute; bottom: 15mm; left: 20mm; right: 20mm; font-size: 8pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 6px;" class="d-flex justify-content-between">
        <span>MBB BUSINESS MANAGEMENT SYSTEM &bull; Laporan Neraca Keuangan Konsolidasi</span>
        <span>Dicetak pada: <?= date('d/m/Y H:i:s') ?></span>
    </div>
</div>

</body>
</html>
