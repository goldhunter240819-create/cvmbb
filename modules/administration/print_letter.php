<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Dokumen Surat Resmi Berkop MBB
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('administration.view');

$db = Database::getInstance();
$letterId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM outgoing_letters WHERE id = ?");
$stmt->execute([$letterId]);
$letter = $stmt->fetch();

if (!$letter) {
    die("Surat keluar tidak ditemukan.");
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
    <title>Surat Resmi - <?= e($letter['letter_number']) ?></title>
    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Times New Roman', Times, serif;
            color: #000;
        }
        .page-sheet {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            padding: 25mm 20mm;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .kop-surat {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 25px;
        }
        .kop-logo {
            width: 90px;
            height: auto;
            margin-right: 20px;
        }
        .kop-text {
            flex: 1;
            text-align: center;
        }
        .kop-company {
            font-size: 20pt;
            font-weight: bold;
            margin: 0;
            color: #0d1e3a;
            letter-spacing: 1px;
        }
        .kop-sub {
            font-size: 10pt;
            margin: 2px 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
            font-size: 11pt;
        }
        .meta-table td {
            vertical-align: top;
            padding: 2px 0;
        }
        .letter-content {
            font-size: 11pt;
            line-height: 1.6;
            text-align: justify;
            min-height: 380px;
        }
        .signature-section {
            margin-top: 40px;
            float: right;
            width: 250px;
            text-align: center;
            font-size: 11pt;
        }
        .sig-space {
            height: 80px;
        }
        @media print {
            body {
                background: #ffffff;
            }
            .page-sheet {
                box-shadow: none;
                margin: 0;
                padding: 15mm;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="no-print text-center py-3 bg-dark text-white shadow-sm">
    <div class="d-inline-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm px-4">
            <i class="bi bi-printer me-1"></i> Cetak Surat / Simpan PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-light btn-sm px-3">
            Tutup
        </button>
    </div>
</div>

<div class="page-sheet">
    <!-- KOP SURAT RESMI MBB -->
    <div class="kop-surat">
        <img src="<?= asset_url('images/logo.png') ?>" class="kop-logo" alt="Logo MBB">
        <div class="kop-text">
            <h1 class="kop-company"><?= e($companyName) ?></h1>
            <div class="kop-sub fw-bold">PERDAGANGAN BIJI KOPI &bull; ROASTERY &bull; AGEN BRILINK &bull; SOUND & DEKORASI</div>
            <div class="kop-sub"><?= e($companyAddress) ?></div>
            <div class="kop-sub">Telp: <?= e($companyPhone) ?> | Email: <?= e($companyEmail) ?></div>
        </div>
    </div>

    <!-- TANGGAL SURAT -->
    <div class="text-end mb-3" style="font-size: 11pt;">
        Air Hitam, <?= format_tanggal($letter['letter_date']) ?>
    </div>

    <!-- INFORMASI SURAT -->
    <table class="meta-table">
        <tr>
            <td style="width: 15%;">Nomor</td>
            <td style="width: 2%;">:</td>
            <td style="width: 53%;" class="fw-bold"><?= e($letter['letter_number']) ?></td>
            <td style="width: 30%;">Kepada Yth.</td>
        </tr>
        <tr>
            <td>Lampiran</td>
            <td>:</td>
            <td>- (Satu Berkas)</td>
            <td class="fw-bold"><?= e($letter['recipient']) ?></td>
        </tr>
        <tr>
            <td>Perihal</td>
            <td>:</td>
            <td class="fw-bold text-decoration-underline"><?= e($letter['subject']) ?></td>
            <td>Di Tempat</td>
        </tr>
    </table>

    <!-- ISI SURAT RESMI -->
    <div class="letter-content mt-4">
        <p>Dengan hormat,</p>
        <p>
            Sehubungan dengan aktivitas operasional dan hubungan kemitraan usaha bersama <strong><?= e($companyName) ?></strong>, melalui surat ini kami menyampaikan hal-hal terkait <strong><?= e($letter['subject']) ?></strong>.
        </p>
        <p>
            Demikian surat ini kami sampaikan agar dapat dipergunakan sebagaimana mestinya. Atas perhatian, kepercayaan, dan kerja sama yang terjalin dengan baik selama ini, kami ucapkan terima kasih.
        </p>
    </div>

    <!-- TANDA TANGAN RESMI -->
    <div class="signature-section">
        <div>Hormat kami,</div>
        <div class="fw-bold"><?= e($companyName) ?></div>
        <div class="sig-space"></div>
        <div class="fw-bold text-decoration-underline"><?= e($letter['signer']) ?></div>
    </div>

    <div style="clear: both;"></div>
</div>

</body>
</html>
