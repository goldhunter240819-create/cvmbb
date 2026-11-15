<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Matriks Pengaturan Hak Akses & Peran (Role Permissions)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('users.manage');

$db = Database::getInstance();

// Ambil daftar role (Kecualikan role 'developer' demi keamanan privat)
$roles = $db->query("SELECT * FROM roles WHERE role_name != 'developer' ORDER BY id ASC")->fetchAll();

$selectedRoleId = (int)($_GET['role_id'] ?? ($roles[1]['id'] ?? 2)); // Default to Komisaris or Direksi (not Super Admin which has all permissions)

// -------------------------------------------------------------
// POST HANDLER: SIMPAN MATRIKS HAK AKSES PERAN
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $targetRoleId = (int)($_POST['role_id'] ?? 0);
    $selectedPerms = $_POST['permissions'] ?? [];

    // Validasi role tidak boleh developer
    $roleCheck = $db->prepare("SELECT role_name, role_title FROM roles WHERE id = ?");
    $roleCheck->execute([$targetRoleId]);
    $roleInfo = $roleCheck->fetch();

    if (!$roleInfo || $roleInfo['role_name'] === 'developer') {
        flash_set('danger', 'Peran yang dipilih tidak valid atau dilindungi sistem.');
        redirect('modules/settings/permissions.php');
    }

    try {
        $db->beginTransaction();

        // Hapus izin lama role tersebut
        $delStmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $delStmt->execute([$targetRoleId]);

        // Simpan izin baru yang dicentang
        if (!empty($selectedPerms)) {
            $insStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($selectedPerms as $permId) {
                $insStmt->execute([$targetRoleId, (int)$permId]);
            }
        }

        $db->commit();
        log_audit('UPDATE_ROLE_PERMISSIONS', 'settings', $targetRoleId, "Memperbarui hak akses untuk peran: {$roleInfo['role_title']} (" . count($selectedPerms) . " izin)");
        flash_set('success', "Hak akses untuk peran '{$roleInfo['role_title']}' berhasil disimpan.");
    } catch (Exception $e) {
        $db->rollBack();
        flash_set('danger', 'Gagal menyimpan hak akses: ' . $e->getMessage());
    }

    redirect('modules/settings/permissions.php?role_id=' . $targetRoleId);
}

// Ambil data role aktif
$activeRole = null;
foreach ($roles as $r) {
    if ((int)$r['id'] === $selectedRoleId) {
        $activeRole = $r;
        break;
    }
}
if (!$activeRole && !empty($roles)) {
    $activeRole = $roles[0];
    $selectedRoleId = (int)$activeRole['id'];
}

// Ambil ID permission yang saat ini dimiliki role ini
$assignedStmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
$assignedStmt->execute([$selectedRoleId]);
$assignedPermIds = $assignedStmt->fetchAll(PDO::FETCH_COLUMN);

// Ambil seluruh permissions dan kelompokkan berdasarkan module
$allPermissions = $db->query("SELECT * FROM permissions ORDER BY module ASC, id ASC")->fetchAll();
$groupedPermissions = [];
foreach ($allPermissions as $p) {
    $mod = $p['module'];
    $groupedPermissions[$mod][] = $p;
}

$moduleDisplayNames = [
    'dashboard'         => 'Dashboard & Ringkasan Eksekutif',
    'organization'      => 'Struktur Organisasi & Jabatan',
    'employees'         => 'Data Pegawai & Personalia',
    'coffee-trading'    => 'Divisi Coffee Trading (Kopi Biji)',
    'coffee-production' => 'Divisi Coffee Production (Roasting/Kemasan)',
    'roasting-service'  => 'Coffee Roasting Service (Maklon)',
    'brilink'           => 'Divisi Agen BRILink',
    'decoration'        => 'Divisi Dekoration (Pesta & Acara)',
    'sound-system'      => 'Divisi Sound Sistem (Audio & Genset)',
    'administration'    => 'Persuratan, Agenda & Dokumen',
    'activities'        => 'Aktivitas & Log Divisi',
    'inventory'         => 'Inventaris & Aset Perusahaan',
    'finance'           => 'Kas & Keuangan Terpadu',
    'reports'           => 'Laporan Manajemen & Konsolidasi',
    'settings'          => 'Pengaturan Sistem & Profil Perusahaan'
];

$pageTitle = 'Pengaturan Hak Akses & Peran (RBAC)';
$currentModule = 'settings-users';
$pageBreadcrumbs = [
    'Pengaturan' => null,
    'Pengguna & Role' => base_url('modules/settings/users.php'),
    'Matriks Hak Akses' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. TOOLBAR PILIH PERAN (ROLE) -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row align-items-center justify-content-between g-2">
            <div class="col-md-6 d-flex align-items-center gap-3">
                <a href="<?= base_url('modules/settings/users.php') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Pengguna
                </a>
                <span class="text-muted">|</span>
                <span class="fw-bold text-dark"><i class="bi bi-shield-shaded text-primary me-1"></i> Pilih Peran yang Dikonfigurasi:</span>
            </div>
            <div class="col-md-5 text-md-end">
                <form method="GET" action="" class="d-inline-flex gap-2 w-100 justify-content-md-end">
                    <select name="role_id" class="form-select form-select-sm w-auto fw-bold" onchange="this.form.submit()">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= (int)$r['id'] === $selectedRoleId ? 'selected' : '' ?>>
                                <?= e($r['role_title']) ?> <?= $r['role_name'] === 'super_admin' ? '(Akses Penuh Otomatis)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 2. KARTU INFORMASI PERAN -->
<div class="card-mbb mb-4 border-start border-4 border-primary">
    <div class="card-mbb-body p-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace text-uppercase"><?= e($activeRole['role_name']) ?></span>
                <h5 class="fw-bold text-dark mb-1 mt-2"><?= e($activeRole['role_title']) ?></h5>
                <p class="text-muted small mb-0"><?= e($activeRole['description'] ?? 'Peran pengguna operasional CV. Maju Berkah Bersama.') ?></p>
            </div>
            <?php if ($activeRole['role_name'] === 'super_admin'): ?>
                <div class="alert alert-info py-2 px-3 mb-0 small border-info">
                    <i class="bi bi-info-circle-fill me-1"></i> Peran <strong>SUPER ADMIN</strong> otomatis memiliki hak akses tak terbatas ke seluruh sistem secara permanen.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 3. MATRIKS CHECKBOX PERMISSIONS PER MODUL -->
<form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-ui-checks-grid text-primary me-2"></i>Daftar Hak Akses Modul</h6>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnCheckAll">
                <i class="bi bi-check-all me-1"></i> Centang Semua
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUncheckAll">
                <i class="bi bi-x-circle me-1"></i> Hapus Semua Centang
            </button>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($groupedPermissions as $moduleKey => $perms): ?>
            <?php $modLabel = $moduleDisplayNames[$moduleKey] ?? ucfirst(str_replace('-', ' ', $moduleKey)); ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-mbb h-100">
                    <div class="card-mbb-header py-2 bg-light">
                        <span class="fw-bold text-dark small text-truncate" title="<?= e($modLabel) ?>">
                            <i class="bi bi-folder2-open text-primary me-1"></i> <?= e($modLabel) ?>
                        </span>
                    </div>
                    <div class="card-mbb-body p-3">
                        <?php foreach ($perms as $p): ?>
                            <?php $isAssigned = in_array((int)$p['id'], $assignedPermIds, true) || $activeRole['role_name'] === 'super_admin'; ?>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" 
                                       value="<?= $p['id'] ?>" id="perm_<?= $p['id'] ?>"
                                       <?= $isAssigned ? 'checked' : '' ?>
                                       <?= $activeRole['role_name'] === 'super_admin' ? 'disabled' : '' ?>>
                                <label class="form-check-label small" for="perm_<?= $p['id'] ?>">
                                    <strong class="d-block text-dark"><?= e($p['name']) ?></strong>
                                    <span class="text-muted" style="font-size: 0.76rem;"><?= e($p['description'] ?? $p['permission_key']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($activeRole['role_name'] !== 'super_admin'): ?>
    <div class="mt-4 p-3 bg-white border rounded shadow-sm d-flex justify-content-between align-items-center">
        <span class="text-muted small">Pastikan pengaturan hak akses sudah sesuai dengan tugas pokok & fungsi (tupoksi) jabatan masing-masing divisi.</span>
        <button type="submit" class="btn btn-primary px-4 fw-bold">
            <i class="bi bi-save me-1"></i> Simpan Matriks Hak Akses
        </button>
    </div>
    <?php endif; ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('btnCheckAll');
    const uncheckAll = document.getElementById('btnUncheckAll');
    const checkboxes = document.querySelectorAll('.perm-checkbox');

    if (checkAll) {
        checkAll.addEventListener('click', function() {
            checkboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = true;
            });
        });
    }

    if (uncheckAll) {
        uncheckAll.addEventListener('click', function() {
            checkboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = false;
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
