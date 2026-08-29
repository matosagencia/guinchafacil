<?php
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();
$flashMessages = $_SESSION['_flash'] ?? [];
if (isset($flashMessages['message'])) $flashMessages = [$flashMessages];
unset($_SESSION['_flash']);
include __DIR__ . '/../layouts/header.php';
$statusLabels = [
    'aguardando_pagamento' => 'Aguardando Pagamento',
    'aguardando_guincho'   => 'Aguardando Guincho',
    'a_caminho'            => 'A Caminho',
    'no_local'             => 'No Local',
    'em_reboque'           => 'Em Reboque',
    'concluido'            => 'Concluído',
    'cancelado'            => 'Cancelado',
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-pedidodetalhe.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <?php foreach ($flashMessages as $flash): ?>
    <div class="alert alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'danger'; ?> mb-3"><i class="fas fa-<?php echo ($flash['type'] ?? '') === 'success' ? 'check-circle' : 'triangle-exclamation'; ?> me-2"></i><?php echo htmlspecialchars((string)($flash['message'] ?? '')); ?></div>
    <?php endforeach; ?>

    <?php if (!empty($_GET['criado'])): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Pedido criado com sucesso!</div>
    <?php endif; ?>

    <?php if (($_GET['msg'] ?? '') === 'pix_reprocessado' || ($_GET['msg'] ?? '') === 'payment_job_reenfileirado'): ?>
    <div class="alert alert-success mb-3"><i class="fas fa-check-circle me-2"></i>Repasse reenfileirado com sucesso!</div>
    <?php elseif (($_GET['msg'] ?? '') === 'pix_falha' || ($_GET['msg'] ?? '') === 'payment_job_retry_falha'): ?>
    <div class="alert alert-danger mb-3">
        <i class="fas fa-exclamation-triangle me-2"></i>Falha no reenfileiramento do repasse.
        <?php if (!empty($_GET['job_error'])): ?>
        <span class="d-block small mt-1"><?php echo htmlspecialchars((string)$_GET['job_error']); ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $jobReprocessavel = null;
    foreach (($paymentJobs ?? []) as $candidateJob) {
        if (($candidateJob['status'] ?? '') !== 'completed') {
            $jobReprocessavel = $candidateJob;
            break;
        }
    }
    ?>
    <?php if (!empty($pedido['concluido_manualmente'])): ?>
    <div class="alert alert-<?php echo ($pedido['revisao_manual_status'] ?? '') === 'pendente' ? 'danger' : 'secondary'; ?> mb-3">
        <i class="fas fa-user-shield me-2"></i>
        <strong>Este pedido foi concluído manualmente</strong>
        <i class="fas fa-info-circle ms-1 hint-icon" title="Conclusão manual assistida: usada quando o GPS ou o servidor falha durante o atendimento e o trajeto não pode ser validado automaticamente. O admin confirma o comprovante de coleta/entrega na mão, com justificativa obrigatória e senha — mas isso não fecha o caso sozinho: fica marcado 'Aguardando revisão de auditoria' até alguém confirmar ou rejeitar, exatamente como as demais liberações manuais financeiras do sistema."></i>
        (GPS/servidor indisponível durante o atendimento), em
        <?php echo !empty($pedido['concluido_manual_em']) ? date('d/m/Y \à\s H:i', strtotime($pedido['concluido_manual_em'])) : '—'; ?>.
        Justificativa: “<?php echo htmlspecialchars((string)($pedido['concluido_manual_justificativa'] ?? '')); ?>”.
        <?php if (($pedido['revisao_manual_status'] ?? '') === 'pendente'): ?>
        <div class="mt-2">
            <span class="badge bg-danger me-2"><i class="fas fa-hourglass-half me-1"></i>Aguardando revisão de auditoria</span>
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalRevisarManual">
                <i class="fas fa-check-double me-1"></i>Revisar comprovantes
            </button>
        </div>
        <?php else: ?>
        <span class="badge bg-<?php echo ($pedido['revisao_manual_status'] ?? '') === 'confirmada' ? 'success' : 'danger'; ?>">
            Revisão: <?php echo htmlspecialchars(ucfirst((string)($pedido['revisao_manual_status'] ?? ''))); ?>
        </span>
        <?php if (!empty($pedido['revisao_manual_nota'])): ?>
        <span class="ms-2 small text-muted">Nota: <?php echo htmlspecialchars((string)$pedido['revisao_manual_nota']); ?></span>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($demandasDoPedido)): ?>
    <div class="card mb-3">
        <div class="card-header">
            <i class="fas fa-user-shield me-2"></i>Histórico de demandas (funcionário → gerente)
            <i class="fas fa-info-circle ms-1 hint-icon" title="Toda ação sensível pedida por um funcionário (cancelamento, conclusão manual, pagamento, reembolso, alteração de dados) fica registrada aqui: quem solicitou, quem aprovou ou rejeitou, e quando. Nenhuma dessas ações é executada sem essa trilha."></i>
        </div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                <?php foreach ($demandasDoPedido as $dem):
                    $tipoLabel = ucfirst(str_replace('_', ' ', (string)$dem['tipo']));
                    $criadoEm = date('d/m/Y H:i', strtotime((string)$dem['criado_em']));
                ?>
                <li class="mb-2 pb-2 border-bottom">
                    <strong>#<?php echo (int)$dem['id']; ?> — <?php echo htmlspecialchars($tipoLabel); ?></strong>
                    solicitado por <strong><?php echo htmlspecialchars((string)$dem['solicitante_nome']); ?></strong> em <?php echo $criadoEm; ?>.
                    <?php if ($dem['status'] === 'pendente'): ?>
                        <span class="badge bg-secondary">aguardando aprovação de gerente</span>
                    <?php elseif ($dem['status'] === 'aprovada_parcial'): ?>
                        <span class="badge bg-info">1ª aprovação: <?php echo htmlspecialchars((string)$dem['gerente_nome']); ?> em <?php echo date('d/m/Y H:i', strtotime((string)$dem['decidido_em'])); ?> — aguardando 2º gerente</span>
                    <?php elseif ($dem['status'] === 'rejeitada'): ?>
                        <span class="badge bg-danger">rejeitada por <?php echo htmlspecialchars((string)$dem['gerente_nome']); ?> em <?php echo date('d/m/Y H:i', strtotime((string)$dem['decidido_em'])); ?></span>
                        <?php if (!empty($dem['nota_gerente'])): ?><div class="small text-muted">Motivo: <?php echo htmlspecialchars((string)$dem['nota_gerente']); ?></div><?php endif; ?>
                    <?php elseif (in_array($dem['status'], ['aprovada', 'executada'], true)): ?>
                        <span class="badge bg-success">
                            aprovado por <?php echo htmlspecialchars((string)$dem['gerente_nome']); ?><?php echo $dem['segundo_gerente_nome'] ? ' e ' . htmlspecialchars((string)$dem['segundo_gerente_nome']) : ''; ?>
                            em <?php echo date('d/m/Y H:i', strtotime((string)($dem['segundo_decidido_em'] ?? $dem['decidido_em']))); ?>
                            <?php echo $dem['status'] === 'executada' ? '— executado' : '— aguardando execução'; ?>
                        </span>
                    <?php elseif ($dem['status'] === 'falhou'): ?>
                        <span class="badge bg-dark">aprovada, mas falhou ao executar</span>
                        <?php if (!empty($dem['erro_execucao'])): ?><div class="small text-muted">Erro: <?php echo htmlspecialchars((string)$dem['erro_execucao']); ?></div><?php endif; ?>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($jobReprocessavel): ?>
    <div class="alert alert-warning mb-3 d-flex align-items-center justify-content-between">
        <span><i class="fas fa-exclamation-triangle me-2"></i>Existe um repasse pendente, em retry ou falho para este pedido.</span>
        <form method="POST" action="<?php echo htmlspecialchars($bp . '/admin/payment-job/retry/' . (int)$jobReprocessavel['id']); ?>" class="ms-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars('/admin/pedido/' . (int)$pedido['id']); ?>">
            <button type="submit" class="btn btn-warning btn-sm">
                <i class="fas fa-redo me-1"></i>Reenfileirar Repasse
            </button>
        </form>
    </div>
    <?php endif; ?>

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>
                <i class="fas fa-file-alt me-2 text-primary-custom"></i>
                Pedido #<?php echo (int)($pedido['id'] ?? 0); ?>
                <span id="pedidoStatusBadge" class="badge badge-<?php echo htmlspecialchars($pedido['status'] ?? ''); ?> ms-2" data-status="<?php echo htmlspecialchars((string)($pedido['status'] ?? '')); ?>">
                    <?php echo htmlspecialchars($statusLabels[$pedido['status'] ?? ''] ?? ucfirst($pedido['status'] ?? '')); ?>
                </span>
            </h1>
            <p>
                Criado em <?php echo isset($pedido['criado_em']) ? date('d/m/Y \à\s H:i', strtotime($pedido['criado_em'])) : '—'; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $bp; ?>/admin/pedidos" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
            <a href="<?php echo $bp; ?>/admin/pedido/trilha/<?php echo (int)($pedido['id'] ?? 0); ?>" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-route me-1"></i>Trilha POR
            </a>
            <?php if (!in_array($pedido['status'] ?? '', ['concluido','cancelado'])): ?>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalStatus">
                <i class="fas fa-edit me-1"></i>Alterar Status
            </button>
            <?php if (empty($pedido['guincho_id'])): ?>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalGuincho">
                <i class="fas fa-truck me-1"></i>Atribuir Guincho
            </button>
            <?php endif; ?>
            <?php if (in_array($pedido['status'] ?? '', ['a_caminho', 'no_local', 'em_reboque'], true)): ?>
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalConcluirManual" title="Use apenas quando o GPS do cliente/guincho ou o servidor falhar e o atendimento não conseguir evoluir normalmente.">
                <i class="fas fa-user-shield me-1"></i>Conclusão manual assistida
            </button>
            <?php endif; ?>
            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                <i class="fas fa-ban me-1"></i>Cancelar
            </button>
            <?php endif; ?>
            <a href="<?php echo $bp; ?>/admin/pedido/novo" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>Novo Pedido
            </a>
        </div>
    </header>

    <!-- Prova de Serviço: Fotos -->
    <?php
    $evidenciaColeta = $evidenciaColeta ?? null;
    $evidenciaEntrega = $evidenciaEntrega ?? null;
    ?>
    <?php if (!empty($evidenciaColeta) || !empty($evidenciaEntrega)): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-camera me-2"></i>Prova de Serviço</div>
        <div class="card-body">
            <div class="row">
                <?php if (!empty($evidenciaColeta)): ?>
                <div class="col-md-6">
                    <p class="small text-muted">Foto na Plataforma (coleta)</p>
                    <a href="<?php echo $bp; ?>/evidencia/<?php echo (int)$evidenciaColeta['id']; ?>" target="_blank">
                        <img src="<?php echo $bp; ?>/evidencia/<?php echo (int)$evidenciaColeta['id']; ?>" class="img-thumbnail pd-thumb">
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($evidenciaEntrega)): ?>
                <div class="col-md-6">
                    <p class="small text-muted">Foto no Destino (entrega)</p>
                    <a href="<?php echo $bp; ?>/evidencia/<?php echo (int)$evidenciaEntrega['id']; ?>" target="_blank">
                        <img src="<?php echo $bp; ?>/evidencia/<?php echo (int)$evidenciaEntrega['id']; ?>" class="img-thumbnail pd-thumb">
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Alterar Status -->

    <div class="modal fade" id="modalStatus" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content pd-modal-content">
                <div class="modal-header pd-modal-border">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Alterar Status do Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/status">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <label class="form-label">Novo Status</label>
                        <select class="form-select" name="status" required>
                            <?php
                            $todosStatus = [
                                'aguardando_pagamento' => 'Aguardando Pagamento',
                                'aguardando_guincho'   => 'Aguardando Guincho',
                                'a_caminho'            => 'A Caminho',
                                'no_local'             => 'No Local',
                                'em_reboque'           => 'Em Reboque',
                                'concluido'            => 'Concluído',
                            ];
                            foreach ($todosStatus as $val => $lbl):
                            ?>
                            <option value="<?php echo $val; ?>" <?php echo ($pedido['status'] ?? '') === $val ? 'selected' : ''; ?>>
                                <?php echo $lbl; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer pd-modal-border">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-check me-1"></i>Confirmar Alteração
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Atribuir Guincho -->
    <?php if (empty($pedido['guincho_id'])): ?>
    <div class="modal fade" id="modalGuincho" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content pd-modal-content">
                <div class="modal-header pd-modal-border">
                    <h5 class="modal-title"><i class="fas fa-truck me-2"></i>Atribuir Guincho ao Pedido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/atribuir">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <label class="form-label">Selecionar Guincho Disponível</label>
                        <select class="form-select" name="guincho_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($guinchoDisponiveis ?? [] as $g): ?>
                            <option value="<?php echo (int)$g['id']; ?>">
                                <?php echo htmlspecialchars($g['nome_operador'] . ' — Placa: ' . $g['placa_guincho']); ?>
                                (Rep: <?php echo number_format($g['reputacao'] ?? 0, 1); ?>⭐)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($guinchoDisponiveis)): ?>
                        <div class="alert alert-warning mt-2 mb-0 py-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Nenhum guincho disponível no momento.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer pd-modal-border">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success btn-sm" <?php echo empty($guinchoDisponiveis) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check me-1"></i>Atribuir Guincho
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Conclusão manual assistida (GPS/servidor indisponível) -->
    <div class="modal fade" id="modalConcluirManual" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content pd-modal-content">
                <div class="modal-header pd-modal-border">
                    <h5 class="modal-title"><i class="fas fa-user-shield me-2"></i>Conclusão manual assistida — Pedido #<?php echo (int)$pedido['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/concluir-manual" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">

                        <div class="alert alert-warning small mb-3">
                            <i class="fas fa-triangle-exclamation me-1"></i>
                            Use apenas quando o GPS do cliente/guincho ou o servidor tiver falhado e o atendimento não
                            evoluir pelo fluxo normal. O pedido será concluído sem confirmação de geofence e ficará
                            <strong>pendente de revisão de auditoria</strong> — anexe os comprovantes que conseguiu
                            obter (foto enviada por WhatsApp/telefone, por exemplo).
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Justificativa (obrigatório, mín. 20 caracteres)</label>
                            <textarea class="form-control" name="justificativa" minlength="20" required
                                placeholder="Ex.: guincho relatou por telefone que o app travou após a coleta; app não enviava GPS há 25 min; comprovante recebido via WhatsApp às 14h32."></textarea>
                        </div>

                        <div class="row g-3 mb-2">
                            <?php if (empty($jaTemEvidencia['coleta'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Comprovante de coleta (obrigatório)</label>
                                <input type="file" class="form-control" name="comprovante_coleta" accept="image/jpeg,image/png" required>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Comprovante de coleta</label>
                                <div class="small text-success"><i class="fas fa-check-circle me-1"></i>Já existe evidência de coleta aceita via GPS.</div>
                            </div>
                            <?php endif; ?>
                            <?php if (empty($jaTemEvidencia['entrega'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Comprovante de entrega (obrigatório)</label>
                                <input type="file" class="form-control" name="comprovante_entrega" accept="image/jpeg,image/png" required>
                            </div>
                            <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label text-muted">Comprovante de entrega</label>
                                <div class="small text-success"><i class="fas fa-check-circle me-1"></i>Já existe evidência de entrega aceita via GPS.</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Sua senha (confirmação)</label>
                            <input type="password" class="form-control" name="senha" required>
                            <small class="text-muted">Necessário para auditoria de segurança — ação sensível e sujeita a revisão.</small>
                        </div>
                    </div>
                    <div class="modal-footer pd-modal-border">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark btn-sm">
                            <i class="fas fa-user-shield me-1"></i>Concluir manualmente
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Revisar conclusão manual -->
    <?php if (!empty($pedido['concluido_manualmente']) && ($pedido['revisao_manual_status'] ?? '') === 'pendente'): ?>
    <div class="modal fade" id="modalRevisarManual" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content pd-modal-content">
                <div class="modal-header pd-modal-border">
                    <h5 class="modal-title"><i class="fas fa-check-double me-2"></i>Revisar conclusão manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/revisar-manual">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nota da revisão (opcional)</label>
                            <textarea class="form-control" name="nota" placeholder="O que foi verificado nos comprovantes?"></textarea>
                        </div>
                        <label class="form-label d-block">Veredito</label>
                        <div class="btn-group w-100" role="group">
                            <button type="submit" name="veredito" value="confirmada" class="btn btn-outline-success">
                                <i class="fas fa-check me-1"></i>Confirmar conclusão
                            </button>
                            <button type="submit" name="veredito" value="rejeitada" class="btn btn-outline-danger">
                                <i class="fas fa-xmark me-1"></i>Rejeitar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal: Cancelar Pedido -->
    <div class="modal fade" id="modalCancelar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content pd-modal-content">
                <div class="modal-header pd-modal-border">
                    <h5 class="modal-title text-danger"><i class="fas fa-ban me-2"></i>Cancelar Pedido #<?php echo (int)$pedido['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="<?php echo $bp; ?>/admin/pedido/cancelar">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$pedido['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Justificativa (Obrigatório)</label>
                            <textarea class="form-control" name="justificativa" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Sua Senha (Para confirmação)</label>
                            <input type="password" class="form-control" name="senha" required>
                            <small class="text-muted">Necessário para auditoria de segurança.</small>
                        </div>
                    </div>
                    <div class="modal-footer pd-modal-border">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-ban me-1"></i>Confirmar Cancelamento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Coluna esquerda: info do pedido -->
        <div class="col-lg-4">

            <!-- Cliente e Veículo -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-user me-2"></i>Cliente & Veículo</div>
                <div class="card-body">
                    <table class="table table-sm mb-0 pd-table">
                        <tr><td class="pd-label pd-label--35">Cliente</td>
                            <td><?php echo htmlspecialchars($pedido['cliente_nome'] ?? '—'); ?></td></tr>
                        <tr><td class="pd-label">Telefone</td>
                            <td><?php echo htmlspecialchars($pedido['cliente_telefone'] ?? '—'); ?></td></tr>
                        <tr><td class="pd-label">Veículo</td>
                            <td><?php echo htmlspecialchars(($pedido['marca'] ?? '') . ' ' . ($pedido['modelo'] ?? '') . ' - ' . ($pedido['placa'] ?? '')); ?></td></tr>
                        <tr><td class="pd-label">Cor</td>
                            <td><?php echo htmlspecialchars($pedido['cor'] ?? '—'); ?></td></tr>
                    </table>
                    <?php if (!empty($pedido['cliente_id'])): ?>
                    <div class="mt-2">
                        <a href="<?php echo $bp; ?>/admin/usuario/<?php echo (int)$pedido['cliente_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-user me-1"></i>Ver Perfil do Cliente
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Guincho -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-truck me-2"></i>Guincheiro</div>
                <div class="card-body">
                    <?php if (!empty($pedido['guincho_operador'])): ?>
                    <table class="table table-sm mb-0 pd-table">
                        <tr><td class="pd-label pd-label--35">Nome</td>
                            <td id="guinchoNomeValue"><?php echo htmlspecialchars($pedido['guincho_operador']); ?></td></tr>
                        <tr><td class="pd-label">Telefone</td>
                            <td id="guinchoTelefoneValue"><?php echo htmlspecialchars($pedido['guincho_telefone'] ?? '—'); ?></td></tr>
                        <tr><td class="pd-label">Placa</td>
                            <td id="guinchoPlacaValue"><?php echo htmlspecialchars($pedido['guincho_placa'] ?? '—'); ?></td></tr>
                    </table>
                    <?php if (!empty($pedido['guincho_id'])): ?>
                    <div class="mt-2">
                        <a href="<?php echo $bp; ?>/admin/guincho/<?php echo (int)$pedido['guincho_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="fas fa-truck me-1"></i>Ver Perfil do Guincho
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div id="guinchoEmptyState" class="pd-guincho-empty">
                        <i class="fas fa-clock me-1"></i>Aguardando atribuição de guincho
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Financeiro -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-coins me-2"></i>Financeiro</div>
                <div class="card-body">
                    <table class="table table-sm mb-0 pd-table">
                        <tr><td class="pd-label pd-label--50">Distância</td>
                            <td><?php echo number_format($pedido['distancia_km'] ?? 0, 1); ?> km</td></tr>
                        <tr><td class="pd-label">Custo Estimado</td>
                            <td>R$ <?php echo number_format($pedido['custo_estimado'] ?? 0, 2, ',', '.'); ?></td></tr>
                        <?php if (!empty($pedido['custo_final'])): ?>
                        <tr><td class="pd-label">Custo Final</td>
                            <td><strong>R$ <?php echo number_format($pedido['custo_final'], 2, ',', '.'); ?></strong></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <?php if (!empty($paymentJobs)): ?>
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-list-check me-2"></i>Fila de Repasse</div>
                <div class="card-body">
                    <?php foreach ($paymentJobs as $job): ?>
                    <div class="border rounded p-2 mb-3 pd-job-box">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Job #<?php echo (int)$job['id']; ?></strong>
                            <span class="badge bg-<?php echo ($job['status'] ?? '') === 'completed' ? 'success' : ((in_array(($job['status'] ?? ''), ['failed'], true)) ? 'danger' : ((($job['status'] ?? '') === 'running') ? 'primary' : 'warning')); ?>">
                                <?php echo htmlspecialchars((string)($job['status'] ?? 'queued')); ?>
                            </span>
                        </div>
                        <div class="small text-muted">Tipo: <?php echo htmlspecialchars((string)($job['job_type'] ?? '')); ?></div>
                        <div class="small text-muted">Tentativas: <?php echo (int)($job['attempt_count'] ?? 0); ?> / <?php echo (int)($job['max_attempts'] ?? 0); ?></div>
                        <div class="small text-muted">Worker: <?php echo htmlspecialchars((string)($job['worker_id'] ?? '—')); ?></div>
                        <div class="small text-muted">Próxima tentativa: <?php echo htmlspecialchars((string)($job['available_at'] ?? '—')); ?></div>
                        <?php if (!empty($job['last_error'])): ?>
                        <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">
                            <?php echo htmlspecialchars((string)$job['last_error']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (($job['status'] ?? '') !== 'completed'): ?>
                        <form method="POST" action="<?php echo htmlspecialchars($bp . '/admin/payment-job/retry/' . (int)$job['id']); ?>" class="mt-2">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars('/admin/pedido/' . (int)$pedido['id']); ?>">
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="fas fa-rotate-right me-1"></i>Reenfileirar job
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php $attempts = $paymentAttemptsByJob[(int)$job['id']] ?? []; ?>
                        <?php if (!empty($attempts)): ?>
                        <details class="mt-2">
                            <summary class="small">Tentativas</summary>
                            <div class="mt-2">
                                <?php foreach ($attempts as $attempt): ?>
                                <div class="border-top pt-2 mt-2 small">
                                    <div><strong>#<?php echo (int)$attempt['attempt_number']; ?></strong> • <?php echo htmlspecialchars((string)$attempt['created_at']); ?> • <?php echo !empty($attempt['success']) ? 'OK' : 'Falha'; ?></div>
                                    <?php if (!empty($attempt['error_message'])): ?>
                                    <div class="text-danger"><?php echo htmlspecialchars((string)$attempt['error_message']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($attempt['response_json'])): ?>
                                    <pre class="small mb-0 mt-1 pd-job-response"><?php echo htmlspecialchars((string)$attempt['response_json']); ?></pre>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Detalhes do problema -->
            <div class="card">
                <div class="card-header"><i class="fas fa-triangle-exclamation me-2"></i>Problema Reportado</div>
                <div class="card-body">
                    <table class="table table-sm mb-0 pd-table">
                        <tr><td class="pd-label pd-label--35">Tipo</td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedido['tipo_problema'] ?? '—'))); ?></td></tr>
                        <?php if (!empty($pedido['descricao_problema'])): ?>
                        <tr><td class="pd-label">Descrição</td>
                            <td><?php echo nl2br(htmlspecialchars($pedido['descricao_problema'])); ?></td></tr>
                        <?php endif; ?>
                    </table>
                    <div class="mt-3">
                        <div class="pd-field-label"><i class="fas fa-map-marker-alt me-1"></i>Origem</div>
                        <div class="pd-field-value"><?php echo htmlspecialchars($pedido['endereco_origem'] ?? '—'); ?></div>
                    </div>
                    <div class="mt-2">
                        <div class="pd-field-label"><i class="fas fa-flag me-1"></i>Destino</div>
                        <div class="pd-field-value"><?php echo htmlspecialchars($pedido['endereco_destino'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna direita: mapa + chat -->
        <div class="col-lg-8">

            <!-- Mapa da rota -->
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-map me-2"></i>Rota do Atendimento</div>
                <div class="card-body p-0">
                    <div id="map" class="pd-map"></div>
                </div>
                <div class="card-footer small text-muted d-flex flex-wrap gap-3">
                    <span><i class="fas fa-minus pd-legenda-validada"></i> Trilha validada</span>
                    <span><i class="fas fa-circle pd-legenda-rejeitado"></i> Ponto rejeitado</span>
                    <span><i class="fas fa-location-dot pd-legenda-origem-destino"></i> Origem / destino</span>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-road me-2"></i>POR, ruas e ETA</div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="small text-muted">Modo da rota</div>
                            <div class="fw-semibold" id="routingModeLabel"><?php echo htmlspecialchars((string)($routingSnapshot['mode_label'] ?? 'Visão geral')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">Rua atual</div>
                            <div class="fw-semibold" id="currentStreetValue"><?php echo htmlspecialchars((string)($routingSnapshot['current_street'] ?? 'Sem rua confirmada')); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted">ETA</div>
                            <div class="fw-semibold" id="etaValue"><?php echo htmlspecialchars((string)($routingSnapshot['eta_label'] ?? 'Sem ETA')); ?></div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="small text-muted">Distância restante</div>
                            <div class="fw-semibold" id="remainingDistanceValue"><?php echo htmlspecialchars((string)($routingSnapshot['remaining_distance_label'] ?? 'Sem distância')); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Distância validada</div>
                            <div class="fw-semibold" id="distanceValidatedValue"><?php echo number_format((float)($routingSnapshot['distance_validated_m'] ?? 0), 0, ',', '.'); ?> m</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Pontos válidos</div>
                            <div class="fw-semibold" id="validPointsValue"><?php echo (int)($routingSnapshot['valid_points'] ?? 0); ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-muted">Pontos rejeitados</div>
                            <div class="fw-semibold" id="rejectedPointsValue"><?php echo (int)($routingSnapshot['rejected_points'] ?? 0); ?></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Progresso operacional</span>
                            <span id="progressPercentValue"><?php echo (int)($routingSnapshot['progress_percent'] ?? 0); ?>%</span>
                        </div>
                        <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                            <div id="progressBar" class="progress-bar" style="width: <?php echo (int)($routingSnapshot['progress_percent'] ?? 0); ?>%"></div>
                        </div>
                    </div>
                    <div class="small text-muted mb-1">Ruas confirmadas</div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="recentStreetsList">
                        <?php foreach (($routingSnapshot['recent_streets'] ?? []) as $street): ?>
                        <span class="badge text-bg-light border"><?php echo htmlspecialchars((string)$street); ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($routingSnapshot['recent_streets'])): ?>
                        <span class="text-muted small">Aguardando ruas confirmadas.</span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted">
                        Qualidade do rastreamento: <span id="trackingQualityValue"><?php echo htmlspecialchars((string)($routingSnapshot['tracking_quality'] ?? 'unknown')); ?></span>
                        <?php if (!empty($routingSnapshot['last_point_at'])): ?>
                        · último ponto em <span id="lastPointAtValue"><?php echo htmlspecialchars((string)$routingSnapshot['last_point_at']); ?></span>
                        <?php else: ?>
                        · último ponto em <span id="lastPointAtValue">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-chart-area me-2"></i>Resumo POR por fase</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach (['origem' => 'Fase Origem', 'destino' => 'Fase Destino'] as $faseKey => $faseLabel): ?>
                        <?php $faseResumo = $porSummary[$faseKey] ?? null; ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 pd-fase-box">
                                <div class="fw-semibold mb-2"><?php echo htmlspecialchars($faseLabel); ?></div>
                                <?php if ($faseResumo): ?>
                                <div class="small text-muted">Qualidade: <?php echo htmlspecialchars((string)($faseResumo['tracking_quality'] ?? 'unknown')); ?></div>
                                <div class="small text-muted">Pontos: <?php echo (int)($faseResumo['valid_points'] ?? 0); ?> válidos / <?php echo (int)($faseResumo['rejected_points'] ?? 0); ?> rejeitados</div>
                                <div class="small text-muted">Distância validada: <?php echo number_format((float)($faseResumo['distance_validated_m'] ?? 0), 0, ',', '.'); ?> m</div>
                                <div class="small text-muted">Duração: <?php echo (int)($faseResumo['duration_seconds'] ?? 0); ?> s</div>
                                <div class="small text-muted">Última rua: <?php echo htmlspecialchars((string)($faseResumo['last_street'] ?? '—')); ?></div>
                                <?php else: ?>
                                <div class="text-muted small">Sem resumo disponível para esta fase.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-triangle-exclamation me-2"></i>Pontos rejeitados do POR</div>
                <div class="card-body p-0">
                    <?php if (empty($porRejected)): ?>
                    <div class="p-3 text-muted small">Nenhum ponto rejeitado registrado para este pedido.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Seq.</th>
                                    <th>Fase</th>
                                    <th>Código</th>
                                    <th>Rua</th>
                                    <th>Precisão</th>
                                    <th>Velocidade</th>
                                    <th>Recebido</th>
                                </tr>
                            </thead>
                            <tbody id="porRejectedTableBody">
                                <?php foreach ($porRejected as $rejectedPoint): ?>
                                <tr>
                                    <td><?php echo (int)($rejectedPoint['sequence_number'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars((string)($rejectedPoint['fase'] ?? '—')); ?></td>
                                    <td><code><?php echo htmlspecialchars((string)($rejectedPoint['rejection_code'] ?? '—')); ?></code></td>
                                    <td><?php echo htmlspecialchars((string)($rejectedPoint['street_name'] ?? '—')); ?></td>
                                    <td><?php echo $rejectedPoint['accuracy_m'] !== null ? number_format((float)$rejectedPoint['accuracy_m'], 1, ',', '.') . ' m' : '—'; ?></td>
                                    <td><?php echo $rejectedPoint['calculated_speed_kmh'] !== null ? number_format((float)$rejectedPoint['calculated_speed_kmh'], 1, ',', '.') . ' km/h' : '—'; ?></td>
                                    <td><?php echo htmlspecialchars((string)($rejectedPoint['server_timestamp'] ?? '—')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat -->
            <div class="card">
                <div class="card-header"><i class="fas fa-comments me-2"></i>Chat do Atendimento</div>
                <div class="card-body p-0">
                    <div class="chat-box p-3 pd-chatbox" id="chatBox">
                        <?php if (empty($mensagens)): ?>
                        <div class="pd-chat-empty">
                            <i class="fas fa-comments fa-2x mb-2 d-block"></i>
                            Nenhuma mensagem neste atendimento.
                        </div>
                        <?php else: foreach ($mensagens as $m): ?>
                        <div class="chat-msg other mb-2">
                            <div class="pd-chat-meta">
                                <?php echo htmlspecialchars($m['usuario_nome'] ?? ''); ?>
                                &bull; <?php echo date('H:i', strtotime($m['criado_em'])); ?>
                            </div>
                            <div class="chat-bubble"><?php echo nl2br(htmlspecialchars($m['mensagem'])); ?></div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet-routing-machine/leaflet-routing-machine.css">
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/leaflet-routing-machine/leaflet-routing-machine.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/js/routing/formatter-pt-br.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    const basePath = '<?php echo addslashes($bp); ?>';
    const pedidoId = <?php echo (int)($pedido['id'] ?? 0); ?>;
    const trailLimit = 120;
    const statusLabels = <?php echo json_encode($statusLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const latO = <?php echo (float)($pedido['lat_origem']  ?? -23.5505); ?>;
    const lngO = <?php echo (float)($pedido['lng_origem']  ?? -46.6333); ?>;
    const latD = <?php echo (float)($pedido['lat_destino'] ?? -23.5505); ?>;
    const lngD = <?php echo (float)($pedido['lng_destino'] ?? -46.6333); ?>;
    const initialTrail = <?php echo json_encode(array_map(static function (array $point): array {
        return [
            'latitude' => (float)($point['latitude'] ?? 0),
            'longitude' => (float)($point['longitude'] ?? 0),
            'is_valid' => !empty($point['is_valid']),
            'rejection_code' => (string)($point['rejection_code'] ?? ''),
            'street_name' => (string)($point['street_name'] ?? ''),
            'fase' => (string)($point['fase'] ?? ''),
            'sequence_number' => (int)($point['sequence_number'] ?? 0),
            'accuracy_m' => isset($point['accuracy_m']) ? (float)$point['accuracy_m'] : null,
            'calculated_speed_kmh' => isset($point['calculated_speed_kmh']) ? (float)$point['calculated_speed_kmh'] : null,
            'server_timestamp' => $point['server_timestamp'] ?? null,
        ];
    }, $porTrail ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const initialRejected = <?php echo json_encode(array_map(static function (array $point): array {
        return [
            'sequence_number' => (int)($point['sequence_number'] ?? 0),
            'fase' => (string)($point['fase'] ?? ''),
            'rejection_code' => (string)($point['rejection_code'] ?? ''),
            'street_name' => (string)($point['street_name'] ?? ''),
            'accuracy_m' => isset($point['accuracy_m']) ? (float)$point['accuracy_m'] : null,
            'calculated_speed_kmh' => isset($point['calculated_speed_kmh']) ? (float)$point['calculated_speed_kmh'] : null,
            'server_timestamp' => $point['server_timestamp'] ?? null,
        ];
    }, $porRejected ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const map  = L.map('map').setView([latO, lngO], 13);
    const pedidoStatusInicial = '<?php echo addslashes((string)($pedido['status'] ?? '')); ?>';
    L.Icon.Default.imagePath = '<?php echo $bp; ?>/public/assets/img/leaflet/';
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Pinos de origem/destino: vermelho até o guincho chegar naquele ponto,
    // cinza depois — origem "chegou" quando o status passa de a_caminho
    // (no_local/em_reboque/concluido); destino "chegou" só quando concluído.
    function statusIndicaChegouOrigem(status) {
        return ['no_local', 'em_reboque', 'concluido', 'encerrado_financeiro'].includes(String(status || ''));
    }
    function statusIndicaChegouDestino(status) {
        return ['concluido', 'encerrado_financeiro'].includes(String(status || ''));
    }
    function pinIcon(chegou, iconClass) {
        return L.divIcon({
            className: 'mapa-pin ' + (chegou ? 'mapa-pin--concluido' : 'mapa-pin--pendente'),
            html: '<i class="fas ' + iconClass + '"></i>',
            iconSize: [26, 26],
        });
    }

    const markerOrigemPD = L.marker([latO, lngO], { icon: pinIcon(statusIndicaChegouOrigem(pedidoStatusInicial), 'fa-location-dot') })
        .addTo(map).bindPopup('Origem: <?php echo addslashes(htmlspecialchars($pedido['endereco_origem'] ?? '')); ?>');
    const markerDestinoPD = L.marker([latD, lngD], { icon: pinIcon(statusIndicaChegouDestino(pedidoStatusInicial), 'fa-flag-checkered') })
        .addTo(map).bindPopup('Destino: <?php echo addslashes(htmlspecialchars($pedido['endereco_destino'] ?? '')); ?>');

    function atualizarPinsOrigemDestino(status) {
        markerOrigemPD.setIcon(pinIcon(statusIndicaChegouOrigem(status), 'fa-location-dot'));
        markerDestinoPD.setIcon(pinIcon(statusIndicaChegouDestino(status), 'fa-flag-checkered'));
    }

    let currentMarker = null;
    let validTrailLayer = null;
    let rejectedLayer = L.layerGroup().addTo(map);
    let plannedRouteLayer = null;
    let approachRouteLayer = null;
    let refreshTimer = null;

    // URL do serviço de roteamento OSRM-compatible, centralizada via config
    // 'por_road_match_base_url' (item #37 — antes hardcoded pro demo público
    // router.project-osrm.org, que tem rate-limit/ToS que proíbem produção).
    const OSRM_BASE_URL = <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>;

    function buildOsrmUrl(points) {
        const coords = points.map((point) => {
            return String(point.lng) + ',' + String(point.lat);
        }).join(';');
        return OSRM_BASE_URL + '/route/v1/driving/' + coords + '?overview=full&geometries=geojson';
    }

    function clearLayer(layerRef) {
        if (layerRef && map.hasLayer(layerRef)) {
            map.removeLayer(layerRef);
        }
        return null;
    }

    function drawFallbackLine(points, style) {
        // Achado em QA: quando o OSRM público falha/demora, esta linha reta
        // era desenhada com o MESMO estilo da rota real — o admin não tinha
        // como saber que aquilo não era o trajeto de verdade. Agora o
        // fallback sempre aparece tracejado e mais claro, para deixar visível
        // que é só uma estimativa em linha reta.
        const fallbackStyle = Object.assign({}, style, {
            dashArray: '8,6',
            opacity: Math.min(0.6, style.opacity ?? 0.6),
        });
        return L.polyline(points.map((point) => [point.lat, point.lng]), fallbackStyle).addTo(map);
    }

    function drawRouteLine(points, style, assign) {
        if (!Array.isArray(points) || points.length < 2) {
            if (assign === 'planned') {
                plannedRouteLayer = clearLayer(plannedRouteLayer);
            } else {
                approachRouteLayer = clearLayer(approachRouteLayer);
            }
            return;
        }

        function applyLayer(layer) {
            if (assign === 'planned') {
                plannedRouteLayer = clearLayer(plannedRouteLayer);
                plannedRouteLayer = layer;
            } else {
                approachRouteLayer = clearLayer(approachRouteLayer);
                approachRouteLayer = layer;
            }
        }

        // Timeout defensivo: o roteador OSRM público não tem SLA e pode
        // demorar/travar sem nunca rejeitar a promise — sem isso, uma resposta
        // lenta prendia o mapa sem rota nenhuma desenhada indefinidamente.
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 6000);

        fetch(buildOsrmUrl(points), { signal: controller.signal })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                const coordinates = payload && payload.routes && payload.routes[0] && payload.routes[0].geometry
                    ? payload.routes[0].geometry.coordinates
                    : null;
                if (!Array.isArray(coordinates)) {
                    applyLayer(drawFallbackLine(points, style));
                    return;
                }
                const latLngs = coordinates.map((coord) => [coord[1], coord[0]]);
                applyLayer(L.polyline(latLngs, style).addTo(map));
            })
            .catch(() => {
                applyLayer(drawFallbackLine(points, style));
            })
            .finally(() => clearTimeout(timeoutId));
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
        });
    }

    function formatMetric(value, suffix, decimals) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '—';
        }
        return Number(value).toLocaleString('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }) + suffix;
    }

    function drawLiveTrail(points, livePosition) {
        const validPoints = points.filter((point) => point.is_valid).map((point) => [point.latitude, point.longitude]);
        if (validTrailLayer) {
            map.removeLayer(validTrailLayer);
            validTrailLayer = null;
        }
        rejectedLayer.clearLayers();

        if (validPoints.length >= 2) {
            validTrailLayer = L.polyline(validPoints, {
                color: '#16a34a',
                weight: 5,
                opacity: 0.85
            }).addTo(map);
        }

        points.filter((point) => !point.is_valid).forEach((point) => {
            L.circleMarker([point.latitude, point.longitude], {
                radius: 5,
                color: '#dc2626',
                weight: 2,
                fillColor: '#fecaca',
                fillOpacity: 0.95
            }).bindPopup(
                'Ponto rejeitado #' + point.sequence_number
                + '<br>Código: ' + (point.rejection_code || '—')
                + '<br>Fase: ' + (point.fase || '—')
                + '<br>Rua: ' + (point.street_name || '—')
            ).addTo(rejectedLayer);
        });

        if (livePosition && Number.isFinite(livePosition.lat) && Number.isFinite(livePosition.lng)) {
            if (!currentMarker) {
                currentMarker = L.marker([livePosition.lat, livePosition.lng]).addTo(map);
            } else {
                currentMarker.setLatLng([livePosition.lat, livePosition.lng]);
            }
            currentMarker.bindPopup('Posição atual do guincho');
            drawRouteLine([
                { lat: livePosition.lat, lng: livePosition.lng },
                { lat: latO, lng: lngO }
            ], {
                color: '#f59e0b',
                weight: 4,
                opacity: 0.9,
                dashArray: '10, 8'
            }, 'approach');
        } else {
            approachRouteLayer = clearLayer(approachRouteLayer);
        }
    }

    function renderRejectedTable(rows) {
        const body = document.getElementById('porRejectedTableBody');
        if (!body) {
            return;
        }

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Nenhum ponto rejeitado registrado para este pedido.</td></tr>';
            return;
        }

        body.innerHTML = rows.map((point) => {
            return '<tr>'
                + '<td>' + escapeHtml(point.sequence_number) + '</td>'
                + '<td>' + escapeHtml(point.fase || '—') + '</td>'
                + '<td><code>' + escapeHtml(point.rejection_code || '—') + '</code></td>'
                + '<td>' + escapeHtml(point.street_name || '—') + '</td>'
                + '<td>' + escapeHtml(formatMetric(point.accuracy_m, ' m', 1)) + '</td>'
                + '<td>' + escapeHtml(formatMetric(point.calculated_speed_kmh, ' km/h', 1)) + '</td>'
                + '<td>' + escapeHtml(point.server_timestamp || '—') + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderRecentStreets(streets) {
        const target = document.getElementById('recentStreetsList');
        if (!target) {
            return;
        }

        if (!streets.length) {
            target.innerHTML = '<span class="text-muted small">Aguardando ruas confirmadas.</span>';
            return;
        }

        target.innerHTML = streets.map((street) => {
            return '<span class="badge text-bg-light border">' + escapeHtml(street) + '</span>';
        }).join('');
    }

    function applyRealtimePayload(payload) {
        if (!payload || !payload.ok) {
            return;
        }

        const pedido = payload.pedido || {};
        const routing = payload.routing_snapshot || {};
        const badge = document.getElementById('pedidoStatusBadge');
        if (badge) {
            badge.className = 'badge badge-' + String(pedido.status || '');
            badge.setAttribute('data-status', String(pedido.status || ''));
            badge.textContent = pedido.status_label || statusLabels[pedido.status] || String(pedido.status || '');
        }
        if (typeof atualizarPinsOrigemDestino === 'function') {
            atualizarPinsOrigemDestino(pedido.status);
        }

        const guinchoNome = document.getElementById('guinchoNomeValue');
        const guinchoTelefone = document.getElementById('guinchoTelefoneValue');
        const guinchoPlaca = document.getElementById('guinchoPlacaValue');
        const emptyState = document.getElementById('guinchoEmptyState');
        if (guinchoNome) {
            guinchoNome.textContent = pedido.guincho_nome || '—';
        }
        if (guinchoTelefone) {
            guinchoTelefone.textContent = pedido.guincho_telefone || '—';
        }
        if (guinchoPlaca) {
            guinchoPlaca.textContent = pedido.guincho_placa || '—';
        }
        if (emptyState) {
            emptyState.style.display = pedido.guincho_nome ? 'none' : '';
        }

        const mapping = {
            routingModeLabel: routing.mode_label || 'Visão geral',
            currentStreetValue: routing.current_street || 'Sem rua confirmada',
            etaValue: routing.eta_label || 'Sem ETA',
            remainingDistanceValue: routing.remaining_distance_label || 'Sem distância',
            distanceValidatedValue: formatMetric(routing.distance_validated_m || 0, ' m', 0),
            validPointsValue: String(routing.valid_points || 0),
            rejectedPointsValue: String(routing.rejected_points || 0),
            progressPercentValue: String(routing.progress_percent || 0) + '%',
            trackingQualityValue: routing.tracking_quality || 'unknown',
            lastPointAtValue: routing.last_point_at || '—'
        };
        Object.keys(mapping).forEach((id) => {
            const node = document.getElementById(id);
            if (node) {
                node.textContent = mapping[id];
            }
        });

        const progressBar = document.getElementById('progressBar');
        if (progressBar) {
            progressBar.style.width = String(routing.progress_percent || 0) + '%';
        }

        renderRecentStreets(Array.isArray(routing.recent_streets) ? routing.recent_streets : []);
        renderRejectedTable(Array.isArray(payload.por_rejected) ? payload.por_rejected.slice(0, 15) : []);

        const lastValid = payload.last_valid_point;
        const status = String(pedido.status || '');
        const livePosition = lastValid
            ? { lat: Number(lastValid.latitude), lng: Number(lastValid.longitude) }
            : ((pedido.lat_guincho !== null && pedido.lng_guincho !== null)
                ? { lat: Number(pedido.lat_guincho), lng: Number(pedido.lng_guincho) }
                : null);
        drawLiveTrail(Array.isArray(payload.por_trail) ? payload.por_trail : [], livePosition);

        if (status === 'a_caminho' && livePosition) {
            drawRouteLine([
                { lat: livePosition.lat, lng: livePosition.lng },
                { lat: latO, lng: lngO }
            ], {
                color: '#f59e0b',
                weight: 4,
                opacity: 0.9,
                dashArray: '10, 8'
            }, 'approach');
        } else {
            approachRouteLayer = clearLayer(approachRouteLayer);
        }
    }

    function fetchRealtimeData() {
        const url = basePath + '/admin/pedido/status-json/' + String(pedidoId) + '?limit=' + String(trailLimit);
        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then((response) => response.ok ? response.json() : null)
            .then((payload) => {
                if (payload) {
                    applyRealtimePayload(payload);
                }
            })
            .catch(() => {});
    }

    function scheduleRefresh() {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }
        refreshTimer = setTimeout(fetchRealtimeData, 250);
    }

    drawRouteLine([
        { lat: latO, lng: lngO },
        { lat: latD, lng: lngD }
    ], {
        color: '#0d6efd',
        weight: 5,
        opacity: 0.8
    }, 'planned');
    drawLiveTrail(initialTrail, null);
    renderRejectedTable(initialRejected);
    if (pedidoStatusInicial === 'a_caminho' && <?php echo isset($pedido['lat_guincho'], $pedido['lng_guincho']) ? 'true' : 'false'; ?>) {
        drawRouteLine([
            { lat: <?php echo isset($pedido['lat_guincho']) ? (float)$pedido['lat_guincho'] : -23.5505; ?>, lng: <?php echo isset($pedido['lng_guincho']) ? (float)$pedido['lng_guincho'] : -46.6333; ?> },
            { lat: latO, lng: lngO }
        ], {
            color: '#f59e0b',
            weight: 4,
            opacity: 0.9,
            dashArray: '10, 8'
        }, 'approach');
    }

    if (window.EventSource) {
        const source = new EventSource(basePath + '/sse/pedido/' + String(pedidoId));
        source.addEventListener('status_update', scheduleRefresh);
        source.addEventListener('localizacao_guincho', function (event) {
            try {
                const payload = JSON.parse(event.data || '{}');
                drawLiveTrail(initialTrail, {
                    lat: Number(payload.lat),
                    lng: Number(payload.lng)
                });
            } catch (error) {
                return;
            }
            scheduleRefresh();
        });
        source.addEventListener('nova_mensagem', function (event) {
            const cb = document.getElementById('chatBox');
            if (!cb) {
                return;
            }
            let payload = null;
            try {
                payload = JSON.parse(event.data || '{}');
            } catch (error) {
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.className = 'chat-msg other mb-2';
            wrapper.innerHTML = '<div class="pd-chat-meta">'
                + escapeHtml(payload.usuario_nome || '')
                + ' &bull; '
                + escapeHtml((payload.criado_em || '').slice(11, 16) || '')
                + '</div><div class="chat-bubble">' + escapeHtml(payload.mensagem || '') + '</div>';
            cb.appendChild(wrapper);
            cb.scrollTop = cb.scrollHeight;
        });
        source.addEventListener('stream_close', function () {
            source.close();
        });
        window.addEventListener('beforeunload', function () {
            source.close();
        });
    }

    const cb = document.getElementById('chatBox');
    if (cb) cb.scrollTop = cb.scrollHeight;

    // A lista operacional encaminha o admin diretamente para a ação escolhida,
    // mas a execução continua usando os formulários e validações desta tela.
    const requestedAction = new URLSearchParams(window.location.search).get('acao');
    const actionModals = {
        status: 'modalStatus',
        atribuir: 'modalGuincho',
        'concluir-manual': 'modalConcluirManual',
        cancelar: 'modalCancelar'
    };
    const requestedModal = actionModals[requestedAction];
    if (requestedModal) {
        const modalElement = document.getElementById(requestedModal);
        if (modalElement && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    }

    fetchRealtimeData();
});
</script>
