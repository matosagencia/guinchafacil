<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$comissao         = ($comissaoPercent ?? 15) / 100;   // vem do controller
$receitaBruta     = (float)($totais['total_recebido'] ?? 0);
$comissaoPlat     = round($receitaBruta * $comissao, 2);
$receitaLiquida   = $receitaBruta - $comissaoPlat;
$totalAtendimentos = (int)($totais['total_corridas'] ?? 0);
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_guincho.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-coins me-2 text-primary-custom"></i>Extrato Financeiro</div>
        </div>
        <!-- Filtro de mês/ano -->
        <form method="GET" action="" class="d-flex gap-2 align-items-center">
            <select name="mes" class="form-select form-select-sm" style="width:auto">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?php echo $m; ?>" <?php echo ($mes ?? (int)date('m')) == $m ? 'selected' : ''; ?>>
                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                </option>
                <?php endfor; ?>
            </select>
            <select name="ano" class="form-select form-select-sm" style="width:auto">
                <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 3; $a--): ?>
                <option value="<?php echo $a; ?>" <?php echo ($ano ?? (int)date('Y')) == $a ? 'selected' : ''; ?>>
                    <?php echo $a; ?>
                </option>
                <?php endfor; ?>
            </select>
            <button class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-value">R$<?php echo number_format($receitaBruta, 0, ',', '.'); ?></div>
                <div class="stat-label">Receita Bruta</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-value">R$<?php echo number_format($receitaLiquida, 0, ',', '.'); ?></div>
                <div class="stat-label">Receita Líquida</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                <div class="stat-value">R$<?php echo number_format($comissaoPlat, 0, ',', '.'); ?></div>
                <div class="stat-label">Comissão Plataforma</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-value"><?php echo $totalAtendimentos; ?></div>
                <div class="stat-label">Atendimentos</div>
            </div>
        </div>
    </div>

    <!-- Chave Pix do guincho -->
    <?php if (!empty($guincho['chave_pix'])): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="fas fa-qrcode fa-lg"></i>
        <div>
            <strong>Sua chave PIX:</strong>
            <?php echo htmlspecialchars($guincho['chave_pix']); ?>
            <span class="badge bg-secondary ms-1"><?php echo htmlspecialchars($guincho['chave_pix_tipo']); ?></span>
        </div>
        <a href="<?php echo $bp; ?>/guincho/perfil" class="btn btn-sm btn-outline-success ms-auto">Editar</a>
    </div>
    <?php endif; ?>

    <!-- Extrato detalhado -->
    <div class="card">
        <div class="card-header"><i class="fas fa-table me-2"></i>Extrato Detalhado</div>
        <div class="card-body p-0">
            <?php if (!empty($pagamentos)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Origem → Destino</th>
                            <th class="text-end">Valor Bruto</th>
                            <th class="text-end">Comissão</th>
                            <th class="text-end text-success">Líquido</th>
                            <th class="text-center">PIX</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pagamentos as $pg):
                        $bruto     = (float)($pg['valor_total'] ?? 0);
                        $liquido   = (float)($pg['valor_guincho'] ?? 0);
                        $comissao  = round($bruto - $liquido, 2);
                        $pago      = (bool)($pg['pago_guincho'] ?? false);
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($pg['criado_em'])); ?></td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <span class="text-muted small">
                                <?php echo htmlspecialchars(substr($pg['endereco_origem'] ?? '-', 0, 40)); ?>
                                → <?php echo htmlspecialchars(substr($pg['endereco_destino'] ?? '-', 0, 40)); ?>
                            </span>
                        </td>
                        <td class="text-end">R$ <?php echo number_format($bruto, 2, ',', '.'); ?></td>
                        <td class="text-end text-danger">- R$ <?php echo number_format($comissao, 2, ',', '.'); ?></td>
                        <td class="text-end fw-bold text-success">R$ <?php echo number_format($liquido, 2, ',', '.'); ?></td>
                        <td class="text-center">
                            <?php if ($pago): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Pago</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendente</span>
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
                Nenhum pagamento encontrado para o período selecionado.
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
