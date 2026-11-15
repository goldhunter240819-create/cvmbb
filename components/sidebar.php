<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Layout Sidebar Component (Strict Accordion Dropdown)
 */

$currentModule = $currentModule ?? 'dashboard';

$orgModules = ['organization', 'positions', 'employees'];
$bizModules = ['coffee-trading', 'coffee-production', 'roasting-service', 'brilink', 'decoration', 'sound-system'];
$admModules = ['administration', 'activities', 'inventory', 'finance', 'reports'];
$setModules = ['settings-company', 'settings-users', 'settings-backup'];

$isOrgActive = in_array($currentModule, $orgModules);
$isBizActive = in_array($currentModule, $bizModules);
$isAdmActive = in_array($currentModule, $admModules);
$isSetActive = in_array($currentModule, $setModules);
?>
<div id="sidebarBackdrop" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark opacity-50 z-3"></div>

<aside id="sidebar" class="sidebar">
    <div class="sidebar-brand">
        <img src="<?= asset_url('images/logo.png') ?>" alt="Logo MBB">
        <div class="sidebar-brand-text">
            <div class="company-name"><?= e(get_company_setting('company_short_name', 'MBB')) ?></div>
            <div class="app-sub">Management System</div>
        </div>
    </div>

    <div class="sidebar-menu" id="sidebarMenu">
        <!-- 1. DASHBOARD UTAMA -->
        <div class="sidebar-heading">Menu Utama</div>
        <a href="<?= base_url('index.php') ?>" class="nav-link-mbb <?= $currentModule === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <!-- 2. ORGANISASI & SDM (DROPDOWN) -->
        <div class="sidebar-heading">Struktur & Personalia</div>
        <button class="sidebar-dropdown-toggle <?= $isOrgActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuOrg" aria-expanded="<?= $isOrgActive ? 'true' : 'false' ?>" aria-controls="menuOrg">
            <span class="d-flex align-items-center">
                <i class="bi bi-people-fill me-2 fs-6"></i>
                <span>Organisasi & SDM</span>
            </span>
            <i class="bi bi-chevron-down chevron-icon"></i>
        </button>
        <div class="collapse sidebar-submenu-collapse <?= $isOrgActive ? 'show' : '' ?>" id="menuOrg" data-bs-parent="#sidebarMenu">
            <div class="sidebar-submenu-inner">
                <a href="<?= base_url('modules/organization/index.php') ?>" class="nav-link-sub <?= $currentModule === 'organization' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3-fill"></i>
                    <span>Struktur Organisasi</span>
                </a>
                <a href="<?= base_url('modules/organization/positions.php') ?>" class="nav-link-sub <?= $currentModule === 'positions' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge-fill"></i>
                    <span>Jabatan & Divisi</span>
                </a>
                <a href="<?= base_url('modules/employees/index.php') ?>" class="nav-link-sub <?= $currentModule === 'employees' ? 'active' : '' ?>">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Data Pegawai</span>
                </a>
            </div>
        </div>

        <!-- 3. LINI USAHA MBB (DROPDOWN) -->
        <div class="sidebar-heading">Operasional Bisnis</div>
        <button class="sidebar-dropdown-toggle <?= $isBizActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuBiz" aria-expanded="<?= $isBizActive ? 'true' : 'false' ?>" aria-controls="menuBiz">
            <span class="d-flex align-items-center">
                <i class="bi bi-briefcase-fill me-2 fs-6"></i>
                <span>Lini Usaha MBB</span>
            </span>
            <i class="bi bi-chevron-down chevron-icon"></i>
        </button>
        <div class="collapse sidebar-submenu-collapse <?= $isBizActive ? 'show' : '' ?>" id="menuBiz" data-bs-parent="#sidebarMenu">
            <div class="sidebar-submenu-inner">
                <a href="<?= base_url('modules/coffee-trading/index.php') ?>" class="nav-link-sub <?= $currentModule === 'coffee-trading' ? 'active' : '' ?>">
                    <i class="bi bi-cup-hot-fill"></i>
                    <span>Coffee Trading</span>
                </a>
                <a href="<?= base_url('modules/coffee-production/index.php') ?>" class="nav-link-sub <?= $currentModule === 'coffee-production' ? 'active' : '' ?>">
                    <i class="bi bi-gear-wide-connected"></i>
                    <span>Coffee Production</span>
                </a>
                <a href="<?= base_url('modules/roasting-service/index.php') ?>" class="nav-link-sub <?= $currentModule === 'roasting-service' ? 'active' : '' ?>">
                    <i class="bi bi-fire"></i>
                    <span>Roasting Service</span>
                </a>
                <a href="<?= base_url('modules/brilink/index.php') ?>" class="nav-link-sub <?= $currentModule === 'brilink' ? 'active' : '' ?>">
                    <i class="bi bi-credit-card-2-front-fill"></i>
                    <span>Agen BRILink</span>
                </a>
                <a href="<?= base_url('modules/decoration/index.php') ?>" class="nav-link-sub <?= $currentModule === 'decoration' ? 'active' : '' ?>">
                    <i class="bi bi-flower1"></i>
                    <span>Divisi Dekoration</span>
                </a>
                <a href="<?= base_url('modules/sound-system/index.php') ?>" class="nav-link-sub <?= $currentModule === 'sound-system' ? 'active' : '' ?>">
                    <i class="bi bi-speaker-fill"></i>
                    <span>Divisi Sound Sistem</span>
                </a>
            </div>
        </div>

        <!-- 4. ADMINISTRASI & KEUANGAN (DROPDOWN) -->
        <div class="sidebar-heading">Tata Kelola & Keuangan</div>
        <button class="sidebar-dropdown-toggle <?= $isAdmActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuAdm" aria-expanded="<?= $isAdmActive ? 'true' : 'false' ?>" aria-controls="menuAdm">
            <span class="d-flex align-items-center">
                <i class="bi bi-cash-stack me-2 fs-6"></i>
                <span>Administrasi & Keuangan</span>
            </span>
            <i class="bi bi-chevron-down chevron-icon"></i>
        </button>
        <div class="collapse sidebar-submenu-collapse <?= $isAdmActive ? 'show' : '' ?>" id="menuAdm" data-bs-parent="#sidebarMenu">
            <div class="sidebar-submenu-inner">
                <a href="<?= base_url('modules/administration/index.php') ?>" class="nav-link-sub <?= $currentModule === 'administration' ? 'active' : '' ?>">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Persuratan & Dokumen</span>
                </a>
                <a href="<?= base_url('modules/administration/activities.php') ?>" class="nav-link-sub <?= $currentModule === 'activities' ? 'active' : '' ?>">
                    <i class="bi bi-calendar2-check-fill"></i>
                    <span>Agenda & Kegiatan Divisi</span>
                </a>
                <a href="<?= base_url('modules/inventory/index.php') ?>" class="nav-link-sub <?= $currentModule === 'inventory' ? 'active' : '' ?>">
                    <i class="bi bi-boxes"></i>
                    <span>Inventaris & Aset</span>
                </a>
                <a href="<?= base_url('modules/finance/index.php') ?>" class="nav-link-sub <?= $currentModule === 'finance' ? 'active' : '' ?>">
                    <i class="bi bi-wallet2"></i>
                    <span>Kas & Keuangan</span>
                </a>
                <a href="<?= base_url('modules/reports/index.php') ?>" class="nav-link-sub <?= $currentModule === 'reports' ? 'active' : '' ?>">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Laporan Manajemen</span>
                </a>
            </div>
        </div>

        <!-- 5. SISTEM & PENGATURAN (DROPDOWN) -->
        <?php if (Permission::can('users.manage') || Permission::can('company.manage')): ?>
        <div class="sidebar-heading">Konfigurasi</div>
        <button class="sidebar-dropdown-toggle <?= $isSetActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuSettings" aria-expanded="<?= $isSetActive ? 'true' : 'false' ?>" aria-controls="menuSettings">
            <span class="d-flex align-items-center">
                <i class="bi bi-gear-fill me-2 fs-6"></i>
                <span>Sistem & Pengaturan</span>
            </span>
            <i class="bi bi-chevron-down chevron-icon"></i>
        </button>
        <div class="collapse sidebar-submenu-collapse <?= $isSetActive ? 'show' : '' ?>" id="menuSettings" data-bs-parent="#sidebarMenu">
            <div class="sidebar-submenu-inner">
                <?php if (Permission::can('company.manage')): ?>
                <a href="<?= base_url('modules/settings/company.php') ?>" class="nav-link-sub <?= $currentModule === 'settings-company' ? 'active' : '' ?>">
                    <i class="bi bi-building-fill-gear"></i>
                    <span>Profil Perusahaan</span>
                </a>
                <?php endif; ?>
                <?php if (Permission::can('users.manage')): ?>
                <a href="<?= base_url('modules/settings/users.php') ?>" class="nav-link-sub <?= $currentModule === 'settings-users' ? 'active' : '' ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Pengguna & Role</span>
                </a>
                <?php endif; ?>
                <a href="<?= base_url('modules/settings/backup.php') ?>" class="nav-link-sub <?= $currentModule === 'settings-backup' ? 'active' : '' ?>">
                    <i class="bi bi-database-fill-down"></i>
                    <span>Backup Database</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 6. DEVELOPER SPACE (KHUSUS MAUL) -->
        <?php if (Permission::isDeveloper()): ?>
        <div class="sidebar-heading text-info pt-3">Developer Space</div>
        <a href="<?= base_url('modules/developer/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'developer' ? 'active' : '' ?>" style="background: rgba(6, 182, 212, 0.12); border-left: 3px solid #06b6d4; border-radius: 4px;">
            <i class="bi bi-terminal-fill text-info"></i>
            <span class="text-white fw-bold">Portal Developer</span>
            <span class="badge bg-info text-dark ms-auto" style="font-size: 0.65rem;">MAUL</span>
        </a>
        <?php endif; ?>

        <div class="mt-4 mb-2 px-2">
            <a href="<?= base_url('logout.php') ?>" class="btn btn-sm btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-right"></i>
                <span>Keluar Sistem</span>
            </a>
        </div>
    </div>
</aside>
