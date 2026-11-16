<?php
/**
 * Fast Server-side QR Code API
 * CV. MAJU BERKAH BERSAMA
 */
require_once __DIR__ . '/../helpers/utils.php';

$invoice = trim($_GET['inv'] ?? '');
$verifyUrl = base_url('apk/verify.php?inv=' . urlencode($invoice));

if (isset($_GET['raw'])) {
    try {
        require_once __DIR__ . '/../core/qrcode.php';
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        $qr = new QRCode($verifyUrl, ['sf' => 3]);
        $img = $qr->render_image();
        if ($img) {
            imagepng($img);
            imagedestroy($img);
            exit;
        }
    } catch (Throwable $e) {}
}

$qrBase64 = generate_qr_base64($verifyUrl, 3);

header('Content-Type: application/json');
echo json_encode([
    'invoice'    => $invoice,
    'verify_url' => $verifyUrl,
    'qr'         => $qrBase64
]);
