<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Layout Footer Component
 */
?>
    </div><!-- End .content-body -->

    <footer class="app-footer">
        <div>
            &copy; <?= date('Y') ?> <strong><?= e(get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA')) ?></strong>. All rights reserved.
        </div>
        <div>
            <span class="badge bg-light text-secondary border">Versi 1.0.0 (Tahap 1 Core)</span>
        </div>
    </footer>
</div><!-- End .main-content -->
</div><!-- End .app-wrapper -->

<!-- BOTTOM NAVIGATION BAR KHUSUS MOBILE / APK EXPERIENCE -->
<div class="mbb-bottom-nav d-lg-none">
    <a href="<?= base_url('index.php') ?>" class="bottom-nav-item <?= ($currentModule ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2-fill"></i>
        <span>Beranda</span>
    </a>
    <a href="<?= base_url('modules/coffee-trading/index.php') ?>" class="bottom-nav-item <?= in_array($currentModule ?? '', ['coffee-trading', 'coffee-production', 'roasting-service']) ? 'active' : '' ?>">
        <i class="bi bi-cup-hot-fill"></i>
        <span>Kopi</span>
    </a>
    <a href="<?= base_url('modules/brilink/index.php') ?>" class="bottom-nav-item <?= ($currentModule ?? '') === 'brilink' ? 'active' : '' ?>">
        <i class="bi bi-credit-card-2-front-fill"></i>
        <span>BRILink</span>
    </a>
    <a href="<?= base_url('modules/finance/index.php') ?>" class="bottom-nav-item <?= ($currentModule ?? '') === 'finance' ? 'active' : '' ?>">
        <i class="bi bi-wallet2"></i>
        <span>Keuangan</span>
    </a>
    <a href="javascript:void(0)" id="mobileBottomMenuBtn" class="bottom-nav-item">
        <i class="bi bi-grid-3x3-gap-fill"></i>
        <span>Menu</span>
    </a>
</div>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- MBB App JS (with cache buster) -->
<script src="<?= asset_url('js/mbb-app.js?v=' . filemtime(__DIR__ . '/../assets/js/mbb-app.js')) ?>"></script>

<!-- Register PWA Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(function(err) {
        console.log('SW registration error:', err);
    });
}
document.addEventListener('DOMContentLoaded', function() {
    const bottomMenuBtn = document.getElementById('mobileBottomMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (bottomMenuBtn && sidebar) {
        bottomMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            if (backdrop) {
                backdrop.classList.toggle('d-none');
            }
        });
    }
});
</script>

</body>
</html>
