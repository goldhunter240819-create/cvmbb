<?php
/**
 * MBB MOBILE APK - Profil Pengguna & Pengaturan
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Profil Pengguna';
$currentApkNav   = 'profile';

require_once __DIR__ . '/layout/header.php';

// Handle Ganti Password
$successMsg = '';
$errorMsg   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_pass') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass)) {
        $errorMsg = 'Harap isi semua kolom kata sandi.';
    } elseif ($newPass !== $confirmPass) {
        $errorMsg = 'Konfirmasi kata sandi baru tidak cocok.';
    } elseif (strlen($newPass) < 5) {
        $errorMsg = 'Kata sandi baru minimal 5 karakter.';
    } else {
        $stmtU = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmtU->execute([$user['id']]);
        $rowU = $stmtU->fetch();

        if ($rowU && password_verify($currentPass, $rowU['password_hash'])) {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmtUpd = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtUpd->execute([$newHash, $user['id']]);
            $successMsg = 'Kata sandi berhasil diperbarui!';
        } else {
            $errorMsg = 'Kata sandi lama Anda salah.';
        }
    }
}
?>

<div class="p-3">
    <!-- User Info Card -->
    <div class="card border-0 text-white p-3 mb-3 shadow-sm" style="background: linear-gradient(135deg, #0d1e3a 0%, #1e3a8a 100%); border-radius: 20px;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold bg-primary text-white border border-2 border-white shadow-sm" style="width: 56px; height: 56px; font-size: 1.35rem; flex-shrink: 0;">
                <?= strtoupper(substr($user['full_name'] ?: $user['username'], 0, 2)) ?>
            </div>
            <div>
                <span class="badge bg-light text-primary mb-1 font-monospace" style="font-size: 0.65rem;">
                    <?= strtoupper(e($user['role_title'] ?? $user['role_name'] ?? 'PENGGUNA')) ?>
                </span>
                <div class="fw-bold text-white" style="font-size: 1rem; line-height: 1.2;">
                    <?= e($user['full_name'] ?: $user['username']) ?>
                </div>
                <div class="text-white-50 small" style="font-size: 0.72rem;">
                    <?= e($user['email'] ?? 'Akun Sistem MBB') ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success py-2 small mb-3"><?= e($successMsg) ?></div>
    <?php endif; ?>
    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger py-2 small mb-3"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Menu Akun -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-0">
            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-shield-lock-fill text-primary me-2"></i>Keamanan Akun</span>
        </div>
        <div class="card-body p-3 pt-0">
            <form action="" method="POST">
                <input type="hidden" name="action" value="change_pass">
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-muted">Kata Sandi Saat Ini</label>
                    <input type="password" name="current_password" class="form-control form-control-sm" placeholder="••••••••" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold text-muted">Kata Sandi Baru</label>
                    <input type="password" name="new_password" class="form-control form-control-sm" placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Ulangi Sandi Baru</label>
                    <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100 py-2 fw-semibold" style="border-radius: 10px;">
                    <i class="bi bi-check2-circle me-1"></i>Perbarui Sandi
                </button>
            </form>
        </div>
    </div>

    <!-- Info Aplikasi & Server -->
    <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px;">
        <div class="card-header bg-white py-3 border-0">
            <span class="fw-bold text-dark" style="font-size: 0.85rem;"><i class="bi bi-phone-fill text-info me-2"></i>Informasi Aplikasi APK</span>
        </div>
        <div class="list-group list-group-flush px-3 pb-2" style="font-size: 0.75rem;">
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Versi Rilis</span>
                <span class="fw-semibold">MBB Mobile v1.0.0 (Air Hitam)</span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Status Sinkronisasi</span>
                <span class="badge bg-success-subtle text-success">REALTIME (AKTIF)</span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2">
                <span class="text-muted">Akses Langsung Mobile</span>
                <span class="font-monospace fw-semibold text-primary"><?= e(base_url('apk/')) ?></span>
            </div>
            <div class="list-group-item d-flex justify-content-between px-0 py-2 border-0">
                <span class="text-muted">Mode Tampilan</span>
                <span class="badge bg-primary">Dedicated Mobile Framework</span>
            </div>
        </div>
    </div>

    <!-- Tombol Aksi PWA & Keluar -->
    <div class="d-grid gap-2 mb-4">
        <button type="button" class="btn btn-primary py-2 fw-semibold btn-pwa-install shadow-sm" style="border-radius: 12px; font-size: 0.88rem;">
            <i class="bi bi-cloud-arrow-down-fill me-2"></i>Pasang Aplikasi ke Layar HP (PWA)
        </button>
        <a href="<?= base_url('logout.php?from=apk') ?>" class="btn btn-outline-danger py-2 fw-semibold btn-logout-confirm" style="border-radius: 12px; font-size: 0.85rem;">
            <i class="bi bi-box-arrow-right me-2"></i>Keluar (Logout)
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
