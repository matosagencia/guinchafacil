<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
ob_start();
?>
<div class="bo-page">

    <header class="bo-page-title">
        <div>
            <span class="eyebrow">Administração</span>
            <h1>Logs do Sistema</h1>
        </div>
    </header>

    <div class="bo-panel">
        <div class="bo-panel-body">
            <form method="get" action="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="bo-form-grid">
                <div class="bo-field-group">
                    <label class="bo-label">Início</label>
                    <input type="date" name="periodo_inicio" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['periodo_inicio'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Fim</label>
                    <input type="date" name="periodo_fim" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['periodo_fim'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Nível</label>
                    <select name="level" class="bo-select">
                        <option value="">Todos</option>
                        <?php foreach (['INFO','WARN','ERROR'] as $level): ?>
                            <option value="<?php echo $level; ?>" <?php echo (($filtros['level'] ?? '') === $level) ? 'selected' : ''; ?>><?php echo $level; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">System</label>
                    <input type="text" name="system" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['system'] ?? '')); ?>" placeholder="POR, PAGAMENTO...">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Code</label>
                    <input type="text" name="code" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['code'] ?? '')); ?>" placeholder="RTR-001">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Texto</label>
                    <input type="text" name="texto" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['texto'] ?? '')); ?>" placeholder="Mensagem ou contexto">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Classe</label>
                    <input type="text" name="class" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['class'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Função</label>
                    <input type="text" name="function" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['function'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Arquivo</label>
                    <input type="text" name="file" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['file'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Fase</label>
                    <input type="text" name="phase" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['phase'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Request ID</label>
                    <input type="text" name="request_id" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['request_id'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Run ID</label>
                    <input type="text" name="run_id" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['run_id'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Pedido</label>
                    <input type="text" name="pedido_id" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['pedido_id'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Usuário</label>
                    <input type="text" name="usuario_id" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['usuario_id'] ?? '')); ?>">
                </div>
                <div class="bo-field-group">
                    <label class="bo-label">Guincho</label>
                    <input type="text" name="guincho_id" class="bo-input" value="<?php echo htmlspecialchars((string)($filtros['guincho_id'] ?? '')); ?>">
                </div>
                <div class="bo-actions">
                    <button type="submit" class="bo-btn bo-btn--primary">Filtrar</button>
                    <a href="<?php echo htmlspecialchars($bp . '/admin/logs'); ?>" class="bo-btn">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="bo-metrics">
        <div class="bo-metric">
            <span class="bo-metric__label">Eventos filtrados</span>
            <strong class="bo-metric__value"><?php echo (int)($appTotal ?? 0); ?></strong>
        </div>
        <div class="bo-metric is-danger">
            <span class="bo-metric__label">Erros</span>
            <strong class="bo-metric__value"><?php echo (int)($stats['errors'] ?? 0); ?></strong>
        </div>
        <div class="bo-metric is-warning">
            <span class="bo-metric__label">Warnings</span>
            <strong class="bo-metric__value"><?php echo (int)($stats['warns'] ?? 0); ?></strong>
        </div>
        <div class="bo-metric">
            <span class="bo-metric__label">Correlações</span>
            <strong class="bo-metric__value">Requests: <?php echo (int)($stats['requests'] ?? 0); ?><br>Runs: <?php echo (int)($stats['runs'] ?? 0); ?></strong>
        </div>
    </div>

    <div class="bo-panel">
        <div class="bo-panel-header">
            <ul class="nav nav-tabs bo-panel-header-tabs" role="tablist">
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
        <div class="bo-panel-body p-0">
            <div class="tab-content">
                <div class="tab-pane fade show active p-3" id="tab-app">
                    <?php if (empty($appLogs)): ?>
                        <div class="bo-alert bo-alert--warning m-2">Nenhum log do app encontrado.</div>
                    <?php else: ?>
                    <div class="bo-table-scroll">
                        <table class="bo-ledger align-middle">
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
                <div class="bo-tab-panel" id="tab-webhook">
                    <?php if (empty($webhookLogs)): ?>
                        <div class="bo-alert bo-alert--warning m-2">Nenhum log de webhook encontrado.</div>
                    <?php else: ?>
                    <div class="bo-table-scroll">
                        <table class="bo-ledger">
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
                <div class="bo-tab-panel" id="tab-file">
                    <?php if (empty($fileTail)): ?>
                        <div class="bo-alert bo-alert--warning">Arquivo de log não encontrado ou vazio.</div>
                    <?php else: ?>
                        <div class="small text-muted mb-2"><?php echo htmlspecialchars((string)($logFile ?? '')); ?></div>
                        <pre class="logs-file-tail--lg"><?php echo htmlspecialchars(implode("\n", $fileTail)); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="bo-panel-footer d-flex justify-content-between">
            <?php $prevQuery = http_build_query(array_merge($queryBase ?? [], ['pagina' => max(1, (int)($pagina ?? 1) - 1)])); ?>
            <?php $nextQuery = http_build_query(array_merge($queryBase ?? [], ['pagina' => min((int)($totalPaginas ?? 1), (int)($pagina ?? 1) + 1)])); ?>
            <a class="bo-btn bo-btn--sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/logs?' . $prevQuery); ?>">&laquo; Anterior</a>
            <span class="logs-pagina-label">Página <?php echo (int)($pagina??1); ?> de <?php echo (int)($totalPaginas ?? 1); ?></span>
            <a class="bo-btn bo-btn--sm" href="<?php echo htmlspecialchars(($bp ?: '') . '/admin/logs?' . $nextQuery); ?>">Próxima &raquo;</a>
        </div>
    </div>


<?php $conteudo_pagina=ob_get_clean(); $perfil='admin'; $titulo_pagina='Logs do sistema'; $breadcrumb=['Sistema','Logs']; $assets_css=['/public/assets/css/pages/bo-admin-logs.css']; require __DIR__ . '/../layouts/backoffice_shell.php';
