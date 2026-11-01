<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
$mk = static function (string $chave) use ($cur): string {
    return strpos($cur, $chave) !== false ? 'active' : '';
};
$nomeOficina = $oficina['nome'] ?? ($_SESSION['user']['nome'] ?? 'Oficina');
?>
<aside class="bo-sidebar sidebar gf-unified-sidebar gf-profile-sidebar gf-f18c-sidebar" aria-label="Navegação da oficina">
    <a class="gf-profile-brand" href="<?= $bp ?>/oficina/dashboard"><img src="<?= $bp ?>/public/assets/img/logo-48.png" alt="GuinchaFácil" width="30" height="30"><span>Guincha<strong>Fácil</strong></span></a>
    <div class="gf-profile-workspace"><span class="gf-profile-avatar"><i class="fas fa-screwdriver-wrench" aria-hidden="true"></i></span><span><strong>Área da oficina</strong><small>Workspace operacional</small></span></div>
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
    <div class="gf-profile-sidebar-footer"><a href="<?= $bp ?>/">Voltar ao site</a></div>
</aside>
