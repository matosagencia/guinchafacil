<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $cur = $_SERVER['REQUEST_URI'] ?? ''; ?>
<aside class="sidebar">
    <div class="sidebar-title">Aprovações</div>
    <a href="<?php echo $bp; ?>/gerente/dashboard" class="sidebar-link <?php echo strpos($cur,'dashboard') !== false ? 'active':''; ?>"><i class="fas fa-gauge"></i> Painel</a>
    <a href="<?php echo $bp; ?>/gerente/demandas"  class="sidebar-link <?php echo strpos($cur,'demandas')  !== false ? 'active':''; ?>"><i class="fas fa-clipboard-check"></i> Demandas pendentes</a>
</aside>
