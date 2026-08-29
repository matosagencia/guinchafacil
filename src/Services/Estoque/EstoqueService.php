<?php

declare(strict_types=1);

// File: guinchafacil/src/Services/Estoque/EstoqueService.php
// ROADMAP socorro automotivo — Etapa 8 (produtos e estoque).
//
// Toda mudança de saldo passa por aqui e grava um movimento no livro-razão
// (estoque_movimentos). A baixa por pedido é idempotente via hash — reenviar
// a mesma baixa (retry, refresh) não debita duas vezes. Trava a linha de
// estoque com SELECT ... FOR UPDATE dentro da transação para evitar corrida.
//
// Sem gatilho financeiro: dar baixa em estoque NÃO cria cobrança. Quem decide
// dinheiro é o fluxo financeiro (ChargePolicyService), quando/se for ligado.

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../Models/ProviderProdutoEstoque.php';
require_once __DIR__ . '/../Logger.php';

class EstoqueService
{
    public static function disponivel(int $providerId, int $produtoId): int
    {
        $linha = ProviderProdutoEstoque::buscar($providerId, $produtoId);
        return $linha && (int)$linha['active'] === 1 ? max(0, (int)$linha['quantidade']) : 0;
    }

    /** Entrada de estoque (compra/reposição). Sempre soma. */
    public static function entrada(int $providerId, int $produtoId, int $qtd, ?string $descricao = null): bool
    {
        if ($qtd <= 0) {
            return false;
        }
        return self::aplicar($providerId, $produtoId, null, 'ENTRADA', $qtd, $descricao ?? 'Entrada de estoque', null);
    }

    /**
     * Baixa por pedido (consumo de produto no atendimento). Idempotente:
     * o hash é derivado de (pedido, produto, prestador), então a mesma baixa
     * nunca debita duas vezes. Retorna false se não houver saldo suficiente.
     */
    public static function baixarPorPedido(int $providerId, int $produtoId, int $pedidoId, int $qtd = 1, ?string $descricao = null): bool
    {
        if ($qtd <= 0) {
            return false;
        }
        $hash = "SAIDA:{$pedidoId}:{$produtoId}:{$providerId}";
        return self::aplicar($providerId, $produtoId, $pedidoId, 'SAIDA', -abs($qtd), $descricao ?? "Baixa por pedido #{$pedidoId}", $hash);
    }

    /** Estorno de uma baixa (ex.: serviço cancelado após consumo registrado). Idempotente por hash. */
    public static function estornarPorPedido(int $providerId, int $produtoId, int $pedidoId, int $qtd = 1): bool
    {
        if ($qtd <= 0) {
            return false;
        }
        $hash = "ESTORNO:{$pedidoId}:{$produtoId}:{$providerId}";
        return self::aplicar($providerId, $produtoId, $pedidoId, 'ESTORNO', abs($qtd), "Estorno de baixa do pedido #{$pedidoId}", $hash);
    }

    /**
     * Núcleo transacional. $delta é o ajuste no saldo (negativo para SAIDA).
     * Se $hash != null e já existir um movimento com esse hash, é no-op
     * (idempotente). SAIDA que estouraria saldo negativo é rejeitada.
     */
    private static function aplicar(int $providerId, int $produtoId, ?int $pedidoId, string $tipo, int $delta, string $descricao, ?string $hash): bool
    {
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            if ($hash !== null) {
                $stmt = $pdo->prepare("SELECT id FROM estoque_movimentos WHERE hash_idempotencia = ? LIMIT 1");
                $stmt->execute([$hash]);
                if ($stmt->fetchColumn()) {
                    $pdo->commit(); // já aplicado — no-op idempotente
                    return true;
                }
            }

            $lock = self::lockClause($pdo);
            $stmt = $pdo->prepare("SELECT * FROM provider_produtos_estoque WHERE provider_id = ? AND produto_id = ?" . $lock);
            $stmt->execute([$providerId, $produtoId]);
            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            $saldoAtual = $linha ? (int)$linha['quantidade'] : 0;
            $novoSaldo = $saldoAtual + $delta;

            if ($novoSaldo < 0) {
                $pdo->rollBack();
                Logger::log(Logger::LEVEL_WARN, 'EstoqueService', 'aplicar', 'estoque',
                    "Saldo insuficiente: prestador #{$providerId} produto #{$produtoId} (saldo {$saldoAtual}, delta {$delta})",
                    ['provider_id' => $providerId, 'produto_id' => $produtoId, 'delta' => $delta, 'saldo' => $saldoAtual]);
                return false;
            }

            if ($linha) {
                $pdo->prepare("UPDATE provider_produtos_estoque SET quantidade = ?, atualizado_em = NOW() WHERE id = ?")
                    ->execute([$novoSaldo, (int)$linha['id']]);
            } else {
                // Cria a linha de estoque no ato (ex.: primeira entrada).
                $pdo->prepare("INSERT INTO provider_produtos_estoque (provider_id, produto_id, quantidade, active, criado_em, atualizado_em) VALUES (?,?,?,1,NOW(),NOW())")
                    ->execute([$providerId, $produtoId, $novoSaldo]);
            }

            try {
                $pdo->prepare(
                    "INSERT INTO estoque_movimentos (provider_id, produto_id, pedido_id, tipo, quantidade, saldo_apos, hash_idempotencia, descricao, criado_em)
                     VALUES (?,?,?,?,?,?,?,?,NOW())"
                )->execute([$providerId, $produtoId, $pedidoId, $tipo, $delta, $novoSaldo, $hash, $descricao]);
            } catch (PDOException $dup) {
                // Corrida: outra requisição gravou o mesmo hash primeiro.
                $pdo->rollBack();
                return true; // idempotente — considera aplicado
            }

            $pdo->commit();

            Logger::log(Logger::LEVEL_INFO, 'EstoqueService', 'aplicar', 'estoque',
                "Movimento {$tipo}: prestador #{$providerId} produto #{$produtoId} delta {$delta} -> saldo {$novoSaldo}",
                ['provider_id' => $providerId, 'produto_id' => $produtoId, 'pedido_id' => $pedidoId, 'tipo' => $tipo, 'saldo_apos' => $novoSaldo]);

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::exception('EstoqueService', 'aplicar', 'estoque', $e,
                ['provider_id' => $providerId, 'produto_id' => $produtoId, 'tipo' => $tipo]);
            return false;
        }
    }

    /** SQLite (testes) não suporta FOR UPDATE; MySQL sim. */
    private static function lockClause(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }

    /**
     * §COBERTURA-RAIO-01 (06/08/2026): reverte a baixa de estoque de um
     * orçamento APROVADO quando o pedido é cancelado depois disso — sem
     * isso, o saldo do prestador fica permanentemente errado (peça
     * computada como usada num serviço que não aconteceu). Item sem
     * produto_id (mão de obra/serviço) é ignorado, como sempre.
     *
     * Método central e único (antes existia uma cópia igual dentro de
     * CancelamentoService — movida pra cá porque `PedidoTransitionService::
     * cancelByAdmin()` também cancela pedidos em em_execucao_servico/
     * autorizacao_servico_pendente com orçamento já aprovado, via
     * AdminController::cancelarPedido() e DemandaService — mesmo gap,
     * mesmo fix, um lugar só). Nunca lança exceção pro chamador — falha
     * aqui não pode derrubar o cancelamento em si, só fica em log pra
     * reconciliação manual.
     */
    public static function estornarEstoqueDeOrcamentoAprovado(int $pedidoId): void
    {
        try {
            require_once __DIR__ . '/../../Models/PedidoOrcamento.php';
            require_once __DIR__ . '/../../Models/Pedido.php';

            $orcamento = PedidoOrcamento::buscarPorPedido($pedidoId);
            if (!$orcamento || $orcamento['status'] !== PedidoOrcamento::APROVADO || empty($orcamento['itens'])) {
                return;
            }

            $pedido = Pedido::buscarPorId($pedidoId);
            $providerId = (int)($pedido['guincho_id'] ?? 0);
            if ($providerId <= 0) {
                return;
            }

            foreach ($orcamento['itens'] as $item) {
                $produtoId = (int)($item['produto_id'] ?? 0);
                if ($produtoId <= 0) {
                    continue;
                }
                $qtd = max(1, (int)($item['quantidade'] ?? 1));
                self::estornarPorPedido($providerId, $produtoId, $pedidoId, $qtd);
            }
        } catch (Throwable $e) {
            Logger::exception('EstoqueService', 'estornarEstoqueDeOrcamentoAprovado', 'estoque', $e, ['pedido_id' => $pedidoId]);
        }
    }
}
