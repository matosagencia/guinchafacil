<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$buildQuery = static function (array $params): string {
    return http_build_query(array_filter($params, static fn ($value): bool => $value !== '' && $value !== null));
};

$formatMs = static function (?int $value): string {
    return $value === null ? '-' : $value . ' ms';
};
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-logs.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <div class="ops-topbar">
        <form method="get" action="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="ops-topbar__search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="text" name="texto" value="<?php echo htmlspecialchars((string)($filtros['texto'] ?? '')); ?>" placeholder="Buscar por texto na mensagem de log" autocomplete="off" aria-label="Buscar logs">
        </form>
        <div class="ops-topbar__meta">
            <span class="ops-topbar__status"><span class="ops-topbar__status-dot"></span><?php echo (int)$appTotal; ?> eventos</span>
            <?php if ((int)$stats['errors'] > 0): ?><span style="color:var(--danger,#dc3545);font-weight:700;"><i class="fas fa-triangle-exclamation me-1"></i><?php echo (int)$stats['errors']; ?> erros</span><?php endif; ?>
        </div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">SRE</span>
            <h1><i class="fas fa-terminal me-2 text-primary-custom"></i>Logs do Sistema</h1>
            <p>Correlação, métricas e exportação mascarada para investigação rápida.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($bp . '/admin/logs/export?' . $buildQuery(array_merge($queryBase, ['format' => 'jsonl']))); ?>">Exportar JSONL</a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($bp . '/admin/logs/export?' . $buildQuery(array_merge($queryBase, ['format' => 'csv']))); ?>">Exportar CSV</a>
        </div>
    </header>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="row g-2">
                <?php foreach ([
                    ['periodo_inicio', 'Início', 'date'],
                    ['periodo_fim', 'Fim', 'date'],
                    ['level', 'Nível', 'select'],
                    ['system', 'System', 'text'],
                    ['code', 'Code', 'text'],
                    ['texto', 'Texto', 'text'],
                    ['class', 'Classe', 'text'],
                    ['function', 'Função', 'text'],
                    ['file', 'Arquivo', 'text'],
                    ['phase', 'Fase', 'text'],
                    ['request_id', 'Request ID', 'text'],
                    ['run_id', 'Run ID', 'text'],
                    ['pedido_id', 'Pedido', 'text'],
                    ['usuario_id', 'Usuário', 'text'],
                    ['guincho_id', 'Guincho', 'text'],
                ] as $field): ?>
                    <div class="col-md-2">
                        <label class="form-label"><?php echo htmlspecialchars($field[1]); ?></label>
                        <?php if ($field[2] === 'select'): ?>
                            <select name="<?php echo htmlspecialchars($field[0]); ?>" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach (['DEBUG', 'INFO', 'WARN', 'ERROR'] as $level): ?>
                                    <option value="<?php echo $level; ?>" <?php echo (($filtros[$field[0]] ?? '') === $level) ? 'selected' : ''; ?>><?php echo $level; ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input
                                type="<?php echo htmlspecialchars($field[2]); ?>"
                                name="<?php echo htmlspecialchars($field[0]); ?>"
                                class="form-control"
                                value="<?php echo htmlspecialchars((string)($filtros[$field[0]] ?? '')); ?>"
                            >
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="stat-grid mb-3">
        <div class="stat-grid__item"><span class="stat-grid__label">Eventos</span><strong class="stat-grid__value"><?php echo (int)$appTotal; ?></strong></div>
        <div class="stat-grid__item is-danger"><span class="stat-grid__label">Erros</span><strong class="stat-grid__value"><?php echo (int)$stats['errors']; ?></strong></div>
        <div class="stat-grid__item is-warning"><span class="stat-grid__label">Warnings</span><strong class="stat-grid__value"><?php echo (int)$stats['warns']; ?></strong></div>
        <div class="stat-grid__item"><span class="stat-grid__label">Requests</span><strong class="stat-grid__value"><?php echo (int)$stats['requests']; ?></strong></div>
        <div class="stat-grid__item"><span class="stat-grid__label">Runs QA</span><strong class="stat-grid__value"><?php echo (int)$stats['runs']; ?></strong></div>
        <div class="stat-grid__item"><span class="stat-grid__label">P50 / P95</span><strong class="stat-grid__value" style="font-size:.92rem;"><?php echo htmlspecialchars($formatMs($latency['p50'])); ?><br><?php echo htmlspecialchars($formatMs($latency['p95'])); ?></strong></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Erros por Sistema</div>
                <div class="card-body">
                    <?php if (empty($charts['systems'])): ?>
                        <div class="text-muted small">Sem dados.</div>
                    <?php else: ?>
                        <?php foreach ($charts['systems'] as $row): ?>
                            <?php $width = max(8, min(100, $appTotal > 0 ? (int)round(((int)$row['total'] / $appTotal) * 100) : 0)); ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small">
                                    <span><?php echo htmlspecialchars((string)$row['label']); ?></span>
                                    <span><?php echo (int)$row['total']; ?> / erro <?php echo (int)$row['errors']; ?></span>
                                </div>
                                <div class="progress logs-progress-track">
                                    <div class="progress-bar" style="width: <?php echo $width; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Códigos Mais Frequentes</div>
                <div class="card-body">
                    <?php if (empty($charts['codes'])): ?>
                        <div class="text-muted small">Sem dados.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Código</th><th>Total</th></tr></thead>
                                <tbody>
                                <?php foreach ($charts['codes'] as $row): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars((string)$row['label']); ?></code></td>
                                        <td><?php echo (int)$row['total']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">Latência por Operação</div>
                <div class="card-body">
                    <?php if (empty($latency['operations'])): ?>
                        <div class="text-muted small">Sem dados de duração.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Operação</th><th>P50</th><th>P95</th></tr></thead>
                                <tbody>
                                <?php foreach ($latency['operations'] as $row): ?>
                                    <tr>
                                        <td title="<?php echo htmlspecialchars((string)$row['operation']); ?>"><?php echo htmlspecialchars(substr((string)$row['operation'], 0, 42)); ?></td>
                                        <td><?php echo htmlspecialchars($formatMs($row['p50'])); ?></td>
                                        <td><?php echo htmlspecialchars($formatMs($row['p95'])); ?></td>
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

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Correlação</div>
                <div class="card-body">
                    <?php if (empty($correlation)): ?>
                        <div class="text-muted small">Use `request_id`, `run_id` ou `pedido_id` para abrir correlação direta.</div>
                    <?php else: ?>
                        <?php foreach ($correlation as $item): ?>
                            <div class="border rounded p-2 mb-2">
                                <?php if (isset($item['label'])): ?>
                                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string)$item['label']); ?> <code><?php echo htmlspecialchars((string)$item['value']); ?></code></div>
                                    <?php if (!empty($item['run']['run_id'])): ?>
                                        <div class="small mb-2">
                                            <a href="<?php echo htmlspecialchars($bp . '/admin/simulador/' . rawurlencode((string)$item['run']['run_id'])); ?>">Abrir run Playwright</a>
                                            · status <?php echo htmlspecialchars((string)($item['run']['status'] ?? '')); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php foreach (($item['rows'] ?? []) as $row): ?>
                                        <div class="small mb-1"><strong><?php echo htmlspecialchars((string)$row['criado_em']); ?></strong> · <?php echo htmlspecialchars((string)$row['system']); ?> · <code><?php echo htmlspecialchars((string)$row['code']); ?></code> · <?php echo htmlspecialchars((string)$row['msg']); ?></div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="small">
                                        <strong><?php echo htmlspecialchars((string)$item['ultimo_evento']); ?></strong>
                                        · <?php if (!empty($item['request_id'])): ?><code><?php echo htmlspecialchars((string)$item['request_id']); ?></code><?php endif; ?>
                                        <?php if (!empty($item['run_id'])): ?> · <code><?php echo htmlspecialchars((string)$item['run_id']); ?></code><?php endif; ?>
                                        <?php if (!empty($item['pedido_id'])): ?> · <a href="<?php echo htmlspecialchars($bp . '/admin/pedido/' . (int)$item['pedido_id']); ?>">Pedido #<?php echo (int)$item['pedido_id']; ?></a><?php endif; ?>
                                        · <?php echo (int)$item['total']; ?> evento(s)
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">App Logs</div>
                <div class="card-body p-0">
                    <?php if (empty($appLogs)): ?>
                        <div class="alert alert-warning m-3">Nenhum log encontrado.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead><tr><th>Data</th><th>Nível</th><th>Origem</th><th>Correlação</th><th>Mensagem</th></tr></thead>
                                <tbody>
                                <?php foreach ($appLogs as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($log['criado_em'] ?? '')); ?></td>
                                        <td><span class="badge bg-<?php echo ($log['level'] ?? '') === 'ERROR' ? 'danger' : (($log['level'] ?? '') === 'WARN' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars((string)($log['level'] ?? '')); ?></span></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars((string)($log['system'] ?? '')); ?> <code><?php echo htmlspecialchars((string)($log['code'] ?? '')); ?></code></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars((string)($log['cls'] ?? '')); ?>::<?php echo htmlspecialchars((string)($log['func'] ?? '')); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars((string)($log['phase'] ?? '')); ?></div>
                                        </td>
                                        <td class="logs-col-correlacao">
                                            <?php if (!empty($log['request_id'])): ?><div><span class="small text-muted">REQ</span> <code><?php echo htmlspecialchars((string)$log['request_id']); ?></code></div><?php endif; ?>
                                            <?php if (!empty($log['run_id'])): ?><div><a href="<?php echo htmlspecialchars($bp . '/admin/simulador/' . rawurlencode((string)$log['run_id'])); ?>">Run <?php echo htmlspecialchars((string)$log['run_id']); ?></a></div><?php endif; ?>
                                            <?php if (!empty($log['pedido_id'])): ?><div><a href="<?php echo htmlspecialchars($bp . '/admin/pedido/' . (int)$log['pedido_id']); ?>">Pedido #<?php echo (int)$log['pedido_id']; ?></a></div><?php endif; ?>
                                            <?php if (!empty($log['duration_ms'])): ?><div class="small text-muted"><?php echo (int)$log['duration_ms']; ?> ms</div><?php endif; ?>
                                        </td>
                                        <td class="logs-col-mensagem">
                                            <div title="<?php echo htmlspecialchars((string)($log['msg'] ?? '')); ?>"><?php echo htmlspecialchars((string)($log['msg'] ?? '')); ?></div>
                                            <?php if (!empty($log['ctx_json'])): ?>
                                                <details class="mt-1">
                                                    <summary class="small">Contexto</summary>
                                                    <pre class="small mb-0 logs-ctx-pre"><?php echo htmlspecialchars((string)$log['ctx_json']); ?></pre>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/logs?' . $buildQuery(array_merge($queryBase, ['pagina' => max(1, $pagina - 1)]))); ?>">&laquo; Anterior</a>
                    <span class="small text-muted">Página <?php echo (int)$pagina; ?> de <?php echo (int)$totalPaginas; ?></span>
                    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/logs?' . $buildQuery(array_merge($queryBase, ['pagina' => min($totalPaginas, $pagina + 1)]))); ?>">Próxima &raquo;</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Webhooks</div>
                <div class="card-body p-0">
                    <?php if (empty($webhookLogs)): ?>
                        <div class="alert alert-warning m-3">Nenhum log de webhook encontrado.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Data</th><th>Fonte</th><th>Evento</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($webhookLogs as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)($row['criado_em'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['fonte'] ?? $row['tipo'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['evento'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($row['status_http'] ?? $row['status'] ?? '')); ?></td>
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
                <div class="card-header">Arquivo JSONL</div>
                <div class="card-body">
                    <div class="small text-muted mb-2"><?php echo htmlspecialchars((string)$logFile); ?></div>
                    <?php if (empty($fileTail)): ?>
                        <div class="alert alert-warning mb-0">Arquivo de log não encontrado ou vazio.</div>
                    <?php else: ?>
                        <pre class="logs-file-tail"><?php echo htmlspecialchars(implode("\n", $fileTail)); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
