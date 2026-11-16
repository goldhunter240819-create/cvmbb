<?php
/**
 * MBB SAFE MIGRATION RUNNER
 * Aman dijalankan di server live / hosting. Tidak menghapus/menimpa data yang ada.
 */

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    // 1. Receivables
    $db->exec("
        CREATE TABLE IF NOT EXISTS `receivables` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reference_number` VARCHAR(50) NOT NULL,
            `business_line` VARCHAR(50) NOT NULL,
            `debtor_name` VARCHAR(150) NOT NULL,
            `debtor_phone` VARCHAR(30) NULL,
            `due_date` DATE NULL,
            `total_amount` DECIMAL(15,2) NOT NULL,
            `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
            `remaining_amount` DECIMAL(15,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
            `status` ENUM('BELUM_LUNAS', 'SEBAGIAN', 'LUNAS') DEFAULT 'BELUM_LUNAS',
            `notes` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Payables
    $db->exec("
        CREATE TABLE IF NOT EXISTS `payables` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `reference_number` VARCHAR(50) NOT NULL,
            `business_line` VARCHAR(50) NOT NULL,
            `creditor_name` VARCHAR(150) NOT NULL,
            `creditor_phone` VARCHAR(30) NULL,
            `due_date` DATE NULL,
            `total_amount` DECIMAL(15,2) NOT NULL,
            `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
            `remaining_amount` DECIMAL(15,2) GENERATED ALWAYS AS (`total_amount` - `paid_amount`) STORED,
            `status` ENUM('BELUM_LUNAS', 'SEBAGIAN', 'LUNAS') DEFAULT 'BELUM_LUNAS',
            `notes` TEXT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "<h3 style='color:green;'>✓ Migrasi Database Aman Selesai!</h3>";
    echo "<p>Tabel <strong>payables</strong> dan <strong>receivables</strong> siap digunakan.</p>";
    echo "<p>Semua data transaksi yang sudah ada <strong>100% AMAN dan TIDAK TERTIRU/TERTIMPA</strong>.</p>";
    echo "<a href='../../apk/debts.php'>Buka Buku Hutang & Piutang APK &raquo;</a>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>Gagal Migrasi: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
