<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totais = $totais ?? [];
$pagamentos = $pagamentos ?? [];
$systemMode = $systemMode ?? 'production';

$money = static fn($value): string => 'R$ ' . number_format((float)$value, 2, ',', '.');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/tow-financeiro.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">
    <div class="fin-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Financeiro</span>
                <h1><i class="fas fa-coins me-2 text-primary-custom"></i>Financeiro</h1>
                <p>Receita por corrida, retenções de cancelamento, repasses concluídos e pendentes.</p>
            </div>
            <form method="GET" action="" class="d-flex gap-2 align-items-center">
                <select name="mes" class="form-select form-select-sm fin-select-auto">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo ($mes ?? (int)date('m')) == $m ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <select name="ano" class="form-select form-select-sm fin-select-auto">
                    <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 3; $a--): ?>
                    <option value="<?php echo $a; ?>" <?php echo ($ano ?? (int)date('Y')) == $a ? 'selected' : ''; ?>>
                        <?php echo $a; ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-primary btn-sm">Filtrar</button>
            </form>
        </header>

        <section class="fin-hero p-4 p-lg-5 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="fin-pill"><i class="fas fa-wallet text-success"></i>Repasse recebido</span>
                        <span class="fin-pill"><i class="fas fa-clock text-warning"></i>Repasse pendente</span>
                        <span class="fin-pill"><i class="fas fa-ban text-danger"></i>Cancelamento e retenção</span>
                    </div>
                    <h2 class="mb-2 fin-title">Extrato operacional do guincho.</h2>
                    <p class="mb-0 text-muted">Cada linha mostra o que foi concluído, cancelado, estornado e o que já entrou ou ainda aguarda repasse para você.</p>
                </div>
                <div class="col-lg-4">
                    <div class="history-item">
                        <div class="small text-muted mb-1">Chave PIX cadastrada</div>
                        <strong><?php echo htmlspecialchars((string)($guincho['chave_pix'] ?? 'Não informada')); ?></strong>
                        <div class="small text-muted mt-2">Modo operacional: <?php echo htmlspecialchars($systemMode); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($systemMode === 'freeflow'): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-circle-info me-2"></i>No ambiente <strong>freeflow</strong>, os pagamentos locais podem ser marcados diretamente como aprovados/concluídos sem fila real de PIX.
        </div>
        <?php endif; ?>

        <div class="alert alert-light border mb-4">
            <strong>Como ler este extrato:</strong>
            <span class="d-block small text-muted mt-1">
                <strong>Repasse recebido</strong> = já caiu para você.
                <strong>Repasse pendente</strong> = aprovado, mas ainda não confirmado.
                <strong>Valor estornado</strong> = atendimento cancelado e devolvido ao cliente.
                <strong>Retenção em cancelamentos</strong> = valor retido pela regra de cancelamento tardio.
            </span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_bruto_aprovado'] ?? 0); ?></div>
                    <div class="stat-label">Bruto Aprovado</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_liquido_aprovado'] ?? 0); ?></div>
                    <div class="stat-label">Líquido Apurado
                        <i class="fas fa-info-circle ms-1 hint-icon" title="Valor que já é seu por direito (bruto menos comissão da plataforma) nos pagamentos aprovados do período. Aprovado não é o mesmo que pago — ver 'Repasse Recebido' abaixo pra saber o que já caiu de fato na sua conta."></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_pago_guincho'] ?? 0); ?></div>
                    <div class="stat-label">Repasse Recebido
                        <i class="fas fa-info-circle ms-1 hint-icon" title="Dinheiro que já foi efetivamente transferido pra você via PIX. Só entra aqui depois que o repasse é processado com sucesso — pagamento aprovado pelo cliente não garante repasse imediato."></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_pendente_guincho'] ?? 0); ?></div>
                    <div class="stat-label">Repasse Pendente
                        <i class="fas fa-info-circle ms-1 hint-icon" title="Valor já reconhecido como seu (pagamento aprovado), mas que ainda aguarda a liberação/processamento do repasse via PIX. Se ficar pendente por muito tempo, pode indicar fila de repasse travada — fale com o admin."></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-rotate-left"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_estornado'] ?? 0); ?></div>
                    <div class="stat-label">Valor Estornado</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-scissors"></i></div>
                    <div class="stat-value"><?php echo $money($totais['taxa_retida_cancelamento'] ?? 0); ?></div>
                    <div class="stat-label">Retenção em Cancelamentos
                        <i class="fas fa-info-circle ms-1 hint-icon" title="Parte do valor do pedido que fica retida quando o cliente cancela depois de um certo ponto do atendimento (regra de cancelamento tardio), como compensação pelo deslocamento já feito."></i>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
                    <div class="stat-value"><?php echo (int)($totais['concluidos'] ?? 0); ?></div>
                    <div class="stat-label">Concluídos</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ban"></i></div>
                    <div class="stat-value"><?php echo (int)($totais['cancelados'] ?? 0); ?></div>
                    <div class="stat-label">Cancelados</div>
                </div>
            </div>
        </div>

        <section class="fin-card p-4 p-lg-5">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h3 class="mb-1 fin-subtitle">
                        <i class="fas fa-table me-2 text-primary-custom"></i>Extrato detalhado por atendimento
                        <i class="fas fa-info-circle ms-1 hint-icon" title="Coluna 'Repasse': 'Pago' = já caiu na sua conta; 'Pendente'/status do PIX = aprovado mas repasse ainda em processamento; 'Cancelado com/sem retenção' = pedido cancelado, ver a coluna Retenção; 'Estornado' = pagamento foi devolvido ao cliente, não gera repasse."></i>
                    </h3>
                    <p class="mb-0 text-muted">Corrida, cliente, repasse, estorno e retenção do cancelamento no mesmo lugar.</p>
                </div>
            </div>

            <?php if (!empty($pagamentos)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th class="text-end">Bruto</th>
                            <th class="text-end">Comissão</th>
                            <th class="text-end">Líquido</th>
                            <th class="text-end">Retenção</th>
                            <th class="text-center">Repasse</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentos as $pg): ?>
                        <?php
                        $bruto = (float)($pg['valor_total'] ?? $pg['custo_final'] ?? $pg['custo_estimado'] ?? 0);
                        $liquido = (float)($pg['valor_guincho'] ?? 0);
                        $comissao = max(0, $bruto - $liquido);
                        $retencao = (float)($pg['taxa_cancelamento'] ?? 0);
                        $pedidoStatus = (string)($pg['pedido_status'] ?? '');
                        $pagamentoStatus = (string)($pg['pagamento_status'] ?? '');
                        $statusPix = (string)($pg['status_pix'] ?? '');
                        $repasseLabel = 'Sem repasse';
                        $repasseBadge = 'secondary';

                        if ($pedidoStatus === 'cancelado') {
                            $repasseLabel = $retencao > 0 ? 'Cancelado com retenção' : 'Cancelado sem retenção';
                            $repasseBadge = $retencao > 0 ? 'warning text-dark' : 'secondary';
                        } elseif ($pagamentoStatus === 'estornado') {
                            $repasseLabel = 'Estornado';
                            $repasseBadge = 'danger';
                        } elseif (!empty($pg['pago_guincho'])) {
                            $repasseLabel = 'Pago';
                            $repasseBadge = 'success';
                        } elseif ($pagamentoStatus === 'aprovado') {
                            $repasseLabel = $statusPix !== '' ? $statusPix : 'Pendente';
                            $repasseBadge = 'warning text-dark';
                        }
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime((string)($pg['pedido_em'] ?? 'now'))); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($bp . '/guincho/atendimento/' . (int)$pg['pedido_id']); ?>">#<?php echo (int)$pg['pedido_id']; ?></a>
                                <div class="small text-muted"><?php echo htmlspecialchars((string)($pg['tipo_problema'] ?? '')); ?></div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars((string)($pg['cliente_nome'] ?? '—')); ?>
                                <div class="small text-muted"><?php echo htmlspecialchars((string)($pg['endereco_origem'] ?? '')); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $pedidoStatus === 'concluido' ? 'success' : ($pedidoStatus === 'cancelado' ? 'danger' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoStatus))); ?>
                                </span>
                                <?php if (!empty($pg['motivo_cancelamento'])): ?>
                                <div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$pg['motivo_cancelamento']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo $money($bruto); ?></td>
                            <td class="text-end text-danger"><?php echo $money($comissao); ?></td>
                            <td class="text-end fw-bold text-success"><?php echo $money($liquido); ?></td>
                            <td class="text-end"><?php echo $retencao > 0 ? $money($retencao) : '—'; ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $repasseBadge; ?>"><?php echo htmlspecialchars($repasseLabel); ?></span>
                                <?php if (!empty($pg['data_pagamento_guincho'])): ?>
                                <div class="small text-muted mt-1"><?php echo date('d/m H:i', strtotime((string)$pg['data_pagamento_guincho'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($pg['pago_guincho'])): ?>
                                <a class="d-block small mt-1" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($bp . '/guincho/pedido/recibo/' . (int)$pg['pedido_id']); ?>">
                                    <i class="fas fa-receipt me-1"></i>Recibo
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-coins fa-2x mb-2 d-block"></i>
                Nenhum registro financeiro encontrado para o período selecionado.
            </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
