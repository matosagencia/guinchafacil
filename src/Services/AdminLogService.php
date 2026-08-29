<?php

declare(strict_types=1);

require_once __DIR__ . '/Logger.php';

class AdminLogService
{
    public static function normalizeFilters(array $input): array
    {
        $today = date('Y-m-d');

        return [
            'periodo_inicio' => self::normalizeDate($input['periodo_inicio'] ?? $today),
            'periodo_fim' => self::normalizeDate($input['periodo_fim'] ?? $today),
            'level' => self::normalizeText($input['level'] ?? ''),
            'system' => self::normalizeText($input['system'] ?? ''),
            'code' => self::normalizeText($input['code'] ?? ''),
            'texto' => trim((string)($input['texto'] ?? '')),
            'class' => self::normalizeText($input['class'] ?? ''),
            'function' => self::normalizeText($input['function'] ?? ''),
            'file' => trim((string)($input['file'] ?? '')),
            'phase' => self::normalizeText($input['phase'] ?? ''),
            'request_id' => trim((string)($input['request_id'] ?? '')),
            'run_id' => trim((string)($input['run_id'] ?? '')),
            'pedido_id' => self::normalizeInt($input['pedido_id'] ?? ''),
            'usuario_id' => self::normalizeInt($input['usuario_id'] ?? ''),
            'guincho_id' => self::normalizeInt($input['guincho_id'] ?? ''),
        ];
    }

    public static function fetchDashboard(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(200, $perPage));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = self::buildWhere($filters);
        $pdo = getPDO();

        $sqlLogs = "SELECT *
                      FROM app_logs
                     WHERE {$where}
                     ORDER BY criado_em DESC, id DESC
                     LIMIT {$perPage} OFFSET {$offset}";
        $stmtLogs = $pdo->prepare($sqlLogs);
        $stmtLogs->execute($params);
        $appLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sqlTotal = "SELECT COUNT(*) FROM app_logs WHERE {$where}";
        $stmtTotal = $pdo->prepare($sqlTotal);
        $stmtTotal->execute($params);
        $appTotal = (int)$stmtTotal->fetchColumn();

        $summary = self::buildSummary($pdo, $where, $params);
        $charts = self::buildCharts($pdo, $where, $params);
        $latency = self::buildLatency($pdo, $where, $params);
        $correlation = self::buildCorrelation($pdo, $filters, $where, $params);

        return [
            'appLogs' => $appLogs,
            'appTotal' => $appTotal,
            'stats' => $summary,
            'charts' => $charts,
            'latency' => $latency,
            'correlation' => $correlation,
            'pagina' => $page,
            'totalPaginas' => max(1, (int)ceil($appTotal / $perPage)),
        ];
    }

    public static function fetchWebhookLogs(int $limit = 100): array
    {
        if (!self::tableExists('webhook_logs')) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $stmt = getPDO()->query("SELECT * FROM webhook_logs ORDER BY criado_em DESC, id DESC LIMIT {$limit}");
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public static function readFileTail(int $lineLimit = 200): array
    {
        $lineLimit = max(20, min(1000, $lineLimit));
        $logFile = Logger::filePath();
        if (!is_file($logFile)) {
            return ['file' => $logFile, 'lines' => []];
        }

        $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return ['file' => $logFile, 'lines' => []];
        }

        return [
            'file' => $logFile,
            'lines' => array_slice($lines, -$lineLimit),
        ];
    }

    public static function exportRows(string $format, array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);
        $stmt = getPDO()->prepare("SELECT * FROM app_logs WHERE {$where} ORDER BY criado_em DESC, id DESC LIMIT 5000");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $masked = array_map([self::class, 'maskExportRow'], $rows);

        if ($format === 'jsonl') {
            $content = '';
            foreach ($masked as $row) {
                $content .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            }
            return [
                'content_type' => 'application/x-ndjson; charset=UTF-8',
                'filename' => 'app-logs-export-' . date('Ymd-His') . '.jsonl',
                'content' => $content,
            ];
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, array_keys($masked[0] ?? self::maskExportRow([])));
        foreach ($masked as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return [
            'content_type' => 'text/csv; charset=UTF-8',
            'filename' => 'app-logs-export-' . date('Ymd-His') . '.csv',
            'content' => $content,
        ];
    }

    private static function buildSummary(PDO $pdo, string $where, array $params): array
    {
        $stmt = $pdo->prepare(
            "SELECT
                SUM(CASE WHEN level = 'ERROR' THEN 1 ELSE 0 END) AS errors,
                SUM(CASE WHEN level = 'WARN' THEN 1 ELSE 0 END) AS warns,
                COUNT(DISTINCT NULLIF(request_id, '')) AS requests,
                COUNT(DISTINCT NULLIF(run_id, '')) AS runs,
                COUNT(DISTINCT pedido_id) AS pedidos
             FROM app_logs
             WHERE {$where}"
        );
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'errors' => (int)($summary['errors'] ?? 0),
            'warns' => (int)($summary['warns'] ?? 0),
            'requests' => (int)($summary['requests'] ?? 0),
            'runs' => (int)($summary['runs'] ?? 0),
            'pedidos' => (int)($summary['pedidos'] ?? 0),
        ];
    }

    private static function buildCharts(PDO $pdo, string $where, array $params): array
    {
        $systemStmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(system, ''), 'APP') AS label, COUNT(*) AS total,
                    SUM(CASE WHEN level = 'ERROR' THEN 1 ELSE 0 END) AS errors
               FROM app_logs
              WHERE {$where}
              GROUP BY COALESCE(NULLIF(system, ''), 'APP')
              ORDER BY total DESC
              LIMIT 8"
        );
        $systemStmt->execute($params);

        $codeStmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(code, ''), 'SEM-CODIGO') AS label, COUNT(*) AS total
               FROM app_logs
              WHERE {$where}
              GROUP BY COALESCE(NULLIF(code, ''), 'SEM-CODIGO')
              ORDER BY total DESC
              LIMIT 8"
        );
        $codeStmt->execute($params);

        return [
            'systems' => $systemStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'codes' => $codeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    private static function buildLatency(PDO $pdo, string $where, array $params): array
    {
        $stmt = $pdo->prepare(
            "SELECT duration_ms, COALESCE(NULLIF(code, ''), CONCAT(COALESCE(NULLIF(cls, ''), 'APP'), '::', COALESCE(NULLIF(func, ''), 'run'))) AS operation
               FROM app_logs
              WHERE {$where}
                AND duration_ms IS NOT NULL
                AND duration_ms > 0
              ORDER BY criado_em DESC
              LIMIT 5000"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $durations = [];
        $byOperation = [];
        foreach ($rows as $row) {
            $duration = (int)$row['duration_ms'];
            if ($duration <= 0) {
                continue;
            }
            $durations[] = $duration;
            $operation = (string)$row['operation'];
            $byOperation[$operation][] = $duration;
        }

        $operations = [];
        foreach ($byOperation as $operation => $items) {
            $operations[] = [
                'operation' => $operation,
                'count' => count($items),
                'p50' => self::percentile($items, 0.50),
                'p95' => self::percentile($items, 0.95),
                'avg' => (int)round(array_sum($items) / count($items)),
            ];
        }

        usort($operations, static function (array $left, array $right): int {
            return $right['p95'] <=> $left['p95'];
        });

        return [
            'count' => count($durations),
            'p50' => self::percentile($durations, 0.50),
            'p95' => self::percentile($durations, 0.95),
            'avg' => empty($durations) ? null : (int)round(array_sum($durations) / count($durations)),
            'operations' => array_slice($operations, 0, 8),
        ];
    }

    private static function buildCorrelation(PDO $pdo, array $filters, string $where, array $params): array
    {
        $entries = [];

        if ($filters['request_id'] !== '') {
            $stmt = $pdo->prepare(
                "SELECT criado_em, level, system, code, msg
                   FROM app_logs
                  WHERE request_id = ?
                  ORDER BY criado_em DESC, id DESC
                  LIMIT 20"
            );
            $stmt->execute([$filters['request_id']]);
            $entries[] = [
                'label' => 'Request ID',
                'value' => $filters['request_id'],
                'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ];
        }

        if ($filters['run_id'] !== '') {
            $run = false;
            if (class_exists('SimulationRun')) {
                try {
                    $run = SimulationRun::buscarPorRunId($filters['run_id']);
                } catch (Throwable) {
                    $run = false;
                }
            }

            $stmt = $pdo->prepare(
                "SELECT criado_em, level, system, code, msg
                   FROM app_logs
                  WHERE run_id = ?
                  ORDER BY criado_em DESC, id DESC
                  LIMIT 20"
            );
            $stmt->execute([$filters['run_id']]);
            $entries[] = [
                'label' => 'Run ID',
                'value' => $filters['run_id'],
                'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'run' => $run ?: null,
            ];
        }

        if ($filters['pedido_id'] !== '') {
            $stmt = $pdo->prepare(
                "SELECT criado_em, level, system, code, msg, request_id, run_id
                   FROM app_logs
                  WHERE pedido_id = ?
                  ORDER BY criado_em DESC, id DESC
                  LIMIT 20"
            );
            $stmt->execute([(int)$filters['pedido_id']]);
            $entries[] = [
                'label' => 'Pedido',
                'value' => (string)$filters['pedido_id'],
                'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ];
        }

        if (empty($entries)) {
            $stmt = $pdo->prepare(
                "SELECT request_id, run_id, pedido_id, MAX(criado_em) AS ultimo_evento, COUNT(*) AS total
                   FROM app_logs
                  WHERE {$where}
                    AND (NULLIF(request_id, '') IS NOT NULL OR NULLIF(run_id, '') IS NOT NULL OR pedido_id IS NOT NULL)
                  GROUP BY request_id, run_id, pedido_id
                  ORDER BY ultimo_evento DESC
                  LIMIT 12"
            );
            $stmt->execute($params);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        return $entries;
    }

    private static function buildWhere(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if ($filters['periodo_inicio'] !== '') {
            $where[] = 'DATE(criado_em) >= ?';
            $params[] = $filters['periodo_inicio'];
        }
        if ($filters['periodo_fim'] !== '') {
            $where[] = 'DATE(criado_em) <= ?';
            $params[] = $filters['periodo_fim'];
        }

        foreach ([
            'level' => 'level',
            'system' => '`system`',
            'code' => 'code',
            'class' => 'cls',
            'function' => 'func',
            'file' => '`file`',
            'phase' => 'phase',
            'request_id' => 'request_id',
            'run_id' => 'run_id',
        ] as $filterKey => $column) {
            if ($filters[$filterKey] === '') {
                continue;
            }
            $where[] = "{$column} = ?";
            $params[] = $filters[$filterKey];
        }

        foreach ([
            'pedido_id' => 'pedido_id',
            'usuario_id' => 'usuario_id',
            'guincho_id' => 'guincho_id',
        ] as $filterKey => $column) {
            if ($filters[$filterKey] === '') {
                continue;
            }
            $where[] = "{$column} = ?";
            $params[] = (int)$filters[$filterKey];
        }

        if ($filters['texto'] !== '') {
            $where[] = '(msg LIKE ? OR ctx_json LIKE ?)';
            $like = '%' . $filters['texto'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $where), $params];
    }

    private static function percentile(array $values, float $ratio): ?int
    {
        if (empty($values)) {
            return null;
        }

        sort($values);
        $index = (int)floor((count($values) - 1) * $ratio);
        return (int)$values[$index];
    }

    private static function tableExists(string $table): bool
    {
        $stmt = getPDO()->prepare(
            "SELECT COUNT(*)
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function normalizeDate(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private static function normalizeText(mixed $value): string
    {
        return trim((string)$value);
    }

    private static function normalizeInt(mixed $value): string
    {
        return is_numeric($value) && (int)$value > 0 ? (string)(int)$value : '';
    }

    private static function maskExportRow(array $row): array
    {
        $ctx = [];
        if (!empty($row['ctx_json'])) {
            $decoded = json_decode((string)$row['ctx_json'], true);
            if (is_array($decoded)) {
                $ctx = self::maskRecursive($decoded);
            }
        }

        return [
            'criado_em' => (string)($row['criado_em'] ?? ''),
            'level' => (string)($row['level'] ?? ''),
            'system' => (string)($row['system'] ?? ''),
            'class' => (string)($row['cls'] ?? ''),
            'function' => (string)($row['func'] ?? ''),
            'file' => (string)($row['file'] ?? ''),
            'phase' => (string)($row['phase'] ?? ''),
            'code' => (string)($row['code'] ?? ''),
            'request_id' => (string)($row['request_id'] ?? ''),
            'run_id' => (string)($row['run_id'] ?? ''),
            'pedido_id' => $row['pedido_id'] ?? null,
            'usuario_id' => $row['usuario_id'] ?? null,
            'guincho_id' => $row['guincho_id'] ?? null,
            'duration_ms' => $row['duration_ms'] ?? null,
            'message' => (string)($row['msg'] ?? ''),
            'context' => $ctx,
        ];
    }

    private static function maskRecursive(array $payload): array
    {
        $masked = [];
        foreach ($payload as $key => $value) {
            $normalizedKey = strtolower((string)$key);
            if (preg_match('/password|senha|token|secret|pix|cpf|authorization|cookie/', $normalizedKey)) {
                $masked[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = self::maskRecursive($value);
                continue;
            }

            if (is_string($value) && strlen($value) > 400) {
                $masked[$key] = substr($value, 0, 400) . '...';
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }
}
