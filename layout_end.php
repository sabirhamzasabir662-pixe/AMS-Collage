    </main><!-- /page-body -->

    <footer class="main-footer">
        &copy; <?= date('Y') ?> <?= APP_NAME ?> — Management System
    </footer>
</div><!-- /main-content -->
</div><!-- /app-wrapper -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="<?= isset($depth) ? $depth : '../' ?>js/script.js"></script>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
