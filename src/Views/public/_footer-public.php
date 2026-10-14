<?php
/**
 * Footer publico centralizado (Onda 5.2 - Abordagem A).
 * Fecha <body> e </html>.
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
?>
<footer class="gf-site-footer">
    <div class="gf-container">
        <div class="gf-footer-top">
            <div>
                <a class="gf-brand" href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>">Guincha<span>Facil</span>.</a>
                <p>Seu caminho continua aqui.</p>
                <span class="gf-status-inline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6h4"/>
                    </svg>
                    Assistencia 24 horas
                </span>
            </div>
            <div>
                <h3>Para voce</h3>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/pre-cotacao">Pedir guincho</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/guincho/niteroi-rj">Guincho em Niteroi</a>
            </div>
            <div>
                <h3>Para parceiros</h3>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/parceiros">Rede de parceiros</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/parceiros/guinchos">Sou guincheiro</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/parceiros/oficinas">Tenho uma oficina</a>
            </div>
            <div>
                <h3>GuinchaFacil</h3>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/mapa-do-site">Mapa do site &nearr;</a>
                <a href="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/pre-cotacao">Faca uma cotacao &rarr;</a>
            </div>
        </div>

        <?php
        $paymentsView = __DIR__ . '/../components/gf-footer-payments.php';
        if (is_file($paymentsView)) {
            require $paymentsView;
        }
        ?>

        <div class="gf-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> GuinchaFacil. Todos os direitos reservados.</span>
            <span>Assistencia 24 horas &middot; Niteroi, RJ</span>
        </div>
    </div>
</footer>

<script<?php echo function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : ''; ?> src="<?php echo htmlspecialchars($bp, ENT_QUOTES, 'UTF-8'); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>