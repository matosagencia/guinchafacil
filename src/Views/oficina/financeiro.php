<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totais          = $totais ?? [];
$pagamentos      = $pagamentos ?? [];
$systemMode      = $systemMode ?? 'production';
$oficinaCompleta = $oficinaCompleta ?? [];
$mes = $mes ?? (int)date('m');
$ano = $ano ?? (int)date('Y');

$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Marco',4=>'Abril',5=>'Maio',6=>'Junho',
          7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];

$money = static fn($v): string => 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/pages/tow-financeiro.css">
<script>document.body.classList.add('theme-oficina');</script>

<div class="main-wrapper" style="display:flex;min-height:100vh">
<?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
<main class="main-content" style="flex:1;padding:1.5rem">
    <div class="fin-shell">
        <header class="page-head mb-4">
            <div>
                <span class="eyebrow">Financeiro</span>
                <h1><i class="fas fa-coins me-2 text-primary-custom"></i>Financeiro</h1>
                <p>Receita por servico, taxas da plataforma, repasses concluidos e pendentes.</p>
            </div>
            <form method="GET" action="" class="d-flex gap-2 align-items-center">
                <select name="mes" class="form-select form-select-sm fin-select-auto">
                    <?php foreach ($meses as $m => $nome): ?>
                    <option value="<?= $m ?>" <?= $mes === $m ? 'selected' : '' ?>><?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="ano" class="form-select form-select-sm fin-select-auto">
                    <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 3; $a--): ?>
                    <option value="<?= $a ?>" <?= $ano === $a ? 'selected' : '' ?>><?= $a ?></option>
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
                        <span class="fin-pill"><i class="fas fa-ban text-danger"></i>Cancelamento e retencao</span>
                    </div>
                    <h2 class="mb-2 fin-title">Extrato operacional da oficina.</h2>
                    <p class="mb-0 text-muted">Cada linha mostra o que foi concluido, cancelado, estornado e o que ja entrou ou ainda aguarda repasse para voce.</p>
                </div>
                <div class="col-lg-4">
                    <div class="history-item">
                        <div class="small text-muted mb-1">Chave PIX cadastrada</div>
                        <strong><?= htmlspecialchars((string)($oficinaCompleta['pix_chave'] ?? 'Nao informada')) ?></strong>
                        <div class="small text-muted mt-2">Modo operacional: <?= htmlspecialchars($systemMode) ?></div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($systemMode === 'freeflow'): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-circle-info me-2"></i>No ambiente <strong>freeflow</strong>, os pagamentos locais podem ser marcados diretamente como aprovados/concluidos sem fila real de PIX.
        </div>
        <?php endif; ?>

        <div class="alert alert-light border mb-4">
            <strong>Como ler este extrato:</strong>
            <span class="d-block small text-muted mt-1">
                <strong>Repasse recebido</strong> = ja caiu para voce.
                <strong>Repasse pendente</strong> = aprovado, mas ainda nao confirmado.
                <strong>Valor estornado</strong> = atendimento cancelado e devolvido ao cliente.
                <strong>Retencao em cancelamentos</strong> = valor retido pela regra de cancelamento tardio.
            </span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-up"></i></div><div class="stat-value"><?= $money($totais['valor_bruto'] ?? 0) ?></div><div class="stat-label">Bruto Aprovado</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-wallet"></i></div><div class="stat-value"><?= $money($totais['valor_liquido'] ?? 0) ?></div><div class="stat-label">Liquido Apurado</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?= $money($totais['valor_pago_oficina'] ?? 0) ?></div><div class="stat-label">Repasse Recebido</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div><div class="stat-value"><?= $money($totais['valor_pendente_oficina'] ?? 0) ?></div><div class="stat-label">Repasse Pendente</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-rotate-left"></i></div><div class="stat-value"><?= $money($totais['valor_estornado'] ?? 0) ?></div><div class="stat-label">Valor Estornado</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-scissors"></i></div><div class="stat-value"><?= $money($totais['taxa_retida_cancelamento'] ?? 0) ?></div><div class="stat-label">Retencao em Cancelamentos</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-flag-checkered"></i></div><div class="stat-value"><?= (int)($totais['concluidos'] ?? 0) ?></div><div class="stat-label">Concluidos</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-icon"><i class="fas fa-ban"></i></div><div class="stat-value"><?= (int)($totais['cancelados'] ?? 0) ?></div><div class="stat-label">Cancelados</div></div></div>
        </div>

        <section class="fin-card p-4 p-lg-5">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h3 class="mb-1 fin-subtitle"><i class="fas fa-table me-2 text-primary-custom"></i>Extrato detalhado por atendimento</h3>
                    <p class="mb-0 text-muted">Servico, cliente, repasse, estorno e retencao do cancelamento no mesmo lugar.</p>
                </div>
            </div>

            <?php if (!empty($pagamentos)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr>
                        <th>Data</th><th>Pedido</th><th>Cliente</th><th>Status</th>
                        <th class="text-end">Bruto</th><th class="text-end">Taxa</th>
                        <th class="text-end">Liquido</th><th class="text-end">Retencao</th>
                        <th class="text-center">Repasse</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pagamentos as $pg): ?>
                    <?php
                    $bruto = (float)($pg['valor_bruto'] ?? 0);
                    $taxa  = (float)($pg['taxa_plataforma'] ?? 0);
                    $liquido = (float)($pg['valor_liquido'] ?? 0);
                    $retencao = (float)($pg['taxa_cancelamento'] ?? 0);
                    $pedidoStatus = (string)($pg['pedido_status'] ?? '');
                    $status = (string)($pg['status'] ?? '');
                    $repasseLabel = 'Sem repasse';
                    $repasseBadge = 'secondary';
                    if ($pedidoStatus === 'cancelado') {
                        $repasseLabel = $retencao > 0 ? 'Cancelado com retencao' : 'Cancelado sem retencao';
                        $repasseBadge = $retencao > 0 ? 'warning text-dark' : 'secondary';
                    } elseif ($status === 'estornado') { $repasseLabel = 'Estornado'; $repasseBadge = 'danger'; }
                    elseif ($status === 'pago') { $repasseLabel = 'Pago'; $repasseBadge = 'success'; }
                    elseif ($status === 'pendente') { $repasseLabel = 'Pendente'; $repasseBadge = 'warning text-dark'; }
                    ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime((string)($pg['criado_em'] ?? 'now'))) ?></td>
                        <td><a href="<?= htmlspecialchars($bp . '/oficina/pedido/' . (int)($pg['pedido_id'] ?? 0)) ?>">#<?= (int)($pg['pedido_id'] ?? 0) ?></a><div class="small text-muted"><?= htmlspecialchars((string)($pg['tipo_problema'] ?? '')) ?></div></td>
                        <td><?= htmlspecialchars((string)($pg['cliente_nome'] ?? 'â€”')) ?><div class="small text-muted"><?= htmlspecialchars((string)($pg['endereco_origem'] ?? '')) ?></div></td>
                        <td><span class="badge bg-<?= $pedidoStatus === 'concluido' ? 'success' : ($pedidoStatus === 'cancelado' ? 'danger' : 'secondary') ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pedidoStatus))) ?></span>
                            <?php if (!empty($pg['motivo_cancelamento'])): ?><div class="small text-muted mt-1"><?= htmlspecialchars((string)$pg['motivo_cancelamento']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-end"><?= $money($bruto) ?></td>
                        <td class="text-end text-danger"><?= $money($taxa) ?></td>
                        <td class="text-end fw-bold text-success"><?= $money($liquido) ?></td>
                        <td class="text-end"><?= $retencao > 0 ? $money($retencao) : 'â€”' ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $repasseBadge ?>"><?= htmlspecialchars($repasseLabel) ?></span>
                            <?php if (!empty($pg['pix_enviado_em'])): ?><div class="small text-muted mt-1"><?= date('d/m H:i', strtotime((string)$pg['pix_enviado_em'])) ?></div><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted"><i class="fas fa-coins fa-2x mb-2 d-block"></i>Nenhum registro financeiro encontrado para o periodo selecionado.</div>
            <?php endif; ?>
        </section>
    </div>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php';