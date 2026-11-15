<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Edit Profil Pegawai
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

$stmt = $db->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->execute([$empId]);
$emp = $stmt->fetch();

if (!$emp) {
    flash_set('danger', 'Data pegawai tidak ditemukan.');
    redirect('modules/employees/index.php');
}

$divisions = $db->query("SELECT * FROM divisions ORDER BY division_name ASC")->fetchAll();
$positions = $db->query("SELECT * FROM positions ORDER BY level_rank ASC, position_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect("modules/employees/edit.php?id={$empId}");
    }

    $code    = trim($_POST['employee_code'] ?? '');
    $name    = trim($_POST['full_name'] ?? '');
    $gender  = $_POST['gender'] ?? 'L';
    $phone   = trim($_POST['phone_number'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $divId   = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
    $posId   = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $joinDate = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
    $status  = $_POST['employment_status'] ?? 'TETAP';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $notes   = trim($_POST['notes'] ?? '');

    if (empty($code) || empty($name)) {
        flash_set('danger', 'Kode Pegawai dan Nama Lengkap wajib diisi.');
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE employees 
                SET employee_code = ?, full_name = ?, gender = ?, phone_number = ?, email = ?,
                    address = ?, division_id = ?, position_id = ?, join_date = ?, employment_status = ?, is_active = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$code, $name, $gender, $phone, $email, $address, $divId, $posId, $joinDate, $status, $isActive, $notes, $empId]);

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
            <div class="card-mbb-header bg-primary text-white">
                <h5 class="card-mbb-title text-white">
                    <i class="bi bi-pencil-square me-2"></i> Edit Data Pegawai: <?= e($emp['full_name']) ?>
                </h5>
                <a href="<?= base_url('modules/employees/index.php') ?>" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-mbb-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>

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
