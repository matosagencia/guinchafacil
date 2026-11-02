<?php
$adminPanelUser = $_SESSION['user'] ?? [];
$adminPanelName = trim((string)($adminPanelUser['nome'] ?? 'Administrador')) ?: 'Administrador';
$adminPanelBase = defined('BASE_PATH') ? BASE_PATH : '';
?>
<header class="gf-panel-topbar">
    <div class="gf-panel-breadcrumb">
        <span>Painel <i class="fas fa-chevron-right" aria-hidden="true"></i> <strong>Administração</strong></span>
    </div>
    <div class="gf-panel-actions">
        <span><?php echo htmlspecialchars($adminPanelName, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="<?php echo htmlspecialchars($adminPanelBase, ENT_QUOTES, 'UTF-8'); ?>/logout" data-confirm-logout><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sair</a>
    </div>
</header>
