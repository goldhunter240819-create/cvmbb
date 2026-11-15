<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Cetak Dokumen Struktur Organisasi Resmi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('organization.view');

$db = Database::getInstance();

$companyName    = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$companyShort   = get_company_setting('company_short_name', 'MBB');
$companyAddress = get_company_setting('company_address', 'Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat');
$companyPhone   = get_company_setting('company_phone', '0812-7890-1234');
$companyEmail   = get_company_setting('company_email', 'info@cvmbb.co.id');

$officials = $db->query("
    SELECT e.*, p.position_name, p.position_code, d.division_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    WHERE e.is_active = 1
    ORDER BY p.level_rank ASC, e.id ASC
")->fetchAll();

$byCode = [];
foreach ($officials as $off) {
    if ($off['position_code']) {
        $byCode[$off['position_code']] = $off;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Struktur Organisasi - <?= e($companyName) ?></title>
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
            padding: 20mm;
            margin: 20px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .kop-surat {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000;
            padding-bottom: 12px;
            margin-bottom: 24px;
        }
        .kop-logo {
            width: 85px;
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
            letter-spacing: 1px;
            color: #0d1e3a;
        }
        .kop-sub {
            font-size: 10pt;
            margin: 2px 0;
        }
        .doc-title {
            text-align: center;
            margin-bottom: 25px;
        }
        .doc-title h4 {
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .tree-box {
            border: 2px solid #0d1e3a;
            padding: 8px 12px;
            text-align: center;
            border-radius: 6px;
            background: #f8fafc;
            min-width: 170px;
        }
        .tree-box .title {
            font-weight: bold;
            font-size: 10pt;
            color: #0d1e3a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
            margin-bottom: 2px;
        }
        .tree-box .name {
            font-size: 10pt;
            font-weight: bold;
        }
        .line-v {
            width: 2px;
            height: 20px;
            background: #000;
            margin: 0 auto;
        }
        .table-print {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 20px;
        }
        .table-print th, .table-print td {
            border: 1px solid #000;
            padding: 6px 10px;
        }
        .table-print th {
            background-color: #f1f5f9;
            text-align: center;
        }
        .signature-block {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .sig-box {
            width: 200px;
            text-align: center;
            font-size: 11pt;
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
            <i class="bi bi-printer me-1"></i> Cetak Dokumen / Simpan PDF
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

    <!-- JUDUL DOKUMEN -->
    <div class="doc-title">
        <h4>BAGAN STRUKTUR ORGANISASI PERUSAHAAN</h4>
        <div style="font-size: 10pt;">Nomor: 001/SK-ORG/MBB/<?= date('Y') ?></div>
    </div>

    <!-- BAGAN RESMI -->
    <div class="d-flex flex-column align-items-center mb-4">
        <!-- KOMISARIS -->
        <div class="tree-box" style="width: 240px;">
            <div class="title">KOMISARIS</div>
            <div class="name"><?= e($byCode['POS-KMS']['full_name'] ?? 'KH. MUHAMMAD THOHA') ?></div>
        </div>

        <div class="line-v"></div>

        <!-- DIREKSI -->
        <div class="tree-box" style="width: 240px;">
            <div class="title">DIREKSI</div>
            <div class="name"><?= e($byCode['POS-DIRK']['full_name'] ?? 'NUR ISLAH') ?></div>
        </div>

        <div class="line-v"></div>

        <!-- CABANG 3 JALUR: ADM, DIREKTUR, OPERASIONAL -->
        <div style="width: 100%;">
            <div style="border-top: 2px solid #000; width: 85%; margin: 0 auto;"></div>
            <div class="d-flex justify-content-between pt-2">
                
                <!-- DIVISI ADMINISTRASI -->
                <div class="d-flex flex-column align-items-center" style="width: 30%;">
                    <div class="line-v" style="height: 12px; margin-top: -8px;"></div>
                    <div class="tree-box w-100">
                        <div class="title">DIVISI ADMINISTRASI</div>
                        <div class="name"><?= e($byCode['POS-ADMH']['full_name'] ?? 'NURIL HUDA') ?></div>
                        <small style="font-size: 8pt;">Penanggung Jawab</small>
                    </div>
                </div>

                <!-- JALUR DIREKTUR & MANAGER -->
                <div class="d-flex flex-column align-items-center" style="width: 36%;">
                    <div class="line-v" style="height: 12px; margin-top: -8px;"></div>
                    <div class="tree-box w-100">
                        <div class="title">DIREKTUR</div>
                        <div class="name"><?= e($byCode['POS-DIR']['full_name'] ?? 'M. ROZIKIN') ?></div>
                    </div>

                    <div class="line-v"></div>

                    <div class="tree-box w-100">
                        <div class="title">MANAGER</div>
                        <div class="name"><?= e($byCode['POS-MGR']['full_name'] ?? 'M. ILHAM SIFA') ?></div>
                    </div>

                    <div class="line-v"></div>

                    <!-- DIVISI BAWAH MANAGER -->
                    <div style="width: 100%;">
                        <div style="border-top: 2px solid #000; width: 75%; margin: 0 auto;"></div>
                        <div class="d-flex justify-content-between pt-2">
                            <div class="d-flex flex-column align-items-center" style="width: 48%;">
                                <div class="line-v" style="height: 10px; margin-top: -8px;"></div>
                                <div class="tree-box w-100 p-1">
                                    <div class="title" style="font-size: 8pt;">DIVISI DEKORATION</div>
                                    <div class="name" style="font-size: 9pt;"><?= e($byCode['POS-DEKH']['full_name'] ?? 'FATKHUR ROHMAN') ?></div>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-center" style="width: 48%;">
                                <div class="line-v" style="height: 10px; margin-top: -8px;"></div>
                                <div class="tree-box w-100 p-1">
                                    <div class="title" style="font-size: 8pt;">DIVISI SOUND SISTEM</div>
                                    <div class="name" style="font-size: 9pt;"><?= e($byCode['POS-SNDH']['full_name'] ?? 'MISBAHUL MUNIR') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DIVISI OPERASIONAL -->
                <div class="d-flex flex-column align-items-center" style="width: 30%;">
                    <div class="line-v" style="height: 12px; margin-top: -8px;"></div>
                    <div class="tree-box w-100">
                        <div class="title">DIVISI OPERASIONAL</div>
                        <div class="name"><?= e($byCode['POS-OPSH']['full_name'] ?? 'AHMAD SOBIRIN') ?></div>
                        <small style="font-size: 8pt;">Penanggung Jawab</small>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- TABEL RINCIAN TANGGUNG JAWAB -->
    <table class="table-print">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Nama Lengkap</th>
                <th style="width: 25%;">Jabatan Struktural</th>
                <th style="width: 25%;">Unit / Divisi</th>
                <th style="width: 20%;">Status Keanggotaan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($officials as $idx => $row): ?>
                <tr>
                    <td class="text-center"><?= $idx + 1 ?></td>
                    <td class="fw-bold"><?= e($row['full_name']) ?></td>
                    <td><?= e($row['position_name']) ?></td>
                    <td><?= e($row['division_name'] ?: 'Manajemen Pusat') ?></td>
                    <td class="text-center"><?= e($row['employment_status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TANDA TANGAN PENGESAHAN -->
    <div class="signature-block">
        <div class="sig-box">
            <div>Mengetahui,</div>
            <div class="fw-bold">KOMISARIS PERUSAHAAN</div>
            <div style="height: 70px;"></div>
            <div class="fw-bold text-decoration-underline"><?= e($byCode['POS-KMS']['full_name'] ?? 'KH. MUHAMMAD THOHA') ?></div>
        </div>

        <div class="sig-box">
            <div>Air Hitam, <?= format_tanggal(date('Y-m-d')) ?></div>
            <div>Ditetapkan oleh,</div>
            <div class="fw-bold">DIREKTUR PERUSAHAAN</div>
            <div style="height: 70px;"></div>
            <div class="fw-bold text-decoration-underline"><?= e($byCode['POS-DIR']['full_name'] ?? 'M. ROZIKIN') ?></div>
        </div>
    </div>
</div>

</body>
</html>
