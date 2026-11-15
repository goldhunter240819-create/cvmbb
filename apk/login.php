<?php
/**
 * MBB MOBILE APK - Halaman Login Khusus Aplikasi Mobile
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../helpers/utils.php';

// Jika sudah login, langsung ke beranda mobile
if (Auth::check()) {
    redirect('apk/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = 'Validasi sesi keamanan (CSRF) kedaluwarsa. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Silakan masukkan username/email dan kata sandi Anda.';
        } else {
            $result = Auth::attempt($username, $password);
            if ($result['status']) {
                flash_set('success', 'Selamat datang di Aplikasi Mobile MBB!');
                redirect('apk/index.php');
            } else {
                $error = $result['message'];
            }
        }
    }
}

$companyName  = get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA');
$companyShort = get_company_setting('company_short_name', 'MBB');
$companyAddress = get_company_setting('company_address', 'Air Hitam, Lampung Barat');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Masuk Aplikasi - <?= e($companyShort) ?> Mobile</title>

    <link rel="icon" type="image/png" href="<?= asset_url('images/logo.png') ?>">
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#071224">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Fonts & Bootstrap -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --mbb-navy: #071224;
            --mbb-blue: #1a56db;
            --mbb-accent: #0ea5e9;
            --mbb-bg: #f8fafc;
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

        /* Container Khusus Mobile */
        .mobile-login-container {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            min-height: 100vh;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            box-shadow: 0 0 35px rgba(15, 23, 42, 0.08);
        }

        /* Hero Header Bernuansa Aplikasi Modern */
        .mobile-login-header {
            background: linear-gradient(180deg, #071224 0%, #0d1e3a 70%, #173663 100%);
            color: #ffffff;
            padding: 44px 24px 60px 24px;
            border-bottom-left-radius: 36px;
            border-bottom-right-radius: 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .header-decor-circle {
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18) 0%, rgba(14, 165, 233, 0) 70%);
            top: -60px;
            right: -60px;
            pointer-events: none;
        }
        .logo-emblem {
            width: 76px;
            height: 76px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px auto;
            border: 2px solid rgba(255, 255, 255, 0.22);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
        }
        .logo-emblem img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        /* Form Card */
        .mobile-login-card {
            margin: -36px 20px 20px 20px;
            background: #ffffff;
            border-radius: 24px;
            padding: 26px 22px;
            box-shadow: 0 10px 30px rgba(13, 30, 58, 0.09);
            border: 1px solid #f1f5f9;
            position: relative;
            z-index: 10;
        }

        /* Input Controls */
        .mobile-input-group {
            position: relative;
            margin-bottom: 16px;
        }
        .mobile-input-group i.input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.15rem;
            z-index: 5;
        }
        .mobile-form-control {
            width: 100%;
            height: 50px;
            background-color: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 10px 16px 10px 48px;
            font-size: 0.92rem;
            color: #1e293b;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .mobile-form-control:focus {
            background-color: #ffffff;
            border-color: var(--mbb-blue);
            outline: none;
            box-shadow: 0 0 0 4px rgba(26, 86, 219, 0.12);
        }
        .toggle-password-btn {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.1rem;
            cursor: pointer;
            z-index: 5;
            padding: 4px;
        }

        /* Tombol Masuk Mobile */
        .btn-mobile-login {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #1a56db 0%, #0ea5e9 100%);
            border: none;
            border-radius: 14px;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 22px rgba(26, 86, 219, 0.35);
            transition: all 0.2s ease;
        }
        .btn-mobile-login:active {
            transform: scale(0.97);
            box-shadow: 0 4px 12px rgba(26, 86, 219, 0.25);
        }

        /* Biometric Button */
        .btn-biometric {
            width: 100%;
            height: 46px;
            background-color: #f8fafc;
            border: 1.5px dashed #cbd5e1;
            border-radius: 14px;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
            transition: all 0.15s ease;
        }
        .btn-biometric:hover, .btn-biometric:active {
            background-color: #eff6ff;
            border-color: #1a56db;
            color: #1a56db;
        }

        /* Footer Info */
        .mobile-login-footer {
            padding: 20px;
            text-align: center;
            font-size: 0.78rem;
            color: #64748b;
        }
        .badge-app-ver {
            background: #eff6ff;
            color: #1a56db;
            font-size: 0.68rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>

<div class="mobile-login-container">
    <!-- ATAS: HERO HEADER -->
    <div>
        <div class="mobile-login-header">
            <div class="header-decor-circle"></div>
            <div class="logo-emblem">
                <img src="<?= asset_url('images/logo.png') ?>" alt="Logo MBB">
            </div>
            <h4 class="fw-bold mb-1 text-white tracking-wide"><?= e($companyShort) ?> MOBILE</h4>
            <div class="text-white-50 small mb-2" style="font-size: 0.78rem;"><?= e($companyName) ?></div>
            <span class="badge bg-white bg-opacity-20 text-white rounded-pill px-3 py-1 font-monospace" style="font-size: 0.68rem;">
                <i class="bi bi-shield-check me-1"></i>PORTAL OPERASIONAL LAPANGAN
            </span>
        </div>

        <!-- TENGAH: CARD FORM LOGIN -->
        <div class="mobile-login-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-dark mb-0" style="font-size: 0.98rem;">Masuk ke Aplikasi</h6>
                <span class="badge-app-ver mb-0">v1.0.0 APK</span>
            </div>

            <!-- Pesan Error / Notifikasi Flash -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-6 text-danger"></i>
                    <div><?= e($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (function_exists('flash_has') && flash_has()): ?>
                <div class="mb-3">
                    <?= flash_display() ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" autocomplete="off">
                <?= csrf_field() ?>

                <!-- Input Username -->
                <div class="mobile-input-group">
                    <i class="bi bi-person-fill input-icon"></i>
                    <input type="text" name="username" class="mobile-form-control" placeholder="Username atau Email" required autofocus>
                </div>

                <!-- Input Password -->
                <div class="mobile-input-group">
                    <i class="bi bi-lock-fill input-icon"></i>
                    <input type="password" name="password" id="apkPasswordInput" class="mobile-form-control" placeholder="Kata Sandi Akun" required>
                    <button type="button" class="toggle-password-btn" id="toggleApkPassBtn" title="Lihat Sandi">
                        <i class="bi bi-eye-slash" id="toggleApkPassIcon"></i>
                    </button>
                </div>

                <!-- Pilihan Remember Me & Bantuan -->
                <div class="d-flex justify-content-between align-items-center mb-3" style="font-size: 0.78rem;">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMeCheck" checked>
                        <label class="form-check-label text-muted" for="rememberMeCheck">
                            Ingat sesi saya
                        </label>
                    </div>
                    <a href="https://wa.me/6281210000003?text=Halo%20Admin%20MBB,%20saya%20butuh%20bantuan%20login%20Aplikasi%20Mobile%20MBB" target="_blank" class="text-decoration-none text-primary fw-semibold">
                        Bantuan IT?
                    </a>
                </div>

                <!-- Tombol Submit Masuk -->
                <button type="submit" class="btn-mobile-login">
                    <span>Masuk ke Aplikasi</span>
                    <i class="bi bi-arrow-right-circle-fill ms-2 fs-5"></i>
                </button>

                <!-- Tombol Shortcut Sidik Jari (Biometrik Similasi) -->
                <button type="button" class="btn-biometric" id="bioBtn">
                    <i class="bi bi-fingerprint text-primary fs-5"></i>
                    <span>Masuk Cepat Biometrik</span>
                </button>
            </form>
        </div>
    </div>

    <!-- BAWAH: FOOTER -->
    <div class="mobile-login-footer">
        <div class="text-muted small" style="font-size: 0.72rem;">
            <?= e($companyName) ?><br>
            <?= e($companyAddress) ?>
        </div>
    </div>
</div>

<!-- Modal Dialog Biometrik Interaktif -->
<div class="modal fade" id="modalBio" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-4 text-center shadow">
            <div class="mb-3">
                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center p-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-fingerprint display-5"></i>
                </div>
            </div>
            <h6 class="fw-bold mb-1">Sensor Sidik Jari Siaga</h6>
            <p class="text-muted small mb-3">Silakan gunakan username dan password Anda terlebih dahulu untuk mengaktifkan token perangkat ini.</p>
            <button type="button" class="btn btn-sm btn-primary rounded-pill px-4" data-bs-dismiss="modal">Mengerti</button>
        </div>
    </div>
</div>

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Toggle Show/Hide Password
const passInput = document.getElementById('apkPasswordInput');
const toggleBtn = document.getElementById('toggleApkPassBtn');
const toggleIcon = document.getElementById('toggleApkPassIcon');

if (toggleBtn && passInput && toggleIcon) {
    toggleBtn.addEventListener('click', function() {
        if (passInput.type === 'password') {
            passInput.type = 'text';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        } else {
            passInput.type = 'password';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        }
    });
}

// Biometric Prompt
const bioBtn = document.getElementById('bioBtn');
if (bioBtn) {
    bioBtn.addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('modalBio'));
        modal.show();
    });
}
</script>

</body>
</html>
