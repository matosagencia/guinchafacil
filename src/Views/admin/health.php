<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

$totalOk    = count(array_filter($checks, fn($c) => $c['nivel'] === 'ok'));
$totalAviso = count(array_filter($checks, fn($c) => $c['nivel'] === 'aviso'));
$totalErro  = count(array_filter($checks, fn($c) => $c['nivel'] === 'erro'));
$saudavel   = $totalErro === 0;
$checklistOk = count(array_filter($productionChecklist ?? [], fn($item) => ($item['nivel'] ?? '') === 'ok'));
$checklistPending = count(array_filter($productionChecklist ?? [], fn($item) => ($item['nivel'] ?? '') === 'aviso'));
$checklistBlocked = count(array_filter($productionChecklist ?? [], fn($item) => ($item['nivel'] ?? '') === 'erro'));
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-health.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <div class="ops-topbar">
        <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="healthBusca" placeholder="Buscar por subsistema" autocomplete="off" aria-label="Buscar subsistemas"></div>
        <div class="ops-topbar__meta">
            <span class="ops-topbar__status"><span class="ops-topbar__status-dot <?php echo $saudavel ? '' : 'is-danger'; ?>"></span><?php echo $saudavel ? 'Tudo operacional' : $totalErro . ' erro(s) ativo(s)'; ?></span>
            <a href="<?php echo htmlspecialchars($bp); ?>/admin/health" class="ops-dashboard-link"><i class="fas fa-rotate me-1"></i>Atualizar</a>
        </div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">SRE</span>
            <h1>
                <?php if ($saudavel): ?>
                    <i class="fas fa-heart-pulse me-2 text-success"></i>Health Check
                <?php else: ?>
                    <i class="fas fa-heart-crack me-2 text-danger"></i>Health Check
                <?php endif; ?>
            </h1>
            <p>§HEALTH-01 — status consolidado dos subsistemas</p>
        </div>
    </header>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon--ok"><i class="fas fa-circle-check"></i></div>
                <div class="stat-value stat-value--ok"><?php echo $totalOk; ?></div>
                <div class="stat-label">OK</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon--aviso"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stat-value stat-value--aviso"><?php echo $totalAviso; ?></div>
                <div class="stat-label">Avisos</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon--erro"><i class="fas fa-circle-xmark"></i></div>
                <div class="stat-value stat-value--erro"><?php echo $totalErro; ?></div>
                <div class="stat-label">Erros</div>
            </div>
        </div>
    </div>

    <!-- Checks -->
    <div class="card">
        <div class="card-header"><i class="fas fa-list-check me-2"></i>Subsistemas</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr><th>Subsistema</th><th>Status</th><th>Detalhes</th></tr>
                </thead>
                <tbody>
                <?php foreach ($checks as $c): ?>
                <?php
                $rowClass   = match($c['nivel']) {
                    'erro'  => 'table-danger',
                    'aviso' => 'table-warning',
                    default => '',
                };
                $badgeClass = match($c['nivel']) {
                    'ok'    => 'bg-success',
                    'aviso' => 'bg-warning text-dark',
                    'erro'  => 'bg-danger',
                    default => 'bg-secondary',
                };
                $icon = match($c['nivel']) {
                    'ok'    => 'fa-circle-check',
                    'aviso' => 'fa-triangle-exclamation',
                    'erro'  => 'fa-circle-xmark',
                    default => 'fa-circle-question',
                };
                ?>
                <tr class="<?php echo $rowClass; ?>" data-search="<?php echo htmlspecialchars(strtolower($c['label']), ENT_QUOTES, 'UTF-8'); ?>">
                    <td><strong><?php echo htmlspecialchars($c['label']); ?></strong></td>
                    <td>
                        <span class="badge <?php echo $badgeClass; ?>">
                            <i class="fas <?php echo $icon; ?> me-1"></i><?php echo htmlspecialchars($c['status']); ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?php echo htmlspecialchars($c['info']); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><i class="fas fa-clock-rotate-left me-2"></i>Cron Jobs</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Agendamento</th>
                                <th>Script</th>
                                <th>Status</th>
                                <th>Heartbeat</th>
                                <th>Duração</th>
                                <th>Última mensagem</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($cronJobs ?? []) as $job): ?>
                            <?php
                            $jobStatus = (string)($job['ultima_execucao_status'] ?? '');
                            $jobBadge = match($jobStatus) {
                                'ok' => 'bg-success',
                                'warning' => 'bg-warning text-dark',
                                'error' => 'bg-danger',
                                'running' => 'bg-primary',
                                default => 'bg-secondary',
                            };
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string)$job['job_code']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string)($job['descricao'] ?? '')); ?></div>
                                </td>
                                <td class="font-monospace small"><?php echo htmlspecialchars((string)($job['schedule_hint'] ?? '')); ?></td>
                                <td class="font-monospace small"><?php echo htmlspecialchars((string)($job['script_path'] ?? '-')); ?></td>
                                <td><span class="badge <?php echo $jobBadge; ?>"><?php echo htmlspecialchars($jobStatus !== '' ? $jobStatus : 'nunca'); ?></span></td>
                                <td class="small"><?php echo htmlspecialchars((string)($job['heartbeat_at'] ?? '-')); ?></td>
                                <td class="small"><?php echo htmlspecialchars((string)($job['ultimo_duration_ms'] ?? '-')); ?> ms</td>
                                <td class="small text-muted"><?php echo htmlspecialchars((string)($job['ultima_mensagem'] ?? '-')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cronJobs)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum cron registrado.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-box-archive me-2"></i>Política de Retenção</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php
                            $retentionLabels = [
                                'retention_simulation_artifacts_days' => 'Artefatos QA',
                                'retention_simulation_runs_days' => 'Execuções QA',
                                'retention_jsonl_logs_days' => 'Logs JSONL',
                                'retention_cron_executions_days' => 'Histórico de cron',
                                'retention_por_days' => 'Pontos POR',
                                'retention_evidencias_days' => 'Evidências',
                                'retention_chat_days' => 'Chat',
                            ];
                            foreach ($retentionLabels as $key => $label):
                            ?>
                            <tr>
                                <th><?php echo htmlspecialchars($label); ?></th>
                                <td class="text-end font-monospace"><?php echo htmlspecialchars((string)($retentionConfig[$key] ?? '-')); ?> dias</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-terminal me-2"></i>Execuções Recentes</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Status</th>
                                <th>Início</th>
                                <th>Duração</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($cronExecutions ?? []) as $execution): ?>
                            <?php
                            $execStatus = (string)($execution['status'] ?? '');
                            $execBadge = match($execStatus) {
                                'ok' => 'bg-success',
                                'warning' => 'bg-warning text-dark',
                                'error' => 'bg-danger',
                                'running' => 'bg-primary',
                                default => 'bg-secondary',
                            };
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars((string)$execution['job_code']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars((string)($execution['message'] ?? '')); ?></div>
                                </td>
                                <td><span class="badge <?php echo $execBadge; ?>"><?php echo htmlspecialchars($execStatus); ?></span></td>
                                <td class="small"><?php echo htmlspecialchars((string)($execution['started_at'] ?? '')); ?></td>
                                <td class="small"><?php echo htmlspecialchars((string)($execution['duration_ms'] ?? '-')); ?> ms</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cronExecutions)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Nenhuma execução recente.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><i class="fas fa-screwdriver-wrench me-2"></i>Comandos de Instalação do Scheduler</div>
        <div class="card-body">
            <div class="alert alert-warning">
                O projeto registra e monitora cron jobs, mas o agendamento real precisa ser criado no servidor.
                Use as linhas abaixo no `crontab`/cPanel do ambiente de produção.
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Job</th>
                            <th>Agendamento</th>
                            <th>Comando sugerido (Unix/cPanel)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($cronInstallCommands ?? []) as $command): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string)$command['job_code']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string)$command['descricao']); ?></div>
                            </td>
                            <td class="font-monospace small"><?php echo htmlspecialchars((string)$command['schedule_hint']); ?></td>
                            <td><code><?php echo htmlspecialchars((string)$command['command_unix']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cronInstallCommands)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">Catálogo de cron indisponível.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><i class="fas fa-clipboard-check me-2"></i>Checklist de Produção P0</div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon--ok"><i class="fas fa-circle-check"></i></div>
                        <div class="stat-value stat-value--ok"><?php echo $checklistOk; ?></div>
                        <div class="stat-label">Prontos</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon--aviso"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-value stat-value--aviso"><?php echo $checklistPending; ?></div>
                        <div class="stat-label">Pendentes</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon stat-icon--erro"><i class="fas fa-ban"></i></div>
                        <div class="stat-value stat-value--erro"><?php echo $checklistBlocked; ?></div>
                        <div class="stat-label">Bloqueios</div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Categoria</th>
                            <th>Status</th>
                            <th>Diagnóstico</th>
                            <th>Ação final</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($productionChecklist ?? []) as $item): ?>
                        <?php
                        $itemNivel = (string)($item['nivel'] ?? 'aviso');
                        $itemBadge = match($itemNivel) {
                            'ok' => 'bg-success',
                            'erro' => 'bg-danger',
                            default => 'bg-warning text-dark',
                        };
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string)$item['label']); ?></div>
                                <div class="small text-muted font-monospace"><?php echo htmlspecialchars((string)$item['code']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string)$item['category']); ?></td>
                            <td><span class="badge <?php echo $itemBadge; ?>"><?php echo htmlspecialchars((string)$item['status']); ?></span></td>
                            <td class="small text-muted"><?php echo htmlspecialchars((string)$item['detail']); ?></td>
                            <td class="small"><?php echo htmlspecialchars((string)$item['action']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($productionChecklist)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Checklist indisponível.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('healthBusca');
        var linhas = Array.prototype.slice.call(document.querySelectorAll('tr[data-search]'));
        if (!busca) return;
        busca.addEventListener('input', function () {
            var query = busca.value.trim().toLowerCase();
            linhas.forEach(function (linha) {
                linha.hidden = Boolean(query) && (linha.getAttribute('data-search') || '').indexOf(query) < 0;
            });
        });
    })();
    </script>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
