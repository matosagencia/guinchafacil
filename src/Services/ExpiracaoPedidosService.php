<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/ExpiracaoPedidosService.php
//
// §COBERTURA-RAIO-01 (05/08/2026): lógica extraída de
// tools/cron_cancelar_pedidos_expirados.php pra poder ser chamada tanto
// pelo cron real (produção, a cada minuto) quanto por um script de QA
// (tools/qa_executar_cron_expiracao.php) que precisa do resultado como JSON
// limpo, sem as linhas de log que o cron imprime no stdout. Nenhum
// comportamento muda — é só extração de método, o cron continua fazendo
// exatamente o mesmo fluxo de antes.

require_once __DIR__ . '/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/EstornoService.php';
require_once __DIR__ . '/../Models/Pedido.php';
require_once __DIR__ . '/../Models/Pagamento.php';

final class ExpiracaoPedidosService
{
    public const MOTIVO_TIMEOUT = 'Expiração automática do aceite do guincho.';

    /**
     * Cancela pedidos em aguardando_guincho cuja expiracao_aceite já
     * passou, e dispara estorno integral quando havia pagamento aprovado.
     *
     * @return array{expired_found:int,cancelled:int,refunds_ok:int,refunds_failed:int,errors:int}
     */
    public static function executar(): array
    {
        $metrics = [
            'expired_found' => 0,
            'cancelled' => 0,
            'refunds_ok' => 0,
            'refunds_failed' => 0,
            'errors' => 0,
        ];

        $pdo = getPDO();
        $stmt = $pdo->query(
            "SELECT id
               FROM pedidos
              WHERE status = 'aguardando_guincho'
                AND expiracao_aceite IS NOT NULL
                AND expiracao_aceite < NOW()
              ORDER BY expiracao_aceite ASC
              LIMIT 100"
        );
        $pedidoIds = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $metrics['expired_found'] = count($pedidoIds);

        foreach ($pedidoIds as $pedidoIdRaw) {
            $pedidoId = (int)$pedidoIdRaw;
            $result = PedidoTransitionService::cancelByAdmin(
                $pedidoId,
                0,
                self::MOTIVO_TIMEOUT
            );

            if (!$result->ok) {
                $metrics['errors']++;
                continue;
            }

            $metrics['cancelled']++;

            $pagamento = Pagamento::buscarAprovadoPorPedido($pedidoId);
            if ($pagamento) {
                $refund = EstornoService::estornar($pedidoId);
                if (!empty($refund['sucesso'])) {
                    $metrics['refunds_ok']++;
                } else {
                    $metrics['refunds_failed']++;
                }
            }
        }

        return $metrics;
    }
}
