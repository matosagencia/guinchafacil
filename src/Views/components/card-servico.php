<?php
$icone = isset($icone) ? (string) $icone : 'fas fa-check';
$titulo = isset($titulo) ? (string) $titulo : '';
$texto = isset($texto) ? (string) $texto : '';
$esc = static function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<article class="gf-card-servico">
    <div class="gf-card-servico__icone" aria-hidden="true">
        <svg viewBox="0 0 48 48" focusable="false">
            <circle cx="24" cy="24" r="22" fill="currentColor" opacity=".12"></circle>
            <path d="M16 24h16M24 16v16" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2.5"></path>
        </svg>
        <i class="<?php echo $esc($icone); ?>"></i>
    </div>
    <h3 class="gf-card-servico__titulo"><?php echo $esc($titulo); ?></h3>
    <p class="gf-card-servico__texto"><?php echo $esc($texto); ?></p>
</article>
