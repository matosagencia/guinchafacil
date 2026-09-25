<?php

declare(strict_types=1);

final class PedidoOrcamentoPrevio
{
    public const PENDENTE_CLIENTE = 'PENDENTE_CLIENTE';
    public const PENDENTE = 'PENDENTE';
    public const APROVADO = 'APROVADO';
    public const RECUSADO_SOLICITANDO_REBOQUE = 'RECUSADO_SOLICITANDO_REBOQUE';
    public const REJEITADO = 'REJEITADO';
    public const EXPIRADO = 'EXPIRADO';

    public static function criar(array $dados, ?PDO $pdo = null): int
    {
        $min = (float)($dados['estimativa_minima'] ?? -1);
        $max = (float)($dados['estimativa_maxima'] ?? -1);
        $taxa = (float)($dados['taxa_diagnostico_local'] ?? 0);
        if ($min < 0 || $max < $min || $taxa < 0) {
            throw new InvalidArgumentException('Valores inválidos para o orçamento prévio.');
        }
        $pdo ??= getPDO();
        $stmt = $pdo->prepare('INSERT INTO pedido_orcamentos_previos
            (pedido_id, provider_id, taxa_diagnostico_local, estimativa_minima, estimativa_maxima,
             descricao_avaria, abater_diagnostico_na_os, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([
            (int)$dados['pedido_id'], (int)$dados['provider_id'], $taxa, $min, $max,
            trim((string)($dados['descricao_avaria'] ?? '')), !empty($dados['abater_diagnostico_na_os']) ? 1 : 0,
            self::PENDENTE_CLIENTE,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function criarProviderOrcamento(array $dados, ?PDO $pdo = null): int
    {
        $pdo ??= getPDO();
        $maoObra = round(max(0.0, (float)($dados['valor_mao_obra'] ?? 0)), 2);
        $pecas = round(max(0.0, (float)($dados['valor_pecas'] ?? 0)), 2);
        $total = round((float)($dados['valor_total'] ?? ($maoObra + $pecas)), 2);
        if (abs($total - ($maoObra + $pecas)) > 0.01) {
            throw new InvalidArgumentException('Total do orçamento deve ser a soma de mão de obra e peças.');
        }

        $columns = self::columns($pdo, 'pedido_orcamentos_previos');
        if (isset($columns['valor_mao_obra'], $columns['valor_pecas'], $columns['valor_total'])) {
            $stmt = $pdo->prepare('INSERT INTO pedido_orcamentos_previos
                (pedido_id, provider_id, valor_mao_obra, valor_pecas, valor_total, taxa_saida_abater, status, descricao, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                (int)$dados['pedido_id'],
                (int)$dados['provider_id'],
                $maoObra,
                $pecas,
                $total,
                round(max(0.0, (float)($dados['taxa_saida_abater'] ?? 0)), 2),
                self::PENDENTE,
                trim((string)($dados['descricao'] ?? $dados['descricao_avaria'] ?? '')),
            ]);
            return (int)$pdo->lastInsertId();
        }

        return self::criar([
            'pedido_id' => (int)$dados['pedido_id'],
            'provider_id' => (int)$dados['provider_id'],
            'taxa_diagnostico_local' => round(max(0.0, (float)($dados['taxa_saida_abater'] ?? 0)), 2),
            'estimativa_minima' => $total,
            'estimativa_maxima' => $total,
            'descricao_avaria' => trim((string)($dados['descricao'] ?? $dados['descricao_avaria'] ?? '')),
            'abater_diagnostico_na_os' => !empty($dados['taxa_saida_abater']),
        ], $pdo);
    }

    public static function buscarPorPedido(int $pedidoId, ?PDO $pdo = null): ?array
    {
        $pdo ??= getPDO();
        $stmt = $pdo->prepare('SELECT * FROM pedido_orcamentos_previos WHERE pedido_id = ? LIMIT 1');
        $stmt->execute([$pedidoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function aprovar(int $id, ?PDO $pdo = null): bool
    {
        $pdo ??= getPDO();
        $columns = self::columns($pdo, 'pedido_orcamentos_previos');
        $approvedColumn = isset($columns['approved_at']) ? 'approved_at' : 'termo_aceite_cliente_at';
        $stmt = $pdo->prepare("UPDATE pedido_orcamentos_previos SET status = 'APROVADO', {$approvedColumn} = NOW(), updated_at = NOW() WHERE id = ? AND status IN ('PENDENTE_CLIENTE', 'PENDENTE')");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function recusarSolicitandoReboque(int $id, ?PDO $pdo = null): bool
    {
        $pdo ??= getPDO();
        $columns = self::columns($pdo, 'pedido_orcamentos_previos');
        $rejected = isset($columns['rejected_at']) ? ', rejected_at = NOW()' : '';
        $stmt = $pdo->prepare("UPDATE pedido_orcamentos_previos SET status = ?, updated_at = NOW() {$rejected} WHERE id = ? AND status IN ('PENDENTE_CLIENTE', 'PENDENTE')");
        $stmt->execute([self::RECUSADO_SOLICITANDO_REBOQUE, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function calcularAbatimento(array $orcamento, float $valorOs): float
    {
        $taxaSaidaAbater = (float)($orcamento['taxa_saida_abater'] ?? 0);
        if ($taxaSaidaAbater > 0) {
            return min(max(0.0, $valorOs), $taxaSaidaAbater);
        }
        if (empty($orcamento['abater_diagnostico_na_os'])) {
            return 0.0;
        }
        return min(max(0.0, $valorOs), max(0.0, (float)($orcamento['taxa_diagnostico_local'] ?? 0)));
    }

    private static function columns(PDO $pdo, string $table): array
    {
        $columns = [];
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $row) {
                    $columns[(string)$row['name']] = true;
                }
                return $columns;
            }
            foreach ($pdo->query('SHOW COLUMNS FROM ' . $table) as $row) {
                $columns[(string)$row['Field']] = true;
            }
        } catch (Throwable) {
        }
        return $columns;
    }
}
