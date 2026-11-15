<?php
require_once __DIR__ . '/../core/Database.php';

$db = Database::getInstance();

// 1. Insert role 'developer'
$stmt = $db->prepare("
    INSERT INTO roles (id, role_name, role_title, description, is_system_reserved)
    VALUES (9, 'developer', 'DEVELOPER / ARCHITECT', 'Akses tertinggi teknis sistem, portal pengembang, server diagnostics, konfigurasi environment dan audit console', 1)
    ON DUPLICATE KEY UPDATE role_name = 'developer', role_title = 'DEVELOPER / ARCHITECT', description = VALUES(description);
");
$stmt->execute();

// 2. Set user 'Maul' to role_id = 9 (developer)
$stmtUser = $db->prepare("UPDATE users SET role_id = 9 WHERE username = 'Maul'");
$stmtUser->execute();

// 3. Set user 'admin' to role_id = 1 (super_admin)
$stmtAdmin = $db->prepare("UPDATE users SET role_id = 1 WHERE username = 'admin'");
$stmtAdmin->execute();

echo "Role developer created and assigned to user 'Maul'. User 'admin' remains 'super_admin'.\n";
