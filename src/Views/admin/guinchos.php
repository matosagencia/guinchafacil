<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

// Status do guincho calculado dinamicamente
function guinchoStatusBadge(array $g): string {
    if (!(int)($g['ativo'] ?? 1)) return '<span class="guincho-status-offline">Suspenso</span>';
    if ((int)($g['disponivel'] ?? 0)) return '<span class="guincho-status-online">Online</span>';
    return '<span class="guincho-status-offline">Offline</span>';
}
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title">
                <i class="fas fa-truck me-2" style="color:var(--primary)"></i>Gerenciar Guinchos
            </div>
            <div class="page-subtitle">
                <?php echo count($pendentes ?? []) + count($aprovados ?? []); ?> guincheiros cadastrados
                &bull; <?php echo count($pendentes ?? []); ?> aguardando aprovação
            </div>
        </div>
        <a href="<?php echo $bp; ?>/admin/guincho/novo" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Cadastrar Guincheiro
        </a>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
    <?php $msgMap = ['aprovado'=>['success','check-circle','Guincho aprovado com sucesso!'],
                     'rejeitado'=>['warning','times-circle','Guincho rejeitado.'],
                     'criado'=>['success','check-circle','Guincheiro cadastrado com sucesso!']];
          $m = $msgMap[$_GET['msg']] ?? ['info','info-circle',htmlspecialchars($_GET['msg'])]; ?>
    <div class="alert alert-<?php echo $m[0]; ?> mb-3">
        <i class="fas fa-<?php echo $m[1]; ?> me-2"></i><?php echo $m[2]; ?>
    </div>
    <?php endif; ?>

    <!-- SEÇÃO: Pendentes de Aprovação -->
    <?php if (!empty($pendentes)): ?>
    <div class="d-flex align-items-center gap-2 mb-3">
        <span style="width:10px;height:10px;border-radius:50%;background:#f59e0b;display:inline-block"></span>
        <h6 class="mb-0" style="color:var(--theme-muted);font-size:.82rem;text-transform:uppercase;letter-spacing:.08em;font-weight:700">
            Aguardando Aprovação — <?php echo count($pendentes); ?>
        </h6>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ($pendentes as $g): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100" style="border-left:4px solid #f59e0b">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon" style="margin:0;width:44px;height:44px;font-size:1rem;background:rgba(245,158,11,.15)">
                            <i class="fas fa-clock" style="color:#f59e0b"></i>
                        </div>
                        <div class="flex-fill min-w-0">
                            <div style="font-weight:700;color:var(--theme-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?php echo htmlspecialchars($g['nome_operador'] ?? '—'); ?>
                            </div>
                            <div style="font-size:.77rem;color:var(--theme-muted)">
                                <?php echo htmlspecialchars($g['email'] ?? ''); ?>
                            </div>
                        </div>
                        <span style="font-size:.7rem;color:#f59e0b;font-weight:700;white-space:nowrap">
                            <?php echo isset($g['criado_em']) ? date('d/m/Y', strtotime($g['criado_em'])) : '—'; ?>
                        </span>
                    </div>

                    <table class="table table-sm mb-3" style="font-size:.81rem">
                        <tr>
                            <td style="color:var(--theme-muted);width:38%">Placa</td>
                            <td><span class="badge badge-aguardando_pagamento"><?php echo htmlspecialchars($g['placa_guincho'] ?? '—'); ?></span></td>
                        </tr>
                        <tr>
                            <td style="color:var(--theme-muted)">CNH</td>
                            <td><?php echo htmlspecialchars($g['cnh_numero'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--theme-muted)">Telefone</td>
                            <td><?php echo htmlspecialchars($g['telefone'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <td style="color:var(--theme-muted)">Capacidade</td>
                            <td><?php echo number_format($g['capacidade_ton'] ?? 0, 1); ?> ton</td>
                        </tr>
                    </table>

                    <?php if (!empty($g['doc_cnh_frente'])): ?>
                    <div class="d-flex gap-2 mb-3">
                        <a href="/uploads/<?php echo htmlspecialchars($g['doc_cnh_frente']); ?>" target="_blank"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fas fa-file me-1"></i>CNH Frente
                        </a>
                        <?php if (!empty($g['doc_cnh_verso'])): ?>
                        <a href="/uploads/<?php echo htmlspecialchars($g['doc_cnh_verso']); ?>" target="_blank"
                           class="btn btn-sm btn-outline-primary flex-fill">
                            <i class="fas fa-file me-1"></i>CNH Verso
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/aprovar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                            <button class="btn btn-success btn-sm w-100">
                                <i class="fas fa-check me-1"></i>Aprovar
                            </button>
                        </form>
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/rejeitar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                            <button class="btn btn-danger btn-sm w-100"
                                    onclick="return confirm('Rejeitar este guincheiro?')">
                                <i class="fas fa-times me-1"></i>Rejeitar
                            </button>
                        </form>
                        <a href="<?php echo $bp; ?>/admin/guincho/<?php echo (int)$g['id']; ?>"
                           class="btn btn-outline-primary btn-sm" title="Ver detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card mb-4">
        <div class="card-body text-center py-3" style="color:var(--theme-muted)">
            <i class="fas fa-check-circle me-2 text-success"></i>Nenhum guincheiro pendente de aprovação.
        </div>
    </div>
    <?php endif; ?>

    <!-- SEÇÃO: Todos os Guinchos (pendentes + aprovados) -->
    <div class="d-flex align-items-center gap-2 mb-3">
        <span style="width:10px;height:10px;border-radius:50%;background:var(--primary);display:inline-block"></span>
        <h6 class="mb-0" style="color:var(--theme-muted);font-size:.82rem;text-transform:uppercase;letter-spacing:.08em;font-weight:700">
            Todos os Guinchos — <?php echo count($aprovados ?? []); ?> aprovados
        </h6>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Guincheiro</th>
                            <th>Veículo</th>
                            <th>Contato</th>
                            <th>Status</th>
                            <th>Reputação</th>
                            <th>Ativo</th>
                            <th>Cadastro</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($aprovados)): ?>
                        <?php foreach ($aprovados as $g): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--theme-muted)">#<?php echo (int)$g['id']; ?></td>
                            <td>
                                <div style="font-weight:600;color:var(--theme-text)">
                                    <?php echo htmlspecialchars($g['nome_operador'] ?? '—'); ?>
                                </div>
                                <small style="color:var(--theme-muted)"><?php echo htmlspecialchars($g['email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <span class="badge badge-aguardando_pagamento">
                                    <?php echo htmlspecialchars($g['placa_guincho'] ?? '—'); ?>
                                </span>
                                <?php if (!empty($g['capacidade_ton'])): ?>
                                <div style="font-size:.73rem;color:var(--theme-muted);margin-top:2px">
                                    <?php echo number_format($g['capacidade_ton'], 1); ?> ton
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.84rem"><?php echo htmlspecialchars($g['telefone'] ?? '—'); ?></td>
                            <td><?php echo guinchoStatusBadge($g); ?></td>
                            <td>
                                <?php $rep = (float)($g['reputacao'] ?? 0); ?>
                                <span style="color:<?php echo $rep >= 4 ? '#22c55e' : ($rep >= 3 ? '#f59e0b' : ($rep > 0 ? '#ef4444' : 'var(--theme-muted)')); ?>;font-weight:700">
                                    <?php echo $rep > 0 ? '★ '.number_format($rep, 1) : '—'; ?>
                                </span>
                                <?php if (!empty($g['total_avaliacoes'])): ?>
                                <small style="color:var(--theme-muted)">(<?php echo (int)$g['total_avaliacoes']; ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($g['ativo'] ?? 1): ?>
                                <span style="color:#22c55e;font-size:.8rem"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Ativo</span>
                                <?php else: ?>
                                <span style="color:#f87171;font-size:.8rem"><i class="fas fa-circle me-1" style="font-size:.5rem"></i>Suspenso</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.8rem;color:var(--theme-muted)">
                                <?php echo !empty($g['criado_em']) ? date('d/m/Y', strtotime($g['criado_em'])) : '—'; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?php echo $bp; ?>/admin/guincho/<?php echo (int)$g['id']; ?>"
                                       class="btn btn-sm btn-outline-primary" title="Ver detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $bp; ?>/admin/usuario/editar/<?php echo (int)$g['usuario_id']; ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-5" style="color:var(--theme-muted)">
                                <i class="fas fa-truck fa-2x d-block mb-2" style="opacity:.3"></i>
                                Nenhum guincheiro aprovado ainda.
                                <br>
                                <a href="<?php echo $bp; ?>/admin/guincho/novo" class="btn btn-primary btn-sm mt-3">
                                    <i class="fas fa-plus me-1"></i>Cadastrar Primeiro Guincheiro
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
