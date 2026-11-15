<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Ganti Password Akun Sendiri
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
$userId = Auth::id();
$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/settings/change-password.php');
    }

    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass     = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        flash_set('danger', 'Semua kolom wajib diisi.');
    } elseif ($newPass !== $confirmPass) {
        flash_set('danger', 'Konfirmasi kata sandi baru tidak cocok.');
    } elseif (strlen($newPass) < 5) {
        flash_set('danger', 'Kata sandi baru minimal 5 karakter.');
    } else {
        // Cek password saat ini
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch();

        if (!password_verify($currentPass, $userRow['password_hash'])) {
            flash_set('danger', 'Kata sandi saat ini salah.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
            $stmt->execute([$newHash, $userId]);
            log_audit('CHANGE_MY_PASSWORD', 'auth', $userId, "Pengguna mengubah kata sandinya.");
            flash_set('success', 'Kata sandi Anda berhasil diperbarui!');
            redirect('index.php');
        }
    }
}

$pageTitle = 'Ganti Kata Sandi';
$currentModule = 'settings-password';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Ganti Password' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card-mbb">
            <div class="card-mbb-header bg-primary text-white">
                <h5 class="card-mbb-title text-white">
                    <i class="bi bi-key-fill me-2"></i> Ganti Kata Sandi Akun
                </h5>
            </div>
            <div class="card-mbb-body p-4">
                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kata Sandi Saat Ini <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control" placeholder="Masukkan password lama..." required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Kata Sandi Baru <span class="text-danger">*</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimal 5 karakter..." required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Ulangi Kata Sandi Baru <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Ketik ulang password baru..." required>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="<?= base_url('index.php') ?>" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> Perbarui Kata Sandi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
