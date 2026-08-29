<?php
$comunicados = $comunicados ?? [];
if (empty($comunicados)) {
    return;
}
?>
<section class="communication-carousel mb-4" data-communications>
    <div class="row g-3">
        <?php foreach ($comunicados as $com): ?>
            <div class="col-12">
                <div class="communication-card">
                    <div class="communication-card__body">
                        <?php if (!empty($com['etiqueta'])): ?><span class="communication-card__tag"><?php echo htmlspecialchars($com['etiqueta']); ?></span><?php endif; ?>
                        <h3 class="communication-card__title"><?php echo htmlspecialchars($com['titulo']); ?></h3>
                        <?php if (!empty($com['subtitulo'])): ?><p class="communication-card__text"><?php echo htmlspecialchars($com['subtitulo']); ?></p><?php endif; ?>
                        <?php if (!empty($com['cta_url']) && !empty($com['cta_label'])): ?>
                            <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($com['cta_url']); ?>"><?php echo htmlspecialchars($com['cta_label']); ?></a>
                        <?php endif; ?>
                    </div>
                    <div class="communication-art" aria-hidden="true"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
