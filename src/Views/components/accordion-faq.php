<?php
$grupos = isset($grupos) && is_array($grupos) ? $grupos : [];
$esc = static function ($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
};
?>
<section class="gf-accordion-faq" aria-label="Perguntas frequentes">
    <?php foreach ($grupos as $tema => $itens): ?>
        <?php if (!is_array($itens) || $itens === []): continue; endif; ?>
        <section class="gf-accordion-faq__grupo">
            <h3 class="gf-accordion-faq__tema"><?php echo $esc($tema); ?></h3>
            <?php foreach ($itens as $item): ?>
                <?php if (!is_array($item)): continue; endif; ?>
                <details class="gf-accordion-faq__item">
                    <summary><?php echo $esc($item['pergunta'] ?? ''); ?></summary>
                    <div class="gf-accordion-faq__resposta"><?php echo nl2br($esc($item['resposta'] ?? '')); ?></div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</section>
