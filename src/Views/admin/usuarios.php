<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$buscaAtual  = $_GET['busca'] ?? '';
$tipoAtual   = $_GET['tipo']  ?? '';
$paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
$totalPaginas = (int)ceil(($total ?? 0) / 20);
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-users me-2 text-primary-custom"></i>Gerenciar Usuários</div>
            <div class="page-subtitle">Total: <strong><?php echo (int)($total ?? 0); ?></strong> usuários</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo $bp; ?>/admin/usuario/novo" class="btn btn-primary btn-sm">
                <i class="fas fa-user-plus me-1"></i>Criar Cliente
            </a>
            <a href="<?php echo $bp; ?>/admin/guincho/novo" class="btn btn-success btn-sm">
                <i class="fas fa-truck me-1"></i>Criar Guincheiro
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['criado'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Usuário criado com sucesso!</div>
    <?php endif; ?>
    <?php if (!empty($_GET['msg'])): ?>
    <div class="alert alert-info mb-3">
        <?php
        $msgs = ['ativado'=>'Usuário reativado.','suspenso'=>'Usuário suspenso.'];
        echo htmlspecialchars($msgs[$_GET['msg']] ?? $_GET['msg']);
        ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="GET" action="<?php echo $bp; ?>/admin/usuarios" class="card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small mb-1">Buscar</label>
                    <input type="text" class="form-control form-control-sm" name="busca"
                           value="<?php echo htmlspecialchars($buscaAtual); ?>"
                           placeholder="Nome ou e-mail...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Tipo</label>
                    <select class="form-select form-select-sm" name="tipo">
                        <option value="">Todos os tipos</option>
                        <option value="admin"   <?php echo $tipoAtual==='admin'   ?'selected':''; ?>>Admin</option>
                        <option value="guincho" <?php echo $tipoAtual==='guincho' ?'selected':''; ?>>Guincho</option>
                        <option value="cliente" <?php echo $tipoAtual==='cliente' ?'selected':''; ?>>Cliente</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-search me-1"></i>Filtrar
                    </button>
                    <a href="<?php echo $bp; ?>/admin/usuarios" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($usuarios)): foreach ($usuarios as $u): ?>
                        <tr>
                            <td>#<?php echo (int)$u['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($u['nome']); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['telefone'] ?? '—'); ?></td>
                            <td><span class="badge-perfil <?php echo $u['tipo']; ?>"><?php echo ucfirst($u['tipo']); ?></span></td>
                            <td>
                                <span class="badge <?php echo $u['ativo'] ? 'badge-concluido' : 'badge-cancelado'; ?>">
                                    <?php echo $u['ativo'] ? 'Ativo' : 'Suspenso'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($u['criado_em'])); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?php echo $bp; ?>/admin/usuario/<?php echo (int)$u['id']; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Ver perfil">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $bp; ?>/admin/usuario/editar/<?php echo (int)$u['id']; ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($u['ativo']): ?>
                                    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/suspender" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <button class="btn btn-sm btn-outline-warning" title="Suspender"
                                                onclick="return confirm('Suspender <?php echo addslashes(htmlspecialchars($u["nome"])); ?>?')">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" action="<?php echo $bp; ?>/admin/usuario/ativar" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <button class="btn btn-sm btn-outline-success" title="Reativar">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5" style="color:var(--theme-muted)">
                                <i class="fas fa-users-slash fa-2x d-block mb-2"></i>
                                Nenhum usuário encontrado.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <li class="page-item <?php echo $i === $paginaAtual ? 'active' : ''; ?>">
                <a class="page-link"
                   href="<?php echo $bp; ?>/admin/usuarios?pagina=<?php echo $i; ?>&tipo=<?php echo urlencode($tipoAtual); ?>&busca=<?php echo urlencode($buscaAtual); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
