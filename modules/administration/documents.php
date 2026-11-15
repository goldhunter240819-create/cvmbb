<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Arsip Dokumen Digital Perusahaan
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
// PROSES AKSI FORM
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Permission::require('administration.manage');

    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/administration/documents.php');
    }

    $action = $_POST['action'] ?? '';

    // 1. SIMPAN DOKUMEN BARU
    if ($action === 'save_document') {
        $docId    = !empty($_POST['document_id']) ? (int)$_POST['document_id'] : null;
        $docCode  = trim($_POST['doc_code'] ?? '');
        $title    = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'LEGALITAS';
        $desc     = trim($_POST['description'] ?? '');
        $filePath = null;
        $fileSize = 0;

        if (empty($docCode) || empty($title)) {
            flash_set('danger', 'Kode Dokumen dan Judul Dokumen wajib diisi.');
            redirect('modules/administration/documents.php');
        }

        // Upload berkas
        if (!empty($_FILES['doc_file']['name']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['doc_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];

            if (in_array($ext, $allowed)) {
                $filename = 'dokumen_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destDir = __DIR__ . '/../../uploads/documents/';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                if (move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                    $filePath = 'uploads/documents/' . $filename;
                    $fileSize = (int)$file['size'];
                }
            } else {
                flash_set('danger', 'Format berkas dokumen tidak didukung.');
                redirect('modules/administration/documents.php');
            }
        }

        if ($docId) {
            // Update
            if ($filePath) {
                $stmt = $db->prepare("
                    UPDATE documents 
                    SET doc_code = ?, title = ?, category = ?, description = ?, file_path = ?, file_size = ?
                    WHERE id = ?
                ");
                $stmt->execute([$docCode, $title, $category, $desc, $filePath, $fileSize, $docId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE documents 
                    SET doc_code = ?, title = ?, category = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$docCode, $title, $category, $desc, $docId]);
            }
            log_audit('UPDATE_DOCUMENT', 'administration', $docId, "Memperbarui arsip dokumen: {$title} ({$docCode})");
            flash_set('success', "Dokumen '{$title}' berhasil diperbarui.");
        } else {
            // Insert baru (file wajib ada jika baru)
            if (!$filePath) {
                flash_set('danger', 'Unggahan berkas dokumen wajib disertakan.');
                redirect('modules/administration/documents.php');
            }

            $stmt = $db->prepare("
                INSERT INTO documents (doc_code, title, category, file_path, file_size, description, uploaded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$docCode, $title, $category, $filePath, $fileSize, $desc, Auth::id()]);
            $newId = $db->lastInsertId();
            log_audit('CREATE_DOCUMENT', 'administration', $newId, "Mengunggah arsip dokumen: {$title} ({$docCode})");
            flash_set('success', "Dokumen '{$title}' berhasil diarsipkan.");
        }
        redirect('modules/administration/documents.php');
    }

    // 2. HAPUS DOKUMEN
    if ($action === 'delete_document') {
        $docId = (int)($_POST['document_id'] ?? 0);
        $stmt = $db->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['file_path'])) {
            $absPath = __DIR__ . '/../../' . $row['file_path'];
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }

        $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        log_audit('DELETE_DOCUMENT', 'administration', $docId, "Menghapus arsip dokumen ID {$docId}");
        flash_set('success', 'Dokumen berhasil dihapus dari arsip.');
        redirect('modules/administration/documents.php');
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$query = "
    SELECT d.*, u.username
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $query .= " AND (d.doc_code LIKE ? OR d.title LIKE ? OR d.description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($categoryFilter !== '') {
    $query .= " AND d.category = ?";
    $params[] = $categoryFilter;
}

$query .= " ORDER BY d.id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll();

// Generator Kode Dokumen Otomatis
$maxIdStmt = $db->query("SELECT MAX(id) AS max_id FROM documents");
$nextDocId = (int)$maxIdStmt->fetch()['max_id'] + 1;
$suggestedDocCode = 'DOC-MBB-' . str_pad($nextDocId, 3, '0', STR_PAD_LEFT);

$pageTitle = 'Arsip Dokumen Digital';
$currentModule = 'administration';
$pageCategory = 'TATA USAHA & PERSURATAN (PJ: NURIL HUDA)';
$pageDesc = 'Penyimpanan arsip digital dokumen legalitas perusahaan, akta, sertifikat mutu kopi, kontrak kerja karyawan, dan SOP operasional.';
$pageBreadcrumbs = [
    'Administrasi' => base_url('modules/administration/index.php'),
    'Arsip Dokumen' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/administration/index.php') . '" class="btn btn-outline-light btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Dashboard TU
        </a>' . 
        (Permission::can('administration.manage') ? '
        <button type="button" class="btn btn-info text-white btn-sm fw-bold shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAddDoc">
            <i class="bi bi-cloud-arrow-up-fill"></i> Upload Dokumen Baru
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
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control" placeholder="Cari kode dokumen, judul, atau deskripsi...">
                </div>
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select form-select-sm">
                    <option value="">-- Semua Kategori Dokumen --</option>
                    <option value="LEGALITAS" <?= $categoryFilter === 'LEGALITAS' ? 'selected' : '' ?>>Legalitas Perusahaan (NIB, Akta, NPWP)</option>
                    <option value="SERTIFIKASI" <?= $categoryFilter === 'SERTIFIKASI' ? 'selected' : '' ?>>Sertifikasi Mutu & Uji Lab Kopi</option>
                    <option value="KONTRAK" <?= $categoryFilter === 'KONTRAK' ? 'selected' : '' ?>>Kontrak Kerja & Perjanjian Kerjasama</option>
                    <option value="SOP" <?= $categoryFilter === 'SOP' ? 'selected' : '' ?>>Standar Operasional Prosedur (SOP)</option>
                    <option value="SK_DIREKSI" <?= $categoryFilter === 'SK_DIREKSI' ? 'selected' : '' ?>>Surat Keputusan (SK) Direksi</option>
                    <option value="LAINNYA" <?= $categoryFilter === 'LAINNYA' ? 'selected' : '' ?>>Lain-lain</option>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary px-3">Filter</button>
                <?php if ($search !== '' || $categoryFilter !== ''): ?>
                    <a href="<?= base_url('modules/administration/documents.php') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- TABEL DOKUMEN DIGITAL -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-info">
            <i class="bi bi-folder-fill"></i> Repositori Dokumen Perusahaan
        </h6>
        <span class="badge bg-info-subtle text-info border border-info-subtle"><?= count($documents) ?> Dokumen Tersimpan</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Kode</th>
                        <th>Judul Dokumen</th>
                        <th>Kategori</th>
                        <th>Ukuran Berkas</th>
                        <th>Waktu Unggah</th>
                        <th>Pengunggah</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                Belum ada berkas dokumen digital yang diunggah.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-primary"><?= e($doc['doc_code']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($doc['title']) ?></div>
                                    <?php if (!empty($doc['description'])): ?>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 280px;"><?= e($doc['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($doc['category']) ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?= round($doc['file_size'] / 1024, 1) ?> KB</small>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_tanggal($doc['created_at'], true) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= e($doc['username'] ?? 'System') ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url($doc['file_path']) ?>" target="_blank" class="btn btn-outline-success" title="Unduh / Buka Dokumen">
                                            <i class="bi bi-download"></i> Unduh
                                        </a>
                                        <?php if (Permission::can('administration.manage')): ?>
                                        <button type="button" class="btn btn-outline-primary btn-edit-doc"
                                                data-id="<?= $doc['id'] ?>"
                                                data-code="<?= e($doc['doc_code']) ?>"
                                                data-title="<?= e($doc['title']) ?>"
                                                data-cat="<?= e($doc['category']) ?>"
                                                data-desc="<?= e($doc['description']) ?>"
                                                title="Edit Metadata">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <form method="POST" action="" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_document">
                                            <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-confirm-delete"
                                                    data-confirm="Hapus dokumen '<?= e($doc['title']) ?>'?" title="Hapus">
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
     MODAL TAMBAH / EDIT DOKUMEN
     ======================================================== -->
<div class="modal fade" id="modalAddDoc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_document">
                <input type="hidden" name="document_id" id="doc_edit_id" value="">

                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="docModalTitle">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Form Arsip Dokumen Digital
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Kode Dokumen <span class="text-danger">*</span></label>
                            <input type="text" name="doc_code" id="doc_code" class="form-control" value="<?= e($suggestedDocCode) ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Kategori Dokumen <span class="text-danger">*</span></label>
                            <select name="category" id="doc_category" class="form-select" required>
                                <option value="LEGALITAS">Legalitas Perusahaan</option>
                                <option value="SERTIFIKASI">Sertifikasi & Uji Mutu Kopi</option>
                                <option value="KONTRAK">Kontrak & Perjanjian Kerjasama</option>
                                <option value="SOP">Standar Operasional (SOP)</option>
                                <option value="SK_DIREKSI">Surat Keputusan (SK) Direksi</option>
                                <option value="LAINNYA">Lain-lain</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Judul / Nama Dokumen <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="doc_title" class="form-control" placeholder="Contoh: Sertifikat Halal & Uji Cita Rasa Kopi Robusta..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Pilih Berkas Dokumen <span class="text-danger">*</span></label>
                        <input type="file" name="doc_file" id="doc_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                        <small class="text-muted">Mendukung PDF, Word, Excel, JPG, PNG, ZIP (Maks. 25 MB)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Deskripsi / Catatan Dokumen</label>
                        <textarea name="description" id="doc_description" class="form-control" rows="2" placeholder="Uraian singkat masa berlaku, nomor referensi, dsb..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-info text-white btn-sm px-4 fw-bold">Simpan Dokumen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const docModal = new bootstrap.Modal(document.getElementById('modalAddDoc'));

    document.querySelectorAll('.btn-edit-doc').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('docModalTitle').innerText = 'Edit Metadata: ' + this.dataset.title;
            document.getElementById('doc_edit_id').value = this.dataset.id;
            document.getElementById('doc_code').value = this.dataset.code;
            document.getElementById('doc_title').value = this.dataset.title;
            document.getElementById('doc_category').value = this.dataset.cat;
            document.getElementById('doc_description').value = this.dataset.desc || '';
            document.getElementById('doc_file').removeAttribute('required');
            docModal.show();
        });
    });

    document.getElementById('modalAddDoc').addEventListener('hidden.bs.modal', function () {
        document.getElementById('docModalTitle').innerText = 'Form Arsip Dokumen Digital';
        document.getElementById('doc_edit_id').value = '';
        document.getElementById('doc_code').value = '<?= e($suggestedDocCode) ?>';
        document.getElementById('doc_title').value = '';
        document.getElementById('doc_category').value = 'LEGALITAS';
        document.getElementById('doc_description').value = '';
        document.getElementById('doc_file').setAttribute('required', 'required');
    });
});
</script>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
