<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Login Page
 */

require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/helpers/utils.php';

// Jika sudah login, langsung ke dashboard
if (Auth::check()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Validasi sesi keamanan (CSRF) gagal. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Silakan isi username/email dan password.';
        } else {
            $result = Auth::attempt($username, $password);
            if ($result['status']) {
                flash_set('success', 'Selamat datang di MBB Business Management System.');
                redirect('index.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$companyName = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$companyShort = get_company_setting('company_short_name', 'MBB');
$companyTagline = get_company_setting('company_tagline', 'Integritas, Kualitas, dan Keberkahan Usaha');
$companyAddress = get_company_setting('company_address', 'Jln. Raya Gunung Terang No. 09, Air Hitam, Lampung Barat');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MBB Business Management System</title>

    <!-- Favicon / Tab Icon sesuai logo MBB -->
    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        :root {
            --mbb-navy: #0d1e3a;
            --mbb-navy-dark: #071324;
            --mbb-blue: #1a56db;
            --mbb-blue-light: #e8f1fd;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #071324 0%, #0d1e3a 50%, #162f58 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #334155;
        }
        .login-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 960px;
            display: flex;
        }
        .login-brand-panel {
            background: linear-gradient(145deg, #0d1e3a 0%, #1a56db 100%);
            color: #ffffff;
            padding: 48px;
            width: 48%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .login-brand-panel::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
        }
        .login-form-panel {
            padding: 48px;
            width: 52%;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .company-logo {
            max-height: 80px;
            width: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.25));
        }
        .form-floating label {
            color: #64748b;
        }
        .form-control:focus {
            border-color: var(--mbb-blue);
            box-shadow: 0 0 0 0.25rem rgba(26, 86, 219, 0.15);
        }
        .btn-mbb-login {
            background: var(--mbb-blue);
            color: #ffffff;
            font-weight: 600;
            padding: 12px;
            border-radius: 10px;
            border: none;
            transition: all 0.2s;
        }
        .btn-mbb-login:hover {
            background: #1442a6;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(26, 86, 219, 0.35);
        }
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
            }
            .login-brand-panel, .login-form-panel {
                width: 100%;
                padding: 32px 24px;
            }
            .login-brand-panel {
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Panel Kiri: Identitas MBB -->
    <div class="login-brand-panel">
        <div>
            <img src="<?= asset_url('images/logo.png') ?>" alt="Logo CV. Maju Berkah Bersama" class="company-logo">
            <h3 class="fw-bold mb-1 text-white"><?= e($companyName) ?></h3>
            <p class="text-info small fw-medium mb-3">Enterprise Resource Planning</p>
            <p class="text-light opacity-75 small lh-base"><?= e($companyTagline) ?></p>
        </div>

        <div class="mt-4 pt-3 border-top border-white border-opacity-25 text-light opacity-75 small">
            <div class="d-flex align-items-center mb-1">
                <i class="bi bi-geo-alt me-2 text-warning"></i>
                <span><?= e($companyAddress) ?></span>
            </div>
            <div class="d-flex align-items-center">
                <i class="bi bi-shield-check me-2 text-success"></i>
                <span>Sistem Terenkripsi & Terintegrasi</span>
            </div>
        </div>
    </div>

    <!-- Panel Kanan: Form Login -->
    <div class="login-form-panel">
        <div class="mb-4">
            <h4 class="fw-bold text-dark mb-1">Selamat Datang</h4>
            <p class="text-muted small">Silakan masuk ke portal MBB Business Management System</p>
        </div>

        <?= flash_display() ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                <div><?= e($error) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('login.php') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username atau Email" required autofocus>
                <label for="username"><i class="bi bi-person me-2"></i>Username atau Email</label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password"><i class="bi bi-key me-2"></i>Kata Sandi (Password)</label>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label text-muted small" for="rememberMe">
                        Ingat saya di perangkat ini
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-mbb-login w-100 mb-3 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Masuk ke Sistem</span>
            </button>
        </form>

        <div class="text-center text-muted small mt-3">
            Butuh bantuan akses? Hubungi Tim Administrasi / IT MBB.
        </div>
        <div class="text-center mt-3 pt-2 border-top">
            <a href="<?= base_url('apk/login.php') ?>" class="text-decoration-none text-primary small fw-semibold">
                <i class="bi bi-phone-fill me-1"></i>Buka Versi MBB Mobile APK
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
