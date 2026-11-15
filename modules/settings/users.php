<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Manajemen Pengguna & Hak Akses (RBAC)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('users.manage');

$db = Database::getInstance();

// -------------------------------------------------------------
// PROSES AKSI FORM
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/settings/users.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. TAMBAH PENGGUNA BARU
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $empId    = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;

        if (empty($username) || empty($email) || empty($password) || !$roleId) {
            flash_set('danger', 'Semua kolom wajib diisi lengkap.');
        } else {
            // Cek duplikasi
            $checkStmt = $db->prepare("SELECT COUNT(id) FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            if ($checkStmt->fetchColumn() > 0) {
                flash_set('danger', 'Username atau Email sudah terdaftar.');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password_hash, role_id, employee_id, is_active, must_change_password)
                    VALUES (?, ?, ?, ?, ?, 1, 0)
                ");
                $stmt->execute([$username, $email, $hash, $roleId, $empId]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_USER', 'users', $newId, "Menambahkan pengguna baru: {$username}");
                flash_set('success', "Akun pengguna '{$username}' berhasil dibuat.");
            }
        }
        redirect('modules/settings/users.php');
    }

    // 2. RESET PASSWORD PENGGUNA
    if ($action === 'reset_password') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $newPass  = trim($_POST['new_password'] ?? '');

        // Proteksi akun sistem / developer
        $checkDev = $db->prepare("SELECT u.username, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $checkDev->execute([$userId]);
        $devRow = $checkDev->fetch();
        if ($devRow && ($devRow['role_name'] === 'developer' || $devRow['username'] === 'Maul')) {
            flash_set('danger', 'Akses Ditolak: Akun sistem ini dilindungi dan tidak dapat dimodifikasi.');
            redirect('modules/settings/users.php');
        }

        if ($userId && !empty($newPass)) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            log_audit('RESET_PASSWORD', 'users', $userId, "Mereset password user ID: {$userId}");
            flash_set('success', 'Kata sandi pengguna berhasil diubah.');
        }
        redirect('modules/settings/users.php');
    }

    // 3. TOGGLE STATUS AKTIF
    if ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);

        // Proteksi akun sistem / developer
        $checkDev = $db->prepare("SELECT u.username, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $checkDev->execute([$userId]);
        $devRow = $checkDev->fetch();
        if ($devRow && ($devRow['role_name'] === 'developer' || $devRow['username'] === 'Maul')) {
            flash_set('danger', 'Akses Ditolak: Akun sistem ini dilindungi.');
            redirect('modules/settings/users.php');
        }

        if ($userId === Auth::id()) {
            flash_set('danger', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        } else {
            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$userId]);
            log_audit('TOGGLE_USER_STATUS', 'users', $userId, "Mengubah status aktif user ID: {$userId}");
            flash_set('success', 'Status akun berhasil diubah.');
        }
        redirect('modules/settings/users.php');
    }

    // 4. HAPUS PENGGUNA
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        // Proteksi mutlak akun developer agar tidak bisa disentuh / dihapus oleh admin
        $checkDev = $db->prepare("SELECT u.username, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $checkDev->execute([$userId]);
        $devRow = $checkDev->fetch();
        if ($devRow && ($devRow['role_name'] === 'developer' || $devRow['username'] === 'Maul')) {
            flash_set('danger', 'Akses Ditolak: Akun sistem ini dilindungi mutlak.');
            redirect('modules/settings/users.php');
        }

        if ($userId === Auth::id()) {
            flash_set('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            log_audit('DELETE_USER', 'users', $userId, "Menghapus user ID: {$userId}");
            flash_set('success', 'Akun pengguna berhasil dihapus.');
        }
        redirect('modules/settings/users.php');
    }
}

// Ambil list pengguna (SEMBUNYIKAN AKUN DEVELOPER MAUL DARI TAMPILAN ADMIN)
$users = $db->query("
    SELECT u.*, r.role_title, e.full_name, e.employee_code, p.position_name
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE r.role_name != 'developer' AND u.username != 'Maul'
    ORDER BY u.id ASC
")->fetchAll();

// Sembunyikan role 'developer' dari opsi penambahan role
$roles = $db->query("SELECT * FROM roles WHERE role_name != 'developer' ORDER BY id ASC")->fetchAll();
$employees = $db->query("SELECT id, employee_code, full_name FROM employees WHERE is_active = 1 ORDER BY full_name ASC")->fetchAll();

$pageTitle = 'Manajemen Pengguna & Role';
$currentModule = 'settings-users';
$pageBreadcrumbs = [
    'Pengaturan' => null,
    'Pengguna' => null
];

$pageActionBtn = '
    <button type="button" class="btn btn-light btn-sm fw-bold shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalAddUser">
        <i class="bi bi-person-plus-fill text-primary"></i> Tambah Pengguna Baru
    </button>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group" role="group">
        <a href="<?= base_url('modules/settings/users.php') ?>" class="btn btn-sm btn-primary active">
            <i class="bi bi-people-fill me-1"></i> Daftar Akun Pengguna
        </a>
        <a href="<?= base_url('modules/settings/permissions.php') ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-shield-check me-1"></i> Matriks Hak Akses Peran
        </a>
    </div>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddUser">
        <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
    </button>
</div>

<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-shield-lock-fill"></i> Daftar Akun Pengguna Terdaftar
        </h6>
        <span class="badge bg-primary-subtle text-primary"><?= count($users) ?> Akun</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Peran (Role)</th>
                        <th>Terkait Pegawai</th>
                        <th>Login Terakhir</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $idx => $u): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($u['username']) ?></div>
                                <?php if ($u['id'] === Auth::id()): ?>
                                    <span class="badge bg-success-subtle text-success border" style="font-size: 0.68rem;">Sesi Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                    <?= e($u['role_title']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($u['full_name']): ?>
                                    <div class="small fw-semibold"><?= e($u['full_name']) ?></div>
                                    <small class="text-muted"><?= e($u['position_name'] ?: '-') ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">Akun Sistem</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= $u['last_login_at'] ? format_tanggal($u['last_login_at'], true) : 'Belum pernah' ?>
                                </small>
                            </td>
                            <td>
                                <form method="POST" action="" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm p-0 border-0" <?= $u['id'] === Auth::id() ? 'disabled' : '' ?>>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-warning btn-reset-pass" 
                                            data-id="<?= $u['id'] ?>" 
                                            data-username="<?= e($u['username']) ?>"
                                            title="Ganti Password">
                                        <i class="bi bi-key-fill"></i>
                                    </button>
                                    <?php if ($u['id'] !== Auth::id()): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                data-confirm="Hapus akun '<?= e($u['username']) ?>'?" title="Hapus Akun">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL TAMBAH USER
     ======================================================== -->
<div class="modal fade" id="modalAddUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" placeholder="Nama pengguna login" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" placeholder="user@cvmbb.co.id" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Kata Sandi (Password) <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Hak Peran (Role) <span class="text-danger">*</span></label>
                            <select name="role_id" class="form-select" required>
                                <option value="">-- Pilih Peran --</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= e($r['role_title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tautkan ke Pegawai</label>
                            <select name="employee_id" class="form-select">
                                <option value="">-- Tanpa Tautan Pegawai --</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= e($e['full_name']) ?> (<?= e($e['employee_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL RESET PASSWORD
     ======================================================== -->
<div class="modal fade" id="modalResetPass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id" value="">

                <div class="modal-header bg-warning text-dark">
                    <h6 class="modal-title fw-bold" id="resetModalTitle">Ganti Password</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <label class="form-label small fw-bold">Password Baru <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" class="form-control" placeholder="Password baru..." required>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const resetModal = new bootstrap.Modal(document.getElementById('modalResetPass'));
    document.querySelectorAll('.btn-reset-pass').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('reset_user_id').value = this.dataset.id;
            document.getElementById('resetModalTitle').innerText = 'Password: ' + this.dataset.username;
            resetModal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
