<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Data Pegawai & Anggota
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('employees.view');

$db = Database::getInstance();

// Hapus Pegawai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    Permission::require('employees.manage');
    if (verify_csrf_token()) {
        $empId = (int)($_POST['employee_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$empId]);
        log_audit('DELETE_EMPLOYEE', 'employees', $empId, "Menghapus pegawai ID: {$empId}");
        flash_set('success', 'Data pegawai berhasil dihapus.');
        redirect('modules/employees/index.php');
    }
}

$search = trim($_GET['q'] ?? '');
$divisionFilter = (int)($_GET['division_id'] ?? 0);

$query = "
    SELECT e.*, p.position_name, d.division_name, u.username, u.id AS user_id
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    LEFT JOIN users u ON u.employee_id = e.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (e.full_name LIKE ? OR e.employee_code LIKE ? OR e.phone_number LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($divisionFilter > 0) {
    $query .= " AND e.division_id = ?";
    $params[] = $divisionFilter;
}

$query .= " ORDER BY e.id ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$divisions = $db->query("SELECT * FROM divisions ORDER BY division_name ASC")->fetchAll();

$pageTitle = 'Data Pegawai & Anggota';
$currentModule = 'employees';
$pageBreadcrumbs = [
    'Kepegawaian' => null
];

if (Permission::can('employees.manage')) {
    $pageActionBtn = '
        <a href="' . base_url('modules/employees/create.php') . '" class="btn btn-light btn-sm fw-bold px-3 py-2 shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-person-plus-fill text-primary"></i> Tambah Pegawai
        </a>
    ';
}

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- FILTER & SEARCH -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari nama, NIP, atau no telepon...">
                </div>
            </div>
            <div class="col-md-4">
                <select name="division_id" class="form-select form-select-sm">
                    <option value="">-- Filter Semua Divisi --</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $divisionFilter === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['division_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== '' || $divisionFilter > 0): ?>
                    <a href="<?= base_url('modules/employees/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL PEGAWAI -->
<div class="card-mbb">
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Kode NIP</th>
                        <th>Nama Lengkap</th>
                        <th>Jabatan</th>
                        <th>Divisi</th>
                        <th>Kontak</th>
                        <th>Status</th>
                        <th>Akun Sistem</th>
                        <?php if (Permission::can('employees.manage')): ?>
                        <th class="text-end pe-4">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">Tidak ada data pegawai yang cocok.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $idx => $emp): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-primary"><?= e($emp['employee_code']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($emp['full_name']) ?></div>
                                    <small class="text-muted"><?= e($emp['address'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= e($emp['position_name'] ?: 'Staf') ?>
                                    </span>
                                </td>
                                <td><?= e($emp['division_name'] ?: '-') ?></td>
                                <td>
                                    <div class="small text-dark"><?= e($emp['phone_number'] ?: '-') ?></div>
                                    <small class="text-muted"><?= e($emp['email'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($emp['employment_status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($emp['username']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-check2"></i> <?= e($emp['username']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Tanpa Akun</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (Permission::can('employees.manage')): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/employees/edit.php?id=' . $emp['id']) ?>" class="btn btn-outline-primary" title="Edit Profil">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="employee_id" value="<?= $emp['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete" 
                                                    data-confirm="Hapus data pegawai '<?= e($emp['full_name']) ?>'?" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
