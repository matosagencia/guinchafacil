<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/CancelamentoService.php';
require_once __DIR__ . '/../../src/Models/CancellationSnapshot.php';
require_once __DIR__ . '/../../src/Models/Pedido.php';

/**
 * Pacote L1.6 — cobre cenários de corrida: pedido muda de fase entre o preview
 * e a confirmação, ou duas confirmações disputam o mesmo pedido.
 */
final class CancellationRaceTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['cancelamento_snapshots', 'pedido_cancelamentos', 'pedido_idempotency', 'pagamentos', 'pedidos', 'guinchos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('cancelamento_gratis_min', '0')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_cancelamento_percent', '20')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('taxa_cancelamento_fixa', '15')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('km_bloqueio_cancelamento', '2')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('cancelamento_preview_ttl_min', '3')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('penalidade_reputacao_cancelamento', '0.25')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (611, 'Cliente Race', 'cliente.race@example.com', 'hash', '11999990002', '61111111111', 'cliente'),
            (612, 'Guincho Race', 'guincho.race@example.com', 'hash', '11999990003', '61222222222', 'guincho')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (611, 611, 'RCE1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel, reputacao, total_cancelamentos) VALUES (611, 612, 1, 0, 5.00, 0)");
    }

    public function testPedidoConcludedBetweenPreviewAndConfirmationBlocksConfirmation(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (711, 'aguardando_guincho', 100.00, 611, 611, 611, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(711);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 611);

        // Simula outro fluxo concluindo o pedido entre o preview e a confirmação.
        $pdo->exec("UPDATE pedidos SET status = 'concluido' WHERE id = 711");

        $resultado = CancelamentoService::cancelarPorCliente(711, 611, 'Tentando cancelar tarde', (int)$preview['snapshot_id']);
        $this->assertFalse($resultado['ok']);

        $status = $pdo->query("SELECT status FROM pedidos WHERE id = 711")->fetchColumn();
        $this->assertSame('concluido', $status, 'Status não pode retroceder para cancelado depois de concluído.');
    }

    public function testSecondConfirmationAfterFirstSucceedsIsRejected(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (712, 'aguardando_guincho', 100.00, 611, 611, 611, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(712);
        $previewA = CancelamentoService::previewClienteComSnapshot($pedido, 611);
        $previewB = CancelamentoService::previewClienteComSnapshot($pedido, 611);

        $this->assertNotSame($previewA['snapshot_id'], $previewB['snapshot_id'], 'Cada preview deve gerar um snapshot próprio.');

        $primeira = CancelamentoService::cancelarPorCliente(712, 611, 'Confirmação A', (int)$previewA['snapshot_id']);
        $this->assertTrue($primeira['ok']);

        // O segundo snapshot ainda é "pending" isoladamente, mas o pedido já está cancelado.
        $segunda = CancelamentoService::cancelarPorCliente(712, 611, 'Confirmação B', (int)$previewB['snapshot_id']);
        $this->assertFalse($segunda['ok']);
    }

    public function testGuinchoPenaltyIsAtomicWithRequeue(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (713, 'a_caminho', 100.00, 611, 611, 611, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $resultado = CancelamentoService::cancelarPorGuincho(713, 611, 'Pane mecânica');
        $this->assertTrue($resultado['ok'], (string)($resultado['erro'] ?? ''));
        $this->assertEqualsWithDelta(0.25, $resultado['penalidade_reputacao'], 0.001);

        $pedido = $pdo->query("SELECT status, guincho_id FROM pedidos WHERE id = 713")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('aguardando_guincho', $pedido['status']);
        $this->assertNull($pedido['guincho_id']);

        $guincho = $pdo->query("SELECT reputacao, total_cancelamentos, disponivel FROM guinchos WHERE id = 611")->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(4.75, (float)$guincho['reputacao'], 0.001, 'Reputação deve cair exatamente na mesma operação do reenfileiramento.');
        $this->assertSame(1, (int)$guincho['total_cancelamentos']);
        $this->assertSame('1', (string)$guincho['disponivel']);

        $auditoria = $pdo->query("SELECT * FROM pedido_cancelamentos WHERE pedido_id = 713")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($auditoria);
        $this->assertSame('guincho', $auditoria['ator_tipo']);
        $this->assertEqualsWithDelta(0.25, (float)$auditoria['penalidade'], 0.001);
    }

    public function testGuinchoCannotCancelAfterArrivalAndNoPenaltyIsApplied(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (714, 'no_local', 100.00, 611, 611, 611, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $resultado = CancelamentoService::cancelarPorGuincho(714, 611, 'Tarde demais');
        $this->assertFalse($resultado['ok']);

        $guincho = $pdo->query("SELECT reputacao, total_cancelamentos FROM guinchos WHERE id = 611")->fetch(PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(5.00, (float)$guincho['reputacao'], 0.001, 'Reputação não pode ser penalizada quando o cancelamento é rejeitado.');
        $this->assertSame(0, (int)$guincho['total_cancelamentos']);
    }
}
