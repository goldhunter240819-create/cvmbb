<?php
/**
 * MBB MOBILE APK - Bagan Struktur Organisasi & Pengurus
 * CV. MAJU BERKAH BERSAMA
 */

$mobilePageTitle = 'Bagan Pejabat';
$currentApkNav   = 'home';

require_once __DIR__ . '/layout/header.php';

// Ambil Struktur Pegawai MBB
$employees = $db->query("
    SELECT e.*, p.position_name, d.division_name
    FROM employees e
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN divisions d ON p.division_id = d.id
    WHERE e.is_active = 1
    ORDER BY p.id ASC, e.id ASC
")->fetchAll();
?>

<div class="p-3">
    <!-- Header Banner -->
    <div class="card border-0 text-white p-3 mb-3 shadow-sm" style="background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%); border-radius: 18px;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="text-white-50 small">Struktur Kepengurusan Resmi</span>
            <span class="badge bg-white text-dark">CV. MBB</span>
        </div>
        <h5 class="fw-bold mb-1">Bagan Organisasi Perusahaan</h5>
        <div class="text-white-50 small" style="font-size: 0.72rem;">Air Hitam, Lampung Barat &bull; 8 Personil Inti</div>
    </div>

    <!-- Hierarchy Group Cards -->
    <div class="mb-3">
        <?php foreach ($employees as $emp): ?>
            <?php
            $isTop = in_array($emp['position_name'], ['Komisaris', 'Direksi', 'Direktur']);
            $cardBg = $isTop ? 'border-primary border-opacity-25' : 'border-0';
            $initials = strtoupper(substr($emp['full_name'], 0, 2));
            $phoneClean = preg_replace('/[^0-9]/', '', $emp['phone'] ?? '');
            if (str_starts_with($phoneClean, '0')) {
                $phoneClean = '62' . substr($phoneClean, 1);
            }
            ?>
            <div class="card <?= $cardBg ?> shadow-sm mb-2" style="border-radius: 16px;">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold <?= $isTop ? 'bg-primary text-white' : 'bg-light text-dark border' ?>" style="width: 48px; height: 48px; font-size: 1.1rem; flex-shrink: 0;">
                            <?= $initials ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge <?= $isTop ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' ?> mb-1" style="font-size: 0.65rem;">
                                        <?= e($emp['position_name']) ?>
                                    </span>
                                    <div class="fw-bold text-dark" style="font-size: 0.88rem; line-height: 1.2;">
                                        <?= e($emp['full_name']) ?>
                                    </div>
                                    <div class="text-muted small" style="font-size: 0.7rem;">
                                        <?= e($emp['division_name']) ?> &bull; <span class="font-monospace"><?= e($emp['employee_code']) ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($phoneClean)): ?>
                                    <a href="https://wa.me/<?= $phoneClean ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-circle p-2 shadow-sm" style="width: 36px; height: 36px; display:flex; align-items:center; justify-content:center;">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
