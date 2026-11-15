<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Ekspor Laporan Keuangan Konsolidasi ke Format Excel / CSV
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

$filename = "Laba_Rugi_Konsolidasi_MBB_{$yearFilter}" . ($monthFilter ? "_{$monthFilter}" : "") . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF");

fputcsv($output, ['CV. MAJU BERKAH BERSAMA (MBB)']);
fputcsv($output, ['LAPORAN LABA RUGI KONSOLIDASI']);
fputcsv($output, ['Periode', ($monthFilter ? "Bulan {$monthFilter} - " : "") . "Tahun {$yearFilter}"]);
fputcsv($output, []);

fputcsv($output, ['Lini Usaha / Pos Keuangan', 'Pendapatan (Rp)', 'Biaya / Beban (Rp)', 'Laba Bersih (Rp)']);

// 1. Trading
fputcsv($output, ['Coffee Trading (Kopi Biji)', $trading['total_revenue'], $trading['total_capital'] + $trading['total_langsir'] + $trading['total_worker'] + $trading['total_op'], $trading['net_profit']]);

// 2. Roasting Service
fputcsv($output, ['Coffee Roasting Service (Maklon)', $roasting['total_fee'], 0, $roasting['total_fee']]);

// 3. BRILink
fputcsv($output, ['Agen BRILink (Fee Layanan)', $brilink['net_income'], 0, $brilink['net_income']]);

// 4. Dekorasi
fputcsv($output, ['Divisi Dekoration', $dekor['total_income'], $dekor['total_cost'], $dekorProfit]);

// 5. Sound Sistem
fputcsv($output, ['Divisi Sound Sistem', $sound['total_income'], $sound['total_cost'], $soundProfit]);

// 6. Beban Umum
fputcsv($output, ['Beban Operasional Kantor Pusat', 0, $generalExpense, -$generalExpense]);

fputcsv($output, []);
fputcsv($output, ['TOTAL LABA BERSIH KONSOLIDASI PERUSAHAAN', '', '', $totalConsolidatedProfit]);

fclose($output);
exit;
