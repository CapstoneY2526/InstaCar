<footer class="footer text-center py-3 mt-auto">
    <small class="text-muted">
        &copy; <?= date('Y') ?> Car Rental System. All rights reserved.
    </small>
</footer>

<style>
    .footer {
        background-color: transparent;
        border-top: 1px solid transparent;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }
    body:not(.dark-mode) .footer {
        background-color: transparent;
        border-top-color: #e2e8f0;
    }
    body.dark-mode .footer,
    html.dark-mode .footer {
        background-color: #141414;
        border-top-color: #27272a;
    }
    body.dark-mode .footer small,
    body.dark-mode .footer .text-muted,
    html.dark-mode .footer small,
    html.dark-mode .footer .text-muted {
        color: #a1a1aa !important;
    }
</style>

<script src="../../public/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>