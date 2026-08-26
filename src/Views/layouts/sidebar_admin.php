<?php
$bp  = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
function sidebarActive(string $uri, string $match): string {
    return strpos($uri, $match) !== false ? 'active' : '';
}
?>
<aside class="sidebar">

    <!-- Seção Principal -->
    <div class="sidebar-section-header">
        <i class="fas fa-home"></i> Principal
    </div>
    <a href="<?php echo $bp; ?>/admin/dashboard"
       class="sidebar-link <?php echo strpos($cur,'dashboard') !== false ? 'active':''; ?>">
        <i class="fas fa-gauge"></i><span>Dashboard</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/pedidos"
       class="sidebar-link <?php echo (strpos($cur,'/pedidos') !== false || preg_match('#/pedido/\d#',$cur)) ? 'active':''; ?>">
        <i class="fas fa-list-check"></i><span>Todos os Pedidos</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/pedido/novo" class="sidebar-link sidebar-link-action">
        <i class="fas fa-circle-plus"></i><span>Criar Pedido</span>
    </a>

    <div class="sidebar-sep"></div>

    <!-- Seção Usuários -->
    <div class="sidebar-section-header">
        <i class="fas fa-users"></i> Usuários
    </div>
    <a href="<?php echo $bp; ?>/admin/usuarios"
       class="sidebar-link <?php echo (strpos($cur,'/usuarios') !== false) ? 'active':''; ?>">
        <i class="fas fa-users"></i><span>Todos os Usuários</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/usuario/novo" class="sidebar-link sidebar-link-action">
        <i class="fas fa-user-plus"></i><span>Criar Cliente</span>
    </a>

    <div class="sidebar-sep"></div>

    <!-- Seção Guinchos -->
    <div class="sidebar-section-header">
        <i class="fas fa-truck"></i> Guinchos
    </div>
    <a href="<?php echo $bp; ?>/admin/guinchos"
       class="sidebar-link <?php echo (strpos($cur,'/guinchos') !== false || preg_match('#/guincho/\d#',$cur)) ? 'active':''; ?>">
        <i class="fas fa-truck"></i><span>Gerenciar Guinchos</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/guinchospendentes"
       class="sidebar-link <?php echo strpos($cur,'pendentes') !== false ? 'active':''; ?>">
        <i class="fas fa-clock"></i><span>Pendentes Aprovação</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/guincho/novo" class="sidebar-link sidebar-link-action">
        <i class="fas fa-truck-medical"></i><span>Cadastrar Guincheiro</span>
    </a>

    <div class="sidebar-sep"></div>

    <!-- Seção Financeiro & Sistema -->
    <div class="sidebar-section-header">
        <i class="fas fa-cog"></i> Sistema
    </div>
    <a href="<?php echo $bp; ?>/admin/financeiro"
       class="sidebar-link <?php echo strpos($cur,'financeiro') !== false ? 'active':''; ?>">
        <i class="fas fa-chart-line"></i><span>Financeiro</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/configuracoes"
       class="sidebar-link <?php echo strpos($cur,'configuracoes') !== false ? 'active':''; ?>">
        <i class="fas fa-gear"></i><span>Configurações</span>
    </a>
    <a href="<?php echo $bp; ?>/admin/logs"
       class="sidebar-link <?php echo strpos($cur,'logs') !== false ? 'active':''; ?>">
        <i class="fas fa-terminal"></i><span>Logs</span>
    </a>

    <div class="sidebar-sep"></div>

    <!-- Ver Como -->
    <div class="sidebar-section-header" style="color:#2fb34a">
        <i class="fas fa-eye"></i> Ver como
    </div>
    <a href="<?php echo $bp; ?>/guincho/dashboard" class="sidebar-link sidebar-link-preview">
        <i class="fas fa-truck-pickup"></i><span>Área Guincheiro</span>
        <span class="sidebar-preview-badge">PREVIEW</span>
    </a>
    <a href="<?php echo $bp; ?>/cliente/dashboard" class="sidebar-link sidebar-link-preview">
        <i class="fas fa-user"></i><span>Área Cliente</span>
        <span class="sidebar-preview-badge">PREVIEW</span>
    </a>

</aside>
