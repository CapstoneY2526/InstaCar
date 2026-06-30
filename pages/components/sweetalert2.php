<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toast configuration for success/error/warning
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 5000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    <?php if (isset($_SESSION['success'])): ?>
        Toast.fire({
            icon: 'success',
            title: '<?= addslashes($_SESSION['success']); ?>'
        });
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        Toast.fire({
            icon: 'error',
            title: '<?= addslashes($_SESSION['error']); ?>'
        });
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning'])): ?>
        // For warning with HTML content (extension details)
        Swal.fire({
            icon: 'warning',
            title: '⚠️ Extension / Late Return',
            html: `<?= addslashes($_SESSION['warning']); ?>`,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Got it',
            backdrop: true,
            allowOutsideClick: false
        });
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>
});
</script>