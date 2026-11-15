<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Pengaturan Profil Perusahaan
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('company.manage');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/settings/company.php');
    }

    $keys = [
        'company_name'         => trim($_POST['company_name'] ?? ''),
        'company_short_name'   => trim($_POST['company_short_name'] ?? ''),
        'company_tagline'      => trim($_POST['company_tagline'] ?? ''),
        'company_address'      => trim($_POST['company_address'] ?? ''),
        'company_phone'        => trim($_POST['company_phone'] ?? ''),
        'company_email'        => trim($_POST['company_email'] ?? ''),
        'app_name'             => trim($_POST['app_name'] ?? ''),
        'default_signer'       => trim($_POST['default_signer'] ?? ''),
        'default_signer_title' => trim($_POST['default_signer_title'] ?? '')
    ];

    // Proses Upload Logo jika ada
    if (!empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['company_logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'webp'];

        if (in_array($ext, $allowed)) {
            $destUpload = __DIR__ . '/../../uploads/company/logo.' . $ext;
            $destAssets = __DIR__ . '/../../assets/images/logo.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $destUpload)) {
                copy($destUpload, $destAssets);
                $keys['company_logo'] = 'assets/images/logo.' . $ext;
            }
        } else {
            flash_set('danger', 'Format logo harus berupa file gambar (PNG, JPG, JPEG, WEBP).');
        }
    }

    $stmt = $db->prepare("
        INSERT INTO company_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($keys as $k => $v) {
        if ($v !== '') {
            $stmt->execute([$k, $v]);
        }
    }

    log_audit('UPDATE_SETTINGS', 'settings', null, "Memperbarui profil identitas perusahaan CV. MAJU BERKAH BERSAMA");
    flash_set('success', 'Profil identitas perusahaan berhasil diperbarui.');
    redirect('modules/settings/company.php');
}

$pageTitle = 'Profil Perusahaan';
$currentModule = 'settings-company';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Pengaturan' => null,
    'Profil Perusahaan' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card-mbb">
            <div class="card-mbb-header bg-primary text-white">
                <h5 class="card-mbb-title text-white">
                    <i class="bi bi-building-fill-gear me-2"></i> Pengaturan Identitas & Profil Perusahaan
                </h5>
            </div>
            <div class="card-mbb-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="row mb-4 align-items-center">
                        <div class="col-md-3 text-center">
                            <img src="<?= asset_url('images/logo.png') ?>" alt="Logo MBB" class="img-fluid rounded p-2 border bg-light shadow-sm" style="max-height: 110px;">
                            <div class="small text-muted mt-2">Logo Resmi Aktif</div>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-bold">Ganti Logo Perusahaan</label>
                            <input type="file" name="company_logo" class="form-control" accept="image/*">
                            <small class="text-muted">Format: PNG, JPG, WEBP transparan. Logo ini otomatis tampil di favicon tab, sidebar, dashboard, dan kop cetak dokumen.</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Nama Lengkap Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?= e(get_company_setting('company_name', 'CV. MAJU BERKAH BERSAMA')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Singkatan Perusahaan</label>
                            <input type="text" name="company_short_name" class="form-control" value="<?= e(get_company_setting('company_short_name', 'MBB')) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tagline / Motto Perusahaan</label>
                        <input type="text" name="company_tagline" class="form-control" value="<?= e(get_company_setting('company_tagline', 'Integritas, Kualitas, dan Keberkahan Usaha')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat Kantor Pusat <span class="text-danger">*</span></label>
                        <textarea name="company_address" class="form-control" rows="2" required><?= e(get_company_setting('company_address', 'Jln. Raya Gunung Terang No. 09, Kecamatan Air Hitam, Kabupaten Lampung Barat')) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nomor Kontak Telepon / WhatsApp</label>
                            <input type="text" name="company_phone" class="form-control" value="<?= e(get_company_setting('company_phone', '0812-7890-1234')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Alamat Email Resmi</label>
                            <input type="email" name="company_email" class="form-control" value="<?= e(get_company_setting('company_email', 'info@cvmbb.co.id')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nama Pejabat Penandatangan Dokumen</label>
                            <input type="text" name="default_signer" class="form-control" value="<?= e(get_company_setting('default_signer', 'M. ROZIKIN')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jabatan Penandatangan Standar</label>
                            <input type="text" name="default_signer_title" class="form-control" value="<?= e(get_company_setting('default_signer_title', 'Direktur')) ?>">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check-circle me-1"></i> Simpan Profil Perusahaan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
