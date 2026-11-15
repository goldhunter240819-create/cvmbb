<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Backup & Pemeliharaan Database
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('backup.manage');

$db = Database::getInstance();
$backupDir = __DIR__ . '/../../storage/backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Download Backup File
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filePath = $backupDir . $file;
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// Proses Backup Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_backup') {
    if (!verify_csrf_token()) {
        flash_set('danger', 'Validasi sesi keamanan (CSRF) gagal.');
        redirect('modules/settings/backup.php');
    }

    try {
        $tables = [];
        $stmt = $db->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sqlDump = "-- =====================================================\n";
        $sqlDump .= "-- MBB BUSINESS MANAGEMENT SYSTEM - DATABASE BACKUP\n";
        $sqlDump .= "-- CV. MAJU BERKAH BERSAMA\n";
        $sqlDump .= "-- Tanggal: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- =====================================================\n\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $tbl) {
            // Create Table DDL
            $createStmt = $db->query("SHOW CREATE TABLE `{$tbl}`");
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            $sqlDump .= "DROP TABLE IF EXISTS `{$tbl}`;\n";
            $sqlDump .= $createRow[1] . ";\n\n";

            // Data Rows
            $dataStmt = $db->query("SELECT * FROM `{$tbl}`");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $colNames = implode('`, `', $columns);

                foreach ($rows as $r) {
                    $values = [];
                    foreach ($r as $val) {
                        if ($val === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $db->quote($val);
                        }
                    }
                    $sqlDump .= "INSERT INTO `{$tbl}` (`{$colNames}`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sqlDump .= "\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        $fileName = 'backup_mbb_' . date('Y_m_d_His') . '.sql';
        file_put_contents($backupDir . $fileName, $sqlDump);

        log_audit('BACKUP_DATABASE', 'system', null, "Membuat file cadangan database: {$fileName}");
        flash_set('success', "Pencadangan database berhasil disimpan: {$fileName}");
    } catch (Exception $e) {
        flash_set('danger', 'Gagal mencadangkan database: ' . $e->getMessage());
    }
    redirect('modules/settings/backup.php');
}

// Daftar file backup yang ada
$backupFiles = [];
foreach (glob($backupDir . '*.sql') as $f) {
    $backupFiles[] = [
        'name' => basename($f),
        'size' => round(filesize($f) / 1024, 2) . ' KB',
        'date' => date('d/m/Y H:i', filemtime($f))
    ];
}
rsort($backupFiles);

$pageTitle = 'Backup Database';
$currentModule = 'settings-backup';
$pageBreadcrumbs = [
    'Pengaturan' => null,
    'Backup Database' => null
];

$pageActionBtn = '
    <form method="POST" action="" class="d-inline">
        ' . csrf_field() . '
        <input type="hidden" name="action" value="create_backup">
        <button type="submit" class="btn btn-light btn-sm fw-bold shadow-sm d-flex align-items-center gap-2">
            <i class="bi bi-cloud-arrow-down-fill text-primary"></i> Buat Backup Sekarang
        </button>
    </form>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-clock-history"></i> Riwayat Arsip File Backup
        </h6>
        <span class="badge bg-light text-secondary border"><?= count($backupFiles) ?> File Tersedia</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Nama File Cadangan</th>
                        <th>Ukuran File</th>
                        <th>Waktu Pembuatan</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backupFiles)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada file backup yang dibuat. Silakan klik tombol "Buat Backup Database Sekarang".</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($backupFiles as $idx => $bf): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $idx + 1 ?></td>
                                <td class="fw-bold text-dark">
                                    <i class="bi bi-file-earmark-code text-primary me-2"></i>
                                    <?= e($bf['name']) ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($bf['size']) ?></span></td>
                                <td><?= e($bf['date']) ?></td>
                                <td class="text-end pe-4">
                                    <a href="?download=<?= urlencode($bf['name']) ?>" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-download me-1"></i> Unduh File
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4 d-flex align-items-center">
    <i class="bi bi-shield-check fs-4 me-3"></i>
    <div class="small">
        <strong>Keamanan & Integritas:</strong> File cadangan (.sql) memuat seluruh struktur tabel dan data operasional mbb_management secara utuh. Simpan salinan file cadangan ke drive eksternal secara berkala.
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
