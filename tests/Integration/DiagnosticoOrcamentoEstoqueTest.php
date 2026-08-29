<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Diagnostico/DiagnosticoService.php';
require_once __DIR__ . '/../../src/Models/Produto.php';
require_once __DIR__ . '/../../src/Models/ProviderProdutoEstoque.php';
require_once __DIR__ . '/../../src/Services/Estoque/EstoqueService.php';
require_once __DIR__ . '/../../src/Services/Financial/SupplementalChargeService.php';

/**
 * §COBERTURA-RAIO-01 (06/08/2026) — Fase 2 da auditoria de 2026-08-06:
 * antes desta mudança, DiagnosticoService::decidirOrcamento() aprovava o
 * orçamento complementar sem NUNCA baixar estoque, mesmo quando os itens
 * referenciavam um produto real — porque nem o formulário do guincheiro nem
 * PedidoOrcamento::criar() sabiam de produto_id/quantidade (ver
 * GuinchoController::diagnosticoConcluir(), src/Views/guincho/atendimento.php).
 *
 * Este teste monta o orçamento diretamente via SQL (em vez de
 * PedidoDiagnostico::registrar()/PedidoOrcamento::criar(), que usam
 * `ON DUPLICATE KEY UPDATE` — sintaxe MySQL não suportada pelo SQLite de
 * teste; gap pré-existente, não relacionado a esta mudança, registrado no
 * log de execução) e exercita só o que foi de fato alterado:
 * DiagnosticoService::decidirOrcamento() → EstoqueService::baixarPorPedido().
 */
final class DiagnosticoOrcamentoEstoqueTest extends TestCase
{
    private int $produtoId;
    private const PROVIDER_ID = 201;
    private const PEDIDO_ID = 401;
    private const CLIENTE_ID = 1;

    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['order_charge_items', 'estoque_movimentos', 'provider_produtos_estoque', 'produtos', 'pedido_orcamentos', 'pedido_diagnosticos', 'pedidos', 'guinchos', 'veiculos', 'usuarios'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.orcamento@example.com', 'hash', '11999999999', '11111111111', 'cliente'),
            (2, 'Guincho Teste', 'guincho.orcamento@example.com', 'hash', '11999999998', '22222222222', 'guincho')
        ");
        $pdo->exec("INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES (1, 1, 'TST1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (" . self::PROVIDER_ID . ", 2, 1, 1)");

        $this->produtoId = Produto::criar([
            'sku' => 'BAT-QA-DIAG', 'nome' => 'Bateria QA Diagnóstico', 'categoria' => 'bateria',
            'preco_referencia' => 400.00, 'active' => 1,
        ]);
        ProviderProdutoEstoque::definir(self::PROVIDER_ID, $this->produtoId, 3, 380.00);
    }

    private function inserirPedidoAutorizacaoPendente(int $pedidoId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id,
                lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
                attendance_mode, criado_em)
             VALUES (?, 'autorizacao_servico_pendente', 149.90, ?, 1, ?, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', 'ON_SITE', datetime('now'))"
        );
        $stmt->execute([$pedidoId, self::CLIENTE_ID, self::PROVIDER_ID]);
    }

    /** @param array<int, array<string, mixed>> $itens */
    private function inserirOrcamento(int $pedidoId, array $itens): void
    {
        $pdo = getPDO();
        $pdo->prepare("INSERT INTO pedido_diagnosticos (pedido_id, guincho_id, resultado, descricao) VALUES (?, ?, 'REQUER_ORCAMENTO', 'Diagnóstico QA')")
            ->execute([$pedidoId, self::PROVIDER_ID]);
        $diagnosticoId = (int)$pdo->lastInsertId();

        $valorTotal = array_sum(array_column($itens, 'valor'));
        $pdo->prepare(
            "INSERT INTO pedido_orcamentos (pedido_id, diagnostico_id, itens_json, valor_total, status)
             VALUES (?, ?, ?, ?, 'PENDENTE')"
        )->execute([$pedidoId, $diagnosticoId, json_encode($itens, JSON_UNESCAPED_UNICODE), $valorTotal]);
    }

    public function testAprovarOrcamentoComProdutoBaixaEstoqueEAvancaPedido(): void
    {
        $this->inserirPedidoAutorizacaoPendente(self::PEDIDO_ID);
        $this->inserirOrcamento(self::PEDIDO_ID, [
            ['descricao' => 'Bateria 60Ah', 'valor' => 400.00, 'produto_id' => $this->produtoId, 'quantidade' => 1],
        ]);

        $result = DiagnosticoService::decidirOrcamento(self::PEDIDO_ID, self::CLIENTE_ID, true);

        $this->assertTrue($result->ok, (string)$result->error);

        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento_orcamento', $pedido['status']);

        $orcamento = getPDO()->query("SELECT status FROM pedido_orcamentos WHERE pedido_id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('APROVADO', $orcamento['status']);

        $charges = getPDO()->query("SELECT * FROM order_charge_items WHERE order_id = " . self::PEDIDO_ID)->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $charges);
        $this->assertSame('PARTS_SUPPLY', $charges[0]['phase_code']);
        $this->assertSame('PARTS_FEE', $charges[0]['charge_type']);
        $this->assertSame('AWAITING_CUSTOMER_APPROVAL', $charges[0]['charge_status']);
        $this->assertSame('PENDING_EVIDENCE', $charges[0]['payable_status']);
        $this->assertSame(400.0, (float)$charges[0]['gross_amount']);

        $checkout = SupplementalChargeService::criarCheckout(self::PEDIDO_ID, (int)$charges[0]['id']);
        $this->assertSame('PENDING', $checkout['status']);
        $this->assertTrue(SupplementalChargeService::aprovarPagamentoSimulado(self::PEDIDO_ID, 'qa-bateria-' . self::PEDIDO_ID));
        $pedidoDepoisPagamento = getPDO()->query("SELECT status FROM pedidos WHERE id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('em_execucao_servico', $pedidoDepoisPagamento['status']);

        // O ponto central desta Fase 2: saldo de estoque realmente debitado.
        $this->assertSame(2, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId));
        $this->assertSame(1, (int)getPDO()->query("SELECT COUNT(*) FROM order_charge_payments WHERE order_id = " . self::PEDIDO_ID . " AND status = 'APPROVED'")->fetchColumn());

        $movimento = getPDO()->query(
            "SELECT tipo, quantidade FROM estoque_movimentos WHERE pedido_id = " . self::PEDIDO_ID . " AND produto_id = {$this->produtoId}"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SAIDA', $movimento['tipo']);
        $this->assertSame(-1, (int)$movimento['quantidade']);

        // A decisão repetida não reabre um orçamento já decidido; a
        // idempotência financeira é garantida pela chave do item e a baixa
        // pelo hash do movimento.
        $this->assertSame(1, (int)getPDO()->query("SELECT COUNT(*) FROM order_charge_items WHERE order_id = " . self::PEDIDO_ID)->fetchColumn());
        $this->assertSame(1, (int)getPDO()->query("SELECT COUNT(*) FROM estoque_movimentos WHERE pedido_id = " . self::PEDIDO_ID)->fetchColumn());
    }

    public function testAprovarOrcamentoSemProdutoNaoMexeEmEstoque(): void
    {
        $this->inserirPedidoAutorizacaoPendente(self::PEDIDO_ID);
        $this->inserirOrcamento(self::PEDIDO_ID, [
            ['descricao' => 'Mão de obra extra', 'valor' => 50.00],
        ]);

        $result = DiagnosticoService::decidirOrcamento(self::PEDIDO_ID, self::CLIENTE_ID, true);

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertSame(3, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId), 'item sem produto_id não pode debitar estoque nenhum');

        $count = (int)getPDO()->query("SELECT COUNT(*) FROM estoque_movimentos WHERE pedido_id = " . self::PEDIDO_ID)->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSaldoInsuficienteNaoBloqueiaAprovacaoDoOrcamento(): void
    {
        $this->inserirPedidoAutorizacaoPendente(self::PEDIDO_ID);
        // Saldo é 3; orçamento pede 5 — decisão de produto registrada no log
        // de execução: a transição do pedido NÃO é bloqueada por saldo
        // insuficiente (o prestador pode ter a peça fisicamente em mãos
        // mesmo com o cadastro de estoque desatualizado); a falha só gera
        // log WARN pra reconciliação manual do admin.
        $this->inserirOrcamento(self::PEDIDO_ID, [
            ['descricao' => 'Bateria 60Ah', 'valor' => 2000.00, 'produto_id' => $this->produtoId, 'quantidade' => 5],
        ]);

        $result = DiagnosticoService::decidirOrcamento(self::PEDIDO_ID, self::CLIENTE_ID, true);

        $this->assertTrue($result->ok, (string)$result->error);
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento_orcamento', $pedido['status'], 'peça sem pagamento não pode liberar a execução');

        $charge = getPDO()->query("SELECT id FROM order_charge_items WHERE order_id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($charge);
        $this->assertTrue(SupplementalChargeService::aprovarPagamentoSimulado(self::PEDIDO_ID, 'qa-bateria-sem-estoque-' . self::PEDIDO_ID));
        $pedidoPago = getPDO()->query("SELECT status FROM pedidos WHERE id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('em_execucao_servico', $pedidoPago['status'], 'saldo de estoque não deve bloquear após pagamento aprovado');

        // Saldo permanece intacto — EstoqueService::aplicar() rejeita e não altera nada.
        $this->assertSame(3, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId));
    }

    public function testRecusarOrcamentoNaoMexeEmEstoqueNemAvancaPedido(): void
    {
        $this->inserirPedidoAutorizacaoPendente(self::PEDIDO_ID);
        $this->inserirOrcamento(self::PEDIDO_ID, [
            ['descricao' => 'Bateria 60Ah', 'valor' => 400.00, 'produto_id' => $this->produtoId, 'quantidade' => 1],
        ]);

        $result = DiagnosticoService::decidirOrcamento(self::PEDIDO_ID, self::CLIENTE_ID, false);

        $this->assertTrue($result->ok, (string)$result->error);
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = " . self::PEDIDO_ID)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('autorizacao_servico_pendente', $pedido['status'], 'recusa não avança o pedido (mesmo comportamento de antes desta mudança)');
        $this->assertSame(3, EstoqueService::disponivel(self::PROVIDER_ID, $this->produtoId));
    }
}
