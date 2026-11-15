<?php
/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Modul Struktur Organisasi
 * CV. MAJU BERKAH BERSAMA
 */

require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Permission.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../helpers/utils.php';

Auth::requireLogin();
Permission::require('organization.view');

$db = Database::getInstance();

// Ambil data pejabat struktural lengkap
$stmt = $db->query("
    SELECT e.*, p.position_name, p.position_code, p.parent_position_id, d.division_name, d.division_code
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON e.division_id = d.id
    WHERE e.is_active = 1
    ORDER BY p.level_rank ASC, e.id ASC
");
$officials = $stmt->fetchAll();

// Mapping pejabat per posisi
$officialsByCode = [];
foreach ($officials as $off) {
    if ($off['position_code']) {
        $officialsByCode[$off['position_code']] = $off;
    }
}

// Data helper jika ada yang belum terisi
$komisaris = $officialsByCode['POS-KMS'] ?? null;
$direksi   = $officialsByCode['POS-DIRK'] ?? null;
$direktur  = $officialsByCode['POS-DIR'] ?? null;
$adminDiv  = $officialsByCode['POS-ADMH'] ?? null;
$opsDiv    = $officialsByCode['POS-OPSH'] ?? null;
$manager   = $officialsByCode['POS-MGR'] ?? null;
$dekorDiv  = $officialsByCode['POS-DEKH'] ?? null;
$soundDiv  = $officialsByCode['POS-SNDH'] ?? null;

$pageTitle = 'Struktur Organisasi';
$currentModule = 'organization';
$pageBreadcrumbs = [
    'Struktur Organisasi' => null
];

$pageActionBtn = '
    <div class="d-flex flex-wrap gap-2">
        <a href="' . base_url('modules/organization/print.php') . '" target="_blank" class="btn btn-light btn-sm fw-bold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-printer text-primary"></i> Cetak Dokumen
        </a>
        <a href="' . base_url('modules/organization/history.php') . '" class="btn btn-outline-light btn-sm fw-bold d-flex align-items-center gap-1">
            <i class="bi bi-clock-history"></i> Riwayat
        </a>' . 
        (Permission::can('organization.manage') ? '
        <a href="' . base_url('modules/organization/positions.php') . '" class="btn btn-warning btn-sm fw-bold shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-gear-fill"></i> Kelola Jabatan
        </a>' : '') . '
    </div>
';

require_once __DIR__ . '/../../components/header.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/topbar.php';
?>

<!-- ========================================================
     DIAGRAM HIERARKI ORGANISASI RESMI MBB
     ======================================================== -->
<div class="card-mbb mb-4">
    <div class="card-mbb-header bg-white">
        <h6 class="card-mbb-title text-primary">
            <i class="bi bi-diagram-3-fill"></i> Visualisasi Bagan Hierarki Perusahaan
        </h6>
        <span class="badge bg-primary text-white">Resmi & Dinamis</span>
    </div>
    <div class="card-mbb-body bg-light overflow-auto py-5">
        <div class="d-flex flex-column align-items-center" style="min-width: 820px;">
            
            <!-- LEVEL 1: KOMISARIS -->
            <div class="org-node komisaris">
                <span class="badge-position bg-primary-subtle text-primary border border-primary-subtle">DEWAN KOMISARIS</span>
                <div class="emp-name"><?= e($komisaris['full_name'] ?? 'KH. MUHAMMAD THOHA') ?></div>
                <div class="div-sub">Komisaris Perusahaan</div>
            </div>

            <!-- GARIS KONEKTOR KE DIREKSI -->
            <div class="org-connector-down"></div>

            <!-- LEVEL 2: DIREKSI -->
            <div class="org-node direksi">
                <span class="badge-position bg-success-subtle text-success border border-success-subtle">DIREKSI</span>
                <div class="emp-name"><?= e($direksi['full_name'] ?? 'NUR ISLAH') ?></div>
                <div class="div-sub">Pimpinan Eksekutif</div>
            </div>

            <!-- GARIS KONEKTOR DARI DIREKSI MENURUN -->
            <div class="org-connector-down"></div>

            <!-- CABANG DI BAWAH DIREKSI: ADM, DIREKTUR, OPERASIONAL -->
            <div style="width: 780px;">
                <div class="border-top border-2 border-secondary" style="margin: 0 auto; width: 680px;"></div>
                <div class="d-flex justify-content-between pt-3">
                    
                    <!-- 1. DIVISI ADMINISTRASI (DI BAWAH DIREKSI) -->
                    <div class="d-flex flex-column align-items-center" style="width: 210px;">
                        <div class="org-connector-down" style="height: 20px; margin-top: -16px;"></div>
                        <div class="org-node divisi w-100" style="min-width: unset;">
                            <span class="badge-position bg-secondary text-white">DIVISI DI BAWAH DIREKSI</span>
                            <div class="small fw-bold text-primary mb-1">DIVISI ADMINISTRASI</div>
                            <div class="fw-bold text-dark"><?= e($adminDiv['full_name'] ?? 'NURIL HUDA') ?></div>
                            <div class="div-sub">Penanggung Jawab</div>
                        </div>
                    </div>

                    <!-- 2. DIREKTUR (DI BAWAH DIREKSI) -->
                    <div class="d-flex flex-column align-items-center" style="width: 250px;">
                        <div class="org-connector-down" style="height: 20px; margin-top: -16px;"></div>
                        <div class="org-node direktur w-100">
                            <span class="badge-position bg-info text-white">DIREKTUR</span>
                            <div class="emp-name"><?= e($direktur['full_name'] ?? 'M. ROZIKIN') ?></div>
                            <div class="div-sub">Direktur Utama</div>
                        </div>

                        <!-- GARIS KE MANAGER -->
                        <div class="org-connector-down"></div>

                        <!-- 3. MANAGER (DI BAWAH DIREKTUR) -->
                        <div class="org-node manager w-100">
                            <span class="badge-position bg-warning text-dark">MANAGER</span>
                            <div class="emp-name"><?= e($manager['full_name'] ?? 'M. ILHAM SIFA') ?></div>
                            <div class="div-sub">Manager Operasional</div>
                        </div>

                        <!-- GARIS KE DIVISI BAWAH MANAGER -->
                        <div class="org-connector-down"></div>

                        <!-- CABANG DIVISI DI BAWAH MANAGER -->
                        <div style="width: 440px;">
                            <div class="border-top border-2 border-secondary" style="margin: 0 auto; width: 280px;"></div>
                            <div class="d-flex justify-content-between pt-3">
                                
                                <!-- DIVISI DEKORATION -->
                                <div class="d-flex flex-column align-items-center" style="width: 210px;">
                                    <div class="org-connector-down" style="height: 20px; margin-top: -16px;"></div>
                                    <div class="org-node divisi w-100" style="min-width: unset;">
                                        <span class="badge-position bg-dark text-white">DI BAWAH MANAGER</span>
                                        <div class="small fw-bold text-success mb-1">DIVISI DEKORATION</div>
                                        <div class="fw-bold text-dark"><?= e($dekorDiv['full_name'] ?? 'FATKHUR ROHMAN') ?></div>
                                        <div class="div-sub">Penanggung Jawab</div>
                                    </div>
                                </div>

                                <!-- DIVISI SOUND SISTEM -->
                                <div class="d-flex flex-column align-items-center" style="width: 210px;">
                                    <div class="org-connector-down" style="height: 20px; margin-top: -16px;"></div>
                                    <div class="org-node divisi w-100" style="min-width: unset;">
                                        <span class="badge-position bg-dark text-white">DI BAWAH MANAGER</span>
                                        <div class="small fw-bold text-danger mb-1">DIVISI SOUND SISTEM</div>
                                        <div class="fw-bold text-dark"><?= e($soundDiv['full_name'] ?? 'MISBAHUL MUNIR') ?></div>
                                        <div class="div-sub">Penanggung Jawab</div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- 3. DIVISI OPERASIONAL (DI BAWAH DIREKSI) -->
                    <div class="d-flex flex-column align-items-center" style="width: 210px;">
                        <div class="org-connector-down" style="height: 20px; margin-top: -16px;"></div>
                        <div class="org-node divisi w-100" style="min-width: unset;">
                            <span class="badge-position bg-secondary text-white">DIVISI DI BAWAH DIREKSI</span>
                            <div class="small fw-bold text-primary mb-1">DIVISI OPERASIONAL</div>
                            <div class="fw-bold text-dark"><?= e($opsDiv['full_name'] ?? 'AHMAD SOBIRIN') ?></div>
                            <div class="div-sub">Penanggung Jawab</div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- ========================================================
     TABEL DATA ANGGOTA PEJABAT STRUKTURAL
     ======================================================== -->
<div class="card-mbb">
    <div class="card-mbb-header">
        <h6 class="card-mbb-title">
            <i class="bi bi-people-fill text-primary"></i> Daftar Pejabat & Anggota Struktural
        </h6>
        <?php if (Permission::can('employees.manage')): ?>
        <a href="<?= base_url('modules/organization/mutate.php') ?>" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
            <i class="bi bi-arrow-left-right"></i> Mutasi / Pindah Divisi
        </a>
        <?php endif; ?>
    </div>
    <div class="card-mbb-body p-0">
        <div class="table-responsive">
            <table class="table-mbb align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">No</th>
                        <th>Kode</th>
                        <th>Nama Pejabat</th>
                        <th>Jabatan Struktural</th>
                        <th>Divisi</th>
                        <th>Kontak Telepon</th>
                        <th>Status</th>
                        <?php if (Permission::can('organization.manage') || Permission::can('employees.manage')): ?>
                        <th class="text-end pe-4">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($officials)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada data anggota struktural.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($officials as $idx => $row): ?>
                            <tr>
                                <td class="ps-4 text-muted"><?= $idx + 1 ?></td>
                                <td class="fw-semibold text-primary"><?= e($row['employee_code']) ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($row['full_name']) ?></div>
                                    <small class="text-muted"><?= e($row['email'] ?: '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <?= e($row['position_name'] ?: 'Belum Ditetapkan') ?>
                                    </span>
                                </td>
                                <td><?= e($row['division_name'] ?: '-') ?></td>
                                <td><?= e($row['phone_number'] ?: '-') ?></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success-subtle text-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (Permission::can('organization.manage') || Permission::can('employees.manage')): ?>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('modules/organization/mutate.php?id=' . $row['id']) ?>" class="btn btn-outline-secondary" title="Mutasi / Pindah Divisi">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </a>
                                        <a href="<?= base_url('modules/employees/edit.php?id=' . $row['id']) ?>" class="btn btn-outline-primary" title="Edit Profil">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
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

<?php require_once __DIR__ . '/../../components/footer.php'; ?>
