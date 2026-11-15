<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Portal Khusus Developer & System Architect (Maul)
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::requireDeveloper();

$db = Database::getInstance();

// -------------------------------------------------------------
// POST HANDLER DEVELOPER TOOLS
// -------------------------------------------------------------
$queryResult = null;
$queryError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear_audit_logs') {
        csrf_validate();
        $db->exec("TRUNCATE TABLE audit_logs");
        log_audit('DEV_CLEAR_LOGS', 'developer', 0, "Developer Maul membersihkan tabel audit log.");
        flash_set('success', 'Seluruh data audit logs berhasil dibersihkan.');
        redirect('modules/developer/index.php');
    }

    if ($action === 'optimize_tables') {
        csrf_validate();
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $t) {
            $db->exec("OPTIMIZE TABLE `{$t}`");
        }
        log_audit('DEV_OPTIMIZE_DB', 'developer', 0, "Developer Maul mengoptimasi seluruh tabel database.");
        flash_set('success', 'Seluruh 35 tabel database berhasil dioptimasi.');
        redirect('modules/developer/index.php');
    }

    if ($action === 'run_sql_query') {
        csrf_validate();
        $sql = trim($_POST['sql_query'] ?? '');
        // Safety check: hanya perbolehkan SELECT atau SHOW atau DESCRIBE
        if (!preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\s+/i', $sql)) {
            $queryError = 'Hanya query pembacaan (SELECT, SHOW, DESCRIBE, EXPLAIN) yang diperbolehkan di sandbox ini demi keamanan integritas data.';
        } else {
            try {
                $stmt = $db->query($sql);
                $queryResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                log_audit('DEV_SQL_QUERY', 'developer', 0, "Developer Maul mengeksekusi SQL sandbox: " . substr($sql, 0, 100));
            } catch (Exception $e) {
                $queryError = $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------
// METRIK SERVER & DATABASE
// -------------------------------------------------------------
$dbTables = $db->query("
    SELECT 
        TABLE_NAME AS table_name,
        TABLE_ROWS AS table_rows,
        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) AS size_kb,
        AUTO_INCREMENT AS auto_inc,
        ENGINE AS engine,
        TABLE_COLLATION AS collation
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'mbb_management'
    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
")->fetchAll();

$totalDbSizeKb = array_sum(array_column($dbTables, 'size_kb'));
$totalDbRows = array_sum(array_column($dbTables, 'table_rows'));

// Status PHP Extensions
$extensions = [
    'PDO' => extension_loaded('pdo'),
    'PDO_MySQL' => extension_loaded('pdo_mysql'),
    'OpenSSL' => extension_loaded('openssl'),
    'cURL' => extension_loaded('curl'),
    'MBString' => extension_loaded('mbstring'),
    'GD Image' => extension_loaded('gd'),
    'JSON' => extension_loaded('json')
];

// Audit logs terbaru
$logs = $db->query("
    SELECT al.*, u.username, r.role_title
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    ORDER BY al.id DESC
    LIMIT 20
")->fetchAll();

$pageTitle = 'Portal Developer & System Architect';
$currentModule = 'developer';
$pageBreadcrumbs = [
    'Dashboard' => base_url('index.php'),
    'Portal Developer' => null
];

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- 1. HERO DEVELOPER BADGE -->
<div class="card-mbb mb-4 border-start border-4 border-info shadow-sm" style="background: linear-gradient(135deg, #0d1e3a 0%, #172554 100%); color: #ffffff;">
    <div class="card-mbb-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-danger text-white font-monospace"><i class="bi bi-shield-shaded me-1"></i>DEV CONSOLE</span>
                    <span class="badge bg-info text-dark font-monospace">PHP <?= PHP_VERSION ?></span>
                    <span class="badge bg-success text-white font-monospace">MySQL / MariaDB InnoDB</span>
                </div>
                <h3 class="fw-bold mb-1 text-white">MBB Developer & System Control Portal</h3>
                <p class="text-light opacity-75 mb-0 small">
                    Selamat datang kembali, <strong>Maul</strong> (System Architect). Portal ini dirancang khusus untuk diagnostik server, audit internal, inspeksi database, dan peralatan teknis.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= base_url('modules/settings/backup.php') ?>" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-database-down me-1"></i> Backup DB (.sql)
                </a>
                <form method="POST" action="" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="optimize_tables">
                    <button type="submit" class="btn btn-sm btn-info text-dark fw-bold">
                        <i class="bi bi-speedometer2 me-1"></i> Optimasi Tabel
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 2. DIAGNOSTIK SERVER & STATISTIK DATABASE -->
<div class="row g-4 mb-4">
    <!-- Server Environment Specs -->
    <div class="col-md-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-cpu-fill"></i> Spesifikasi Runtime Server</h6>
                <span class="badge bg-success">Online & Healthy</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4 text-muted">PHP Version</td>
                        <td class="fw-bold text-dark pe-4 text-end"><?= phpversion() ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Web Server / SAPI</td>
                        <td class="fw-semibold pe-4 text-end"><?= php_sapi_name() ?> (Apache XAMPP)</td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Memory Limit</td>
                        <td class="pe-4 text-end"><code><?= ini_get('memory_limit') ?></code></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Max Execution Time</td>
                        <td class="pe-4 text-end"><code><?= ini_get('max_execution_time') ?>s</code></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Max Upload / Post Size</td>
                        <td class="pe-4 text-end"><code><?= ini_get('upload_max_filesize') ?> / <?= ini_get('post_max_size') ?></code></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Ekstensi PHP Kunci</td>
                        <td class="pe-4 text-end">
                            <?php foreach ($extensions as $ext => $loaded): ?>
                                <span class="badge <?= $loaded ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger' ?> small">
                                    <?= $ext ?>
                                </span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Database Engine & Storage Metrics -->
    <div class="col-md-6">
        <div class="card-mbb h-100">
            <div class="card-mbb-header">
                <h6 class="card-mbb-title text-primary"><i class="bi bi-database-fill-gear"></i> Status Database `mbb_management`</h6>
                <span class="badge bg-primary"><?= count($dbTables) ?> Tabel InnoDB</span>
            </div>
            <div class="card-mbb-body p-0">
                <table class="table-mbb align-middle mb-0">
                    <tr>
                        <td class="ps-4 text-muted">Kapasitas Penyimpanan Terpakai</td>
                        <td class="fw-bold text-primary pe-4 text-end"><?= number_format($totalDbSizeKb / 1024, 2) ?> MB (<?= number_format($totalDbSizeKb, 1) ?> KB)</td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Total Baris Rekaman</td>
                        <td class="fw-bold text-dark pe-4 text-end"><?= number_format($totalDbRows) ?> Baris Data</td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Default Engine</td>
                        <td class="pe-4 text-end"><span class="badge bg-light text-dark border">InnoDB (ACID Compliant)</span></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Collation</td>
                        <td class="pe-4 text-end"><code>utf8mb4_unicode_ci</code></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Lokasi Server DB</td>
                        <td class="pe-4 text-end"><code>127.0.0.1:3306</code></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">Driver Koneksi</td>
                        <td class="pe-4 text-end"><span class="badge bg-success-subtle text-success">PDO Prepared Statements</span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 3. SQL QUERY SANDBOX (READ-ONLY DEVELOPER TOOLKIT) -->
<div class="card-mbb mb-4">
    <div class="card-mbb-header d-flex justify-content-between align-items-center">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-terminal-fill"></i> SQL Query Sandbox (Developer Read-Only)
        </h6>
        <span class="badge bg-secondary-subtle text-secondary border">SELECT / SHOW / DESCRIBE</span>
    </div>
    <div class="card-mbb-body">
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_sql_query">
            <div class="mb-3">
                <textarea name="sql_query" class="form-control font-monospace" rows="3" placeholder="SELECT * FROM users LIMIT 5;" style="background-color: #0d1e3a; color: #a5f3fc;"><?= e($_POST['sql_query'] ?? 'SELECT username, email, role_id, is_active FROM users;') ?></textarea>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="small text-muted"><i class="bi bi-info-circle me-1"></i> Query dibatasi read-only untuk proteksi basis data.</span>
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-play-fill me-1"></i> Jalankan Query</button>
            </div>
        </form>

        <?php if ($queryError): ?>
            <div class="alert alert-danger mt-3 mb-0 small">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Error: <?= e($queryError) ?>
            </div>
        <?php endif; ?>

        <?php if ($queryResult !== null): ?>
            <div class="table-responsive mt-3 border rounded" style="max-height: 250px;">
                <table class="table table-sm table-striped table-bordered font-monospace small mb-0">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <?php if (!empty($queryResult)): ?>
                                <?php foreach (array_keys($queryResult[0]) as $col): ?>
                                    <th><?= e($col) ?></th>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <th>Result</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queryResult)): ?>
                            <tr><td class="text-center py-2 text-muted">0 rows returned.</td></tr>
                        <?php else: ?>
                            <?php foreach ($queryResult as $row): ?>
                                <tr>
                                    <?php foreach ($row as $val): ?>
                                        <td><?= e($val ?? 'NULL') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 4. TABEL INSPEKSI SCHEMA 35 TABEL INNODB -->
<div class="card-mbb mb-4">
    <div class="card-mbb-header d-flex justify-content-between align-items-center">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-table"></i> Inspeksi Struktur & Ukuran 35 Tabel Database
        </h6>
        <span class="badge bg-primary-subtle text-primary border">Total <?= count($dbTables) ?> Tabel</span>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive" style="max-height: 380px;">
            <table class="table-mbb align-middle table-sm font-monospace small mb-0">
                <thead class="sticky-top bg-light">
                    <tr>
                        <th class="ps-4">Nama Tabel</th>
                        <th class="text-end">Jumlah Baris</th>
                        <th class="text-end">Ukuran (KB)</th>
                        <th class="text-end">Auto Inc</th>
                        <th>Engine</th>
                        <th class="pe-4">Collation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dbTables as $tbl): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark"><?= e($tbl['table_name']) ?></td>
                            <td class="text-end"><?= number_format($tbl['table_rows']) ?></td>
                            <td class="text-end text-primary fw-semibold"><?= number_format($tbl['size_kb'], 1) ?> KB</td>
                            <td class="text-end text-muted"><?= $tbl['auto_inc'] ?: '-' ?></td>
                            <td><span class="badge bg-light text-dark border"><?= e($tbl['engine']) ?></span></td>
                            <td class="pe-4 text-muted"><?= e($tbl['collation']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 5. AUDIT LOG CONSOLE -->
<div class="card-mbb">
    <div class="card-mbb-header d-flex justify-content-between align-items-center">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-shield-check"></i> Audit Log Console (20 Aktivitas Sistem Terkini)
        </h6>
        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Kosongkan riwayat audit log?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_audit_logs">
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash me-1"></i> Bersihkan Log
            </button>
        </form>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle small mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Waktu</th>
                        <th>Aksi</th>
                        <th>Modul</th>
                        <th>Pengguna</th>
                        <th>IP Address</th>
                        <th class="pe-4">Deskripsi Aktivitas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-3 text-muted">Belum ada log aktivitas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="ps-4 text-nowrap"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                                <td><span class="badge bg-dark font-monospace"><?= e($l['action']) ?></span></td>
                                <td><span class="badge bg-light text-dark border"><?= e($l['module']) ?></span></td>
                                <td class="fw-semibold text-dark"><?= e($l['username'] ?? 'System') ?></td>
                                <td><code><?= e($l['ip_address'] ?? '127.0.0.1') ?></code></td>
                                <td class="pe-4 text-muted"><?= e($l['description']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
