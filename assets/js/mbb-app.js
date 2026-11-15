/**
 * MBB BUSINESS MANAGEMENT SYSTEM
 * Core Application JavaScript
 */

// Global SweetAlert2 Anti-Shift (Mencegah Layar Bergeser/Melompat Saat Popup Terbuka)
if (typeof Swal !== 'undefined') {
    const _origFire = Swal.fire;
    Swal.fire = function (...args) {
        if (typeof args[0] === 'object' && args[0] !== null) {
            args[0].scrollbarPadding = false;
            args[0].heightAuto = false;
        }
        return _origFire.apply(this, args);
    };
}

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

    // 3. Confirm Delete & Action Dialog Helper (SweetAlert2)
    document.querySelectorAll('.btn-confirm-delete, [data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const form = this.closest('form');
            const message = this.getAttribute('data-confirm') || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.';
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Konfirmasi Tindakan',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="bi bi-trash-fill me-1"></i> Ya, Lanjutkan',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    focusCancel: true,
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0'
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        if (form) {
                            form.submit();
                        } else if (button.tagName === 'A' && button.getAttribute('href')) {
                            window.location.href = button.getAttribute('href');
                        }
                    }
                });
            } else {
                if (confirm(message)) {
                    if (form) form.submit();
                    else if (button.tagName === 'A') window.location.href = button.getAttribute('href');
                }
            }
        });
    });

    // 3.5 Intercept Logout with SweetAlert2
    document.querySelectorAll('a[href*="logout.php"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetUrl = this.getAttribute('href');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Keluar dari Sistem?',
                    text: 'Sesi Anda akan diakhiri. Pastikan semua transaksi sudah tersimpan.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: '<i class="bi bi-box-arrow-right me-1"></i> Ya, Keluar',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    focusCancel: true,
                    customClass: {
                        popup: 'rounded-4 shadow-lg border-0'
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location.href = targetUrl;
                    }
                });
            } else {
                window.location.href = targetUrl;
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
