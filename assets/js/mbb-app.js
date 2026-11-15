/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Core Application JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.toggle('d-none');
            }
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
            sidebarBackdrop.classList.add('d-none');
        });
    }

    // 2. Initialize Bootstrap Tooltips & Popovers
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 3. Confirm Delete Dialog Helper
    document.querySelectorAll('.btn-confirm-delete').forEach(function (button) {
        button.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 4. Auto dismiss alerts after 5 seconds
    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        });
    }, 6000);

    // 5. Strict Sidebar Accordion: Hanya boleh 1 dropdown terbuka sekaligus
    const sidebarMenu = document.getElementById('sidebarMenu');
    if (sidebarMenu) {
        const collapses = sidebarMenu.querySelectorAll('.sidebar-submenu-collapse');
        collapses.forEach(function (colEl) {
            colEl.addEventListener('show.bs.collapse', function () {
                collapses.forEach(function (otherEl) {
                    if (otherEl !== colEl) {
                        const bsCollapse = bootstrap.Collapse.getInstance(otherEl);
                        if (bsCollapse) {
                            bsCollapse.hide();
                        } else if (otherEl.classList.contains('show')) {
                            new bootstrap.Collapse(otherEl, { toggle: false }).hide();
                        }
                    }
                });
            });
        });
    }
});

/**
 * Format currency helper in JS
 */
function formatRupiahJS(number) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(number);
}
