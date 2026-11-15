<?php
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

$activities = [
    // Dekoration (division_id = 5)
    ['DEK-202611-001', 5, 'Dekorasi Pernikahan Adat Lampung Bella & Rendy', 'FATKHUR ROHMAN', 'GSG Air Hitam', '2026-11-08', '2026-11-09', 4200000, 12500000, 'SELESAI', 'Pelaminan adat modifikasi, tenda VIP, lighting'],
    ['DEK-202611-002', 5, 'Dekorasi Panggung Pentas Seni & Wisuda Santri', 'FATKHUR ROHMAN', 'Kompleks Pesantren Air Hitam', '2026-11-14', '2026-11-14', 1800000, 5000000, 'SELESAI', 'Backdrop 10m x 4m, taman mini, mini stage'],
    ['DEK-202611-003', 5, 'Booking Dekorasi Lamaran & Akad Bpk. Hartono', 'FATKHUR ROHMAN', 'Gunung Terang No. 12', '2026-11-28', '2026-11-28', 1200000, 3500000, 'RENCANA', 'Paket intimate rustic backdrop'],

    // Sound System (division_id = 6)
    ['SND-202611-001', 6, 'Sewa Sound System Lapangan Pengajian Akbar Maulid', 'MISBAHUL MUNIR', 'Lapangan Merdeka Air Hitam', '2026-11-05', '2026-11-05', 2500000, 7500000, 'SELESAI', 'Line Array 10.000 Watt, genset 25 KVA, 4 wireless mic'],
    ['SND-202611-002', 6, 'Paket Audio Resepsi Pernikahan Bella & Rendy', 'MISBAHUL MUNIR', 'GSG Air Hitam', '2026-11-09', '2026-11-09', 1800000, 5500000, 'SELESAI', 'Sound indoor, monitor panggung, band akustik'],
    ['SND-202611-003', 6, 'Booking Sound Konser Musik Remaja Karang Taruna', 'MISBAHUL MUNIR', 'Stadion Mini Air Hitam', '2026-11-22', '2026-11-22', 3000000, 8000000, 'RENCANA', 'Full rig 15.000 watt, lighting moving head']
];

$stmt = $db->prepare("
    INSERT INTO division_activities (
        activity_code, division_id, activity_name, person_in_charge,
        location, start_date, end_date, total_cost, total_income,
        status, notes, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE activity_name = VALUES(activity_name)
");

foreach ($activities as $act) {
    $stmt->execute($act);
}

// Ensure at least 1-2 seed roasting orders exist
$orders = [
    ['RST-202611-001', 1, '2026-11-10', 50.0, 'MEDIUM', 41.2, 17.6, 15000, 750000, 750000, 'LUNAS', 'SELESAI', 'Kopi Robusta Petik Merah - Bpk Bambang'],
    ['RST-202611-002', 2, '2026-11-12', 30.0, 'DARK', 24.3, 19.0, 15000, 450000, 450000, 'LUNAS', 'SELESAI', 'Kopi Robusta Asalan - Kedai Kopi Kita'],
    ['RST-202611-003', 1, '2026-11-15', 25.0, 'MEDIUM', 0, 0, 15000, 375000, 0, 'BELUM_BAYAR', 'ROASTING', 'Dalam oven roasting']
];

$stmtR = $db->prepare("
    INSERT INTO roasting_orders (
        order_number, customer_id, order_date, raw_weight_kg, roast_profile,
        roasted_weight_kg, shrinkage_percent, service_fee_per_kg, total_fee,
        paid_amount, payment_status, work_status, notes, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE notes = VALUES(notes)
");

foreach ($orders as $o) {
    $stmtR->execute($o);
}

// Ensure at least 3-4 seed BRILink transactions exist
$brilinkTrx = [
    ['BRL-20261115-001', '2026-11-15 09:15:00', 1, 'TARIK_TUNAI', 'Bpk. Suwandi', '0812-3344-5566', 2000000, 10000, 3000, 2010000, 'BERHASIL', 'Tarik tunai ATM BRI'],
    ['BRL-20261115-002', '2026-11-15 10:30:00', 1, 'TRANSFER', 'Ibu Marini', '0852-7788-9900', 1500000, 7000, 2500, 1507000, 'BERHASIL', 'Transfer ke Bank Mandiri an Andi'],
    ['BRL-20261115-003', '2026-11-15 11:45:00', 2, 'BAYAR_TAGIHAN', 'Bpk. Gunawan', '0813-1122-3344', 350000, 5000, 2000, 355000, 'BERHASIL', 'Token Listrik PLN 300rb'],
    ['BRL-20261115-004', '2026-11-15 13:20:00', 1, 'SETOR_TUNAI', 'Sdr. Hendra', '0821-9988-7766', 3000000, 10000, 3000, 3010000, 'BERHASIL', 'Setor tunai ke rekening BRI']
];

$stmtB = $db->prepare("
    INSERT INTO brilink_transactions (
        reference_no, transaction_date, brilink_account_id, trx_type,
        customer_name, customer_phone, nominal_amount, admin_fee_charged,
        bank_fee, cash_received, status, operator_id, notes
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
    ON DUPLICATE KEY UPDATE notes = VALUES(notes)
");

foreach ($brilinkTrx as $b) {
    $stmtB->execute($b);
}

echo "Seeded activities, roasting orders, and brilink transactions successfully.\n";
