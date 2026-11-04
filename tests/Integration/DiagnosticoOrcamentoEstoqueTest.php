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
    private int $providerId;
    private int $pedidoId;
    private int $clienteId;
    private int $veiculoId;

    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec('DROP TRIGGER IF EXISTS fail_pedido_transition_update');
        foreach (['order_charge_payments', 'order_charge_items', 'estoque_movimentos', 'provider_produtos_estoque', 'produtos', 'pedido_orcamentos', 'pedido_diagnosticos', 'pedidos', 'guinchos', 'veiculos', 'usuarios'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $suffix = bin2hex(random_bytes(4));
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo) VALUES (?, ?, 'hash', '11999999999', ?, 'cliente')")
            ->execute(['Cliente Teste', 'cliente.orcamento.' . $suffix . '@example.com', $suffix . '111111']);
        $this->clienteId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, telefone, cpf, tipo) VALUES (?, ?, 'hash', '11999999998', ?, 'guincho')")
            ->execute(['Guincho Teste', 'guincho.orcamento.' . $suffix . '@example.com', $suffix . '222222']);
        $guinchoUsuarioId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO veiculos (usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES (?, ?, 'Marca', 'Modelo', 2020, 'Prata', 'carro')")
            ->execute([$this->clienteId, 'TST' . strtoupper(substr($suffix, 0, 4))]);
        $this->veiculoId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO guinchos (usuario_id, aprovado, disponivel) VALUES (?, 1, 1)")->execute([$guinchoUsuarioId]);
        $this->providerId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO providers (id, provider_type, legal_name, created_at, updated_at) VALUES (?, 'GUINCHO', 'Guincho QA', NOW(), NOW())")
            ->execute([$this->providerId]);
        $this->pedidoId = 0;

        $this->produtoId = Produto::criar([
            'sku' => 'BAT-QA-DIAG', 'nome' => 'Bateria QA Diagnóstico', 'categoria' => 'bateria',
            'preco_referencia' => 400.00, 'active' => 1,
        ]);
        ProviderProdutoEstoque::definir($this->providerId, $this->produtoId, 3, 380.00);
    }

    private function inserirPedidoAutorizacaoPendente(int $pedidoId): void
    {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id,
                lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
                attendance_mode, criado_em)
            VALUES (?, 'autorizacao_servico_pendente', 149.90, ?, ?, ?, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', 'ON_SITE', NOW())"
        );
        $stmt->execute([$pedidoId, $this->clienteId, $this->veiculoId, $this->providerId]);
        $this->pedidoId = $pedidoId;
    }

    /** @param array<int, array<string, mixed>> $itens */
    private function inserirOrcamento(int $pedidoId, array $itens): void
    {
        $pdo = getPDO();
        $pdo->prepare("INSERT INTO pedido_diagnosticos (pedido_id, guincho_id, resultado, descricao) VALUES (?, ?, 'REQUER_ORCAMENTO', 'Diagnóstico QA')")
            ->execute([$pedidoId, $this->providerId]);
        $diagnosticoId = (int)$pdo->lastInsertId();

        $valorTotal = array_sum(array_column($itens, 'valor'));
        $pdo->prepare(
            "INSERT INTO pedido_orcamentos (pedido_id, diagnostico_id, itens_json, valor_total, status)
             VALUES (?, ?, ?, ?, 'PENDENTE')"
        )->execute([$pedidoId, $diagnosticoId, json_encode($itens, JSON_UNESCAPED_UNICODE), $valorTotal]);
    }

    public function testAprovarOrcamentoComProdutoBaixaEstoqueEAvancaPedido(): void
    {
        $this->inserirPedidoAutorizacaoPendente($this->pedidoId = (int)getPDO()->query('SELECT COALESCE(MAX(id),0)+1 FROM pedidos')->fetchColumn());
        $this->inserirOrcamento($this->pedidoId, [
            ['descricao' => 'Bateria 60Ah', 'valor' => 400.00, 'produto_id' => $this->produtoId, 'quantidade' => 1],
        ]);

        $result = DiagnosticoService::decidirOrcamento($this->pedidoId, $this->clienteId, true);

        $this->assertTrue($result->ok, (string)$result->error);

        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento_orcamento', $pedido['status']);

        $orcamento = getPDO()->query("SELECT status FROM pedido_orcamentos WHERE pedido_id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('APROVADO', $orcamento['status']);

        $charges = getPDO()->query("SELECT * FROM order_charge_items WHERE order_id = " . $this->pedidoId)->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $charges);
        $this->assertSame('PARTS_SUPPLY', $charges[0]['phase_code']);
        $this->assertSame('PARTS_FEE', $charges[0]['charge_type']);
        $this->assertSame('AWAITING_CUSTOMER_APPROVAL', $charges[0]['charge_status']);
        $this->assertSame('PENDING_EVIDENCE', $charges[0]['payable_status']);
        $this->assertSame(400.0, (float)$charges[0]['gross_amount']);

        $checkout = SupplementalChargeService::criarCheckout($this->pedidoId, (int)$charges[0]['id']);
        $this->assertSame('PENDING', $checkout['status']);
        $this->assertTrue(SupplementalChargeService::aprovarPagamentoSimulado($this->pedidoId, 'qa-bateria-' . $this->pedidoId));
        $pedidoDepoisPagamento = getPDO()->query("SELECT status FROM pedidos WHERE id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('em_execucao_servico', $pedidoDepoisPagamento['status']);

        // O ponto central desta Fase 2: saldo de estoque realmente debitado.
        $this->assertSame(2, EstoqueService::disponivel($this->providerId, $this->produtoId));
        $this->assertSame(1, (int)getPDO()->query("SELECT COUNT(*) FROM order_charge_payments WHERE order_id = " . $this->pedidoId . " AND status = 'APPROVED'")->fetchColumn());

        $movimento = getPDO()->query(
            "SELECT tipo, quantidade FROM estoque_movimentos WHERE pedido_id = " . $this->pedidoId . " AND produto_id = {$this->produtoId}"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('SAIDA', $movimento['tipo']);
        $this->assertSame(-1, (int)$movimento['quantidade']);

        // A decisão repetida não reabre um orçamento já decidido; a
        // idempotência financeira é garantida pela chave do item e a baixa
        // pelo hash do movimento.
        $this->assertSame(1, (int)getPDO()->query("SELECT COUNT(*) FROM order_charge_items WHERE order_id = " . $this->pedidoId)->fetchColumn());
        $this->assertSame(1, (int)getPDO()->query("SELECT COUNT(*) FROM estoque_movimentos WHERE pedido_id = " . $this->pedidoId)->fetchColumn());
    }

    public function testAprovarOrcamentoSemProdutoNaoMexeEmEstoque(): void
    {
        $this->inserirPedidoAutorizacaoPendente($this->pedidoId = (int)getPDO()->query('SELECT COALESCE(MAX(id),0)+1 FROM pedidos')->fetchColumn());
        $this->inserirOrcamento($this->pedidoId, [
            ['descricao' => 'Mão de obra extra', 'valor' => 50.00],
        ]);

        $result = DiagnosticoService::decidirOrcamento($this->pedidoId, $this->clienteId, true);

        $this->assertTrue($result->ok, (string)$result->error);
        $this->assertSame(3, EstoqueService::disponivel($this->providerId, $this->produtoId), 'item sem produto_id nao pode debitar estoque nenhum');

        $count = (int)getPDO()->query("SELECT COUNT(*) FROM estoque_movimentos WHERE pedido_id = " . $this->pedidoId)->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testSaldoInsuficienteNaoBloqueiaAprovacaoDoOrcamento(): void
    {
        $this->inserirPedidoAutorizacaoPendente($this->pedidoId = (int)getPDO()->query('SELECT COALESCE(MAX(id),0)+1 FROM pedidos')->fetchColumn());
        // Saldo é 3; orçamento pede 5 — decisão de produto registrada no log
        // de execução: a transição do pedido NÃO é bloqueada por saldo
        // insuficiente (o prestador pode ter a peça fisicamente em mãos
        // mesmo com o cadastro de estoque desatualizado); a falha só gera
        // log WARN pra reconciliação manual do admin.
        $this->inserirOrcamento($this->pedidoId, [
            ['descricao' => 'Bateria 60Ah', 'valor' => 2000.00, 'produto_id' => $this->produtoId, 'quantidade' => 5],
        ]);

        $result = DiagnosticoService::decidirOrcamento($this->pedidoId, $this->clienteId, true);

        $this->assertTrue($result->ok, (string)$result->error);
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento_orcamento', $pedido['status'], 'peça sem pagamento não pode liberar a execução');

        $charge = getPDO()->query("SELECT id FROM order_charge_items WHERE order_id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($charge);
        $this->assertTrue(SupplementalChargeService::aprovarPagamentoSimulado($this->pedidoId, 'qa-bateria-sem-estoque-' . $this->pedidoId));
        $pedidoPago = getPDO()->query("SELECT status FROM pedidos WHERE id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('em_execucao_servico', $pedidoPago['status'], 'saldo de estoque não deve bloquear após pagamento aprovado');

        // Saldo permanece intacto — EstoqueService::aplicar() rejeita e não altera nada.
        $this->assertSame(3, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }

    public function testRecusarOrcamentoNaoMexeEmEstoqueNemAvancaPedido(): void
    {
        $this->inserirPedidoAutorizacaoPendente($this->pedidoId = (int)getPDO()->query('SELECT COALESCE(MAX(id),0)+1 FROM pedidos')->fetchColumn());
        $this->inserirOrcamento($this->pedidoId, [
            ['descricao' => 'Bateria 60Ah', 'valor' => 400.00, 'produto_id' => $this->produtoId, 'quantidade' => 1],
        ]);

        $result = DiagnosticoService::decidirOrcamento($this->pedidoId, $this->clienteId, false);

        $this->assertTrue($result->ok, (string)$result->error);
        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = " . $this->pedidoId)->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('autorizacao_servico_pendente', $pedido['status'], 'recusa não avança o pedido (mesmo comportamento de antes desta mudança)');
        $this->assertSame(3, EstoqueService::disponivel($this->providerId, $this->produtoId));
    }
}
