<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Layout Sidebar Component (Clean Dropdown Accordion)
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
<style>
/* CSS Scoped Bersih & Mewah untuk Sidebar Dropdown MBB */
.sidebar .sidebar-dropdown-toggle {
    all: unset;
    box-sizing: border-box;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    width: 100% !important;
    padding: 10px 14px !important;
    color: #cbd5e1 !important;
    background: transparent !important;
    border: 1px solid transparent !important;
    border-radius: 8px !important;
    margin-bottom: 4px !important;
    cursor: pointer !important;
    font-size: 0.88rem !important;
    font-weight: 500 !important;
    transition: all 0.2s ease !important;
}

.sidebar .sidebar-dropdown-toggle .menu-left {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
}

.sidebar .sidebar-dropdown-toggle .menu-left i {
    font-size: 1.15rem !important;
    color: #94a3b8 !important;
    transition: color 0.2s !important;
}

.sidebar .sidebar-dropdown-toggle:hover {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.08) !important;
}

.sidebar .sidebar-dropdown-toggle:hover .menu-left i {
    color: #60a5fa !important;
}

.sidebar .sidebar-dropdown-toggle.active {
    color: #38bdf8 !important;
    background: rgba(14, 165, 233, 0.12) !important;
    border: 1px solid rgba(14, 165, 233, 0.25) !important;
    font-weight: 600 !important;
}

.sidebar .sidebar-dropdown-toggle.active .menu-left i {
    color: #38bdf8 !important;
}

.sidebar .sidebar-dropdown-toggle .chevron-icon {
    font-size: 0.75rem !important;
    color: #64748b !important;
    transition: transform 0.25s ease, color 0.2s ease !important;
}

.sidebar .sidebar-dropdown-toggle:hover .chevron-icon {
    color: #94a3b8 !important;
}

.sidebar .sidebar-dropdown-toggle[aria-expanded="true"] .chevron-icon {
    transform: rotate(180deg) !important;
    color: #38bdf8 !important;
}

.sidebar .sidebar-submenu-inner {
    border-left: 2px solid rgba(255, 255, 255, 0.12) !important;
    margin-left: 22px !important;
    padding-left: 10px !important;
    padding-top: 4px !important;
    padding-bottom: 6px !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 3px !important;
}

.sidebar .nav-link-sub {
    display: flex !important;
    align-items: center !important;
    padding: 8px 12px !important;
    color: #94a3b8 !important;
    text-decoration: none !important;
    font-size: 0.83rem !important;
    font-weight: 500 !important;
    border-radius: 6px !important;
    transition: all 0.2s ease !important;
}

.sidebar .nav-link-sub i {
    font-size: 0.95rem !important;
    margin-right: 10px !important;
    color: #64748b !important;
    transition: color 0.2s !important;
}

.sidebar .nav-link-sub:hover {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.08) !important;
    text-decoration: none !important;
}

.sidebar .nav-link-sub:hover i {
    color: #38bdf8 !important;
}

.sidebar .nav-link-sub.active {
    color: #ffffff !important;
    background: linear-gradient(90deg, #0284c7 0%, #0369a1 100%) !important;
    font-weight: 600 !important;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.35) !important;
    text-decoration: none !important;
}

.sidebar .nav-link-sub.active i {
    color: #ffffff !important;
}
</style>
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
        <a href="<?= base_url('index.php') ?>" class="nav-link-mbb <?= $currentModule === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <!-- 2. ORGANISASI & SDM (DROPDOWN) -->
        <button class="sidebar-dropdown-toggle <?= $isOrgActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuOrg" aria-expanded="<?= $isOrgActive ? 'true' : 'false' ?>" aria-controls="menuOrg">
            <span class="menu-left">
                <i class="bi bi-people-fill"></i>
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
        <button class="sidebar-dropdown-toggle <?= $isBizActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuBiz" aria-expanded="<?= $isBizActive ? 'true' : 'false' ?>" aria-controls="menuBiz">
            <span class="menu-left">
                <i class="bi bi-briefcase-fill"></i>
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
        <button class="sidebar-dropdown-toggle <?= $isAdmActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuAdm" aria-expanded="<?= $isAdmActive ? 'true' : 'false' ?>" aria-controls="menuAdm">
            <span class="menu-left">
                <i class="bi bi-cash-stack"></i>
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
        <button class="sidebar-dropdown-toggle <?= $isSetActive ? 'active' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#menuSettings" aria-expanded="<?= $isSetActive ? 'true' : 'false' ?>" aria-controls="menuSettings">
            <span class="menu-left">
                <i class="bi bi-gear-fill"></i>
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
        <div class="my-3 border-top border-secondary border-opacity-25"></div>
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
