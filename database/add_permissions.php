<?php
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

$newPerms = [
    ['decoration.view', 'decoration', 'Lihat Divisi Dekoration', 'Melihat jadwal dan agenda job dekorasi'],
    ['decoration.manage', 'decoration', 'Kelola Divisi Dekoration', 'Mencatat, mengedit, dan menghapus job dekorasi'],
    ['sound_system.view', 'sound-system', 'Lihat Divisi Sound Sistem', 'Melihat persewaan sound system'],
    ['sound_system.manage', 'sound-system', 'Kelola Divisi Sound Sistem', 'Mencatat, mengedit, dan menghapus persewaan sound system'],
    ['reports.view', 'reports', 'Lihat Laporan Manajemen', 'Melihat laporan konsolidasi dan neraca laba rugi'],
];

$stmt = $db->prepare("
    INSERT INTO permissions (permission_key, module, name, description)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)
");

foreach ($newPerms as $p) {
    $stmt->execute($p);
}

echo "Added extra permissions successfully.\n";
