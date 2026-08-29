<?php
declare(strict_types=1);

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/AuditTrailService.php';
require_once __DIR__ . '/NotificacaoService.php';
require_once __DIR__ . '/../Models/Pagamento.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Guincho.php';
require_once __DIR__ . '/PixService.php';

final class PaymentJobService
{
    public static function enqueuePixPayout(int $pedidoId, float $valorGuincho, float $valorPlataforma): array
    {
        $pdo = getPDO();
        $idempotencyKey = 'pix_payout:' . $pedidoId;

        try {
            $pdo->beginTransaction();

            $existing = $pdo->prepare("SELECT * FROM payment_jobs WHERE idempotency_key = ? LIMIT 1" . self::lockClause($pdo));
            $existing->execute([$idempotencyKey]);
            $job = $existing->fetch(PDO::FETCH_ASSOC);
            if ($job) {
                $pdo->commit();
                return ['ok' => true, 'job_id' => (int)$job['id'], 'queued' => false, 'status' => (string)$job['status']];
            }

            $guard = Pagamento::prepararRepassePix($pedidoId, $valorGuincho, $valorPlataforma);
            if (!$guard['ok']) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => (string)$guard['erro']];
            }

            $payload = json_encode([
                'pedido_id' => $pedidoId,
                'pagamento_id' => (int)($guard['pagamento']['id'] ?? 0),
                'valor_guincho' => $valorGuincho,
                'valor_plataforma' => $valorPlataforma,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $stmt = $pdo->prepare("
                INSERT INTO payment_jobs (
                    pedido_id, pagamento_id, job_type, idempotency_key, status,
                    attempt_count, max_attempts, available_at, payload_json, created_at, updated_at
                ) VALUES (?, ?, 'pix_payout', ?, 'queued', 0, 5, NOW(), ?, NOW(), NOW())
            ");
            $stmt->execute([
                $pedidoId,
                (int)($guard['pagamento']['id'] ?? 0),
                $idempotencyKey,
                $payload,
            ]);

            $jobId = (int)$pdo->lastInsertId();
            $pdo->commit();

            AuditTrailService::evento('payment_job_enqueued', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'event_code' => 'PAY-JOB-001',
                'job_id' => $jobId,
                'pagamento_id' => (int)($guard['pagamento']['id'] ?? 0),
            ]);

            return ['ok' => true, 'job_id' => $jobId, 'queued' => true, 'status' => 'queued'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'enqueue', $e, [
                'pedido_id' => $pedidoId,
                'code' => 'PAY-JOB-ERR-001',
            ]);
            return ['ok' => false, 'erro' => 'Erro interno ao enfileirar repasse PIX.'];
        }
    }

    public static function processNext(string $workerId = 'cron_pix'): array
    {
        $pdo = getPDO();

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->query("
                SELECT * FROM payment_jobs
                 WHERE job_type = 'pix_payout'
                   AND status IN ('queued', 'retry')
                   AND available_at <= NOW()
                 ORDER BY id ASC
                 LIMIT 1
                 " . self::lockClause($pdo) . "
            ");
            $job = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (!$job) {
                $pdo->commit();
                return ['ok' => true, 'processed' => false];
            }

            $pdo->prepare("
                UPDATE payment_jobs
                   SET status = 'running',
                       worker_id = ?,
                       locked_at = NOW(),
                       updated_at = NOW()
                 WHERE id = ?
            ")->execute([$workerId, (int)$job['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception(__CLASS__, __FUNCTION__, 'claim', $e, ['code' => 'PAY-JOB-ERR-002']);
            return ['ok' => false, 'processed' => false, 'erro' => 'Falha ao reivindicar job.'];
        }

        return self::processJob((int)$job['id'], $workerId);
    }

    public static function processJob(int $jobId, string $workerId = 'cron_pix'): array
    {
        $pdo = getPDO();
        $jobStmt = $pdo->prepare("SELECT * FROM payment_jobs WHERE id = ? LIMIT 1");
        $jobStmt->execute([$jobId]);
        $job = $jobStmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return ['ok' => false, 'processed' => false, 'erro' => 'Job não encontrado.'];
        }
        if ((string)($job['status'] ?? '') === 'completed') {
            return ['ok' => true, 'processed' => false, 'job_id' => $jobId, 'status' => 'completed'];
        }
        if ((string)($job['status'] ?? '') === 'failed') {
            return ['ok' => false, 'processed' => false, 'erro' => 'Job está em falha permanente.'];
        }
        if (!in_array((string)($job['status'] ?? ''), ['queued', 'retry', 'running'], true)) {
            return ['ok' => false, 'processed' => false, 'erro' => 'Job em status inválido para processamento.'];
        }

        $pedidoId = (int)$job['pedido_id'];
        $pagamentoId = (int)$job['pagamento_id'];
        $attempt = (int)$job['attempt_count'] + 1;

        if ($pagamentoId > 0) {
            try {
                getPDO()->prepare("
                    UPDATE pagamentos
                       SET status_pix = 'processando'
                     WHERE id = ?
                ")->execute([$pagamentoId]);
            } catch (Throwable $e) {
                Logger::exception(__CLASS__, __FUNCTION__, 'status_pix_running', $e, [
                    'job_id' => $jobId,
                    'pagamento_id' => $pagamentoId,
                    'code' => 'PAY-JOB-ERR-004',
                ]);
            }
        }

        $payload = json_decode((string)($job['payload_json'] ?? '{}'), true) ?: [];
        $pedido = Pedido::buscarPorId($pedidoId);
        if (!$pedido || (string)($pedido['status'] ?? '') !== 'concluido') {
            self::markFailed($job, $attempt, 'Pedido não está concluído para repasse.', $workerId, true);
            return ['ok' => false, 'processed' => true, 'erro' => 'Pedido não concluído.'];
        }

        $guinchoId = (int)($pedido['guincho_id'] ?? 0);
        $guincho = $guinchoId > 0 ? Guincho::buscarPorId($guinchoId) : null;
        if (!$guincho || empty($guincho['chave_pix'])) {
            self::markFailed($job, $attempt, 'Guincho sem chave PIX válida.', $workerId, true);
            return ['ok' => false, 'processed' => true, 'erro' => 'Guincho sem chave PIX.'];
        }

        $result = PixService::transferir(
            $pedidoId,
            (float)($payload['valor_guincho'] ?? 0),
            (string)$guincho['chave_pix'],
            (string)($guincho['chave_pix_tipo'] ?? 'aleatoria')
        );

        self::recordAttempt($jobId, $attempt, $workerId, $result);

        if (!empty($result['sucesso'])) {
            $confirmed = Pagamento::confirmarRepassePix($pagamentoId, (string)($result['id_transacao'] ?? ''));
            if (!$confirmed) {
                self::markFailed($job, $attempt, 'Falha ao confirmar repasse após aprovação do gateway.', $workerId, false);
                return ['ok' => false, 'processed' => true, 'erro' => 'Falha ao confirmar repasse.'];
            }

            $pdo->prepare("
                UPDATE payment_jobs
                   SET status = 'completed',
                       attempt_count = ?,
                       last_error = NULL,
                       finished_at = NOW(),
                       updated_at = NOW()
                 WHERE id = ?
            ")->execute([$attempt, $jobId]);

            AuditTrailService::evento('pix_payout_completed', __CLASS__, __FUNCTION__, [
                'pedido_id' => $pedidoId,
                'event_code' => 'PAY-PIX-006',
                'job_id' => $jobId,
                'pagamento_id' => $pagamentoId,
                'worker_id' => $workerId,
                'id_transacao_pix' => (string)($result['id_transacao'] ?? ''),
            ]);

            try {
                $pagamentoAtualizado = Pagamento::buscarPorPedido($pedidoId);
                NotificacaoService::pixEfetivadoAdmin(
                    $pedido,
                    ['nome' => $guincho['nome'] ?? '', 'email' => $guincho['email'] ?? ''],
                    $pagamentoAtualizado ?: ['valor_guincho' => (float)($payload['valor_guincho'] ?? 0), 'id_transacao_pix' => (string)($result['id_transacao'] ?? '')]
                );
            } catch (Throwable $e) {
                Logger::exception(__CLASS__, __FUNCTION__, 'notify_success', $e, [
                    'pedido_id' => $pedidoId,
                    'job_id' => $jobId,
                    'code' => 'PAY-JOB-NTF-001',
                ]);
            }

            return ['ok' => true, 'processed' => true, 'job_id' => $jobId];
        }

        self::markFailed($job, $attempt, (string)($result['erro'] ?? 'Erro desconhecido no gateway PIX.'), $workerId, false);
        return ['ok' => false, 'processed' => true, 'erro' => (string)($result['erro'] ?? 'Erro desconhecido no gateway PIX.')];
    }

    private static function recordAttempt(int $jobId, int $attempt, string $workerId, array $result): void
    {
        $pdo = getPDO();
        $pdo->prepare("
            INSERT INTO payment_job_attempts (
                payment_job_id, attempt_number, worker_id, success,
                response_json, error_message, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $jobId,
            $attempt,
            $workerId,
            !empty($result['sucesso']) ? 1 : 0,
            json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string)($result['erro'] ?? ''),
        ]);
    }

    private static function markFailed(array $job, int $attempt, string $error, string $workerId, bool $permanent): void
    {
        $pdo = getPDO();
        $maxAttempts = (int)($job['max_attempts'] ?? 5);
        $isPermanent = $permanent || $attempt >= $maxAttempts;
        $nextStatus = $isPermanent ? 'failed' : 'retry';
        $nextAvailable = $isPermanent
            ? date('Y-m-d H:i:s')
            : date('Y-m-d H:i:s', time() + self::retryDelaySeconds($attempt));

        $pdo->prepare("
            UPDATE payment_jobs
               SET status = ?,
                   attempt_count = ?,
                   last_error = ?,
                   available_at = ?,
                   worker_id = ?,
                   updated_at = NOW(),
                   finished_at = CASE WHEN ? = 'failed' THEN NOW() ELSE finished_at END
             WHERE id = ?
        ")->execute([$nextStatus, $attempt, $error, $nextAvailable, $workerId, $nextStatus, (int)$job['id']]);

        $pagamentoId = (int)($job['pagamento_id'] ?? 0);
        if ($pagamentoId > 0) {
            $pdo->prepare("
                UPDATE pagamentos
                   SET status_pix = ?
                 WHERE id = ?
            ")->execute([$isPermanent ? 'falha_permanente' : 'falha', $pagamentoId]);
        }

        AuditTrailService::evento('pix_payout_failed', __CLASS__, __FUNCTION__, [
            'pedido_id' => (int)$job['pedido_id'],
            'event_code' => 'PAY-PIX-007',
            'job_id' => (int)$job['id'],
            'pagamento_id' => $pagamentoId,
            'worker_id' => $workerId,
            'attempt_number' => $attempt,
            'permanent' => $isPermanent,
            'error' => $error,
        ]);

        try {
            $pedido = Pedido::buscarPorId((int)$job['pedido_id']);
            if ($pedido) {
                NotificacaoService::falhaPixAdmin($pedido, $error);
            }
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'notify_failure', $e, [
                'pedido_id' => (int)$job['pedido_id'],
                'job_id' => (int)$job['id'],
                'code' => 'PAY-JOB-NTF-002',
            ]);
        }
    }

    public static function forceRetry(int $jobId, int $actorId = 0): array
    {
        try {
            $stmt = getPDO()->prepare("SELECT * FROM payment_jobs WHERE id = ? LIMIT 1");
            $stmt->execute([$jobId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$job) {
                return ['ok' => false, 'erro' => 'Job não encontrado.'];
            }
            if ((string)($job['status'] ?? '') === 'completed') {
                return ['ok' => false, 'erro' => 'Job já concluído.'];
            }

            getPDO()->prepare("
                UPDATE payment_jobs
                   SET status = 'queued',
                       available_at = NOW(),
                       last_error = NULL,
                       worker_id = NULL,
                       locked_at = NULL,
                       finished_at = NULL,
                       updated_at = NOW()
                 WHERE id = ?
            ")->execute([$jobId]);

            $pagamentoId = (int)($job['pagamento_id'] ?? 0);
            if ($pagamentoId > 0) {
                getPDO()->prepare("
                    UPDATE pagamentos
                       SET status_pix = 'pendente'
                     WHERE id = ?
                ")->execute([$pagamentoId]);
            }

            AuditTrailService::evento('payment_job_forced_retry', __CLASS__, __FUNCTION__, [
                'pedido_id' => (int)($job['pedido_id'] ?? 0),
                'event_code' => 'PAY-JOB-002',
                'job_id' => $jobId,
                'actor_type' => 'admin',
                'actor_id' => $actorId,
            ]);

            return ['ok' => true, 'job_id' => $jobId];
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'force_retry', $e, [
                'job_id' => $jobId,
                'actor_id' => $actorId,
                'code' => 'PAY-JOB-ERR-003',
            ]);
            return ['ok' => false, 'erro' => 'Erro interno ao reencaminhar job.'];
        }
    }

    public static function listByPedido(int $pedidoId): array
    {
        try {
            $stmt = getPDO()->prepare("
                SELECT *
                  FROM payment_jobs
                 WHERE pedido_id = ?
                 ORDER BY id DESC
            ");
            $stmt->execute([$pedidoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'list_by_pedido', $e, [
                'pedido_id' => $pedidoId,
                'code' => 'PAY-JOB-QRY-001',
            ]);
            return [];
        }
    }

    public static function listAttempts(int $jobId): array
    {
        try {
            $stmt = getPDO()->prepare("
                SELECT *
                  FROM payment_job_attempts
                 WHERE payment_job_id = ?
                 ORDER BY attempt_number DESC, id DESC
            ");
            $stmt->execute([$jobId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'list_attempts', $e, [
                'job_id' => $jobId,
                'code' => 'PAY-JOB-QRY-002',
            ]);
            return [];
        }
    }

    public static function list(array $filters = [], int $limit = 50): array
    {
        try {
            $limit = max(1, min(500, $limit));
            $where = [];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = 'pj.status = :status';
                $params[':status'] = (string)$filters['status'];
            }

            if (!empty($filters['job_type'])) {
                $where[] = 'pj.job_type = :job_type';
                $params[':job_type'] = (string)$filters['job_type'];
            }

            if (!empty($filters['pedido_id'])) {
                $where[] = 'pj.pedido_id = :pedido_id';
                $params[':pedido_id'] = (int)$filters['pedido_id'];
            }

            if (!empty($filters['worker_id'])) {
                $where[] = 'pj.worker_id = :worker_id';
                $params[':worker_id'] = (string)$filters['worker_id'];
            }

            if (!empty($filters['data_inicio'])) {
                $where[] = 'DATE(pj.created_at) >= :data_inicio';
                $params[':data_inicio'] = (string)$filters['data_inicio'];
            }

            if (!empty($filters['data_fim'])) {
                $where[] = 'DATE(pj.created_at) <= :data_fim';
                $params[':data_fim'] = (string)$filters['data_fim'];
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "
                SELECT
                    pj.*,
                    pe.status AS pedido_status,
                    pg.status AS pagamento_status,
                    pg.status_pix AS pagamento_status_pix,
                    uc.nome AS cliente_nome,
                    ug.nome AS guincho_nome
                FROM payment_jobs pj
                LEFT JOIN pedidos pe ON pe.id = pj.pedido_id
                LEFT JOIN pagamentos pg ON pg.id = pj.pagamento_id
                LEFT JOIN usuarios uc ON uc.id = pe.cliente_id
                LEFT JOIN guinchos g ON g.id = pe.guincho_id
                LEFT JOIN usuarios ug ON ug.id = g.usuario_id
                {$whereClause}
                ORDER BY pj.id DESC
                LIMIT :limit
            ";

            $stmt = getPDO()->prepare($sql);
            foreach ($params as $key => $value) {
                $type = $key === ':pedido_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'list', $e, [
                'code' => 'PAY-JOB-QRY-003',
                'filters' => $filters,
                'limit' => $limit,
            ]);
            return [];
        }
    }

    public static function summarize(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['job_type'])) {
                $where[] = 'job_type = :job_type';
                $params[':job_type'] = (string)$filters['job_type'];
            }

            if (!empty($filters['pedido_id'])) {
                $where[] = 'pedido_id = :pedido_id';
                $params[':pedido_id'] = (int)$filters['pedido_id'];
            }

            if (!empty($filters['worker_id'])) {
                $where[] = 'worker_id = :worker_id';
                $params[':worker_id'] = (string)$filters['worker_id'];
            }

            if (!empty($filters['data_inicio'])) {
                $where[] = 'DATE(created_at) >= :data_inicio';
                $params[':data_inicio'] = (string)$filters['data_inicio'];
            }

            if (!empty($filters['data_fim'])) {
                $where[] = 'DATE(created_at) <= :data_fim';
                $params[':data_fim'] = (string)$filters['data_fim'];
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = getPDO()->prepare("
                SELECT
                    COUNT(*) AS total_jobs,
                    SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued_jobs,
                    SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) AS running_jobs,
                    SUM(CASE WHEN status = 'retry' THEN 1 ELSE 0 END) AS retry_jobs,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_jobs,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_jobs
                FROM payment_jobs
                {$whereClause}
            ");
            foreach ($params as $key => $value) {
                $type = $key === ':pedido_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total_jobs' => (int)($row['total_jobs'] ?? 0),
                'queued_jobs' => (int)($row['queued_jobs'] ?? 0),
                'running_jobs' => (int)($row['running_jobs'] ?? 0),
                'retry_jobs' => (int)($row['retry_jobs'] ?? 0),
                'completed_jobs' => (int)($row['completed_jobs'] ?? 0),
                'failed_jobs' => (int)($row['failed_jobs'] ?? 0),
            ];
        } catch (Throwable $e) {
            Logger::exception(__CLASS__, __FUNCTION__, 'summary', $e, [
                'code' => 'PAY-JOB-QRY-004',
                'filters' => $filters,
            ]);
            return [
                'total_jobs' => 0,
                'queued_jobs' => 0,
                'running_jobs' => 0,
                'retry_jobs' => 0,
                'completed_jobs' => 0,
                'failed_jobs' => 0,
            ];
        }
    }

    private static function retryDelaySeconds(int $attempt): int
    {
        return match (true) {
            $attempt <= 1 => 5 * 60,
            $attempt === 2 => 15 * 60,
            $attempt === 3 => 30 * 60,
            $attempt === 4 => 60 * 60,
            default => 3 * 60 * 60,
        };
    }

    private static function lockClause(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }
}
