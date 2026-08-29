<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-health.css">
<?php
$statusOk = ($run['status'] ?? '') === 'completed';
$engine = (string)($run['engine'] ?? 'php_internal');
$artifactsByStep = [];
foreach (($artifacts ?? []) as $artifactRow) {
    $stepKey = isset($artifactRow['step_id']) ? (string)$artifactRow['step_id'] : '';
    if ($stepKey === '' || $stepKey === '0') {
        continue;
    }
    $artifactsByStep[$stepKey][] = $artifactRow;
}
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>
                <?php if ($statusOk): ?>
                    <i class="fas fa-circle-check me-2 text-success"></i>Execução Concluída
                <?php else: ?>
                    <i class="fas fa-circle-xmark me-2 text-danger"></i>Execução com Falha
                <?php endif; ?>
            </h1>
            <p>run_id: <code><?php echo htmlspecialchars((string)$run['run_id']); ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <?php if (in_array(($run['status'] ?? ''), ['queued', 'running'], true)): ?>
            <form method="POST" action="<?php echo $bp; ?>/admin/qa/run/cancel/<?php echo htmlspecialchars((string)$run['run_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(AuthService::gerarCsrfToken()); ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-stop me-1"></i>Cancelar
                </button>
            </form>
            <?php endif; ?>
            <a href="<?php echo $bp; ?>/admin/simulador" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </header>

    <?php if (!empty($runWarnings)): ?>
    <div class="alert alert-warning mb-4">
        <div class="fw-semibold mb-2"><i class="fas fa-triangle-exclamation me-2"></i>Execução carregada com ressalvas</div>
        <?php foreach ($runWarnings as $warning): ?>
            <div class="small"><?php echo htmlspecialchars((string)$warning); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-list-check"></i></div>
                <div class="stat-value"><?php echo (int)$run['total_fases']; ?></div>
                <div class="stat-label">Total de Fases</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon--ok"><i class="fas fa-circle-check"></i></div>
                <div class="stat-value stat-value--ok"><?php echo (int)$run['fases_ok']; ?></div>
                <div class="stat-label">Fases OK</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon stat-icon--erro"><i class="fas fa-circle-xmark"></i></div>
                <div class="stat-value stat-value--erro"><?php echo (int)$run['fases_erro']; ?></div>
                <div class="stat-label">Fases com Erro</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $run['duracao_ms'] !== null ? (int)$run['duracao_ms'] : '—'; ?></div>
                <div class="stat-label">Duração (ms)</div>
            </div>
        </div>
    </div>

    <!-- Metadados -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-sm-4"><strong>Engine:</strong> <span class="badge bg-dark"><?php echo htmlspecialchars($engine); ?></span></div>
                <div class="col-sm-4"><strong>Suite:</strong> <?php echo htmlspecialchars((string)($run['suite'] ?? 'full')); ?></div>
                <div class="col-sm-4"><strong>Status:</strong> <?php echo htmlspecialchars((string)($run['status'] ?? 'unknown')); ?></div>
                <div class="col-sm-4"><strong>Pedido criado:</strong> <?php echo $run['pedido_id'] ? '#' . (int)$run['pedido_id'] : '—'; ?></div>
                <div class="col-sm-4"><strong>Modo Pix:</strong> <?php echo $run['pix_dry_run'] ? '<span class="badge bg-secondary">dry-run</span>' : '<span class="badge bg-warning text-dark">real</span>'; ?></div>
                <div class="col-sm-4"><strong>Iniciado em:</strong> <?php echo htmlspecialchars((string)$run['iniciado_em']); ?></div>
                <div class="col-sm-4"><strong>Heartbeat:</strong> <?php echo htmlspecialchars((string)($run['heartbeat_at'] ?? '—')); ?></div>
                <div class="col-sm-4"><strong>Worker:</strong> <?php echo htmlspecialchars((string)($run['worker_id'] ?? '—')); ?></div>
                <div class="col-sm-4"><strong>Exit code:</strong> <?php echo htmlspecialchars((string)($run['exit_code'] ?? '—')); ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($artifacts)): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-paperclip me-2"></i>Artefatos</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th>ID</th><th>Step</th><th>Tipo</th><th>Arquivo</th><th>Tamanho</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($artifacts as $artifact): ?>
                    <tr>
                        <td><?php echo (int)$artifact['id']; ?></td>
                        <td><?php echo !empty($artifact['step_id']) ? '#' . (int)$artifact['step_id'] : '—'; ?></td>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string)$artifact['kind']); ?></span></td>
                        <td><?php echo htmlspecialchars((string)$artifact['filename']); ?></td>
                        <td><?php echo isset($artifact['size_bytes']) ? (int)$artifact['size_bytes'] . ' B' : '—'; ?></td>
                        <td>
                            <a class="btn btn-outline-primary btn-sm" href="<?php echo $bp; ?>/admin/qa/run/artifact/<?php echo htmlspecialchars((string)$artifact['id']); ?>?run_id=<?php echo htmlspecialchars((string)$run['run_id']); ?>" target="_blank" rel="noopener">
                                <i class="fas fa-up-right-from-square"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fases -->
    <div class="card">
        <div class="card-header"><i class="fas fa-list me-2"></i>Detalhamento das Fases</div>
        <?php if (empty($steps)): ?>
        <div class="card-body">
            <div class="alert alert-secondary mb-0">Nenhuma fase registrada para este run.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Fase</th><th>Status</th><th>Artefatos</th><th>Mensagem</th></tr>
                </thead>
                <tbody>
                <?php foreach ($steps as $i => $s): ?>
                <tr class="<?php echo $s['ok'] ? '' : 'table-danger'; ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td><code><?php echo htmlspecialchars((string)$s['fase']); ?></code></td>
                    <td>
                        <?php if ($s['ok']): ?>
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>OK</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i>FALHOU</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $stepArtifacts = $artifactsByStep[(string)$s['id']] ?? []; ?>
                        <?php if (empty($stepArtifacts)): ?>
                            —
                        <?php else: ?>
                            <?php foreach ($stepArtifacts as $artifact): ?>
                                <a class="badge text-bg-light text-decoration-none border" href="<?php echo $bp; ?>/admin/qa/run/artifact/<?php echo htmlspecialchars((string)$artifact['id']); ?>?run_id=<?php echo htmlspecialchars((string)$run['run_id']); ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars((string)$artifact['kind']); ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars((string)$s['mensagem']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

<?php if ($engine === 'playwright' && in_array(($run['status'] ?? ''), ['queued', 'running'], true)): ?>
<script<?php echo csp_script_nonce_attr(); ?>>
setInterval(function () {
    fetch('<?php echo $bp; ?>/admin/qa/run/status/<?php echo htmlspecialchars((string)$run['run_id']); ?>', { headers: { 'Accept': 'application/json' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data && data.ok && !['queued', 'running'].includes(data.status)) {
                window.location.reload();
            }
        })
        .catch(function (e) { console.warn('[admin/simulacao] falha:', e); });
}, 5000);
</script>
<?php endif; ?>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
