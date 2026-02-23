
</div><!-- /container-fluid -->

<footer class="footer mt-auto py-3 bg-dark text-light">
    <div class="container-fluid text-center">
        <small>&copy; <?= date('Y') ?> APM Leave Management System &mdash; v<?= APP_VERSION ?></small>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- FullCalendar -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<!-- Custom JS -->
<script src="/APM/assets/js/main.js"></script>

<!-- PWA Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/APM/service-worker.js')
        .then(reg => console.log('SW registered'))
        .catch(err => console.log('SW error', err));
}
</script>

<?php if (isset($extraJs)): ?>
<script><?= $extraJs ?></script>
<?php endif; ?>

</body>
</html>
