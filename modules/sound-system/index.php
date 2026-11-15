<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Divisi Sound Sistem (Penanggung Jawab: MISBAHUL MUNIR)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('sound_system.view');

$db = Database::getInstance();
$divisionId = 6; // Divisi Sound Sistem

// -------------------------------------------------------------
// POST HANDLER: KEGIATAN SOUND SISTEM
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_activity') {
        Permission::require('sound_system.manage');
        csrf_validate();

        $activityCode   = 'SND-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $activityName   = sanitize($_POST['activity_name'] ?? '');
        $personInCharge = sanitize($_POST['person_in_charge'] ?? 'MISBAHUL MUNIR');
        $location       = sanitize($_POST['location'] ?? '');
        $startDate      = sanitize($_POST['start_date'] ?? date('Y-m-d'));
        $endDate        = !empty($_POST['end_date']) ? sanitize($_POST['end_date']) : null;
        $totalIncome    = (float)($_POST['total_income'] ?? 0);
        $totalCost      = (float)($_POST['total_cost'] ?? 0);
        $status         = sanitize($_POST['status'] ?? 'RENCANA');
        $notes          = sanitize($_POST['notes'] ?? '');

        if ($activityName === '') {
            flash_set('danger', 'Nama acara / pekerjaan sound system wajib diisi.');
            redirect('modules/sound-system/index.php');
        }

        $stmt = $db->prepare("
            INSERT INTO division_activities (
                activity_code, division_id, activity_name, person_in_charge,
                location, start_date, end_date, total_cost, total_income,
                status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $activityCode, $divisionId, $activityName, $personInCharge,
            $location, $startDate, $endDate, $totalCost, $totalIncome,
            $status, $notes, Auth::id()
        ]);
        $actId = $db->lastInsertId();

        log_audit('CREATE_SOUND_JOB', 'sound-system', $actId, "Mencatat job sound system {$activityCode} - {$activityName}");
        flash_set('success', "Kegiatan sound system '{$activityName}' berhasil dicatat.");
        redirect('modules/sound-system/index.php');
    }

    if ($action === 'update_status') {
        Permission::require('sound_system.manage');
        csrf_validate();

        $actId       = (int)($_POST['activity_id'] ?? 0);
        $status      = sanitize($_POST['status'] ?? 'RENCANA');
        $totalIncome = (float)($_POST['total_income'] ?? 0);
        $totalCost   = (float)($_POST['total_cost'] ?? 0);

        $stmt = $db->prepare("UPDATE division_activities SET status = ?, total_income = ?, total_cost = ? WHERE id = ? AND division_id = ?");
        $stmt->execute([$status, $totalIncome, $totalCost, $actId, $divisionId]);

        log_audit('UPDATE_SOUND_STATUS', 'sound-system', $actId, "Update status job sound system ID {$actId} ke {$status}");
        flash_set('success', 'Status kegiatan berhasil diperbarui.');
        redirect('modules/sound-system/index.php');
    }

    if ($action === 'delete_activity') {
        Permission::require('sound_system.manage');
        csrf_validate();

        $actId = (int)($_POST['activity_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM division_activities WHERE id = ? AND division_id = ?");
        $stmt->execute([$actId, $divisionId]);

        log_audit('DELETE_SOUND_JOB', 'sound-system', $actId, "Menghapus kegiatan sound system ID {$actId}");
        flash_set('success', 'Kegiatan sound system berhasil dihapus.');
        redirect('modules/sound-system/index.php');
    }
}

// -------------------------------------------------------------
// FILTER & QUERY
// -------------------------------------------------------------
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$query = "
    SELECT * FROM division_activities
    WHERE division_id = ?
";
$params = [$divisionId];

if ($search !== '') {
    $query .= " AND (activity_name LIKE ? OR location LIKE ? OR person_in_charge LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

$query .= " ORDER BY start_date DESC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// KPI Metrics
$kpi = $db->prepare("
    SELECT 
        COUNT(*) AS total_jobs,
        COALESCE(SUM(total_income), 0) AS total_revenue,
        COALESCE(SUM(total_cost), 0) AS total_expense,
        COALESCE(SUM(CASE WHEN status IN ('RENCANA', 'BERJALAN') THEN 1 ELSE 0 END), 0) AS active_jobs
    FROM division_activities
    WHERE division_id = ?
");
$kpi->execute([$divisionId]);
$metrics = $kpi->fetch();

$netIncome = $metrics['total_revenue'] - $metrics['total_expense'];

$pageTitle = 'Divisi Sound Sistem';
$currentModule = 'sound-system';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Divisi Sound Sistem' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. KPI SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total Job Tata Suara</div>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= number_format($metrics['total_jobs']) ?></h3>
                </div>
                <div class="rounded-circle bg-primary-subtle text-primary p-3">
                    <i class="bi bi-speaker-fill fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Aktif/Berjalan: <strong><?= $metrics['active_jobs'] ?></strong> event</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Pendapatan Sewa Audio</div>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= format_rupiah($metrics['total_revenue']) ?></h3>
                </div>
                <div class="rounded-circle bg-success-subtle text-success p-3">
                    <i class="bi bi-cash-stack fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">Nilai sewa sound lapangan & acara</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Biaya Operator & Genset</div>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= format_rupiah($metrics['total_expense']) ?></h3>
                </div>
                <div class="rounded-circle bg-warning-subtle text-warning p-3">
                    <i class="bi bi-lightning-charge fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">BBM genset, transport & honor operator</div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-mbb p-3 h-100 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Laba Bersih Divisi</div>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= format_rupiah($netIncome) ?></h3>
                </div>
                <div class="rounded-circle bg-info-subtle text-info p-3">
                    <i class="bi bi-wallet2 fs-4"></i>
                </div>
            </div>
            <div class="mt-2 small text-muted">PJ Divisi: <strong>MISBAHUL MUNIR</strong></div>
        </div>
    </div>
</div>

<!-- 2. TOOLBAR & FILTER -->
<div class="card-mbb mb-4">
    <div class="card-mbb-body py-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-md-7">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari event, lokasi, penanggung jawab...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="RENCANA" <?= $statusFilter === 'RENCANA' ? 'selected' : '' ?>>Rencana / Booking</option>
                            <option value="BERJALAN" <?= $statusFilter === 'BERJALAN' ? 'selected' : '' ?>>Sedang Berjalan</option>
                            <option value="SELESAI" <?= $statusFilter === 'SELESAI' ? 'selected' : '' ?>>Selesai</option>
                            <option value="BATAL" <?= $statusFilter === 'BATAL' ? 'selected' : '' ?>>Batal</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($search !== '' || $statusFilter !== ''): ?>
                            <a href="<?= base_url('modules/sound-system/index.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="col-md-auto text-end">
                <?php if (Permission::can('sound_system.manage')): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreateActivity">
                        <i class="bi bi-plus-circle me-1"></i> Catat Job Sound Sistem Baru
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. TABEL DATA SOUND SISTEM -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-music-note-list"></i> Daftar Jadwal & Persewaan Sound Sistem MBB
        </h6>
        <span class="badge bg-primary-subtle text-primary border"><?= count($activities) ?> Acara</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Kode & Acara</th>
                        <th>Tanggal Pelaksanaan</th>
                        <th>Lokasi Acara</th>
                        <th>Penanggung Jawab</th>
                        <th>Pendapatan Sewa</th>
                        <th>Biaya Kru & Genset</th>
                        <th>Laba Bersih</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada agenda pekerjaan sound sistem. Klik <strong>Catat Job Sound Sistem Baru</strong> untuk memulai.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activities as $a): ?>
                            <?php $laba = $a['total_income'] - $a['total_cost']; ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark">
                                    <?= e($a['activity_name']) ?>
                                    <div class="small text-muted font-monospace"><?= e($a['activity_code']) ?></div>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($a['start_date'])) ?>
                                    <?php if (!empty($a['end_date']) && $a['end_date'] !== $a['start_date']): ?>
                                        <div class="small text-muted">s/d <?= date('d/m/Y', strtotime($a['end_date'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><i class="bi bi-geo-alt text-danger me-1"></i><?= e($a['location'] ?? '-') ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($a['person_in_charge']) ?></span></td>
                                <td class="fw-bold text-success"><?= format_rupiah($a['total_income']) ?></td>
                                <td class="text-muted"><?= format_rupiah($a['total_cost']) ?></td>
                                <td class="fw-bold <?= $laba >= 0 ? 'text-primary' : 'text-danger' ?>">
                                    <?= format_rupiah($laba) ?>
                                </td>
                                <td>
                                    <?php
                                    $stBadge = match($a['status']) {
                                        'RENCANA' => 'bg-info-subtle text-info border border-info',
                                        'BERJALAN' => 'bg-warning-subtle text-warning border border-warning',
                                        'SELESAI' => 'bg-success-subtle text-success border border-success',
                                        'BATAL' => 'bg-danger-subtle text-danger border border-danger',
                                        default => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $stBadge ?>"><?= e($a['status']) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-edit-activity"
                                                data-id="<?= $a['id'] ?>"
                                                data-name="<?= e($a['activity_name']) ?>"
                                                data-status="<?= e($a['status']) ?>"
                                                data-income="<?= $a['total_income'] ?>"
                                                data-cost="<?= $a['total_cost'] ?>"
                                                title="Edit Status / Biaya">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <?php if (Permission::can('sound_system.manage')): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_activity">
                                                <input type="hidden" name="activity_id" value="<?= $a['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                        data-confirm="Hapus agenda '<?= e($a['activity_name']) ?>'?" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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

<!-- 4. MODAL TAMBAH AGENDA SOUND SISTEM -->
<div class="modal fade" id="modalCreateActivity" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_activity">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-speaker-fill me-2"></i>Catat Acara / Persewaan Sound Sistem Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Nama Acara / Penyewa <span class="text-danger">*</span></label>
                        <input type="text" name="activity_name" class="form-control" placeholder="Cth: Sewa Sound Pengajian Akbar Lapangan Gunung Terang" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Penanggung Jawab</label>
                        <input type="text" name="person_in_charge" class="form-control" value="MISBAHUL MUNIR" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tanggal Mulai Acara <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tanggal Selesai (Opsional)</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Lokasi Acara</label>
                        <input type="text" name="location" class="form-control" placeholder="Cth: Lapangan Sepak Bola Air Hitam">
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <h6 class="fw-bold text-primary mb-0"><i class="bi bi-cash-stack"></i> Nilai Kontrak Sewa & Biaya Lapangan</h6>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nilai Sewa / Pendapatan (Rp)</label>
                        <input type="number" name="total_income" class="form-control" value="0" placeholder="Rp">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Biaya Kru, BBM & Genset (Rp)</label>
                        <input type="number" name="total_cost" class="form-control" value="0" placeholder="Rp">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status Awal</label>
                        <select name="status" class="form-select">
                            <option value="RENCANA" selected>RENCANA (Booking)</option>
                            <option value="BERJALAN">SEDANG BERJALAN</option>
                            <option value="SELESAI">SELESAI</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Catatan Perangkat / Spesifikasi Audio</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Cth: Paket Audio 10.000 Watt, Line Array 4 Box/Side, Subwoofer 18 inch 4 unit, Genset 25 KVA, Mic Wireless 4 unit"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-1"></i> Simpan Job Sound Sistem</button>
            </div>
        </form>
    </div>
</div>

<!-- 5. MODAL EDIT STATUS & BIAYA -->
<div class="modal fade" id="modalEditActivity" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="activity_id" id="edit_act_id">

            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="edit_modal_title">Update Status Acara</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Status Acara</label>
                    <select name="status" id="edit_act_status" class="form-select">
                        <option value="RENCANA">RENCANA / BOOKING</option>
                        <option value="BERJALAN">SEDANG BERJALAN</option>
                        <option value="SELESAI">SELESAI</option>
                        <option value="BATAL">BATAL</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Total Pendapatan Akhir (Rp)</label>
                    <input type="number" name="total_income" id="edit_act_income" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Total Biaya Operasional Akhir (Rp)</label>
                    <input type="number" name="total_cost" id="edit_act_cost" class="form-control">
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-edit-activity').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const st = this.getAttribute('data-status');
            const inc = this.getAttribute('data-income');
            const cst = this.getAttribute('data-cost');

            document.getElementById('edit_act_id').value = id;
            document.getElementById('edit_modal_title').textContent = 'Update: ' + name;
            document.getElementById('edit_act_status').value = st;
            document.getElementById('edit_act_income').value = inc;
            document.getElementById('edit_act_cost').value = cst;

            const modal = new bootstrap.Modal(document.getElementById('modalEditActivity'));
            modal.show();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
