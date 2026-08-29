<?php
$bp = $bp ?? (defined('BASE_PATH') ? BASE_PATH : '');
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($bp) ?>/public/assets/css/themes/especialista.css">
<div class="shell especialista-shell">
    <?php include __DIR__ . '/../layouts/sidebar_especialista.php'; ?>
    <main class="main-content especialista-dashboard-main">
        <div class="container-fluid py-4">
            <header class="page-head mb-4">
                <div>
                    <span class="eyebrow">Catálogo operacional</span>
                    <h1 class="h3 mb-2">Serviços habilitados</h1>
                    <p class="text-muted mb-0">Os preços são definidos e atualizados exclusivamente pela GuinchaFácil. Você apenas escolhe os serviços que está habilitado a executar.</p>
                </div>
            </header>
            <div class="alert alert-info mb-4"><i class="fas fa-shield-halved me-2"></i>Não é permitido alterar preços, fazer leilão ou cobrar peças diretamente do cliente. A venda de peças está desativada nesta fase.</div>
            <div class="row g-3">
                <?php foreach (($servicos ?? []) as $s): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card h-100">
                        <div class="card-body">
                            <span class="badge text-bg-warning mb-2"><?= htmlspecialchars((string)$s['categoria']) ?></span>
                            <h2 class="h5"><?= htmlspecialchars((string)$s['nome']) ?></h2>
                            <div class="small text-muted mb-3">Valor tabelado ao cliente: R$ <?= number_format((float)$s['preco_atendimento'], 2, ',', '.') ?><?php if ((float)$s['preco_adicional'] > 0): ?> · execução: R$ <?= number_format((float)$s['preco_adicional'], 2, ',', '.') ?><?php endif; ?></div>
                            <div class="small"><i class="fas fa-circle-check me-1 text-success"></i>Repasse calculado automaticamente antes do aceite.</div>
                        </div>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
