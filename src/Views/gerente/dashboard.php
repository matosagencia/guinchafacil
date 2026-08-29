<?php $bp = defined('BASE_PATH') ? BASE_PATH : ''; include __DIR__ . '/../layouts/header.php'; ?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_gerente.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gerência</span>
            <h1><i class="fas fa-clipboard-check me-2 text-primary-custom"></i>Painel do gerente</h1>
            <p>Aprove ou rejeite demandas criadas pelos funcionários. Você nunca decide uma demanda que você mesmo criou, e demandas de maior risco exigem um segundo gerente diferente de você.</p>
        </div>
    </header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-value"><?php echo (int)($resumo['pendente'] ?? 0); ?></div>
                <div class="stat-label">Pendentes (1ª decisão)</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon text-info"><i class="fas fa-user-clock"></i></div>
                <div class="stat-value"><?php echo (int)($resumo['aprovada_parcial'] ?? 0); ?></div>
                <div class="stat-label">Aguardando 2ª aprovação</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stat-value"><?php echo (int)$antigas; ?></div>
                <div class="stat-label">Pendentes há +24h</div>
            </div>
        </div>
    </div>

    <a href="<?php echo $bp; ?>/gerente/demandas" class="btn btn-primary"><i class="fas fa-list-check me-2"></i>Ver fila de demandas</a>
</main>
</div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
