<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Ekspor Data Penjualan & Pengiriman Kopi ke Format Excel / CSV
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('coffee_trading.view');

$db = Database::getInstance();

$month = trim($_GET['month'] ?? '');
$query = "
    SELECT s.*, c.name AS customer_name 
    FROM sales s 
    LEFT JOIN customers c ON s.customer_id = c.id
    WHERE 1=1
";
$params = [];
if ($month !== '') {
    $query .= " AND DATE_FORMAT(s.sale_date, '%Y-%m') = ?";
    $params[] = $month;
}
$query .= " ORDER BY s.sale_date ASC, s.id ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll();

$filename = "Rekap_Penjualan_Kopi_MBB_" . ($month ?: date('Y')) . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM for UTF-8 Excel support
fputs($output, "\xEF\xBB\xBF");

// Header CSV
fputcsv($output, [
    'No',
    'No. Surat Jalan / Nota',
    'Tanggal Kirim',
    'Pembeli / Rekanan',
    'Volume (Kg)',
    'Modal Kopi (Rp)',
    'Omzet Penjualan (Rp)',
    'Biaya Langsir (Rp)',
    'Biaya Pekerja (Rp)',
    'Operasional Armada (Rp)',
    'MBB Reserve (Rp)',
    'Laba Bersih MBB (Rp)',
    'Catatan'
]);

$no = 1;
foreach ($sales as $s) {
    fputcsv($output, [
        $no++,
        $s['invoice_number'],
        $s['sale_date'],
        $s['customer_name'] ?? 'Pelanggan Umum',
        $s['total_weight_kg'],
        $s['capital_cost'],
        $s['grand_total'],
        $s['langsir_fee'],
        $s['worker_fee'],
        $s['operational_cost'],
        $s['mbb_amount'],
        $s['profit_amount'],
        $s['notes']
    ]);
}

fclose($output);
exit;
