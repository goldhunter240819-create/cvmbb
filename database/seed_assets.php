<?php
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

$assets = [
    ['AST-202601-001', 'Mesin Roasting Kopi Kapasitas 10 Kg MBB-PRO', 7, 'Mesin & Peralatan', '2026-01-15', 75000000, 'BAIK', 'Gudang Roasting Air Hitam', 'Ahmad Sobirin', 'Drum stainless 304, burner infrared'],
    ['AST-202601-002', 'Mesin Huller & Grinder Kopi Komersial', 7, 'Mesin & Peralatan', '2026-02-10', 18500000, 'BAIK', 'Gudang Roasting Air Hitam', 'Ahmad Sobirin', 'Kapasitas giling 50 kg/jam'],
    ['AST-202602-003', 'Timbangan Digital Presisi 150 Kg', 7, 'Mesin & Peralatan', '2026-02-12', 3200000, 'BAIK', 'Penerimaan Biji Kopi', 'Ahmad Sobirin', 'Timbangan duduk platform'],
    ['AST-202603-004', 'Paket Audio Sound System Line Array 10.000 Watt', 6, 'Elektronik & Audio', '2026-03-05', 125000000, 'BAIK', 'Gudang Audio & Sound', 'Misbahul Munir', '8 Box Line array, 4 sub 18 inch, power amplifier'],
    ['AST-202603-005', 'Genset Silent Diesel 25 KVA', 6, 'Mesin & Peralatan', '2026-03-20', 48000000, 'BAIK', 'Gudang Audio & Sound', 'Misbahul Munir', 'Cadangan daya sound system lapangan'],
    ['AST-202604-006', 'Set Rangka Pelaminan Rustic Modern & Karpet VIP', 5, 'Peralatan Dekorasi', '2026-04-10', 35000000, 'BAIK', 'Gudang Dekorasi', 'Fatkhur Rohman', 'Modul besi hollow, bunga artificial premium'],
    ['AST-202604-007', 'Spotlight Lighting Panggung & Tenda VIP (12 Unit)', 5, 'Peralatan Dekorasi', '2026-04-15', 12000000, 'BAIK', 'Gudang Dekorasi', 'Fatkhur Rohman', 'Moving head & Par LED'],
    ['AST-202605-008', 'Mesin EDC BRILink Pax S900', 8, 'Elektronik & Audio', '2026-05-01', 4500000, 'BAIK', 'Loket BRILink MBB', 'Nuril Huda', 'Sertifikasi resmi Bank BRI'],
    ['AST-202605-009', 'Laptop Administrasi & Kasir Lenovo ThinkPad', 3, 'Perlengkapan Kantor', '2026-05-10', 9500000, 'BAIK', 'Kantor Administrasi', 'Nuril Huda', 'Intel Core i5, RAM 16GB']
];

$stmt = $db->prepare("
    INSERT INTO inventory_assets (
        asset_code, asset_name, division_id, category, purchase_date,
        acquisition_cost, asset_condition, location, person_in_charge, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE asset_name = VALUES(asset_name)
");

foreach ($assets as $a) {
    $stmt->execute($a);
}

echo "Seeded 9 initial assets successfully.\n";
