<?php
declare(strict_types=1);

/**
 * Trilha de auditoria de pedidos. Grava em `pedido_eventos` a cada
 * transição de status, dentro da MESMA transação da mudança.
 *
 * Schema atual (dump 12/10/2026):
 *   id, pedido_id, evento, context_json, created_at
 *
 * ator_tipo / ator_id / status_anterior / status_novo / motivo vão
 * dentro do context_json. Se no futuro precisar indexar, migra.
 */
final class PedidoEventoService
{
        public static function registrar(
            PDO $pdo,
            int $pedidoId,
            string $evento,
            ?string $statusAnterior = null,
            ?string $statusNovo = null,
            ?string $atorTipo = null,
            ?int $atorId = null,
            ?string $motivo = null,
            array $contextoExtra = []
        ): void {
            try {
                $ctx = array_merge($contextoExtra, [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                $pdo->prepare(
                    'INSERT INTO pedido_eventos
                        (pedido_id, ator_id, ator_tipo, evento,
                         status_anterior, status_novo, motivo,
                         context_json, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $pedidoId,
                    $atorId,
                    $atorTipo,
                    $evento,
                    $statusAnterior,
                    $statusNovo,
                    $motivo,
                    json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            } catch (Throwable $e) {
                error_log('[PedidoEventoService] falha: ' . $e->getMessage()
                    . ' | pedido=' . $pedidoId . ' evento=' . $evento);
            }
        }
    public static function registrarFinanceiro(PDO $pdo,int $pedidoId,string $evento,?string $anterior,?string $novo,?string $atorTipo,?int $atorId,?string $motivo,array $context=[]): void
    {
        GFIntegratedApproval::guard($pdo);
        if (!$pdo->inTransaction()) throw new LogicException('FIN_TRANSACTION_REQUIRED');
        $q=$pdo->prepare('INSERT INTO pedido_eventos (pedido_id,ator_id,ator_tipo,evento,status_anterior,status_novo,motivo,context_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $q->execute([$pedidoId,$atorId,$atorTipo,$evento,$anterior,$novo,$motivo,json_encode($context,JSON_THROW_ON_ERROR)]);
        if ($q->rowCount()!==1) throw new RuntimeException('FIN_ORDER_EVENT_WRITE_FAILED');
    }
}