<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-truck me-2 text-primary-custom"></i>
                <?php echo htmlspecialchars($usuario['nome'] ?? 'Guincho'); ?>
                <span class="badge-perfil guincho ms-2">Guincho</span>
                <?php if (!$guincho['aprovado']): ?>
                <span class="badge badge-aguardando_guincho ms-2">Pendente Aprovação</span>
                <?php endif; ?>
            </div>
            <div class="page-subtitle">Placa: <?php echo htmlspecialchars($guincho['placa_guincho'] ?? '—'); ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $bp; ?>/admin/guinchos" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Guinchos
            </a>
            <a href="<?php echo $bp; ?>/admin/usuario/editar/<?php echo $usuario['id'] ?? 0; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit me-1"></i>Editar
            </a>
            <a href="<?php echo $bp; ?>/admin/usuario/<?php echo $usuario['id'] ?? 0; ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-user me-1"></i>Ver Usuário
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-id-badge me-2"></i>Dados do Operador</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:35%">Nome</td><td><?php echo htmlspecialchars($usuario['nome'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">E-mail</td><td><?php echo htmlspecialchars($usuario['email'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Telefone</td><td><?php echo htmlspecialchars($usuario['telefone'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">CPF</td><td><?php echo htmlspecialchars($usuario['cpf'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Cadastro</td><td><?php echo date('d/m/Y', strtotime($usuario['criado_em'] ?? 'now')); ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Dados do Veículo</div>
                <div class="card-body">
                    <table class="table table-sm mb-0" style="font-size:.88rem">
                        <tr><td style="color:var(--theme-muted);width:40%">Placa</td><td><?php echo htmlspecialchars($guincho['placa_guincho'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">CNH Nº</td><td><?php echo htmlspecialchars($guincho['cnh_numero'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Validade CNH</td><td><?php echo isset($guincho['cnh_validade']) ? date('d/m/Y', strtotime($guincho['cnh_validade'])) : '—'; ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Capacidade</td><td><?php echo htmlspecialchars($guincho['capacidade_ton'] ?? '—'); ?> ton</td></tr>
                        <tr><td style="color:var(--theme-muted)">Chave Pix</td><td><?php echo htmlspecialchars($guincho['chave_pix'] ?? '—'); ?></td></tr>
                        <tr><td style="color:var(--theme-muted)">Reputação</td><td><?php echo number_format($guincho['reputacao'] ?? 0, 1); ?>/5 (<?php echo (int)($guincho['total_avaliacoes'] ?? 0); ?> avals)</td></tr>
                    </table>
                </div>
            </div>

            <?php if (!$guincho['aprovado']): ?>
            <div class="card">
                <div class="card-header" style="background:#d97706"><i class="fas fa-triangle-exclamation me-2"></i>Aprovação Pendente</div>
                <div class="card-body">
                    <p style="color:var(--theme-muted);font-size:.88rem">Revise os documentos antes de aprovar ou rejeitar este guincho.</p>
                    <div class="d-flex gap-2">
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/aprovar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $guincho['id']; ?>">
                            <button class="btn btn-primary w-100">
                                <i class="fas fa-check me-1"></i>Aprovar
                            </button>
                        </form>
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/rejeitar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo $guincho['id']; ?>">
                            <button class="btn btn-danger w-100" onclick="return confirm('Rejeitar e suspender este guincho?')">
                                <i class="fas fa-times me-1"></i>Rejeitar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documentos -->
            <?php if (!empty($guincho['doc_cnh_frente']) || !empty($guincho['foto_veiculo'])): ?>
            <div class="card mt-4">
                <div class="card-header"><i class="fas fa-file-image me-2"></i>Documentos Enviados</div>
                <div class="card-body d-flex flex-column gap-2">
                    <?php if (!empty($guincho['doc_cnh_frente'])): ?>
                    <a href="/uploads/<?php echo htmlspecialchars($guincho['doc_cnh_frente']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-id-card me-1"></i>CNH Frente
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($guincho['doc_cnh_verso'])): ?>
                    <a href="/uploads/<?php echo htmlspecialchars($guincho['doc_cnh_verso']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-id-card me-1"></i>CNH Verso
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($guincho['foto_veiculo'])): ?>
                    <a href="/uploads/<?php echo htmlspecialchars($guincho['foto_veiculo']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-truck me-1"></i>Foto do Veículo
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pedidos do guincho -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-list-check me-2"></i>Atendimentos (últimos 15)</div>
                <div class="card-body p-0">
                    <?php if (empty($pedidos)): ?>
                    <div class="p-4 text-center" style="color:var(--theme-muted)">
                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                        Nenhum atendimento encontrado.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>#</th><th>Cliente</th><th>Problema</th><th>Status</th><th>Valor</th><th>Data</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($pedidos as $p): ?>
                                <tr>
                                    <td><?php echo $p['id']; ?></td>
                                    <td><?php echo htmlspecialchars($p['cliente_nome'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($p['tipo_problema'] ?? '—')); ?></td>
                                    <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo str_replace('_',' ',ucfirst($p['status'])); ?></span></td>
                                    <td>R$ <?php echo number_format($p['custo_estimado'] ?? 0, 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($p['criado_em'])); ?></td>
                                    <td><a href="<?php echo $bp; ?>/admin/pedido/<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
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
