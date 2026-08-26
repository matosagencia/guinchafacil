<?php
// File: guinchafacil/src/Services/Logger.php

/**
 * Logger
 * - Loga em arquivo (para dev via FTP) e opcionalmente em banco (para admin no backoffice)
 * - Formato humano: CLASSE - FUNCAO - sistema/algoritimo - descricao
 */
class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO  = 'INFO';
    public const LEVEL_WARN  = 'WARN';
    public const LEVEL_ERROR = 'ERROR';

    public static function filePath(): string
    {
        $dir = defined('LOG_DIR') ? (string) LOG_DIR : (dirname(__DIR__, 2) . '/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/app.log';
    }

    public static function log(
        string $level,
        string $cls,
        string $func,
        string $system,
        string $msg,
        array $ctx = []
    ): void {
        $ts = date('c');
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '');

        $human = sprintf(
            '%s [%s] %s - %s - %s - %s',
            $ts,
            strtoupper($level),
            $cls,
            $func,
            $system,
            $msg
        );

        // 1) Arquivo (dev/FTP)
        $line = $human;
        if (!empty($ctx)) {
            $line .= ' | ctx=' . json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $line .= "\n";
        @file_put_contents(self::filePath(), $line, FILE_APPEND);

        // 2) Banco (admin/backoffice) - best effort
        try {
            if (function_exists('getPDO')) {
                $pdo = getPDO();
                // Tabela criada sob demanda (não derruba se faltar permissão)
                $pdo->exec('CREATE TABLE IF NOT EXISTS app_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    level VARCHAR(16) NOT NULL,
                    cls VARCHAR(96) NOT NULL,
                    func VARCHAR(96) NOT NULL,
                    `system` VARCHAR(96) NOT NULL,
                    msg TEXT NOT NULL,
                    uri VARCHAR(255) NULL,
                    ip VARCHAR(64) NULL,
                    ctx_json MEDIUMTEXT NULL,
                    PRIMARY KEY (id),
                    KEY idx_created (criado_em),
                    KEY idx_level (level)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

                $st = $pdo->prepare('INSERT INTO app_logs (level, cls, func, `system`, msg, uri, ip, ctx_json) VALUES (?,?,?,?,?,?,?,?)');
                $st->execute([
                    strtoupper($level),
                    $cls,
                    $func,
                    $system,
                    $msg,
                    $uri ?: null,
                    $ip ?: null,
                    !empty($ctx) ? json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                ]);
            }
        } catch (Throwable $e) {
            // Nunca derrubar o app
            error_log('[Logger] ' . $e->getMessage());
        }
    }

    public static function exception(string $cls, string $func, string $system, Throwable $e, array $ctx = []): void
    {
        $ctx2 = array_merge($ctx, [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        self::log(self::LEVEL_ERROR, $cls, $func, $system, $e->getMessage(), $ctx2);
    }
}
