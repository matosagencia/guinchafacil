<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-clock me-2" style="color:#f59e0b"></i>Guinchos Pendentes</div>
            <div class="page-subtitle">
                <?php echo count($pendentes ?? []); ?> operador(es) aguardando aprovação
            </div>
        </div>
        <a href="<?php echo $bp; ?>/admin/guinchos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Ver Aprovados
        </a>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
    <?php $msgMap = ['aprovado'=>['success','check-circle','Guincheiro aprovado! Ele já pode receber pedidos.'],
                     'rejeitado'=>['warning','times-circle','Cadastro rejeitado.']];
          $m = $msgMap[$_GET['msg']] ?? ['info','info-circle',htmlspecialchars($_GET['msg'])]; ?>
    <div class="alert alert-<?php echo $m[0]; ?> mb-3">
        <i class="fas fa-<?php echo $m[1]; ?> me-2"></i><?php echo $m[2]; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($pendentes)): ?>
    <div class="row g-4">
        <?php foreach ($pendentes as $g): ?>
        <div class="col-lg-6">
            <div class="card h-100" style="border-left:4px solid #f59e0b">
                <div class="card-header" style="background:rgba(245,158,11,.12)">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon" style="margin:0;width:44px;height:44px;background:rgba(245,158,11,.2)">
                            <i class="fas fa-truck" style="color:#f59e0b"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:var(--theme-text)">
                                <?php echo htmlspecialchars($g['nome_operador'] ?? '—'); ?>
                            </div>
                            <div style="font-size:.78rem;color:var(--theme-muted)">
                                <?php echo htmlspecialchars($g['email'] ?? ''); ?>
                                <?php if (!empty($g['telefone'])): ?>
                                &nbsp;·&nbsp;<?php echo htmlspecialchars($g['telefone']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span style="margin-left:auto;font-size:.72rem;color:#f59e0b;font-weight:700">
                            <?php echo isset($g['criado_em']) ? date('d/m/Y', strtotime($g['criado_em'])) : '—'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div style="font-size:.72rem;color:var(--theme-muted);text-transform:uppercase;letter-spacing:.05em">Placa</div>
                            <span class="badge badge-aguardando_pagamento" style="font-size:.8rem">
                                <?php echo htmlspecialchars($g['placa_guincho'] ?? '—'); ?>
                            </span>
                        </div>
                        <div class="col-6">
                            <div style="font-size:.72rem;color:var(--theme-muted);text-transform:uppercase;letter-spacing:.05em">CNH</div>
                            <div style="font-size:.85rem"><?php echo htmlspecialchars($g['cnh_numero'] ?? '—'); ?></div>
                        </div>
                        <div class="col-6">
                            <div style="font-size:.72rem;color:var(--theme-muted);text-transform:uppercase;letter-spacing:.05em">Capacidade</div>
                            <div style="font-size:.85rem"><?php echo number_format($g['capacidade_ton'] ?? 0, 1); ?> ton</div>
                        </div>
                        <div class="col-6">
                            <div style="font-size:.72rem;color:var(--theme-muted);text-transform:uppercase;letter-spacing:.05em">Raio</div>
                            <div style="font-size:.85rem"><?php echo (int)($g['raio_cobertura_km'] ?? 20); ?> km</div>
                        </div>
                        <?php if (!empty($g['cpf'])): ?>
                        <div class="col-12">
                            <div style="font-size:.72rem;color:var(--theme-muted);text-transform:uppercase;letter-spacing:.05em">CPF</div>
                            <div style="font-size:.85rem"><?php echo htmlspecialchars($g['cpf']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Documentos -->
                    <?php if (!empty($g['doc_cnh_frente']) || !empty($g['foto_veiculo'])): ?>
                    <div class="mb-3">
                        <div style="font-size:.72rem;color:var(--theme-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Documentos</div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (!empty($g['doc_cnh_frente'])): ?>
                            <a href="/uploads/<?php echo htmlspecialchars($g['doc_cnh_frente']); ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-id-card me-1"></i>CNH Frente
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($g['doc_cnh_verso'])): ?>
                            <a href="/uploads/<?php echo htmlspecialchars($g['doc_cnh_verso']); ?>"
                               target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-id-card me-1"></i>CNH Verso
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($g['foto_veiculo'])): ?>
                            <a href="/uploads/<?php echo htmlspecialchars($g['foto_veiculo']); ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-truck me-1"></i>Foto Veículo
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mb-3 p-2 rounded" style="background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.2);font-size:.8rem;color:#f87171">
                        <i class="fas fa-exclamation-triangle me-1"></i>Nenhum documento enviado
                    </div>
                    <?php endif; ?>

                    <!-- Ver perfil + ações -->
                    <div class="d-flex gap-2">
                        <a href="<?php echo $bp; ?>/admin/guincho/<?php echo (int)$g['id']; ?>"
                           class="btn btn-sm btn-outline-primary" title="Ver perfil completo">
                            <i class="fas fa-eye me-1"></i>Ver Perfil
                        </a>
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/aprovar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                            <button class="btn btn-success btn-sm w-100">
                                <i class="fas fa-check me-1"></i>Aprovar
                            </button>
                        </form>
                        <form method="POST" action="<?php echo $bp; ?>/admin/guincho/rejeitar" class="flex-fill">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                            <button class="btn btn-danger btn-sm w-100"
                                    onclick="return confirm('Rejeitar cadastro de <?php echo addslashes(htmlspecialchars($g['nome_operador'] ?? '')); ?>?')">
                                <i class="fas fa-times me-1"></i>Rejeitar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-3x d-block mb-3" style="color:var(--primary);opacity:.5"></i>
            <h5 style="color:var(--theme-text)">Nenhum cadastro pendente</h5>
            <p style="color:var(--theme-muted)">Todos os guincheiros foram revisados.</p>
            <a href="<?php echo $bp; ?>/admin/guincho/novo" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Cadastrar Guincheiro
            </a>
        </div>
    </div>
    <?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
