<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Struk Transaksi Agen BRILink
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('brilink.view');

$db = Database::getInstance();
$trxId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT t.*, a.account_name, a.device_edc_id, a.account_number, u.full_name AS operator_name
    FROM brilink_transactions t
    LEFT JOIN brilink_accounts a ON t.brilink_account_id = a.id
    LEFT JOIN users u ON t.operator_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$trxId]);
$trx = $stmt->fetch();

if (!$trx) {
    die("Transaksi BRILink tidak ditemukan.");
}

$companyName    = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$companyAddress = get_company_setting('company_address', 'Air Hitam, Lampung Barat');
$companyPhone   = get_company_setting('company_phone', '0812-7890-1234');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Transaksi BRILink - <?= e($trx['reference_no']) ?></title>
    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, monospace;
            color: #000;
        }
        .receipt-card {
            background: #ffffff;
            width: 80mm;
            padding: 15px;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px dashed #cbd5e1;
        }
        .dashed-line {
            border-bottom: 1px dashed #000;
            margin: 8px 0;
        }
        .receipt-header {
            text-align: center;
        }
        .receipt-header h5 {
            font-weight: bold;
            margin: 0;
            font-size: 13pt;
        }
        .receipt-table {
            width: 100%;
            font-size: 9pt;
        }
        .receipt-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .action-bar {
            text-align: center;
            margin-bottom: 10px;
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
            .receipt-card {
                box-shadow: none;
                margin: 0;
                border: none;
                width: 100%;
                padding: 5mm;
            }
        }
    </style>
</head>
<body>

<div class="action-bar pt-3">
    <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak Struk (Thermal / Kertas)
    </button>
    <a href="<?= base_url('modules/brilink/index.php') ?>" class="btn btn-sm btn-outline-secondary">
        Kembali
    </a>
</div>

<div class="receipt-card">
    <div class="receipt-header">
        <div class="fw-bold" style="font-size: 12pt;">AGEN BRILink RESMI</div>
        <h5><?= e($companyName) ?></h5>
        <div style="font-size: 8pt;"><?= e($companyAddress) ?></div>
        <div style="font-size: 8pt;">TELP: <?= e($companyPhone) ?></div>
    </div>

    <div class="dashed-line"></div>

    <table class="receipt-table">
        <tr>
            <td>TANGGAL</td>
            <td>:</td>
            <td><?= date('d/m/Y H:i', strtotime($trx['transaction_date'])) ?> WIB</td>
        </tr>
        <tr>
            <td>NO. REFF</td>
            <td>:</td>
            <td class="fw-bold"><?= e($trx['reference_no']) ?></td>
        </tr>
        <tr>
            <td>TERMINAL</td>
            <td>:</td>
            <td><?= e($trx['device_edc_id']) ?></td>
        </tr>
        <tr>
            <td>OPERATOR</td>
            <td>:</td>
            <td><?= e($trx['operator_name'] ?? 'Admin MBB') ?></td>
        </tr>
        <tr>
            <td>LAYANAN</td>
            <td>:</td>
            <td class="fw-bold"><?= e($trx['trx_type']) ?></td>
        </tr>
        <tr>
            <td>NASABAH</td>
            <td>:</td>
            <td><?= e($trx['customer_name'] ?? 'Umum') ?></td>
        </tr>
    </table>

    <div class="dashed-line"></div>

    <table class="receipt-table">
        <tr>
            <td>NOMINAL</td>
            <td class="text-end fw-bold"><?= format_rupiah($trx['nominal_amount']) ?></td>
        </tr>
        <tr>
            <td>BIAYA ADMIN</td>
            <td class="text-end"><?= format_rupiah($trx['admin_fee_charged']) ?></td>
        </tr>
        <tr class="fw-bold">
            <td>TOTAL BAYAR</td>
            <td class="text-end"><?= format_rupiah($trx['nominal_amount'] + $trx['admin_fee_charged']) ?></td>
        </tr>
    </table>

    <div class="dashed-line"></div>

    <table class="receipt-table">
        <tr>
            <td>STATUS</td>
            <td class="text-end fw-bold text-success"><?= e($trx['status']) ?></td>
        </tr>
        <?php if (!empty($trx['notes'])): ?>
            <tr>
                <td colspan="2" style="font-size: 8pt;">Ket: <?= e($trx['notes']) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="dashed-line"></div>

    <div class="text-center" style="font-size: 8pt; margin-top: 8px;">
        *** TERIMA KASIH ***<br>
        Simpan struk ini sebagai bukti transaksi yang sah.<br>
        CV. MAJU BERKAH BERSAMA
    </div>
</div>

</body>
</html>
