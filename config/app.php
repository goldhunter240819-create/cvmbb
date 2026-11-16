<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Application Configuration
 */

// 1. Deteksi Protokol (Mendukung Cloudflare Tunnel HTTPS)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
    || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// 2. Deteksi Root Folder Relatif terhadap DocumentRoot Apache
$docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
$appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));

// Cek apakah diakses via domain/subdomain publik (Cloudflare Tunnel atau custom domain)
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isDomainTunnel = ($host !== 'localhost' && !str_starts_with($host, '127.0.0.1') && !str_starts_with($host, '192.168.'));

if ($isDomainTunnel && !str_starts_with($requestUri, '/cvmbb/')) {
    $baseFolder = '/';
} elseif (!empty($docRoot) && str_starts_with($appRoot, $docRoot)) {
    $relative = trim(substr($appRoot, strlen($docRoot)), '/\\');
    $baseFolder = !empty($relative) ? '/' . $relative . '/' : '/';
} else {
    $baseFolder = '/cvmbb/';
}

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
