<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Controllers/GuinchoController.php';
require_once __DIR__ . '/../../src/Models/Pagamento.php';

/**
 * Cobre o FIX crítico de GuinchoController::splitRepasseParaConclusao()
 * (achado via CarteiraService::checarReconciliacaoGlobal(), tela
 * /admin/carteiras): antes deste fix, a conclusão do atendimento recalculava
 * comissão/repasse do zero com uma fórmula diferente da usada na aprovação
 * do pagamento (sem descontar reserva_gateway_percentual), pagando o
 * guincheiro a mais do que o ledger contábil sabia. Este teste prova que:
 *   1) quando existe um pagamento aprovado compatível, o método SEMPRE
 *      reaproveita o split já gravado (nunca recalcula um valor diferente);
 *   2) quando não existe (fallback), o cálculo usa a MESMA fórmula líquida
 *      da aprovação (com reserva de gateway), não a fórmula antiga.
 */
final class SplitRepasseConclusaoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['payout_ledger_entries', 'pagamentos', 'pedidos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.20')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('reserva_gateway_percentual', '0.045')");

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (931, 'Cliente Split', 'cliente.split@example.com', 'hash', '11999994001', '93111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (931, 931, 'SPL1A23', 'Marca', 'Modelo', 2020, 'Prata', 'carro')
        ");
    }

    private function invoke(int $pedidoId, float $valorTotal, array $cfg): array
    {
        $controller = new GuinchoController();
        $ref = new ReflectionMethod(GuinchoController::class, 'splitRepasseParaConclusao');
        $ref->setAccessible(true);
        return $ref->invoke($controller, $pedidoId, $valorTotal, $cfg);
    }

    public function testReaproveitaSplitJaAprovadoEmVezDeRecalcular(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, custo_final, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (931, 'em_reboque', 100.00, 100.00, 931, 931, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        // Split já aprovado com a fórmula líquida correta (§SPLIT-LIQUIDO-01):
        // líquido pós-gateway = 100 * 0.955 = 95.5; comissão 20% = 19.10; guincho = 76.40.
        $pdo->exec("
            INSERT INTO pagamentos (pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma)
            VALUES (931, 'mercadopago', 'aprovado', 100.00, 76.40, 19.10)
        ");

        [$valorGuincho, $valorPlataforma] = $this->invoke(931, 100.00, ['comissao_plataforma' => '0.20', 'reserva_gateway_percentual' => '0.045']);

        // O valor tem que ser EXATAMENTE o já aprovado (76.40), não o que a
        // fórmula antiga (sem reserva) geraria (80.00 — comissão 20% direto
        // sobre 100, sem descontar a reserva de gateway).
        $this->assertEqualsWithDelta(76.40, $valorGuincho, 0.01, 'Deve reaproveitar o split já aprovado, não recalcular sem a reserva de gateway.');
        $this->assertEqualsWithDelta(19.10, $valorPlataforma, 0.01);
        $this->assertNotEqualsWithDelta(80.00, $valorGuincho, 0.01, 'NUNCA deve retornar o valor da fórmula antiga divergente (sem reserva de gateway).');
    }

    public function testFallbackUsaFormulaLiquidaQuandoNaoHaPagamentoAprovadoCompativel(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, custo_final, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (932, 'em_reboque', 100.00, 100.00, 931, 931, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        // Nenhum pagamento aprovado para este pedido (cenário freeflow puro).

        [$valorGuincho, $valorPlataforma] = $this->invoke(932, 100.00, ['comissao_plataforma' => '0.20', 'reserva_gateway_percentual' => '0.045']);

        // Fallback deve usar a MESMA fórmula líquida da aprovação (com
        // reserva de gateway descontada), não a fórmula antiga.
        $this->assertEqualsWithDelta(76.40, $valorGuincho, 0.01, 'Fallback deve usar a fórmula líquida (com reserva de gateway), igual à aprovação.');
        $this->assertEqualsWithDelta(19.10, $valorPlataforma, 0.01);
    }

    public function testValorTotalDivergenteCaiNoFallbackEmVezDeUsarSplitDesatualizado(): void
    {
        $pdo = getPDO();
        $pdo->exec("
            INSERT INTO pedidos (id, status, custo_estimado, custo_final, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino)
            VALUES (933, 'em_reboque', 100.00, 250.00, 931, 931, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')
        ");
        // Pagamento aprovado com valor_total ANTIGO (100), mas o pedido já
        // tem custo_final maior (250) — não deve reaproveitar cegamente o
        // split de 100 pra um total de 250.
        $pdo->exec("
            INSERT INTO pagamentos (pedido_id, metodo, status, valor_total, valor_guincho, valor_plataforma)
            VALUES (933, 'mercadopago', 'aprovado', 100.00, 76.40, 19.10)
        ");

        [$valorGuincho, ] = $this->invoke(933, 250.00, ['comissao_plataforma' => '0.20', 'reserva_gateway_percentual' => '0.045']);

        $this->assertNotEqualsWithDelta(76.40, $valorGuincho, 0.01, 'Não deve reaproveitar um split calculado para um valor_total diferente do atual.');
        // Fallback com fórmula líquida sobre 250: 250*0.955=238.75; comissão 20%=47.75; guincho=191.00
        $this->assertEqualsWithDelta(191.00, $valorGuincho, 0.01);
    }
}
