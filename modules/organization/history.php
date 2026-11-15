<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Riwayat Perubahan Jabatan & Mutasi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('organization.view');

$db = Database::getInstance();

$histories = $db->query("
    SELECT h.*, e.full_name, e.employee_code,
           p_old.position_name AS old_position,
           p_new.position_name AS new_position,
           d_old.division_name AS old_division,
           d_new.division_name AS new_division
    FROM position_histories h
    INNER JOIN employees e ON h.employee_id = e.id
    LEFT JOIN positions p_old ON h.old_position_id = p_old.id
    LEFT JOIN positions p_new ON h.new_position_id = p_new.id
    LEFT JOIN divisions d_old ON h.old_division_id = d_old.id
    LEFT JOIN divisions d_new ON h.new_division_id = d_new.id
    ORDER BY h.change_date DESC, h.id DESC
")->fetchAll();

$pageTitle = 'Riwayat Perubahan Jabatan';
$currentModule = 'organization';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Struktur Organisasi' => base_url('modules/organization/index.php'),
    'Riwayat Jabatan' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <div>
        <h4 class="fw-bold text-dark mb-1">Riwayat Perubahan Jabatan & Mutasi</h4>
        <p class="text-muted small mb-0">Arsip kronologis pengangkatan, mutasi, dan promosi struktural di lingkungan CV. MAJU BERKAH BERSAMA</p>
    </div>
    <div>
        <a href="<?= base_url('modules/organization/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Struktur
        </a>
    </div>
</div>

<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-clock-history"></i> Log Kronologis Perubahan Jabatan
        </h6>
        <span class="badge bg-primary-subtle text-primary"><?= count($histories) ?> Catatan</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Tanggal</th>
                        <th>Pejabat</th>
                        <th>Jabatan Asal</th>
                        <th>Jabatan Baru</th>
                        <th>No. SK & Pengesah</th>
                        <th class="pe-4">Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($histories)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat mutasi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($histories as $h): ?>
                            <tr>
                                <td class="ps-4 text-nowrap fw-semibold text-dark">
                                    <?= format_tanggal($h['change_date']) ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($h['full_name']) ?></div>
                                    <small class="text-muted"><?= e($h['employee_code']) ?></small>
                                </td>
                                <td>
                                    <div class="text-muted small"><?= e($h['old_position'] ?: 'Posisi Awal') ?></div>
                                    <small class="text-secondary"><?= e($h['old_division'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <?= e($h['new_position']) ?>
                                    </span>
                                    <div class="small text-muted mt-1"><?= e($h['new_division'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?= e($h['sk_number'] ?: 'SK Perusahaan') ?></div>
                                    <small class="text-muted">Disetujui: <?= e($h['approved_by'] ?: '-') ?></small>
                                </td>
                                <td class="pe-4 text-muted small"><?= e($h['change_reason'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
