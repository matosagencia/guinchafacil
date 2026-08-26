<?php
/**
 * Cron: reprocessar transferências Pix com falha (§11)
 * Execução recomendada: a cada 5 minutos
 * cPanel: *\/5 * * * * php /home/usuario/public_html/tools/cron_reprocessar_pix.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/PixService.php';

$pdo = getPDO();

// Busca pedidos concluídos com status_pix=falha (máx 10 por rodada para não sobrecarregar)
$stmt = $pdo->query(
    "SELECT DISTINCT pg.pedido_id
     FROM pagamentos pg
     JOIN pedidos p ON p.id = pg.pedido_id
     WHERE pg.status_pix = 'falha'
       AND p.status = 'concluido'
     LIMIT 10"
);
$pendentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($pendentes)) {
    echo date('[Y-m-d H:i:s]') . " Nenhum Pix pendente de reprocessamento.\n";
    exit(0);
}

foreach ($pendentes as $pedidoId) {
    $pedidoId  = (int)$pedidoId;
    $resultado = PixService::reprocessar($pedidoId);
    $msg       = $resultado['sucesso'] ? 'OK' : 'FALHOU: ' . $resultado['erro'];
    echo date('[Y-m-d H:i:s]') . " Pix pedido #{$pedidoId}: {$msg}\n";
    error_log("[cron_pix] Pedido #{$pedidoId}: {$msg}");
}

exit(0);
