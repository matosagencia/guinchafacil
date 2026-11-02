<?php
$clientPanelUser = $_SESSION['user'] ?? [];
$clientPanelName = trim((string)($clientPanelUser['nome'] ?? 'Cliente')) ?: 'Cliente';
$clientPanelBase = defined('BASE_PATH') ? BASE_PATH : '';
?>
<header class="gf-panel-topbar">
    <div class="gf-panel-breadcrumb">
        <span>Painel <i class="fas fa-chevron-right" aria-hidden="true"></i> <strong>Cliente</strong></span>
    </div>
    <div class="gf-panel-actions">
        <span><?php echo htmlspecialchars($clientPanelName, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="<?php echo htmlspecialchars($clientPanelBase, ENT_QUOTES, 'UTF-8'); ?>/logout" data-confirm-logout><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sair</a>
    </div>
</header>
