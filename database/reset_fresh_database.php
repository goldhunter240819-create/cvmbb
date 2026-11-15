<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Script Pembersihan Database Menjadi FRESH / BERSIH untuk Mulai Operasional
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

if (php_sapi_name() !== 'cli') {
    echo '<!DOCTYPE html><html><head><title>Reset Database Fresh - CV. MBB</title></head><body style="background:#0f172a; color:#f8fafc; font-family:monospace; padding:30px;"><div style="max-width:800px; margin:0 auto; background:#1e293b; padding:25px; border-radius:12px; border:1px solid #334155;"><h2 style="color:#38bdf8; margin-top:0;">⚡ MBB Fresh Database Reset</h2><pre style="background:#090d16; padding:15px; border-radius:8px; color:#4ade80;">';
}

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
    'suppliers',
    'inventory_assets'
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

if (php_sapi_name() !== 'cli') {
    echo '</pre>';
    echo '<div style="margin-top:20px;">';
    echo '<a href="../index.php" style="background:#0d6efd; color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-family:sans-serif; font-weight:bold;">← Kembali ke Dashboard MBB</a>';
    echo '</div></div></body></html>';
}
