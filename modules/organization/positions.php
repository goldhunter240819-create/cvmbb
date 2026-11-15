<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Master Jabatan & Divisi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('organization.manage');

$db = Database::getInstance();

// -------------------------------------------------------------
// PROSES AKSI POST (TAMBAH / EDIT / HAPUS)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/organization/positions.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. TAMBAH / EDIT JABATAN
    if ($action === 'save_position') {
        $posId      = !empty($_POST['position_id']) ? (int)$_POST['position_id'] : null;
        $posCode    = trim($_POST['position_code'] ?? '');
        $posName    = trim($_POST['position_name'] ?? '');
        $divId      = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
        $parentId   = !empty($_POST['parent_position_id']) ? (int)$_POST['parent_position_id'] : null;
        $levelRank  = (int)($_POST['level_rank'] ?? 1);
        $jobDesc    = trim($_POST['job_description'] ?? '');

        if (empty($posCode) || empty($posName)) {
            flash_set('danger', 'Kode dan Nama Jabatan wajib diisi.');
        } else {
            if ($posId) {
                // Update
                $stmt = $db->prepare("
                    UPDATE positions 
                    SET position_code = ?, position_name = ?, division_id = ?, parent_position_id = ?, level_rank = ?, job_description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$posCode, $posName, $divId, $parentId, $levelRank, $jobDesc, $posId]);
                log_audit('UPDATE_POSITION', 'organization', $posId, "Memperbarui jabatan: {$posName} ({$posCode})");
                flash_set('success', "Jabatan '{$posName}' berhasil diperbarui.");
            } else {
                // Insert
                $stmt = $db->prepare("
                    INSERT INTO positions (position_code, position_name, division_id, parent_position_id, level_rank, job_description)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$posCode, $posName, $divId, $parentId, $levelRank, $jobDesc]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_POSITION', 'organization', $newId, "Menambahkan jabatan baru: {$posName} ({$posCode})");
                flash_set('success', "Jabatan baru '{$posName}' berhasil ditambahkan.");
            }
        }
        redirect('modules/organization/positions.php');
    }

    // 2. HAPUS JABATAN
    if ($action === 'delete_position') {
        $posId = (int)($_POST['position_id'] ?? 0);
        // Cek apakah dipakai oleh pegawai
        $stmt = $db->prepare("SELECT COUNT(id) FROM employees WHERE position_id = ?");
        $stmt->execute([$posId]);
        if ($stmt->fetchColumn() > 0) {
            flash_set('danger', 'Jabatan tidak dapat dihapus karena masih digunakan oleh anggota pegawai.');
        } else {
            $stmt = $db->prepare("DELETE FROM positions WHERE id = ?");
            $stmt->execute([$posId]);
            log_audit('DELETE_POSITION', 'organization', $posId, "Menghapus jabatan ID {$posId}");
            flash_set('success', 'Jabatan berhasil dihapus.');
        }
        redirect('modules/organization/positions.php');
    }

    // 3. TAMBAH / EDIT DIVISI
    if ($action === 'save_division') {
        $divId    = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
        $divCode  = trim($_POST['division_code'] ?? '');
        $divName  = trim($_POST['division_name'] ?? '');
        $parentDivId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $desc     = trim($_POST['description'] ?? '');

        if (empty($divCode) || empty($divName)) {
            flash_set('danger', 'Kode dan Nama Divisi wajib diisi.');
        } else {
            if ($divId) {
                $stmt = $db->prepare("
                    UPDATE divisions 
                    SET division_code = ?, division_name = ?, parent_id = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$divCode, $divName, $parentDivId, $desc, $divId]);
                log_audit('UPDATE_DIVISION', 'organization', $divId, "Memperbarui divisi: {$divName}");
                flash_set('success', "Divisi '{$divName}' berhasil diperbarui.");
            } else {
                $stmt = $db->prepare("
                    INSERT INTO divisions (division_code, division_name, parent_id, description)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$divCode, $divName, $parentDivId, $desc]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_DIVISION', 'organization', $newId, "Menambahkan divisi baru: {$divName}");
                flash_set('success', "Divisi baru '{$divName}' berhasil ditambahkan.");
            }
        }
        redirect('modules/organization/positions.php');
    }

    // 4. HAPUS DIVISI
    if ($action === 'delete_division') {
        $divId = (int)($_POST['division_id'] ?? 0);
        $stmt = $db->prepare("SELECT COUNT(id) FROM employees WHERE division_id = ?");
        $stmt->execute([$divId]);
        if ($stmt->fetchColumn() > 0) {
            flash_set('danger', 'Divisi tidak dapat dihapus karena masih menampung anggota pegawai.');
        } else {
            $stmt = $db->prepare("DELETE FROM divisions WHERE id = ?");
            $stmt->execute([$divId]);
            log_audit('DELETE_DIVISION', 'organization', $divId, "Menghapus divisi ID {$divId}");
            flash_set('success', 'Divisi berhasil dihapus.');
        }
        redirect('modules/organization/positions.php');
    }
}

// Ambil list data jabatan
$positions = $db->query("
    SELECT p.*, d.division_name, parent.position_name AS parent_name,
           (SELECT COUNT(e.id) FROM employees e WHERE e.position_id = p.id) AS employee_count
    FROM positions p
    LEFT JOIN divisions d ON p.division_id = d.id
    LEFT JOIN positions parent ON p.parent_position_id = parent.id
    ORDER BY p.level_rank ASC, p.position_name ASC
")->fetchAll();

// Ambil list divisi
$divisions = $db->query("
    SELECT d.*, parent.division_name AS parent_div_name,
           (SELECT COUNT(e.id) FROM employees e WHERE e.division_id = d.id) AS employee_count
    FROM divisions d
    LEFT JOIN divisions parent ON d.parent_id = parent.id
    ORDER BY d.id ASC
")->fetchAll();

$pageTitle = 'Master Jabatan & Divisi';
$currentModule = 'positions';
$pageBreadcrumbs = [
    'Struktur Organisasi' => base_url('modules/organization/index.php'),
    'Jabatan & Divisi' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/organization/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-diagram-3 me-1"></i> Bagan Struktur
        </a>
        <button type="button" class="btn btn-light btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddPosition">
            <i class="bi bi-plus-circle text-primary me-1"></i> Tambah Jabatan
        </button>
        <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddDivision">
            <i class="bi bi-folder-plus me-1"></i> Tambah Divisi
        </button>
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="row g-4">
    <!-- TABEL MASTER JABATAN -->
    <div class="col-lg-7">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title text-primary">
                    <i class="bi bi-person-badge-fill"></i> Master Jabatan Struktural
                </h6>
                <span class="badge bg-primary-subtle text-primary"><?= count($positions) ?> Jabatan</span>
            </div>
            <div class="card-mbb-body p-0">
                <div class="table-responsive">
                    <table class="table-mbb align-middle">
                        <thead>
                            <tr>
                                <th class="ps-3">Kode</th>
                                <th>Nama Jabatan</th>
                                <th>Divisi</th>
                                <th>Atasan Langsung</th>
                                <th>Anggota</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($positions as $p): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= e($p['position_code']) ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($p['position_name']) ?></div>
                                        <small class="text-muted">Level Rank: <?= $p['level_rank'] ?></small>
                                    </td>
                                    <td><?= e($p['division_name'] ?: '-') ?></td>
                                    <td><small class="text-muted"><?= e($p['parent_name'] ?: 'Pucuk Pimpinan') ?></small></td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border"><?= $p['employee_count'] ?> Orang</span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-position"
                                                data-id="<?= $p['id'] ?>"
                                                data-code="<?= e($p['position_code']) ?>"
                                                data-name="<?= e($p['position_name']) ?>"
                                                data-divid="<?= $p['division_id'] ?>"
                                                data-parentid="<?= $p['parent_position_id'] ?>"
                                                data-level="<?= $p['level_rank'] ?>"
                                                data-desc="<?= e($p['job_description']) ?>"
                                                title="Edit Jabatan">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_position">
                                            <input type="hidden" name="position_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-confirm-delete" 
                                                    data-confirm="Apakah Anda yakin ingin menghapus jabatan '<?= e($p['position_name']) ?>'?" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL MASTER DIVISI -->
    <div class="col-lg-5">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title text-success">
                    <i class="bi bi-diagram-2-fill"></i> Master Divisi Perusahaan
                </h6>
                <span class="badge bg-success-subtle text-success"><?= count($divisions) ?> Divisi</span>
            </div>
            <div class="card-mbb-body p-0">
                <div class="table-responsive">
                    <table class="table-mbb align-middle">
                        <thead>
                            <tr>
                                <th class="ps-3">Kode</th>
                                <th>Nama Divisi</th>
                                <th>Anggota</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($divisions as $d): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-success"><?= e($d['division_code']) ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($d['division_name']) ?></div>
                                        <small class="text-muted"><?= e($d['parent_div_name'] ? 'Induk: ' . $d['parent_div_name'] : 'Divisi Utama') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= $d['employee_count'] ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-division"
                                                data-id="<?= $d['id'] ?>"
                                                data-code="<?= e($d['division_code']) ?>"
                                                data-name="<?= e($d['division_name']) ?>"
                                                data-parentid="<?= $d['parent_id'] ?>"
                                                data-desc="<?= e($d['description']) ?>"
                                                title="Edit Divisi">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_division">
                                            <input type="hidden" name="division_id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Apakah Anda yakin ingin menghapus divisi '<?= e($d['division_name']) ?>'?" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL TAMBAH / EDIT JABATAN
     ======================================================== -->
<div class="modal fade" id="modalAddPosition" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_position">
                <input type="hidden" name="position_id" id="pos_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="posModalTitle">
                        <i class="bi bi-plus-circle me-1"></i> Form Jabatan
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Kode Jabatan <span class="text-danger">*</span></label>
                            <input type="text" name="position_code" id="pos_code" class="form-control" placeholder="MISAL: POS-SPV" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nama Jabatan <span class="text-danger">*</span></label>
                            <input type="text" name="position_name" id="pos_name" class="form-control" placeholder="Nama posisi jabatan" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Divisi Penempatan</label>
                            <select name="division_id" id="pos_division_id" class="form-select">
                                <option value="">-- Tanpa Divisi Khusus --</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Atasan Langsung (Hierarki)</label>
                            <select name="parent_position_id" id="pos_parent_id" class="form-select">
                                <option value="">-- Pucuk Pimpinan / Tidak Ada --</option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= e($p['position_name']) ?> (Level <?= $p['level_rank'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tingkat Level Rank</label>
                            <input type="number" name="level_rank" id="pos_level_rank" class="form-control" value="3" min="1" max="10">
                            <small class="text-muted" style="font-size: 0.72rem;">1: Komisaris, 2: Direksi, 3: Direktur, dst.</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Deskripsi Tugas (Jobdesc)</label>
                            <textarea name="job_description" id="pos_job_desc" class="form-control" rows="2" placeholder="Uraian singkat tanggung jawab..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Simpan Jabatan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL TAMBAH / EDIT DIVISI
     ======================================================== -->
<div class="modal fade" id="modalAddDivision" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_division">
                <input type="hidden" name="division_id" id="div_id" value="">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="divModalTitle">
                        <i class="bi bi-folder-plus me-1"></i> Form Divisi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Kode Divisi <span class="text-danger">*</span></label>
                            <input type="text" name="division_code" id="div_code" class="form-control" placeholder="MISAL: DIV-LOG" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nama Divisi <span class="text-danger">*</span></label>
                            <input type="text" name="division_name" id="div_name" class="form-control" placeholder="Nama divisi baru" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Divisi Induk (Opsional)</label>
                            <select name="parent_id" id="div_parent_id" class="form-select">
                                <option value="">-- Divisi Utama / Mandiri --</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold">Deskripsi Divisi</label>
                            <textarea name="description" id="div_desc" class="form-control" rows="2" placeholder="Ruang lingkup aktivitas divisi..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-4">Simpan Divisi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Edit Position Handler
    const posModal = new bootstrap.Modal(document.getElementById('modalAddPosition'));
    document.querySelectorAll('.btn-edit-position').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('posModalTitle').innerText = 'Edit Jabatan: ' + this.dataset.name;
            document.getElementById('pos_id').value = this.dataset.id;
            document.getElementById('pos_code').value = this.dataset.code;
            document.getElementById('pos_name').value = this.dataset.name;
            document.getElementById('pos_division_id').value = this.dataset.divid || '';
            document.getElementById('pos_parent_id').value = this.dataset.parentid || '';
            document.getElementById('pos_level_rank').value = this.dataset.level || 1;
            document.getElementById('pos_job_desc').value = this.dataset.desc || '';
            posModal.show();
        });
    });

    // Reset saat tambah baru
    document.getElementById('modalAddPosition').addEventListener('hidden.bs.modal', function () {
        document.getElementById('posModalTitle').innerText = 'Tambah Jabatan';
        document.getElementById('pos_id').value = '';
        document.getElementById('pos_code').value = '';
        document.getElementById('pos_name').value = '';
        document.getElementById('pos_division_id').value = '';
        document.getElementById('pos_parent_id').value = '';
        document.getElementById('pos_level_rank').value = '3';
        document.getElementById('pos_job_desc').value = '';
    });

    // 2. Edit Division Handler
    const divModal = new bootstrap.Modal(document.getElementById('modalAddDivision'));
    document.querySelectorAll('.btn-edit-division').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('divModalTitle').innerText = 'Edit Divisi: ' + this.dataset.name;
            document.getElementById('div_id').value = this.dataset.id;
            document.getElementById('div_code').value = this.dataset.code;
            document.getElementById('div_name').value = this.dataset.name;
            document.getElementById('div_parent_id').value = this.dataset.parentid || '';
            document.getElementById('div_desc').value = this.dataset.desc || '';
            divModal.show();
        });
    });

    // Reset saat tambah divisi baru
    document.getElementById('modalAddDivision').addEventListener('hidden.bs.modal', function () {
        document.getElementById('divModalTitle').innerText = 'Tambah Divisi';
        document.getElementById('div_id').value = '';
        document.getElementById('div_code').value = '';
        document.getElementById('div_name').value = '';
        document.getElementById('div_parent_id').value = '';
        document.getElementById('div_desc').value = '';
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
