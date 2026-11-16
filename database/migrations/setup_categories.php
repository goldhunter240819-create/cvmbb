<?php
require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance();

    $db->exec("
        CREATE TABLE IF NOT EXISTS `finance_categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_type` ENUM('IN', 'OUT') NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    $count = (int)$db->query("SELECT COUNT(*) FROM finance_categories")->fetchColumn();
    if ($count === 0) {
        $inCats = [
            'Penjualan Kopi Trading',
            'Penjualan Kopi Bubuk',
            'Fee Jasa Roasting',
            'Fee Agen BRILink',
            'Jasa Sewa Sound Sistem',
            'Jasa Sewa Dekorasi',
            'Pelunasan Piutang Usaha',
            'Modal Masuk / Investasi',
            'Pemasukan Lain-lain'
        ];

        $outCats = [
            'Pembelian Kopi Petani',
            'Operasional Kantor',
            'Gaji & Upah Karyawan',
            'BBM & Transportasi Pengiriman',
            'Listrik, Air & Internet',
            'Pembelian Karung & Kemasan',
            'Konsumsi & Dapur Lapangan',
            'Perawatan Mesin & Aset',
            'Pembayaran Hutang Usaha',
            'Pengeluaran Lain-lain'
        ];

        $stmt = $db->prepare("INSERT INTO finance_categories (category_type, name) VALUES (?, ?)");
        foreach ($inCats as $c) {
            $stmt->execute(['IN', $c]);
        }
        foreach ($outCats as $c) {
            $stmt->execute(['OUT', $c]);
        }
        echo "SUCCESS: Kategori kas berhasil dibuat (" . (count($inCats) + count($outCats)) . " kategori)!\n";
    } else {
        echo "EXISTS: Kategori kas sudah ada ({$count} kategori).\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
