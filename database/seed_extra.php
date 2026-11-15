<?php
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

$products = [
    ['KOP-ROAST-MED', 'Roasted Beans Robusta MBB (Medium)', 2, 'kg', 65000, 85000, 50, 10, 0],
    ['KOP-ROAST-DRK', 'Roasted Beans Robusta MBB (Dark)', 2, 'kg', 65000, 85000, 50, 10, 0],
    ['KOP-BUBUK-250', 'Kopi Bubuk Asli MBB Kemasan 250 Gram', 2, 'pack', 18000, 25000, 120, 20, 0],
    ['KOP-BUBUK-500', 'Kopi Bubuk Asli MBB Kemasan 500 Gram', 2, 'pack', 35000, 48000, 80, 15, 0],
    ['KOP-BUBUK-1KG', 'Kopi Bubuk Asli MBB Kemasan 1 Kg', 2, 'pack', 68000, 90000, 40, 10, 0],
];

$stmt = $db->prepare("
    INSERT INTO products (sku, product_name, category_id, unit, cost_price, sale_price, current_stock, min_stock, is_raw_material)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE product_name = VALUES(product_name), sale_price = VALUES(sale_price)
");

foreach ($products as $p) {
    $stmt->execute($p);
}

// Ensure BRILink accounts exist
$db->exec("
    INSERT INTO brilink_accounts (account_name, device_edc_id, account_number, current_balance, is_active)
    VALUES 
    ('EDC BRI MBB Air Hitam', 'EDC-BRI-001', '0123-01-009876-50-1', 45000000, 1),
    ('BRILink Mobile Unit 2', 'MOB-BRI-002', '0123-01-009876-50-2', 20000000, 1)
    ON DUPLICATE KEY UPDATE account_name = VALUES(account_name);
");

// Ensure Cash Accounts exist
$db->exec("
    INSERT INTO cash_accounts (account_code, account_name, account_type, account_number, bank_name, current_balance, is_active)
    VALUES
    ('KAS-UTAMA', 'Kas Tunai Utama MBB', 'CASH_ON_HAND', '-', '-', 25000000, 1),
    ('BANK-BRI', 'Rekening Operasional BRI MBB', 'BANK_ACCOUNT', '0123-01-001234-50-0', 'Bank BRI', 85000000, 1),
    ('KAS-KOPI', 'Kas Operasional Coffee Trading', 'CASH_ON_HAND', '-', '-', 15000000, 1)
    ON DUPLICATE KEY UPDATE account_name = VALUES(account_name);
");

echo "Seeded products, brilink_accounts, and cash_accounts successfully.\n";
