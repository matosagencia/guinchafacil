<?php
/**
 * Adiciona colunas opcionais em tabelas existentes (idempotente).
 * Uso: php database/alter_columns.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once dirname(__DIR__) . '/config.php';
$pdo = getPDO();

$alterations = [
    [
        'table'  => 'pedidos',
        'column' => 'gateway_cobranca',
        'ddl'    => "ALTER TABLE `pedidos` ADD COLUMN `gateway_cobranca` VARCHAR(30) NULL DEFAULT NULL AFTER `status`",
    ],
    [
        'table'  => 'pagamentos',
        'column' => 'comissao_plataforma',
        'ddl'    => "ALTER TABLE `pagamentos` ADD COLUMN `comissao_plataforma` DECIMAL(10,2) NULL DEFAULT NULL AFTER `valor_plataforma`",
    ],
];

foreach ($alterations as $a) {
    $exists = (int)$pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '{$a['table']}'
           AND COLUMN_NAME = '{$a['column']}'"
    )->fetchColumn();

    if ($exists) {
        echo "SKIP (já existe): {$a['table']}.{$a['column']}\n";
    } else {
        try {
            $pdo->exec($a['ddl']);
            echo "OK: adicionado {$a['table']}.{$a['column']}\n";
        } catch (PDOException $e) {
            echo "ERRO em {$a['table']}.{$a['column']}: " . $e->getMessage() . "\n";
        }
    }
}
echo "Concluído.\n";
