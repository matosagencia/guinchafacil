<?php

// File: guinchafacil/src/Views/layouts/header.php

$bp   = defined('BASE_PATH') ? BASE_PATH : '';

$user = $_SESSION['user'] ?? null;

$tipo = is_array($user) ? ($user['tipo'] ?? null) : null;

$nome = is_array($user) ? ($user['nome'] ?? 'Usuário') : 'Usuário';

// Admin visitando área de outro perfil mantém seu próprio tema

$temaClass = $tipo ?? '';

// Pacote L2.1: mapeia o perfil (mesma classe já usada no <body> abaixo,
// já compatível com body.cliente/body.guincho/body.admin) para o arquivo
// de tema novo em public/assets/css/themes/. Arquivo de tema é opcional —
// perfis sem sessão (páginas públicas) simplesmente não recebem nenhum.
$temaArquivoMap = ['cliente' => 'client', 'guincho' => 'tow', 'especialista' => 'especialista', 'admin' => 'admin', 'funcionario' => 'funcionario', 'gerente' => 'gerente'];
$temaArquivo = $temaArquivoMap[$temaClass] ?? null;

// Modo de debug global (arquitetura de observabilidade): expõe pro JS de
// toda página autenticada se o admin ligou `debug_mode_ativo`. Ver
// DebugMode.php (backend) e public/assets/js/debug.js (frontend/gfDebug()).
require_once __DIR__ . '/../../Services/DebugMode.php';
$debugModeAtivo = DebugMode::jsFlag();

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">

    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">

    <!-- Pacote L2.1: fundação do design system modular (tokens/base/shell/tema).
         Aditivo a style.css acima (nomes de variável diferentes, não conflita);
         componentes/páginas novos dos pacotes L2.2+ passam a consumir isto. -->
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/tokens.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/base.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/shell.css?v=20260802-2" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/components/page-head.css" rel="stylesheet">
    <?php if ($temaArquivo): ?>
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/themes/<?php echo htmlspecialchars($temaArquivo); ?>.css" rel="stylesheet">
    <?php endif; ?>
    <?php if ($temaClass === 'admin'): ?>
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css?v=20260801-4" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-classic-shell.css?v=20260801-4" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-reference.css?v=20260802-3" rel="stylesheet">
    <?php endif; ?>

    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">

    <title>GuinchaFácil</title>

    <?php if (in_array($temaClass, ['cliente', 'guincho'], true)): ?>
    <?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
    <?php endif; ?>

    
    <script<?php echo csp_script_nonce_attr(); ?>>window.APP_DEBUG = <?php echo $debugModeAtivo; ?>;</script>
    <script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/debug.js"></script>
    <script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/bootstrap-config.js"></script>
    <script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/session-manager.js"></script>
    <script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/ui-hooks.js"></script>
    <script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/app.js"></script>
    <?php if ($temaClass === 'admin'): ?>
    <script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-global-search.js?v=20260802-2"></script>
    <?php endif; ?>
</head>

<body class="<?php echo htmlspecialchars($temaClass); ?>" data-base-path="<?php echo htmlspecialchars($bp); ?>">
<?php if ($temaClass === 'admin'): ?>
<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    try {
        if (localStorage.getItem('gf_admin_sidebar_collapsed') === '1') {
            document.body.classList.add('is-sidebar-collapsed');
        }
    } catch (e) {}
})();
</script>
<?php endif; ?>

<div class="modal fade" id="sessionExpiredModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clock me-2"></i>Sessão expirada</h5>
            </div>
            <div class="modal-body">
                Sua sessão terminou por segurança. Entre novamente para continuar nesta mesma página.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-session-login>
                    Entrar novamente
                </button>
            </div>
        </div>
    </div>
</div>




<nav class="navbar navbar-expand-lg">

    <div class="container-fluid px-3">



        <!-- Brand -->

        <a class="navbar-brand" href="<?php echo htmlspecialchars($bp); ?>/">

            <img src="<?php echo htmlspecialchars($bp); ?>/public/assets/img/logo-48.png"

                 alt="GuinchaFácil" width="30" height="30" style="border-radius:8px">

            <span>Guincha</span>Fácil

            <?php if ($tipo): ?>

                <span class="badge-perfil <?php echo htmlspecialchars($tipo); ?>">

                    <?php echo htmlspecialchars(ucfirst($tipo)); ?>

                </span>

            <?php endif; ?>

        </a>



        <!-- Toggler (mobile) -->

        <button class="navbar-toggler border-0" type="button"

                data-bs-toggle="collapse" data-bs-target="#navbarMain"

                aria-controls="navbarMain" aria-expanded="false" aria-label="Menu">

            <span class="navbar-toggler-icon"></span>

        </button>



        <!-- Links -->

        <div class="collapse navbar-collapse" id="navbarMain">

            <!-- Links de navegação por perfil: só aparecem no mobile
                 (d-lg-none), onde a sidebar fica escondida (ver regra
                 @media (max-width: 991.98px) .sidebar{display:none} em
                 style.css). No desktop a navegação já vem pela sidebar
                 (sidebar_guincho.php / sidebar_cliente / sidebar_admin),
                 então esses links ficam ocultos para não duplicar. -->

            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1 d-lg-none">



                <?php if ($tipo === 'admin'): ?>

                    <!-- ADMIN -->

                    <li class="nav-item dropdown">

                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">

                            <i class="fas fa-shield-halved me-1"></i>Admin

                        </a>

                        <ul class="dropdown-menu dropdown-menu-dark">

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/dashboard">

                                <i class="fas fa-gauge me-2"></i>Dashboard</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/pedidos">

                                <i class="fas fa-list me-2"></i>Pedidos</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/novo">

                                <i class="fas fa-plus me-2"></i>Novo Pedido</a></li>

                            <li><hr class="dropdown-divider" style="border-color:#444"></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/usuarios">

                                <i class="fas fa-users me-2"></i>Usuários</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/usuario/novo">

                                <i class="fas fa-user-plus me-2"></i>Criar Cliente</a></li>

                            <li><hr class="dropdown-divider" style="border-color:#444"></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/guinchos">

                                <i class="fas fa-truck me-2"></i>Guinchos</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/guincho/novo">

                                <i class="fas fa-truck-medical me-2"></i>Cadastrar Guincheiro</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/especialistas">
                                <i class="fas fa-screwdriver-wrench me-2 text-warning"></i>Especialistas</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/catalogo-servicos/tipos">
                                <i class="fas fa-tags me-2"></i>Catálogo e preços de serviços</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/produtos">
                                <i class="fas fa-box me-2"></i>Produtos e peças</a></li>

                            <li><hr class="dropdown-divider" style="border-color:#444"></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/financeiro">

                                <i class="fas fa-chart-line me-2"></i>Financeiro</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/configuracoes">

                                <i class="fas fa-gear me-2"></i>Configurações</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/admin/logs">

                                <i class="fas fa-terminal me-2"></i>Logs</a></li>

                        </ul>

                    </li>

                    <!-- VER COMO - admin visualiza outras áreas -->

                    <li class="nav-item dropdown">

                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"

                           style="border: 1px dashed rgba(47,179,74,.5); border-radius:8px; padding-left:.75rem; padding-right:.75rem;">

                            <i class="fas fa-eye me-1" style="color:var(--primary)"></i>Ver como

                        </a>

                        <ul class="dropdown-menu dropdown-menu-dark">

                            <li class="px-3 py-1" style="font-size:.7rem;color:#888;letter-spacing:.06em;text-transform:uppercase">

                                Visualizar como outro perfil</li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/guincho/dashboard">

                                <i class="fas fa-truck-pickup me-2 text-success"></i>Área Guincheiro</a></li>

                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($bp); ?>/cliente/dashboard">

                                <i class="fas fa-user me-2 text-info"></i>Área Cliente</a></li>

                        </ul>

                    </li>



                <?php elseif ($tipo === 'guincho'): ?>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/guincho/dashboard">

                            <i class="fas fa-gauge me-1"></i>Painel</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/guincho/historico">

                            <i class="fas fa-clock-rotate-left me-1"></i>Histórico</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/guincho/financeiro">

                            <i class="fas fa-coins me-1"></i>Financeiro</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/guincho/perfil">

                            <i class="fas fa-user-pen me-1"></i>Meu Perfil</a></li>



                <?php elseif ($tipo === 'funcionario'): ?>

                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/funcionario/dashboard">
                            <i class="fas fa-gauge me-1"></i>Painel</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/funcionario/pedidos">
                            <i class="fas fa-list me-1"></i>Pedidos</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/funcionario/financeiro">
                            <i class="fas fa-coins me-1"></i>Financeiro</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/funcionario/demandas">
                            <i class="fas fa-paper-plane me-1"></i>Minhas demandas</a></li>

                <?php elseif ($tipo === 'gerente'): ?>

                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/gerente/dashboard">
                            <i class="fas fa-gauge me-1"></i>Painel</a></li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/gerente/demandas">
                            <i class="fas fa-clipboard-check me-1"></i>Demandas pendentes</a></li>

                <?php elseif ($tipo === 'cliente'): ?>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/cliente/dashboard">

                            <i class="fas fa-gauge me-1"></i>Painel</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/cliente/pedido/novo">

                            <i class="fas fa-circle-plus me-1"></i>Pedir Socorro</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/cliente/historico">

                            <i class="fas fa-clock-rotate-left me-1"></i>Histórico</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/cliente/veiculos">

                            <i class="fas fa-car me-1"></i>Veículos</a></li>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/cliente/oficinas">

                            <i class="fas fa-wrench me-1"></i>Oficinas</a></li>

                <?php endif; ?>

            </ul>



            <!-- Usuário / sair -->

            <ul class="navbar-nav ms-auto">

                <?php if ($user): ?>

                    <li class="nav-item d-flex align-items-center me-2">

                        <span style="font-size:.82rem;color:var(--theme-muted)">

                            <i class="fas fa-circle-user me-1"></i><?php echo htmlspecialchars($nome); ?>

                        </span>

                    </li>

                    <li class="nav-item">

                        <a class="nav-link text-danger" href="<?php echo htmlspecialchars($bp); ?>/logout"
                           data-confirm-logout>

                            <i class="fas fa-right-from-bracket me-1"></i>Sair

                        </a>

                    </li>

                <?php else: ?>

                    <li class="nav-item">

                        <a class="nav-link" href="<?php echo htmlspecialchars($bp); ?>/login">

                            <i class="fas fa-right-to-bracket me-1"></i>Entrar

                        </a>

                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>

<?php if ($temaClass === 'admin'): ?>
<div class="admin-global-search" data-admin-global-search>
    <div class="admin-global-search__inner">
        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" placeholder="Buscar nesta tela por pedido, cliente, guincho ou evento" autocomplete="off" aria-label="Buscar nesta tela">
        <span class="admin-global-search__hint">Busca local</span>
    </div>
</div>
<?php
$moduleTabs = [];
$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($adminPath, '/admin/financeiro') || str_starts_with($adminPath, '/admin/carteira') || str_starts_with($adminPath, '/admin/saques')) {
    $moduleTabs = [
        ['path' => '/admin/financeiro', 'label' => 'Visão geral / Pagamentos', 'icon' => 'fa-chart-line'],
        ['path' => '/admin/carteiras', 'label' => 'Carteiras', 'icon' => 'fa-wallet'],
        ['path' => '/admin/saques', 'label' => 'Saques e repasses', 'icon' => 'fa-money-bill-transfer'],
    ];
} elseif (str_starts_with($adminPath, '/admin/avaliacoes') || str_starts_with($adminPath, '/admin/proof-of-road') || str_starts_with($adminPath, '/admin/checklists-incompletos')) {
    $moduleTabs = [
        ['path' => '/admin/avaliacoes', 'label' => 'Avaliações', 'icon' => 'fa-star-half-stroke'],
        ['path' => '/admin/proof-of-road', 'label' => 'Proof-of-Road', 'icon' => 'fa-route'],
        ['path' => '/admin/checklists-incompletos', 'label' => 'Checklists incompletos', 'icon' => 'fa-list-check'],
        ['path' => '', 'label' => 'Revisões manuais', 'disabled' => true],
    ];
} elseif (str_starts_with($adminPath, '/admin/servicos') || str_starts_with($adminPath, '/admin/catalogo-servicos') || str_starts_with($adminPath, '/admin/produto')) {
    $moduleTabs = [
        ['path' => '/admin/servicos', 'label' => 'Serviços do cliente', 'icon' => 'fa-user-facing'],
        ['path' => '/admin/catalogo-servicos/tipos', 'label' => 'Tipos operacionais', 'icon' => 'fa-toolbox'],
        ['path' => '/admin/catalogo-servicos/capacidades', 'label' => 'Capacidades', 'icon' => 'fa-user-check'],
        ['path' => '/admin/catalogo-servicos/compatibilidade', 'label' => 'Compatibilidade', 'icon' => 'fa-truck'],
        ['path' => '/admin/catalogo-servicos/tarifas', 'label' => 'Tarifas', 'icon' => 'fa-tags'],
        ['path' => '/admin/produtos', 'label' => 'Produtos e peças', 'icon' => 'fa-box'],
    ];
}
if ($moduleTabs) include __DIR__ . '/../components/admin_module_tabs.php';
?>
<?php endif; ?>

