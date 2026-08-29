<?php

declare(strict_types=1);

require_once __DIR__ . '/RequestIpResolver.php';

class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO  = 'INFO';
    public const LEVEL_WARN  = 'WARN';
    public const LEVEL_ERROR = 'ERROR';

    public static function filePath(): string
    {
        $dir = defined('LOG_DIR') ? (string)LOG_DIR : (dirname(__DIR__, 2) . '/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/app-' . date('Y-m-d') . '.jsonl';
    }

    public static function log(
        string $level,
        string $cls,
        string $func,
        string $system,
        string $msg,
        array $ctx = []
    ): void {
        self::event([
            'level' => strtoupper($level),
            'class' => $cls,
            'function' => $func,
            'system' => $system,
            'message' => $msg,
            'context' => $ctx,
        ]);
    }

    /**
     * §LOG-TOGGLE-01: chave seletora ligada/desligada em /admin/env
     * (SYSTEM_LOG_ENABLED). Kill switch total e intencional — quando
     * desligado, nenhum evento é gravado (nem arquivo, nem app_logs),
     * inclusive ERROR: a decisão de negócio é "log ligado só em DEV e em
     * janelas de manutenção periódica em produção", não um filtro por
     * severidade. Métodos que chamam log()/exception() continuam
     * funcionando normalmente (fire-and-forget) mesmo com log desligado.
     */
    public static function enabled(): bool
    {
        return !defined('SYSTEM_LOG_ENABLED') || (bool)SYSTEM_LOG_ENABLED;
    }

    public static function event(array $event): void
    {
        if (!self::enabled()) {
            return;
        }

        $payload = [
            'ts' => date('c'),
            'level' => strtoupper((string)($event['level'] ?? self::LEVEL_INFO)),
            'application' => 'GUINCHAFACIL',
            'system' => (string)($event['system'] ?? 'APP'),
            'class' => (string)($event['class'] ?? ''),
            'function' => (string)($event['function'] ?? ''),
            'file' => (string)($event['file'] ?? ''),
            'phase' => (string)($event['phase'] ?? ''),
            'code' => (string)($event['code'] ?? ''),
            'request_id' => (string)($event['request_id'] ?? self::requestId()),
            'run_id' => (string)($event['run_id'] ?? ($_SERVER['HTTP_X_RUN_ID'] ?? '')),
            'pedido_id' => self::nullableInt($event['pedido_id'] ?? ($event['context']['pedido_id'] ?? null)),
            'usuario_id' => self::nullableInt($event['usuario_id'] ?? ($event['context']['usuario_id'] ?? null)),
            'guincho_id' => self::nullableInt($event['guincho_id'] ?? ($event['context']['guincho_id'] ?? null)),
            'duration_ms' => self::nullableInt($event['duration_ms'] ?? null),
            'message' => (string)($event['message'] ?? ''),
            'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'ip' => RequestIpResolver::resolve(), // §IP-CANONICO-01
            'context' => self::sanitizeContext((array)($event['context'] ?? [])),
        ];

        @file_put_contents(
            self::filePath(),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND
        );

        try {
            if (!function_exists('getPDO')) {
                return;
            }

            $ctxJson = json_encode($payload['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                'INSERT INTO app_logs (
                    level, cls, func, `system`, msg, uri, ip, ctx_json,
                    application, file, phase, code, request_id, run_id, pedido_id, usuario_id, guincho_id, duration_ms
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $payload['level'],
                $payload['class'],
                $payload['function'],
                $payload['system'],
                $payload['message'],
                $payload['uri'] !== '' ? $payload['uri'] : null,
                $payload['ip'] !== '' ? $payload['ip'] : null,
                $ctxJson !== '[]' ? $ctxJson : null,
                $payload['application'],
                $payload['file'] !== '' ? $payload['file'] : null,
                $payload['phase'] !== '' ? $payload['phase'] : null,
                $payload['code'] !== '' ? $payload['code'] : null,
                $payload['request_id'] !== '' ? $payload['request_id'] : null,
                $payload['run_id'] !== '' ? $payload['run_id'] : null,
                $payload['pedido_id'],
                $payload['usuario_id'],
                $payload['guincho_id'],
                $payload['duration_ms'],
            ]);
        } catch (Throwable $e) {
            error_log('[Logger] ' . $e->getMessage());
        }
    }

    public static function startSpan(): float
    {
        return microtime(true);
    }

    public static function endSpan(float $startedAt): int
    {
        return (int)round((microtime(true) - $startedAt) * 1000);
    }

    public static function exception(string $cls, string $func, string $system, Throwable $e, array $ctx = []): void
    {
        self::event([
            'level' => self::LEVEL_ERROR,
            'class' => $cls,
            'function' => $func,
            'system' => $system,
            'file' => $e->getFile(),
            'phase' => (string)($ctx['phase'] ?? 'exception'),
            'code' => (string)($ctx['code'] ?? ''),
            'message' => $e->getMessage(),
            'context' => array_merge($ctx, [
                'exception_type' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
            ]),
        ]);
    }

    private static function requestId(): string
    {
        if (defined('REQUEST_ID')) {
            return (string)REQUEST_ID;
        }
        return (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    }

    private static function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int)$value : null;
    }

    private static function sanitizeContext(array $ctx): array
    {
        $sanitized = [];
        foreach ($ctx as $key => $value) {
            $keyStr = strtolower((string)$key);
            if (preg_match('/password|senha|token|secret|pix|cpf|authorization|cookie/', $keyStr)) {
                $sanitized[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeContext($value);
                continue;
            }

            if (is_string($value) && strlen($value) > 3000) {
                $sanitized[$key] = substr($value, 0, 3000) . '...';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
