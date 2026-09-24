<?php

declare(strict_types=1);

/**
 * Trilha enxuta das decisões do atendimento.
 *
 * Valores de orçamento não são persistidos aqui. O modelo guarda somente o
 * marco operacional necessário para liberar o próximo passo do pedido.
 */
final class PedidoDecisaoSocorro
{
    public const ORCAMENTO_INFORMADO = 'ORCAMENTO_INFORMADO';
    public const ORCAMENTO_APROVADO = 'ORCAMENTO_APROVADO';
    public const ORCAMENTO_RECUSADO = 'ORCAMENTO_RECUSADO';
    public const REBOQUE_NAO_NECESSARIO = 'REBOQUE_NAO_NECESSARIO';
    public const REBOQUE_SOLICITADO = 'REBOQUE_SOLICITADO';
    public const SAIDA_OFICINA_COM_DESCONTO = 'SAIDA_OFICINA_COM_DESCONTO';

    public static function registrar(
        int $pedidoId,
        string $decisionCode,
        string $actorType,
        ?int $actorId = null,
        ?int $providerId = null,
        array $metadata = []
    ): array {
        if ($pedidoId <= 0 || trim($decisionCode) === '' || trim($actorType) === '') {
            throw new InvalidArgumentException('Dados inválidos para decisão do socorro.');
        }

        $key = sprintf('socorro:%d:%s', $pedidoId, $decisionCode);
        $pdo = getPDO();
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = 'INSERT INTO pedido_decisoes_socorro
                (pedido_id, provider_id, actor_type, actor_id, decision_code, metadata_json, idempotency_key, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)';
        $sql .= $driver === 'sqlite'
            ? ' ON CONFLICT(idempotency_key) DO NOTHING'
            : ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $pedidoId,
            $providerId,
            trim($actorType),
            $actorId,
            trim($decisionCode),
            $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            $key,
        ]);

        $sets = ['decisoes_socorro_at = CURRENT_TIMESTAMP'];
        $values = [];
        if ($decisionCode === self::ORCAMENTO_INFORMADO) {
            $sets[] = 'orcamento_informado_at = CURRENT_TIMESTAMP';
        } elseif ($decisionCode === self::ORCAMENTO_APROVADO) {
            $sets[] = 'cliente_decisao_orcamento = ?';
            $values[] = 'APROVADO';
        } elseif ($decisionCode === self::ORCAMENTO_RECUSADO) {
            $sets[] = 'cliente_decisao_orcamento = ?';
            $values[] = 'RECUSADO';
        } elseif ($decisionCode === self::REBOQUE_NAO_NECESSARIO) {
            $sets[] = 'cliente_decisao_reboque = ?';
            $values[] = 'NAO';
        } elseif ($decisionCode === self::REBOQUE_SOLICITADO) {
            $sets[] = 'cliente_decisao_reboque = ?';
            $values[] = 'SIM';
        }
        $values[] = $pedidoId;
        $pedido = $pdo->prepare('UPDATE pedidos SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $pedido->execute($values);

        $read = $pdo->prepare('SELECT * FROM pedido_decisoes_socorro WHERE idempotency_key = ? LIMIT 1');
        $read->execute([$key]);
        return $read->fetch(PDO::FETCH_ASSOC) ?: ['idempotency_key' => $key, 'decision_code' => $decisionCode];
    }

    public static function possuiDecisao(int $pedidoId, string $decisionCode): bool
    {
        try {
            $stmt = getPDO()->prepare(
                'SELECT 1 FROM pedido_decisoes_socorro WHERE pedido_id = ? AND decision_code = ? LIMIT 1'
            );
            $stmt->execute([$pedidoId, $decisionCode]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            // Suites legadas com SQLite podem não ter recebido a migration
            // nova; nesse caso o caminho antigo continua sem desconto.
            return false;
        }
    }
}
