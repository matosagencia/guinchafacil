<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/CancelamentoService.php';
require_once __DIR__ . '/../../src/Models/CancellationSnapshot.php';
require_once __DIR__ . '/../../src/Models/Pedido.php';

/**
 * Pacote L1.6 — cobre a consistência entre snapshot (fee/refund previstos),
 * o valor efetivamente gravado em pedidos.taxa_cancelamento e o resultado
 * do estorno, no caminho feliz (sem pagamento aprovado, sem chamada real
 * a gateway — EstornoService::estornar() já retorna sucesso trivial nesse caso).
 */
final class CancellationRefundAtomicityTest extends TestCase
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

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (621, 'Cliente Estorno', 'cliente.estorno@example.com', 'hash', '11999990004', '62111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (621, 621, 'RFD1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
    }

    public function testFeeAndRefundInSnapshotMatchFinalPersistedTaxa(): void
    {
        $pdo = getPDO();
        // aguardando_guincho => taxa prevista é 0 (sem guincho envolvido ainda).
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (721, 'aguardando_guincho', 200.00, 621, 621, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");

        $pedido = Pedido::buscarPorId(721);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 621);

        $this->assertEqualsWithDelta(0.0, $preview['taxa'], 0.001);
        // Sem taxa, o reembolso previsto é o valor integral do pedido (nada retido).
        $this->assertEqualsWithDelta(200.0, $preview['refund_previsto'], 0.001);

        $resultado = CancelamentoService::cancelarPorCliente(721, 621, 'Sem taxa', (int)$preview['snapshot_id']);
        $this->assertTrue($resultado['ok']);
        $this->assertEqualsWithDelta(0.0, $resultado['taxa'], 0.001);
        $this->assertTrue((bool)($resultado['estorno']['sucesso'] ?? false));

        $taxaPersistida = (float)$pdo->query("SELECT taxa_cancelamento FROM pedidos WHERE id = 721")->fetchColumn();
        $this->assertEqualsWithDelta((float)$preview['taxa'], $taxaPersistida, 0.001, 'A taxa gravada no pedido deve ser exatamente a do snapshot confirmado.');

        $snapshot = CancellationSnapshot::buscarPorId((int)$preview['snapshot_id']);
        $this->assertEqualsWithDelta($taxaPersistida, (float)$snapshot['fee_amount'], 0.001);
    }

    public function testFeeIsAppliedWhenGuinchoAlreadyAssignedFarFromOrigin(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (622, 'Guincho Distante', 'guincho.distante@example.com', 'hash', '11999990005', '62222222222', 'guincho')
        ");
        $pdo->exec("INSERT INTO guinchos (id, usuario_id, aprovado, disponivel, lat_atual, lng_atual) VALUES (622, 622, 1, 0, -23.70, -46.80)");
        // criado_em no passado: garante que a janela de cancelamento grátis (baseada
        // em criado_em) já expirou, isolando o teste da regra de isenção temporal.
        // DATE_SUB(...) é sintaxe MySQL; o SQLite de teste não entende essa
        // gramática (não é uma função comum, é sintaxe de expressão), então
        // calculamos o timestamp em PHP e passamos como parâmetro — funciona
        // igual nos dois bancos.
        $criadoEm = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        $pdo->prepare("
            INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, guincho_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino, distancia_km, criado_em)
            VALUES (722, 'a_caminho', 200.00, 621, 621, 622, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino', 10, ?)
        ")->execute([$criadoEm]);

        $pedido = Pedido::buscarPorId(722);
        $preview = CancelamentoService::previewClienteComSnapshot($pedido, 621);

        $this->assertTrue($preview['pode']);
        $this->assertGreaterThan(0.0, $preview['taxa'], 'Com guincho a caminho e distante, deve haver taxa de cancelamento.');

        $resultado = CancelamentoService::cancelarPorCliente(722, 621, 'Taxa aplicada', (int)$preview['snapshot_id']);
        $this->assertTrue($resultado['ok'], (string)$resultado['erro']);

        $taxaPersistida = (float)$pdo->query("SELECT taxa_cancelamento FROM pedidos WHERE id = 722")->fetchColumn();
        $this->assertEqualsWithDelta((float)$preview['taxa'], $taxaPersistida, 0.001);
        $this->assertGreaterThan(0.0, $taxaPersistida);
    }
}
