<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; $cur = $_SERVER['REQUEST_URI'] ?? ''; ?>
<aside class="sidebar">
    <div class="sidebar-title">Principal</div>
    <a href="<?php echo $bp; ?>/cliente/dashboard"     class="sidebar-link <?php echo strpos($cur,'dashboard') !== false ? 'active':''; ?>"><i class="fas fa-gauge"></i> Painel</a>
    <a href="<?php echo $bp; ?>/cliente/pedido/novo"   class="sidebar-link <?php echo strpos($cur,'pedidonovo') !== false || strpos($cur,'pedido/novo') !== false ? 'active':''; ?>"><i class="fas fa-circle-plus"></i> Pedir Socorro</a>
    <a href="<?php echo $bp; ?>/cliente/historico"     class="sidebar-link <?php echo strpos($cur,'historico') !== false ? 'active':''; ?>"><i class="fas fa-clock-rotate-left"></i> Histórico</a>
    <a href="<?php echo $bp; ?>/cliente/financeiro"    class="sidebar-link <?php echo strpos($cur,'financeiro') !== false ? 'active':''; ?>"><i class="fas fa-wallet"></i> Financeiro</a>
    <div class="sidebar-title mt-3">Conta</div>
    <a href="<?php echo $bp; ?>/cliente/veiculos"      class="sidebar-link <?php echo strpos($cur,'veiculo') !== false ? 'active':''; ?>"><i class="fas fa-car"></i> Veículos</a>
    <a href="<?php echo $bp; ?>/cliente/oficinas"      class="sidebar-link <?php echo strpos($cur,'oficina') !== false ? 'active':''; ?>"><i class="fas fa-wrench"></i> Oficinas</a>
    <a href="<?php echo $bp; ?>/cliente/perfil"        class="sidebar-link <?php echo strpos($cur,'perfil') !== false ? 'active':''; ?>"><i class="fas fa-user-pen"></i> Meu Perfil</a>
</aside>
