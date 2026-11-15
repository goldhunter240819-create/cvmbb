<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Mutasi / Pindah Divisi & Perubahan Jabatan
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('organization.manage');

$db = Database::getInstance();
$targetEmployeeId = (int)($_GET['id'] ?? 0);

// Ambil data seluruh pegawai
$employees = $db->query("
    SELECT e.*, p.position_name, d.division_name 
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    ORDER BY e.full_name ASC
")->fetchAll();

$positions = $db->query("SELECT * FROM positions ORDER BY level_rank ASC, position_name ASC")->fetchAll();
$divisions = $db->query("SELECT * FROM divisions ORDER BY division_name ASC")->fetchAll();

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/organization/mutate.php' . ($targetEmployeeId ? "?id={$targetEmployeeId}" : ''));
    }

    $empId      = (int)($_POST['employee_id'] ?? 0);
    $newDivId   = !empty($_POST['new_division_id']) ? (int)$_POST['new_division_id'] : null;
    $newPosId   = (int)($_POST['new_position_id'] ?? 0);
    $changeDate = $_POST['change_date'] ?? date('Y-m-d');
    $skNumber   = trim($_POST['sk_number'] ?? '');
    $reason     = trim($_POST['change_reason'] ?? '');
    $approver   = trim($_POST['approved_by'] ?? Auth::user()['full_name']);

    if (!$empId || !$newPosId) {
        flash_set('danger', 'Pegawai dan Jabatan Baru wajib dipilih.');
    } else {
        // Ambil data lama
        $stmt = $db->prepare("SELECT division_id, position_id, full_name FROM employees WHERE id = ?");
        $stmt->execute([$empId]);
        $oldData = $stmt->fetch();

        if ($oldData) {
            $db->beginTransaction();
            try {
                // 1. Simpan ke riwayat
                $stmt = $db->prepare("
                    INSERT INTO position_histories 
                    (employee_id, old_division_id, new_division_id, old_position_id, new_position_id, change_date, change_reason, sk_number, approved_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empId,
                    $oldData['division_id'],
                    $newDivId,
                    $oldData['position_id'],
                    $newPosId,
                    $changeDate,
                    $reason,
                    $skNumber,
                    $approver
                ]);

                // 2. Update pegawai
                $stmt = $db->prepare("UPDATE employees SET division_id = ?, position_id = ? WHERE id = ?");
                $stmt->execute([$newDivId, $newPosId, $empId]);

                $db->commit();

                log_audit('MUTATE_EMPLOYEE', 'organization', $empId, "Mutasi jabatan pegawai {$oldData['full_name']} ke posisi baru ID: {$newPosId}");
                flash_set('success', "Mutasi jabatan dan divisi untuk '{$oldData['full_name']}' berhasil diterapkan.");
                redirect('modules/organization/index.php');
            } catch (Exception $e) {
                $db->rollBack();
                flash_set('danger', "Gagal memproses mutasi: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Mutasi Jabatan & Pindah Divisi';
$currentModule = 'organization';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Struktur Organisasi' => base_url('modules/organization/index.php'),
    'Mutasi Jabatan' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card-mbb">
            <div class="card-mbb-header bg-primary text-white">
                <h5 class="card-mbb-title text-white">
                    <i class="bi bi-arrow-left-right me-2"></i> Formulir Mutasi & Rotasi Jabatan
                </h5>
                <a href="<?= base_url('modules/organization/index.php') ?>" class="btn btn-sm btn-light">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-mbb-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Anggota / Pejabat <span class="text-danger">*</span></label>
                        <select name="employee_id" id="employee_id" class="form-select select2" required>
                            <option value="">-- Pilih Anggota --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>" <?= $targetEmployeeId === (int)$emp['id'] ? 'selected' : '' ?>>
                                    <?= e($emp['full_name']) ?> (<?= e($emp['employee_code']) ?>) &mdash; <?= e($emp['position_name'] ?: 'Tanpa Jabatan') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jabatan Baru <span class="text-danger">*</span></label>
                            <select name="new_position_id" class="form-select" required>
                                <option value="">-- Pilih Jabatan Baru --</option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= $p['id'] ?>">
                                        <?= e($p['position_name']) ?> (Rank <?= $p['level_rank'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Divisi Baru</label>
                            <select name="new_division_id" class="form-select">
                                <option value="">-- Sesuaikan dengan Posisi / Tanpa Divisi --</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Efektif Mutasi <span class="text-danger">*</span></label>
                            <input type="date" name="change_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor Surat Keputusan (SK)</label>
                            <input type="text" name="sk_number" class="form-control" placeholder="Contoh: SK/MBB/2026/012">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Pejabat yang Menyetujui / Pengesah</label>
                        <input type="text" name="approved_by" class="form-control" value="Direktur MBB" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Alasan Perubahan / Mutasi</label>
                        <textarea name="change_reason" class="form-control" rows="3" placeholder="Uraian pertimbangan promosi, rotasi jabatan, atau restrukturisasi organisasi..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= base_url('modules/organization/index.php') ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Simpan & Terapkan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
