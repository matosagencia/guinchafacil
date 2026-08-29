<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Models/Pedido.php';

final class PedidoTransitionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        $pdo->exec('DROP TRIGGER IF EXISTS fail_pedido_transition_update');
        // Ordem importa: apagar filhos antes dos pais por causa das FKs.
        foreach (['pedido_idempotency', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes', 'app_logs'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        // §SPLIT-LIQUIDO-01: reserva_gateway_percentual=0 aqui é DELIBERADO —
        // isola este teste (que verifica só a matemática comissão/repasse)
        // do default de produção (0.045), documentado e coberto à parte em
        // PedidoTransitionServiceSplitLiquidoTest.
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('reserva_gateway_percentual', '0')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");

        // Fixtures mínimas que os testes abaixo referenciam via FK
        // (usuario 1 = cliente, usuarios 2/3 = donos dos guinchos, veiculo 1 = do cliente).
        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.teste@example.com', 'hash', '11999999999', '11111111111', 'cliente'),
            (2, 'Guincho Teste 2', 'guincho2.teste@example.com', 'hash', '11999999998', '22222222222', 'guincho'),
            (3, 'Guincho Teste 3', 'guincho3.teste@example.com', 'hash', '11999999997', '33333333333', 'guincho')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'TST1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
    }

    public function testApprovePaymentTransitionsPedidoAndUpdatesSplit(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino) VALUES (101, 'aguardando_pagamento', 100.00, 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES (101, 'mercadopago', 'pendente', 100.00)");

        $result = PedidoTransitionService::approvePayment(101, 'mp_test_101', '{"approved":true}');

        $this->assertTrue($result->ok, (string)$result->error);

        $pedido = $pdo->query("SELECT status, expiracao_aceite, raio_atual_km FROM pedidos WHERE id = 101")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_guincho', $pedido['status']);
        $this->assertNotEmpty($pedido['expiracao_aceite']);
        $this->assertSame('10', (string)$pedido['raio_atual_km']);

        $pagamento = $pdo->query("SELECT status, id_externo, valor_guincho, valor_plataforma FROM pagamentos WHERE pedido_id = 101")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aprovado', $pagamento['status']);
        $this->assertSame('mp_test_101', $pagamento['id_externo']);
        $this->assertEqualsWithDelta(85.00, (float)$pagamento['valor_guincho'], 0.01);
        $this->assertEqualsWithDelta(15.00, (float)$pagamento['valor_plataforma'], 0.01);
    }

    public function testRequeueByGuinchoReturnsPedidoToQueue(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (201, 2, 1, 0)");
        $pdo->exec("INSERT INTO pedidos (id, status, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino) VALUES (202, 'a_caminho', 1, 1, 201, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')");

        $result = PedidoTransitionService::requeueByGuincho(202, 201, 2, 'Pane mecânica', 5);

        $this->assertTrue($result->ok, (string)$result->error);

        $pedido = $pdo->query("SELECT status, guincho_id, motivo_cancelamento, expiracao_aceite FROM pedidos WHERE id = 202")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_guincho', $pedido['status']);
        $this->assertNull($pedido['guincho_id']);
        $this->assertStringContainsString('Pane mecânica', (string)$pedido['motivo_cancelamento']);
        $this->assertNotEmpty($pedido['expiracao_aceite']);

        $guincho = $pdo->query("SELECT disponivel FROM guinchos WHERE id = 201")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('1', (string)$guincho['disponivel']);
    }

    public function testCancelByClienteUsesStateMachineAndFreesGuincho(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel) VALUES (301, 3, 1, 0)");
        $pdo->exec("INSERT INTO pedidos (id, status, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino) VALUES (302, 'aguardando_guincho', 1, 1, 301, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')");

        $result = PedidoTransitionService::cancelByCliente(302, 1, 'Desisti do atendimento', 12.50);

        $this->assertTrue($result->ok, (string)$result->error);

        $pedido = $pdo->query("SELECT status, cancelado_por, taxa_cancelamento FROM pedidos WHERE id = 302")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('cancelado', $pedido['status']);
        $this->assertSame('cliente', $pedido['cancelado_por']);
        $this->assertEqualsWithDelta(12.50, (float)$pedido['taxa_cancelamento'], 0.01);

        $guincho = $pdo->query("SELECT disponivel FROM guinchos WHERE id = 301")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('1', (string)$guincho['disponivel']);
    }

    public function testPedidoModelLegacyStatusMutationIsBlocked(): void
    {
        $this->expectException(LogicException::class);
        Pedido::atualizarStatus(999, 'cancelado');
    }

    public function testFalhaIntermediariaFazRollbackSemEfeitosParciais(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos
            (id, status, cliente_id, veiculo_id, endereco_origem, endereco_destino)
            VALUES (401, 'aguardando_pagamento', 1, 1, 'Origem', 'Destino')");

        // O trigger aborta exatamente depois que a transição tenta persistir o
        // novo status. A exceção deve cair no catch da máquina e desfazer toda
        // a transação, deixando o pedido como estava antes da tentativa.
        $pdo->exec("CREATE TRIGGER fail_pedido_transition_update
            AFTER UPDATE OF status ON pedidos
            WHEN NEW.id = 401
            BEGIN
                SELECT RAISE(ABORT, 'falha intermediaria simulada');
            END");

        $result = PedidoTransitionService::transition(
            new PedidoTransitionRequest('admin', 1, 401, 'aguardando_guincho')
        );

        $this->assertFalse($result->ok);
        $this->assertSame('Erro interno ao atualizar o pedido.', $result->error);

        $pedido = $pdo->query(
            "SELECT status, guincho_id, expiracao_aceite, raio_atual_km
             FROM pedidos WHERE id = 401"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_pagamento', $pedido['status']);
        $this->assertNull($pedido['guincho_id']);
        $this->assertNull($pedido['expiracao_aceite']);
        $this->assertNull($pedido['raio_atual_km']);
        $this->assertFalse($pdo->inTransaction());
    }
}
