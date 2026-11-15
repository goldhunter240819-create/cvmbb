<?php
/**
 * MBB MOBILE APK - Layout Header
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin('apk/login.php');
$user = Auth::user();
$db = Database::getInstance();
$companyShort = get_company_setting('company_short_name', 'MBB');
$companyName  = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');

$mobilePageTitle = $mobilePageTitle ?? 'MBB Mobile';
$mobileBackUrl   = $mobileBackUrl ?? base_url('apk/index.php');
$isHome          = $isHome ?? false;
$localIp         = gethostbyname(gethostname());
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= e($mobilePageTitle) ?> - <?= e($companyShort) ?> Mobile</title>

    <link rel="icon" type="image/png" href="<?= asset_url('images/logoapk.png?v=' . filemtime(__DIR__ . '/../../assets/images/logoapk.png')) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= asset_url('images/logoapk.png?v=' . filemtime(__DIR__ . '/../../assets/images/logoapk.png')) ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('images/logoapk.png?v=' . filemtime(__DIR__ . '/../../assets/images/logoapk.png')) ?>">
    <link rel="manifest" href="<?= base_url('apk/manifest.json?v=' . filemtime(__DIR__ . '/../manifest.json')) ?>">
    <meta name="theme-color" content="#0d1e3a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --mbb-navy: #0d1e3a;
            --mbb-blue: #1a56db;
            --mbb-accent: #0ea5e9;
            --mbb-bg: #f8fafc;
            --mbb-card: #ffffff;
            --mbb-border: #e2e8f0;
        }
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #1e293b;
            min-height: 100vh;
        }

        /* Anti Page Shift / Jumping pada Popups & Modals */
        html.swal2-shown,
        body.swal2-shown,
        body.modal-open {
            overflow-y: scroll !important;
            padding-right: 0 !important;
        }
        .swal2-container {
            overflow-y: auto !important;
        }

        /* Mobile Typography Fine-Tuning (Compact & Anti-Clipping) */
        body {
            font-size: 0.82rem !important;
            line-height: 1.38;
        }
        h1, .h1 { font-size: 1.3rem !important; }
        h2, .h2 { font-size: 1.15rem !important; }
        h3, .h3 { font-size: 1.05rem !important; }
        h4, .h4 { font-size: 0.95rem !important; }
        h5, .h5 { font-size: 0.88rem !important; }
        h6, .h6 { font-size: 0.80rem !important; }

        .app-top-navbar h5 {
            font-size: 0.90rem !important;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .form-control, .form-select {
            font-size: 0.82rem !important;
            padding: 7px 11px !important;
            border-radius: 10px !important;
        }
        .form-label {
            font-size: 0.75rem !important;
            margin-bottom: 3px !important;
            font-weight: 600;
        }
        .btn {
            font-size: 0.82rem !important;
            padding: 7px 12px !important;
            border-radius: 10px !important;
        }
        .btn-sm {
            font-size: 0.74rem !important;
            padding: 4px 8px !important;
            border-radius: 7px !important;
        }
        .badge {
            font-size: 0.65rem !important;
            font-weight: 600 !important;
            padding: 3px 6px !important;
        }
        .table {
            font-size: 0.76rem !important;
        }
        .table th, .table td {
            padding: 6px 8px !important;
        }
        .mobile-card {
            padding: 14px 16px !important;
            border-radius: 14px !important;
        }
        .card-title {
            font-size: 0.88rem !important;
        }

        /* Container Aplikasi Fleksibel & Rapi (Menyesuaikan Layar HP / Desktop Widescreen) */
        .app-container {
            width: 100%;
            max-width: 820px;
            margin: 0 auto;
            min-height: 100vh;
            background: #ffffff;
            position: relative;
            box-shadow: 0 0 35px rgba(15, 23, 42, 0.08);
            display: flex;
            flex-direction: column;
        }

        /* Header Sub-Halaman Rapi */
        .app-top-navbar {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #0d1e3a 0%, #152c52 100%);
            color: #ffffff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1020;
            box-shadow: 0 4px 14px rgba(13, 30, 58, 0.15);
        }
        .nav-back-btn {
            color: #ffffff;
            font-size: 1.15rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.14);
            transition: all 0.15s ease;
        }
        .nav-back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            transform: translateX(-2px);
        }

        /* Area Isi Konten */
        .app-content-body {
            flex: 1;
            background-color: #f8fafc;
            padding-bottom: 90px; /* Jarak aman di atas bottom bar */
        }

        /* Mobile Card & Container */
        .mobile-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 16px 20px;
            margin: 0 16px 14px 16px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
            border: 1px solid #e2e8f0;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .mobile-card:hover {
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07);
        }

        /* Floating Action Button (FAB) */
        .mobile-fab {
            position: fixed;
            bottom: 80px;
            right: max(20px, calc(50% - 390px));
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a56db 0%, #0ea5e9 100%);
            color: #ffffff !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 8px 22px rgba(26, 86, 219, 0.38);
            z-index: 1050;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .mobile-fab:hover {
            transform: scale(1.08);
            box-shadow: 0 10px 26px rgba(26, 86, 219, 0.48);
            color: #ffffff;
        }
        .mobile-fab:active {
            transform: scale(0.94);
        }

        /* Bottom Navigation Bar Tetap di Bawah */
        .mobile-bottom-bar {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 820px;
            height: 64px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0 12px;
            z-index: 1040;
            box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.06);
        }
        .nav-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 12px;
            transition: all 0.15s ease;
        }
        .nav-btn i {
            font-size: 1.35rem;
            line-height: 1;
            margin-bottom: 2px;
        }
        .nav-btn.active {
            color: #1a56db;
            background: #eff6ff;
        }
        .nav-btn:hover {
            color: #1a56db;
        }
        .nav-btn:active {
            transform: scale(0.92);
        }

        /* Di HP / Layar Sempit */
        @media (max-width: 820px) {
            .app-container {
                box-shadow: none;
            }
            .mobile-fab {
                right: 18px;
            }
        }
    </style>
</head>
<body>

<div class="app-container">
    <?php if (!$isHome): ?>
        <!-- SUB-PAGE TOP NAVBAR -->
        <div class="app-top-navbar">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= e($mobileBackUrl) ?>" class="nav-back-btn" title="Kembali">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <div class="fw-bold text-truncate" style="font-size: 1rem; letter-spacing: -0.2px;">
                        <?= e($mobilePageTitle) ?>
                    </div>
                    <div class="text-white-50 small" style="font-size: 0.68rem; line-height: 1;">
                        <?= e($companyShort) ?> Field System
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (isset($mobileHeaderAction)): ?>
                    <?= $mobileHeaderAction ?>
                <?php else: ?>
                    <a href="<?= base_url('apk/index.php') ?>" class="btn btn-sm btn-outline-light rounded-circle p-0" style="width: 34px; height: 34px; display:flex; align-items:center; justify-content:center;" title="Beranda">
                        <i class="bi bi-house-door"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ISI KONTEN APLIKASI -->
    <div class="app-content-body">
