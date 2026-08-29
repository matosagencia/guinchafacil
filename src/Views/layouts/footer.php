<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; ?>
</main><!-- /main-content -->
</div><!-- /main-wrapper -->

<footer>
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <span>&copy; <?php echo date('Y'); ?> GuinchaFácil. Todos os direitos reservados.</span>
            </div>
            <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
                <a href="<?php echo htmlspecialchars($bp); ?>/termos-servico.php">Termos de Uso</a> &middot;
                <a href="<?php echo htmlspecialchars($bp); ?>/politica-privacidade.php">Privacidade</a> &middot;
                <a href="mailto:<?php echo htmlspecialchars((string)ADMIN_EMAIL); ?>">Contato</a>
            </div>
        </div>
    </div>
</footer>

<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
