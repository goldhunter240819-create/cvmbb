<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Tambah Pegawai Baru
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('employees.manage');

$db = Database::getInstance();

$divisions = $db->query("SELECT * FROM divisions ORDER BY division_name ASC")->fetchAll();
$positions = $db->query("SELECT * FROM positions ORDER BY level_rank ASC, position_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/employees/create.php');
    }

    $code    = trim($_POST['employee_code'] ?? '');
    $name    = trim($_POST['full_name'] ?? '');
    $gender  = $_POST['gender'] ?? 'L';
    $phone   = trim($_POST['phone_number'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $divId   = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
    $posId   = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
    $joinDate = !empty($_POST['join_date']) ? $_POST['join_date'] : date('Y-m-d');
    $status  = $_POST['employment_status'] ?? 'TETAP';
    $notes   = trim($_POST['notes'] ?? '');

    if (empty($code) || empty($name)) {
        flash_set('danger', 'Kode Pegawai (NIP) dan Nama Lengkap wajib diisi.');
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO employees (employee_code, full_name, gender, phone_number, email, address, division_id, position_id, join_date, employment_status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $name, $gender, $phone, $email, $address, $divId, $posId, $joinDate, $status, $notes]);
            $newId = $db->lastInsertId();

            // Catat ke riwayat jabatan awal jika ada jabatan
            if ($posId) {
                $histStmt = $db->prepare("
                    INSERT INTO position_histories (employee_id, new_division_id, new_position_id, change_date, change_reason, approved_by)
                    VALUES (?, ?, ?, ?, 'Pengangkatan Anggota Baru', ?)
                ");
                $histStmt->execute([$newId, $divId, $posId, $joinDate, Auth::user()['full_name']]);
            }

            log_audit('CREATE_EMPLOYEE', 'employees', $newId, "Menambahkan pegawai baru: {$name} ({$code})");
            flash_set('success', "Pegawai '{$name}' berhasil ditambahkan ke sistem.");
            redirect('modules/employees/index.php');
        } catch (Exception $e) {
            flash_set('danger', "Gagal menyimpan pegawai: " . $e->getMessage());
        }
    }
}

// Generate automatic next employee code
$maxCodeStmt = $db->query("SELECT MAX(id) AS max_id FROM employees");
$nextId = (int)$maxCodeStmt->fetch()['max_id'] + 1;
$suggestedCode = 'MBB-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

$pageTitle = 'Tambah Pegawai Baru';
$currentModule = 'employees';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Kepegawaian' => base_url('modules/employees/index.php'),
    'Tambah Pegawai' => null
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
                    <i class="bi bi-person-plus-fill me-2"></i> Formulir Tambah Pegawai & Anggota
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
                            <input type="text" name="employee_code" class="form-control" value="<?= e($suggestedCode) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" placeholder="Nama lengkap beserta gelar..." required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Jenis Kelamin</label>
                            <select name="gender" class="form-select">
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jabatan Struktural</label>
                            <select name="position_id" class="form-select">
                                <option value="">-- Pilih Posisi Jabatan --</option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['position_name']) ?> (Rank <?= $p['level_rank'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Divisi Penempatan</label>
                            <select name="division_id" class="form-select">
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Nomor Telepon / WhatsApp</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="nama@cvmbb.co.id">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tanggal Mulai Bergabung</label>
                            <input type="date" name="join_date" value="<?= date('Y-m-d') ?>" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Status Hubungan Kerja</label>
                            <select name="employment_status" class="form-select">
                                <option value="TETAP">Karyawan Tetap</option>
                                <option value="KONTRAK">Kontrak</option>
                                <option value="MAGANG">Magang</option>
                                <option value="HONORER">Honorer</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Alamat Domisili</label>
                            <input type="text" name="address" class="form-control" placeholder="Alamat lengkap...">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan keahlian, riwayat, atau penugasan khusus..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= base_url('modules/employees/index.php') ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Pegawai
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
