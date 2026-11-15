<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Laporan Keuangan & Laba Rugi Konsolidasi Resmi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('reports.view');

$db = Database::getInstance();

$yearFilter  = (int)($_GET['year'] ?? 2026);
$monthFilter = trim($_GET['month'] ?? '');

$dateConditionSales = "YEAR(sale_date) = :year";
$dateConditionRoast = "YEAR(order_date) = :year";
$dateConditionBri   = "YEAR(transaction_date) = :year";
$dateConditionAct   = "YEAR(start_date) = :year";
$dateConditionCash  = "YEAR(trx_date) = :year";

$sqlParams = [':year' => $yearFilter];

if ($monthFilter !== '') {
    $dateConditionSales .= " AND MONTH(sale_date) = :month";
    $dateConditionRoast .= " AND MONTH(order_date) = :month";
    $dateConditionBri   .= " AND MONTH(transaction_date) = :month";
    $dateConditionAct   .= " AND MONTH(start_date) = :month";
    $dateConditionCash  .= " AND MONTH(trx_date) = :month";
    $sqlParams[':month'] = (int)$monthFilter;
}

// Data queries
$stmtTrading = $db->prepare("SELECT COALESCE(SUM(total_weight_kg),0) AS total_kg, COALESCE(SUM(grand_total),0) AS total_revenue, COALESCE(SUM(capital_cost),0) AS total_capital, COALESCE(SUM(langsir_fee),0) AS total_langsir, COALESCE(SUM(worker_fee),0) AS total_worker, COALESCE(SUM(operational_cost),0) AS total_op, COALESCE(SUM(profit_amount),0) AS net_profit FROM sales WHERE {$dateConditionSales}");
$stmtTrading->execute($sqlParams);
$trading = $stmtTrading->fetch();

$stmtRoast = $db->prepare("SELECT COALESCE(SUM(raw_weight_kg),0) AS total_raw, COALESCE(SUM(total_fee),0) AS total_fee FROM roasting_orders WHERE {$dateConditionRoast}");
$stmtRoast->execute($sqlParams);
$roasting = $stmtRoast->fetch();

$stmtBri = $db->prepare("SELECT COALESCE(SUM(company_income),0) AS net_income FROM brilink_transactions WHERE {$dateConditionBri} AND status = 'BERHASIL'");
$stmtBri->execute($sqlParams);
$brilink = $stmtBri->fetch();

$stmtDekor = $db->prepare("SELECT COALESCE(SUM(total_income),0) AS total_income, COALESCE(SUM(total_cost),0) AS total_cost FROM division_activities WHERE division_id = 5 AND {$dateConditionAct} AND status = 'SELESAI'");
$stmtDekor->execute($sqlParams);
$dekor = $stmtDekor->fetch();
$dekorProfit = $dekor['total_income'] - $dekor['total_cost'];

$stmtSound = $db->prepare("SELECT COALESCE(SUM(total_income),0) AS total_income, COALESCE(SUM(total_cost),0) AS total_cost FROM division_activities WHERE division_id = 6 AND {$dateConditionAct} AND status = 'SELESAI'");
$stmtSound->execute($sqlParams);
$sound = $stmtSound->fetch();
$soundProfit = $sound['total_income'] - $sound['total_cost'];

$stmtGeneralExp = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total_gen_exp FROM cash_transactions WHERE trx_direction = 'OUT' AND business_line = 'MANAGEMENT' AND category != 'Mutasi Antar-Kas' AND {$dateConditionCash}");
$stmtGeneralExp->execute($sqlParams);
$generalExpense = (float)$stmtGeneralExp->fetchColumn();

$totalConsolidatedProfit = (
    $trading['net_profit'] +
    $roasting['total_fee'] +
    $brilink['net_income'] +
    $dekorProfit +
    $soundProfit -
    $generalExpense
);

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
    <title>Laporan Laba Rugi Konsolidasi - <?= $yearFilter ?></title>
    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1e293b;
        }
        .page-sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 4px;
            position: relative;
        }
        .kop-surat {
            display: flex;
            align-items: center;
            border-bottom: 3px double #0d1e3a;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .kop-logo {
            width: 80px;
            height: auto;
            margin-right: 18px;
        }
        .kop-text {
            flex: 1;
            text-align: center;
        }
        .kop-company {
            font-size: 18pt;
            font-weight: 800;
            margin: 0;
            color: #0d1e3a;
            letter-spacing: 0.5px;
        }
        .kop-sub {
            font-size: 9.5pt;
            color: #475569;
            margin: 2px 0 0 0;
        }
        .doc-badge {
            display: inline-block;
            background: #0d1e3a;
            color: #ffffff;
            padding: 4px 14px;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 1px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .table-data {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table-data th, .table-data td {
            border: 1px solid #cbd5e1;
            padding: 7px 12px;
            font-size: 9.5pt;
        }
        .table-data th {
            background-color: #f8fafc;
            color: #0d1e3a;
            font-weight: 700;
        }
        .ttd-box {
            margin-top: 40px;
        }
        .ttd-col {
            text-align: center;
            font-size: 9.5pt;
        }
        .ttd-space {
            height: 65px;
        }
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            padding: 12px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        @media print {
            body {
                background: #ffffff;
                margin: 0;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .page-sheet {
                box-shadow: none;
                margin: 0;
                width: 100%;
                min-height: auto;
                padding: 10mm;
            }
        }
    </style>
</head>
<body>

<div class="action-bar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= base_url('modules/reports/index.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Laporan
        </a>
        <span class="text-muted">|</span>
        <span class="fw-semibold text-dark">Laporan Konsolidasi Laba Rugi <?= $yearFilter ?></span>
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
            <p class="kop-sub mb-0 fw-semibold text-primary">LAPORAN MANAJEMEN & KEUANGAN KONSOLIDASI</p>
            <p class="kop-sub"><?= e($companyAddress) ?> &bull; Telp: <?= e($companyPhone) ?></p>
        </div>
    </div>

    <div class="text-center my-3">
        <span class="doc-badge">LAPORAN LABA RUGI KONSOLIDASI PERUSAHAAN</span>
        <div class="mt-2 fw-bold text-dark">
            Periode: <?= $monthFilter ? date('F', mktime(0,0,0,(int)$monthFilter, 10)) . ' ' : 'Tahun ' ?><?= $yearFilter ?>
        </div>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th>DESKRIPSI LINI USAHA / POS KEUANGAN</th>
                <th style="width: 140px;" class="text-end">PENDAPATAN</th>
                <th style="width: 140px;" class="text-end">BIAYA / BEBAN</th>
                <th style="width: 160px;" class="text-end">LABA BERSIH</th>
            </tr>
        </thead>
        <tbody>
            <!-- 1. COFFEE TRADING -->
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" class="fw-bold text-primary">1. DIVISI COFFEE TRADING (PERDAGANGAN KOPI BIJI)</td>
            </tr>
            <tr>
                <td class="ps-4">Penjualan Biji Kopi Robusta (<?= number_format($trading['total_kg'], 0, ',', '.') ?> Kg)</td>
                <td class="text-end"><?= format_rupiah($trading['total_revenue']) ?></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="ps-4 text-muted">- Modal Kopi (HPP Petani & Supplier)</td>
                <td></td>
                <td class="text-end text-danger"><?= format_rupiah($trading['total_capital']) ?></td>
                <td></td>
            </tr>
            <tr>
                <td class="ps-4 text-muted">- Biaya Langsir, Upah Pekerja & Armada</td>
                <td></td>
                <td class="text-end text-danger"><?= format_rupiah($trading['total_langsir'] + $trading['total_worker'] + $trading['total_op']) ?></td>
                <td></td>
            </tr>
            <tr class="fw-bold">
                <td class="ps-4">Subtotal Laba Bersih Coffee Trading</td>
                <td></td>
                <td></td>
                <td class="text-end text-success"><?= format_rupiah($trading['net_profit']) ?></td>
            </tr>

            <!-- 2. ROASTING SERVICE -->
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" class="fw-bold text-primary">2. DIVISI COFFEE ROASTING SERVICE (JASA MAKLON KOPI)</td>
            </tr>
            <tr>
                <td class="ps-4">Pendapatan Jasa Roasting Kopi Pelanggan</td>
                <td class="text-end"><?= format_rupiah($roasting['total_fee']) ?></td>
                <td class="text-end text-muted">-</td>
                <td class="text-end fw-bold text-success"><?= format_rupiah($roasting['total_fee']) ?></td>
            </tr>

            <!-- 3. AGEN BRILINK -->
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" class="fw-bold text-primary">3. DIVISI AGEN BRILINK (LAYANAN KEUANGAN MIKRO)</td>
            </tr>
            <tr>
                <td class="ps-4">Pendapatan Bersih Margin Fee Jasa BRILink</td>
                <td class="text-end"><?= format_rupiah($brilink['net_income']) ?></td>
                <td class="text-end text-muted">-</td>
                <td class="text-end fw-bold text-success"><?= format_rupiah($brilink['net_income']) ?></td>
            </tr>

            <!-- 4. DEKORATION -->
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" class="fw-bold text-primary">4. DIVISI DEKORATION (PJ: FATKHUR ROHMAN)</td>
            </tr>
            <tr>
                <td class="ps-4">Pendapatan Jasa Sewa Dekorasi Acara</td>
                <td class="text-end"><?= format_rupiah($dekor['total_income']) ?></td>
                <td class="text-end text-danger"><?= format_rupiah($dekor['total_cost']) ?></td>
                <td class="text-end fw-bold text-success"><?= format_rupiah($dekorProfit) ?></td>
            </tr>

            <!-- 5. SOUND SISTEM -->
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" class="fw-bold text-primary">5. DIVISI SOUND SISTEM (PJ: MISBAHUL MUNIR)</td>
            </tr>
            <tr>
                <td class="ps-4">Pendapatan Jasa Sewa Tata Suara / Audio</td>
                <td class="text-end"><?= format_rupiah($sound['total_income']) ?></td>
                <td class="text-end text-danger"><?= format_rupiah($sound['total_cost']) ?></td>
                <td class="text-end fw-bold text-success"><?= format_rupiah($soundProfit) ?></td>
            </tr>

            <!-- BEBAN UMUM -->
            <tr style="background-color: #f1f5f9;">
                <td colspan="4" class="fw-bold text-danger">6. BEBAN OPERASIONAL KANTOR PUSAT & ADMINISTRASI</td>
            </tr>
            <tr>
                <td class="ps-4 text-muted">Beban Operasional Umum (Listrik, Internet, ATK, Rumah Tangga Kantor)</td>
                <td></td>
                <td class="text-end text-danger"><?= format_rupiah($generalExpense) ?></td>
                <td class="text-end fw-bold text-danger">- <?= format_rupiah($generalExpense) ?></td>
            </tr>

            <!-- GRAND TOTAL KONSOLIDASI -->
            <tr style="background-color: #0d1e3a; color: #ffffff;" class="fw-bold">
                <td colspan="3" class="fs-6">KONSOLIDASI LABA BERSIH PERUSAHAAN (NET PROFIT)</td>
                <td class="text-end fs-6 text-white"><?= format_rupiah($totalConsolidatedProfit) ?></td>
            </tr>
        </tbody>
    </table>

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
        <span>MBB BUSINESS MANAGEMENT SYSTEM &bull; Laporan Laba Rugi Konsolidasi Resmi</span>
        <span>Dicetak pada: <?= date('d/m/Y H:i:s') ?></span>
    </div>
</div>

</body>
</html>
