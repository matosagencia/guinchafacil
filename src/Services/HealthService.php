<?php

declare(strict_types=1);

require_once __DIR__ . '/Security/ConfigSecurityService.php';
require_once __DIR__ . '/CronMonitorService.php';

/**
 * §HEALTH-01 — Verificação de saúde dos subsistemas do GuinchaFácil.
 *
 * Cada check retorna:
 *   ok:     bool   — true = verde / false = amarelo ou vermelho
 *   label:  string — nome legível do subsistema
 *   status: string — texto curto do resultado
 *   info:   string — detalhe para diagnóstico
 *   nivel:  'ok'|'aviso'|'erro'
 */
class HealthService
{
    /** @return array<string, array{ok:bool, label:string, status:string, info:string, nivel:string}> */
    public static function runAll(): array
    {
        return [
            // §19 — Domínios mínimos
            'db'          => self::checkDb(),
            'schema'      => self::checkSchema(),
            'auth'        => self::checkAuth(),
            'csp'         => self::checkCsp(),
            'webhook'     => self::checkWebhook(),
            'pix'         => self::checkPix(),
            'gateway'     => self::checkGateway(),
            'geocoding'   => self::checkGeocoding(),
            'cliente'     => self::checkCliente(),
            'guincheiro'  => self::checkGuincheiro(),
            'pedido'      => self::checkPedido(),
            'chat'        => self::checkChat(),
            'cron'        => self::checkCron(),
            'retencao'    => self::checkRetention(),
            'carteira'    => self::checkCarteira(),
            'saques'      => self::checkSaques(),
            'env'         => self::checkEnv(),
            'simulador'   => self::checkSimulador(),
            'notificacoes'=> self::checkNotificacoes(),
        ];
    }

    /** @return array<int, array{code:string,label:string,category:string,status:string,nivel:string,detail:string,action:string}> */
    public static function productionChecklist(): array
    {
        $checks = self::runAll();
        $runtimeAudit = ConfigSecurityService::auditManagedEnvironment(dirname(__DIR__, 2), $_ENV);
        $cronSummary = CronMonitorService::summarizeExpectedJobs();
        $retentionConfig = Configuracao::getMultiplas([
            'retention_simulation_artifacts_days',
            'retention_simulation_runs_days',
            'retention_jsonl_logs_days',
            'retention_cron_executions_days',
            'retention_por_days',
            'retention_evidencias_days',
            'retention_chat_days',
        ]);

        $items = [];

        $criticalSecretsOk = empty($runtimeAudit['critical'] ?? []);
        $items[] = self::checklistItem(
            'secrets',
            'Segredos e .env gerenciado',
            'Ambiente',
            $criticalSecretsOk ? 'ok' : 'erro',
            $criticalSecretsOk
                ? 'Sem falhas críticas na auditoria do ambiente.'
                : implode('. ', array_slice($runtimeAudit['critical'] ?? ['Falhas críticas no ambiente.'], 0, 4)),
            $criticalSecretsOk
                ? 'Manter rotação e auditoria periódica de segredos.'
                : 'Corrigir chaves obrigatórias, placeholders e permissões do .env antes do deploy.'
        );

        $schedulerOk = (bool)($cronSummary['ok'] ?? false);
        $items[] = self::checklistItem(
            'scheduler',
            'Scheduler de produção',
            'Operação',
            $schedulerOk ? 'ok' : 'erro',
            (string)($cronSummary['info'] ?? 'Catálogo de cron indisponível.'),
            $schedulerOk
                ? 'Validar primeira execução no servidor após deploy.'
                : 'Cadastrar/validar todos os cron jobs reais no servidor e confirmar heartbeat.'
        );

        $retentionMissing = [];
        foreach ([
            'retention_simulation_artifacts_days',
            'retention_simulation_runs_days',
            'retention_jsonl_logs_days',
            'retention_cron_executions_days',
            'retention_por_days',
            'retention_evidencias_days',
            'retention_chat_days',
        ] as $key) {
            if (!isset($retentionConfig[$key]) || trim((string)$retentionConfig[$key]) === '') {
                $retentionMissing[] = $key;
            }
        }
        $retentionHealth = $checks['retencao']['nivel'] ?? 'aviso';
        $retentionOk = empty($retentionMissing) && $retentionHealth === 'ok';
        $items[] = self::checklistItem(
            'retention',
            'Retenção e limpeza operacional',
            'Operação',
            $retentionOk ? 'ok' : ($retentionHealth === 'erro' ? 'erro' : 'aviso'),
            $retentionOk
                ? 'Políticas de retenção configuradas e cron operacional monitorado.'
                : (!empty($retentionMissing)
                    ? 'Configurações ausentes: ' . implode(', ', $retentionMissing)
                    : (string)($checks['retencao']['info'] ?? 'Retenção ainda não comprovada.')),
            $retentionOk
                ? 'Acompanhar crescimento de artefatos e logs.'
                : 'Fechar retenção configurada e validar cron de limpeza em produção.'
        );

        $externalWarnings = [];
        foreach (['gateway', 'pix', 'geocoding', 'notificacoes'] as $key) {
            if (($checks[$key]['nivel'] ?? 'erro') !== 'ok') {
                $externalWarnings[] = $checks[$key]['label'] ?? $key;
            }
        }
        $externalOk = $externalWarnings === [];
        $items[] = self::checklistItem(
            'external_dependencies',
            'Dependências externas',
            'Integrações',
            $externalOk ? 'ok' : 'aviso',
            $externalOk
                ? 'Gateway, PIX, geocoding e notificações estão configurados.'
                : 'Dependências com pendência: ' . implode(', ', $externalWarnings),
            $externalOk
                ? 'Monitorar SLA e credenciais dos provedores.'
                : 'Validar credenciais, rotas externas e fallback operacional antes do go-live.'
        );

        $qaSuiteFiles = [
            'qa/suites/por-antifraude.spec.ts',
            'qa/suites/concorrencia-aceite.spec.ts',
            'qa/suites/pagamento-sandbox.spec.ts',
            'qa/suites/upload-seguranca.spec.ts',
        ];
        $missingQaSuites = [];
        foreach ($qaSuiteFiles as $relativePath) {
            $absolutePath = dirname(__DIR__, 2) . '/' . str_replace('\\', '/', $relativePath);
            if (!is_file($absolutePath)) {
                $missingQaSuites[] = basename($relativePath);
            }
        }
        $qaArtifactsDir = dirname(__DIR__, 2) . '/files/qa-runs';
        $qaArtifactsCount = self::countDirectoryEntries($qaArtifactsDir);
        $qaRuntime = self::qaEvidenceStatus();
        $qaOk = empty($missingQaSuites) && ($qaRuntime['nivel'] ?? 'aviso') === 'ok';
        $qaNivel = !empty($missingQaSuites) ? 'erro' : (string)($qaRuntime['nivel'] ?? 'aviso');
        $items[] = self::checklistItem(
            'qa_artifacts',
            'QA crítico e artefatos',
            'Validação',
            $qaNivel,
            !empty($missingQaSuites)
                ? 'Suítes ausentes: ' . implode(', ', $missingQaSuites)
                : trim(($qaRuntime['info'] ?? '') . " Diretório de artefatos QA: {$qaArtifactsCount} item(ns)."),
            $qaOk
                ? 'Anexar os artefatos da última execução válida ao pacote de deploy.'
                : (!empty($missingQaSuites)
                    ? 'Adicionar as suítes críticas faltantes ao runner oficial.'
                    : 'Executar e validar as suítes críticas com pedidos/credenciais reais antes do deploy.')
        );

        $hardeningIssues = [];
        $cspIssue = self::cspHasUnsafeInlineAttr();
        if ($cspIssue) {
            $hardeningIssues[] = "CSP ainda permite script-src-attr 'unsafe-inline'";
        }
        if ((defined('APP_ENV') ? (string)APP_ENV : 'production') === 'development') {
            $hardeningIssues[] = 'APP_ENV=development';
        }
        if (defined('APP_DEBUG') && APP_DEBUG && (!defined('APP_ENV') || APP_ENV === 'production')) {
            $hardeningIssues[] = 'APP_DEBUG=true em produção';
        }
        if (defined('APP_URL') && is_string(APP_URL) && APP_URL !== '' && (!defined('APP_ENV') || APP_ENV === 'production')) {
            if (stripos(APP_URL, 'https://') !== 0) {
                $hardeningIssues[] = 'APP_URL sem HTTPS';
            }
        }
        if (ini_get('display_errors') === '1' && (!defined('APP_ENV') || APP_ENV === 'production')) {
            $hardeningIssues[] = 'display_errors=1 em produção';
        }
        $hardeningStatus = empty($hardeningIssues) ? 'ok' : 'aviso';
        $items[] = self::checklistItem(
            'hardening',
            'Endurecimento do ambiente',
            'Segurança',
            $hardeningStatus,
            empty($hardeningIssues)
                ? 'Sem fragilidades principais detectadas no hardening.'
                : implode('. ', $hardeningIssues),
            empty($hardeningIssues)
                ? 'Manter revisão após cada mudança de frontend ou infra.'
                : 'Remover handlers inline restantes, revisar CSP final e confirmar flags de produção.'
        );

        return $items;
    }

    // ── Checks ────────────────────────────────────────────────────────────────

    private static function checkDb(): array
    {
        try {
            $pdo     = getPDO();
            // Portabilidade: em MySQL usa @@version/SHOW TABLES; em SQLite
            // (harness de teste) usa as equivalentes, senão o check marca o
            // banco como 'erro' por sintaxe MySQL-only num ambiente íntegro.
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $version = (string)$pdo->query('SELECT sqlite_version()')->fetchColumn();
                $tables  = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
                return self::ok('Banco de Dados', 'conectado',
                    "SQLite {$version} — " . count($tables) . " tabela(s)");
            }
            $version = (string)$pdo->query("SELECT @@version")->fetchColumn();
            $tables  = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            return self::ok('Banco de Dados', 'conectado',
                "MySQL {$version} — " . count($tables) . " tabela(s)");
        } catch (Throwable $e) {
            return self::fail('Banco de Dados', 'desconectado', $e->getMessage());
        }
    }

    private static function checkAuth(): array
    {
        $issues = [];

        if (!defined('ENCRYPTION_KEY') || (string)ENCRYPTION_KEY === '') {
            $issues[] = 'ENCRYPTION_KEY vazia';
        }
        // As flags de sessão só se aplicam ao SAPI web — em CLI (PHPUnit) o
        // config.php não consegue setá-las (headers já enviados), então não
        // fazem sentido como sinal de saúde aqui.
        if (PHP_SAPI !== 'cli') {
            if (ini_get('session.use_strict_mode') !== '1') {
                $issues[] = 'session.use_strict_mode desabilitado';
            }
            if (ini_get('session.cookie_httponly') !== '1') {
                $issues[] = 'session.cookie_httponly desabilitado';
            }
        }

        if (!empty($issues)) {
            return self::warn('Autenticação', 'aviso', implode('; ', $issues));
        }

        $keyLen = strlen((string)(defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : ''));
        return self::ok('Autenticação', 'configurado',
            "ENCRYPTION_KEY={$keyLen} chars. Sessão segura (strict_mode + httponly).");
    }

    private static function checkSchema(): array
    {
        $required = [
            'schema_migrations' => [
                'version', 'filename', 'checksum_sha256', 'applied_at', 'success',
            ],
            'pedidos' => [
                'status', 'expiracao_aceite', 'raio_atual_km', 'score_minimo_atual',
                'foto_plataforma', 'foto_destino', 'cancelado_por', 'taxa_cancelamento',
            ],
            'pagamentos' => [
                'metodo', 'valor_total', 'valor_guincho', 'valor_plataforma',
                'status_pix', 'id_transacao_pix',
            ],
            'payment_jobs' => [
                'pedido_id', 'pagamento_id', 'job_type', 'idempotency_key', 'status',
                'attempt_count', 'max_attempts', 'available_at',
            ],
            'pedido_localizacoes' => [
                'pedido_id', 'guincho_id', 'fase', 'sequence_number', 'client_point_id',
                'distance_accumulated_m', 'street_name', 'is_valid', 'server_timestamp',
            ],
            'pedido_evidencias' => [
                'pedido_id', 'guincho_id', 'tipo', 'status', 'nonce_token',
                'point_id', 'stored_name', 'sha256',
            ],
            'simulation_runs' => [
                'run_id', 'engine', 'suite', 'status', 'worker_id', 'heartbeat_at',
            ],
            'simulation_steps' => [
                'run_id', 'phase', 'code', 'status', 'duration_ms',
            ],
            'simulation_artifacts' => [
                'run_id', 'step_id', 'kind', 'private_path',
            ],
        ];

        try {
            $pdo = getPDO();
            // A validação de schema usa DATABASE()/information_schema (MySQL).
            // No harness SQLite (que só cria um subconjunto curado de tabelas)
            // ela não é aplicável — reporta ok em vez de erro de sintaxe.
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                return self::ok('Schema', 'ok', 'Validação de schema não se aplica ao ambiente SQLite (harness de teste).');
            }
            $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $issues = [];
            $warnings = [];
            $checkedColumns = 0;

            foreach ($required as $table => $columns) {
                $stmtTable = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?"
                );
                $stmtTable->execute([$dbName, $table]);
                if ((int)$stmtTable->fetchColumn() === 0) {
                    $issues[] = "tabela {$table} ausente";
                    continue;
                }

                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $stmtCols = $pdo->prepare(
                    "SELECT column_name FROM information_schema.columns
                     WHERE table_schema = ? AND table_name = ? AND column_name IN ({$placeholders})"
                );
                $stmtCols->execute(array_merge([$dbName, $table], $columns));
                $existing = array_map('strval', $stmtCols->fetchAll(PDO::FETCH_COLUMN));
                $checkedColumns += count($columns);

                foreach ($columns as $column) {
                    if (!in_array($column, $existing, true)) {
                        $issues[] = "{$table}.{$column} ausente";
                    }
                }
            }

            if (!empty($issues)) {
                return self::fail('Schema crítico', 'incompleto', implode('; ', $issues));
            }

            $expectedMigrations = [
                'migration_observability_v2.sql',
                'migration_por_v1.sql',
                'migration_evidencias_v1.sql',
                'migration_simulation_runner_v2.sql',
                'migration_payment_jobs_v1.sql',
                'migration_schema_versions.sql',
            ];

            $missingMigrations = self::detectMissingSchemaMigrations($pdo, $expectedMigrations);
            if (!empty($missingMigrations)) {
                $warnings[] = 'migrations pendentes: ' . implode(', ', $missingMigrations);
            }

            $info = "{$checkedColumns} coluna(s) críticas verificadas.";
            if (!empty($warnings)) {
                return self::warn('Schema crítico', 'atenção', $info . ' ' . implode('; ', $warnings));
            }

            return self::ok('Schema crítico', 'ok', $info);
        } catch (Throwable $e) {
            return self::fail('Schema crítico', 'erro', $e->getMessage());
        }
    }

    private static function checkCsp(): array
    {
        $issues = [];
        $infos  = [];

        $env       = defined('APP_ENV')    ? (string)APP_ENV    : 'production';
        $httpsOnly = defined('HTTPS_ONLY') && HTTPS_ONLY;

        if ($env === 'production' && !$httpsOnly) {
            $issues[] = 'HTTPS_ONLY=false em produção';
        }
        if ($env === 'development') {
            $issues[] = "APP_ENV=development (não usar em produção)";
        }
        if (ini_get('display_errors') === '1' && $env === 'production') {
            $issues[] = 'display_errors=1 em produção';
        }

        if (self::cspHasUnsafeInlineAttr()) {
            $infos[] = "script-src-attr ainda permite 'unsafe-inline' para handlers legados";
        } else {
            $infos[] = 'CSP sem handlers inline permitidos';
        }

        if (!empty($issues)) {
            return self::warn('Segurança / CSP', 'aviso',
                implode('; ', array_merge($issues, $infos)));
        }
        return self::ok('Segurança / CSP', 'ok',
            "APP_ENV={$env}" . ($httpsOnly ? ', HTTPS_ONLY=true' : '') . '. ' . implode('. ', $infos));
    }

    private static function checkWebhook(): array
    {
        $issues = [];
        $infos  = [];

        if (!defined('MP_WEBHOOK_SECRET') || (string)MP_WEBHOOK_SECRET === '') {
            $issues[] = 'MP_WEBHOOK_SECRET não configurado (HMAC-SHA256 desabilitado)';
        } else {
            $infos[] = 'Secret HMAC-SHA256 configurado';
        }

        try {
            $count   = (int)getPDO()->query("SELECT COUNT(*) FROM logs_webhook")->fetchColumn();
            $infos[] = "logs_webhook: {$count} registro(s)";
        } catch (Throwable) {
            $issues[] = 'tabela logs_webhook inacessível';
        }

        if (!empty($issues)) {
            $nivel = (defined('MP_WEBHOOK_SECRET') && (string)MP_WEBHOOK_SECRET !== '')
                ? 'aviso' : 'erro';
            return ['ok' => false, 'label' => 'Webhook', 'status' => 'pendente',
                'info' => implode('. ', array_merge($issues, $infos)), 'nivel' => $nivel];
        }
        return self::ok('Webhook', 'ok', implode('. ', $infos));
    }

    private static function checkPix(): array
    {
        $issues = [];
        $infos  = [];

        if (!defined('MP_ACCESS_TOKEN') || (string)MP_ACCESS_TOKEN === '') {
            $issues[] = 'MP_ACCESS_TOKEN ausente';
        } else {
            $len     = strlen((string)MP_ACCESS_TOKEN);
            $infos[] = "MP_ACCESS_TOKEN presente ({$len} chars)";
        }
        if (!defined('MP_PUBLIC_KEY') || (string)MP_PUBLIC_KEY === '') {
            $issues[] = 'MP_PUBLIC_KEY ausente';
        } else {
            $infos[] = 'MP_PUBLIC_KEY presente';
        }

        $maxTent = defined('MAX_PIX_TENTATIVAS') ? (int)MAX_PIX_TENTATIVAS : 5;
        $infos[] = "MAX_PIX_TENTATIVAS={$maxTent}";

        if (!empty($issues)) {
            return self::fail('Pix / MercadoPago', 'pendente',
                implode('. ', array_merge($issues, $infos)));
        }
        return self::ok('Pix / MercadoPago', 'configurado', implode('. ', $infos));
    }

    private static function checkGeocoding(): array
    {
        $ttl = defined('GEOCODING_CACHE_TTL_DAYS') ? (int)GEOCODING_CACHE_TTL_DAYS : 30;
        try {
            $count = (int)getPDO()->query("SELECT COUNT(*) FROM geocoding_cache")->fetchColumn();
            return self::ok('Geocoding Cache', 'ok', "TTL={$ttl}d. Entradas em cache: {$count}");
        } catch (Throwable) {
            return self::warn('Geocoding Cache', 'sem tabela',
                "TTL={$ttl}d. Tabela geocoding_cache não existe (criada no primeiro uso).");
        }
    }

    /**
     * §HEALTH-DOMINIO-CLIENTE-01: domínio "cliente" exigido pela
     * constituição (§19.1) e ainda ausente do runAll() — conta usuários
     * cliente cadastrados/ativos. Não é um KPI de produto, só um sinal
     * mínimo de saúde: se a tabela some ou zera silenciosamente, precisa
     * aparecer aqui antes de virar reclamação de cliente real.
     */
    private static function checkCliente(): array
    {
        try {
            $total  = (int)getPDO()->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'")->fetchColumn();
            $ativos = (int)getPDO()->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente' AND ativo = 1")->fetchColumn();
            return self::ok('Clientes', 'ok', "{$total} cadastrado(s), {$ativos} ativo(s)");
        } catch (Throwable $e) {
            return self::fail('Clientes', 'erro', $e->getMessage());
        }
    }

    /**
     * §HEALTH-DOMINIO-GUINCHEIRO-01: domínio "guincheiro" exigido pela
     * constituição (§19.1). Sinal operacional real: se não há nenhum
     * guincheiro aprovado e disponível, novos pedidos não têm quem
     * aceitar — isso é 'aviso', não 'erro' (pode ser fora do horário de
     * operação), mas precisa aparecer no health.
     */
    private static function checkGuincheiro(): array
    {
        try {
            $total       = (int)getPDO()->query("SELECT COUNT(*) FROM guinchos")->fetchColumn();
            $aprovados   = (int)getPDO()->query("SELECT COUNT(*) FROM guinchos WHERE aprovado = 1")->fetchColumn();
            $disponiveis = (int)getPDO()->query("SELECT COUNT(*) FROM guinchos WHERE aprovado = 1 AND disponivel = 1")->fetchColumn();
            $info = "{$total} cadastrado(s), {$aprovados} aprovado(s), {$disponiveis} disponível/eis agora";

            if ($aprovados === 0) {
                return self::fail('Guincheiros', 'sem aprovados', $info);
            }
            if ($disponiveis === 0) {
                return self::warn('Guincheiros', 'nenhum disponível agora', $info);
            }
            return self::ok('Guincheiros', 'ok', $info);
        } catch (Throwable $e) {
            return self::fail('Guincheiros', 'erro', $e->getMessage());
        }
    }

    /**
     * §HEALTH-DOMINIO-PEDIDO-01: domínio "pedido" exigido pela
     * constituição (§19.1). Além da contagem básica, sinaliza checkouts
     * abandonados — pedido parado em aguardando_pagamento por mais de 2h
     * é, na prática, um checkout que o cliente nunca terminou (ou um
     * webhook que nunca chegou), e isso é diferente do check de
     * 'aguardando_guincho' expirado que já existe em checkCron().
     */
    private static function checkPedido(): array
    {
        try {
            $total = (int)getPDO()->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
            $hoje  = (int)getPDO()->query("SELECT COUNT(*) FROM pedidos WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
            $checkoutAbandonado = (int)getPDO()->query(
                "SELECT COUNT(*) FROM pedidos
                 WHERE status = 'aguardando_pagamento'
                   AND criado_em < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
            )->fetchColumn();

            $info = "{$total} pedido(s) no total, {$hoje} hoje";
            if ($checkoutAbandonado > 0) {
                return self::warn('Pedidos', 'checkout(s) abandonado(s)',
                    "{$info}. {$checkoutAbandonado} pedido(s) parado(s) em aguardando_pagamento há mais de 2h.");
            }
            return self::ok('Pedidos', 'ok', $info);
        } catch (Throwable $e) {
            return self::fail('Pedidos', 'erro', $e->getMessage());
        }
    }

    private static function checkChat(): array
    {
        try {
            $total   = (int)getPDO()->query("SELECT COUNT(*) FROM chat_mensagens")->fetchColumn();
            $recente = (int)getPDO()->query(
                "SELECT COUNT(*) FROM chat_mensagens
                 WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn();
            return self::ok('Chat', 'ok',
                "Total: {$total} msg. Últimas 24h: {$recente}");
        } catch (Throwable $e) {
            return self::warn('Chat', 'sem tabela',
                "chat_mensagens inacessível: " . $e->getMessage());
        }
    }

    private static function checkCron(): array
    {
        $scheduler = CronMonitorService::summarizeExpectedJobs();
        if (!$scheduler['ok']) {
            return $scheduler;
        }

        try {
            $travados = (int)getPDO()->query(
                "SELECT COUNT(*) FROM pedidos
                 WHERE status = 'aguardando_guincho'
                   AND expiracao_aceite IS NOT NULL
                   AND expiracao_aceite < NOW()"
            )->fetchColumn();

            if ($travados > 0) {
                return self::warn('Cron / Expiração', 'aviso',
                    "{$travados} pedido(s) expirado(s) sem processamento. {$scheduler['info']}");
            }
            return self::ok('Cron / Expiração', 'ok',
                'Nenhum pedido expirado aguardando processamento. ' . $scheduler['info']);
        } catch (Throwable $e) {
            return self::fail('Cron / Expiração', 'erro', $e->getMessage());
        }
    }

    private static function checkRetention(): array
    {
        try {
            $issues = [];
            $infos = [];

            $counts = [
                'qa_runs_dir' => self::countDirectoryEntries(dirname(__DIR__, 2) . '/files/qa-runs'),
                'playwright_results' => self::countDirectoryEntries(dirname(__DIR__, 2) . '/qa/test-results'),
                'playwright_report' => self::countDirectoryEntries(dirname(__DIR__, 2) . '/qa/playwright-report'),
            ];

            foreach ($counts as $label => $count) {
                $infos[] = "{$label}: {$count}";
            }

            if (self::tableExistsSafe('simulation_artifacts')) {
                $artifactCount = (int)getPDO()->query("SELECT COUNT(*) FROM simulation_artifacts")->fetchColumn();
                $infos[] = "simulation_artifacts: {$artifactCount}";
                if ($artifactCount > 5000) {
                    $issues[] = 'simulation_artifacts acima de 5000 registros';
                }
            }

            if (self::tableExistsSafe('cron_jobs')) {
                $stmt = getPDO()->prepare("SELECT ultima_execucao_status, heartbeat_at FROM cron_jobs WHERE job_code = 'cron_retencao_operacional' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $issues[] = 'cron_retencao_operacional não cadastrado';
                } elseif ((string)($row['ultima_execucao_status'] ?? '') === 'error') {
                    $issues[] = 'cron_retencao_operacional com erro na última execução';
                }
            }

            if (!empty($issues)) {
                return self::warn('Retenção operacional', 'atenção', implode('. ', array_merge($issues, $infos)));
            }

            return self::ok('Retenção operacional', 'ok', implode('. ', $infos));
        } catch (Throwable $e) {
            return self::warn('Retenção operacional', 'incompleto', $e->getMessage());
        }
    }

    private static function checkGateway(): array
    {
        $gateway = defined('PAYMENT_GATEWAY_ACTIVE') ? (string)PAYMENT_GATEWAY_ACTIVE : '';
        if (!in_array($gateway, ['mercadopago', 'pagseguro'], true)) {
            return self::fail('Gateway de Cobrança', 'inválido',
                "PAYMENT_GATEWAY_ACTIVE='{$gateway}' — deve ser 'mercadopago' ou 'pagseguro'");
        }

        $infos = ["Gateway ativo: {$gateway}"];

        if ($gateway === 'mercadopago') {
            if (!defined('MP_ACCESS_TOKEN') || (string)MP_ACCESS_TOKEN === '') {
                return self::fail('Gateway de Cobrança', 'MP sem token', "PAYMENT_GATEWAY_ACTIVE=mercadopago mas MP_ACCESS_TOKEN ausente");
            }
            $infos[] = 'MP_ACCESS_TOKEN presente';
        } else {
            if (!defined('PS_TOKEN') || (string)PS_TOKEN === '') {
                return self::fail('Gateway de Cobrança', 'PS sem token', "PAYMENT_GATEWAY_ACTIVE=pagseguro mas PS_TOKEN ausente");
            }
            $infos[] = 'PS_TOKEN presente';
        }

        return self::ok('Gateway de Cobrança', 'configurado', implode('. ', $infos));
    }

    /**
     * §ESCOPO-CARTEIRA-01 (decisão 20/07, ratificada 22/07): a carteira
     * financeira do guincheiro (saldo em_compensacao/liberado + saque manual)
     * foi decidida como FORA DE ESCOPO — o repasse é automático via
     * PaymentJobService::enqueuePixPayout() ao concluir o pedido, e a
     * resolução manual de falha de repasse é feita pelo fluxo de Demanda
     * (tipo 'pagamento' -> DemandaService::executar() -> PaymentJobService::forceRetry(),
     * decidido por admin ou por dois gerentes distintos quando exige dupla
     * aprovação). As tabelas guincheiro_carteira/guincheiro_movimentos/
     * pagamento_liquidacoes/saques_guincheiro/saque_eventos podem existir ou
     * não no banco (histórico de database/migrate_fase2.sql) mas não são mais
     * lidas pela lógica de negócio — não é um bloqueador de saúde do sistema.
     * Ver doc/CONSTITUICAO_ATUALIZACAO_SECAO6_2026-07-18.md, linhas 42/45/46.
     */
    private static function checkCarteira(): array
    {
        return self::ok('Carteira Financeira', 'suprimido',
            'Funcionalidade suprimida por decisão de escopo (repasse automático via PaymentJobService + resolução manual via Demanda tipo "pagamento"). Não é um bloqueador.');
    }

    private static function checkSaques(): array
    {
        return self::ok('Saques', 'suprimido',
            'Não há fluxo de solicitação de saque — repasse é automático ao concluir o pedido, com resolução manual de falha via Demanda tipo "pagamento" (admin ou dois gerentes). Não é um bloqueador.');
    }

    private static function checkEnv(): array
    {
        $issues = [];
        $warnings = [];
        $infos  = [];
        $audit = ConfigSecurityService::auditManagedEnvironment(dirname(__DIR__, 2), $_ENV);

        $obrigatorias = ['DB_HOST','DB_NAME','DB_USER','ENCRYPTION_KEY','APP_URL','PAYMENT_GATEWAY_ACTIVE'];
        foreach ($obrigatorias as $k) {
            if (!defined($k) || constant($k) === '' || constant($k) === null) {
                $issues[] = "{$k} ausente";
            }
        }

        // Governança: tabela env_auditoria acessível?
        try {
            $total = (int)getPDO()->query("SELECT COUNT(*) FROM env_auditoria")->fetchColumn();
            $infos[] = "env_auditoria: {$total} registro(s)";
        } catch (Throwable) {
            $issues[] = "tabela env_auditoria inacessível — execute a migração de Fase 2";
        }

        $issues = array_merge($issues, $audit['critical'] ?? []);
        $warnings = array_merge($warnings, $audit['warnings'] ?? []);
        $infos = array_merge($infos, $audit['infos'] ?? []);

        if (!empty($issues)) {
            return self::fail('Governança .env', 'pendente', implode('. ', array_merge($issues, $warnings, $infos)));
        }
        if (!empty($warnings)) {
            return self::warn('Governança .env', 'atenção', implode('. ', array_merge($warnings, $infos)));
        }
        return self::ok('Governança .env', 'ok', implode('. ', $infos));
    }

    private static function checkSimulador(): array
    {
        $enabled = defined('SIMULATION_ENABLED') ? SIMULATION_ENABLED : false;
        $infos   = ['SIMULATION_ENABLED=' . ($enabled ? 'true' : 'false')];

        try {
            $total = (int)getPDO()->query("SELECT COUNT(*) FROM simulation_runs")->fetchColumn();
            $ok    = (int)getPDO()->query("SELECT COUNT(*) FROM simulation_runs WHERE status='completed'")->fetchColumn();
            $infos[] = "execuções: {$total} total, {$ok} OK";

            $ultima = getPDO()->query("SELECT run_id, criado_em, status FROM simulation_runs ORDER BY criado_em DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($ultima) {
                $infos[] = "última em {$ultima['criado_em']} — status: {$ultima['status']}";
            }
        } catch (Throwable $e) {
            return self::warn('Simulador', 'sem histórico',
                implode('. ', $infos) . " | simulation_runs inacessível: " . $e->getMessage());
        }

        if (!$enabled) {
            return self::warn('Simulador', 'desabilitado', implode('. ', $infos) . ' — ative SIMULATION_ENABLED=true para executar');
        }
        return self::ok('Simulador', 'habilitado', implode('. ', $infos));
    }

    private static function checkNotificacoes(): array
    {
        $issues = [];
        $infos  = [];

        $smtpFields = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM_EMAIL'];
        foreach ($smtpFields as $f) {
            if (!defined($f) || (string)constant($f) === '') {
                $issues[] = "{$f} ausente";
            }
        }

        if (defined('SMTP_PORT') && SMTP_PORT > 0) {
            $infos[] = "SMTP_PORT=" . SMTP_PORT;
        }
        if (defined('SMTP_FROM_NAME') && (string)SMTP_FROM_NAME !== '') {
            $infos[] = "FROM=" . (string)SMTP_FROM_NAME;
        }

        // Verifica se PHPMailer está disponível
        $mailerPath = dirname(__DIR__, 3) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        if (!is_file($mailerPath)) {
            $issues[] = 'PHPMailer não encontrado em vendor/';
        } else {
            $infos[] = 'PHPMailer presente';
        }

        if (!empty($issues)) {
            return self::warn('Notificações (SMTP)', 'incompleto', implode('. ', array_merge($issues, $infos)));
        }
        return self::ok('Notificações (SMTP)', 'configurado', implode('. ', $infos));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{ok:bool, label:string, status:string, info:string, nivel:string} */
    private static function ok(string $label, string $status, string $info): array
    {
        return ['ok' => true, 'label' => $label, 'status' => $status, 'info' => $info, 'nivel' => 'ok'];
    }

    /** @return array{ok:bool, label:string, status:string, info:string, nivel:string} */
    private static function warn(string $label, string $status, string $info): array
    {
        return ['ok' => false, 'label' => $label, 'status' => $status, 'info' => $info, 'nivel' => 'aviso'];
    }

    /** @return array{ok:bool, label:string, status:string, info:string, nivel:string} */
    private static function fail(string $label, string $status, string $info): array
    {
        return ['ok' => false, 'label' => $label, 'status' => $status, 'info' => $info, 'nivel' => 'erro'];
    }

    /** @return array{code:string,label:string,category:string,status:string,nivel:string,detail:string,action:string} */
    private static function checklistItem(string $code, string $label, string $category, string $nivel, string $detail, string $action): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'category' => $category,
            'status' => match ($nivel) {
                'ok' => 'pronto',
                'erro' => 'bloqueado',
                default => 'pendente',
            },
            'nivel' => $nivel,
            'detail' => $detail,
            'action' => $action,
        ];
    }

    private static function countDirectoryEntries(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $entries = scandir($dir) ?: [];
        return count(array_filter($entries, static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
    }

    private static function tableExistsSafe(string $table): bool
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?"
            );
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function cspHasUnsafeInlineAttr(): bool
    {
        $routerPath = dirname(__DIR__, 2) . '/index.php';
        $contents = @file_get_contents($routerPath);
        if ($contents === false) {
            return true;
        }

        return str_contains($contents, "script-src-attr 'unsafe-inline'");
    }

    /** @return array{ok:bool,label:string,status:string,info:string,nivel:string} */
    private static function qaEvidenceStatus(): array
    {
        $criticalSuites = [
            'por-antifraude',
            'concorrencia-aceite',
            'pagamento-sandbox',
            'upload-seguranca',
        ];

        $qaRoot = dirname(__DIR__, 2) . '/qa';
        $nodeModulesOk = is_dir($qaRoot . '/node_modules/@playwright/test');

        try {
            if (!self::tableExistsSafe('simulation_runs')) {
                return self::warn('QA crítico', 'sem histórico', 'Tabela simulation_runs ausente para evidência QA.');
            }

            $suitePlaceholders = implode(',', array_fill(0, count($criticalSuites), '?'));
            $stmt = getPDO()->prepare(
                "SELECT run_id, suite, status, started_at, finished_at
                   FROM simulation_runs
                  WHERE engine = 'playwright'
                    AND suite IN ({$suitePlaceholders})
                  ORDER BY COALESCE(finished_at, started_at, iniciado_em) DESC
                  LIMIT 20"
            );
            $stmt->execute($criticalSuites);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($rows === []) {
                return self::warn(
                    'QA crítico',
                    'sem execução',
                    'Nenhuma execução Playwright crítica encontrada.'
                    . ($nodeModulesOk ? ' Dependências QA locais presentes.' : ' Dependências QA locais ausentes.')
                );
            }

            $latestCompleted = null;
            foreach ($rows as $row) {
                if (($row['status'] ?? '') === 'completed') {
                    $latestCompleted = $row;
                    break;
                }
            }

            if ($latestCompleted === null) {
                return self::warn(
                    'QA crítico',
                    'sem sucesso',
                    'Há histórico de execuções críticas, mas nenhuma concluída com sucesso.'
                    . ($nodeModulesOk ? ' Runner local disponível.' : ' Runner local indisponível.')
                );
            }

            $artifactCount = 0;
            if (self::tableExistsSafe('simulation_artifacts')) {
                $artifactStmt = getPDO()->prepare("SELECT COUNT(*) FROM simulation_artifacts WHERE run_id = ?");
                $artifactStmt->execute([(string)$latestCompleted['run_id']]);
                $artifactCount = (int)$artifactStmt->fetchColumn();
            }

            $finishedAt = (string)($latestCompleted['finished_at'] ?? $latestCompleted['started_at'] ?? '');
            $ageDays = null;
            if ($finishedAt !== '') {
                $ts = strtotime($finishedAt);
                if ($ts !== false) {
                    $ageDays = (int)floor((time() - $ts) / 86400);
                }
            }

            $issues = [];
            if ($artifactCount <= 0) {
                $issues[] = 'última execução sem artefatos persistidos';
            }
            if ($ageDays !== null && $ageDays > 14) {
                $issues[] = "evidência expirada há {$ageDays} dia(s)";
            }

            $info = sprintf(
                'Última suíte crítica válida: %s (%s)%s. Artefatos: %d.',
                (string)($latestCompleted['suite'] ?? 'desconhecida'),
                $finishedAt !== '' ? $finishedAt : 'sem data',
                $ageDays !== null ? " há {$ageDays} dia(s)" : '',
                $artifactCount
            );
            if ($nodeModulesOk) {
                $info .= ' Runner local presente.';
            } else {
                $info .= ' Runner local ausente.';
            }

            if ($issues !== []) {
                return self::warn('QA crítico', 'evidência pendente', $info . ' ' . implode('. ', $issues) . '.');
            }

            return self::ok('QA crítico', 'ok', $info);
        } catch (Throwable $e) {
            return self::warn('QA crítico', 'incompleto', $e->getMessage());
        }
    }

    /** @return string[] */
    private static function detectMissingSchemaMigrations(PDO $pdo, array $expectedFiles): array
    {
        if (!$expectedFiles || !self::tableExistsSafe('schema_migrations')) {
            return $expectedFiles;
        }

        $placeholders = implode(',', array_fill(0, count($expectedFiles), '?'));
        $stmt = $pdo->prepare(
            "SELECT filename
               FROM schema_migrations
              WHERE success = 1
                AND filename IN ({$placeholders})"
        );
        $stmt->execute($expectedFiles);
        $applied = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return array_values(array_diff($expectedFiles, $applied));
    }
}
