<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Layout Sidebar Component
 */

$currentModule = $currentModule ?? 'dashboard';
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

    <div class="sidebar-menu">
        <div class="sidebar-heading">Menu Utama</div>
        <a href="<?= base_url('index.php') ?>" class="nav-link-mbb <?= $currentModule === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-heading">Organisasi & SDM</div>
        <a href="<?= base_url('modules/organization/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'organization' ? 'active' : '' ?>">
            <i class="bi bi-diagram-3-fill"></i>
            <span>Struktur Organisasi</span>
        </a>
        <a href="<?= base_url('modules/organization/positions.php') ?>" class="nav-link-mbb <?= $currentModule === 'positions' ? 'active' : '' ?>">
            <i class="bi bi-person-badge-fill"></i>
            <span>Jabatan & Divisi</span>
        </a>
        <a href="<?= base_url('modules/employees/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'employees' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i>
            <span>Data Pegawai</span>
        </a>

        <div class="sidebar-heading">Lini Usaha MBB</div>
        <a href="<?= base_url('modules/coffee-trading/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'coffee-trading' ? 'active' : '' ?>">
            <i class="bi bi-cup-hot-fill"></i>
            <span>Coffee Trading</span>
        </a>
        <a href="<?= base_url('modules/coffee-production/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'coffee-production' ? 'active' : '' ?>">
            <i class="bi bi-gear-wide-connected"></i>
            <span>Coffee Production</span>
        </a>
        <a href="<?= base_url('modules/roasting-service/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'roasting-service' ? 'active' : '' ?>">
            <i class="bi bi-fire"></i>
            <span>Roasting Service</span>
        </a>
        <a href="<?= base_url('modules/brilink/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'brilink' ? 'active' : '' ?>">
            <i class="bi bi-credit-card-2-front-fill"></i>
            <span>Agen BRILink</span>
        </a>
        <a href="<?= base_url('modules/decoration/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'decoration' ? 'active' : '' ?>">
            <i class="bi bi-flower1"></i>
            <span>Divisi Dekoration</span>
        </a>
        <a href="<?= base_url('modules/sound-system/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'sound-system' ? 'active' : '' ?>">
            <i class="bi bi-speaker-fill"></i>
            <span>Divisi Sound Sistem</span>
        </a>

        <div class="sidebar-heading">Administrasi & Keuangan</div>
        <a href="<?= base_url('modules/administration/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'administration' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text-fill"></i>
            <span>Persuratan & Dokumen</span>
        </a>
        <a href="<?= base_url('modules/administration/activities.php') ?>" class="nav-link-mbb <?= $currentModule === 'activities' ? 'active' : '' ?>">
            <i class="bi bi-calendar2-check-fill"></i>
            <span>Agenda & Kegiatan Divisi</span>
        </a>
        <a href="<?= base_url('modules/inventory/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'inventory' ? 'active' : '' ?>">
            <i class="bi bi-boxes"></i>
            <span>Inventaris & Aset</span>
        </a>
        <a href="<?= base_url('modules/finance/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'finance' ? 'active' : '' ?>">
            <i class="bi bi-wallet2"></i>
            <span>Kas & Keuangan</span>
        </a>
        <a href="<?= base_url('modules/reports/index.php') ?>" class="nav-link-mbb <?= $currentModule === 'reports' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line-fill"></i>
            <span>Laporan Manajemen</span>
        </a>

        <?php if (Permission::can('users.manage') || Permission::can('company.manage')): ?>
        <div class="sidebar-heading">Sistem & Pengaturan</div>
        <?php if (Permission::can('company.manage')): ?>
        <a href="<?= base_url('modules/settings/company.php') ?>" class="nav-link-mbb <?= $currentModule === 'settings-company' ? 'active' : '' ?>">
            <i class="bi bi-building-fill-gear"></i>
            <span>Profil Perusahaan</span>
        </a>
        <?php endif; ?>
        <?php if (Permission::can('users.manage')): ?>
        <a href="<?= base_url('modules/settings/users.php') ?>" class="nav-link-mbb <?= $currentModule === 'settings-users' ? 'active' : '' ?>">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Pengguna & Role</span>
        </a>
        <?php endif; ?>
        <a href="<?= base_url('modules/settings/backup.php') ?>" class="nav-link-mbb <?= $currentModule === 'settings-backup' ? 'active' : '' ?>">
            <i class="bi bi-database-fill-down"></i>
            <span>Backup Database</span>
        </a>
        <?php endif; ?>

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
