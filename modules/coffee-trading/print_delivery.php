<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Surat Jalan & Nota Pengiriman Kopi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('coffee_trading.view');

$db = Database::getInstance();
$saleId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT s.*, c.name AS customer_name, c.phone AS customer_phone, c.address AS customer_address
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Data pengiriman kopi tidak ditemukan.");
}

$companyName    = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$companyShort   = get_company_setting('company_short_name', 'MBB');
$companyAddress = get_company_setting('company_address', 'Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat');
$companyPhone   = get_company_setting('company_phone', '0812-7890-1234');
$companyEmail   = get_company_setting('company_email', 'info@cvmbb.co.id');

$pricePerKg = $sale['total_weight_kg'] > 0 ? ($sale['grand_total'] / $sale['total_weight_kg']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan Pengiriman Kopi - <?= e($sale['invoice_number']) ?></title>
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

<!-- TOP ACTION BAR -->
<div class="action-bar d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
        <a href="<?= base_url('modules/coffee-trading/index.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Coffee Trading
        </a>
        <span class="text-muted">|</span>
        <span class="fw-semibold text-dark">Surat Jalan & Nota: <?= e($sale['invoice_number']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak / Simpan PDF
        </button>
    </div>
</div>

<div class="page-sheet">
    <!-- KOP SURAT RESMI MBB -->
    <div class="kop-surat">
        <img src="<?= asset_url('images/logo.png') ?>" alt="Logo MBB" class="kop-logo">
        <div class="kop-text">
            <h1 class="kop-company"><?= e($companyName) ?></h1>
            <p class="kop-sub mb-0 fw-semibold text-primary">DIVISI COFFEE TRADING & LOGISTIK PERKEBUNAN</p>
            <p class="kop-sub"><?= e($companyAddress) ?></p>
            <p class="kop-sub">Telp: <?= e($companyPhone) ?> &bull; Email: <?= e($companyEmail) ?></p>
        </div>
    </div>

    <!-- JUDUL DOKUMEN -->
    <div class="text-center my-3">
        <span class="doc-badge">SURAT JALAN & NOTA PENGIRIMAN KOPI</span>
        <div class="mt-2 fw-bold text-dark" style="font-size: 11pt;">Nomor: <?= e($sale['invoice_number']) ?></div>
    </div>

    <!-- INFO PENGIRIMAN & PENERIMA -->
    <div class="row mt-4 mb-3" style="font-size: 10pt;">
        <div class="col-7">
            <table class="table-borderless w-100">
                <tr>
                    <td style="width: 130px;" class="fw-semibold text-muted">Tanggal Kirim</td>
                    <td style="width: 15px;">:</td>
                    <td class="fw-bold text-dark"><?= format_date_indo($sale['sale_date']) ?></td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Divisi Pengirim</td>
                    <td>:</td>
                    <td>Coffee Trading MBB (Gudang Air Hitam)</td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Armada / Driver</td>
                    <td>:</td>
                    <td>Armada Ekspedisi CV. MBB</td>
                </tr>
                <tr>
                    <td class="fw-semibold text-muted">Status Transaksi</td>
                    <td>:</td>
                    <td><span class="badge bg-success-subtle text-success border border-success"><?= e($sale['payment_status']) ?></span></td>
                </tr>
            </table>
        </div>
        <div class="col-5">
            <div class="p-3 border rounded bg-light">
                <div class="fw-bold text-primary mb-1"><i class="bi bi-person-fill"></i> TUJUAN PENGIRIMAN:</div>
                <div class="fw-bold text-dark" style="font-size: 11pt;"><?= e($sale['customer_name'] ?? 'Pelanggan Umum') ?></div>
                <div class="text-muted small mt-1">
                    <?= e($sale['customer_address'] ?? 'Lampung / Tujuan Ekspedisi') ?><br>
                    Telp: <?= e($sale['customer_phone'] ?? '-') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL RINCIAN PENGIRIMAN KOPI -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 40px;">NO</th>
                <th>DESKRIPSI BARANG</th>
                <th style="width: 120px;">BERAT NETTO</th>
                <th style="width: 140px;">HARGA / KG</th>
                <th style="width: 160px;">TOTAL NILAI</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>
                    <strong>Biji Kopi Robusta Asalan / Kering Jemur</strong>
                    <div class="text-muted small">Kualitas Kopi Asalan Air Hitam Lampung Barat &bull; Pengiriman Resmi CV MBB</div>
                    <?php if (!empty($sale['notes'])): ?>
                        <div class="text-primary small mt-1"><i class="bi bi-info-circle"></i> Catatan: <?= e($sale['notes']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-end fw-bold">
                    <?= number_format($sale['total_weight_kg'], 0, ',', '.') ?> Kg
                </td>
                <td class="text-end">
                    <?= format_rupiah($pricePerKg) ?>
                </td>
                <td class="text-end fw-bold text-primary">
                    <?= format_rupiah($sale['grand_total']) ?>
                </td>
            </tr>
            <!-- TOTAL ROW -->
            <tr style="background-color: #f8fafc;">
                <td colspan="2" class="text-end fw-bold">TOTAL PENGIRIMAN</td>
                <td class="text-end fw-bold text-dark"><?= number_format($sale['total_weight_kg'], 0, ',', '.') ?> Kg</td>
                <td></td>
                <td class="text-end fw-bold text-primary" style="font-size: 11pt;"><?= format_rupiah($sale['grand_total']) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- TERBILANG & KETENTUAN -->
    <div class="mt-3 p-2 bg-light border rounded" style="font-size: 9.5pt;">
        <div><strong>Keterangan / Biaya Langsir & Pekerja:</strong> Telah dialokasikan dan diselesaikan via kas operasional logistik MBB.</div>
        <div class="text-muted small mt-1">Harap barang diperiksa dengan teliti pada saat serah terima. Surat jalan ini berlaku sebagai bukti sah penerimaan barang.</div>
    </div>

    <!-- TANDA TANGAN 3 PIHAK -->
    <div class="ttd-box">
        <div class="row text-center">
            <div class="col-4 ttd-col">
                <div class="text-muted">Penerima Barang / Pembeli,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline"><?= e($sale['customer_name'] ?? 'Penerima') ?></div>
                <div class="small text-muted">Cap / Tanda Tangan</div>
            </div>
            <div class="col-4 ttd-col">
                <div class="text-muted">Divisi Operasional & Logistik,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">AHMAD SOBIRIN</div>
                <div class="small text-muted">PJ Divisi Operasional MBB</div>
            </div>
            <div class="col-4 ttd-col">
                <div class="text-muted">Administrasi Perusahaan,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">NURIL HUDA</div>
                <div class="small text-muted">PJ Divisi Administrasi MBB</div>
            </div>
        </div>
    </div>

    <!-- FOOTER INFORMASI DOKUMEN -->
    <div style="position: absolute; bottom: 15mm; left: 20mm; right: 20mm; font-size: 8pt; color: #94a3b8; border-top: 1px dashed #cbd5e1; padding-top: 6px;" class="d-flex justify-content-between">
        <span>MBB BUSINESS MANAGEMENT SYSTEM &bull; Dokumen Resmi CV. MAJU BERKAH BERSAMA</span>
        <span>Dicetak pada: <?= date('d/m/Y H:i:s') ?></span>
    </div>
</div>

</body>
</html>
