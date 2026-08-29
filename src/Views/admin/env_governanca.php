<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';

// Chaves sensíveis — valor mascarado na exibição
$sensivel = ['DB_PASS','MP_ACCESS_TOKEN','MP_PUBLIC_KEY','MP_WEBHOOK_SECRET','PS_TOKEN','SMTP_PASS','ENCRYPTION_KEY','SIMULATION_ADMIN_TOKEN'];

function maskValue(string $chave, string $valor, array $sensivel): string {
    if (!in_array($chave, $sensivel, true)) return $valor;
    if ($valor === '') return '';
    $len = strlen($valor);
    if ($len <= 6) return str_repeat('*', $len);
    return substr($valor, 0, 3) . str_repeat('*', max(4, $len - 6)) . substr($valor, -3);
}

$grupos = [
    'Aplicação' => ['APP_NAME','APP_URL','APP_ENV','APP_DEBUG','HTTPS_ONLY','FORCE_BASEPATH'],
    'Banco de Dados' => ['DB_HOST','DB_NAME','DB_USER','DB_PASS'],
    'Institucional' => ['COMPANY_ADDRESS','COMPANY_WHATSAPP','ADMIN_EMAIL'],
    'Gateway Ativo' => ['PAYMENT_GATEWAY_ACTIVE'],
    'MercadoPago' => [
        'MP_ACCESS_TOKEN','MP_PUBLIC_KEY','MP_WEBHOOK_SECRET','MP_ENV',
        'MP_ACCESS_TOKEN_SANDBOX','MP_ACCESS_TOKEN_PROD',
        'MP_PUBLIC_KEY_SANDBOX','MP_PUBLIC_KEY_PROD',
        'MP_CLIENT_ID_PROD', 'MP_CLIENT_SECRET_PROD'
    ],
    'PagSeguro' => ['PS_EMAIL','PS_TOKEN','PS_ENV'],
    'SMTP / Email' => ['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_FROM_EMAIL','SMTP_FROM_NAME'],
    'Simulado / Testes' => ['SIMULATION_ENABLED','PIX_DRY_RUN','SIMULATION_ADMIN_TOKEN'],
    'Operacional' => ['MAX_PIX_TENTATIVAS','GEOCODING_CACHE_TTL_DAYS','TARIFA_BASE','TARIFA_KM','ENCRYPTION_KEY'],
    'Log do Sistema' => ['SYSTEM_LOG_ENABLED'],
];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-health.css">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Administração</span>
            <h1><i class="fas fa-shield-halved me-2 text-warning"></i>Governança do Ambiente</h1>
            <p>§15 — Edição e auditoria das variáveis de ambiente</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo $bp; ?>/admin/env/auditoria" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-clock-rotate-left me-1"></i>Histórico
            </a>
            <a href="<?php echo $bp; ?>/admin/health" class="btn btn-outline-success btn-sm">
                <i class="fas fa-heart-pulse me-1"></i>Health Check
            </a>
        </div>
    </header>

    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg === 'salvo' ? 'success' : 'danger'; ?> alert-dismissible fade show">
        <?php if ($msg === 'salvo'): ?>
            <i class="fas fa-check-circle me-2"></i>Ambiente salvo com sucesso e auditado.
        <?php elseif ($msg === 'erro_validacao'): ?>
            <i class="fas fa-xmark me-2"></i>Ambiente rejeitado por validação de segurança.
            <?php if (!empty($envErrors)): ?>
                <div class="small mt-2"><?php echo htmlspecialchars(implode('; ', $envErrors)); ?></div>
            <?php endif; ?>
        <?php elseif ($msg === 'erro_parse'): ?>
            <i class="fas fa-xmark me-2"></i>Erro de validação: arquivo .env inválido. Alterações revertidas.
        <?php elseif ($msg === 'erro_write'): ?>
            <i class="fas fa-xmark me-2"></i>Erro ao gravar arquivo. Verifique permissões.
        <?php else: ?>
            <i class="fas fa-xmark me-2"></i>Erro ao salvar: <?php echo htmlspecialchars($msg); ?>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php
    $auditCritical = $runtimeAudit['critical'] ?? [];
    $auditWarnings = $runtimeAudit['warnings'] ?? [];
    $auditInfos = $runtimeAudit['infos'] ?? [];
    $auditRecommendations = $runtimeAudit['recommendations'] ?? [];
    $runtimeInfo = $runtimeAudit['runtime'] ?? [];
    ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon--erro"><i class="fas fa-shield-virus"></i></div>
                <div class="stat-value stat-value--erro"><?php echo count($auditCritical); ?></div>
                <div class="stat-label">Críticos</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon--aviso"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stat-value stat-value--aviso"><?php echo count($auditWarnings); ?></div>
                <div class="stat-label">Avisos</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon stat-icon--ok"><i class="fas fa-circle-check"></i></div>
                <div class="stat-value stat-value--ok"><?php echo count($auditInfos); ?></div>
                <div class="stat-label">Sinais OK</div>
            </div>
        </div>
    </div>

    <?php if (!empty($auditCritical) || !empty($auditWarnings) || !empty($auditRecommendations)): ?>
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="fas fa-user-shield me-2"></i>Diagnóstico de Segurança</div>
        <div class="card-body">
            <?php if (!empty($auditCritical)): ?>
            <div class="alert alert-danger mb-3">
                <strong>Crítico</strong><br>
                <?php echo htmlspecialchars(implode(' | ', $auditCritical)); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($auditWarnings)): ?>
            <div class="alert alert-warning mb-3">
                <strong>Avisos</strong><br>
                <?php echo htmlspecialchars(implode(' | ', $auditWarnings)); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($auditRecommendations)): ?>
            <div class="alert alert-info mb-0">
                <strong>Ações recomendadas</strong><br>
                <?php echo htmlspecialchars(implode(' | ', $auditRecommendations)); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="alert alert-secondary mb-4">
        <strong>Arquivo gerenciado:</strong>
        <code><?php echo htmlspecialchars((string)($runtimeInfo['env_local_path'] ?? '')); ?></code>
    </div>

    <!-- Alerta Gateway Ativo -->
    <div class="alert alert-info d-flex align-items-center mb-4">
        <i class="fas fa-info-circle me-2 fs-5"></i>
        <div>
            <strong>Gateway ativo:</strong>
            <span class="badge bg-success ms-2"><?php echo htmlspecialchars(strtoupper($envAtual['PAYMENT_GATEWAY_ACTIVE'] ?? 'mercadopago')); ?></span>
            &mdash; O cliente não escolhe o gateway. Somente o admin pode alterar via campo abaixo.
        </div>
    </div>

    <form method="POST" action="<?php echo $bp; ?>/admin/env/salvar" id="formEnv">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <?php foreach ($grupos as $nomeGrupo => $chaves): ?>
        <div class="card mb-4">
            <div class="card-header fw-bold">
                <i class="fas fa-<?php echo match($nomeGrupo) {
                    'Aplicação' => 'globe',
                    'Banco de Dados' => 'database',
                    'Institucional' => 'building',
                    'Gateway Ativo' => 'credit-card',
                    'MercadoPago' => 'money-bill-wave',
                    'PagSeguro' => 'shield',
                    'SMTP / Email' => 'envelope',
                    'Simulado / Testes' => 'flask',
                    'Operacional' => 'sliders',
                    'Log do Sistema' => 'file-lines',
                    default => 'cog'
                }; ?> me-2"></i><?php echo htmlspecialchars($nomeGrupo); ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                <?php foreach ($chaves as $chave): ?>
                <?php
                $valorAtual = $envAtual[$chave] ?? '';
                $ehSensivel = in_array($chave, $sensivel, true);
                $mascarado  = maskValue($chave, $valorAtual, $sensivel);
                $ehGateway  = ($chave === 'PAYMENT_GATEWAY_ACTIVE');
                $ehBool     = in_array($chave, ['APP_DEBUG','HTTPS_ONLY','SIMULATION_ENABLED','PIX_DRY_RUN','SYSTEM_LOG_ENABLED'], true);
                ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">
                        <?php echo htmlspecialchars($chave); ?>
                        <?php if ($ehSensivel): ?>
                            <span class="badge bg-warning text-dark ms-1 small"><i class="fas fa-eye-slash"></i> sensível</span>
                        <?php endif; ?>
                    </label>

                    <?php if ($ehGateway): ?>
                    <select name="env[<?php echo $chave; ?>]" class="form-select">
                        <option value="mercadopago" <?php echo ($valorAtual === 'mercadopago') ? 'selected' : ''; ?>>MercadoPago</option>
                        <option value="pagseguro"   <?php echo ($valorAtual === 'pagseguro')   ? 'selected' : ''; ?>>PagSeguro</option>
                    </select>
                    <div class="form-text text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Trocar impacta novos pedidos imediatamente.</div>

                    <?php elseif ($ehBool): ?>
                    <select name="env[<?php echo $chave; ?>]" class="form-select">
                        <option value="true"  <?php echo ($valorAtual === 'true')  ? 'selected' : ''; ?>>true</option>
                        <option value="false" <?php echo ($valorAtual === 'false') ? 'selected' : ''; ?>>false</option>
                    </select>
                    <?php if ($chave === 'SYSTEM_LOG_ENABLED'): ?>
                    <div class="form-text"><i class="fas fa-circle-info me-1"></i>Kill switch total: desligado, nada é gravado em <code>logs/app-*.jsonl</code> nem em <code>app_logs</code> (nenhum nível, incluindo erro). Mantenha ligado em desenvolvimento; em produção o recomendado é deixar desligado e ligar só durante janelas de manutenção periódica.</div>
                    <?php endif; ?>

                    <?php elseif ($ehSensivel): ?>
                    <div class="input-group">
                        <input type="password" class="form-control font-monospace"
                               name="env[<?php echo $chave; ?>]"
                               placeholder="<?php echo htmlspecialchars($mascarado ?: 'não definido'); ?>"
                               value=""
                               autocomplete="new-password">
                        <button type="button" class="btn btn-outline-secondary btn-toggle-pass" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="form-text">Deixe em branco para manter o valor atual.</div>

                    <?php else: ?>
                    <input type="text" class="form-control font-monospace"
                           name="env[<?php echo $chave; ?>]"
                           value="<?php echo htmlspecialchars($valorAtual); ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex gap-3 justify-content-end mb-5">
            <a href="<?php echo $bp; ?>/admin/dashboard" class="btn btn-secondary">Cancelar</a>
            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalConfirmar">
                <i class="fas fa-floppy-disk me-1"></i>Salvar e Auditar
            </button>
        </div>
    </form>

    <!-- Modal confirmação -->
    <div class="modal fade" id="modalConfirmar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirmar Alterações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Você está prestes a salvar as variáveis de ambiente do sistema.</p>
                    <p class="text-warning fw-semibold">⚠ Alterações incorretas podem derrubar a aplicação.</p>
                    <p>Um registro de auditoria será gravado com seu usuário, horário e campos alterados.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" data-submit-form="formEnv">
                        <i class="fas fa-floppy-disk me-1"></i>Confirmar e Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>

<script<?php echo csp_script_nonce_attr(); ?>>
// Toggle mostrar/ocultar campo sensível
document.querySelectorAll('.btn-toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
        const inp = btn.previousElementSibling;
        if (inp.type === 'password') {
            inp.type = 'text';
            btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
            inp.type = 'password';
            btn.innerHTML = '<i class="fas fa-eye"></i>';
        }
    });
});
</script>
