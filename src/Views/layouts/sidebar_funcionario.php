<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $cur = $_SERVER['REQUEST_URI'] ?? ''; ?>
<aside class="sidebar">
    <div class="sidebar-title">Atendimento</div>
    <a href="<?php echo $bp; ?>/funcionario/dashboard"  class="sidebar-link <?php echo strpos($cur,'dashboard')  !== false ? 'active':''; ?>"><i class="fas fa-gauge"></i> Painel</a>
    <a href="<?php echo $bp; ?>/funcionario/pedidos"    class="sidebar-link <?php echo strpos($cur,'pedidos')    !== false ? 'active':''; ?>"><i class="fas fa-list"></i> Pedidos</a>
    <a href="<?php echo $bp; ?>/funcionario/financeiro" class="sidebar-link <?php echo strpos($cur,'financeiro') !== false ? 'active':''; ?>"><i class="fas fa-coins"></i> Financeiro</a>
    <a href="<?php echo $bp; ?>/funcionario/demandas"   class="sidebar-link <?php echo strpos($cur,'demandas')   !== false ? 'active':''; ?>"><i class="fas fa-paper-plane"></i> Minhas demandas</a>
</aside>
