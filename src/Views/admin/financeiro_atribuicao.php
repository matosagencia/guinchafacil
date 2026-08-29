<?php
/**
 * Financeiro — visão unificada de atribuição/receita/margem.
 * Reestruturada pro padrão shell-ops (mesma arquitetura de
 * /admin/planejamento): é um relatório/dashboard, não uma listagem de
 * entidades pra virar worklist clicável, por isso usa
 * .shell-ops--no-worklist (sidebar + workspace único, sem coluna do meio).
 * A versão anterior desta view não incluía a sidebar de navegação nem o
 * wrapper .shell-ops — ficava "solta" dentro de um .container-fluid, fora
 * do padrão do resto do admin (correção pedida pelo usuário em 03/08/2026).
 *
 * @var array $resumo
 * @var array $porCanal
 * @var array $celulas
 * @var array $gastos
 * @var string $csrfToken
 */
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$money = static fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-central-operacional.css">

<div class="ops-topbar" style="padding:10px 24px;border-bottom:1px solid var(--theme-border,#232c35);background:var(--theme-nav,#030405)">
    <form class="ops-topbar__search" method="get">
        <i class="fas fa-magnifying-glass"></i>
        <input type="date" name="inicio" value="<?php echo htmlspecialchars($resumo['desde']); ?>">
        <span style="color:var(--theme-muted);padding:0 6px;">até</span>
        <input type="date" name="fim" value="<?php echo htmlspecialchars($resumo['ate']); ?>">
        <button class="btn btn-sm btn-primary ms-2">Aplicar</button>
    </form>
    <div class="ops-topbar__meta">
        <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span>Fechamento financeiro e atribuição</span>
        <a href="<?php echo $bp; ?>/admin/financeiro/visao-unificada/csv?inicio=<?php echo urlencode($resumo['desde']); ?>&fim=<?php echo urlencode($resumo['ate']); ?>" class="ops-dashboard-link">
            <i class="fas fa-file-csv me-1"></i>Exportar CSV
        </a>
    </div>
</div>

<section class="ops-summary" aria-label="Resumo financeiro do período">
    <article class="ops-metric">
        <span class="ops-metric__label">Pedidos pagos</span>
        <strong class="ops-metric__value"><?php echo (int)$resumo['pedidos_pagos']; ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Bruto aprovado</span>
        <strong class="ops-metric__value"><?php echo $money($resumo['bruto_aprovado']); ?></strong>
    </article>
    <article class="ops-metric">
        <span class="ops-metric__label">Comissão plataforma</span>
        <strong class="ops-metric__value"><?php echo $money($resumo['comissao_plataforma']); ?></strong>
    </article>
    <article class="ops-metric <?php echo $resumo['margem_liquida'] < 0 ? 'is-danger' : ''; ?>">
        <span class="ops-metric__label">Margem líquida</span>
        <strong class="ops-metric__value <?php echo $resumo['margem_liquida'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($resumo['margem_liquida']); ?></strong>
    </article>
</section>

<div class="shell-ops shell-ops--no-worklist" id="financeiroAtribuicaoShell">

    <aside class="shell-ops-sidebar" id="financeiroAtribuicaoSidebar">
        <?php include __DIR__ . '/../components/admin_nav_operacional.php'; ?>
    </aside>

    <section class="shell-ops-workspace" id="financeiroAtribuicaoWorkspace" aria-label="Receita e margem" style="padding:24px;">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gestão</span>
            <h1><i class="fas fa-chart-line me-2 text-primary-custom"></i>Receita e margem</h1>
            <p>Reconciliação de gateway, repasses, perdas, campanhas e células operacionais.</p>
        </div>
    </header>

    <?php if (isset($_GET['erro'])): ?>
        <div class="alert alert-danger mb-3">Não foi possível processar o lançamento.</div>
    <?php elseif (isset($_GET['ok'])): ?>
        <div class="alert alert-success mb-3">Operação registrada.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><strong>Fechamento</strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7">Líquido confirmado gateway</dt>
                        <dd class="col-5 text-end"><?php echo $resumo['liquido_gateway'] === null ? 'Não liquidado' : $money($resumo['liquido_gateway']); ?></dd>

                        <dt class="col-7">Taxas gateway</dt>
                        <dd class="col-5 text-end"><?php echo $money($resumo['taxas_gateway']); ?></dd>

                        <dt class="col-7">Crédito guincho</dt>
                        <dd class="col-5 text-end"><?php echo $money($resumo['credito_guincho']); ?></dd>

                        <dt class="col-7">Repasse concluído</dt>
                        <dd class="col-5 text-end"><?php echo $money($resumo['repassado']); ?></dd>

                        <dt class="col-7">Estornos/perdas</dt>
                        <dd class="col-5 text-end text-danger"><?php echo $money($resumo['estornos']); ?></dd>

                        <dt class="col-7">Marketing</dt>
                        <dd class="col-5 text-end"><?php echo $money($resumo['gasto_marketing']); ?></dd>
                    </dl>
                    <div class="small text-muted mt-3">
                        Liquidações confirmadas: <?php echo (int)$resumo['liquidacoes_confirmadas']; ?>.
                        Pagamento aprovado, saldo, saque e repasse permanecem estados separados.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><strong>Lançar gasto de marketing</strong></div>
                <div class="card-body">
                    <form method="post" action="<?php echo $bp; ?>/admin/financeiro/marketing/gasto">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label">Data</label>
                                <input type="date" name="data" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Canal</label>
                                <select name="canal" class="form-select">
                                    <option>google_ads</option>
                                    <option>meta_ads</option>
                                    <option>whatsapp</option>
                                    <option>indicacao</option>
                                    <option>organico</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Valor</label>
                                <input name="valor_gasto" class="form-control" inputmode="decimal" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Campanha</label>
                                <input name="campanha" class="form-control">
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3">Registrar gasto</button>
                    </form>
                    <hr>
                    <form method="post" action="<?php echo $bp; ?>/admin/financeiro/marketing/importar" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <label class="form-label">Importar CSV (data;campanha;canal;valor_gasto)</label>
                        <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required>
                        <button class="btn btn-outline-secondary mt-2">Importar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><strong>Margem por canal</strong></div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Canal</th><th>Pedidos</th><th>Bruto</th><th>Comissão</th><th>Gasto</th><th>CAC</th><th>Margem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($porCanal as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['canal']); ?></td>
                        <td><?php echo (int)$r['pedidos']; ?></td>
                        <td><?php echo $money($r['bruto']); ?></td>
                        <td><?php echo $money($r['comissao']); ?></td>
                        <td><?php echo $money($r['gasto_marketing']); ?></td>
                        <td><?php echo $money($r['cac']); ?></td>
                        <td class="<?php echo $r['margem'] < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo $money($r['margem']); ?></td>
                    </tr>
                    <?php endforeach; if (!$porCanal): ?>
                    <tr><td colspan="7" class="text-muted">Nenhum pedido pago no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><strong>Células: canal × cidade × serviço × categoria</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Canal</th><th>Cidade</th><th>Serviço</th><th>Categoria</th><th>Pedidos</th><th>Comissão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($celulas as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['canal']); ?></td>
                        <td><?php echo htmlspecialchars($r['cidade']); ?></td>
                        <td><?php echo htmlspecialchars($r['servico']); ?></td>
                        <td><?php echo htmlspecialchars($r['categoria']); ?></td>
                        <td><?php echo (int)$r['pedidos']; ?></td>
                        <td><?php echo $money($r['comissao']); ?></td>
                    </tr>
                    <?php endforeach; if (!$celulas): ?>
                    <tr><td colspan="6" class="text-muted">Nenhuma célula no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-3 mb-3">
        <div class="card-header"><strong>Gastos lançados</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Data</th><th>Canal</th><th>Campanha</th><th>Valor</th><th>Origem</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gastos as $g): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($g['data']); ?></td>
                        <td><?php echo htmlspecialchars($g['canal']); ?></td>
                        <td><?php echo htmlspecialchars($g['campanha']); ?></td>
                        <td><?php echo $money($g['valor_gasto']); ?></td>
                        <td><?php echo htmlspecialchars($g['origem_lancamento']); ?></td>
                        <td>
                            <form method="post" action="<?php echo $bp; ?>/admin/financeiro/marketing/excluir" onsubmit="return confirm('Excluir este lançamento?')">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Excluir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; if (!$gastos): ?>
                    <tr><td colspan="6" class="text-muted">Nenhum gasto lançado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    </section>

</div>

<?php
// Não usa layouts/footer.php: esta página usa .shell-ops--no-worklist,
// mesma arquitetura de grid do Dashboard/Planejamento — é um relatório,
// não uma listagem de entidades pra virar worklist clicável.
?>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp); ?>/public/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
