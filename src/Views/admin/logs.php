<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-logs.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-terminal me-2 text-primary-custom"></i>Logs do Sistema</h1>
        </div>
    </header>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="row g-2">
                <div class="col-md-2">
                    <label class="form-label">Início</label>
                    <input type="date" name="periodo_inicio" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['periodo_inicio'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fim</label>
                    <input type="date" name="periodo_fim" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['periodo_fim'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nível</label>
                    <select name="level" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (['INFO','WARN','ERROR'] as $level): ?>
                            <option value="<?php echo $level; ?>" <?php echo (($filtros['level'] ?? '') === $level) ? 'selected' : ''; ?>><?php echo $level; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">System</label>
                    <input type="text" name="system" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['system'] ?? '')); ?>" placeholder="POR, PAGAMENTO...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['code'] ?? '')); ?>" placeholder="RTR-001">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Texto</label>
                    <input type="text" name="texto" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['texto'] ?? '')); ?>" placeholder="Mensagem ou contexto">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Classe</label>
                    <input type="text" name="class" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['class'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Função</label>
                    <input type="text" name="function" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['function'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Arquivo</label>
                    <input type="text" name="file" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['file'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fase</label>
                    <input type="text" name="phase" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['phase'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Request ID</label>
                    <input type="text" name="request_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['request_id'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Run ID</label>
                    <input type="text" name="run_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['run_id'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Pedido</label>
                    <input type="text" name="pedido_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['pedido_id'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Usuário</label>
                    <input type="text" name="usuario_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['usuario_id'] ?? '')); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Guincho</label>
                    <input type="text" name="guincho_id" class="form-control" value="<?php echo htmlspecialchars((string)($filtros['guincho_id'] ?? '')); ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="stat-grid mb-3">
        <div class="stat-grid__item">
            <span class="stat-grid__label">Eventos filtrados</span>
            <strong class="stat-grid__value"><?php echo (int)($appTotal ?? 0); ?></strong>
        </div>
        <div class="stat-grid__item is-danger">
            <span class="stat-grid__label">Erros</span>
            <strong class="stat-grid__value"><?php echo (int)($stats['errors'] ?? 0); ?></strong>
        </div>
        <div class="stat-grid__item is-warning">
            <span class="stat-grid__label">Warnings</span>
            <strong class="stat-grid__value"><?php echo (int)($stats['warns'] ?? 0); ?></strong>
        </div>
        <div class="stat-grid__item">
            <span class="stat-grid__label">Correlações</span>
            <strong class="stat-grid__value" style="font-size:.92rem;">Requests: <?php echo (int)($stats['requests'] ?? 0); ?><br>Runs: <?php echo (int)($stats['runs'] ?? 0); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active text-white" data-bs-toggle="tab" data-bs-target="#tab-app" type="button">
                        App <span class="badge bg-secondary ms-1"><?php echo (int)($appTotal ?? 0); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#tab-webhook" type="button">
                        Webhooks <span class="badge bg-secondary ms-1"><?php echo (int)($webhookTotal ?? 0); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#tab-file" type="button">
                        Arquivo
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content">
                <div class="tab-pane fade show active p-3" id="tab-app">
                    <?php if (empty($appLogs)): ?>
                        <div class="alert alert-warning m-2">Nenhum log do app encontrado.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead><tr><th>Data</th><th>Nível</th><th>System</th><th>Código</th><th>Classe/Função</th><th>Correlação</th><th>Mensagem</th></tr></thead>
                            <tbody>
                            <?php foreach ($appLogs as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($l['criado_em'] ?? '')); ?></td>
                                    <td><span class="badge bg-<?php echo ($l['level']??'') === 'ERROR' ? 'danger' : (($l['level']??'') === 'WARN' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars((string)($l['level'] ?? '')); ?></span></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($l['system'] ?? '')); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string)($l['phase'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars((string)($l['code'] ?? '')); ?></code>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars((string)($l['cls'] ?? '')); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars((string)($l['func'] ?? '')); ?></div>
                                        <?php if (!empty($l['file'])): ?>
                                            <div class="small text-muted"><?php echo htmlspecialchars((string)$l['file']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="logs-col-correlacao--180">
                                        <?php if (!empty($l['request_id'])): ?><div><span class="small text-muted">REQ</span> <code><?php echo htmlspecialchars((string)$l['request_id']); ?></code></div><?php endif; ?>
                                        <?php if (!empty($l['run_id'])): ?><div><span class="small text-muted">RUN</span> <code><?php echo htmlspecialchars((string)$l['run_id']); ?></code></div><?php endif; ?>
                                        <?php if (!empty($l['pedido_id'])): ?><div><a href="<?php echo htmlspecialchars($bp . '/admin/pedido/' . (int)$l['pedido_id']); ?>">Pedido #<?php echo (int)$l['pedido_id']; ?></a></div><?php endif; ?>
                                        <?php if (!empty($l['usuario_id'])): ?><div class="small text-muted">Usuário #<?php echo (int)$l['usuario_id']; ?></div><?php endif; ?>
                                        <?php if (!empty($l['guincho_id'])): ?><div class="small text-muted">Guincho #<?php echo (int)$l['guincho_id']; ?></div><?php endif; ?>
                                    </td>
                                    <td class="logs-col-mensagem">
                                        <div title="<?php echo htmlspecialchars((string)($l['msg'] ?? '')); ?>"><?php echo htmlspecialchars((string)($l['msg'] ?? '')); ?></div>
                                        <?php if (!empty($l['ctx_json'])): ?>
                                            <details class="mt-1">
                                                <summary class="small">Contexto</summary>
                                                <pre class="small mb-0 logs-ctx-pre"><?php echo htmlspecialchars((string)$l['ctx_json']); ?></pre>
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
                <div class="tab-pane fade p-3" id="tab-webhook">
                    <?php if (empty($webhookLogs)): ?>
                        <div class="alert alert-warning m-2">Nenhum log de webhook encontrado.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Data</th><th>Fonte</th><th>Evento</th><th>Status</th><th>Payload</th></tr></thead>
                            <tbody>
                            <?php foreach ($webhookLogs as $w): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($w['criado_em'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($w['fonte'] ?? $w['tipo'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($w['evento'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($w['status_http'] ?? $w['status'] ?? '')); ?></td>
                                    <td><code class="logs-payload-code"><?php echo htmlspecialchars(substr((string)($w['payload'] ?? ''), 0, 200)); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="tab-pane fade p-3" id="tab-file">
                    <?php if (empty($fileTail)): ?>
                        <div class="alert alert-warning">Arquivo de log não encontrado ou vazio.</div>
                    <?php else: ?>
                        <div class="small text-muted mb-2"><?php echo htmlspecialchars((string)($logFile ?? '')); ?></div>
                        <pre class="logs-file-tail--lg"><?php echo htmlspecialchars(implode("\n", $fileTail)); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <?php $prevQuery = http_build_query(array_merge($queryBase ?? [], ['pagina' => max(1, (int)($pagina ?? 1) - 1)])); ?>
            <?php $nextQuery = http_build_query(array_merge($queryBase ?? [], ['pagina' => min((int)($totalPaginas ?? 1), (int)($pagina ?? 1) + 1)])); ?>
            <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/logs?' . $prevQuery); ?>">&laquo; Anterior</a>
            <span class="logs-pagina-label">Página <?php echo (int)($pagina??1); ?> de <?php echo (int)($totalPaginas ?? 1); ?></span>
            <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/logs?' . $nextQuery); ?>">Próxima &raquo;</a>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
