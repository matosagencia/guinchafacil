<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totais = $totais ?? [];
$pagamentos = $pagamentos ?? [];
$money = static fn($value): string => 'R$ ' . number_format((float)$value, 2, ',', '.');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-financeiro.css">

<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <div class="fin-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Financeiro</span>
                <h1><i class="fas fa-wallet me-2 text-primary-custom"></i>Financeiro</h1>
                <p>Pagamentos, estornos e taxas de cancelamento com transparência por pedido.</p>
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
            <h2 class="mb-2 fin-title">Tudo o que foi pago, devolvido ou retido.</h2>
            <p class="mb-0 text-muted">Aqui você acompanha pedido por pedido: valor cobrado, estorno efetivado e taxa de cancelamento aplicada quando houver.</p>
        </section>

        <div class="alert alert-light border mb-4">
            <strong>Como ler este extrato:</strong>
            <span class="d-block small text-muted mt-1">
                <strong>Pago</strong> = valor autorizado no pedido.
                <strong>Estornado</strong> = valor devolvido ao cliente após cancelamento.
                <strong>Taxa</strong> = parcela retida em cancelamento tardio.
                <strong>Financeiro</strong> = situação atual do registro do pedido.
            </span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_pago'] ?? 0); ?></div>
                    <div class="stat-label">Pago</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-rotate-left"></i></div>
                    <div class="stat-value"><?php echo $money($totais['valor_estornado'] ?? 0); ?></div>
                    <div class="stat-label">Estornado</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-scissors"></i></div>
                    <div class="stat-value"><?php echo $money($totais['taxa_cancelamento_total'] ?? 0); ?></div>
                    <div class="stat-label">Taxas de Cancelamento</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-value"><?php echo (int)($totais['total_pedidos'] ?? 0); ?></div>
                    <div class="stat-label">Pedidos no Período</div>
                </div>
            </div>
        </div>

        <section class="fin-card p-4 p-lg-5">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h3 class="mb-1 fin-subtitle"><i class="fas fa-table me-2 text-primary-custom"></i>Extrato por pedido</h3>
                    <p class="mb-0 text-muted">Cada atendimento com situação do pedido, valor pago, estorno e motivo de cancelamento quando existir.</p>
                </div>
            </div>

            <?php if (!empty($pagamentos)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pedido</th>
                            <th>Guincho</th>
                            <th>Status</th>
                            <th class="text-end">Cobrado</th>
                            <th class="text-end">Estornado</th>
                            <th class="text-end">Taxa</th>
                            <th class="text-center">Financeiro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagamentos as $pg): ?>
                        <?php
                        $pedidoStatus = (string)($pg['pedido_status'] ?? '');
                        $pagamentoStatus = (string)($pg['pagamento_status'] ?? '');
                        $bruto = (float)($pg['valor_total'] ?? $pg['custo_final'] ?? $pg['custo_estimado'] ?? 0);
                        $retencao = (float)($pg['taxa_cancelamento'] ?? 0);
                        $estornado = $pagamentoStatus === 'estornado' ? $bruto : 0.0;
                        $financeiroLabel = $pagamentoStatus !== '' ? $pagamentoStatus : 'sem registro';
                        $financeiroBadge = $pagamentoStatus === 'aprovado' ? 'success'
                            : ($pagamentoStatus === 'estornado' ? 'danger' : ($pagamentoStatus === 'pendente' ? 'warning text-dark' : 'secondary'));
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime((string)($pg['pedido_em'] ?? 'now'))); ?></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($bp . '/cliente/pedido/' . (int)$pg['pedido_id']); ?>">#<?php echo (int)$pg['pedido_id']; ?></a>
                                <div class="small text-muted"><?php echo htmlspecialchars((string)($pg['tipo_problema'] ?? '')); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string)($pg['guincho_nome'] ?? '—')); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $pedidoStatus === 'concluido' ? 'success' : ($pedidoStatus === 'cancelado' ? 'danger' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoStatus))); ?>
                                </span>
                                <?php if (!empty($pg['motivo_cancelamento'])): ?>
                                <div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$pg['motivo_cancelamento']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo $money($bruto); ?></td>
                            <td class="text-end"><?php echo $estornado > 0 ? $money($estornado) : '—'; ?></td>
                            <td class="text-end"><?php echo $retencao > 0 ? $money($retencao) : '—'; ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $financeiroBadge; ?>"><?php echo htmlspecialchars($financeiroLabel); ?></span>
                                <?php if (!empty($pg['data_pagamento'])): ?>
                                <div class="small text-muted mt-1"><?php echo date('d/m H:i', strtotime((string)$pg['data_pagamento'])); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-wallet fa-2x mb-2 d-block"></i>
                Nenhum registro financeiro encontrado para o período selecionado.
            </div>
            <?php endif; ?>
        </section>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
