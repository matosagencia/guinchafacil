<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <?php
    $flash = $_SESSION['_flash'] ?? null;
    if ($flash) { unset($_SESSION['_flash']); }
    ?>
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']==='error'?'danger':$flash['type']; ?> mb-3">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-user me-2 text-primary-custom"></i>
                <?php echo htmlspecialchars($usuario['nome']); ?>
                <span class="badge-perfil <?php echo $usuario['tipo']; ?> ms-2"><?php echo ucfirst($usuario['tipo']); ?></span>
            </div>
            <div class="page-subtitle">ID #<?php echo $usuario['id']; ?> &bull; <?php echo htmlspecialchars($usuario['email']); ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $bp; ?>/admin/usuarios" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <a href="<?php echo $bp; ?>/admin/usuario/editar/<?php echo (int)$usuario['id']; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-edit me-1"></i>Editar
            </a>
            <?php if ($usuario['ativo']): ?>
            <form method="POST" action="<?php echo $bp; ?>/admin/usuario/suspender" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                <button class="btn btn-warning btn-sm" onclick="return confirm('Suspender este usuário?')">
                    <i class="fas fa-ban me-1"></i>Suspender
                </button>
            </form>
            <?php else: ?>
            <form method="POST" action="<?php echo $bp; ?>/admin/usuario/ativar" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                <button class="btn btn-success btn-sm">
                    <i class="fas fa-check me-1"></i>Reativar
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Dados do usuário -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-id-card me-2"></i>Dados do Usuário</div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="stat-icon" style="margin:0;width:56px;height:56px;font-size:1.6rem">
                            <i class="fas fa-<?php echo $usuario['tipo']==='guincho'?'truck':'user'; ?>"></i>
                        </div>
                        <div>
                            <div style="font-weight:800;font-size:1.1rem;color:var(--theme-text)"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                            <div style="font-size:.82rem;color:var(--theme-muted)"><?php echo htmlspecialchars($usuario['email']); ?></div>
                        </div>
                    </div>
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:35%">Telefone</td><td><?php echo htmlspecialchars($usuario['telefone'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">CPF</td><td><?php echo htmlspecialchars($usuario['cpf'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Tipo</td><td><span class="badge-perfil <?php echo $usuario['tipo']; ?>"><?php echo ucfirst($usuario['tipo']); ?></span></td></tr>
                        <tr><td style="color:var(--theme-muted)">Status</td>
                            <td><span class="badge <?php echo $usuario['ativo'] ? 'badge-concluido' : 'badge-cancelado'; ?>">
                                <?php echo $usuario['ativo'] ? 'Ativo' : 'Suspenso'; ?>
                            </span></td>
                        </tr>
                        <tr><td style="color:var(--theme-muted)">Cadastro</td><td><?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Último Login</td>
                            <td><?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : '—'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (!empty($extra) && $usuario['tipo'] === 'guincho'): ?>
            <div class="card">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Guincho</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:40%">Placa</td><td><?php echo htmlspecialchars($extra['placa_guincho'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">CNH</td><td><?php echo htmlspecialchars($extra['cnh_numero'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Aprovado</td>
                            <td><span class="badge <?php echo $extra['aprovado'] ? 'badge-concluido':'badge-aguardando_guincho'; ?>">
                                <?php echo $extra['aprovado'] ? 'Sim' : 'Pendente'; ?>
                            </span></td>
                        </tr>
                        <tr><td style="color:var(--theme-muted)">Disponível</td>
                            <td><span class="badge <?php echo $extra['disponivel'] ? 'badge-concluido':'badge-cancelado'; ?>">
                                <?php echo $extra['disponivel'] ? 'Online' : 'Offline'; ?>
                            </span></td>
                        </tr>
                        <tr><td style="color:var(--theme-muted)">Reputação</td><td><?php echo number_format($extra['reputacao'] ?? 0, 1); ?> / 5.0</td></tr>
                        <tr><td style="color:var(--theme-muted)">Avaliações</td><td><?php echo (int)($extra['total_avaliacoes'] ?? 0); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Pix</td><td><?php echo htmlspecialchars($extra['chave_pix'] ?? '—'); ?></td></tr>
                    </table>

                    <?php if (!$extra['aprovado']): ?>
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/aprovar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $extra['id']; ?>">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-check me-1"></i>Aprovar</button>
                        </form>
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/rejeitar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $extra['id']; ?>">
                            <button class="btn btn-danger btn-sm w-100" onclick="return confirm('Rejeitar este guincho?')">
                                <i class="fas fa-times me-1"></i>Rejeitar
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($extra['doc_cnh_frente'])): ?>
                    <div class="mt-3">
                        <div style="font-size:.8rem;color:var(--theme-muted);margin-bottom:.5rem">Documentos</div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="/uploads/<?php echo htmlspecialchars($extra['doc_cnh_frente']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file me-1"></i>CNH Frente
                            </a>
                            <?php if (!empty($extra['doc_cnh_verso'])): ?>
                            <a href="/uploads/<?php echo htmlspecialchars($extra['doc_cnh_verso']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-file me-1"></i>CNH Verso
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pedidos do usuário -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list-check me-2"></i>
                    <?php echo $usuario['tipo'] === 'guincho' ? 'Atendimentos Realizados' : 'Histórico de Pedidos'; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pedidos)): ?>
                    <div class="p-4 text-center" style="color:var(--theme-muted)">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Nenhum pedido encontrado.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo $usuario['tipo']==='guincho'?'Cliente':'Guincho'; ?></th>
                                    <th>Problema</th>
                                    <th>Status</th>
                                    <th>Valor</th>
                                    <th>Data</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $p): ?>
                                <tr>
                                    <td><?php echo $p['id']; ?></td>
                                    <td><?php echo htmlspecialchars($p['cliente_nome'] ?? $p['guincho_operador'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($p['tipo_problema'] ?? '—')); ?></td>
                                    <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo str_replace('_',' ',ucfirst($p['status'])); ?></span></td>
                                    <td>R$ <?php echo number_format($p['custo_estimado'] ?? 0, 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></td>
                                    <td>
                                        <a href="<?php echo $bp; ?>/admin/pedido/<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
