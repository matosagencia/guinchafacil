<?php

declare(strict_types=1);

/**
 * Demanda — pedido de ação sensível criado por um funcionário, decidido por
 * um gerente. Este model só faz CRUD; toda a regra de separação de deveres
 * (quem pode criar, quem pode decidir, dupla aprovação, execução real) vive
 * em DemandaService — este arquivo não deve crescer lógica de negócio.
 */
class Demanda
{
    public const TIPOS = ['cancelamento', 'conclusao_manual', 'pagamento', 'alteracao_dados', 'reembolso'];
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_APROVADA_PARCIAL = 'aprovada_parcial';
    public const STATUS_APROVADA = 'aprovada';
    public const STATUS_REJEITADA = 'rejeitada';
    public const STATUS_EXECUTADA = 'executada';
    public const STATUS_FALHOU = 'falhou';

    public static function criar(array $dados): int
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'INSERT INTO demandas
                (tipo, status, solicitante_id, pedido_id, guincho_id, payment_job_id,
                 valor_envolvido, requer_dupla_aprovacao, justificativa, payload_json,
                 hash_idempotencia, ip, user_agent, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $dados['tipo'],
            $dados['status'] ?? self::STATUS_PENDENTE,
            $dados['solicitante_id'],
            $dados['pedido_id'] ?? null,
            $dados['guincho_id'] ?? null,
            $dados['payment_job_id'] ?? null,
            $dados['valor_envolvido'] ?? null,
            !empty($dados['requer_dupla_aprovacao']) ? 1 : 0,
            $dados['justificativa'],
            $dados['payload_json'] ?? null,
            $dados['hash_idempotencia'] ?? null,
            $dados['ip'] ?? null,
            $dados['user_agent'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function buscar(int $id): ?array
    {
        $stmt = getPDO()->prepare(
            'SELECT d.*, u.nome AS solicitante_nome, g.nome AS gerente_nome, g2.nome AS segundo_gerente_nome,
                    p.status AS pedido_status, p.endereco_origem, p.endereco_destino
               FROM demandas d
               JOIN usuarios u ON u.id = d.solicitante_id
               LEFT JOIN usuarios g ON g.id = d.gerente_id
               LEFT JOIN usuarios g2 ON g2.id = d.segundo_gerente_id
               LEFT JOIN pedidos p ON p.id = d.pedido_id
              WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Trava a linha (FOR UPDATE) — usar sempre dentro de uma transação ao decidir/executar. */
    public static function buscarParaAtualizar(PDO $pdo, int $id): ?array
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lock = $driver === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $pdo->prepare('SELECT * FROM demandas WHERE id = ?' . $lock);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function listarPendentes(?string $tipo = null, int $limit = 100): array
    {
        $sql = 'SELECT d.*, u.nome AS solicitante_nome
                  FROM demandas d
                  JOIN usuarios u ON u.id = d.solicitante_id
                 WHERE d.status IN (?, ?)';
        $params = [self::STATUS_PENDENTE, self::STATUS_APROVADA_PARCIAL];
        if ($tipo !== null) {
            $sql .= ' AND d.tipo = ?';
            $params[] = $tipo;
        }
        $sql .= ' ORDER BY d.criado_em ASC LIMIT ' . (int)$limit;
        $stmt = getPDO()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listarPorSolicitante(int $solicitanteId, int $limit = 100): array
    {
        $stmt = getPDO()->prepare(
            'SELECT d.*, p.endereco_origem, p.endereco_destino
               FROM demandas d
               LEFT JOIN pedidos p ON p.id = d.pedido_id
              WHERE d.solicitante_id = ?
              ORDER BY d.criado_em DESC LIMIT ' . (int)$limit
        );
        $stmt->execute([$solicitanteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function atualizar(PDO $pdo, int $id, array $campos): void
    {
        if (empty($campos)) {
            return;
        }
        $sets = [];
        $valores = [];
        foreach ($campos as $coluna => $valor) {
            $sets[] = "`{$coluna}` = ?";
            $valores[] = $valor;
        }
        $valores[] = $id;
        $stmt = $pdo->prepare('UPDATE demandas SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($valores);
    }

    /**
     * Histórico auditável de demandas de um pedido específico — usado no
     * registro do próprio pedido (admin/pedidodetalhe.php) pra deixar
     * explícito "quem pediu, quem aprovou/rejeitou, quando", sem precisar
     * ir a uma tela separada procurar essa informação.
     */
    public static function listarPorPedido(int $pedidoId): array
    {
        $stmt = getPDO()->prepare(
            'SELECT d.*, u.nome AS solicitante_nome, g.nome AS gerente_nome, g2.nome AS segundo_gerente_nome
               FROM demandas d
               JOIN usuarios u ON u.id = d.solicitante_id
               LEFT JOIN usuarios g ON g.id = d.gerente_id
               LEFT JOIN usuarios g2 ON g2.id = d.segundo_gerente_id
              WHERE d.pedido_id = ?
              ORDER BY d.criado_em DESC'
        );
        $stmt->execute([$pedidoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Mesma ideia, para o registro de um job de repasse (pagamento) específico. */
    public static function listarPorPaymentJob(int $jobId): array
    {
        $stmt = getPDO()->prepare(
            'SELECT d.*, u.nome AS solicitante_nome, g.nome AS gerente_nome, g2.nome AS segundo_gerente_nome
               FROM demandas d
               JOIN usuarios u ON u.id = d.solicitante_id
               LEFT JOIN usuarios g ON g.id = d.gerente_id
               LEFT JOIN usuarios g2 ON g2.id = d.segundo_gerente_id
              WHERE d.payment_job_id = ?
              ORDER BY d.criado_em DESC'
        );
        $stmt->execute([$jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function contarPendentesAntigas(int $horasLimite = 24): int
    {
        $stmt = getPDO()->prepare(
            'SELECT COUNT(*) FROM demandas
              WHERE status IN (?, ?) AND criado_em < DATE_SUB(NOW(), INTERVAL ? HOUR)'
        );
        $stmt->execute([self::STATUS_PENDENTE, self::STATUS_APROVADA_PARCIAL, $horasLimite]);
        return (int)$stmt->fetchColumn();
    }
}
