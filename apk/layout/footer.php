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

<!-- PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(err => console.log(err));
}
</script>

</body>
</html>
