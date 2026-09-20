<?php

declare(strict_types=1);

final class PedidoOrcamentoPrevio
{
    public const PENDENTE_CLIENTE = 'PENDENTE_CLIENTE';
    public const APROVADO = 'APROVADO';
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
        $stmt = $pdo->prepare("UPDATE pedido_orcamentos_previos SET status = 'APROVADO', termo_aceite_cliente_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'PENDENTE_CLIENTE'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function calcularAbatimento(array $orcamento, float $valorOs): float
    {
        if (empty($orcamento['abater_diagnostico_na_os'])) {
            return 0.0;
        }
        return min(max(0.0, $valorOs), max(0.0, (float)($orcamento['taxa_diagnostico_local'] ?? 0)));
    }
}
