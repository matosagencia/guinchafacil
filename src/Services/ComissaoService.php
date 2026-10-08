<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';

final class ComissaoService
{
    public static function schemaDisponivel(PDO $pdo): bool
    {
        try {
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $cols = $pdo->query('PRAGMA table_info(pedidos)')->fetchAll(PDO::FETCH_ASSOC);
                return count(array_intersect(['comissao_valor', 'comissao_tipo', 'valor_liquido_parceiro'], array_column($cols, 'name'))) === 3;
            }
            return (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pedidos' AND COLUMN_NAME IN ('comissao_valor','comissao_tipo','valor_liquido_parceiro')")->fetchColumn() === 3;
        } catch (Throwable) { return false; }
    }
    public static function calcular(int $pedidoId, string $formaPagamento, float $valorTotal, ?int $cidadeId = null): array
    {
        $base = self::normalizarPercentual((float) Configuracao::get('comissao_plataforma', '0'));
        $extra = 0.0;
        if ($cidadeId !== null && $cidadeId > 0) {
            $stmt = getPDO()->prepare('SELECT percentual FROM comissoes_cidade WHERE cidade_id=? AND ativo=1 LIMIT 1');
            $stmt->execute([$cidadeId]);
            $extra = self::normalizarPercentual((float) ($stmt->fetchColumn() ?: 0));
        }
        $total = round(max(0.0, $valorTotal), 2);
        $percentual = round($base + $extra, 4);
        $valor = round($total * $percentual, 2);
        $tipo = strtolower(trim($formaPagamento)) === 'pago_na_chegada' ? 'faturada' : 'descontada';
        return ['percentual' => $percentual, 'valor' => $valor, 'tipo' => $tipo,
            'valor_liquido_parceiro' => $tipo === 'faturada' ? $total : round($total - $valor, 2)];
    }

    public static function persistirParaPedido(PDO $pdo, array $pedido): array
    {
        $id = (int) ($pedido['id'] ?? 0);
        if ($id < 1) throw new InvalidArgumentException('COMISSAO-001: pedido inválido.');
        $forma = (string) ($pedido['forma_pagamento_escolhida'] ?? '');
        $total = (float) ($pedido['custo_final'] ?? $pedido['custo_estimado'] ?? 0);
        $calc = self::calcular($id, $forma, $total, isset($pedido['cidade_id']) ? (int)$pedido['cidade_id'] : null);
        $stmt = $pdo->prepare('UPDATE pedidos SET comissao_valor=?, comissao_tipo=?, valor_liquido_parceiro=? WHERE id=? AND comissao_valor IS NULL');
        $stmt->execute([$calc['valor'], $calc['tipo'], $calc['valor_liquido_parceiro'], $id]);
        if ($stmt->rowCount() === 0) {
            $q = $pdo->prepare('SELECT comissao_valor, comissao_tipo, valor_liquido_parceiro FROM pedidos WHERE id=?'); $q->execute([$id]);
            $atual = $q->fetch(PDO::FETCH_ASSOC) ?: [];
            $calc['valor'] = (float)($atual['comissao_valor'] ?? $calc['valor']);
            $calc['tipo'] = (string)($atual['comissao_tipo'] ?? $calc['tipo']);
            $calc['valor_liquido_parceiro'] = (float)($atual['valor_liquido_parceiro'] ?? $calc['valor_liquido_parceiro']);
        }
        return $calc;
    }
    private static function normalizarPercentual(float $valor): float { return max(0.0, $valor > 1 ? $valor / 100 : $valor); }
}
