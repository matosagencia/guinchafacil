<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content">

    <div class="page-header">
        <div>
            <div class="page-title"><i class="fas fa-terminal me-2 text-primary-custom"></i>Logs do Sistema</div>
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
                        <table class="table table-sm table-hover mb-0">
                            <thead><tr><th>Data</th><th>Nível</th><th>Classe</th><th>Função</th><th>Mensagem</th></tr></thead>
                            <tbody>
                            <?php foreach ($appLogs as $l): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($l['criado_em'] ?? '')); ?></td>
                                    <td><span class="badge bg-<?php echo ($l['level']??'') === 'ERROR' ? 'danger' : (($l['level']??'') === 'WARN' ? 'warning' : 'secondary'); ?>"><?php echo htmlspecialchars((string)($l['level'] ?? '')); ?></span></td>
                                    <td><?php echo htmlspecialchars((string)($l['cls'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($l['func'] ?? '')); ?></td>
                                    <td style="max-width:400px"><div class="text-truncate" title="<?php echo htmlspecialchars((string)($l['msg'] ?? '')); ?>"><?php echo htmlspecialchars((string)($l['msg'] ?? '')); ?></div></td>
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
                            <thead><tr><th>Data</th><th>Tipo</th><th>Status</th><th>Payload</th></tr></thead>
                            <tbody>
                            <?php foreach ($webhookLogs as $w): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)($w['criado_em'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($w['tipo'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)($w['status'] ?? '')); ?></td>
                                    <td><code style="font-size:.75rem"><?php echo htmlspecialchars(substr((string)($w['payload'] ?? ''), 0, 200)); ?></code></td>
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
                        <pre style="white-space:pre-wrap;max-height:60vh;overflow:auto;background:#050505;color:#4ade80;padding:1rem;border-radius:8px;font-size:.82rem"><?php echo htmlspecialchars(implode("\n", $fileTail)); ?></pre>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <a class="btn btn-secondary btn-sm" href="?pagina=<?php echo max(1, (int)($pagina??1) - 1); ?>">&laquo; Anterior</a>
            <span style="color:var(--theme-muted);font-size:.85rem">Página <?php echo (int)($pagina??1); ?></span>
            <a class="btn btn-secondary btn-sm" href="?pagina=<?php echo (int)($pagina??1) + 1; ?>">Próxima &raquo;</a>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
