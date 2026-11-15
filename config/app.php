<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Application Configuration
 */

// Menentukan Base URL secara dinamis agar portabel
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Cari root direktori proyek
$parts = explode('/', trim($scriptName, '/'));
$baseFolder = !empty($parts[0]) ? '/' . $parts[0] . '/' : '/cvmbb/';
$baseUrl = $protocol . $host . $baseFolder;

return [
    'app_name'    => 'MBB BUSINESS MANAGEMENT SYSTEM',
    'app_version' => '1.0.0',
    'app_env'     => 'development', // 'development' or 'production'
    'base_url'    => rtrim($baseUrl, '/') . '/',
    'timezone'    => 'Asia/Jakarta',
    'session_lifetime' => 86400, // 24 jam
    'company'     => [
        'name'       => 'CV. MAJU BERKAH BERSAMA',
        'short_name' => 'MBB',
        'address'    => 'Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat',
        'logo'       => 'assets/images/logo.png',
        'tagline'    => 'Integritas, Kualitas, dan Keberkahan Usaha',
    ]
];
