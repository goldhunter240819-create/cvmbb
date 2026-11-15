<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Pengarsipan Surat Masuk
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('administration.view');

$db = Database::getInstance();

// -------------------------------------------------------------
// PROSES AKSI FORM (SIMPAN / EDIT / HAPUS)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('administration.manage');

    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/administration/incoming.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. SIMPAN / UPDATE SURAT MASUK
    if ($action === 'save_letter') {
        $letterId    = !empty($_POST['letter_id']) ? (int)$_POST['letter_id'] : null;
        $agendaNo    = trim($_POST['agenda_number'] ?? '');
        $letterNo    = trim($_POST['letter_number'] ?? '');
        $sender      = trim($_POST['sender'] ?? '');
        $letterDate  = $_POST['letter_date'] ?? date('Y-m-d');
        $receivedDate= $_POST['received_date'] ?? date('Y-m-d');
        $subject     = trim($_POST['subject'] ?? '');
        $notes       = trim($_POST['notes'] ?? '');
        $filePath    = null;

        // Upload berkas lampiran jika ada
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($ext, $allowed)) {
                $filename = 'surat_masuk_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destDir = __DIR__ . '/../../uploads/letters/incoming/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                    $filePath = 'uploads/letters/incoming/' . $filename;
                }
            } else {
                flash_set('warning', 'Format berkas lampiran tidak didukung (gunakan PDF/JPG/PNG). Berkas diabaikan.');
            }
        }

        if (empty($agendaNo) || empty($letterNo) || empty($sender) || empty($subject)) {
            flash_set('danger', 'Nomor Agenda, Nomor Surat, Pengirim, dan Perihal wajib diisi.');
        } else {
            if ($letterId) {
                // Update
                if ($filePath) {
                    $stmt = $db->prepare("
                        UPDATE incoming_letters 
                        SET agenda_number = ?, letter_number = ?, sender = ?, letter_date = ?, received_date = ?, subject = ?, notes = ?, file_path = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$agendaNo, $letterNo, $sender, $letterDate, $receivedDate, $subject, $notes, $filePath, $letterId]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE incoming_letters 
                        SET agenda_number = ?, letter_number = ?, sender = ?, letter_date = ?, received_date = ?, subject = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$agendaNo, $letterNo, $sender, $letterDate, $receivedDate, $subject, $notes, $letterId]);
                }
                log_audit('UPDATE_INCOMING_LETTER', 'administration', $letterId, "Memperbarui surat masuk no agenda: {$agendaNo}");
                flash_set('success', "Surat masuk no agenda '{$agendaNo}' berhasil diperbarui.");
            } else {
                // Insert
                $stmt = $db->prepare("
                    INSERT INTO incoming_letters (agenda_number, letter_number, sender, letter_date, received_date, subject, file_path, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$agendaNo, $letterNo, $sender, $letterDate, $receivedDate, $subject, $filePath, $notes, Auth::id()]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_INCOMING_LETTER', 'administration', $newId, "Menambahkan surat masuk baru no agenda: {$agendaNo} dari {$sender}");
                flash_set('success', "Surat masuk berhasil dicatat dengan Nomor Agenda: {$agendaNo}");
            }
        }
        redirect('modules/administration/incoming.php');
    }

    // 2. HAPUS SURAT MASUK
    if ($action === 'delete_letter') {
        $letterId = (int)($_POST['letter_id'] ?? 0);
        // Hapus berkas jika ada
        $stmt = $db->prepare("SELECT file_path FROM incoming_letters WHERE id = ?");
        $stmt->execute([$letterId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['file_path'])) {
            $absPath = __DIR__ . '/../../' . $row['file_path'];
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }

        $stmt = $db->prepare("DELETE FROM incoming_letters WHERE id = ?");
        $stmt->execute([$letterId]);
        log_audit('DELETE_INCOMING_LETTER', 'administration', $letterId, "Menghapus surat masuk ID {$letterId}");
        flash_set('success', 'Surat masuk berhasil dihapus dari arsip.');
        redirect('modules/administration/incoming.php');
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$yearFilter = trim($_GET['year'] ?? date('Y'));

$query = "SELECT * FROM incoming_letters WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (agenda_number LIKE ? OR letter_number LIKE ? OR sender LIKE ? OR subject LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($yearFilter !== '') {
    $query .= " AND YEAR(received_date) = ?";
    $params[] = $yearFilter;
}

$query .= " ORDER BY received_date DESC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$letters = $stmt->fetchAll();

// Generate Next Agenda Number
$maxIdStmt = $db->query("SELECT MAX(id) AS max_id FROM incoming_letters");
$nextAgendaId = (int)$maxIdStmt->fetch()['max_id'] + 1;
$suggestedAgenda = 'AGD/' . date('Y') . '/' . str_pad($nextAgendaId, 3, '0', STR_PAD_LEFT);

$pageTitle = 'Arsip Surat Masuk';
$currentModule = 'administration';
$pageCategory = 'TATA USAHA & PERSURATAN (PJ: NURIL HUDA)';
$pageDesc = 'Buku agenda surat masuk, pencatatan asal pengirim, tanggal penerimaan, ringkasan perihal, dan scan berkas lampiran digital.';
$pageBreadcrumbs = [
    'Administrasi' => base_url('modules/administration/index.php'),
    'Surat Masuk' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/administration/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Dashboard TU
        </a>' . 
        (Permission::can('administration.manage') ? '
        <button type="button" class="btn btn-light btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddLetter">
            <i class="bi bi-plus-circle-fill text-primary"></i> Catat Surat Masuk
        </button>' : '') . '
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
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari no agenda, no surat asal, pengirim, atau perihal...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="year" class="form-select form-select-sm">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== '' || $yearFilter != date('Y')): ?>
                    <a href="<?= base_url('modules/administration/incoming.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL SURAT MASUK -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-inbox-fill"></i> Buku Agenda Surat Masuk
        </h6>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= count($letters) ?> Surat Terdaftar</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Agenda</th>
                        <th>Nomor & Tanggal Asal</th>
                        <th>Instansi Pengirim</th>
                        <th>Perihal Surat</th>
                        <th>Tanggal Masuk</th>
                        <th>Lampiran</th>
                        <?php if (Permission::can('administration.manage')): ?>
                        <th class="text-end pe-4">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($letters)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Belum ada arsip surat masuk yang cocok dengan kriteria pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($letters as $l): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= e($l['agenda_number']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($l['letter_number']) ?></div>
                                    <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= format_tanggal($l['letter_date']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($l['sender']) ?></div>
                                </td>
                                <td>
                                    <div class="text-dark"><?= e($l['subject']) ?></div>
                                    <?php if (!empty($l['notes'])): ?>
                                        <small class="text-secondary"><i class="bi bi-sticky me-1"></i><?= e($l['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= format_tanggal($l['received_date']) ?></span>
                                </td>
                                <td>
                                    <?php if ($l['file_path']): ?>
                                        <a href="<?= base_url($l['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Buka / Download File">
                                            <i class="bi bi-paperclip me-1"></i> Berkas
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Tanpa Berkas</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (Permission::can('administration.manage')): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary btn-edit-letter"
                                                data-id="<?= $l['id'] ?>"
                                                data-agenda="<?= e($l['agenda_number']) ?>"
                                                data-number="<?= e($l['letter_number']) ?>"
                                                data-sender="<?= e($l['sender']) ?>"
                                                data-letterdate="<?= $l['letter_date'] ?>"
                                                data-recvdate="<?= $l['received_date'] ?>"
                                                data-subject="<?= e($l['subject']) ?>"
                                                data-notes="<?= e($l['notes']) ?>"
                                                title="Edit Data Surat">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_letter">
                                            <input type="hidden" name="letter_id" value="<?= $l['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus surat masuk no agenda '<?= e($l['agenda_number']) ?>'?" title="Hapus">
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

<!-- ========================================================
     MODAL TAMBAH / EDIT SURAT MASUK
     ======================================================== -->
<div class="modal fade" id="modalAddLetter" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_letter">
                <input type="hidden" name="letter_id" id="edit_letter_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle">
                        <i class="bi bi-inbox-fill me-1"></i> Form Pencatatan Surat Masuk
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Nomor Agenda <span class="text-danger">*</span></label>
                            <input type="text" name="agenda_number" id="agenda_number" class="form-control" value="<?= e($suggestedAgenda) ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nomor Surat Asal <span class="text-danger">*</span></label>
                            <input type="text" name="letter_number" id="letter_number" class="form-control" placeholder="Contoh: 045/PAN-KOP/VI/2026" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pengirim / Instansi Asal <span class="text-danger">*</span></label>
                        <input type="text" name="sender" id="sender" class="form-control" placeholder="Nama dinas, kelompok tani, supplier, bank, dsb..." required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Surat Asal <span class="text-danger">*</span></label>
                            <input type="date" name="letter_date" id="letter_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Diterima di Kantor <span class="text-danger">*</span></label>
                            <input type="date" name="received_date" id="received_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Perihal / Pokok Isi Surat <span class="text-danger">*</span></label>
                        <textarea name="subject" id="subject" class="form-control" rows="2" placeholder="Uraian ringkas maksud dan tujuan surat..." required></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Upload Scan Lampiran Berkas</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Maksimal 10 MB (Format PDF, JPG, PNG)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Catatan Disposisi / Keterangan</label>
                            <input type="text" name="notes" id="notes" class="form-control" placeholder="Instruksi direksi atau tujuan disposisi divisi...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">Simpan Surat Masuk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const letterModal = new bootstrap.Modal(document.getElementById('modalAddLetter'));

    document.querySelectorAll('.btn-edit-letter').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('modalTitle').innerText = 'Edit Surat Masuk: ' + this.dataset.agenda;
            document.getElementById('edit_letter_id').value = this.dataset.id;
            document.getElementById('agenda_number').value = this.dataset.agenda;
            document.getElementById('letter_number').value = this.dataset.number;
            document.getElementById('sender').value = this.dataset.sender;
            document.getElementById('letter_date').value = this.dataset.letterdate;
            document.getElementById('received_date').value = this.dataset.recvdate;
            document.getElementById('subject').value = this.dataset.subject;
            document.getElementById('notes').value = this.dataset.notes || '';
            letterModal.show();
        });
    });

    document.getElementById('modalAddLetter').addEventListener('hidden.bs.modal', function () {
        document.getElementById('modalTitle').innerText = 'Form Pencatatan Surat Masuk';
        document.getElementById('edit_letter_id').value = '';
        document.getElementById('agenda_number').value = '<?= e($suggestedAgenda) ?>';
        document.getElementById('letter_number').value = '';
        document.getElementById('sender').value = '';
        document.getElementById('letter_date').value = '<?= date('Y-m-d') ?>';
        document.getElementById('received_date').value = '<?= date('Y-m-d') ?>';
        document.getElementById('subject').value = '';
        document.getElementById('notes').value = '';
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
