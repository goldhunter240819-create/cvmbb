-- =====================================================================
-- MBB BUSINESS MANAGEMENT SYSTEM DATABASE SCHEMA
-- Perusahaan: CV. MAJU BERKAH BERSAMA (MBB)
-- Lokasi    : Air Hitam, Lampung Barat
-- Database  : mbb_management
-- Engine    : InnoDB
-- Charset   : utf8mb4 / utf8mb4_unicode_ci
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `mbb_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mbb_management`;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. TABEL PENGATURAN PERUSAHAAN (COMPANY SETTINGS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `company_settings`;
CREATE TABLE `company_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. TABEL PERAN PENGGUNA (ROLES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE,
    `role_title` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `is_system_reserved` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. TABEL HAK AKSES PERMISSION (PERMISSIONS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `permission_key` VARCHAR(100) NOT NULL UNIQUE,
    `module` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. TABEL RELASI ROLE - PERMISSION (ROLE_PERMISSIONS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_role_perm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_perm_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. TABEL MASTER DIVISI (DIVISIONS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `divisions`;
CREATE TABLE `divisions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `division_code` VARCHAR(20) NOT NULL UNIQUE,
    `division_name` VARCHAR(100) NOT NULL,
    `parent_id` INT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_div_parent` FOREIGN KEY (`parent_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. TABEL MASTER JABATAN (POSITIONS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `positions`;
CREATE TABLE `positions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `position_code` VARCHAR(20) NOT NULL UNIQUE,
    `position_name` VARCHAR(100) NOT NULL,
    `division_id` INT NULL,
    `parent_position_id` INT NULL,
    `level_rank` INT DEFAULT 1,
    `job_description` TEXT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pos_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pos_parent` FOREIGN KEY (`parent_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. TABEL ANGGOTA & PEGAWAI (EMPLOYEES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_code` VARCHAR(30) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `gender` ENUM('L', 'P') DEFAULT 'L',
    `phone_number` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `division_id` INT NULL,
    `position_id` INT NULL,
    `join_date` DATE NULL,
    `employment_status` ENUM('TETAP', 'KONTRAK', 'MAGANG', 'HONORER') DEFAULT 'TETAP',
    `is_active` TINYINT(1) DEFAULT 1,
    `avatar` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_emp_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_emp_pos` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. TABEL RIWAYAT JABATAN & MUTASI PEGAWAI (POSITION_HISTORIES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `position_histories`;
CREATE TABLE `position_histories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `old_division_id` INT NULL,
    `new_division_id` INT NULL,
    `old_position_id` INT NULL,
    `new_position_id` INT NOT NULL,
    `change_date` DATE NOT NULL,
    `change_reason` VARCHAR(255) NULL,
    `sk_number` VARCHAR(100) NULL,
    `approved_by` VARCHAR(100) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_hist_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. TABEL AKUN PENGGUNA SISTEM (USERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id` INT NOT NULL,
    `employee_id` INT NULL UNIQUE,
    `is_active` TINYINT(1) DEFAULT 1,
    `must_change_password` TINYINT(1) DEFAULT 0,
    `last_login_at` DATETIME NULL,
    `last_login_ip` VARCHAR(45) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    CONSTRAINT `fk_user_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. TABEL AUDIT LOG (JEJAK AKTIVITAS SISTEM)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(50) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `record_id` VARCHAR(50) NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_module` (`module`),
    INDEX `idx_audit_created` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. TABEL KATEGORI PRODUK (CATEGORIES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. TABEL GUDANG (WAREHOUSES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `warehouses`;
CREATE TABLE `warehouses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `warehouse_code` VARCHAR(30) NOT NULL UNIQUE,
    `warehouse_name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(255) NULL,
    `capacity_desc` VARCHAR(100) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. TABEL PRODUK & BARANG (PRODUCTS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `product_name` VARCHAR(150) NOT NULL,
    `category_id` INT NULL,
    `unit` VARCHAR(20) DEFAULT 'kg',
    `cost_price` DECIMAL(15,2) DEFAULT 0.00,
    `sale_price` DECIMAL(15,2) DEFAULT 0.00,
    `current_stock` DECIMAL(12,2) DEFAULT 0.00,
    `min_stock` DECIMAL(12,2) DEFAULT 0.00,
    `is_raw_material` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. TABEL MUTASI STOK / KARTU STOK (STOCK_MOVEMENTS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT NULL,
    `movement_type` ENUM('IN', 'OUT', 'ADJUSTMENT') NOT NULL,
    `reference_type` ENUM('PURCHASE', 'SALE', 'PRODUCTION_IN', 'PRODUCTION_OUT', 'SHRINKAGE', 'OPNAME', 'OTHER') NOT NULL,
    `reference_id` VARCHAR(50) NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `unit_cost` DECIMAL(15,2) DEFAULT 0.00,
    `total_cost` DECIMAL(15,2) DEFAULT 0.00,
    `balance_after` DECIMAL(12,2) NOT NULL,
    `description` VARCHAR(255) NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sm_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sm_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. TABEL PETANI (FARMERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `farmers`;
CREATE TABLE `farmers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `village` VARCHAR(100) NULL,
    `sub_district` VARCHAR(100) DEFAULT 'Air Hitam',
    `farmer_group` VARCHAR(100) NULL,
    `bank_account` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. TABEL SUPPLIER (SUPPLIERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `address` TEXT NULL,
    `bank_details` VARCHAR(150) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. TABEL PELANGGAN (CUSTOMERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `customer_type` ENUM('INDIVIDUAL', 'CAFE', 'WHOLESALER', 'CORPORATE') DEFAULT 'INDIVIDUAL',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. TABEL PEMBELIAN KOPI / BARANG (PURCHASES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `purchase_date` DATE NOT NULL,
    `partner_type` ENUM('FARMER', 'SUPPLIER') NOT NULL,
    `farmer_id` INT NULL,
    `supplier_id` INT NULL,
    `warehouse_id` INT NULL,
    `gross_weight` DECIMAL(12,2) DEFAULT 0.00,
    `tare_weight` DECIMAL(12,2) DEFAULT 0.00,
    `water_content_percent` DECIMAL(5,2) DEFAULT 0.00,
    `deduction_percent` DECIMAL(5,2) DEFAULT 0.00,
    `net_weight` DECIMAL(12,2) DEFAULT 0.00,
    `price_per_kg` DECIMAL(15,2) DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) NOT NULL,
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `payment_status` ENUM('LUNAS', 'SEBAGIAN', 'BELUM_BAYAR') DEFAULT 'LUNAS',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pur_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pur_supp` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_pur_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. TABEL DETAIL PEMBELIAN (PURCHASE_DETAILS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_details`;
CREATE TABLE `purchase_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `purchase_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `weight_kg` DECIMAL(12,2) NOT NULL,
    `water_content` DECIMAL(5,2) DEFAULT 0.00,
    `deduction_kg` DECIMAL(12,2) DEFAULT 0.00,
    `net_weight_kg` DECIMAL(12,2) NOT NULL,
    `price_per_kg` DECIMAL(15,2) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    CONSTRAINT `fk_purd_head` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_purd_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 20. TABEL PENJUALAN KOPI / BARANG (SALES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
    `sale_date` DATE NOT NULL,
    `customer_id` INT NULL,
    `warehouse_id` INT NULL,
    `total_weight_kg` DECIMAL(12,2) DEFAULT 0.00,
    `capital_cost` DECIMAL(15,2) DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL,
    `discount_amount` DECIMAL(15,2) DEFAULT 0.00,
    `grand_total` DECIMAL(15,2) NOT NULL,
    `langsir_fee` DECIMAL(15,2) DEFAULT 0.00,
    `worker_fee` DECIMAL(15,2) DEFAULT 0.00,
    `operational_cost` DECIMAL(15,2) DEFAULT 0.00,
    `invs_amount` DECIMAL(15,2) DEFAULT 0.00,
    `mbb_amount` DECIMAL(15,2) DEFAULT 0.00,
    `profit_amount` DECIMAL(15,2) DEFAULT 0.00,
    `worker_distribution` TEXT NULL,
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `payment_status` ENUM('LUNAS', 'SEBAGIAN', 'BELUM_BAYAR') DEFAULT 'LUNAS',
    `payment_method` ENUM('TUNAI', 'TRANSFER', 'BRILINK') DEFAULT 'TUNAI',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sale_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sale_wh` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 21. TABEL DETAIL PENJUALAN (SALE_DETAILS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sale_details`;
CREATE TABLE `sale_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sale_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `qty` DECIMAL(12,2) NOT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL,
    `subtotal` DECIMAL(15,2) NOT NULL,
    CONSTRAINT `fk_saled_head` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_saled_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 22. TABEL PRODUKSI KOPI INTERNAL (PRODUCTION_ORDERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `production_orders`;
CREATE TABLE `production_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_number` VARCHAR(50) NOT NULL UNIQUE,
    `production_date` DATE NOT NULL,
    `operator_id` INT NULL,
    `raw_product_id` INT NOT NULL,
    `raw_weight_kg` DECIMAL(12,2) NOT NULL,
    `raw_cost_per_kg` DECIMAL(15,2) NOT NULL,
    `total_raw_cost` DECIMAL(15,2) NOT NULL,
    `operational_cost` DECIMAL(15,2) DEFAULT 0.00,
    `output_product_id` INT NOT NULL,
    `output_weight_kg` DECIMAL(12,2) NOT NULL,
    `shrinkage_kg` DECIMAL(12,2) GENERATED ALWAYS AS (`raw_weight_kg` - `output_weight_kg`) STORED,
    `shrinkage_percent` DECIMAL(5,2) GENERATED ALWAYS AS (ROUND(((`raw_weight_kg` - `output_weight_kg`) / `raw_weight_kg`) * 100, 2)) STORED,
    `total_hpp` DECIMAL(15,2) NOT NULL,
    `hpp_per_kg` DECIMAL(15,2) NOT NULL,
    `roast_level` ENUM('LIGHT', 'MEDIUM', 'DARK', 'BUBUK') DEFAULT 'MEDIUM',
    `status` ENUM('DRAFT', 'DALAM_PROSES', 'SELESAI', 'BATAL') DEFAULT 'SELESAI',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_prod_raw` FOREIGN KEY (`raw_product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_prod_out` FOREIGN KEY (`output_product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 23. TABEL JASA ROASTING MAKLON (ROASTING_ORDERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `roasting_orders`;
CREATE TABLE `roasting_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(50) NOT NULL UNIQUE,
    `customer_id` INT NOT NULL,
    `order_date` DATE NOT NULL,
    `raw_weight_kg` DECIMAL(12,2) NOT NULL,
    `roast_profile` ENUM('LIGHT', 'MEDIUM', 'DARK') DEFAULT 'MEDIUM',
    `roasted_weight_kg` DECIMAL(12,2) DEFAULT 0.00,
    `shrinkage_percent` DECIMAL(5,2) DEFAULT 0.00,
    `service_fee_per_kg` DECIMAL(15,2) NOT NULL,
    `total_fee` DECIMAL(15,2) NOT NULL,
    `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
    `payment_status` ENUM('LUNAS', 'BELUM_BAYAR') DEFAULT 'BELUM_BAYAR',
    `work_status` ENUM('ANTREAN', 'ROASTING', 'COOLING_RESTING', 'PACKAGING', 'SIAP_DIAMBIL', 'SELESAI') DEFAULT 'ANTREAN',
    `handover_date` DATE NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_roast_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 24. TABEL AKUN / EDC BRILINK (BRILINK_ACCOUNTS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `brilink_accounts`;
CREATE TABLE `brilink_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_name` VARCHAR(100) NOT NULL,
    `device_edc_id` VARCHAR(50) NULL,
    `account_number` VARCHAR(50) NOT NULL,
    `current_balance` DECIMAL(15,2) DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 25. TABEL TRANSAKSI BRILINK (BRILINK_TRANSACTIONS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `brilink_transactions`;
CREATE TABLE `brilink_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_no` VARCHAR(50) NOT NULL UNIQUE,
    `transaction_date` DATETIME NOT NULL,
    `brilink_account_id` INT NULL,
    `trx_type` ENUM('TRANSFER', 'TARIK_TUNAI', 'SETOR_TUNAI', 'BAYAR_TAGIHAN', 'PULSA_DIGITAL') NOT NULL,
    `customer_name` VARCHAR(100) NULL,
    `customer_phone` VARCHAR(30) NULL,
    `nominal_amount` DECIMAL(15,2) NOT NULL,
    `admin_fee_charged` DECIMAL(15,2) NOT NULL,
    `bank_fee` DECIMAL(15,2) DEFAULT 0.00,
    `company_income` DECIMAL(15,2) GENERATED ALWAYS AS (`admin_fee_charged` - `bank_fee`) STORED,
    `cash_received` DECIMAL(15,2) NOT NULL,
    `status` ENUM('BERHASIL', 'PENDING', 'GAGAL') DEFAULT 'BERHASIL',
    `operator_id` INT NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_brx_acc` FOREIGN KEY (`brilink_account_id`) REFERENCES `brilink_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 26. TABEL AKUN KAS PERUSAHAAN (CASH_ACCOUNTS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `cash_accounts`;
CREATE TABLE `cash_accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `account_code` VARCHAR(30) NOT NULL UNIQUE,
    `account_name` VARCHAR(100) NOT NULL,
    `account_type` ENUM('CASH_ON_HAND', 'BANK_ACCOUNT', 'E_WALLET') DEFAULT 'CASH_ON_HAND',
    `account_number` VARCHAR(50) NULL,
    `bank_name` VARCHAR(50) NULL,
    `current_balance` DECIMAL(15,2) DEFAULT 0.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 27. TABEL JURNAL TRANSAKSI KAS (CASH_TRANSACTIONS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `cash_transactions`;
CREATE TABLE `cash_transactions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `trx_code` VARCHAR(50) NOT NULL UNIQUE,
    `trx_date` DATE NOT NULL,
    `cash_account_id` INT NOT NULL,
    `trx_direction` ENUM('IN', 'OUT') NOT NULL,
    `business_line` ENUM('MANAGEMENT', 'COFFEE_TRADING', 'COFFEE_PRODUCTION', 'ROASTING_SERVICE', 'BRILINK', 'DECORATION', 'SOUND_SYSTEM') DEFAULT 'MANAGEMENT',
    `category` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` VARCHAR(50) NULL,
    `description` TEXT NOT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cashtrx_acc` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 28. TABEL BEBAN BIAYA OPERASIONAL (EXPENSES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expense_code` VARCHAR(50) NOT NULL UNIQUE,
    `expense_date` DATE NOT NULL,
    `cash_account_id` INT NOT NULL,
    `division_id` INT NULL,
    `category` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `recipient` VARCHAR(150) NULL,
    `description` TEXT NOT NULL,
    `receipt_file` VARCHAR(255) NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_exp_cash` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
    CONSTRAINT `fk_exp_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 29. TABEL PIUTANG USAHA (RECEIVABLES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `receivables`;
CREATE TABLE `receivables` (
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

-- ---------------------------------------------------------------------
-- 30. TABEL UTANG USAHA (PAYABLES)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `payables`;
CREATE TABLE `payables` (
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

-- ---------------------------------------------------------------------
-- 31. TABEL INVENTARIS & ASET PERUSAHAAN (INVENTORY_ASSETS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `inventory_assets`;
CREATE TABLE `inventory_assets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `asset_code` VARCHAR(50) NOT NULL UNIQUE,
    `asset_name` VARCHAR(150) NOT NULL,
    `division_id` INT NULL,
    `category` VARCHAR(100) NULL,
    `purchase_date` DATE NULL,
    `acquisition_cost` DECIMAL(15,2) DEFAULT 0.00,
    `asset_condition` ENUM('BAIK', 'RUSAK_RINGAN', 'RUSAK_BERAT') DEFAULT 'BAIK',
    `location` VARCHAR(150) NULL,
    `person_in_charge` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_asset_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 32. TABEL ARSIP SURAT MASUK (INCOMING_LETTERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `incoming_letters`;
CREATE TABLE `incoming_letters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `agenda_number` VARCHAR(50) NOT NULL UNIQUE,
    `letter_number` VARCHAR(100) NOT NULL,
    `sender` VARCHAR(150) NOT NULL,
    `letter_date` DATE NOT NULL,
    `received_date` DATE NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 33. TABEL ARSIP SURAT KELUAR (OUTGOING_LETTERS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `outgoing_letters`;
CREATE TABLE `outgoing_letters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `letter_number` VARCHAR(100) NOT NULL UNIQUE,
    `recipient` VARCHAR(150) NOT NULL,
    `letter_date` DATE NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `signer` VARCHAR(100) NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `status` ENUM('DRAFT', 'DIKIRIM', 'DIARSIPKAN') DEFAULT 'DIKIRIM',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 34. TABEL DOKUMEN PERUSAHAAN (DOCUMENTS)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `doc_code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(200) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_size` INT DEFAULT 0,
    `description` TEXT NULL,
    `uploaded_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 35. TABEL KEGIATAN DIVISI (DEKORASI, SOUND SISTEM, OPERASIONAL)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `division_activities`;
CREATE TABLE `division_activities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `activity_code` VARCHAR(50) NOT NULL UNIQUE,
    `division_id` INT NOT NULL,
    `activity_name` VARCHAR(200) NOT NULL,
    `person_in_charge` VARCHAR(100) NOT NULL,
    `location` VARCHAR(255) NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NULL,
    `total_cost` DECIMAL(15,2) DEFAULT 0.00,
    `total_income` DECIMAL(15,2) DEFAULT 0.00,
    `status` ENUM('RENCANA', 'BERJALAN', 'SELESAI', 'BATAL') DEFAULT 'RENCANA',
    `documentation_url` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_act_div` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA AWAL (PERUSAHAAN, ROLES, PERMISSIONS, ORGANISASI, ADMIN)
-- =====================================================================

-- 1. Identitas Perusahaan
INSERT INTO `company_settings` (`setting_key`, `setting_value`, `description`) VALUES
('company_name', 'CV. MAJU BERKAH BERSAMA', 'Nama Resmi Perusahaan'),
('company_short_name', 'MBB', 'Singkatan Perusahaan'),
('company_tagline', 'Integritas, Kualitas, dan Keberkahan Usaha', 'Slogan Perusahaan'),
('company_address', 'Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat', 'Alamat Kantor Pusat'),
('company_phone', '0812-7890-1234', 'Nomor Telepon Kantor'),
('company_email', 'info@cvmbb.co.id', 'Email Resmi Perusahaan'),
('company_logo', 'assets/images/logo.png', 'Path File Logo Perusahaan'),
('app_name', 'MBB BUSINESS MANAGEMENT SYSTEM', 'Nama Aplikasi ERP'),
('default_signer', 'M. ROZIKIN', 'Penandatangan Dokumen Standar'),
('default_signer_title', 'Direktur', 'Jabatan Penandatangan Standar')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- 2. Master Peran (Roles)
INSERT INTO `roles` (`id`, `role_name`, `role_title`, `description`, `is_system_reserved`) VALUES
(1, 'super_admin', 'SUPER ADMIN', 'Akses penuh ke seluruh aplikasi, konfigurasi, dan database', 1),
(2, 'komisaris', 'KOMISARIS', 'Akses strategis, ringkasan laporan keuangan dan performa perusahaan', 1),
(3, 'direksi', 'DIREKSI', 'Memantau administrasi, operasional, dan pengawasan manajerial', 1),
(4, 'direktur', 'DIREKTUR', 'Memantau operasional eksekutif dan menyetujui keputusan manajemen', 1),
(5, 'manager', 'MANAGER', 'Memantau kegiatan divisi operasional dan memimpin lini produksi/jasa', 1),
(6, 'administrasi', 'ADMINISTRASI', 'Mengelola administrasi persuratan, arsip, SDM, dan dokumen', 1),
(7, 'operasional', 'OPERASIONAL', 'Mengelola transaksi kopi, produksi, dan pergudangan harian', 1),
(8, 'petugas_divisi', 'PETUGAS DIVISI', 'Mengelola operasional teknis divisi spesifik (Dekorasi/Sound/BRILink)', 1)
ON DUPLICATE KEY UPDATE `role_title` = VALUES(`role_title`);

-- 3. Master Permissions
INSERT INTO `permissions` (`permission_key`, `module`, `name`, `description`) VALUES
('dashboard.view', 'dashboard', 'Lihat Dashboard', 'Melihat ringkasan KPI dan grafik statistik'),
('organization.view', 'organization', 'Lihat Struktur Organisasi', 'Melihat diagram hierarki dan daftar jabatan'),
('organization.manage', 'organization', 'Kelola Organisasi', 'Tambah, edit, hapus jabatan dan divisi'),
('employees.view', 'employees', 'Lihat Anggota & Pegawai', 'Melihat data anggota perusahaan'),
('employees.manage', 'employees', 'Kelola Pegawai', 'Tambah, edit, hapus data pegawai & mutasi jabatan'),
('administration.view', 'administration', 'Lihat Dokumen & Surat', 'Melihat arsip surat masuk dan keluar'),
('administration.manage', 'administration', 'Kelola Dokumen & Surat', 'Membuat surat, mengunggah arsip dokumen digital'),
('coffee_trading.view', 'coffee-trading', 'Lihat Trading Kopi', 'Melihat data pembelian & penjualan kopi'),
('coffee_trading.manage', 'coffee-trading', 'Kelola Trading Kopi', 'Melakukan transaksi beli dan jual kopi'),
('production.view', 'coffee-production', 'Lihat Produksi Kopi', 'Melihat riwayat batch produksi roasting internal'),
('production.manage', 'coffee-production', 'Kelola Produksi Kopi', 'Membuat order roasting, grinding, dan packaging'),
('roasting_service.view', 'roasting-service', 'Lihat Jasa Roasting', 'Melihat pesanan jasa maklon roasting'),
('roasting_service.manage', 'roasting-service', 'Kelola Jasa Roasting', 'Menerima order, ubah status kerja & cetak nota'),
('brilink.view', 'brilink', 'Lihat Transaksi BRILink', 'Melihat rekap transaksi harian BRILink'),
('brilink.manage', 'brilink', 'Kelola Transaksi BRILink', 'Mencatat transaksi & rekonsiliasi kas harian'),
('division_activity.view', 'activities', 'Lihat Kegiatan Divisi', 'Melihat proyek dekorasi & sound sistem'),
('division_activity.manage', 'activities', 'Kelola Kegiatan Divisi', 'Membuat & mengupdate proyek dekorasi/sound'),
('inventory.view', 'inventory', 'Lihat Inventaris Aset', 'Melihat daftar aset perusahaan'),
('inventory.manage', 'inventory', 'Kelola Inventaris Aset', 'Menambah dan memperbarui data aset & mutasi'),
('finance.view', 'finance', 'Lihat Laporan Keuangan', 'Melihat arus kas, pengeluaran, utang piutang'),
('finance.manage', 'finance', 'Kelola Transaksi Kas', 'Mencatat pengeluaran, pelunasan utang/piutang'),
('users.manage', 'settings', 'Kelola Pengguna & Hak Akses', 'Mengatur akun pengguna dan izin role'),
('company.manage', 'settings', 'Kelola Profil Perusahaan', 'Mengubah profil, alamat, dan logo instansi'),
('backup.manage', 'settings', 'Backup & Restore Database', 'Melakukan pencadangan database perusahaan')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 4. Role Permissions Mapping: Super Admin (All Permissions)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions`;

-- Mapping role Direksi & Direktur
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `permission_key` LIKE '%.view' OR `permission_key` = 'organization.view';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, `id` FROM `permissions` WHERE `permission_key` LIKE '%.view' OR `permission_key` IN ('organization.manage', 'employees.manage', 'company.manage');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, `id` FROM `permissions` WHERE `permission_key` LIKE '%.view' OR `permission_key` IN ('coffee_trading.manage', 'production.manage', 'roasting_service.manage', 'division_activity.manage', 'finance.view');

-- 5. Divisi Perusahaan MBB
INSERT INTO `divisions` (`id`, `division_code`, `division_name`, `parent_id`, `description`) VALUES
(1, 'DIV-KMS', 'Dewan Komisaris', NULL, 'Lembaga pengawas dan penasihat strategis perusahaan'),
(2, 'DIV-DIR', 'Direksi & Manajemen', NULL, 'Pimpinan eksekutif tertinggi perusahaan'),
(3, 'DIV-ADM', 'Divisi Administrasi', 2, 'Pengelolaan persuratan, tata usaha, dan kepegawaian di bawah pengawasan Direksi'),
(4, 'DIV-OPS', 'Divisi Operasional', 2, 'Pengelolaan operasional harian perusahaan di bawah pengawasan Direksi'),
(5, 'DIV-DEK', 'Divisi Dekoration', 2, 'Penyelenggaraan jasa dekorasi acara dan pesta di bawah koordinasi Manager'),
(6, 'DIV-SND', 'Divisi Sound Sistem', 2, 'Penyediaan dan operasional sistem tata suara di bawah koordinasi Manager'),
(7, 'DIV-KOP', 'Divisi Kopi & Produksi', 2, 'Trading biji kopi, produksi bubuk kopi, dan jasa roasting maklon'),
(8, 'DIV-BRI', 'Divisi Agen BRILink', 2, 'Layanan keuangan mikro dan perbankan digital BRILink')
ON DUPLICATE KEY UPDATE `division_name` = VALUES(`division_name`);

-- 6. Master Jabatan Berhierarki Sesuai Ketetapan Perusahaan
INSERT INTO `positions` (`id`, `position_code`, `position_name`, `division_id`, `parent_position_id`, `level_rank`, `job_description`) VALUES
(1, 'POS-KMS', 'Komisaris', 1, NULL, 1, 'Pengawas tertinggi dan penasihat strategis arah kebijakan perusahaan'),
(2, 'POS-DIRK', 'Direksi', 2, 1, 2, 'Pimpinan eksekutif yang membawahi manajemen, divisi administrasi dan operasional'),
(3, 'POS-ADMH', 'Penanggung Jawab Divisi Administrasi', 3, 2, 3, 'Penanggung jawab urusan administrasi, legalitas, arsip, dan korespondensi perusahaan di bawah Direksi'),
(4, 'POS-OPSH', 'Penanggung Jawab Divisi Operasional', 4, 2, 3, 'Penanggung jawab operasional terpadu perusahaan di bawah Direksi'),
(5, 'POS-DIR', 'Direktur', 2, 2, 3, 'Pimpinan eksekutif operasional perusahaan'),
(6, 'POS-MGR', 'Manager', 2, 5, 4, 'Koordinator operasional yang mengawasi divisi dekorasi, sound sistem, dan teknis lapangan'),
(7, 'POS-DEKH', 'Penanggung Jawab Divisi Dekoration', 5, 6, 5, 'Penanggung jawab desain, persiapan, dan eksekusi dekorasi di bawah Manager'),
(8, 'POS-SNDH', 'Penanggung Jawab Divisi Sound Sistem', 6, 6, 5, 'Penanggung jawab instalasi, kalibrasi, dan operasional audio sound sistem di bawah Manager')
ON DUPLICATE KEY UPDATE `position_name` = VALUES(`position_name`);

-- 7. Anggota Pejabat Struktural Resmi Sesuai Data Perusahaan
INSERT INTO `employees` (`id`, `employee_code`, `full_name`, `gender`, `phone_number`, `email`, `address`, `division_id`, `position_id`, `join_date`, `employment_status`, `is_active`, `notes`) VALUES
(1, 'MBB-001', 'KH. MUHAMMAD THOHA', 'L', '0812-1000-0001', 'komisaris@cvmbb.co.id', 'Air Hitam, Lampung Barat', 1, 1, '2023-01-01', 'TETAP', 1, 'Komisaris Perusahaan'),
(2, 'MBB-002', 'NUR ISLAH', 'P', '0812-1000-0002', 'direksi@cvmbb.co.id', 'Air Hitam, Lampung Barat', 2, 2, '2023-01-01', 'TETAP', 1, 'Direksi Perusahaan'),
(3, 'MBB-003', 'M. ROZIKIN', 'L', '0812-1000-0003', 'direktur@cvmbb.co.id', 'Air Hitam, Lampung Barat', 2, 5, '2023-01-01', 'TETAP', 1, 'Direktur Perusahaan'),
(4, 'MBB-004', 'NURIL HUDA', 'L', '0812-1000-0004', 'administrasi@cvmbb.co.id', 'Air Hitam, Lampung Barat', 3, 3, '2023-01-01', 'TETAP', 1, 'Penanggung Jawab Divisi Administrasi'),
(5, 'MBB-005', 'AHMAD SOBIRIN', 'L', '0812-1000-0005', 'operasional@cvmbb.co.id', 'Air Hitam, Lampung Barat', 4, 4, '2023-01-01', 'TETAP', 1, 'Penanggung Jawab Divisi Operasional'),
(6, 'MBB-006', 'M. ILHAM SIFA', 'L', '0812-1000-0006', 'manager@cvmbb.co.id', 'Air Hitam, Lampung Barat', 2, 6, '2023-01-01', 'TETAP', 1, 'Manager Operasional'),
(7, 'MBB-007', 'FATKHUR ROHMAN', 'L', '0812-1000-0007', 'dekorasi@cvmbb.co.id', 'Air Hitam, Lampung Barat', 5, 7, '2023-01-01', 'TETAP', 1, 'Penanggung Jawab Divisi Dekoration'),
(8, 'MBB-008', 'MISBAHUL MUNIR', 'L', '0812-1000-0008', 'soundsystem@cvmbb.co.id', 'Air Hitam, Lampung Barat', 6, 8, '2023-01-01', 'TETAP', 1, 'Penanggung Jawab Divisi Sound Sistem')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- 8. Riwayat Jabatan Baseline
INSERT INTO `position_histories` (`employee_id`, `old_division_id`, `new_division_id`, `old_position_id`, `new_position_id`, `change_date`, `change_reason`, `sk_number`, `approved_by`) VALUES
(1, NULL, 1, NULL, 1, '2023-01-01', 'Penetapan Pendirian Perusahaan', 'SK/MBB/2023/001', 'Rapat Umum Pemegang Saham'),
(2, NULL, 2, NULL, 2, '2023-01-01', 'Pengangkatan Direksi Utama', 'SK/MBB/2023/002', 'KH. MUHAMMAD THOHA'),
(3, NULL, 2, NULL, 5, '2023-01-01', 'Pengangkatan Direktur Operasional', 'SK/MBB/2023/003', 'NUR ISLAH'),
(4, NULL, 3, NULL, 3, '2023-01-01', 'Penugasan PJ Divisi Administrasi', 'SK/MBB/2023/004', 'NUR ISLAH'),
(5, NULL, 4, NULL, 4, '2023-01-01', 'Penugasan PJ Divisi Operasional', 'SK/MBB/2023/005', 'NUR ISLAH'),
(6, NULL, 2, NULL, 6, '2023-01-01', 'Pengangkatan Manager', 'SK/MBB/2023/006', 'M. ROZIKIN'),
(7, NULL, 5, NULL, 7, '2023-01-01', 'Penugasan PJ Divisi Dekoration', 'SK/MBB/2023/007', 'M. ILHAM SIFA'),
(8, NULL, 6, NULL, 8, '2023-01-01', 'Penugasan PJ Divisi Sound Sistem', 'SK/MBB/2023/008', 'M. ILHAM SIFA');

-- 9. Akun Login Administrator & Developer Awal
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `employee_id`, `is_active`, `must_change_password`) VALUES
(1, 'admin', 'admin@cvmbb.co.id', '$2y$10$ypAbq8d3Rjx/4/7ZCYhw8uu.zEJ5ryMVP0gNyCaj0zmBhNVf8yPSS', 1, 3, 1, 0),
(2, 'Maul', 'developer@cvmbb.co.id', '$2y$10$K3H.3yJHbxq2xjk4jDYXR.ud0qjBGwKsJ//kjJiM3gFa3FU/SApni', 1, NULL, 1, 0)
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`), `password_hash` = VALUES(`password_hash`);

-- 10. Akun Kas Awal
INSERT INTO `cash_accounts` (`id`, `account_code`, `account_name`, `account_type`, `account_number`, `bank_name`, `current_balance`, `is_active`) VALUES
(1, 'KAS-01', 'Kas Utama Kantor', 'CASH_ON_HAND', '-', 'Tunai Kantor', 0.00, 1),
(2, 'KAS-02', 'Kas Operasional Kopi', 'CASH_ON_HAND', '-', 'Tunai Lapangan', 0.00, 1),
(3, 'KAS-03', 'Kas Kasir BRILink', 'CASH_ON_HAND', '-', 'Kas Fisik Tunai BRILink', 0.00, 1),
(4, 'BNK-01', 'Rekening BRI Operasional', 'BANK_ACCOUNT', '0123-01-000456-50-8', 'Bank BRI', 0.00, 1)
ON DUPLICATE KEY UPDATE `account_name` = VALUES(`account_name`);

-- 11. Kategori Produk Awal
INSERT INTO `categories` (`id`, `code`, `name`, `description`) VALUES
(1, 'CAT-GB', 'Green Beans (Kopi Mentah)', 'Biji kopi mentah hasil panen petani lokal'),
(2, 'CAT-RB', 'Roasted Beans (Kopi Sangrai)', 'Biji kopi yang telah disangrai sesuai roast profile'),
(3, 'CAT-GC', 'Ground Coffee (Kopi Bubuk)', 'Kopi yang telah digiling halus/medium untuk konsumsi'),
(4, 'CAT-PKG', 'Kemasan & Bahan Pembantu', 'Plastik standing pouch, valve, stiker kemasan')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 12. Gudang Awal
INSERT INTO `warehouses` (`id`, `warehouse_code`, `warehouse_name`, `location`, `capacity_desc`, `is_active`) VALUES
(1, 'GDG-01', 'Gudang Utama Air Hitam', 'Jln. Raya Gunung Terang No. 09', 'Kapasitas 50 Ton Biji Kopi', 1),
(2, 'GDG-02', 'Gudang Roastery & Kemasan', 'Area Rumah Produksi MBB', 'Kapasitas 10 Ton Produk Jadi', 1)
ON DUPLICATE KEY UPDATE `warehouse_name` = VALUES(`warehouse_name`);
