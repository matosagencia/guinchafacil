<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
$simEnabled = defined('SIMULATION_ENABLED') ? SIMULATION_ENABLED : (env('SIMULATION_ENABLED', 'false') === 'true');
$playwrightEnabled = $playwrightEnabled ?? false;
?>
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <?php
    $totalRuns = count($runsRecentes ?? []);
    $runsFalhados = count(array_filter($runsRecentes ?? [], static fn($r) => ($r['status'] ?? '') === 'failed'));
    $runsRodando = count(array_filter($runsRecentes ?? [], static fn($r) => in_array(($r['status'] ?? ''), ['running', 'queued'], true)));
    ?>
    <div class="ops-topbar">
        <div class="ops-topbar__search"><i class="fas fa-magnifying-glass"></i><input type="search" id="simulacaoBusca" placeholder="Buscar por run_id, suite ou pedido" autocomplete="off" aria-label="Buscar execuções"></div>
        <div class="ops-topbar__meta"><span class="ops-topbar__status"><span class="ops-topbar__status-dot <?php echo $simEnabled ? '' : 'is-warning'; ?>"></span><?php echo $simEnabled ? 'Simulador habilitado' : 'Simulador desabilitado'; ?></span></div>
    </div>

    <header class="page-head mb-3">
        <div>
            <span class="eyebrow">SRE</span>
            <h1><i class="fas fa-flask me-2 text-primary-custom"></i>Simulador Ponta-a-Ponta</h1>
            <p>§LIVE-SIM-01 — executa o fluxo completo em ambiente controlado</p>
        </div>
    </header>

    <section class="ops-summary mb-4" aria-label="Resumo de execuções">
        <article class="ops-metric">
            <span class="ops-metric__label">Execuções registradas</span>
            <strong class="ops-metric__value"><?php echo $totalRuns; ?></strong>
        </article>
        <article class="ops-metric <?php echo $runsRodando > 0 ? 'is-warning' : ''; ?>">
            <span class="ops-metric__label">Em andamento</span>
            <strong class="ops-metric__value"><?php echo $runsRodando; ?></strong>
        </article>
        <article class="ops-metric <?php echo $runsFalhados > 0 ? 'is-danger' : ''; ?>">
            <span class="ops-metric__label">Com falha</span>
            <strong class="ops-metric__value"><?php echo $runsFalhados; ?></strong>
        </article>
    </section>

    <?php if (!$simEnabled): ?>
    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation me-2"></i>
        <strong>Simulador desabilitado.</strong>
        Defina <code>SIMULATION_ENABLED=true</code> no <code>.env</code> para habilitar.
    </div>
    <?php else: ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-flask me-2"></i>Simulação Interna PHP</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/simulador">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="pix_dry_run" value="1" id="chkDryRunPhp" checked>
                                <label class="form-check-label" for="chkDryRunPhp">
                                    <strong>PIX_DRY_RUN</strong> — não executa repasse real
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-play me-1"></i>Executar Simulação
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-robot me-2"></i>Testes Reais Playwright</div>
                <div class="card-body">
                    <?php if (!$playwrightEnabled): ?>
                    <div class="alert alert-warning mb-0">
                        A fila Playwright usa o mesmo gate de segurança do simulador. Defina <code>SIMULATION_ENABLED=true</code> para habilitar.
                    </div>
                    <?php else: ?>
                    <form method="POST" action="<?php echo $bp; ?>/admin/qa/run">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Suíte</label>
                                <select class="form-select" name="suite">
                                    <option value="smoke">smoke</option>
                                    <option value="pedido-novo">pedido-novo</option>
                                    <option value="atendimento-completo">atendimento-completo</option>
                                    <option value="cancelamento">cancelamento</option>
                                    <option value="sessao-seguranca">sessao-seguranca</option>
                                    <option value="constituicao-fluxo">constituicao-fluxo</option>
                                    <option value="por-antifraude">por-antifraude</option>
                                    <option value="concorrencia-aceite">concorrencia-aceite</option>
                                    <option value="pagamento-sandbox">pagamento-sandbox</option>
                                    <option value="upload-seguranca">upload-seguranca</option>
                                    <option value="cadastro-cliente-bulk">cadastro-cliente-bulk</option>
                                    <option value="cadastro-guincho-bulk">cadastro-guincho-bulk</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Navegador</label>
                                <select class="form-select" name="browser">
                                    <option value="chromium">chromium</option>
                                    <option value="firefox">firefox</option>
                                    <option value="webkit">webkit</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Viewport</label>
                                <select class="form-select" name="viewport">
                                    <option value="desktop">desktop</option>
                                    <option value="mobile">mobile</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ambiente alvo</label>
                                <input class="form-control" type="text" name="target_environment" value="staging">
                            </div>
                            <div class="col-12">
                                <label class="form-label">URL alvo</label>
                                <input class="form-control" type="url" name="target_url" value="<?php echo htmlspecialchars((string)(defined('APP_URL') ? APP_URL : '')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Locale</label>
                                <input class="form-control" type="text" name="locale" value="pt-BR">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Timezone</label>
                                <input class="form-control" type="text" name="timezone" value="America/Sao_Paulo">
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pix_dry_run" value="1" id="chkDryRunQa" checked>
                                    <label class="form-check-label" for="chkDryRunQa">PIX dry-run obrigatório</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="record_video" value="1" id="chkVideoQa" checked>
                                    <label class="form-check-label" for="chkVideoQa">Gravar vídeo</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="record_trace" value="1" id="chkTraceQa" checked>
                                    <label class="form-check-label" for="chkTraceQa">Gravar trace</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="stop_on_failure" value="1" id="chkStopQa" checked>
                                    <label class="form-check-label" for="chkStopQa">Parar na primeira falha</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary mt-3">
                            <i class="fas fa-bolt me-1"></i>Enfileirar Playwright
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="fas fa-history me-2"></i>Execuções Recentes</div>
        <?php if (empty($runsRecentes)): ?>
        <div class="card-body">
            <div class="alert alert-secondary mb-0">Nenhuma execução registrada ainda.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>run_id</th>
                        <th>Engine</th>
                        <th>Status</th>
                        <th>Pedido</th>
                        <th>Suite</th>
                        <th>Fases</th>
                        <th>Erros</th>
                        <th>Modo</th>
                        <th>Duração</th>
                        <th>Iniciado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($runsRecentes as $run): ?>
                <tr data-search="<?php echo htmlspecialchars(strtolower((string)$run['run_id'] . ' ' . (string)($run['suite'] ?? '') . ' ' . (string)($run['pedido_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                    <td><code><?php echo htmlspecialchars(substr((string)$run['run_id'], 0, 12)); ?>…</code></td>
                    <td><span class="badge bg-dark"><?php echo htmlspecialchars((string)($run['engine'] ?? 'php_internal')); ?></span></td>
                    <td>
                        <?php
                        $badge = match($run['status']) {
                            'completed' => 'success',
                            'failed'    => 'danger',
                            'running'   => 'warning',
                            'queued'    => 'info',
                            'cancelled' => 'secondary',
                            default     => 'secondary',
                        };
                        ?>
                        <span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars((string)$run['status']); ?></span>
                    </td>
                    <td><?php echo $run['pedido_id'] ? '#' . (int)$run['pedido_id'] : '—'; ?></td>
                    <td><?php echo htmlspecialchars((string)($run['suite'] ?? 'full')); ?></td>
                    <td><?php echo (int)$run['total_fases']; ?></td>
                    <td><?php echo (int)$run['fases_erro'] > 0 ? '<span class="text-danger fw-bold">' . (int)$run['fases_erro'] . '</span>' : '0'; ?></td>
                    <td><?php echo $run['pix_dry_run'] ? '<span class="badge bg-secondary">dry-run</span>' : '<span class="badge bg-warning text-dark">real</span>'; ?></td>
                    <td><?php echo $run['duracao_ms'] !== null ? (int)$run['duracao_ms'] . 'ms' : '—'; ?></td>
                    <td><?php echo htmlspecialchars((string)$run['iniciado_em']); ?></td>
                    <td>
                        <a href="<?php echo $bp; ?>/admin/simulador/<?php echo htmlspecialchars((string)$run['run_id']); ?>" class="btn btn-xs btn-outline-primary btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script<?php echo csp_script_nonce_attr(); ?>>
    (function () {
        var busca = document.getElementById('simulacaoBusca');
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
