<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/ExpiracaoPedidosService.php';

/**
 * §COBERTURA-RAIO-01 (06/08/2026) — gap identificado na auditoria de
 * 2026-08-06: ExpiracaoPedidosService (extraído de
 * tools/cron_cancelar_pedidos_expirados.php em 05/08/2026, o cancelamento +
 * estorno automático de pedidos sem aceite em 30 min) não tinha nenhum
 * teste PHPUnit dedicado — só cobertura E2E via
 * qa/suites/cobertura-timeout-estorno.spec.ts, mais lenta e menos precisa
 * pra garantir as regras de borda cobertas aqui.
 *
 * Usa metodo='paypal' (gateway não suportado) pro cenário de pagamento
 * aprovado — mesmo truque já usado em tests/Unit/EstornoServiceTest.php
 * para forçar falha de refund de forma determinística, sem chamada de rede
 * real (nem MercadoPago nem PagSeguro são chamados, então o teste roda
 * rápido e não depende de credenciais/sandbox).
 */
final class ExpiracaoPedidosServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['pagamentos', 'pedidos', 'usuarios', 'veiculos'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }
        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.expiracao@example.com', 'hash', '11999999999', '11111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'TST1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
    }

    private function inserirPedidoExpirado(int $id, string $status = 'aguardando_guincho'): void
    {
        $pdo = getPDO();
        $expirado = date('Y-m-d H:i:s', time() - 120); // 2 min atrás
        $stmt = $pdo->prepare(
            "INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id,
                lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
                expiracao_aceite, criado_em)
             VALUES (?, ?, 149.90, 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', ?, ?)"
        );
        $stmt->execute([$id, $status, $expirado, date('Y-m-d H:i:s', time() - 1800)]);
    }

    public function testPedidoExpiradoSemPagamentoECanceladoSemTentarEstorno(): void
    {
        $this->inserirPedidoExpirado(301);

        $metrics = ExpiracaoPedidosService::executar();

        $this->assertSame(1, $metrics['expired_found']);
        $this->assertSame(1, $metrics['cancelled']);
        $this->assertSame(0, $metrics['refunds_ok']);
        $this->assertSame(0, $metrics['refunds_failed']);
        $this->assertSame(0, $metrics['errors']);

        $pedido = getPDO()->query("SELECT status, motivo_cancelamento FROM pedidos WHERE id = 301")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('cancelado', $pedido['status']);
        $this->assertSame(ExpiracaoPedidosService::MOTIVO_TIMEOUT, $pedido['motivo_cancelamento']);
    }

    public function testPedidoExpiradoComPagamentoAprovadoTentaEstornoEContabilizaFalha(): void
    {
        $this->inserirPedidoExpirado(302);
        getPDO()->exec("
            INSERT INTO pagamentos (pedido_id, metodo, id_externo, status, valor_total, valor_guincho, valor_plataforma)
            VALUES (302, 'paypal', 'pp_qa_302', 'aprovado', 149.90, 119.92, 29.98)
        ");

        $metrics = ExpiracaoPedidosService::executar();

        $this->assertSame(1, $metrics['cancelled']);
        $this->assertSame(0, $metrics['refunds_ok'], 'gateway não suportado deve falhar o refund, não simular sucesso');
        $this->assertSame(1, $metrics['refunds_failed']);

        $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = 302")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('cancelado', $pedido['status'], 'cancelamento não pode depender do sucesso do refund');

        // Regra de negócio crítica: falha no refund NUNCA deixa o pagamento
        // preso em 'estornando' — ou completou ('estornado') ou voltou pra
        // 'aprovado' pra permitir nova tentativa manual.
        $pagamento = getPDO()->query("SELECT status FROM pagamentos WHERE pedido_id = 302")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aprovado', $pagamento['status']);
    }

    public function testPedidoAindaDentroDoPrazoNaoECancelado(): void
    {
        $pdo = getPDO();
        $futuro = date('Y-m-d H:i:s', time() + 600); // 10 min à frente
        $pdo->exec(
            "INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id,
                lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino,
                expiracao_aceite, criado_em)
             VALUES (303, 'aguardando_guincho', 149.90, 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', '{$futuro}', datetime('now'))"
        );

        $metrics = ExpiracaoPedidosService::executar();

        $this->assertSame(0, $metrics['expired_found']);
        $this->assertSame(0, $metrics['cancelled']);

        $pedido = $pdo->query("SELECT status FROM pedidos WHERE id = 303")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_guincho', $pedido['status']);
    }

    public function testPedidoJaCanceladoNaoEReprocessado(): void
    {
        $this->inserirPedidoExpirado(304, 'cancelado');

        $metrics = ExpiracaoPedidosService::executar();

        // Query base filtra por status='aguardando_guincho' — um pedido já
        // cancelado nunca entra no lote, mesmo com expiracao_aceite vencida.
        $this->assertSame(0, $metrics['expired_found']);
        $this->assertSame(0, $metrics['cancelled']);
    }

    public function testMultiplosPedidosExpiradosSaoProcessadosNaMesmaExecucao(): void
    {
        $this->inserirPedidoExpirado(305);
        $this->inserirPedidoExpirado(306);
        $this->inserirPedidoExpirado(307);

        $metrics = ExpiracaoPedidosService::executar();

        $this->assertSame(3, $metrics['expired_found']);
        $this->assertSame(3, $metrics['cancelled']);

        foreach ([305, 306, 307] as $id) {
            $pedido = getPDO()->query("SELECT status FROM pedidos WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
            $this->assertSame('cancelado', $pedido['status']);
        }
    }
}
