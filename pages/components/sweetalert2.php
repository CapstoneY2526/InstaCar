<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* SweetAlert2 Dark Theme Customization */
    body.swal2-toast-shown .swal2-container.swal2-top-end {
        top: 15px !important;
        right: 15px !important;
    }

    .swal2-popup.swal-theme-toast {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        border-radius: 12px !important;
        color: #f1f5f9 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
    }

    .swal2-popup.swal-theme-toast .swal2-title {
        color: #f1f5f9 !important;
        font-size: 0.95rem !important;
        font-weight: 600 !important;
    }

    .swal2-popup.swal-theme-toast .swal2-timer-progress-bar {
        background-color: #ffcc00 !important;
    }

    /* Modal Styling */
    .swal2-popup.swal-theme-modal {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        border-radius: 20px !important;
        color: #f1f5f9 !important;
    }

    .swal2-popup.swal-theme-modal .swal2-title {
        color: #ffffff !important;
        font-size: 1.35rem !important;
        font-weight: 700 !important;
    }

    .swal2-popup.swal-theme-modal .swal2-html-container {
        color: #cbd5e1 !important;
    }

    .swal2-confirm.swal-theme-btn {
        background-color: #ffcc00 !important;
        color: #000000 !important;
        font-weight: 700 !important;
        border-radius: 10px !important;
        padding: 10px 24px !important;
        box-shadow: none !important;
    }

    .swal2-confirm.swal-theme-btn:hover {
        background-color: #ffd633 !important;
        box-shadow: 0 4px 15px rgba(255, 204, 0, 0.3) !important;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toast configuration matching dashboard aesthetic
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        customClass: {
            popup: 'swal-theme-toast'
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    <?php if (isset($_SESSION['success'])): ?>
        Toast.fire({
            icon: 'success',
            iconColor: '#22c55e',
            title: '<?= addslashes($_SESSION['success']); ?>'
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Toast.fire({
            icon: 'error',
            iconColor: '#ef4444',
            title: '<?= addslashes($_SESSION['error']); ?>'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        Swal.fire({
            icon: 'warning',
            iconColor: '#ffcc00',
            title: '⚠️ Extension / Late Return',
            html: `<?= addslashes($_SESSION['warning']); ?>`,
            confirmButtonText: 'Got it',
            backdrop: 'rgba(0, 0, 0, 0.75)',
            allowOutsideClick: false,
            customClass: {
                popup: 'swal-theme-modal',
                confirmButton: 'swal-theme-btn'
            }
        });
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>
});
</script>