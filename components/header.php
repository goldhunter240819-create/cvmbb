<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Layout Header Component
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Permission.php';
require_once __DIR__ . '/../helpers/utils.php';

Auth::requireLogin();
$currentUser = Auth::user();
$companyName = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$appTitle = ($pageTitle ?? 'Dashboard') . ' - ' . get_company_setting('app_name', 'MBB Business Management System');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appTitle) ?></title>

    <!-- Favicon / Tab Icon sesuai instruksi user -->
    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('images/logo.png') ?>">

    <!-- PWA & Mobile App Settings -->
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#0d1e3a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MBB System">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom MBB Theme CSS (with cache buster) -->
    <link rel="stylesheet" href="<?= asset_url('css/mbb-theme.css?v=' . filemtime(__DIR__ . '/../assets/css/mbb-theme.css')) ?>">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-wrapper">
