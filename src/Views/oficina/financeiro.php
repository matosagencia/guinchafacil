<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Marco',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
?>
<link rel="stylesheet" href="<?= $bp ?>/public/assets/css/themes/oficina.css">
<script>document.body.classList.add('theme-oficina');</script>
<div class="main-wrapper" style="display:flex;min-height:100vh">
    <?php include __DIR__ . '/../layouts/sidebar_oficina.php'; ?>
    <main class="main-content" style="flex:1;padding:1.5rem">
        <h1 style="font-size:1.4rem;font-weight:700;color:var(--ofi-primary-darker);margin-bottom:1rem"><i class="fas fa-coins me-2"></i>Financeiro</h1>
        <form method="GET" class="ofi-card mb-3 d-flex gap-2 align-items-end">
            <div><label class="form-label small mb-1">Mes</label>
                <select name="mes" class="form-select form-select-sm">
                    <?php foreach ($meses as $k=>$v): ?><option value="<?= $k ?>" <?= $k === $mes ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label class="form-label small mb-1">Ano</label><input type="number" name="ano" class="form-control form-control-sm" value="<?= $ano ?>" style="width:100px"></div>
            <button class="btn-ofi-outline">Filtrar</button>
        </form>
        <div class="ofi-stats">
            <div class="ofi-stat"><div class="ofi-stat-value">R$ <?= number_format($totais['bruto'], 2, ',', '.') ?></div><div class="ofi-stat-label">Bruto</div></div>
            <div class="ofi-stat"><div class="ofi-stat-value" style="color:#991B1B">-R$ <?= number_format($totais['taxa'], 2, ',', '.') ?></div><div class="ofi-stat-label">Taxa</div></div>
            <div class="ofi-stat"><div class="ofi-stat-value" style="color:#166534">R$ <?= number_format($totais['liquido'], 2, ',', '.') ?></div><div class="ofi-stat-label">Liquido</div></div>
            <div class="ofi-stat"><div class="ofi-stat-value" style="color:#B45309">R$ <?= number_format($totais['a_receber'], 2, ',', '.') ?></div><div class="ofi-stat-label">A receber</div></div>
        </div>
        <div class="ofi-card">
            <div class="ofi-card-title mb-3">Repasses do mes</div>
            <?php if (empty($repasses)): ?>
                <p class="text-center py-4" style="color:#7C6B5C"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>Nenhum repasse neste mes.</p>
            <?php else: ?>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Data</th><th>Pedido</th><th class="text-end">Bruto</th><th class="text-end">Taxa</th><th class="text-end">Liquido</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($repasses as $r): $badge = ['pendente'=>'ofi-badge--info','pago'=>'ofi-badge--online','estornado'=>'ofi-badge--offline'][$r['status']] ?? 'ofi-badge--info'; ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($r['criado_em'])) ?></td>
                            <td>#<?= (int)($r['pedido_id'] ?? 0) ?></td>
                            <td class="text-end">R$ <?= number_format((float)$r['valor_bruto'], 2, ',', '.') ?></td>
                            <td class="text-end" style="color:#991B1B">-R$ <?= number_format((float)$r['taxa_plataforma'], 2, ',', '.') ?></td>
                            <td class="text-end"><strong>R$ <?= number_format((float)$r['valor_liquido'], 2, ',', '.') ?></strong></td>
                            <td><span class="ofi-badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>