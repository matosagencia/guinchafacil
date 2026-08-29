<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
?>
<aside class="shell-sidebar sidebar sidebar--ops admin-shell-sidebar">
    <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    <div class="sidebar-sep"></div>
    <div class="sidebar-preview">
        <div class="sidebar-section-header"><i class="fas fa-eye"></i> Ver como</div>
        <a href="<?php echo htmlspecialchars($bp); ?>/guincho/dashboard" class="ops-nav-link sidebar-link-preview"><span class="ops-nav-link__icon"><i class="fas fa-truck-pickup"></i></span><span class="ops-nav-link__label">Área Guincheiro</span><span class="sidebar-preview-badge">PREVIEW</span></a>
        <a href="<?php echo htmlspecialchars($bp); ?>/cliente/dashboard" class="ops-nav-link sidebar-link-preview"><span class="ops-nav-link__icon"><i class="fas fa-user"></i></span><span class="ops-nav-link__label">Área Cliente</span><span class="sidebar-preview-badge">PREVIEW</span></a>
    </div>
</aside>
