<?php
/**
 * MBB MOBILE APK - Persuratan Resmi
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Surat Resmi';
$currentApkNav   = 'home';

require_once __DIR__ . '/layout/header.php';

// Handle Tambah Surat Keluar dari Mobile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_outgoing') {
    $seq = $db->query("SELECT COUNT(*) FROM outgoing_letters WHERE YEAR(letter_date) = YEAR(CURDATE())")->fetchColumn() + 1;
    $romanMonths = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    $monthRoman = $romanMonths[(int)date('n')];
    $letNo     = sprintf('%03d/MBB-DIR/%s/%s', $seq, $monthRoman, date('Y'));
    $recipient = trim($_POST['recipient'] ?? '');
    $letDate   = $_POST['letter_date'] ?? date('Y-m-d');
    $subject   = trim($_POST['subject'] ?? '');
    $signer    = trim($_POST['signer'] ?? 'M. ROZIKIN (Direktur)');
    $notes     = trim($_POST['notes'] ?? '');

    try {
        $stmtIns = $db->prepare("
            INSERT INTO outgoing_letters 
            (letter_number, recipient, letter_date, subject, signer, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, 'DIKIRIM', ?, ?)
        ");
        $stmtIns->execute([$letNo, $recipient, $letDate, $subject, $signer, $notes, $user['id']]);
        flash_set('success', 'Surat keluar ' . $letNo . ' berhasil dicatat!');
        redirect('apk/letters.php');
    } catch (Exception $e) {
        $errorMsg = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

// Ambil Surat Keluar & Masuk
$outgoing = $db->query("SELECT * FROM outgoing_letters ORDER BY id DESC LIMIT 15")->fetchAll();
$incoming = $db->query("SELECT * FROM incoming_letters ORDER BY id DESC LIMIT 15")->fetchAll();
?>

<div class="p-3">
    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="fw-bold text-dark" style="font-size: 0.95rem;">Arsip & Persuratan</div>
            <div class="text-muted small" style="font-size: 0.72rem;">Tata Usaha CV. MBB Air Hitam</div>
        </div>
        <button class="btn btn-sm btn-dark rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddLetter">
            <i class="bi bi-file-earmark-plus me-1"></i>Buat Nomor
        </button>
    </div>

    <!-- Nav Tabs Mobile -->
    <ul class="nav nav-pills nav-fill mb-3 bg-white p-1 rounded-pill shadow-sm" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill py-2 small fw-semibold" id="pills-out-tab" data-bs-toggle="pill" data-bs-target="#pills-out" type="button" role="tab">
                <i class="bi bi-send-fill me-1 text-primary"></i>Surat Keluar (<?= count($outgoing) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill py-2 small fw-semibold" id="pills-in-tab" data-bs-toggle="pill" data-bs-target="#pills-in" type="button" role="tab">
                <i class="bi bi-inbox-fill me-1 text-success"></i>Surat Masuk (<?= count($incoming) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        <!-- Tab Surat Keluar -->
        <div class="tab-pane fade show active" id="pills-out" role="tabpanel">
            <?php if (empty($outgoing)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-text display-4 d-block mb-2 text-secondary opacity-50"></i>
                    <p class="small">Belum ada surat keluar terdaftar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($outgoing as $out): ?>
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-primary-subtle text-primary font-monospace" style="font-size: 0.7rem;"><?= e($out['letter_number']) ?></span>
                                <span class="badge bg-success-subtle text-success" style="font-size: 0.65rem;"><?= e($out['status']) ?></span>
                            </div>
                            <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">
                                <?= e($out['subject']) ?>
                            </div>
                            <div class="text-muted small mb-2" style="font-size: 0.72rem;">
                                <i class="bi bi-building me-1"></i>Penerima: <b><?= e($out['recipient']) ?></b><br>
                                <i class="bi bi-calendar-event me-1"></i>Tgl: <?= date('d M Y', strtotime($out['letter_date'])) ?> &bull; TTD: <?= e($out['signer']) ?>
                            </div>
                            <?php if (!empty($out['notes'])): ?>
                                <div class="p-2 bg-light rounded text-muted small" style="font-size: 0.7rem;">
                                    <?= e($out['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Tab Surat Masuk -->
        <div class="tab-pane fade" id="pills-in" role="tabpanel">
            <?php if (empty($incoming)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox display-4 d-block mb-2 text-secondary opacity-50"></i>
                    <p class="small">Belum ada surat masuk terdaftar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($incoming as $inc): ?>
                    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="badge bg-secondary-subtle text-dark font-monospace" style="font-size: 0.7rem;">Agenda: <?= e($inc['agenda_number']) ?></span>
                                <span class="text-muted" style="font-size: 0.65rem;"><?= date('d/m/Y', strtotime($inc['received_date'])) ?></span>
                            </div>
                            <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">
                                <?= e($inc['subject']) ?>
                            </div>
                            <div class="text-muted small mb-1" style="font-size: 0.72rem;">
                                Pengirim: <b><?= e($inc['sender']) ?></b> (No: <?= e($inc['letter_number']) ?>)
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Buat Nomor Surat Baru -->
<div class="modal fade" id="modalAddLetter" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <input type="hidden" name="action" value="add_outgoing">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-file-earmark-plus-fill text-dark me-2"></i>Buat Nomor Surat Keluar</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Penerima Surat (Instansi / Perorangan)</label>
                    <input type="text" name="recipient" class="form-control form-control-sm" placeholder="Contoh: Dinas Perkebunan Kab. Lampung Barat" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Perihal / Isi Ringkas</label>
                    <input type="text" name="subject" class="form-control form-control-sm" placeholder="Contoh: Permohonan Sertifikasi Indikasi Geografis Kopi Robusta" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tanggal Surat</label>
                        <input type="date" name="letter_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Penandatangan</label>
                        <input type="text" name="signer" class="form-control form-control-sm" value="M. ROZIKIN (Direktur)" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Catatan / Keterangan</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Lampiran proposal 5 lembar">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-dark px-3">Terbitkan Nomor</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
