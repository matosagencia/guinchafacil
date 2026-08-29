<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
// Mostra "Tornar-se guincheiro" só para quem ainda não tem reboque aprovado.
$sbMostrarTornarGuincho = true;
try {
    if (!empty($_SESSION['user']['id']) && class_exists('Guincho')) {
        $sbG = Guincho::buscarPorUsuario((int)$_SESSION['user']['id']);
        if ($sbG && (int)($sbG['reboque_aprovado'] ?? 0) === 1) {
            $sbMostrarTornarGuincho = false;
        }
    }
} catch (\Throwable $e) { /* silencioso */ }
?>
<aside class="sidebar">
    <div class="sidebar-title">Operação</div>
    <a href="<?php echo $bp; ?>/guincho/dashboard"  class="sidebar-link <?php echo strpos($cur,'dashboard')  !== false ? 'active':''; ?>"><i class="fas fa-gauge"></i> Painel</a>
    <a href="<?php echo $bp; ?>/guincho/dashboard#fila-ofertas" class="sidebar-link <?php echo strpos($cur,'dashboard') !== false && strpos($cur,'ofertas') !== false ? 'active':''; ?>"><i class="fas fa-bell"></i> Ofertas</a>
    <a href="<?php echo $bp; ?>/guincho/historico"  class="sidebar-link <?php echo strpos($cur,'historico')  !== false ? 'active':''; ?>"><i class="fas fa-clock-rotate-left"></i> Histórico</a>
    <a href="<?php echo $bp; ?>/guincho/financeiro" class="sidebar-link <?php echo strpos($cur,'financeiro') !== false ? 'active':''; ?>"><i class="fas fa-coins"></i> Financeiro</a>
    <div class="sidebar-title mt-3">Conta</div>
    <a href="<?php echo $bp; ?>/guincho/operacao"   class="sidebar-link <?php echo strpos($cur,'operacao')    !== false ? 'active':''; ?>"><i class="fas fa-truck-pickup"></i> Operação</a>
    <a href="<?php echo $bp; ?>/guincho/capacidades" class="sidebar-link <?php echo strpos($cur,'capacidades') !== false ? 'active':''; ?>"><i class="fas fa-toolbox"></i> Serviços que ofereço</a>
    <?php if ($sbMostrarTornarGuincho): ?>
    <a href="<?php echo $bp; ?>/guincho/tornar-se-guincho" class="sidebar-link <?php echo strpos($cur,'tornar-se-guincho') !== false ? 'active':''; ?>"><i class="fas fa-truck-pickup"></i> Tornar-se guincheiro</a>
    <?php endif; ?>
    <a href="<?php echo $bp; ?>/guincho/estoque"    class="sidebar-link <?php echo strpos($cur,'estoque')     !== false ? 'active':''; ?>"><i class="fas fa-boxes-stacked"></i> Meu Estoque</a>
    <a href="<?php echo $bp; ?>/guincho/perfil"     class="sidebar-link <?php echo strpos($cur,'perfil')     !== false ? 'active':''; ?>"><i class="fas fa-user-pen"></i> Meu Perfil</a>
</aside>
