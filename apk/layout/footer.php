<?php
/**
 * MBB MOBILE APK - Layout Footer
 * CV. MAJU BERKAH BERSAMA
 */
$currentApkNav = $currentApkNav ?? 'home';
$localIp = gethostbyname(gethostname());
?>
    </div><!-- End .app-content-body -->

    <!-- FIXED BOTTOM NAVIGATION BAR -->
    <div class="mobile-bottom-bar">
        <a href="<?= base_url('apk/index.php') ?>" class="nav-btn <?= $currentApkNav === 'home' ? 'active' : '' ?>">
            <i class="bi bi-house-door-fill"></i>
            <span>Beranda</span>
        </a>
        <a href="<?= base_url('apk/coffee_sell.php') ?>" class="nav-btn <?= $currentApkNav === 'coffee' ? 'active' : '' ?>">
            <i class="bi bi-cup-hot-fill"></i>
            <span>Kopi</span>
        </a>
        <a href="<?= base_url('apk/brilink.php') ?>" class="nav-btn <?= $currentApkNav === 'brilink' ? 'active' : '' ?>">
            <i class="bi bi-credit-card-2-front-fill"></i>
            <span>BRILink</span>
        </a>
        <a href="<?= base_url('apk/finance.php') ?>" class="nav-btn <?= $currentApkNav === 'finance' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i>
            <span>Keuangan</span>
        </a>
        <a href="<?= base_url('apk/profile.php') ?>" class="nav-btn <?= $currentApkNav === 'profile' ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>Profil</span>
        </a>
    </div>

</div><!-- End .app-container -->

<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 11 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- html2canvas for Generating Image Receipts ala GoPay / E-Wallet -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<!-- QRCode Generator for Digital Receipt Validation -->
<script src="<?= base_url('assets/js/qrcode.min.js') ?>"></script>
<script>
if (typeof QRCode === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>');
}
</script>

<style>
/* Modern SweetAlert2 UI Styling ala Mobile iOS / E-Wallet */
.swal2-popup {
    font-family: 'Plus Jakarta Sans', sans-serif !important;
    border-radius: 1.25rem !important;
    padding: 1.5rem 1.25rem !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}
.swal2-title {
    font-size: 1.12rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    padding-top: 0.25rem !important;
}
.swal2-html-container {
    font-size: 0.84rem !important;
    color: #64748b !important;
    margin-top: 0.4rem !important;
    line-height: 1.4 !important;
}
.swal2-actions {
    margin-top: 1.25rem !important;
    gap: 0.5rem !important;
    width: 100% !important;
    justify-content: center !important;
}
.swal2-confirm, .swal2-cancel {
    border-radius: 50rem !important;
    font-size: 0.82rem !important;
    font-weight: 600 !important;
    padding: 0.55rem 1.4rem !important;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
}
.swal2-icon {
    transform: scale(0.85);
    margin: 0.4rem auto 0.6rem !important;
}
</style>

<script>
// Global SweetAlert2 Anti-Shift (Mencegah Layar Bergeser/Melompat Saat Popup Terbuka)
if (typeof Swal !== 'undefined') {
    const _origFire = Swal.fire;
    Swal.fire = function (...args) {
        if (typeof args[0] === 'object' && args[0] !== null) {
            args[0].scrollbarPadding = false;
            args[0].heightAuto = false;
            if (!args[0].customClass) {
                args[0].customClass = {
                    popup: 'rounded-4 shadow-lg border-0'
                };
            }
        }
        return _origFire.apply(this, args);
    };

    // Override alert bawaan browser agar otomatis tampil sebagai SweetAlert2 keren & modern
    const _origAlert = window.alert;
    window.alert = function (message) {
        Swal.fire({
            title: 'Perhatian',
            text: message,
            icon: 'warning',
            confirmButtonText: 'Oke, Mengerti',
            confirmButtonColor: '#059669',
            buttonsStyling: true
        });
    };
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. Intercept Logout dengan SweetAlert2 Modern
    document.querySelectorAll('.btn-logout-confirm, a[href*="logout.php"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetUrl = this.getAttribute('href');
            Swal.fire({
                title: 'Konfirmasi Keluar',
                text: 'Apakah Anda yakin ingin keluar dari aplikasi MBB Mobile?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Ya, Keluar',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'rounded-4 shadow-lg border-0'
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    window.location.href = targetUrl;
                }
            });
        });
    });

    // 2. Intercept Confirm Delete dengan SweetAlert2
    document.querySelectorAll('.btn-confirm-delete, [data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';
            
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Ya, Hapus',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                focusCancel: true,
                customClass: {
                    popup: 'rounded-4 shadow-lg border-0'
                }
            }).then(function (result) {
                if (result.isConfirmed) {
                    if (form) {
                        form.submit();
                    } else if (button.tagName === 'A' && button.getAttribute('href')) {
                        window.location.href = button.getAttribute('href');
                    }
                }
            });
        });
    });

    // 3. PWA Install Prompt Handler
    let deferredPWA = null;
    const pwaButtons = document.querySelectorAll('.btn-pwa-install');

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPWA = e;
        pwaButtons.forEach(btn => btn.classList.remove('d-none'));
    });

    pwaButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (deferredPWA) {
                deferredPWA.prompt();
                deferredPWA.userChoice.then(function (choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        Swal.fire({
                            title: 'Sedang Memasang...',
                            text: 'Aplikasi MBB Mobile sedang ditambahkan ke layar utama HP Anda.',
                            icon: 'success',
                            confirmButtonColor: '#1a56db'
                        });
                    }
                    deferredPWA = null;
                });
            } else {
                const isIOS = /iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase());
                if (isIOS) {
                    Swal.fire({
                        title: 'Pasang di iPhone / iPad',
                        html: '<div class="text-start small text-muted"><p class="mb-2">1. Ketuk ikon <strong>Bagikan / Share</strong> (kotak tanda panah ke atas) di menu Safari bawah.</p><p class="mb-2">2. Gulir ke bawah lalu pilih <strong>"Tambahkan ke Layar Utama" (Add to Home Screen)</strong>.</p><p class="mb-0">3. Ketuk <strong>Tambah</strong> di pojok kanan atas.</p></div>',
                        icon: 'info',
                        confirmButtonText: 'Mengerti',
                        confirmButtonColor: '#1a56db'
                    });
                } else {
                    Swal.fire({
                        title: 'Instal Aplikasi MBB',
                        html: '<p class="small text-muted mb-0">Ketuk ikon menu titik tiga (⋮) di pojok kanan atas browser Anda, lalu pilih <strong>"Instal aplikasi"</strong> atau <strong>"Tambahkan ke Layar Utama"</strong>.</p>',
                        icon: 'info',
                        confirmButtonText: 'Siap',
                        confirmButtonColor: '#1a56db'
                    });
                }
            }
        });
    });

    window.addEventListener('appinstalled', function () {
        Swal.fire({
            title: 'Berhasil Terpasang!',
            text: 'Aplikasi MBB Mobile kini sudah aktif di layar utama HP Anda!',
            icon: 'success',
            confirmButtonColor: '#1a56db'
        });
    });

    // 4. Flash Messages sebagai Floating SweetAlert2 Toast (Tanpa Menggeser Layout Halaman)
    <?php if (function_exists('flash_has') && flash_has()): ?>
        <?php
        $flashes = $_SESSION['flash_messages'] ?? [];
        $_SESSION['flash_messages'] = [];
        ?>
        const flashes = <?= json_encode($flashes) ?>;
        flashes.forEach(function (f) {
            Swal.fire({
                toast: true,
                position: 'top',
                icon: f.type === 'danger' ? 'error' : (f.type === 'warning' ? 'warning' : 'success'),
                title: f.message,
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                customClass: {
                    popup: 'rounded-4 shadow-lg py-2 px-3 border-0'
                }
            });
        });
    <?php endif; ?>
});

// PWA Service Worker Registration
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(err => console.log(err));
}
</script>

</body>
</html>
