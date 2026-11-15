<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Laporan Inventaris Aset Perusahaan
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('inventory.view');

$db = Database::getInstance();

$assets = $db->query("
    SELECT a.*, d.division_name
    FROM inventory_assets a
    LEFT JOIN divisions d ON a.division_id = d.id
    ORDER BY a.division_id ASC, a.asset_name ASC
")->fetchAll();

$kpi = $db->query("
    SELECT COUNT(*) AS total_items, COALESCE(SUM(acquisition_cost), 0) AS total_val
    FROM inventory_assets
")->fetch();

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
    <title>Daftar Inventaris Aset - <?= e($companyName) ?></title>
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
            width: 297mm;
            min-height: 210mm;
            padding: 15mm;
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
            width: 75px;
            height: auto;
            margin-right: 18px;
        }
        .kop-text {
            flex: 1;
            text-align: center;
        }
        .kop-company {
            font-size: 17pt;
            font-weight: 800;
            margin: 0;
            color: #0d1e3a;
            letter-spacing: 0.5px;
        }
        .kop-sub {
            font-size: 9pt;
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
            padding: 6px 10px;
            font-size: 9pt;
        }
        .table-data th {
            background-color: #f8fafc;
            color: #0d1e3a;
            font-weight: 700;
            text-align: center;
        }
        .ttd-box {
            margin-top: 30px;
        }
        .ttd-col {
            text-align: center;
            font-size: 9.5pt;
        }
        .ttd-space {
            height: 60px;
        }
        .action-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            padding: 10px 20px;
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
        <a href="<?= base_url('modules/inventory/index.php') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali ke Inventaris
        </a>
        <span class="text-muted">|</span>
        <span class="fw-semibold text-dark">Laporan Daftar Inventaris & Aset Perusahaan</span>
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
            <p class="kop-sub mb-0 fw-semibold text-primary">BAGIAN ADMINISTRASI UMUM & PENGELOLAAN ASET</p>
            <p class="kop-sub"><?= e($companyAddress) ?> &bull; Telp: <?= e($companyPhone) ?></p>
        </div>
    </div>

    <div class="text-center my-3">
        <span class="doc-badge">DAFTAR INVENTARIS ASET PERUSAHAAN</span>
        <div class="mt-1 small text-muted">Per Tanggal: <?= date('d/m/Y') ?></div>
    </div>

    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 30px;">NO</th>
                <th style="width: 110px;">KODE ASET</th>
                <th>NAMA BARANG / ASET</th>
                <th style="width: 130px;">KATEGORI</th>
                <th style="width: 160px;">DIVISI</th>
                <th style="width: 130px;">HARGA PEROLEHAN</th>
                <th style="width: 90px;">KONDISI</th>
                <th style="width: 150px;">LOKASI</th>
                <th style="width: 140px;">PENANGGUNG JAWAB</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assets)): ?>
                <tr>
                    <td colspan="9" class="text-center py-3 text-muted">Belum ada aset terdaftar.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($assets as $a): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="fw-bold font-monospace"><?= e($a['asset_code']) ?></td>
                        <td class="fw-semibold"><?= e($a['asset_name']) ?></td>
                        <td><?= e($a['category'] ?? '-') ?></td>
                        <td><?= e($a['division_name'] ?? 'Umum') ?></td>
                        <td class="text-end fw-bold"><?= format_rupiah($a['acquisition_cost']) ?></td>
                        <td class="text-center fw-semibold"><?= e($a['asset_condition']) ?></td>
                        <td><?= e($a['location'] ?? '-') ?></td>
                        <td><?= e($a['person_in_charge'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f8fafc;" class="fw-bold">
                    <td colspan="5" class="text-end">TOTAL NILAI ASET TERDATA:</td>
                    <td class="text-end text-primary"><?= format_rupiah($kpi['total_val']) ?></td>
                    <td colspan="3" class="text-muted small"><?= $kpi['total_items'] ?> Unit Barang</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="ttd-box">
        <div class="row text-center">
            <div class="col-4 ttd-col">
                <div class="text-muted">Pengelola Aset & Inventaris,</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">AHMAD SOBIRIN</div>
                <div class="small text-muted">Divisi Operasional MBB</div>
            </div>
            <div class="col-4 ttd-col">
                <div class="text-muted">Mengetahui (Administrasi),</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">NURIL HUDA</div>
                <div class="small text-muted">Divisi Administrasi MBB</div>
            </div>
            <div class="col-4 ttd-col">
                <div class="text-muted">Menyetujui (Direktur),</div>
                <div class="ttd-space"></div>
                <div class="fw-bold text-decoration-underline">M. ROZIKIN</div>
                <div class="small text-muted">Direktur CV. MBB</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
