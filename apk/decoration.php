<?php
/**
 * MBB MOBILE APK - Divisi Dekorasi
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Divisi Dekorasi';
$currentApkNav   = 'home';

require_once __DIR__ . '/layout/header.php';

// Handle Tambah Agenda Dekorasi dari Mobile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $actCode    = 'DEK-' . date('Ymd') . '-' . rand(100, 999);
    $name       = trim($_POST['activity_name'] ?? '');
    $pic        = trim($_POST['pic'] ?? 'FATKHUR ROHMAN');
    $location   = trim($_POST['location'] ?? '');
    $startDate  = $_POST['start_date'] ?? date('Y-m-d');
    $income     = (float)($_POST['income'] ?? 0);
    $cost       = (float)($_POST['cost'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');

    try {
        $stmtIns = $db->prepare("
            INSERT INTO division_activities 
            (activity_code, division_id, activity_name, person_in_charge, location, start_date, end_date, total_income, total_cost, status, notes, created_by)
            VALUES (?, 5, ?, ?, ?, ?, ?, ?, ?, 'BERJALAN', ?, ?)
        ");
        $stmtIns->execute([$actCode, $name, $pic, $location, $startDate, $startDate, $income, $cost, $notes, $user['id']]);
        flash_set('success', 'Agenda dekorasi ' . $actCode . ' berhasil disimpan!');
        redirect('apk/decoration.php');
    } catch (Exception $e) {
        $errorMsg = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

// Ambil Kegiatan Dekorasi
$events = $db->query("
    SELECT * FROM division_activities 
    WHERE division_id = 5 
    ORDER BY start_date DESC LIMIT 20
")->fetchAll();

$totalIncomeDek = array_sum(array_column($events, 'total_income'));
$totalCostDek   = array_sum(array_column($events, 'total_cost'));
$netDek         = $totalIncomeDek - $totalCostDek;
?>

<div class="p-3">
    <!-- Summary Header -->
    <div class="card border-0 text-white p-3 mb-3 shadow-sm" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); border-radius: 18px;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-white-50 small">Layanan Dekorasi Acara</span>
            <span class="badge bg-white text-dark"><i class="bi bi-flower1 me-1"></i>DIV-DEK</span>
        </div>
        <div class="text-white-50" style="font-size: 0.72rem;">TOTAL PENDAPATAN DEKOR</div>
        <h4 class="fw-bold mb-2"><?= format_rupiah($totalIncomeDek) ?></h4>
        <div class="d-flex justify-content-between pt-2 border-top border-white border-opacity-25" style="font-size: 0.75rem;">
            <span>Biaya Ops: <b><?= format_rupiah($totalCostDek) ?></b></span>
            <span>Laba Bersih: <b class="text-warning"><?= format_rupiah($netDek) ?></b></span>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="fw-bold text-dark" style="font-size: 0.88rem;">Agenda Dekorasi Lapangan</span>
        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAddEvent">
            <i class="bi bi-plus-circle me-1"></i>Agenda Baru
        </button>
    </div>

    <!-- Event Cards -->
    <?php if (empty($events)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-flower1 display-4 d-block mb-2 text-secondary opacity-50"></i>
            <p class="small">Belum ada agenda dekorasi tersimpan.</p>
        </div>
    <?php else: ?>
        <?php foreach ($events as $ev): ?>
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="badge bg-purple-subtle text-purple font-monospace" style="background: #faf5ff; color: #9333ea;"><?= e($ev['activity_code']) ?></span>
                        <span class="badge <?= $ev['status'] === 'SELESAI' ? 'bg-success' : ($ev['status'] === 'BERJALAN' ? 'bg-info text-dark' : 'bg-secondary') ?>" style="font-size: 0.65rem;">
                            <?= e($ev['status']) ?>
                        </span>
                    </div>

                    <div class="fw-bold text-dark mb-1" style="font-size: 0.85rem;">
                        <?= e($ev['activity_name']) ?>
                    </div>
                    <div class="text-muted small mb-2" style="font-size: 0.72rem;">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i><?= e($ev['location'] ?: 'Lokasi Belum Ditentukan') ?><br>
                        <i class="bi bi-calendar-event me-1"></i><?= date('d M Y', strtotime($ev['start_date'])) ?> &bull; PIC: <span class="fw-semibold text-dark"><?= e($ev['person_in_charge']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top" style="font-size: 0.75rem;">
                        <div>
                            <span class="text-muted d-block" style="font-size: 0.68rem;">Tarif Kontrak:</span>
                            <span class="fw-bold text-success"><?= format_rupiah($ev['total_income']) ?></span>
                        </div>
                        <div class="text-end">
                            <span class="text-muted d-block" style="font-size: 0.68rem;">Estimasi Laba:</span>
                            <span class="fw-bold text-primary"><?= format_rupiah($ev['total_income'] - $ev['total_cost']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Agenda Dekorasi Baru -->
<div class="modal fade" id="modalAddEvent" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow" style="border-radius: 20px;">
            <input type="hidden" name="action" value="add_event">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-flower1 text-purple me-2" style="color: #9333ea;"></i>Catat Acara Dekorasi</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Nama Acara / Klien</label>
                    <input type="text" name="activity_name" class="form-control form-control-sm" placeholder="Contoh: Resepsi Pernikahan An. Rizky & Linda" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Lokasi Acara</label>
                    <input type="text" name="location" class="form-control form-control-sm" placeholder="Contoh: Dusun Sukamaju, Air Hitam" required>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tanggal Pasang</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Penanggung Jawab (PIC)</label>
                        <input type="text" name="pic" class="form-control form-control-sm" value="FATKHUR ROHMAN" required>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Tarif Jasa (Rp)</label>
                        <input type="number" name="income" class="form-control form-control-sm" placeholder="5000000" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Biaya Ops/Bunga (Rp)</label>
                        <input type="number" name="cost" class="form-control form-control-sm" placeholder="1500000" value="0">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Catatan Paket / Ukuran Tenda</label>
                    <input type="text" name="notes" class="form-control form-control-sm" placeholder="Paket Pelaminan Rustic 8 meter, tenda 2 plong">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary px-3">Simpan Acara</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
