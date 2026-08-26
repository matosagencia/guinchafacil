<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $cur = $_SERVER['REQUEST_URI'] ?? ''; ?>
<aside class="sidebar">
    <div class="sidebar-title">Operações</div>
    <a href="<?php echo $bp; ?>/guincho/dashboard"  class="sidebar-link <?php echo strpos($cur,'dashboard')  !== false ? 'active':''; ?>"><i class="fas fa-gauge"></i> Painel</a>
    <a href="<?php echo $bp; ?>/guincho/historico"  class="sidebar-link <?php echo strpos($cur,'historico')  !== false ? 'active':''; ?>"><i class="fas fa-clock-rotate-left"></i> Histórico</a>
    <a href="<?php echo $bp; ?>/guincho/financeiro" class="sidebar-link <?php echo strpos($cur,'financeiro') !== false ? 'active':''; ?>"><i class="fas fa-coins"></i> Financeiro</a>
    <div class="sidebar-title mt-3">Conta</div>
    <a href="<?php echo $bp; ?>/guincho/perfil"     class="sidebar-link <?php echo strpos($cur,'perfil')     !== false ? 'active':''; ?>"><i class="fas fa-user-pen"></i> Meu Perfil</a>
</aside>
