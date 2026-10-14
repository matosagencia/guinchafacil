<?php
$mensagem = isset($mensagem) && $mensagem !== '' ? (string) $mensagem : null;
?>
<aside class="gf-barra-emergencia" role="alert">
    <svg class="gf-barra-emergencia__icone" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7 16h10l-1-7a4 4 0 0 0-8 0l-1 7Z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="2"></path><path d="M4 16h16M4 5l2 2m12 0 2-2M12 2v3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"></path></svg>
    <p class="gf-barra-emergencia__texto"><?php if ($mensagem !== null): ?><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?><?php else: ?>Emerg&ecirc;ncia com v&iacute;timas? Ligue 190 (Pol&iacute;cia) ou 192 (SAMU) antes de pedir guincho.<?php endif; ?></p>
    <a class="gf-barra-emergencia__acao" href="tel:190">Ligar 190</a>
</aside>
