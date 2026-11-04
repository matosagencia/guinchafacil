<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$cur = $_SERVER['REQUEST_URI'] ?? '';
$mk = static function (string $path) use ($cur): string { return strpos($cur, $path) !== false ? 'active' : ''; };
?>
<aside class="bo-sidebar sidebar gf-unified-sidebar gf-profile-sidebar gf-f18c-sidebar" aria-label="Navegação do parceiro">
    <a class="gf-profile-brand" href="<?= htmlspecialchars($bp) ?>/parceiro/dashboard"><img src="<?= htmlspecialchars($bp) ?>/public/assets/img/logo.svg" alt="GuinchaFácil" width="30" height="30"><span>Guincha<strong>Fácil</strong></span></a>
    <div class="gf-profile-workspace"><span class="gf-profile-avatar"><i class="fas fa-handshake" aria-hidden="true"></i></span><span><strong>Área do parceiro</strong><small>Workspace de indicação</small></span></div>
    <div class="sidebar-title">Indicações</div>
    <a href="<?= htmlspecialchars($bp) ?>/parceiro/dashboard" class="sidebar-link <?= $mk('/parceiro/dashboard') ?>"><i class="fas fa-gauge"></i> Painel</a>
    <a href="<?= htmlspecialchars($bp) ?>/parceiro/dashboard#link" class="sidebar-link"><i class="fas fa-link"></i> Meu link</a>
    <a href="<?= htmlspecialchars($bp) ?>/parceiro/dashboard#resgates" class="sidebar-link"><i class="fas fa-coins"></i> Comissões e resgates</a>
    <div class="sidebar-title mt-3">Conta</div>
    <a href="<?= htmlspecialchars($bp) ?>/perfil/completar" class="sidebar-link"><i class="fas fa-user-pen"></i> Meu perfil</a>
    <div class="gf-profile-sidebar-footer"><a href="<?= htmlspecialchars($bp) ?>/">Voltar ao site</a></div>
</aside>
