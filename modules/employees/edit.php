<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Edit Profil Pegawai & Pengaturan Akun Login
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('employees.manage');

$db = Database::getInstance();
$empId = (int)($_GET['id'] ?? 0);

// Auto-check column avatar di users
try {
    $colCheck = $db->query("SHOW COLUMNS FROM `users` LIKE 'avatar'")->fetch();
    if (!$colCheck) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(255) NULL AFTER `email`");
    }
} catch (\Throwable $e) {}

$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    flash_set('danger', 'Data pegawai tidak ditemukan.');
    redirect('modules/employees/index.php');
}

$divisions = $db->query("SELECT * FROM divisions ORDER BY division_name ASC")->fetchAll();
$positions = $db->query("SELECT * FROM positions ORDER BY level_rank ASC, position_name ASC")->fetchAll();
$roles = $db->query("SELECT * FROM roles WHERE role_name != 'developer' ORDER BY id ASC")->fetchAll();

// Cek akun login yang terhubung
$stmtUser = $db->prepare("SELECT u.*, r.role_title, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.employee_id = ?");
$stmtUser->execute([$empId]);
$linkedUser = $stmtUser->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect("modules/employees/edit.php?id={$empId}");
    }

    $code     = trim($_POST['employee_code'] ?? '');
    $name     = trim($_POST['full_name'] ?? '');
    $gender   = $_POST['gender'] ?? 'L';
    $phone    = trim($_POST['phone_number'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $divId    = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
    $posId    = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $joinDate = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
    $status   = $_POST['employment_status'] ?? 'TETAP';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes    = trim($_POST['notes'] ?? '');

    // 1. Handle Upload Foto Profil
    $avatarPath = $emp['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['avatar']['tmp_name'];
        $fileName = $_FILES['avatar']['name'];
        $fileSize = $_FILES['avatar']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed) && $fileSize <= 5 * 1024 * 1024) {
            $targetDir = __DIR__ . '/../../uploads/avatars';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0777, true);
            }
            $newFileName = 'avatar_emp_' . $empId . '_' . time() . '.' . $ext;
            $destPath = $targetDir . '/' . $newFileName;
            if (move_uploaded_file($fileTmp, $destPath)) {
                $avatarPath = 'uploads/avatars/' . $newFileName;
            }
        }
    }

    if (empty($code) || empty($name)) {
        flash_set('danger', 'Kode Pegawai dan Nama Lengkap wajib diisi.');
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE employees 
                SET employee_code = ?, full_name = ?, gender = ?, phone_number = ?, email = ?,
                    address = ?, division_id = ?, position_id = ?, join_date = ?, employment_status = ?, is_active = ?, notes = ?, avatar = ?
                WHERE id = ?
            ");
            $stmt->execute([$code, $name, $gender, $phone, $email, $address, $divId, $posId, $joinDate, $status, $isActive, $notes, $avatarPath, $empId]);

            // 2. Handle Akun Login Sistem
            $enableAccount = isset($_POST['enable_account']);
            $accUsername   = trim($_POST['account_username'] ?? '');
            $accPassword   = trim($_POST['account_password'] ?? '');
            $accRoleId     = (int)($_POST['account_role_id'] ?? 0);
            $accActive     = isset($_POST['account_is_active']) ? 1 : 0;

            if ($linkedUser) {
                // Update akun yang sudah ada
                if (!empty($accUsername) && $accRoleId) {
                    $updSql = "UPDATE users SET username = ?, email = ?, role_id = ?, is_active = ?, avatar = ? WHERE id = ?";
                    $params = [$accUsername, $email ?: $linkedUser['email'], $accRoleId, $accActive, $avatarPath, $linkedUser['id']];
                    $db->prepare($updSql)->execute($params);

                    if (!empty($accPassword)) {
                        $newHash = password_hash($accPassword, PASSWORD_BCRYPT);
                        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $linkedUser['id']]);
                    }
                }
            } else {
                // Buat akun baru jika dicentang
                if ($enableAccount && !empty($accUsername) && !empty($accPassword) && $accRoleId) {
                    $chkU = $db->prepare("SELECT id FROM users WHERE username = ?");
                    $chkU->execute([$accUsername]);
                    if ($chkU->fetch()) {
                        flash_set('warning', "Data pegawai tersimpan, namun Username '{$accUsername}' sudah dipakai akun lain.");
                    } else {
                        $hash = password_hash($accPassword, PASSWORD_BCRYPT);
                        $insUser = $db->prepare("
                            INSERT INTO users (username, email, password_hash, role_id, employee_id, is_active, avatar)
                            VALUES (?, ?, ?, ?, ?, 1, ?)
                        ");
                        $insUser->execute([$accUsername, $email ?: "{$accUsername}@cvmbb.co.id", $hash, $accRoleId, $empId, $avatarPath]);
                        log_audit('CREATE_USER', 'users', $db->lastInsertId(), "Membuat akun login pegawai {$name}: {$accUsername}");
                    }
                }
            }

            log_audit('UPDATE_EMPLOYEE', 'employees', $empId, "Memperbarui data pegawai: {$name} ({$code})");
            flash_set('success', "Data pegawai '{$name}' berhasil diperbarui.");
            redirect('modules/employees/index.php');
        } catch (Exception $e) {
            flash_set('danger', "Gagal memperbarui data: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Edit Data Pegawai';
$currentModule = 'employees';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Kepegawaian' => base_url('modules/employees/index.php'),
    'Edit Pegawai' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-mbb">
            <div class="card-mbb-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-mbb-title text-white mb-0">
                    <i class="bi bi-pencil-square me-2"></i> Edit Data Pegawai: <?= e($emp['full_name']) ?>
                </h5>
                <a href="<?= base_url('modules/employees/index.php') ?>" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-mbb-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <!-- 1. FOTO PROFIL PEGAWAI -->
                    <div class="p-3 mb-4 rounded-3 border bg-light d-flex align-items-center gap-3">
                        <div class="position-relative" style="flex-shrink: 0;">
                            <?php if (!empty($emp['avatar']) && file_exists(__DIR__ . '/../../' . $emp['avatar'])): ?>
                                <img src="<?= base_url($emp['avatar']) ?>?v=<?= time() ?>" alt="Avatar" class="rounded-circle border border-3 border-primary shadow-sm" style="width: 72px; height: 72px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white border border-3 border-primary-subtle shadow-sm" style="width: 72px; height: 72px; font-size: 1.6rem;">
                                    <?= strtoupper(substr($emp['full_name'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label fw-bold mb-1"><i class="bi bi-camera me-1"></i>Foto Profil Pegawai</label>
                            <input type="file" name="avatar" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                            <div class="text-muted small mt-1" style="font-size: 0.72rem;">Format gambar JPG, PNG, atau WEBP (Maksimal 5 MB). Foto ini akan digunakan di seluruh kartu pegawai & akun login.</div>
                        </div>
                    </div>

                    <!-- 2. DATA UTAMA PEGAWAI -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Nomor Induk / Kode Pegawai <span class="text-danger">*</span></label>
                            <input type="text" name="employee_code" class="form-control" value="<?= e($emp['employee_code']) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= e($emp['full_name']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Jenis Kelamin</label>
                            <select name="gender" class="form-select">
                                <option value="L" <?= $emp['gender'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $emp['gender'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jabatan Struktural</label>
                            <select name="position_id" class="form-select">
                                <option value="">-- Pilih Posisi Jabatan --</option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $emp['position_id'] == $p['id'] ? 'selected' : '' ?>>
                                        <?= e($p['position_name']) ?> (Rank <?= $p['level_rank'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" style="font-size: 0.75rem;">Untuk mutasi resmi bersurat SK, gunakan menu <a href="<?= base_url('modules/organization/mutate.php?id=' . $emp['id']) ?>">Mutasi Jabatan</a>.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Divisi Penempatan</label>
                            <select name="division_id" class="form-select">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= $emp['division_id'] == $d['id'] ? 'selected' : '' ?>>
                                        <?= e($d['division_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Nomor Telepon</label>
                            <input type="text" name="phone_number" class="form-control" value="<?= e($emp['phone_number']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($emp['email']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tanggal Mulai Masuk</label>
                            <input type="date" name="join_date" value="<?= e($emp['join_date']) ?>" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Status Kepegawaian</label>
                            <select name="employment_status" class="form-select">
                                <option value="TETAP" <?= $emp['employment_status'] === 'TETAP' ? 'selected' : '' ?>>Karyawan Tetap</option>
                                <option value="KONTRAK" <?= $emp['employment_status'] === 'KONTRAK' ? 'selected' : '' ?>>Kontrak</option>
                                <option value="MAGANG" <?= $emp['employment_status'] === 'MAGANG' ? 'selected' : '' ?>>Magang</option>
                                <option value="HONORER" <?= $emp['employment_status'] === 'HONORER' ? 'selected' : '' ?>>Honorer</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Alamat</label>
                            <input type="text" name="address" class="form-control" value="<?= e($emp['address']) ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-center mt-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $emp['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="isActive">Pegawai Aktif</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e($emp['notes']) ?></textarea>
                    </div>

                    <!-- 3. KETERHUBUNGAN AKUN LOGIN SISTEM -->
                    <div class="card border mb-4 shadow-sm" style="border-radius: 12px;">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2 px-3">
                            <span class="fw-bold text-dark small">
                                <i class="bi bi-shield-lock-fill text-primary me-1"></i> Akses Akun Login Sistem (Web & APK)
                            </span>
                            <?php if ($linkedUser): ?>
                                <span class="badge bg-success-subtle text-success border border-success">
                                    <i class="bi bi-check2"></i> Terhubung: <?= e($linkedUser['username']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Belum Punya Akun Login</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-3">
                            <?php if ($linkedUser): ?>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Username Login</label>
                                        <input type="text" name="account_username" class="form-control form-control-sm" value="<?= e($linkedUser['username']) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Ganti Password Baru</label>
                                        <input type="password" name="account_password" class="form-control form-control-sm" placeholder="Kosongkan jika tetap">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-bold">Role Hak Akses</label>
                                        <select name="account_role_id" class="form-select form-select-sm" required>
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?= $r['id'] ?>" <?= $linkedUser['role_id'] == $r['id'] ? 'selected' : '' ?>>
                                                    <?= e($r['role_title']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="account_is_active" id="accActive" <?= $linkedUser['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold" for="accActive">Akun Aktif (Bisa Login)</label>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php
                                $suggestedUser = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($emp['full_name']))[0]));
                                if (strlen($suggestedUser) < 3) $suggestedUser = 'user' . $empId;
                                ?>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="enable_account" id="toggleEnableAccount" onchange="document.getElementById('newAccountFields').classList.toggle('d-none', !this.checked)">
                                    <label class="form-check-label fw-bold text-primary" for="toggleEnableAccount">
                                        Aktifkan & Buat Akun Login untuk Pegawai Ini Sekarang
                                    </label>
                                </div>
                                <div id="newAccountFields" class="d-none border-top pt-3">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Username Baru <span class="text-danger">*</span></label>
                                            <input type="text" name="account_username" class="form-control form-control-sm" value="<?= e($suggestedUser) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Password Awal <span class="text-danger">*</span></label>
                                            <input type="password" name="account_password" class="form-control form-control-sm" value="mbb12345">
                                            <small class="text-muted" style="font-size: 0.7rem;">Default: mbb12345</small>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold">Role Hak Akses <span class="text-danger">*</span></label>
                                            <select name="account_role_id" class="form-select form-select-sm">
                                                <?php foreach ($roles as $r): ?>
                                                    <option value="<?= $r['id'] ?>"><?= e($r['role_title']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= base_url('modules/employees/index.php') ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
