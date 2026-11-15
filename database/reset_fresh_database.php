<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Script Pembersihan Database Menjadi FRESH / BERSIH untuk Mulai Operasional
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

echo "Memulai proses pembersihan transaksi database MBB...\n";

$db->exec("SET FOREIGN_KEY_CHECKS = 0;");

$tablesToTruncate = [
    'purchases',
    'purchase_details',
    'sales',
    'sale_details',
    'roasting_orders',
    'brilink_transactions',
    'cash_transactions',
    'division_activities',
    'production_orders',
    'expenses',
    'receivables',
    'payables',
    'stock_movements',
    'incoming_letters',
    'outgoing_letters',
    'documents',
    'audit_logs',
    'position_histories',
    'farmers',
    'customers',
    'suppliers'
];

foreach ($tablesToTruncate as $tbl) {
    try {
        $db->exec("TRUNCATE TABLE `{$tbl}`");
        echo "  [OK] Tabel `{$tbl}` dikosongkan.\n";
    } catch (Exception $e) {
        echo "  [SKIP/ERR] Tabel `{$tbl}`: " . $e->getMessage() . "\n";
    }
}

// Reset Saldo Kas dan Rekening ke 0 (Fresh)
$db->exec("UPDATE cash_accounts SET current_balance = 0");
echo "  [OK] Saldo seluruh buku kas direset ke Rp 0.\n";

$db->exec("UPDATE brilink_accounts SET current_balance = 0");
echo "  [OK] Saldo seluruh rekening EDC BRILink direset ke Rp 0.\n";

// Reset Stok Produk ke 0 (Fresh)
$db->exec("UPDATE products SET current_stock = 0");
echo "  [OK] Stok seluruh produk direset ke 0 Kg/Pack.\n";

$db->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\nProses pengosongan transaksi selesai! Master Data (Pegawai, User, Divisi, Akun) tetap UTUH.\n";
