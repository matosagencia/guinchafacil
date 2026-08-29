<?php

declare(strict_types=1);

/**
 * src/Models/Ocorrencia.php
 * Pacote L2.3 — item "Ocorrências" da nav reorganizada da Central
 * Operacional (antes "em breve"). Registro estruturado de incidentes de
 * atendimento (avaria, atraso, conduta, veículo, segurança), separado dos
 * alertas 100% derivados/computados do AdminAlertService (que não têm
 * input humano nem persistência própria). Ver install/migration_ocorrencias_v1.sql.
 */
class Ocorrencia
{
    private const TBL = 'pedido_ocorrencias';

    public static function criar(array $dados): int
    {
        $stmt = getPDO()->prepare(
            "INSERT INTO " . self::TBL . "
                (pedido_id, tipo, severidade, status, relator_tipo, relator_id, descricao, criado_em, atualizado_em)
             VALUES (?, ?, ?, 'aberta', ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([
            (int)$dados['pedido_id'],
            (string)$dados['tipo'],
            (string)$dados['severidade'],
            (string)$dados['relator_tipo'],
            $dados['relator_id'] !== null ? (int)$dados['relator_id'] : null,
            (string)$dados['descricao'],
        ]);
        return (int)getPDO()->lastInsertId();
    }

    /**
     * Lista ocorrências com filtros opcionais (status, severidade, pedido_id),
     * já trazendo dados básicos do pedido (cliente, endereço) pra exibição.
     */
    public static function listar(array $filtros = [], int $limite = 100): array
    {
        try {
            $where = '1=1';
            $params = [];
            if (!empty($filtros['status'])) {
                $where .= ' AND o.status = ?';
                $params[] = (string)$filtros['status'];
            }
            if (!empty($filtros['severidade'])) {
                $where .= ' AND o.severidade = ?';
                $params[] = (string)$filtros['severidade'];
            }
            if (!empty($filtros['pedido_id'])) {
                $where .= ' AND o.pedido_id = ?';
                $params[] = (int)$filtros['pedido_id'];
            }
            $params[] = max(1, min(500, $limite));
            $stmt = getPDO()->prepare(
                "SELECT o.*, c.nome AS cliente_nome
                 FROM " . self::TBL . " o
                 LEFT JOIN pedidos p ON p.id = o.pedido_id
                 LEFT JOIN usuarios c ON c.id = p.cliente_id
                 WHERE {$where}
                 ORDER BY FIELD(o.severidade,'critica','alta','media','baixa'), o.criado_em DESC
                 LIMIT ?"
            );
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Ocorrencia::listar: ' . $e->getMessage());
            return [];
        }
    }

    public static function buscarPorId(int $id): ?array
    {
        $stmt = getPDO()->prepare(
            "SELECT o.*, c.nome AS cliente_nome
             FROM " . self::TBL . " o
             LEFT JOIN pedidos p ON p.id = o.pedido_id
             LEFT JOIN usuarios c ON c.id = p.cliente_id
             WHERE o.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function contarPorStatus(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT status, COUNT(*) AS total FROM " . self::TBL . " GROUP BY status"
            );
            $contagem = ['aberta' => 0, 'em_analise' => 0, 'resolvida' => 0, 'arquivada' => 0];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $contagem[(string)$row['status']] = (int)$row['total'];
            }
            return $contagem;
        } catch (PDOException $e) {
            error_log('Ocorrencia::contarPorStatus: ' . $e->getMessage());
            return ['aberta' => 0, 'em_analise' => 0, 'resolvida' => 0, 'arquivada' => 0];
        }
    }

    public static function atualizarStatus(int $id, string $status, ?int $resolvidoPor = null, ?string $resolucao = null): bool
    {
        $statusValidos = ['aberta', 'em_analise', 'resolvida', 'arquivada'];
        if (!in_array($status, $statusValidos, true)) {
            return false;
        }
        if ($status === 'resolvida') {
            $stmt = getPDO()->prepare(
                "UPDATE " . self::TBL . "
                 SET status = ?, resolucao = ?, resolvido_por = ?, resolvido_em = NOW(), atualizado_em = NOW()
                 WHERE id = ?"
            );
            return $stmt->execute([$status, $resolucao, $resolvidoPor, $id]);
        }
        $stmt = getPDO()->prepare(
            "UPDATE " . self::TBL . " SET status = ?, atualizado_em = NOW() WHERE id = ?"
        );
        return $stmt->execute([$status, $id]);
    }
}
