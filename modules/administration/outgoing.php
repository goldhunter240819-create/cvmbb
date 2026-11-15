<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Pengarsipan & Penerbitan Surat Keluar
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('administration.view');

$db = Database::getInstance();

// Konversi Angka Bulan ke Romawi
function get_romawi_month($monthNum) {
    $romawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];
    return $romawi[(int)$monthNum] ?? 'I';
}

// -------------------------------------------------------------
// PROSES AKSI FORM
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('administration.manage');

    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/administration/outgoing.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. SIMPAN / UPDATE SURAT KELUAR
    if ($action === 'save_letter') {
        $letterId   = !empty($_POST['letter_id']) ? (int)$_POST['letter_id'] : null;
        $letterNo   = trim($_POST['letter_number'] ?? '');
        $recipient  = trim($_POST['recipient'] ?? '');
        $letterDate = $_POST['letter_date'] ?? date('Y-m-d');
        $subject    = trim($_POST['subject'] ?? '');
        $signer     = trim($_POST['signer'] ?? get_company_setting('default_signer', 'M. ROZIKIN'));
        $status     = $_POST['status'] ?? 'DIKIRIM';
        $notes      = trim($_POST['notes'] ?? '');
        $filePath   = null;

        // Upload berkas lampiran jika ada
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($ext, $allowed)) {
                $filename = 'surat_keluar_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destDir = __DIR__ . '/../../uploads/letters/outgoing/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                    $filePath = 'uploads/letters/outgoing/' . $filename;
                }
            } else {
                flash_set('warning', 'Format berkas tidak didukung. Mengabaikan unggahan berkas.');
            }
        }

        if (empty($letterNo) || empty($recipient) || empty($subject)) {
            flash_set('danger', 'Nomor Surat, Penerima, dan Perihal wajib diisi.');
        } else {
            if ($letterId) {
                // Update
                if ($filePath) {
                    $stmt = $db->prepare("
                        UPDATE outgoing_letters 
                        SET letter_number = ?, recipient = ?, letter_date = ?, subject = ?, signer = ?, status = ?, notes = ?, file_path = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$letterNo, $recipient, $letterDate, $subject, $signer, $status, $notes, $filePath, $letterId]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE outgoing_letters 
                        SET letter_number = ?, recipient = ?, letter_date = ?, subject = ?, signer = ?, status = ?, notes = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$letterNo, $recipient, $letterDate, $subject, $signer, $status, $notes, $letterId]);
                }
                log_audit('UPDATE_OUTGOING_LETTER', 'administration', $letterId, "Memperbarui surat keluar: {$letterNo}");
                flash_set('success', "Surat keluar '{$letterNo}' berhasil diperbarui.");
            } else {
                // Insert
                $stmt = $db->prepare("
                    INSERT INTO outgoing_letters (letter_number, recipient, letter_date, subject, signer, status, file_path, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$letterNo, $recipient, $letterDate, $subject, $signer, $status, $filePath, $notes, Auth::id()]);
                $newId = $db->lastInsertId();
                log_audit('CREATE_OUTGOING_LETTER', 'administration', $newId, "Menerbitkan surat keluar nomor: {$letterNo} kepada {$recipient}");
                flash_set('success', "Surat keluar nomor '{$letterNo}' berhasil dicatat.");
            }
        }
        redirect('modules/administration/outgoing.php');
    }

    // 2. HAPUS SURAT KELUAR
    if ($action === 'delete_letter') {
        $letterId = (int)($_POST['letter_id'] ?? 0);
        $stmt = $db->prepare("SELECT file_path FROM outgoing_letters WHERE id = ?");
        $stmt->execute([$letterId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['file_path'])) {
            $absPath = __DIR__ . '/../../' . $row['file_path'];
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }

        $stmt = $db->prepare("DELETE FROM outgoing_letters WHERE id = ?");
        $stmt->execute([$letterId]);
        log_audit('DELETE_OUTGOING_LETTER', 'administration', $letterId, "Menghapus surat keluar ID {$letterId}");
        flash_set('success', 'Surat keluar berhasil dihapus.');
        redirect('modules/administration/outgoing.php');
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$yearFilter = trim($_GET['year'] ?? date('Y'));

$query = "SELECT * FROM outgoing_letters WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (letter_number LIKE ? OR recipient LIKE ? OR subject LIKE ? OR signer LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($statusFilter !== '') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}

if ($yearFilter !== '') {
    $query .= " AND YEAR(letter_date) = ?";
    $params[] = $yearFilter;
}

$query .= " ORDER BY letter_date DESC, id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$letters = $stmt->fetchAll();

// Generator Nomor Surat Otomatis Standar MBB
$currYear = date('Y');
$currMonthRomawi = get_romawi_month(date('n'));
$countThisYearStmt = $db->prepare("SELECT COUNT(id) FROM outgoing_letters WHERE YEAR(letter_date) = ?");
$countThisYearStmt->execute([$currYear]);
$nextSeq = (int)$countThisYearStmt->fetchColumn() + 1;
$suggestedLetterNo = str_pad($nextSeq, 3, '0', STR_PAD_LEFT) . '/MBB-DIR/' . $currMonthRomawi . '/' . $currYear;

$defaultSigner = get_company_setting('default_signer', 'M. ROZIKIN');
$defaultSignerTitle = get_company_setting('default_signer_title', 'Direktur');

$pageTitle = 'Arsip Surat Keluar';
$currentModule = 'administration';
$pageCategory = 'TATA USAHA & PERSURATAN (PJ: NURIL HUDA)';
$pageDesc = 'Penerbitan surat keluar resmi perusahaan, format penomoran otomatis berstandar, template surat berkop, dan riwayat pengiriman.';
$pageBreadcrumbs = [
    'Administrasi' => base_url('modules/administration/index.php'),
    'Surat Keluar' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/administration/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Dashboard TU
        </a>' . 
        (Permission::can('administration.manage') ? '
        <button type="button" class="btn btn-warning text-dark btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddOutgoing">
            <i class="bi bi-plus-circle-fill"></i> Buat Surat Keluar
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
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari nomor surat, tujuan, perihal...">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status Surat --</option>
                    <option value="DRAFT" <?= $statusFilter === 'DRAFT' ? 'selected' : '' ?>>Draft</option>
                    <option value="DIKIRIM" <?= $statusFilter === 'DIKIRIM' ? 'selected' : '' ?>>Dikirim</option>
                    <option value="DIARSIPKAN" <?= $statusFilter === 'DIARSIPKAN' ? 'selected' : '' ?>>Diarsipkan</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="year" class="form-select form-select-sm">
                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                        <option value="<?= $y ?>" <?= $yearFilter == $y ? 'selected' : '' ?>>Tahun <?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== '' || $statusFilter !== '' || $yearFilter != date('Y')): ?>
                    <a href="<?= base_url('modules/administration/outgoing.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL SURAT KELUAR -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-warning">
            <i class="bi bi-send-fill"></i> Buku Register Surat Keluar
        </h6>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><?= count($letters) ?> Surat Terbit</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No. Surat</th>
                        <th>Tanggal Terbit</th>
                        <th>Tujuan / Penerima</th>
                        <th>Perihal Surat</th>
                        <th>Penandatangan</th>
                        <th>Status</th>
                        <th>Dokumen</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($letters)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-envelope-paper fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Belum ada surat keluar yang terdaftar.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($letters as $l): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?= e($l['letter_number']) ?></td>
                                <td><?= format_tanggal($l['letter_date']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($l['recipient']) ?></div>
                                </td>
                                <td>
                                    <div class="text-dark"><?= e($l['subject']) ?></div>
                                    <?php if (!empty($l['notes'])): ?>
                                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i><?= e($l['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($l['signer']) ?></span>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = match($l['status']) {
                                        'DIKIRIM' => 'bg-success-subtle text-success border border-success-subtle',
                                        'DIARSIPKAN' => 'bg-secondary-subtle text-secondary border',
                                        default => 'bg-warning-subtle text-warning border border-warning-subtle'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= e($l['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($l['file_path']): ?>
                                        <a href="<?= base_url($l['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Buka Berkas Scan">
                                            <i class="bi bi-file-earmark-pdf"></i> Berkas
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/administration/print_letter.php?id=' . $l['id']) ?>" target="_blank" class="btn btn-outline-secondary" title="Cetak Format Resmi Berkop">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if (Permission::can('administration.manage')): ?>
                                        <button type="button" class="btn btn-outline-primary btn-edit-out"
                                                data-id="<?= $l['id'] ?>"
                                                data-number="<?= e($l['letter_number']) ?>"
                                                data-recipient="<?= e($l['recipient']) ?>"
                                                data-date="<?= $l['letter_date'] ?>"
                                                data-subject="<?= e($l['subject']) ?>"
                                                data-signer="<?= e($l['signer']) ?>"
                                                data-status="<?= e($l['status']) ?>"
                                                data-notes="<?= e($l['notes']) ?>"
                                                title="Edit Surat">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_letter">
                                            <input type="hidden" name="letter_id" value="<?= $l['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus surat keluar '<?= e($l['letter_number']) ?>'?" title="Hapus">
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

<!-- ========================================================
     MODAL TAMBAH / EDIT SURAT KELUAR
     ======================================================== -->
<div class="modal fade" id="modalAddOutgoing" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_letter">
                <input type="hidden" name="letter_id" id="out_letter_id" value="">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="outModalTitle">
                        <i class="bi bi-send-fill me-1"></i> Form Penerbitan Surat Keluar
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Nomor Surat Resmi <span class="text-danger">*</span></label>
                            <input type="text" name="letter_number" id="out_letter_number" class="form-control" value="<?= e($suggestedLetterNo) ?>" required>
                            <small class="text-muted">Nomor surat otomatis sesuai standar format MBB</small>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Tanggal Surat <span class="text-danger">*</span></label>
                            <input type="date" name="letter_date" id="out_letter_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tujuan / Nama Penerima Surat <span class="text-danger">*</span></label>
                        <input type="text" name="recipient" id="out_recipient" class="form-control" placeholder="Kepada Yth. Pimpinan PT / Kelompok Tani / Rekanan..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Perihal / Hal Surat <span class="text-danger">*</span></label>
                        <input type="text" name="subject" id="out_subject" class="form-control" placeholder="Contoh: Penawaran Harga Biji Kopi / Undangan Koordinasi..." required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Pejabat Penandatangan</label>
                            <input type="text" name="signer" id="out_signer" class="form-control" value="<?= e($defaultSigner) ?> (<?= e($defaultSignerTitle) ?>)" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Status Surat</label>
                            <select name="status" id="out_status" class="form-select">
                                <option value="DIKIRIM">DIKIRIM (Resmi Telah Terbit)</option>
                                <option value="DRAFT">DRAFT (Konsep Surat)</option>
                                <option value="DIARSIPKAN">DIARSIPKAN</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Upload Berkas Scan / Lampiran</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Scan berkas yang sudah bertandatangan & cap (opsional)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Catatan / Keterangan Ekspedisi</label>
                            <input type="text" name="notes" id="out_notes" class="form-control" placeholder="Resi kurir, kurir internal, konfirmasi penerimaan...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm px-4 fw-bold">Simpan & Terbitkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const outModal = new bootstrap.Modal(document.getElementById('modalAddOutgoing'));

    document.querySelectorAll('.btn-edit-out').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('outModalTitle').innerText = 'Edit Surat Keluar: ' + this.dataset.number;
            document.getElementById('out_letter_id').value = this.dataset.id;
            document.getElementById('out_letter_number').value = this.dataset.number;
            document.getElementById('out_recipient').value = this.dataset.recipient;
            document.getElementById('out_letter_date').value = this.dataset.date;
            document.getElementById('out_subject').value = this.dataset.subject;
            document.getElementById('out_signer').value = this.dataset.signer;
            document.getElementById('out_status').value = this.dataset.status;
            document.getElementById('out_notes').value = this.dataset.notes || '';
            outModal.show();
        });
    });

    document.getElementById('modalAddOutgoing').addEventListener('hidden.bs.modal', function () {
        document.getElementById('outModalTitle').innerText = 'Form Penerbitan Surat Keluar';
        document.getElementById('out_letter_id').value = '';
        document.getElementById('out_letter_number').value = '<?= e($suggestedLetterNo) ?>';
        document.getElementById('out_recipient').value = '';
        document.getElementById('out_letter_date').value = '<?= date('Y-m-d') ?>';
        document.getElementById('out_subject').value = '';
        document.getElementById('out_signer').value = '<?= e($defaultSigner) ?> (<?= e($defaultSignerTitle) ?>)';
        document.getElementById('out_status').value = 'DIKIRIM';
        document.getElementById('out_notes').value = '';
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
