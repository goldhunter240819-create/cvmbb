-- =====================================================================
-- MIGRATION AMAN (NON-DESTRUKTIF) - MBB SYSTEM
-- Script ini aman dijalankan di database lokal maupun server hosting / live.
-- SIFAT: TIDAK MENGHAPUS / TIDAK MENIMPA DATA LAMA (Menggunakan IF NOT EXISTS)
-- =====================================================================

-- 1. Pastikan Tabel Piutang Usaha (Receivables) Tersedia
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

-- 2. Pastikan Tabel Hutang Usaha (Payables) Tersedia
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


-- 3. Pastikan Tabel Kategori Keuangan (Finance Categories) Tersedia
CREATE TABLE IF NOT EXISTS `finance_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_type` ENUM('IN', 'OUT') NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
