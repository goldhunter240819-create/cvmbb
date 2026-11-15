<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Buku Agenda & Laporan Kegiatan Divisi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();

$db = Database::getInstance();

// -------------------------------------------------------------
// PROSES AKSI FORM (SIMPAN / EDIT / HAPUS)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/administration/activities.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. SIMPAN KEGIATAN DIVISI
    if ($action === 'save_activity') {
        $actId     = !empty($_POST['activity_id']) ? (int)$_POST['activity_id'] : null;
        $code      = trim($_POST['activity_code'] ?? '');
        $divId     = (int)($_POST['division_id'] ?? 0);
        $name      = trim($_POST['activity_name'] ?? '');
        $pic       = trim($_POST['person_in_charge'] ?? '');
        $location  = trim($_POST['location'] ?? '');
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $endDate   = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $cost      = floatval(str_replace(['Rp', '.', ' ', ','], '', $_POST['total_cost'] ?? 0));
        $income    = floatval(str_replace(['Rp', '.', ' ', ','], '', $_POST['total_income'] ?? 0));
        $status    = $_POST['status'] ?? 'RENCANA';
        $docUrl    = trim($_POST['documentation_url'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');

        if (empty($code) || !$divId || empty($name) || empty($pic)) {
            flash_set('danger', 'Kode, Divisi, Nama Kegiatan, dan Penanggung Jawab wajib diisi.');
        } else {
            if ($actId) {
                // Update
                $stmt = $db->prepare("
                    UPDATE division_activities 
                    SET activity_code = ?, division_id = ?, activity_name = ?, person_in_charge = ?, location = ?,
                        start_date = ?, end_date = ?, total_cost = ?, total_income = ?, status = ?, documentation_url = ?, notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $divId, $name, $pic, $location, $startDate, $endDate, $cost, $income, $status, $docUrl, $notes, $actId]);
                log_audit('UPDATE_ACTIVITY', 'activities', $actId, "Memperbarui kegiatan divisi: {$name} ({$code})");
                flash_set('success', "Kegiatan '{$name}' berhasil diperbarui.");
            } else {
                // Insert
                $stmt = $db->prepare("
                    INSERT INTO division_activities 
                    (activity_code, division_id, activity_name, person_in_charge, location, start_date, end_date, total_cost, total_income, status, documentation_url, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$code, $divId, $name, $pic, $location, $startDate, $endDate, $cost, $income, $status, $docUrl, $notes, Auth::id()]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_ACTIVITY', 'activities', $newId, "Menambahkan agenda kegiatan: {$name} ({$code})");
                flash_set('success', "Kegiatan '{$name}' berhasil ditambahkan ke agenda.");
            }
        }
        redirect('modules/administration/activities.php');
    }

    // 2. HAPUS KEGIATAN
    if ($action === 'delete_activity') {
        $actId = (int)($_POST['activity_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM division_activities WHERE id = ?");
        $stmt->execute([$actId]);
        log_audit('DELETE_ACTIVITY', 'activities', $actId, "Menghapus kegiatan divisi ID {$actId}");
        flash_set('success', 'Data kegiatan berhasil dihapus.');
        redirect('modules/administration/activities.php');
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$divisionFilter = (int)($_GET['division_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$query = "
    SELECT a.*, d.division_name, d.division_code
    FROM division_activities a
    LEFT JOIN divisions d ON a.division_id = d.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (a.activity_code LIKE ? OR a.activity_name LIKE ? OR a.person_in_charge LIKE ? OR a.location LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($divisionFilter > 0) {
    $query .= " AND a.division_id = ?";
    $params[] = $divisionFilter;
}

if ($statusFilter !== '') {
    $query .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY a.start_date DESC, a.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

$divisions = $db->query("SELECT * FROM divisions ORDER BY division_name ASC")->fetchAll();

// Generator Kode Kegiatan Otomatis
$maxIdStmt = $db->query("SELECT MAX(id) AS max_id FROM division_activities");
$nextActId = (int)$maxIdStmt->fetch()['max_id'] + 1;
$suggestedActCode = 'ACT/' . date('Ym') . '/' . str_pad($nextActId, 3, '0', STR_PAD_LEFT);

$pageTitle = 'Agenda & Kegiatan Divisi';
$currentModule = 'activities';
$pageCategory = 'MANAJEMEN OPERASIONAL LINTAS DIVISI';
$pageDesc = 'Pencatatan kegiatan, job dekorasi, operasional tata suara sound system, proyek operasional lapangan, dan rekap biaya serta pendapatan.';
$pageBreadcrumbs = [
    'Administrasi' => base_url('modules/administration/index.php'),
    'Kegiatan Divisi' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/administration/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Dashboard TU
        </a>
        <button type="button" class="btn btn-success text-white btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddActivity">
            <i class="bi bi-plus-circle-fill"></i> Tambah Agenda Kegiatan
        </button>
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- FILTER & PENCARIAN -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari kode kegiatan, nama acara, PIC, lokasi...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="division_id" class="form-select form-select-sm">
                    <option value="">-- Semua Divisi --</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= $divisionFilter === (int)$d['id'] ? 'selected' : '' ?>>
                            <?= e($d['division_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="RENCANA" <?= $statusFilter === 'RENCANA' ? 'selected' : '' ?>>Rencana</option>
                    <option value="BERJALAN" <?= $statusFilter === 'BERJALAN' ? 'selected' : '' ?>>Sedang Berjalan</option>
                    <option value="SELESAI" <?= $statusFilter === 'SELESAI' ? 'selected' : '' ?>>Selesai</option>
                    <option value="BATAL" <?= $statusFilter === 'BATAL' ? 'selected' : '' ?>>Batal</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== '' || $divisionFilter > 0 || $statusFilter !== ''): ?>
                    <a href="<?= base_url('modules/administration/activities.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL KEGIATAN DIVISI -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-success">
            <i class="bi bi-calendar2-check-fill"></i> Buku Log Kegiatan & Proyek Divisi
        </h6>
        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= count($activities) ?> Kegiatan</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Kode</th>
                        <th>Kegiatan & Acara</th>
                        <th>Divisi</th>
                        <th>Penanggung Jawab (PIC)</th>
                        <th>Waktu Pelaksanaan</th>
                        <th>Biaya & Pendapatan</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-calendar2-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Belum ada agenda kegiatan divisi tercatat.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-success"><?= e($act['activity_code']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($act['activity_name']) ?></div>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= e($act['location'] ?: 'Lokasi Acara') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($act['division_name']) ?></span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark small"><?= e($act['person_in_charge']) ?></div>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= format_tanggal($act['start_date']) ?></div>
                                    <?php if ($act['end_date']): ?>
                                        <small class="text-muted">s/d <?= format_tanggal($act['end_date']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small text-danger">Biaya: <?= format_rupiah($act['total_cost']) ?></div>
                                    <div class="small text-success fw-bold">Pemasukan: <?= format_rupiah($act['total_income']) ?></div>
                                </td>
                                <td>
                                    <?php
                                    $stBadge = match($act['status']) {
                                        'SELESAI' => 'bg-success-subtle text-success border border-success-subtle',
                                        'BERJALAN' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                        'BATAL' => 'bg-danger-subtle text-danger border border-danger-subtle',
                                        default => 'bg-warning-subtle text-warning border border-warning-subtle'
                                    };
                                    ?>
                                    <span class="badge <?= $stBadge ?>"><?= e($act['status']) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-edit-act"
                                                data-id="<?= $act['id'] ?>"
                                                data-code="<?= e($act['activity_code']) ?>"
                                                data-div="<?= $act['division_id'] ?>"
                                                data-name="<?= e($act['activity_name']) ?>"
                                                data-pic="<?= e($act['person_in_charge']) ?>"
                                                data-loc="<?= e($act['location']) ?>"
                                                data-start="<?= $act['start_date'] ?>"
                                                data-end="<?= $act['end_date'] ?>"
                                                data-cost="<?= $act['total_cost'] ?>"
                                                data-income="<?= $act['total_income'] ?>"
                                                data-status="<?= e($act['status']) ?>"
                                                data-url="<?= e($act['documentation_url']) ?>"
                                                data-notes="<?= e($act['notes']) ?>"
                                                title="Edit Kegiatan">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_activity">
                                            <input type="hidden" name="activity_id" value="<?= $act['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus kegiatan '<?= e($act['activity_name']) ?>'?" title="Hapus">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL TAMBAH / EDIT KEGIATAN DIVISI
     ======================================================== -->
<div class="modal fade" id="modalAddActivity" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_activity">
                <input type="hidden" name="activity_id" id="act_edit_id" value="">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="actModalTitle">
                        <i class="bi bi-calendar2-check-fill me-1"></i> Form Agenda Kegiatan Divisi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Kode Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="activity_code" id="act_code" class="form-control" value="<?= e($suggestedActCode) ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Divisi Penyelenggara <span class="text-danger">*</span></label>
                            <select name="division_id" id="act_division_id" class="form-select" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Nama Acara / Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" name="activity_name" id="act_name" class="form-control" placeholder="Contoh: Job Sound System Konser / Dekorasi Resepsi..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Penanggung Jawab (PIC) <span class="text-danger">*</span></label>
                            <input type="text" name="person_in_charge" id="act_pic" class="form-control" placeholder="Nama PJ Lapangan..." required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Lokasi Pelaksanaan</label>
                            <input type="text" name="location" id="act_location" class="form-control" placeholder="Nama gedung, desa, kecamatan...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="act_start_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tanggal Selesai</label>
                            <input type="date" name="end_date" id="act_end_date" class="form-control">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estimasi Biaya Operasional (Rp)</label>
                            <input type="number" name="total_cost" id="act_cost" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Estimasi Pendapatan / Tarif (Rp)</label>
                            <input type="number" name="total_income" id="act_income" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Status Pekerjaan</label>
                            <select name="status" id="act_status" class="form-select">
                                <option value="RENCANA">RENCANA</option>
                                <option value="BERJALAN">SEDANG BERJALAN</option>
                                <option value="SELESAI">SELESAI</option>
                                <option value="BATAL">BATAL</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tautan / Link Dokumentasi Foto (Google Drive / URL)</label>
                        <input type="url" name="documentation_url" id="act_url" class="form-control" placeholder="https://drive.google.com/...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Catatan & Rincian Perlengkapan</label>
                        <textarea name="notes" id="act_notes" class="form-control" rows="2" placeholder="Catatan logistik peralatan, daftar kru, atau evaluasi kegiatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-4 fw-bold">Simpan Kegiatan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const actModal = new bootstrap.Modal(document.getElementById('modalAddActivity'));

    document.querySelectorAll('.btn-edit-act').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('actModalTitle').innerText = 'Edit Kegiatan: ' + this.dataset.name;
            document.getElementById('act_edit_id').value = this.dataset.id;
            document.getElementById('act_code').value = this.dataset.code;
            document.getElementById('act_division_id').value = this.dataset.div;
            document.getElementById('act_name').value = this.dataset.name;
            document.getElementById('act_pic').value = this.dataset.pic;
            document.getElementById('act_location').value = this.dataset.loc || '';
            document.getElementById('act_start_date').value = this.dataset.start;
            document.getElementById('act_end_date').value = this.dataset.end || '';
            document.getElementById('act_cost').value = this.dataset.cost || 0;
            document.getElementById('act_income').value = this.dataset.income || 0;
            document.getElementById('act_status').value = this.dataset.status;
            document.getElementById('act_url').value = this.dataset.url || '';
            document.getElementById('act_notes').value = this.dataset.notes || '';
            actModal.show();
        });
    });

    document.getElementById('modalAddActivity').addEventListener('hidden.bs.modal', function () {
        document.getElementById('actModalTitle').innerText = 'Form Agenda Kegiatan Divisi';
        document.getElementById('act_edit_id').value = '';
        document.getElementById('act_code').value = '<?= e($suggestedActCode) ?>';
        document.getElementById('act_division_id').value = '';
        document.getElementById('act_name').value = '';
        document.getElementById('act_pic').value = '';
        document.getElementById('act_location').value = '';
        document.getElementById('act_start_date').value = '<?= date('Y-m-d') ?>';
        document.getElementById('act_end_date').value = '';
        document.getElementById('act_cost').value = '0';
        document.getElementById('act_income').value = '0';
        document.getElementById('act_status').value = 'RENCANA';
        document.getElementById('act_url').value = '';
        document.getElementById('act_notes').value = '';
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
