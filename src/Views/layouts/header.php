<?php
// File: guinchafacil/src/Views/layouts/header.php
$bp   = defined('BASE_PATH') ? BASE_PATH : '';
$user = $_SESSION['user'] ?? null;
$tipo = is_array($user) ? ($user['tipo'] ?? null) : null;
$nome = is_array($user) ? ($user['nome'] ?? 'Usuário') : 'Usuário';
// Admin visitando área de outro perfil mantém seu próprio tema
$temaClass = $tipo ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap SEM sourcemap para evitar erro de CSP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bp); ?>/public/assets/img/favicon-32.png">
    <title>GuinchaFácil</title>
    <!-- Suprime requisições de sourcemap -->
    <meta http-equiv="Content-Security-Policy" content="connect-src 'self' https://viacep.com.br https://nominatim.openstreetmap.org https://api.mercadopago.com https://ws.pagseguro.uol.com.br; default-src * 'unsafe-inline' 'unsafe-eval' data: blob:;">
</head>
<body class="<?php echo htmlspecialchars($temaClass); ?>">

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
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">

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
            <ul class="navbar-nav">
                <?php if ($user): ?>
                    <li class="nav-item d-flex align-items-center me-2">
                        <span style="font-size:.82rem;color:var(--theme-muted)">
                            <i class="fas fa-circle-user me-1"></i><?php echo htmlspecialchars($nome); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-danger" href="<?php echo htmlspecialchars($bp); ?>/logout"
                           onclick="return confirm('Sair da conta?');">
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
