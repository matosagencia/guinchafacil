<?php
/**
 * Fragmento do painel de detalhe de um usuário — conteúdo do workspace de
 * /admin/usuarios (shell-ops), devolvido como HTML puro por
 * AdminController::usuarioDetalheFragmento() e injetado via fetch()+innerHTML
 * pelo JS da lista. NÃO inclui header/footer/sidebar — só o miolo.
 * Reaproveita 100% a mesma lógica visual da antiga página standalone
 * usuariodetalhe.php.
 *
 * @var array $usuario
 * @var array|null $extra
 * @var array $pedidos
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
?>
<header class="ops-order-header">
    <div>
        <button type="button" class="ops-back-link" data-action="us-clear-selection">
            <i class="fas fa-arrow-left"></i> Todos os usuários
        </button>
        <h1>
            <?php echo htmlspecialchars($usuario['nome']); ?>
            <span class="ops-badge ops-badge--audit" style="margin-left:8px;"><?php echo ucfirst($usuario['tipo']); ?></span>
        </h1>
        <p>ID #<?php echo (int)$usuario['id']; ?> &bull; <?php echo htmlspecialchars($usuario['email']); ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/usuario/editar/<?php echo (int)$usuario['id']; ?>" class="ops-btn">
            <i class="fas fa-edit"></i> Editar
        </a>
        <?php if ($usuario['ativo']): ?>
        <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/admin/usuario/suspender" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">
            <input type="hidden" name="retorno_usuario_id" value="<?php echo (int)$usuario['id']; ?>">
            <button class="btn btn-warning btn-sm" data-confirm-message="Suspender este usuário?">
                <i class="fas fa-ban me-1"></i>Suspender
            </button>
        </form>
        <?php else: ?>
        <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/admin/usuario/ativar" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">
            <input type="hidden" name="retorno_usuario_id" value="<?php echo (int)$usuario['id']; ?>">
            <button class="btn btn-success btn-sm">
                <i class="fas fa-check me-1"></i>Reativar
            </button>
        </form>
        <?php endif; ?>
    </div>
</header>

<div style="padding:18px 24px 32px">
<div class="row g-4">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-id-card me-2"></i>Dados do Usuário</div>
            <div class="card-body">
                <table class="table table-sm mb-0 ghd-table">
                    <tr><td class="ghd-label ghd-label--35">Telefone</td><td><?php echo htmlspecialchars($usuario['telefone'] ?? '—'); ?></td></tr>
                    <tr><td class="ghd-label">CPF</td><td><?php echo htmlspecialchars($usuario['cpf'] ?? '—'); ?></td></tr>
                    <tr><td class="ghd-label">Status</td>
                        <td><span class="badge <?php echo $usuario['ativo'] ? 'badge-concluido' : 'badge-cancelado'; ?>">
                            <?php echo $usuario['ativo'] ? 'Ativo' : 'Suspenso'; ?>
                        </span></td>
                    </tr>
                    <tr><td class="ghd-label">Cadastro</td><td><?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></td></tr>
                    <tr><td class="ghd-label">Último Login</td>
                        <td><?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : '—'; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if (!empty($extra) && $usuario['tipo'] === 'guincho'): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Guincho</div>
            <div class="card-body">
                <table class="table table-sm mb-0 ghd-table">
                    <tr><td class="ghd-label ghd-label--40">Placa</td><td><?php echo htmlspecialchars($extra['placa_guincho'] ?? '—'); ?></td></tr>
                    <tr><td class="ghd-label">CNH</td><td><?php echo htmlspecialchars($extra['cnh_numero'] ?? '—'); ?></td></tr>
                    <tr><td class="ghd-label">Aprovado</td>
                        <td><span class="badge <?php echo $extra['aprovado'] ? 'badge-concluido':'badge-aguardando_guincho'; ?>">
                            <?php echo $extra['aprovado'] ? 'Sim' : 'Pendente'; ?>
                        </span></td>
                    </tr>
                    <tr><td class="ghd-label">Disponível</td>
                        <td><span class="badge <?php echo $extra['disponivel'] ? 'badge-concluido':'badge-cancelado'; ?>">
                            <?php echo $extra['disponivel'] ? 'Online' : 'Offline'; ?>
                        </span></td>
                    </tr>
                    <tr><td class="ghd-label">Reputação</td><td><?php echo number_format($extra['reputacao'] ?? 0, 1); ?> / 5.0</td></tr>
                    <tr><td class="ghd-label">Avaliações</td><td><?php echo (int)($extra['total_avaliacoes'] ?? 0); ?></td></tr>
                    <tr><td class="ghd-label">Pix</td><td><?php echo htmlspecialchars($extra['chave_pix'] ?? '—'); ?></td></tr>
                </table>

                <?php if (!$extra['aprovado']): ?>
                <div class="d-flex gap-2 mt-3">
                    <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/admin/guincho/aprovar" class="flex-fill">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$extra['id']; ?>">
                        <button class="btn btn-primary btn-sm w-100"><i class="fas fa-check me-1"></i>Aprovar</button>
                    </form>
                    <form method="POST" action="<?php echo htmlspecialchars($bp); ?>/admin/guincho/rejeitar" class="flex-fill">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="id" value="<?php echo (int)$extra['id']; ?>">
                        <button class="btn btn-danger btn-sm w-100" data-confirm-message="Rejeitar este guincho?">
                            <i class="fas fa-times me-1"></i>Rejeitar
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (!empty($extra['doc_cnh_frente'])): ?>
                <div class="mt-3">
                    <div class="small fw-bold mb-1">Documentos</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo htmlspecialchars($bp); ?>/arquivo/<?php echo (int)$extra['id']; ?>?tipo=doc_cnh_frente" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file me-1"></i>CNH Frente
                        </a>
                        <?php if (!empty($extra['doc_cnh_verso'])): ?>
                        <a href="<?php echo htmlspecialchars($bp); ?>/arquivo/<?php echo (int)$extra['id']; ?>?tipo=doc_cnh_verso" target="_blank" class="btn btn-sm btn-outline-primary">
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

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list-check me-2"></i>
                <?php echo $usuario['tipo'] === 'guincho' ? 'Atendimentos Realizados' : 'Histórico de Pedidos'; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pedidos)): ?>
                <div class="p-4 text-center ghd-empty">
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
                                    <a href="<?php echo htmlspecialchars($bp); ?>/admin/pedido/<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">
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
</div>
