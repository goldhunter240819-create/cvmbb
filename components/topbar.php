<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Layout Topbar & Page Hero Banner Component
 * CV. MAJU BERKAH BERSAMA
 */

$user = Auth::user();
$displayName = $user['full_name'] ?? ($user['username'] ?? 'User');
$displayRole = $user['role_title'] ?? ($user['role_name'] ?? 'User');
$displayPos  = $user['position_name'] ?? $displayRole;
$initials    = strtoupper(substr($displayName, 0, 2));

// -------------------------------------------------------------
// METADATA BANNER OTOMATIS BERDASARKAN MODUL AKTIF
// -------------------------------------------------------------
$moduleMetadata = [
    'dashboard' => [
        'title'    => 'Dashboard Eksekutif',
        'category' => 'MANAJEMEN & EKSEKUTIF PERUSAHAAN',
        'icon'     => 'bi-grid-1x2-fill',
        'desc'     => 'Pusat pantauan terpadu performa omzet finansial, persediaan barang, serta operasional seluruh lini usaha CV. MBB.'
    ],
    'organization' => [
        'title'    => 'Struktur Organisasi',
        'category' => 'TATA KELOLA & STRUKTURAL',
        'icon'     => 'bi-diagram-3-fill',
        'desc'     => 'Bagan hierarki resmi dewan komisaris, direksi, pimpinan eksekutif, dan penanggung jawab divisi operasional.'
    ],
    'positions' => [
        'title'    => 'Master Jabatan & Divisi',
        'category' => 'TATA KELOLA & STRUKTURAL',
        'icon'     => 'bi-person-badge-fill',
        'desc'     => 'Pengaturan posisi struktural, jenjang level kepemimpinan, dan master unit divisi perusahaan secara dinamis.'
    ],
    'employees' => [
        'title'    => 'Data Pegawai & Anggota',
        'category' => 'SUMBER DAYA MANUSIA (SDM)',
        'icon'     => 'bi-people-fill',
        'desc'     => 'Kelola data personil, status kepegawaian, penempatan divisi, biodata, dan keterhubungan akun login sistem.'
    ],
    'coffee-trading' => [
        'title'    => 'Coffee Trading',
        'category' => 'PERDAGANGAN BIJI KOPI',
        'icon'     => 'bi-cup-hot-fill',
        'desc'     => 'Pengelolaan transaksi pembelian biji kopi dari petani & supplier, penimbangan, kadar air, dan penjualan kopi.'
    ],
    'coffee-production' => [
        'title'    => 'Coffee Production',
        'category' => 'MANUFAKTUR & PENGOLAHAN',
        'icon'     => 'bi-gear-wide-connected',
        'desc'     => 'Pengolahan biji kopi internal, proses roasting mesin, penggilingan (grinding), susut berat, kemasan, dan kalkulasi HPP.'
    ],
    'roasting-service' => [
        'title'    => 'Roasting Service (Maklon)',
        'category' => 'JASA SANGRAI KOPI KONSUMEN',
        'icon'     => 'bi-fire',
        'desc'     => 'Penerimaan kopi mentah pelanggan, pemilihan profil sangrai (light/medium/dark), penimbangan susut, dan invoice jasa.'
    ],
    'brilink' => [
        'title'    => 'Administrasi Agen BRILink',
        'category' => 'KEUANGAN & PERBANKAN MIKRO',
        'icon'     => 'bi-credit-card-2-front-fill',
        'desc'     => 'Pencatatan transaksi transfer, tarik/setor tunai, fee layanan perusahaan, dan rekonsiliasi kas operasional harian.'
    ],
    'decoration' => [
        'title'    => 'Divisi Dekoration',
        'category' => 'DIVISI DEKORASI (PJ: FATKHUR ROHMAN)',
        'icon'     => 'bi-flower1',
        'desc'     => 'Penyelenggaraan jasa dekorasi acara & pesta, sewa modul perlengkapan, biaya pelaksanaan, dan dokumentasi proyek.'
    ],
    'sound-system' => [
        'title'    => 'Divisi Sound Sistem',
        'category' => 'DIVISI TATA SUARA (PJ: MISBAHUL MUNIR)',
        'icon'     => 'bi-speaker-fill',
        'desc'     => 'Operasional audio sound system, persewaan alat tata suara lapangan, teknisi akustik, dan rekapitulasi job.'
    ],
    'administration' => [
        'title'    => 'Administrasi & Persuratan',
        'category' => 'TATA USAHA & PERSURATAN (PJ: NURIL HUDA)',
        'icon'     => 'bi-file-earmark-text-fill',
        'desc'     => 'Pengarsipan surat masuk, penomoran otomatis surat keluar, dan manajemen berkas digital legalitas perusahaan.'
    ],
    'inventory' => [
        'title'    => 'Inventaris & Aset Perusahaan',
        'category' => 'MANAJEMEN ASET & LOGISTIK',
        'icon'     => 'bi-boxes',
        'desc'     => 'Pendataan aset mesin, sound system, peralatan dekorasi, kondisi barang, lokasi penyimpanan, dan mutasi pemakaian.'
    ],
    'finance' => [
        'title'    => 'Kas & Keuangan Terpadu',
        'category' => 'SISTEM KEUANGAN PERUSAHAAN',
        'icon'     => 'bi-wallet2',
        'desc'     => 'Pengelolaan multi-kas perusahaan, penerimaan, beban operasional, monitoring piutang pelanggan, dan utang dagang.'
    ],
    'reports' => [
        'title'    => 'Laporan Manajemen Konsolidasi',
        'category' => 'PELAPORAN & ANALITIK EKSEKUTIF',
        'icon'     => 'bi-bar-chart-line-fill',
        'desc'     => 'Laporan kinerja laba rugi konsolidasi, neraca, arus kas, dan ekspor laporan berkop resmi MBB format PDF & Excel.'
    ],
    'settings-company' => [
        'title'    => 'Profil Perusahaan',
        'category' => 'PENGATURAN IDENTITAS INSTANSI',
        'icon'     => 'bi-building-fill-gear',
        'desc'     => 'Kelola nama resmi perusahaan, alamat kantor pusat, nomor kontak WhatsApp, logo, dan penandatangan surat.'
    ],
    'settings-users' => [
        'title'    => 'Manajemen Pengguna & RBAC',
        'category' => 'KEAMANAN & AKSES SISTEM',
        'icon'     => 'bi-shield-lock-fill',
        'desc'     => 'Pengelolaan akun login karyawan, penetapan peran (role), reset password, dan pemantauan jejak audit log.'
    ],
    'settings-backup' => [
        'title'    => 'Backup & Pemeliharaan Database',
        'category' => 'PEMELIHARAAN SISTEM',
        'icon'     => 'bi-database-fill-down',
        'desc'     => 'Pencadangan database mbb_management secara mandiri, aman, utuh, dan siap diunduh sewaktu-waktu.'
    ],
    'settings-password' => [
        'title'    => 'Ganti Kata Sandi',
        'category' => 'PENGATURAN AKUN PRIBADI',
        'icon'     => 'bi-key-fill',
        'desc'     => 'Perbarui kata sandi akun Anda secara berkala untuk menjaga keamanan akses portal sistem.'
    ],
    'developer' => [
        'title'    => 'Portal Developer & System Architect',
        'category' => 'DIAGNOSTIK & KONTROL TEKNIS SISTEM',
        'icon'     => 'bi-terminal-fill',
        'desc'     => 'Panel kontrol pengembang: spesifikasi runtime, inspeksi database InnoDB, SQL sandbox, dan pemantauan audit log.'
    ]
];

$activeMeta = $moduleMetadata[$currentModule ?? 'dashboard'] ?? [
    'title'    => $pageTitle ?? 'MBB Management System',
    'category' => 'SISTEM INFORMASI TERPADU',
    'icon'     => 'bi-grid-fill',
    'desc'     => 'Sistem manajemen operasional terpadu CV. MAJU BERKAH BERSAMA'
];

$bannerTitle    = $pageTitle ?? $activeMeta['title'];
$bannerCategory = $pageCategory ?? $activeMeta['category'];
$bannerIcon     = $pageIcon ?? $activeMeta['icon'];
$bannerDesc     = $pageDesc ?? $activeMeta['desc'];
?>
<div class="main-content">
    <!-- TOPBAR KONTROL -->
    <header class="topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="btn btn-sm btn-outline-secondary d-lg-none" type="button" aria-label="Toggle Navigation">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                <i class="bi <?= e($bannerIcon) ?> text-primary fs-5"></i>
                <span class="fw-bold text-dark fs-6"><?= e($bannerTitle) ?></span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-none d-md-inline-block small">CV. MBB</span>
            </div>
        </div>

        <div class="topbar-right">
            <?php if (Permission::isDeveloper()): ?>
                <a href="<?= base_url('modules/developer/index.php') ?>" class="btn btn-sm btn-dark text-info border border-info border-opacity-50 d-flex align-items-center gap-1 font-monospace" style="font-size: 0.78rem;">
                    <i class="bi bi-terminal-fill text-info"></i>
                    <span>DEV PORTAL</span>
                </a>
            <?php endif; ?>

            <!-- Informasi Waktu Real-Time -->
            <div class="d-none d-md-flex align-items-center text-muted px-2 py-1 rounded bg-light border" style="font-size: 0.8rem;">
                <i class="bi bi-calendar3 me-1 text-primary"></i>
                <span><?= format_tanggal(date('Y-m-d')) ?></span>
            </div>

            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <div class="user-profile-badge" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/../' . $user['avatar'])): ?>
                        <img src="<?= base_url($user['avatar']) ?>?v=<?= time() ?>" alt="Avatar" class="user-avatar" style="object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <div class="user-avatar"><?= e($initials) ?></div>
                    <?php endif; ?>
                    <div class="d-none d-sm-block text-start lh-1">
                        <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= e($displayName) ?></div>
                        <small class="text-muted" style="font-size: 0.72rem;"><?= e($displayPos) ?></small>
                    </div>
                    <i class="bi bi-chevron-down text-muted" style="font-size: 0.75rem;"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2" style="min-width: 230px;">
                    <li class="px-3 py-2 border-bottom mb-1">
                        <div class="fw-bold text-dark"><?= e($displayName) ?></div>
                        <div class="text-muted small"><?= e($user['email'] ?? '') ?></div>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1"><?= e($displayRole) ?></span>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= base_url('modules/settings/company.php') ?>">
                            <i class="bi bi-building me-2 text-primary"></i>Profil Perusahaan
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= base_url('modules/settings/change-password.php') ?>">
                            <i class="bi bi-key me-2 text-warning"></i>Ganti Password
                        </a>
                    </li>
                    <?php if (Permission::isDeveloper()): ?>
                    <li>
                        <a class="dropdown-item py-2 fw-semibold text-info" href="<?= base_url('modules/developer/index.php') ?>">
                            <i class="bi bi-terminal-fill me-2 text-info"></i>Portal Developer
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger fw-semibold" href="<?= base_url('logout.php') ?>">
                            <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div class="content-body">
        
        <!-- ========================================================
             PAGE HERO BANNER (PENANDA RESMI LOKASI HALAMAN)
             ======================================================== -->
        <div class="page-hero-banner">
            <!-- Ikon Watermark Latar Belakang -->
            <i class="bi <?= e($bannerIcon) ?> banner-watermark-icon"></i>

            <div class="page-hero-content">
                <div class="banner-title-group">
                    <div class="banner-icon-box">
                        <i class="bi <?= e($bannerIcon) ?>"></i>
                    </div>
                    <div>
                        <div class="banner-badge-category">
                            <i class="bi bi-bookmark-check-fill me-1"></i> <?= e($bannerCategory) ?>
                        </div>
                        <h2 class="banner-page-title"><?= e($bannerTitle) ?></h2>
                        <p class="banner-page-desc"><?= e($bannerDesc) ?></p>

                        <!-- Breadcrumbs Navigasi Kapsul -->
                        <?php if (!empty($pageBreadcrumbs)): ?>
                            <div class="banner-breadcrumbs">
                                <a href="<?= base_url('index.php') ?>"><i class="bi bi-house-door me-1"></i>Beranda</a>
                                <?php foreach ($pageBreadcrumbs as $label => $link): ?>
                                    <span class="separator"><i class="bi bi-chevron-right"></i></span>
                                    <?php if ($link): ?>
                                        <a href="<?= e($link) ?>"><?= e($label) ?></a>
                                    <?php else: ?>
                                        <span class="current"><?= e($label) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bagian Kanan Banner: Action Buttons / Brand Tag -->
                <div class="banner-actions">
                    <?php if (!empty($pageActionBtn)): ?>
                        <?= $pageActionBtn ?>
                    <?php else: ?>
                        <div class="d-none d-md-flex flex-column align-items-end text-end text-light opacity-75 small">
                            <div class="fw-bold text-white"><?= e(get_company_setting('company_short_name', 'MBB')) ?> Management</div>
                            <div style="font-size: 0.72rem;"><i class="bi bi-geo-alt me-1"></i>Air Hitam, Lampung Barat</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?= flash_display() ?>
