<?php
$panelUser = $_SESSION['user'] ?? [];
$panelRole = (string)($panelUser['tipo'] ?? '');
$panelLabel = $panelRole === 'oficina' ? 'Oficina' : 'Guincho';
$panelName = (string)($panelUser['nome'] ?? $panelLabel);
$panelBase = defined('BASE_PATH') ? BASE_PATH : '';
?>
<header class="gf-panel-topbar">
    <div class="gf-panel-breadcrumb">
        <span>Painel <i class="fas fa-chevron-right" aria-hidden="true"></i> <strong><?php echo htmlspecialchars($panelLabel); ?></strong></span>
    </div>
    <div class="gf-panel-actions">
        <span><?php echo htmlspecialchars($panelName); ?></span>
        <a href="<?php echo htmlspecialchars($panelBase); ?>/logout" data-confirm-logout><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sair</a>
    </div>
</header>
