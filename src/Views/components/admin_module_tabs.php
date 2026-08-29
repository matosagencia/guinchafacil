<?php
$moduleTabs = $moduleTabs ?? [];
$moduleCurrent = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (!$moduleTabs) return;
?>
<nav class="admin-module-tabs" aria-label="Navegação do módulo">
    <?php foreach ($moduleTabs as $tab): ?>
        <?php $active = ($tab['path'] ?? '') !== '' && str_starts_with($moduleCurrent, (string)$tab['path']); ?>
        <?php if (!empty($tab['disabled'])): ?>
            <span class="admin-module-tab is-disabled" aria-disabled="true"><?php echo htmlspecialchars((string)$tab['label']); ?></span>
        <?php else: ?>
            <a class="admin-module-tab <?php echo $active ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars($bp . (string)$tab['path']); ?>">
                <?php if (!empty($tab['icon'])): ?><i class="fas <?php echo htmlspecialchars((string)$tab['icon']); ?>" aria-hidden="true"></i><?php endif; ?>
                <?php echo htmlspecialchars((string)$tab['label']); ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
