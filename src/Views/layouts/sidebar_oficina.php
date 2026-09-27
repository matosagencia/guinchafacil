<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$is = fn(string $p) => str_contains($uri, $p) ? ' is-active' : '';
$oficinaNome = $oficina['nome'] ?? ($_SESSION['user']['nome'] ?? 'Oficina');
?>
<aside class="ofi-sidebar">
    <div class="ofi-sidebar-header">
        <a href="<?= $bp ?>/oficina/dashboard" class="ofi-sidebar-brand">
            <i class="fas fa-screwdriver-wrench"></i>
            <span><?= htmlspecialchars($oficinaNome) ?></span>
        </a>
        <div class="ofi-sidebar-sub">
            <i class="fas fa-circle<?= !empty($oficina['disponivel']) ? '' : '-o' ?>"
               style="color:<?= !empty($oficina['disponivel']) ? '#22C55E' : '#94A3B8' ?>"></i>
            <?= !empty($oficina['disponivel']) ? 'Online' : 'Offline' ?>
        </div>
    </div>
    <nav class="ofi-sidebar-nav">
        <a href="<?= $bp ?>/oficina/dashboard"  class="ofi-nav-item<?= $is('/oficina/dashboard') ?>"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a href="<?= $bp ?>/oficina/pedidos"    class="ofi-nav-item<?= $is('/oficina/pedidos') ?>"><i class="fas fa-inbox"></i> Pedidos</a>
        <a href="<?= $bp ?>/oficina/historico"  class="ofi-nav-item<?= $is('/oficina/historico') ?>"><i class="fas fa-clock-rotate-left"></i> Historico</a>
        <a href="<?= $bp ?>/oficina/financeiro" class="ofi-nav-item<?= $is('/oficina/financeiro') ?>"><i class="fas fa-coins"></i> Financeiro</a>
        <a href="<?= $bp ?>/oficina/perfil"     class="ofi-nav-item<?= $is('/oficina/perfil') ?>"><i class="fas fa-user-gear"></i> Perfil</a>
    </nav>
    <div class="ofi-sidebar-footer">
        <a href="<?= $bp ?>/logout" class="ofi-nav-item" style="margin:0"><i class="fas fa-arrow-right-from-bracket"></i> Sair</a>
    </div>
</aside>