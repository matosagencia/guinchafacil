<?php
// §CELULAS-NITEROI-01 (04/08/2026): PorThresholds/$osrmBaseUrl não são mais
// necessários aqui — o "Mapa operacional ao vivo" (que precisava do OSRM
// pra desenhar rota) foi movido pra /admin/central.
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/css/pages/admin-dashboard.css?v=20260802-2">
<?php

$statusLabels = [
    'aguardando_pagamento' => 'Aguard. Pagamento',
    'aguardando_guincho' => 'Aguard. Guincho',
    'a_caminho' => 'A Caminho',
    'no_local' => 'No Local',
    'em_reboque' => 'Em Reboque',
    'concluido' => 'Concluído',
    'cancelado' => 'Cancelado',
];

$serieLabels = array_map(static fn(array $row): string => (string)($row['label'] ?? ''), $pedidoSerie ?? []);
$serieTotais = array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $pedidoSerie ?? []);
$serieConcluidos = array_map(static fn(array $row): int => (int)($row['concluidos'] ?? 0), $pedidoSerie ?? []);
$statusChartLabels = array_map(static fn(array $row): string => $statusLabels[(string)($row['status'] ?? '')] ?? ucfirst(str_replace('_', ' ', (string)($row['status'] ?? ''))), $pedidoStatusBreakdown ?? []);
$statusChartValues = array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $pedidoStatusBreakdown ?? []);
$preQuoteDemandLabels = array_map(static fn(array $row): string => ucfirst(str_replace('_', ' ', (string)($row['service_key'] ?? 'outro'))), $preQuoteDemandSummary ?? []);
$preQuoteDemandValues = array_map(static fn(array $row): int => (int)($row['total'] ?? 0), $preQuoteDemandSummary ?? []);

function admin_tempo_relativo(string $quando): string
{
    if ($quando === '') return '';
    $diffMin = (int)round((time() - strtotime($quando)) / 60);
    if ($diffMin < 1) return 'agora';
    if ($diffMin < 60) return $diffMin . ' min';
    if ($diffMin < 1440) return (int)round($diffMin / 60) . 'h';
    return (int)round($diffMin / 1440) . 'd';
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar admin-dashboard-topbar">
    <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" placeholder="Buscar por pedido, cliente, placa ou telefone" aria-label="Buscar no painel"></div>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span>Operação normal</span>
        <a href="<?php echo htmlspecialchars($bp); ?>/admin/central" class="ops-dashboard-link">Abrir Central</a>
        <a href="<?php echo $bp; ?>/admin/configuracoes" class="ops-dashboard-link"><i class="fas fa-gear me-1"></i>Configurações</a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo operacional">
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-list-check me-1"></i>Pedidos ativos</span>
        <strong class="ops-metric__value" data-dashboard-card="pedidos_ativos"><?php echo (int)($pedidosAtivosTotal ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-truck me-1"></i>Guinchos online</span>
        <strong class="ops-metric__value" data-dashboard-card="guinchos_ativos"><?php echo (int)($guinchoAtivos ?? 0); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-dollar-sign me-1"></i>Receita hoje</span>
        <strong class="ops-metric__value" data-dashboard-card="receita_hoje">R$<?php echo number_format((float)($receitaHoje ?? 0), 0, ',', '.'); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label"><i class="fas fa-clock me-1"></i>ETA médio</span>
        <strong class="ops-metric__value" data-dashboard-card="eta_medio_min"><?php echo $etaMedioMin !== null ? $etaMedioMin . ' min' : '—'; ?></strong>
    </article>
    <article class="ops-metric <?php echo (int)($alertasAbertos ?? 0) > 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label"><i class="fas fa-triangle-exclamation me-1"></i>Alertas abertos</span>
        <strong class="ops-metric__value" data-dashboard-card="alertas_abertos"><?php echo (int)($alertasAbertos ?? 0); ?></strong>
    </article>
</section>

<div class="shell-ops shell-ops--no-worklist" id="dashboardShell">

    <aside class="shell-ops-sidebar" id="dashboardSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-workspace" id="dashboardWorkspace" aria-label="Visão em tempo real da plataforma" style="padding:24px;">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Central operacional</span>
            <h1>Visão em tempo real da plataforma</h1>
            <p>Pedidos, frota, financeiro e qualidade — sem precisar recarregar a página.</p>
        </div>
    </header>

    <div class="lower-grid mb-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-column me-2"></i>Operação por hora
                <small class="text-muted d-block">Pedidos concluídos</small>
            </div>
            <div class="card-body">
                <?php if (empty($operacaoPorHora['labels'])): ?>
                    <div class="text-muted small">Sem pedidos concluídos nas últimas 24h.</div>
                <?php else: ?>
                    <canvas id="chartOperacaoPorHora" height="180"></canvas>
                <?php endif; ?>
            </div>
        </div>
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-coins me-2"></i>Financeiro e jobs
                <small class="text-muted d-block">Últimas 24 horas</small>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Pagamentos aprovados</span>
                    <strong class="text-success"><?php echo $financeiroJobs['pct_aprovados'] !== null ? number_format((float)$financeiroJobs['pct_aprovados'], 1, ',', '.') . '%' : '—'; ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span>Jobs PIX pendentes</span>
                    <strong><?php echo (int)$financeiroJobs['jobs_pendentes']; ?></strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span>Jobs em falha</span>
                    <strong class="<?php echo $financeiroJobs['jobs_falha'] > 0 ? 'text-danger' : ''; ?>"><?php echo (int)$financeiroJobs['jobs_falha']; ?></strong>
                </div>
            </div>
        </div>
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-vial-circle-check me-2"></i>QA de release
                <small class="text-muted d-block">Baseline atual</small>
            </div>
            <div class="card-body">
                <?php if (empty($qaRelease['disponivel'])): ?>
                    <div class="text-muted small">Nenhuma execução Playwright registrada ainda. <a href="<?php echo $bp; ?>/admin/simulador">Rodar simulador</a>.</div>
                <?php else: ?>
                    <div class="fs-2 fw-bold"><?php echo (int)$qaRelease['aprovados']; ?>/<?php echo (int)$qaRelease['total']; ?></div>
                    <?php if (!empty($qaRelease['falha_codigo'])): ?>
                        <span class="badge text-bg-danger"><?php echo htmlspecialchars((string)$qaRelease['falha_codigo']); ?> falhou</span>
                    <?php else: ?>
                        <span class="badge text-bg-success">Todos os checks passaram</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
        // §CELULAS-NITEROI-01 (04/08/2026): rótulo/badge do status de
        // expansão da célula — mesmo mapeamento usado em
        // precificacao_zonas.php (ops-badge--critical é a classe real do
        // design system, não existe "--danger").
        $statusExpansaoLabelDash = ['nao_ativada' => 'Não ativada', 'pedra_morta' => 'Pedra morta', 'pedra_viva' => 'Pedra viva'];
        $statusExpansaoBadgeDash = ['nao_ativada' => 'text-bg-secondary', 'pedra_morta' => 'text-bg-danger', 'pedra_viva' => 'text-bg-success'];
    ?>
    <div class="card mb-4" id="territorioMetasCard">
        <div class="card-header"><i class="fas fa-bullseye me-2"></i>Metas &amp; Território
            <small class="text-muted d-block">Selecione cidade e célula para ver mapa e indicadores ao vivo</small>
        </div>
        <div class="card-body">
            <?php if (empty($territorioPainel)): ?>
                <div class="text-muted small">Nenhuma célula territorial cadastrada.</div>
            <?php else: ?>
            <script<?php echo csp_script_nonce_attr(); ?>>
                window.__territorioPainel = <?php echo json_encode($territorioPainel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                window.__territorioCidades = <?php echo json_encode($cidadesTerritorio ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                window.__basePath = <?php echo json_encode($bp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                window.__osrmBaseUrl = <?php echo json_encode($osrmBaseUrl ?? '', JSON_UNESCAPED_SLASHES); ?>;
            </script>
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label" for="territorioCidade">Cidade</label>
                    <select class="form-select" id="territorioCidade"></select>
                </div>
                <div class="col-md-7">
                    <label class="form-label" for="territorioCelula">Célula/Zona</label>
                    <select class="form-select" id="territorioCelula"></select>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-xl-5">
                    <div class="border rounded overflow-hidden">
                        <div id="territorioMapa" style="height:360px;"></div>
                        <div id="territorioMapaSemPoligono" class="text-muted small p-3 d-none">Sem polígono desenhado ainda.</div>
                    </div>
                    <div id="territorioMapaOverlayInfo" class="text-muted small mt-1"></div>
                </div>
                <div class="col-xl-7">
                    <div id="territorioDetalhes"></div>
                    <?php $legendaMetaHint = 'Cor do gráfico indica o status em relação à meta: verde = bateu a meta, azul = superou a meta, amarelo = perto da meta (80% ou mais), vermelho = abaixo da meta. Passe o mouse sobre o gráfico para ver os números exatos.'; ?>
                    <div class="row g-3">
                        <div class="col-md-4"><div class="border rounded p-2 h-100 text-center">
                            <div class="small text-muted">Pedidos pagos vs meta <i class="fas fa-circle-info hint-icon" title="<?php echo htmlspecialchars($legendaMetaHint); ?>"></i></div>
                            <canvas id="territorioChartPedidos" height="150"></canvas>
                            <div id="territorioMetaPedidos" class="small"></div>
                        </div></div>
                        <div class="col-md-4"><div class="border rounded p-2 h-100 text-center">
                            <div class="small text-muted">Prestadores vs meta <i class="fas fa-circle-info hint-icon" title="<?php echo htmlspecialchars($legendaMetaHint); ?>"></i></div>
                            <canvas id="territorioChartPrestadores" height="150"></canvas>
                            <div id="territorioMetaPrestadores" class="small"></div>
                        </div></div>
                        <div class="col-md-4"><div class="border rounded p-2 h-100 text-center">
                            <div class="small text-muted">Composição financeira <i class="fas fa-circle-info hint-icon" title="Distribuição do valor bruto recebido nesta célula entre repasse ao prestador, comissão da plataforma e perdas por estorno. Passe o mouse para ver os valores."></i></div>
                            <canvas id="territorioChartFinanceiro" height="150"></canvas>
                            <div id="territorioMetaFinanceiro" class="small"></div>
                        </div></div>
                    </div>
                    <div class="small text-muted mt-2">
                        <span style="color:#22c55e">●</span> Na meta &nbsp;
                        <span style="color:#0d6efd">●</span> Superou a meta &nbsp;
                        <span style="color:#f59e0b">●</span> Perto da meta &nbsp;
                        <span style="color:#dc3545">●</span> Abaixo da meta
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo $bp; ?>/admin/precificacao/zonas" class="small d-inline-block mt-3">Gerenciar células e polígonos →</a>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-clipboard-question me-2"></i>As 7 perguntas do marketing
            <small class="text-muted d-block">Mês atual (<?php echo date('d/m', strtotime($inicioMes)); ?>–<?php echo date('d/m/Y'); ?>)</small>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="text-muted small">1–2. Onde e quanto gastamos</div>
                    <strong>R$ <?php echo number_format((float)$resumoEstrategico['gasto_marketing'], 2, ',', '.'); ?></strong>
                    <div class="text-muted small"><?php echo count($canaisEstrategico); ?> canal(is) ativo(s)</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">3. Pedidos pagos</div>
                    <strong><?php echo (int)$resumoEstrategico['pedidos_pagos']; ?></strong>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">4. Quanto recebemos</div>
                    <strong>R$ <?php echo number_format((float)$resumoEstrategico['bruto_aprovado'], 2, ',', '.'); ?></strong>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">5. Quanto repassamos</div>
                    <strong>R$ <?php echo number_format((float)$resumoEstrategico['repassado'], 2, ',', '.'); ?></strong>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">6. Quanto perdemos (estornos)</div>
                    <strong class="<?php echo (float)$resumoEstrategico['estornos'] > 0 ? 'text-danger' : ''; ?>">R$ <?php echo number_format((float)$resumoEstrategico['estornos'], 2, ',', '.'); ?></strong>
                </div>
                <div class="col-6 col-md-3">
                    <div class="text-muted small">7. Margem líquida (comissão - marketing - estornos - taxas)</div>
                    <strong class="<?php echo (float)$resumoEstrategico['margem_liquida'] >= 0 ? 'text-success' : 'text-danger'; ?>">R$ <?php echo number_format((float)$resumoEstrategico['margem_liquida'], 2, ',', '.'); ?></strong>
                </div>
            </div>
            <?php if (!empty($canaisEstrategico)): ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Célula (canal → onde anunciamos)</th><th class="text-end">Pedidos</th><th class="text-end">Bruto</th><th class="text-end">Gasto</th><th class="text-end">CAC</th><th class="text-end">Margem</th></tr></thead>
                    <tbody>
                    <?php foreach ($canaisEstrategico as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$c['canal']); ?></td>
                            <td class="text-end"><?php echo (int)$c['pedidos']; ?></td>
                            <td class="text-end">R$ <?php echo number_format((float)$c['bruto'], 2, ',', '.'); ?></td>
                            <td class="text-end">R$ <?php echo number_format((float)$c['gasto_marketing'], 2, ',', '.'); ?></td>
                            <td class="text-end">R$ <?php echo number_format((float)$c['cac'], 2, ',', '.'); ?></td>
                            <td class="text-end <?php echo (float)$c['margem'] >= 0 ? 'text-success' : 'text-danger'; ?>">R$ <?php echo number_format((float)$c['margem'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-muted small">Nenhum pedido pago com canal de aquisição registrado neste período.</div>
            <?php endif; ?>
            <a href="<?php echo $bp; ?>/admin/financeiro/visao-unificada" class="small">Ver detalhamento completo (qual célula gerou margem) em Financeiro →</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Demanda das pr&eacute;-cota&ccedil;&otilde;es</div>
                <div class="card-body"><canvas id="chartPreQuoteDemand" height="200"></canvas><p class="text-muted small mb-0 mt-2">Distribui&ccedil;&atilde;o agregada dos &uacute;ltimos 30 dias.</p></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Distribuição de Status</div>
                <div class="card-body">
                    <canvas id="chartStatus" height="200"></canvas>
                    <div class="d-grid gap-2 mt-3" data-dashboard-status-list>
                        <?php foreach ($pedidoStatusBreakdown as $row): ?>
                        <?php $status = (string)($row['status'] ?? ''); ?>
                        <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small"><?php echo htmlspecialchars($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status))); ?></span>
                            <span class="badge badge-<?php echo htmlspecialchars($status); ?>"><?php echo (int)($row['total'] ?? 0); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($pedidoStatusBreakdown)): ?>
                        <div class="text-muted small">Sem dados para distribuição de status.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-clock-rotate-left me-2"></i>Últimos Pedidos</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr><th>#</th><th>Cliente</th><th>Status</th><th>Data</th></tr>
                            </thead>
                            <tbody data-dashboard-last-orders>
                                <?php if (empty($ultPedidos)): ?>
                                <tr data-dashboard-empty-row>
                                    <td colspan="4" class="text-center p-3 text-muted small">Nenhum pedido recente encontrado.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($ultPedidos as $pedido): ?>
                                <?php $status = (string)($pedido['status'] ?? ''); ?>
                                <tr>
                                    <td><a href="<?php echo htmlspecialchars($bp . '/admin/pedido/' . (int)$pedido['id']); ?>">#<?php echo (int)$pedido['id']; ?></a></td>
                                    <td><?php echo htmlspecialchars((string)($pedido['cliente_nome'] ?? '—')); ?></td>
                                    <td><span class="badge badge-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status))); ?></span></td>
                                    <td><?php echo htmlspecialchars(date('d/m H:i', strtotime((string)($pedido['criado_em'] ?? 'now')))); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <div><i class="fas fa-bell me-2"></i>Alertas prioritários</div>
                    <small class="text-muted">Requerem ação</small>
                </div>
                <div class="card-body admin-alert-list" data-dashboard-alerts>
                    <?php if (empty($alertasPrioritarios)): ?>
                        <div class="text-muted small">Nenhum alerta aberto no momento.</div>
                    <?php else: foreach ($alertasPrioritarios as $alerta): ?>
                        <div class="admin-alert-item admin-alert-item--<?php echo htmlspecialchars((string)($alerta['nivel'] ?? 'aviso')); ?>">
                            <span class="admin-alert-dot"></span>
                            <div>
                                <strong><?php echo htmlspecialchars((string)($alerta['label'] ?? '')); ?></strong>
                                <p><?php echo htmlspecialchars((string)($alerta['info'] ?? '')); ?></p>
                            </div>
                            <small><?php echo htmlspecialchars(admin_tempo_relativo((string)($alerta['quando'] ?? ''))); ?></small>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-bolt me-2"></i>Insights Operacionais</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">Aguard. pagamento</span>
                            <strong data-dashboard-snapshot="aguardando_pagamento"><?php echo (int)($operationalSnapshot['aguardando_pagamento'] ?? 0); ?></strong>
                        </div>
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">Aguard. guincho</span>
                            <strong data-dashboard-snapshot="aguardando_guincho"><?php echo (int)($operationalSnapshot['aguardando_guincho'] ?? 0); ?></strong>
                        </div>
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">A caminho</span>
                            <strong data-dashboard-snapshot="a_caminho"><?php echo (int)($operationalSnapshot['a_caminho'] ?? 0); ?></strong>
                        </div>
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">No local</span>
                            <strong data-dashboard-snapshot="no_local"><?php echo (int)($operationalSnapshot['no_local'] ?? 0); ?></strong>
                        </div>
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">Em reboque</span>
                            <strong data-dashboard-snapshot="em_reboque"><?php echo (int)($operationalSnapshot['em_reboque'] ?? 0); ?></strong>
                        </div>
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">Concluídos hoje</span>
                            <strong class="text-success" data-dashboard-insight="concluidos_hoje"><?php echo (int)($dashboardInsights['concluidos_hoje'] ?? 0); ?></strong>
                        </div>
                        <div class="col-6 col-md-4 d-flex justify-content-between align-items-center border rounded px-3 py-2">
                            <span class="small">Cancelados hoje</span>
                            <strong class="text-danger" data-dashboard-insight="cancelados_hoje"><?php echo (int)($dashboardInsights['cancelados_hoje'] ?? 0); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </section>

</div>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops--no-worklist (a
// mesma arquitetura de grid das páginas de 3 colunas, só que sem a coluna
// de fila — não há uma entidade única aqui pra virar worklist clicável).
?>
<link rel="stylesheet" href="<?php echo $bp; ?>/public/assets/vendor/leaflet/leaflet.css">
<!-- §CELULAS-NITEROI-01 (04/08/2026): Leaflet aqui é usado pelo mapa de
     leitura de "Metas & Território" (admin-territorio-metas.js), que agora
     também sobrepõe guinchos/clientes/rotas DENTRO do polígono da célula
     selecionada (via /admin/dashboard/mapa-json, filtrado client-side). O
     "Mapa operacional ao vivo" completo (todos os guinchos da plataforma)
     continua em /admin/central — ver central_operacional.php. -->
<style>
.mapa-marker { display:flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; color:#fff; font-size:.75rem; box-shadow:0 4px 10px rgba(0,0,0,.35); }
.mapa-marker--cliente { background:#ef4444; }
.mapa-marker--cliente-concluido { background:#6c757d; }
.mapa-marker--guincho { background:#2fb34a; }
.mapa-marker--guincho-ativo { background:#f59e0b; }
.mapa-marker--guincho-reboque { background:#dc3545; }
</style>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo $bp; ?>/public/assets/vendor/leaflet/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script<?php echo csp_script_nonce_attr(); ?>>
document.addEventListener('DOMContentLoaded', function () {
    const textColor = getComputedStyle(document.body).getPropertyValue('--theme-text') || '#e8e8e8';
    const gridColor = 'rgba(255,255,255,.08)';
    const basePath = <?php echo json_encode($bp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const dashboardJsonUrl = `${basePath}/admin/dashboard/json`;
    let statusChart = null;
    let weeklyChart = null;
    let preQuoteDemandChart = null;

    const demandCanvas = document.getElementById('chartPreQuoteDemand');
    if (demandCanvas) {
        preQuoteDemandChart = new Chart(demandCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($preQuoteDemandLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                datasets: [{ data: <?php echo json_encode($preQuoteDemandValues); ?>, backgroundColor: ['#2fb34a','#0d6efd','#f59e0b','#8b5cf6','#ef4444','#14b8a6'], borderWidth: 0 }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { color: textColor } } } }
        });
    }

    const horaCanvas = document.getElementById('chartOperacaoPorHora');
    if (horaCanvas) {
        new Chart(horaCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($operacaoPorHora['labels'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                datasets: [{
                    label: 'Concluídos',
                    data: <?php echo json_encode($operacaoPorHora['valores'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    backgroundColor: '#2fb34a',
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: textColor }, grid: { color: gridColor } },
                    y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }
                }
            }
        });
    }

    const statusCanvas = document.getElementById('chartStatus');
    if (statusCanvas) {
        statusChart = new Chart(statusCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusChartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusChartValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    backgroundColor: ['#0d6efd', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#22c55e', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: textColor }
                    }
                }
            }
        });
    }

    const weeklyCanvas = document.getElementById('chartPedidosSemana');
    if (weeklyCanvas) {
        weeklyChart = new Chart(weeklyCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($serieLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                datasets: [{
                    label: 'Pedidos',
                    data: <?php echo json_encode($serieTotais, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,.16)',
                    fill: true,
                    tension: 0.35
                }, {
                    label: 'Concluídos',
                    data: <?php echo json_encode($serieConcluidos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34,197,94,.12)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { labels: { color: textColor } }
                },
                scales: {
                    x: { ticks: { color: textColor }, grid: { color: gridColor } },
                    y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: gridColor } }
                }
            }
        });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(value) {
        return `R$${Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`;
    }

    function updateCards(cards) {
        Object.entries(cards || {}).forEach(([key, value]) => {
            const node = document.querySelector(`[data-dashboard-card="${key}"]`);
            if (!node) return;
            if (key === 'receita_hoje') { node.textContent = formatMoney(value); return; }
            if (key === 'eta_medio_min') { node.textContent = (value === null || value === undefined) ? '—' : `${value} min`; return; }
            node.textContent = String(value ?? 0);
        });
    }

    function updateInsights(insights) {
        Object.entries(insights || {}).forEach(([key, value]) => {
            const node = document.querySelector(`[data-dashboard-insight="${key}"]`);
            if (node) node.textContent = String(value ?? 0);
        });
    }

    function updateSnapshot(snapshot) {
        Object.entries(snapshot || {}).forEach(([key, value]) => {
            const node = document.querySelector(`[data-dashboard-snapshot="${key}"]`);
            if (node) node.textContent = String(value ?? 0);
        });
    }

    function updateStatusList(items) {
        const container = document.querySelector('[data-dashboard-status-list]');
        if (!container) return;
        if (!Array.isArray(items) || items.length === 0) {
            container.innerHTML = '<div class="text-muted small">Sem dados para distribuição de status.</div>';
            return;
        }
        container.innerHTML = items.map((item) => `
            <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                <span class="small">${escapeHtml(item.label || '')}</span>
                <span class="badge badge-${escapeHtml(item.status || '')}">${Number(item.total || 0)}</span>
            </div>
        `).join('');
    }

    function updateLastOrders(orders) {
        const tbody = document.querySelector('[data-dashboard-last-orders]');
        if (!tbody) return;
        if (!Array.isArray(orders) || orders.length === 0) {
            tbody.innerHTML = '<tr data-dashboard-empty-row><td colspan="4" class="text-center p-3 text-muted small">Nenhum pedido recente encontrado.</td></tr>';
            return;
        }
        tbody.innerHTML = orders.map((item) => `
            <tr>
                <td><a href="${basePath}/admin/pedido/${Number(item.id || 0)}">#${Number(item.id || 0)}</a></td>
                <td>${escapeHtml(item.cliente_nome || '—')}</td>
                <td><span class="badge badge-${escapeHtml(item.status || '')}">${escapeHtml(item.status_label || '')}</span></td>
                <td>${escapeHtml(item.data_label || '')}</td>
            </tr>
        `).join('');
    }

    function tempoRelativo(quando) {
        if (!quando) return '';
        const diffMin = Math.round((Date.now() - new Date(quando.replace(' ', 'T')).getTime()) / 60000);
        if (diffMin < 1) return 'agora';
        if (diffMin < 60) return `${diffMin} min`;
        if (diffMin < 1440) return `${Math.round(diffMin / 60)}h`;
        return `${Math.round(diffMin / 1440)}d`;
    }

    function updateAlerts(alertas) {
        const container = document.querySelector('[data-dashboard-alerts]');
        if (!container) return;
        if (!Array.isArray(alertas) || alertas.length === 0) {
            container.innerHTML = '<div class="text-muted small">Nenhum alerta aberto no momento.</div>';
            return;
        }
        container.innerHTML = alertas.map((a) => `
            <div class="admin-alert-item admin-alert-item--${escapeHtml(a.nivel || 'aviso')}">
                <span class="admin-alert-dot"></span>
                <div>
                    <strong>${escapeHtml(a.label || '')}</strong>
                    <p>${escapeHtml(a.info || '')}</p>
                </div>
                <small>${escapeHtml(tempoRelativo(a.quando))}</small>
            </div>
        `).join('');
    }

    function updateCharts(payload) {
        const statusBreakdown = Array.isArray(payload.status_breakdown) ? payload.status_breakdown : [];
        const serie = Array.isArray(payload.serie) ? payload.serie : [];

        if (statusChart) {
            statusChart.data.labels = statusBreakdown.map((item) => item.label || '');
            statusChart.data.datasets[0].data = statusBreakdown.map((item) => Number(item.total || 0));
            statusChart.update();
        }

        if (weeklyChart) {
            weeklyChart.data.labels = serie.map((item) => item.label || '');
            weeklyChart.data.datasets[0].data = serie.map((item) => Number(item.total || 0));
            weeklyChart.data.datasets[1].data = serie.map((item) => Number(item.concluidos || 0));
            weeklyChart.update();
        }
        if (preQuoteDemandChart && payload.pre_quote_demand_summary) {
            preQuoteDemandChart.data.labels = payload.pre_quote_demand_summary.map(item => String(item.service_key || 'outro').replaceAll('_', ' '));
            preQuoteDemandChart.data.datasets[0].data = payload.pre_quote_demand_summary.map(item => Number(item.total || 0));
            preQuoteDemandChart.update();
        }
    }

    async function syncDashboard() {
        try {
            const response = await fetch(dashboardJsonUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (!payload || payload.ok !== true) return;
            updateCards(payload.cards || {});
            updateInsights(payload.insights || {});
            updateSnapshot(payload.operational_snapshot || {});
            updateStatusList(payload.status_breakdown || []);
            updateLastOrders(payload.ultimos_pedidos || []);
            updateAlerts(payload.alertas || []);
            updateCharts(payload);
            // §CELULAS-NITEROI-01 (04/08/2026): "Metas & Território" ao vivo
            // — reusa o mesmo poll de 15s do resto do dashboard, sem criar
            // um fetch/timer separado. window.AdminTerritorioMetas só existe
            // se o card renderizou (há pelo menos 1 célula cadastrada).
            if (payload.territorio_painel && window.AdminTerritorioMetas) {
                window.AdminTerritorioMetas.updateData(payload.territorio_painel);
            }
        } catch (error) {
            console.warn('Falha ao sincronizar dashboard admin:', error);
        }
    }

    window.setInterval(syncDashboard, 15000);
});
</script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-territorio-metas.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
