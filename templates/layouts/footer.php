</main>

<footer class="footer bg-light border-top py-2 mt-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center small text-muted">
            <span><i class="bi bi-hospital text-csi"></i> Système CSI Direct Aid Maradi — v1.0</span>
            <span><?= date('d/m/Y H:i') ?></span>
        </div>
    </div>
</footer>

<!-- Scripts locaux : jQuery → Bootstrap → DataTables → Chart.js → app.js -->
<script src="<?= asset('assets/vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('bootstrap/js/bootstrap.bundle.min.js') ?>"></script>

<!-- Vérification visuelle : si Bootstrap n'est pas chargé, fallback CDN -->
<script>
if (typeof bootstrap === 'undefined') {
    console.warn('Bootstrap local non chargé, fallback CDN');
    document.write('<scr' + 'ipt src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"><\/scr' + 'ipt>');
}
</script>

<script src="<?= asset('assets/vendor/datatables/js/jquery.dataTables.min.js') ?>"></script>
<script src="<?= asset('assets/vendor/datatables/js/dataTables.bootstrap5.min.js') ?>"></script>
<script src="<?= asset('assets/vendor/chartjs/chart.umd.min.js') ?>"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>

</body>
</html>
