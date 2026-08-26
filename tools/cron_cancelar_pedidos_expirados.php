<?php
/**
 * Cron: cancelar pedidos com expiracao_aceite vencida (§11)
 * Execução recomendada: a cada 1 minuto
 * cPanel: * * * * * php /home/usuario/public_html/tools/cron_cancelar_pedidos_expirados.php
 */
declare(strict_types=1);

// Apenas CLI
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/EstornoService.php';

$pdo = getPDO();

// Busca pedidos em aguardando_guincho com expiração vencida
$stmt = $pdo->query(
    "SELECT id FROM pedidos
     WHERE status = 'aguardando_guincho'
       AND expiracao_aceite IS NOT NULL
       AND expiracao_aceite < NOW()"
);
$expirados = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($expirados)) {
    echo date('[Y-m-d H:i:s]') . " Nenhum pedido expirado.\n";
    exit(0);
}

foreach ($expirados as $pedidoId) {
    $pedidoId = (int)$pedidoId;
    $upd = $pdo->prepare("UPDATE pedidos SET status = 'cancelado' WHERE id = ? AND status = 'aguardando_guincho'");
    $upd->execute([$pedidoId]);

    if ($upd->rowCount() > 0) {
        // §4.4: estorno automático (pedido estava em aguardando_guincho = pagamento já aprovado)
        $estorno = EstornoService::estornar($pedidoId);
        $msg = $estorno['sucesso']
            ? "cancelado + estorno OK"
            : "cancelado + estorno FALHOU: " . $estorno['erro'];
        echo date('[Y-m-d H:i:s]') . " Pedido #{$pedidoId}: {$msg}\n";
        error_log("[cron_cancelar] Pedido #{$pedidoId}: {$msg}");
    }
}

exit(0);
