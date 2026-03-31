</main>

<!-- ── Footer ───────────────────────────────────────────────────────────────── -->
<footer class="footer bg-light border-top py-2 mt-4">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center small text-muted">
            <span><i class="bi bi-hospital text-csi"></i> Système CSI AMA Maradi — v1.0</span>
            <span><?= date('d/m/Y H:i') ?></span>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if (isset($extraJs)) echo $extraJs; ?>

</body>
</html>
