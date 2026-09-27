<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
$mk = static function (string $chave) use ($cur): string {
    return strpos($cur, $chave) !== false ? 'active' : '';
};
$nomeOficina = $oficina['nome'] ?? ($_SESSION['user']['nome'] ?? 'Oficina');
?>
<aside class="sidebar">
    <div class="sidebar-title">Operação</div>

    <a href="<?= $bp ?>/oficina/dashboard" class="sidebar-link <?= $mk('/oficina/dashboard') ?>">
        <i class="fas fa-gauge"></i> Painel
    </a>
    <a href="<?= $bp ?>/oficina/pedidos" class="sidebar-link <?= $mk('/oficina/pedidos') ?>">
        <i class="fas fa-bell"></i> Ofertas
    </a>
    <a href="<?= $bp ?>/oficina/historico" class="sidebar-link <?= $mk('/oficina/historico') ?>">
        <i class="fas fa-clock-rotate-left"></i> Histórico
    </a>
    <a href="<?= $bp ?>/oficina/financeiro" class="sidebar-link <?= $mk('/oficina/financeiro') ?>">
        <i class="fas fa-coins"></i> Financeiro
    </a>

    <div class="sidebar-title mt-3">Conta</div>

    <a href="<?= $bp ?>/oficina/perfil" class="sidebar-link <?= $mk('/oficina/perfil') ?>">
        <i class="fas fa-user-pen"></i> Meu Perfil
    </a>
</aside>