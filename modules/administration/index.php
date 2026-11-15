<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Pusat Administrasi Perusahaan
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('administration.view');

$db = Database::getInstance();

// Statistik Administrasi
$totalIncoming = $db->query("SELECT COUNT(*) FROM incoming_letters")->fetchColumn();
$totalOutgoing = $db->query("SELECT COUNT(*) FROM outgoing_letters")->fetchColumn();
$totalDocs     = $db->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$activeActivities = $db->query("SELECT COUNT(*) FROM division_activities WHERE status IN ('RENCANA', 'BERJALAN')")->fetchColumn();

// Surat Masuk Terbaru (5 Terakhir)
$recentIncoming = $db->query("
    SELECT * FROM incoming_letters 
    ORDER BY received_date DESC, id DESC 
    LIMIT 5
")->fetchAll();

// Surat Keluar Terbaru (5 Terakhir)
$recentOutgoing = $db->query("
    SELECT * FROM outgoing_letters 
    ORDER BY letter_date DESC, id DESC 
    LIMIT 5
")->fetchAll();

$pageTitle = 'Pusat Tata Usaha & Administrasi';
$currentModule = 'administration';
$pageCategory = 'TATA USAHA & PERSURATAN (PJ: NURIL HUDA)';
$pageDesc = 'Pengarsipan surat masuk, pembuatan surat keluar berpenomoran otomatis, manajemen arsip digital, dan buku log kegiatan divisi.';
$pageBreadcrumbs = [
    'Administrasi' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/administration/incoming.php') . '" class="btn btn-light btn-sm fw-bold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-inbox-fill text-primary"></i> Surat Masuk
        </a>
        <a href="' . base_url('modules/administration/outgoing.php') . '" class="btn btn-warning btn-sm fw-bold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-send-fill"></i> Surat Keluar
        </a>
        <a href="' . base_url('modules/administration/documents.php') . '" class="btn btn-info text-white btn-sm fw-bold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-folder-fill"></i> Arsip Digital
        </a>
        <a href="' . base_url('modules/administration/activities.php') . '" class="btn btn-success btn-sm fw-bold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-calendar-check-fill"></i> Buku Kegiatan
        </a>
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. STATISTIK ADMINISTRASI -->
<div class="row g-3 mb-4">
    <!-- Surat Masuk -->
    <div class="col-xl-3 col-sm-6">
        <a href="<?= base_url('modules/administration/incoming.php') ?>" class="text-decoration-none">
            <div class="stat-card primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Surat Masuk</div>
                        <div class="stat-value text-primary"><?= number_format($totalIncoming) ?></div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    Total arsip surat masuk teragendakan
                </div>
            </div>
        </a>
    </div>

    <!-- Surat Keluar -->
    <div class="col-xl-3 col-sm-6">
        <a href="<?= base_url('modules/administration/outgoing.php') ?>" class="text-decoration-none">
            <div class="stat-card warning">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Surat Keluar</div>
                        <div class="stat-value text-warning"><?= number_format($totalOutgoing) ?></div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="bi bi-send-fill"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    Nomor surat keluar otomatis resmi
                </div>
            </div>
        </a>
    </div>

    <!-- Dokumen Digital -->
    <div class="col-xl-3 col-sm-6">
        <a href="<?= base_url('modules/administration/documents.php') ?>" class="text-decoration-none">
            <div class="stat-card cyan">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Arsip Dokumen</div>
                        <div class="stat-value text-info"><?= number_format($totalDocs) ?></div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="bi bi-file-earmark-lock-fill"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    Berkas legalitas, sertifikasi & SOP
                </div>
            </div>
        </a>
    </div>

    <!-- Kegiatan Divisi Aktif -->
    <div class="col-xl-3 col-sm-6">
        <a href="<?= base_url('modules/administration/activities.php') ?>" class="text-decoration-none">
            <div class="stat-card success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Kegiatan Divisi</div>
                        <div class="stat-value text-success"><?= number_format($activeActivities) ?></div>
                    </div>
                    <div class="stat-card-icon">
                        <i class="bi bi-calendar2-check-fill"></i>
                    </div>
                </div>
                <div class="mt-2 small text-muted">
                    Agenda kegiatan aktif lintas divisi
                </div>
            </div>
        </a>
    </div>
</div>

<!-- 2. REKAP SURAT TERBARU (DUA KOLOM) -->
<div class="row g-4">
    <!-- Rekap Surat Masuk -->
    <div class="col-lg-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title text-primary">
                    <i class="bi bi-inbox-fill"></i> Surat Masuk Terbaru
                </h6>
                <a href="<?= base_url('modules/administration/incoming.php') ?>" class="btn btn-sm btn-link text-decoration-none">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-mbb-body p-0">
                <div class="table-responsive">
                    <table class="table-mbb align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">No. Agenda</th>
                                <th>Pengirim & Perihal</th>
                                <th>Tgl Diterima</th>
                                <th class="text-end pe-3">Lampiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentIncoming)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada surat masuk tercatat.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentIncoming as $inc): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-primary"><?= e($inc['agenda_number']) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($inc['sender']) ?></div>
                                            <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;"><?= e($inc['subject']) ?></small>
                                        </td>
                                        <td><small class="text-muted"><?= format_tanggal($inc['received_date']) ?></small></td>
                                        <td class="text-end pe-3">
                                            <?php if ($inc['file_path']): ?>
                                                <a href="<?= base_url($inc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Buka Lampiran">
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekap Surat Keluar -->
    <div class="col-lg-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title text-warning">
                    <i class="bi bi-send-fill"></i> Surat Keluar Terbaru
                </h6>
                <a href="<?= base_url('modules/administration/outgoing.php') ?>" class="btn btn-sm btn-link text-decoration-none">
                    Lihat Semua <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-mbb-body p-0">
                <div class="table-responsive">
                    <table class="table-mbb align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">No. Surat</th>
                                <th>Tujuan & Perihal</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentOutgoing)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">Belum ada surat keluar tercatat.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentOutgoing as $out): ?>
                                    <tr>
                                        <td class="ps-3 fw-bold text-dark"><?= e($out['letter_number']) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($out['recipient']) ?></div>
                                            <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;"><?= e($out['subject']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success"><?= e($out['status']) ?></span>
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="<?= base_url('modules/administration/print_letter.php?id=' . $out['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak Surat">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
