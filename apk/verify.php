<?php
/**
 * MBB - Sistem Verifikasi & Validasi Struk Digital
 * CV. MAJU BERKAH BERSAMA
 * Halaman Publik (Dapat diakses oleh siapa saja saat scan QR Code struk)
 */

require_once __DIR__ . '/../includes/init.php';

$invoice = trim($_GET['inv'] ?? '');
$trx = null;
$trxType = '';

if (!empty($invoice)) {
    // 1. Cek di Tabel Purchases (Pembelian Kopi Petani)
    $stmt = $db->prepare("
        SELECT p.*, f.name AS partner_name, f.village AS partner_location, f.phone AS partner_phone, 'PURCHASE' as type
        FROM purchases p
        LEFT JOIN farmers f ON p.farmer_id = f.id
        WHERE p.invoice_number = ?
        LIMIT 1
    ");
    $stmt->execute([$invoice]);
    $trx = $stmt->fetch();

    if ($trx) {
        $trxType = 'PEMBELIAN_PETANI';
    } else {
        // 2. Cek di Tabel Sales (Penjualan Kopi Trading)
        $sStmt = $db->prepare("
            SELECT s.*, c.name AS partner_name, c.address AS partner_location, c.phone AS partner_phone, 'SALE' as type
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.invoice_number = ?
            LIMIT 1
        ");
        $sStmt->execute([$invoice]);
        $trx = $sStmt->fetch();

        if ($trx) {
            $trxType = 'PENJUALAN_TRADING';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Validasi Struk Digital - CV. Maju Berkah Bersama</title>
    <link rel="icon" type="image/png" href="<?= base_url('assets/images/logo.png') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            padding: 20px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .header-pattern {
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
            color: white;
            padding: 1.75rem 1.25rem 1.5rem;
            text-align: center;
            position: relative;
        }
        .badge-verified {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            padding: 6px 14px;
            border-radius: 50rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 0.85rem;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>

<div class="verify-card">
    <?php if ($trx): ?>
        <!-- HEADER TERVERIFIKASI -->
        <div class="header-pattern">
            <div class="d-inline-flex p-3 rounded-circle bg-white text-success shadow-sm mb-2">
                <i class="bi bi-shield-fill-check fs-1"></i>
            </div>
            <h5 class="fw-bold mb-1">STRUK TERVERIFIKASI</h5>
            <p class="small text-white-50 mb-2">Dokumen Transaksi Sah & Terdaftar Resmi</p>
            <div class="badge-verified">
                <i class="bi bi-patch-check-fill text-success"></i> DATABASE RESMI CV. MBB
            </div>
        </div>

        <!-- BODY RINCIAN TRANSAKSI -->
        <div class="p-4">
            <!-- Nominal Utama -->
            <div class="text-center py-2 mb-3 bg-light rounded-3 border">
                <span class="text-muted small d-block">Total Nilai Transaksi</span>
                <h3 class="fw-bold text-success mb-0">Rp <?= number_format($trx['grand_total'], 0, ',', '.') ?></h3>
            </div>

            <!-- Detail Grid -->
            <div class="mb-3">
                <div class="detail-row">
                    <span class="text-muted">Nomor Faktur / Nota</span>
                    <strong class="font-monospace text-dark"><?= e($trx['invoice_number']) ?></strong>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Jenis Transaksi</span>
                    <strong class="text-dark">
                        <?= $trxType === 'PEMBELIAN_PETANI' ? 'Pembelian Kopi Petani' : 'Penjualan Kopi Trading' ?>
                    </strong>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Tanggal Transaksi</span>
                    <span class="fw-semibold text-dark">
                        <?= date('d F Y', strtotime($trx['purchase_date'] ?? $trx['sale_date'])) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="text-muted"><?= $trxType === 'PEMBELIAN_PETANI' ? 'Mitra Petani' : 'Mitra Pembeli' ?></span>
                    <strong class="text-dark"><?= e($trx['partner_name'] ?: 'Umum') ?></strong>
                </div>

                <?php if ($trxType === 'PEMBELIAN_PETANI'): ?>
                    <div class="detail-row">
                        <span class="text-muted">Timbangan Bruto / Tarra</span>
                        <span class="text-dark"><?= number_format($trx['gross_weight'], 1, ',', '.') ?> Kg / <?= number_format($trx['tare_weight'], 1, ',', '.') ?> Kg</span>
                    </div>
                    <div class="detail-row bg-success bg-opacity-10 px-2 rounded-2 my-1">
                        <span class="fw-bold text-success">Berat Bersih (Netto)</span>
                        <strong class="fw-bold text-success fs-6"><?= number_format($trx['net_weight'], 1, ',', '.') ?> Kg</strong>
                    </div>
                    <div class="detail-row">
                        <span class="text-muted">Harga Beli per Kg</span>
                        <strong class="text-dark">Rp <?= number_format($trx['price_per_kg'], 0, ',', '.') ?></strong>
                    </div>
                <?php else: ?>
                    <div class="detail-row bg-primary bg-opacity-10 px-2 rounded-2 my-1">
                        <span class="fw-bold text-primary">Total Volume Kopi</span>
                        <strong class="fw-bold text-primary fs-6"><?= number_format($trx['total_weight_kg'], 1, ',', '.') ?> Kg</strong>
                    </div>
                <?php endif; ?>

                <div class="detail-row">
                    <span class="text-muted">Status Pembayaran</span>
                    <?php
                    $status = $trx['payment_status'] ?? 'LUNAS';
                    $paid = (float)($trx['paid_amount'] ?? 0);
                    $total = (float)$trx['grand_total'];
                    $remaining = max(0, $total - $paid);
                    ?>
                    <?php if ($status === 'LUNAS'): ?>
                        <span class="badge bg-success px-2 py-1"><i class="bi bi-check-circle me-1"></i>Lunas</span>
                    <?php elseif ($status === 'SEBAGIAN'): ?>
                        <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-pie-chart me-1"></i>DP / Sebagian</span>
                    <?php else: ?>
                        <span class="badge bg-danger px-2 py-1"><i class="bi bi-clock-history me-1"></i>Tempo / Belum Bayar</span>
                    <?php endif; ?>
                </div>

                <?php if ($status === 'SEBAGIAN'): ?>
                    <div class="p-2 rounded-3 bg-warning-subtle border border-warning border-opacity-25 mt-2 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Nominal DP Diberikan:</span>
                            <strong class="text-success fw-bold">Rp <?= number_format($paid, 0, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-danger fw-semibold">Sisa Tagihan / Hutang:</span>
                            <strong class="text-danger fw-bold">Rp <?= number_format($remaining, 0, ',', '.') ?></strong>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($trx['notes'])): ?>
                    <div class="detail-row">
                        <span class="text-muted">Catatan Transaksi</span>
                        <span class="text-dark small"><?= e($trx['notes']) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stempel Verifikasi Digital -->
            <div class="text-center p-3 rounded-3 bg-light border mt-3" style="font-size: 0.72rem; color: #64748b;">
                <i class="bi bi-check2-all text-success me-1"></i>Terverifikasi resmi secara digital oleh <strong>MBB Cloud System</strong><br>
                <span>Waktu Pengecekan: <?= date('d M Y, H:i:s') ?> WIB</span>
            </div>
            
            <div class="text-center mt-3">
                <a href="<?= base_url('apk/index.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-4">
                    <i class="bi bi-house me-1"></i>Buka Sistem MBB
                </a>
            </div>
        </div>

    <?php else: ?>
        <!-- HEADER TIDAK DITEMUKAN / TIDAK VALID -->
        <div class="bg-danger text-white p-4 text-center">
            <div class="d-inline-flex p-3 rounded-circle bg-white text-danger shadow-sm mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-1"></i>
            </div>
            <h5 class="fw-bold mb-1">STRUK TIDAK DITEMUKAN</h5>
            <p class="small text-white-50 mb-0">Nomor Nota atau Faktur tidak terdaftar</p>
        </div>
        
        <div class="p-4 text-center">
            <p class="text-muted small mb-3">
                Data nomor transaksi <strong class="text-dark font-monospace"><?= e($invoice ?: '-') ?></strong> tidak ditemukan dalam database resmi CV. Maju Berkah Bersama.
            </p>
            <p class="text-muted small mb-4">
                Pastikan Anda memindai kode QR dari struk resmi yang diterbitkan oleh MBB Field System.
            </p>
            <a href="<?= base_url('apk/index.php') ?>" class="btn btn-primary rounded-pill px-4">
                Kembali ke Beranda
            </a>
        </div>
    <?php endif; ?>

    <!-- FOOTER RESMI -->
    <div class="py-3 bg-light text-center border-top text-muted small" style="font-size: 0.72rem;">
        &copy; <?= date('Y') ?> CV. MAJU BERKAH BERSAMA &bull; Gudang Kopi Air Hitam
    </div>
</div>

</body>
</html>
