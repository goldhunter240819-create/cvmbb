<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Bukti Penerimaan & Nota Jasa Roasting Kopi (Maklon)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('roasting_service.view');

$db = Database::getInstance();
$orderId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address
    FROM roasting_orders r
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE r.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Pesanan jasa roasting tidak ditemukan.");
}

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
    <title>Nota Jasa Roasting - <?= e($order['order_number']) ?></title>
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
            padding: 8px 12px;
            font-size: 10pt;
        }
        .table-data th {
            background-color: #f8fafc;
            color: #0d1e3a;
            font-weight: 700;
            text-align: center;
        }
        .ttd-box {
            margin-top: 40px;
        }
        .ttd-col {
            text-align: center;
            font-size: 10pt;
        }
        .ttd-space {
            height: 70px;
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
        <a href="<?= base_url('modules/roasting-service/index.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Roasting Service
        </a>
        <span class="text-muted">|</span>
        <span class="fw-semibold text-dark">Nota & SPK Roasting: <?= e($order['order_number']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<div class="page-sheet">
    <!-- KOP RESMI MBB -->
    <div class="kop-surat">
        <img src="<?= asset_url('images/logo.png') ?>" alt="Logo MBB" class="kop-logo">
        <div class="kop-text">
            <h1 class="kop-company"><?= e($companyName) ?></h1>
            <p class="kop-sub mb-0 fw-semibold text-primary">UNIT JASA ROASTING & PENGOLAHAN KOPI (MAKLON)</p>
            <p class="kop-sub"><?= e($companyAddress) ?></p>
            <p class="kop-sub">Telp: <?= e($companyPhone) ?> &bull; Email: <?= e($companyEmail) ?></p>
        </div>
    </div>

    <!-- JUDUL -->
    <div class="text-center my-3">
        <span class="doc-badge">SURAT PERINTAH KERJA & NOTA JASA ROASTING</span>
        <div class="mt-2 fw-bold text-dark" style="font-size: 11pt;">Nomor: <?= e($order['order_number']) ?></div>
    </div>

    <!-- INFO PELANGGAN & ORDER -->
    <div class="row mt-4 mb-3" style="font-size: 10pt;">
        <div class="col-7">
            <table class="table-borderless w-100">
                <tr>
                    <td style="width: 140px;" class="fw-semibold text-muted">Tanggal Masuk</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold text-dark"><?= format_date_indo($order['order_date']) ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Profil Roasting</td>
                    <td>:</td>
                    <td class="fw-bold text-primary"><?= e($order['roast_profile']) ?> ROAST</td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Status Pekerjaan</td>
                    <td>:</td>
                    <td><span class="badge bg-secondary"><?= e($order['work_status']) ?></span></td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Status Bayar</td>
                    <td>:</td>
                    <td>
                        <span class="badge <?= $order['payment_status'] === 'LUNAS' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                            <?= e($order['payment_status']) ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <div class="col-5">
            <div class="p-3 border rounded bg-light">
                <div class="fw-bold text-primary mb-1"><i class="bi bi-person-circle"></i> DATA PELANGGAN:</div>
                <div class="fw-bold text-dark" style="font-size: 11pt;"><?= e($order['customer_name'] ?? 'Pelanggan Umum') ?></div>
                <div class="text-muted small mt-1">
                    Telp / WA: <?= e($order['customer_phone'] ?? '-') ?><br>
                    <?= e($order['customer_address'] ?? 'Air Hitam, Lampung Barat') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL RINCIAN -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 40px;">NO</th>
                <th>RINCIAN LAYANAN ROASTING</th>
                <th style="width: 120px;">BERAT MENTAH</th>
                <th style="width: 140px;">TARIF / KG</th>
                <th style="width: 160px;">TOTAL TAGIHAN</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>
                    <strong>Jasa Roasting Kopi Profil <?= e($order['roast_profile']) ?></strong>
                    <div class="text-muted small">Mesin Roasting Standar Industri MBB &bull; Cooling & Resting Termasuk</div>
                    <?php if (!empty($order['notes'])): ?>
                        <div class="text-primary small mt-1"><i class="bi bi-info-circle"></i> Catatan Pelanggan: <?= e($order['notes']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-end fw-bold">
                    <?= number_format($order['raw_weight_kg'], 1, ',', '.') ?> Kg
                </td>
                <td class="text-end">
                    <?= format_rupiah($order['service_fee_per_kg']) ?>
                </td>
                <td class="text-end fw-bold text-dark">
                    <?= format_rupiah($order['total_fee']) ?>
                </td>
            </tr>
            <!-- HASIL TIMBANG JADI -->
            <tr>
                <td colspan="2" class="text-end fw-semibold">Hasil Timbang Kopi Matang (Roasted Weight)</td>
                <td class="text-end fw-bold text-success">
                    <?= $order['roasted_weight_kg'] > 0 ? number_format($order['roasted_weight_kg'], 1, ',', '.') . ' Kg' : '-' ?>
                </td>
                <td colspan="2" class="text-muted small">
                    <?= $order['shrinkage_percent'] > 0 ? 'Susut Berat (Shrinkage): ' . number_format($order['shrinkage_percent'], 1) . '%' : 'Menunggu proses roasting' ?>
                </td>
            </tr>
            <!-- TOTAL & PEMBAYARAN -->
            <tr style="background-color: #f8fafc;">
                <td colspan="4" class="text-end fw-bold">TOTAL BIAYA JASA</td>
                <td class="text-end fw-bold text-primary" style="font-size: 11pt;"><?= format_rupiah($order['total_fee']) ?></td>
            </tr>
            <tr>
                <td colspan="4" class="text-end fw-semibold">Pembayaran Diterima (DP / Cash)</td>
                <td class="text-end fw-bold text-success"><?= format_rupiah($order['paid_amount']) ?></td>
            </tr>
            <?php $sisa = max(0, $order['total_fee'] - $order['paid_amount']); ?>
            <tr style="background-color: #fff1f2;">
                <td colspan="4" class="text-end fw-bold text-danger">SISA TAGIHAN</td>
                <td class="text-end fw-bold text-danger"><?= format_rupiah($sisa) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- KETENTUAN PENGERJAAN -->
    <div class="mt-3 p-2 bg-light border rounded" style="font-size: 9pt;">
        <div><strong>Ketentuan Jasa Roasting:</strong></div>
        <ol class="mb-0 ps-3">
            <li>Penyusutan berat alami pada proses sangrai kopi berkisar antara 14% - 20% tergantung kadar air awal dan tingkat sangrai.</li>
            <li>Hasil roasting wajib diambil maksimal 7 hari setelah pemberitahuan status "SIAP DIAMBIL".</li>
            <li>Harap menunjukkan nota ini pada saat serah terima pengambilan hasil roasting.</li>
        </ol>
    </div>

    <!-- TANDA TANGAN -->
    <div class="ttd-box">
        <div class="row text-center">
            <div class="col-6 ttd-col">
                <div class="text-muted">Pelanggan / Pemilik Kopi,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline"><?= e($order['customer_name'] ?? 'Pelanggan') ?></div>
            </div>
            <div class="col-6 ttd-col">
                <div class="text-muted">Petugas Roasting CV. MBB,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">AHMAD SOBIRIN / ROASTER</div>
                <div class="small text-muted">Divisi Operasional & Roasting</div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <div style="position: absolute; bottom: 15mm; left: 20mm; right: 20mm; font-size: 8pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 6px;" class="d-flex justify-content-between">
        <span>MBB BUSINESS MANAGEMENT SYSTEM &bull; Layanan Jasa Roasting CV. MBB</span>
        <span>Dicetak pada: <?= date('d/m/Y H:i:s') ?></span>
    </div>
</div>

</body>
</html>
