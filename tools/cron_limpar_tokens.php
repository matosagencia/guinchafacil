<?php
/**
 * Cron: limpeza de tokens de reset de senha expirados (§11)
 * Execução recomendada: diária
 * cPanel: 0 3 * * * php /home/usuario/public_html/tools/cron_limpar_tokens.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/CronMonitorService.php';

$run = CronMonitorService::start('cron_limpar_tokens');
$pdo = getPDO();

// Remove tokens expirados ou já usados
$stmt = $pdo->prepare(
    "DELETE FROM password_resets WHERE expira_em < NOW() OR usado = 1"
);
$stmt->execute();
$removidos = $stmt->rowCount();

CronMonitorService::finish($run, 'ok', 'Limpeza de tokens concluída.', ['tokens_removidos' => $removidos]);
echo date('[Y-m-d H:i:s]') . " Tokens removidos: {$removidos}\n";
error_log("[cron_tokens] Tokens de reset removidos: {$removidos}");

exit(0);
