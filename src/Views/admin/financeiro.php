<?php
/**
 * Financeiro — reestruturada pro padrão shell-ops (mesma arquitetura de
 * /admin/central, /admin/alertas, /admin/documentos...), preservando 100%
 * dos gráficos, KPIs, filtros e listas de insight já existentes. A única
 * mudança estrutural é que a antiga tabela "Pagamentos" (+ a tabela solta
 * "Fila de Repasse PIX" ao lado dela) vira a worklist clicável do
 * shell-ops: cada pagamento é um item da lista, e o workspace mostra o
 * detalhe completo do pagamento selecionado — incluindo os payment jobs de
 * repasse PIX vinculados a ele (correlacionados por pagamento_id no JS,
 * sem round-trip: os dados já vêm carregados do servidor, igual ao padrão
 * usado em Alertas Operacionais e Documentos).
 *
 * @var array $pagamentos
 * @var array $paymentJobs
 * @var array $filtrosPagamento
 * @var array $filtrosJobs
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

/**
 * §GATEWAY-STATUS-02: mapeia bandeira/forma de pagamento (payment_method_id
 * do MP, ou paymentMethod.type/brand do PagSeguro) pra um ícone + rótulo
 * reconhecível — só apresentação, por isso fica na view e não no model.
 */
$formaPagamentoIcone = static function (string $bandeira, string $forma): array {
    $chave = strtolower($bandeira !== '' ? $bandeira : $forma);
    $mapa = [
        'visa'          => ['fa-brands fa-cc-visa', 'Visa'],
        'master'        => ['fa-brands fa-cc-mastercard', 'Mastercard'],
        'mastercard'    => ['fa-brands fa-cc-mastercard', 'Mastercard'],
        'amex'          => ['fa-brands fa-cc-amex', 'Amex'],
        'american_express' => ['fa-brands fa-cc-amex', 'Amex'],
        'elo'           => ['fa-solid fa-credit-card', 'Elo'],
        'hipercard'     => ['fa-solid fa-credit-card', 'Hipercard'],
        'diners'        => ['fa-brands fa-cc-diners-club', 'Diners'],
        'discover'      => ['fa-brands fa-cc-discover', 'Discover'],
        'pix'           => ['fa-solid fa-qrcode', 'Pix'],
        'bolbradesco'   => ['fa-solid fa-barcode', 'Boleto'],
        'boleto'        => ['fa-solid fa-barcode', 'Boleto'],
        'ticket'        => ['fa-solid fa-barcode', 'Boleto'],
        'pec'           => ['fa-solid fa-barcode', 'Boleto'],
        'credit_card'   => ['fa-solid fa-credit-card', 'Cartão de crédito'],
        'debit_card'    => ['fa-solid fa-credit-card', 'Cartão de débito'],
    ];
    return $mapa[$chave] ?? ['fa-solid fa-money-bill', ucfirst($chave ?: 'outro')];
};

$statusPagamentoOptions = [
    '' => 'Todos',
    'pendente' => 'Pendente',
    'aprovado' => 'Aprovado',
    'estornado' => 'Estornado',
    'rejeitado' => 'Rejeitado',
    'cancelado' => 'Cancelado',
];

$metodoOptions = [
    '' => 'Todos',
    'pix' => 'PIX',
    'mercadopago' => 'Mercado Pago',
    'pagseguro' => 'PagSeguro',
    'freeflow' => 'Freeflow',
];

$jobStatusOptions = [
    '' => 'Todos',
    'queued' => 'Queued',
    'running' => 'Running',
    'retry' => 'Retry',
    'completed' => 'Completed',
    'failed' => 'Failed',
];

$jobTypeOptions = [
    '' => 'Todos',
    'pix_payout' => 'PIX Payout',
];

// Monta os dados de cada pagamento (linha da worklist + payload do
// workspace) uma única vez aqui, incluindo o resumo de gateway e os jobs
// de repasse PIX vinculados — evita reimplementar essa lógica em JS.
$jobsPorPagamento = [];
foreach (($paymentJobs ?? []) as $job) {
    $pid = (int)($job['pagamento_id'] ?? 0);
    if ($pid > 0) {
        $jobsPorPagamento[$pid][] = $job;
    }
}

$pagamentosPayload = [];
foreach (($pagamentos ?? []) as $p) {
    $gw = Pagamento::statusGatewayResumo($p);
    $formaPagamentoCliente = $gw['forma_pagamento'] !== ''
        ? $gw['forma_pagamento']
        : (string)($p['metodo_normalizado'] ?? $p['metodo'] ?? '');
    [$iconeClasse, $rotuloForma] = $formaPagamentoIcone((string)($gw['bandeira'] ?? ''), $formaPagamentoCliente);
    $retencao = 0.0;
    if (($p['status'] ?? '') === 'estornado') {
        $retencao = max(0.0, (float)($p['valor_total'] ?? 0) - (float)($p['valor_guincho'] ?? 0));
    }
    $pagamentosPayload[] = [
        'id' => (int)$p['id'],
        'pedido_id' => (int)$p['pedido_id'],
        'cliente_nome' => (string)($p['cliente_nome'] ?? '—'),
        'guincho_nome' => (string)($p['guincho_nome'] ?? '—'),
        'metodo' => (string)($p['metodo_normalizado'] ?? $p['metodo'] ?? '—'),
        'status' => (string)($p['status'] ?? '—'),
        'tipo_problema' => (string)($p['tipo_problema'] ?? '—'),
        'valor_total' => (float)($p['valor_total'] ?? 0),
        'valor_guincho' => (float)($p['valor_guincho'] ?? 0),
        'valor_plataforma' => (float)($p['valor_plataforma'] ?? 0),
        'retencao' => $retencao,
        'status_pix' => (string)($p['status_pix'] ?? ''),
        'data' => (string)($p['data_pagamento'] ?? $p['criado_em'] ?? '—'),
        'gw_status' => (string)($gw['status'] ?? ''),
        'gw_detalhe' => (string)($gw['detalhe'] ?? ''),
        'forma_pagamento_icone' => $iconeClasse,
        'forma_pagamento_label' => $rotuloForma,
        'jobs' => array_map(static function (array $job): array {
            return [
                'id' => (int)$job['id'],
                'job_type' => (string)($job['job_type'] ?? ''),
                'status' => (string)($job['status'] ?? 'queued'),
                'attempt_count' => (int)($job['attempt_count'] ?? 0),
                'max_attempts' => (int)($job['max_attempts'] ?? 0),
                'worker_id' => (string)($job['worker_id'] ?? '—'),
                'available_at' => (string)($job['available_at'] ?? '—'),
                'last_error' => (string)($job['last_error'] ?? ''),
            ];
        }, $jobsPorPagamento[(int)$p['id']] ?? []),
    ];
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-financeiro.css">
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar">
    <form method="get" action="<?php echo htmlspecialchars($bp . '/admin/financeiro'); ?>" class="ops-topbar__search">
        <i class="fas fa-magnifying-glass"></i>
        <input type="number" name="pedido_id" value="<?php echo htmlspecialchars((string)($filtrosJobs['pedido_id'] ?? '')); ?>" placeholder="Buscar por nº do pedido" autocomplete="off" aria-label="Buscar pagamento por pedido">
    </form>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo (int)($totalPagamentos ?? 0); ?> pagamentos no período</span>
        <span id="opsLastUpdate"><?php echo htmlspecialchars((string)($dataInicio ?? '—')); ?> – <?php echo htmlspecialchars((string)($dataFim ?? '—')); ?></span>
    </div>
</div>

<div style="padding:0 24px;">

<?php if (($_GET['msg'] ?? '') === 'payment_job_reenfileirado'): ?>
<div class="alert alert-success mt-3 mb-0">
    <i class="fas fa-check-circle me-2"></i>Payment job reenfileirado para nova tentativa.
</div>
<?php elseif (($_GET['msg'] ?? '') === 'payment_job_retry_falha'): ?>
<div class="alert alert-danger mt-3 mb-0">
    <i class="fas fa-exclamation-triangle me-2"></i>
    Falha ao reenfileirar payment job.
    <?php if (!empty($_GET['job_error'])): ?>
    <span class="d-block small mt-1"><?php echo htmlspecialchars((string)$_GET['job_error']); ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<header class="page-head mt-3 mb-4">
    <div>
        <span class="eyebrow">Gestão</span>
        <h1><i class="fas fa-chart-line me-2 text-primary-custom"></i>Financeiro</h1>
        <p>Recebimentos aprovados, repasses e fila operacional de PIX</p>
    </div>
    <a href="<?php echo htmlspecialchars($bp . '/admin/financeiro/csv?' . http_build_query($queryBase ?? [])); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-file-csv me-1"></i>Exportar CSV
    </a>
</header>

<div class="alert alert-info mb-3">
    <i class="fas fa-circle-info me-2"></i>
    Os relatórios agora usam, por padrão, todo o intervalo encontrado na base:
    <strong><?php echo htmlspecialchars((string)($dataInicio ?? '—')); ?></strong> até
    <strong><?php echo htmlspecialchars((string)($dataFim ?? '—')); ?></strong>.
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?php echo htmlspecialchars($bp . '/admin/financeiro'); ?>" class="row g-2">
            <div class="col-md-2">
                <label class="form-label">Início</label>
                <input type="date" name="inicio" class="form-control" value="<?php echo htmlspecialchars((string)($dataInicio ?? '')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fim</label>
                <input type="date" name="fim" class="form-control" value="<?php echo htmlspecialchars((string)($dataFim ?? '')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status Pagamento</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusPagamentoOptions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($filtrosPagamento['status'] ?? '') === $value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Método</label>
                <select name="metodo" class="form-select">
                    <?php foreach ($metodoOptions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($filtrosPagamento['metodo'] ?? '') === $value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status Job</label>
                <select name="job_status" class="form-select">
                    <?php foreach ($jobStatusOptions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($filtrosJobs['status'] ?? '') === $value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo Job</label>
                <select name="job_type" class="form-select">
                    <?php foreach ($jobTypeOptions as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($filtrosJobs['job_type'] ?? '') === $value) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Pedido</label>
                <input type="number" name="pedido_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtrosJobs['pedido_id'] ?? '')); ?>" placeholder="123">
            </div>
            <div class="col-md-2">
                <label class="form-label">Worker</label>
                <input type="text" name="worker_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtrosJobs['worker_id'] ?? '')); ?>" placeholder="cron_pix">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="<?php echo htmlspecialchars($bp . '/admin/financeiro'); ?>" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-value">R$ <?php echo number_format((float)($totais['valor_total'] ?? 0), 2, ',', '.'); ?></div>
            <div class="stat-label">Receita Aprovada</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-percentage"></i></div>
            <div class="stat-value">R$ <?php echo number_format((float)($totais['valor_plataforma'] ?? 0), 2, ',', '.'); ?></div>
            <div class="stat-label">Comissão Plataforma</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-handshake"></i></div>
            <div class="stat-value">R$ <?php echo number_format((float)($totais['valor_guincho'] ?? 0), 2, ',', '.'); ?></div>
            <div class="stat-label">Repasse Guincho</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-value"><?php echo (int)($totalPagamentos ?? 0); ?></div>
            <div class="stat-label">Pagamentos Filtrados</div>
        </div>
    </div>
</div>

<div class="stat-grid mb-4">
    <div class="stat-grid__item">
        <span class="stat-grid__label">Ticket Médio</span>
        <strong class="stat-grid__value"><?php echo 'R$ ' . number_format((float)($paymentInsights['ticket_medio'] ?? 0), 2, ',', '.'); ?></strong>
    </div>
    <div class="stat-grid__item is-danger">
        <span class="stat-grid__label">Estornado</span>
        <strong class="stat-grid__value"><?php echo 'R$ ' . number_format((float)($paymentInsights['valor_estornado'] ?? 0), 2, ',', '.'); ?></strong>
    </div>
    <div class="stat-grid__item is-warning">
        <span class="stat-grid__label">Repasse Pendente</span>
        <strong class="stat-grid__value"><?php echo 'R$ ' . number_format((float)($paymentInsights['repasse_pendente'] ?? 0), 2, ',', '.'); ?></strong>
    </div>
    <div class="stat-grid__item">
        <span class="stat-grid__label">Retenção Cancelamento</span>
        <strong class="stat-grid__value"><?php echo 'R$ ' . number_format((float)($paymentInsights['taxa_retida_total'] ?? 0), 2, ',', '.'); ?></strong>
        <span class="stat-grid__hint"><?php echo (int)($paymentInsights['cancelamentos_com_taxa'] ?? 0); ?> cancelamento(s) com taxa</span>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2"></i>Pagamentos por Status
                <i class="fas fa-info-circle ms-1 hint-icon" title="Distribuição dos pagamentos pelo status atual no gateway: aprovado, pendente, recusado, cancelado ou estornado. Pagamento 'aprovado' aqui não significa que o guincho já recebeu o repasse — isso depende da liquidação do gateway e da fila de repasse PIX."></i>
            </div>
            <div class="card-body">
                <canvas id="chartPaymentStatus" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-credit-card me-2"></i>Métodos de Pagamento
                <i class="fas fa-info-circle ms-1 hint-icon" title="Quantidade de pagamentos por gateway usado (Mercado Pago, PagSeguro). O gateway ativo pra novos pedidos é definido pelo admin em Configurações — o cliente nunca escolhe."></i>
            </div>
            <div class="card-body">
                <canvas id="chartPaymentMethod" height="220"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-wave-square me-2"></i>Receita Aprovada por Dia
                <i class="fas fa-info-circle ms-1 hint-icon" title="Soma dos pagamentos com status 'aprovado' agrupada por dia. É receita reconhecida no pedido, não necessariamente dinheiro já disponível para saque — depende da liquidação do gateway (ver Fila de Repasse PIX)."></i>
            </div>
            <div class="card-body">
                <canvas id="chartPaymentSeries" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-truck me-2"></i>Top Guinchos por Repasse
                <i class="fas fa-info-circle ms-1 hint-icon" title="Guinchos com maior valor total de repasse (valor_guincho) nos pedidos concluídos do período — ajuda a identificar os operadores mais ativos e o volume que passa pela carteira de cada um."></i>
            </div>
            <div class="card-body">
                <?php if (empty($topGuinchos)): ?>
                <div class="text-muted small">Sem dados suficientes no período.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Guincho</th><th class="text-end">Corridas</th><th class="text-end">Repasse</th></tr></thead>
                        <tbody>
                            <?php foreach ($topGuinchos as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($row['nome'] ?? '—')); ?></td>
                                <td class="text-end"><?php echo (int)($row['corridas'] ?? 0); ?></td>
                                <td class="text-end"><?php echo 'R$ ' . number_format((float)($row['valor_guincho'] ?? 0), 2, ',', '.'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-users me-2"></i>Top Clientes por Faturamento
                <i class="fas fa-info-circle ms-1 hint-icon" title="Clientes com maior valor total pago à plataforma no período — útil pra identificar contas de maior volume/recorrência."></i>
            </div>
            <div class="card-body">
                <?php if (empty($topClientes)): ?>
                <div class="text-muted small">Sem dados suficientes no período.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Cliente</th><th class="text-end">Pedidos</th><th class="text-end">Valor</th></tr></thead>
                        <tbody>
                            <?php foreach ($topClientes as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string)($row['nome'] ?? '—')); ?></td>
                                <td class="text-end"><?php echo (int)($row['pedidos'] ?? 0); ?></td>
                                <td class="text-end"><?php echo 'R$ ' . number_format((float)($row['valor_total'] ?? 0), 2, ',', '.'); ?></td>
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

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Jobs</div><div class="fs-4 fw-bold"><?php echo (int)($paymentJobStats['total_jobs'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Queued</div><div class="fs-4 fw-bold text-warning"><?php echo (int)($paymentJobStats['queued_jobs'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Running</div><div class="fs-4 fw-bold text-primary"><?php echo (int)($paymentJobStats['running_jobs'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Retry</div><div class="fs-4 fw-bold text-warning"><?php echo (int)($paymentJobStats['retry_jobs'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Completed</div><div class="fs-4 fw-bold text-success"><?php echo (int)($paymentJobStats['completed_jobs'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card h-100"><div class="card-body"><div class="text-muted small">Failed</div><div class="fs-4 fw-bold text-danger"><?php echo (int)($paymentJobStats['failed_jobs'] ?? 0); ?></div></div></div>
    </div>
</div>

<?php if (($systemMode ?? 'production') === 'freeflow'): ?>
<div class="alert alert-info mb-4">
    <i class="fas fa-circle-info me-2"></i>
    O ambiente está em <strong>freeflow</strong>: os atendimentos são concluídos sem fila real de repasse PIX.
    Por isso, os contadores de <strong>Jobs</strong> permanecem zerados até o ambiente operar com repasse assíncrono habilitado.
</div>
<?php endif; ?>

</div>

<div class="shell-ops" id="financeiroShell" style="min-height:640px;">

    <aside class="shell-ops-sidebar" id="financeiroSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-worklist" aria-label="Pagamentos">
        <header class="ops-worklist-header">
            <span class="eyebrow">Extrato</span>
            <h2>Pagamentos</h2>
            <p><span id="financeiroWorklistCount"><?php echo count($pagamentosPayload); ?></span> nesta página</p>
        </header>

        <div class="ops-worklist-search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" id="financeiroWorklistSearch" placeholder="Filtrar nesta lista" autocomplete="off">
        </div>

        <div class="ops-worklist-results" id="financeiroWorklistResults">
            <?php if (empty($pagamentosPayload)): ?>
                <div class="ops-empty-state">
                    <i class="fas fa-money-bill-wave"></i>
                    Nenhum pagamento encontrado para o filtro atual.
                </div>
            <?php else: foreach ($pagamentosPayload as $i => $pp):
                $busca = strtolower($pp['cliente_nome'] . ' ' . $pp['guincho_nome'] . ' ' . $pp['metodo'] . ' ' . $pp['pedido_id']);
                $temFalha = false;
                foreach ($pp['jobs'] as $j) { if ($j['status'] === 'failed') { $temFalha = true; break; } }
            ?>
                <button type="button"
                    class="ops-worklist-item <?php echo $pp['status'] === 'estornado' ? 'is-critical' : ($temFalha ? 'is-warning' : ''); ?>"
                    data-pagamento-id="<?php echo (int)$pp['id']; ?>"
                    data-search-blob="<?php echo htmlspecialchars($busca, ENT_QUOTES, 'UTF-8'); ?>"
                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                >
                    <span class="ops-worklist-item__priority" aria-hidden="true"></span>
                    <span class="ops-worklist-item__content">
                        <span class="ops-worklist-item__top">
                            <strong>Pedido #<?php echo (int)$pp['pedido_id']; ?></strong>
                            <span class="ops-badge <?php echo $pp['status'] === 'estornado' ? 'ops-badge--critical' : ($pp['status'] === 'aprovado' ? 'ops-badge--service' : 'ops-badge--audit'); ?>"><?php echo htmlspecialchars(ucfirst($pp['status'])); ?></span>
                        </span>
                        <span class="ops-worklist-item__customer"><?php echo htmlspecialchars($pp['cliente_nome']); ?> · R$ <?php echo number_format($pp['valor_total'], 2, ',', '.'); ?></span>
                        <span class="ops-worklist-item__footer">
                            <span><?php echo htmlspecialchars($pp['metodo']); ?></span>
                            <span><?php echo htmlspecialchars($pp['data']); ?></span>
                        </span>
                    </span>
                    <span class="ops-worklist-item__signals">
                        <?php if ($temFalha): ?>
                        <span class="ops-signal is-danger" title="Repasse com falha"><i class="fas fa-triangle-exclamation"></i></span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; endif; ?>
        </div>

        <?php if (($totalPaginas ?? 1) > 1): ?>
        <nav style="padding:10px 16px;">
            <div class="d-flex justify-content-between align-items-center">
                <?php $prevQuery = http_build_query(array_merge($queryBase ?? [], ['pagina' => max(1, (int)($pagina ?? 1) - 1)])); ?>
                <?php $nextQuery = http_build_query(array_merge($queryBase ?? [], ['pagina' => min((int)($totalPaginas ?? 1), (int)($pagina ?? 1) + 1)])); ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/financeiro?' . $prevQuery); ?>">&laquo;</a>
                <span class="small text-muted">Pág. <?php echo (int)($pagina ?? 1); ?>/<?php echo (int)($totalPaginas ?? 1); ?></span>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/financeiro?' . $nextQuery); ?>">&raquo;</a>
            </div>
        </nav>
        <?php endif; ?>
    </section>

    <section class="shell-ops-workspace" id="financeiroWorkspace" aria-live="polite">
        <?php if (empty($pagamentosPayload)): ?>
        <div class="ops-empty-state" style="padding:80px 20px">
            <i class="fas fa-inbox"></i>
            Nenhum pagamento pra exibir.
        </div>
        <?php endif; ?>
    </section>

</div>

<script<?php echo csp_script_nonce_attr(); ?>>
window.__financeiroData = <?php echo json_encode($pagamentosPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var FIN_BP = '<?php echo addslashes($bp); ?>';
var FIN_CSRF = <?php echo json_encode($csrfToken ?? ''); ?>;
var FIN_QUERY = <?php echo json_encode('/admin/financeiro?' . http_build_query($queryBase ?? [])); ?>;

(function () {
    var payments = window.__financeiroData || [];
    var shell = document.getElementById('financeiroShell');
    var results = document.getElementById('financeiroWorklistResults');
    var workspace = document.getElementById('financeiroWorkspace');
    if (!shell || !results || !workspace) return;

    var jobBadgeClass = { completed: 'success', failed: 'danger', running: 'primary', queued: 'warning', retry: 'warning' };

    function escapeHtml(value) {
        var el = document.createElement('div');
        el.textContent = String(value == null ? '' : value);
        return el.innerHTML;
    }

    function money(v) {
        return 'R$ ' + Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function renderPayment(paymentId) {
        var p = null;
        for (var i = 0; i < payments.length; i++) { if (payments[i].id === paymentId) { p = payments[i]; break; } }
        if (!p) return;

        var statusBadge = p.status === 'estornado' ? 'ops-badge--critical' : (p.status === 'aprovado' ? 'ops-badge--service' : 'ops-badge--audit');

        var html = '<header class="ops-order-header">' +
            '<div><h1>Pedido #' + p.pedido_id + '</h1>' +
            '<p>' + escapeHtml(p.cliente_nome) + ' · ' + escapeHtml(p.data) + '</p></div>' +
            '<span class="ops-badge ' + statusBadge + '">' + escapeHtml(p.status) + '</span>' +
            '</header>';

        html += '<div style="padding:0 24px 12px"><a class="ops-btn" href="' + FIN_BP + '/admin/pedido/' + p.pedido_id + '"><i class="fas fa-eye"></i> Ver pedido #' + p.pedido_id + '</a></div>';

        html += '<div style="padding:0 24px 32px">';

        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-money-bill-wave me-2"></i>Valores</div><div class="card-body">';
        html += '<table class="table table-sm mb-0 ghd-table">';
        html += '<tr><td class="ghd-label ghd-label--35">Guincho</td><td>' + escapeHtml(p.guincho_nome) + '</td></tr>';
        html += '<tr><td class="ghd-label">Método</td><td>' + escapeHtml(p.metodo) + '</td></tr>';
        html += '<tr><td class="ghd-label">Problema</td><td>' + escapeHtml(p.tipo_problema) + '</td></tr>';
        html += '<tr><td class="ghd-label">Total</td><td>' + money(p.valor_total) + '</td></tr>';
        html += '<tr><td class="ghd-label">Guincho</td><td>' + money(p.valor_guincho) + '</td></tr>';
        html += '<tr><td class="ghd-label">Plataforma</td><td>' + money(p.valor_plataforma) + '</td></tr>';
        html += '<tr><td class="ghd-label">Retenção</td><td>' + (p.retencao > 0 ? money(p.retencao) : '—') + '</td></tr>';
        html += '</table></div></div>';

        html += '<div class="card mb-3"><div class="card-header"><i class="fas fa-credit-card me-2"></i>Status Gateway (pagamento do cliente)</div><div class="card-body">';
        if (p.gw_status || p.gw_detalhe || p.forma_pagamento_label) {
            if (p.gw_status) html += '<span class="badge bg-light text-dark border">' + escapeHtml(p.gw_status) + '</span> ';
            html += '<div class="small text-muted mt-1"><i class="' + escapeHtml(p.forma_pagamento_icone) + ' me-1"></i>Cliente pagou via: <strong>' + escapeHtml(p.forma_pagamento_label) + '</strong></div>';
            if (p.gw_detalhe) html += '<div class="small text-muted">' + escapeHtml(p.gw_detalhe) + '</div>';
        } else {
            html += '<span class="text-muted small">—</span>';
        }
        html += '</div></div>';

        html += '<div class="card"><div class="card-header"><i class="fas fa-list-check me-2"></i>Fila de Repasse PIX (guincho)' +
            '<i class="fas fa-info-circle ms-1 hint-icon" title="Isso é sobre o repasse ao GUINCHO, não sobre o pagamento do cliente."></i></div><div class="card-body p-0">';
        if (!p.jobs.length) {
            html += '<div class="p-3 text-muted small">Nenhum payment job de repasse vinculado a este pagamento.</div>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>Job</th><th>Status</th><th>Tentativas</th><th>Worker</th><th>Próx. Tentativa</th><th>Erro</th><th class="text-end">Ação</th></tr></thead><tbody>';
            p.jobs.forEach(function (job) {
                var badge = jobBadgeClass[job.status] || 'secondary';
                html += '<tr>';
                html += '<td>#' + job.id + '<div class="small text-muted">' + escapeHtml(job.job_type) + '</div></td>';
                html += '<td><span class="badge bg-' + badge + '">' + escapeHtml(job.status) + '</span></td>';
                html += '<td>' + job.attempt_count + ' / ' + job.max_attempts + '</td>';
                html += '<td>' + escapeHtml(job.worker_id) + '</td>';
                html += '<td>' + escapeHtml(job.available_at) + '</td>';
                html += '<td>' + (job.last_error ? '<span class="text-danger small">' + escapeHtml(job.last_error) + '</span>' : '<span class="text-muted">—</span>') + '</td>';
                html += '<td class="text-end">';
                if (job.status !== 'completed') {
                    html += '<form method="post" action="' + FIN_BP + '/admin/payment-job/retry/' + job.id + '">' +
                        '<input type="hidden" name="csrf_token" value="' + escapeHtml(FIN_CSRF) + '">' +
                        '<input type="hidden" name="redirect_to" value="' + escapeHtml(FIN_QUERY) + '">' +
                        '<button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-rotate-right me-1"></i>Reenfileirar</button></form>';
                } else {
                    html += '<span class="text-muted small">Concluído</span>';
                }
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div></div>';

        html += '</div>';

        workspace.innerHTML = html;
    }

    function selectPayment(paymentId) {
        results.querySelectorAll('[data-pagamento-id]').forEach(function (btn) {
            btn.setAttribute('aria-selected', String(Number(btn.dataset.pagamentoId) === paymentId));
        });
        renderPayment(paymentId);
        if (window.matchMedia('(max-width: 767px)').matches) {
            shell.classList.toggle('has-selection', true);
        }
    }

    results.addEventListener('click', function (event) {
        var item = event.target.closest('[data-pagamento-id]');
        if (!item) return;
        selectPayment(Number(item.dataset.pagamentoId));
    });

    function applyFilter(term) {
        var t = term.trim().toLowerCase();
        results.querySelectorAll('[data-pagamento-id]').forEach(function (btn) {
            var blob = btn.dataset.searchBlob || '';
            btn.style.display = (t === '' || blob.indexOf(t) !== -1) ? '' : 'none';
        });
    }

    var worklistSearch = document.getElementById('financeiroWorklistSearch');
    if (worklistSearch) worklistSearch.addEventListener('input', function (e) { applyFilter(e.target.value); });

    if (payments.length > 0) selectPayment(payments[0].id);
})();
</script>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops (grid próprio),
// igual à Central Operacional, Alertas, Documentos, Guinchos, Usuários...
?>
<script<?php echo csp_script_nonce_attr(); ?> src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function () {
    const textColor = getComputedStyle(document.body).getPropertyValue('--theme-text') || '#e8e8e8';
    const gridColor = 'rgba(255,255,255,.08)';

    const statusRows = <?php echo json_encode($paymentStatusBreakdown ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const methodRows = <?php echo json_encode($paymentMethodBreakdown ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const seriesRows = <?php echo json_encode($paymentApprovedSeries ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const statusCanvas = document.getElementById('chartPaymentStatus');
    if (statusCanvas) {
        new Chart(statusCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: statusRows.map((row) => row.status || 'sem-status'),
                datasets: [{
                    data: statusRows.map((row) => Number(row.total || 0)),
                    backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#6b7280', '#0d6efd'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
            }
        });
    }

    const methodCanvas = document.getElementById('chartPaymentMethod');
    if (methodCanvas) {
        new Chart(methodCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: methodRows.map((row) => row.metodo || 'sem-metodo'),
                datasets: [{
                    data: methodRows.map((row) => Number(row.valor_total || 0)),
                    backgroundColor: ['#0d6efd', '#8b5cf6', '#10b981', '#f97316', '#eab308'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
            }
        });
    }

    const seriesCanvas = document.getElementById('chartPaymentSeries');
    if (seriesCanvas) {
        new Chart(seriesCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: seriesRows.map((row) => row.label || ''),
                datasets: [{
                    label: 'Receita aprovada',
                    data: seriesRows.map((row) => Number(row.valor_total || 0)),
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,.12)',
                    fill: true,
                    tension: 0.35
                }, {
                    label: 'Qtde pagamentos',
                    data: seriesRows.map((row) => Number(row.total_pagamentos || 0)),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,.12)',
                    fill: true,
                    tension: 0.35,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { labels: { color: textColor } } },
                scales: {
                    x: { ticks: { color: textColor }, grid: { color: gridColor } },
                    y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        ticks: { color: textColor },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }
});
</script>
</body>
</html>
