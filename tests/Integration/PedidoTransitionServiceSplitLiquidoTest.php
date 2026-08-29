<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Services/Pedido/PedidoTransitionService.php';
require_once __DIR__ . '/../../src/Services/Payment/PayoutLedgerService.php';
require_once __DIR__ . '/../../src/Models/Finance/PayoutLedgerEntry.php';

/**
 * §SPLIT-LIQUIDO-01 (26/07/2026) — o usuário identificou que comissão
 * (21%) + repasse (80%) + taxa de gateway (~4%) somavam mais de 100% do
 * valor bruto recebido do cliente, porque a comissão incidia sobre o BRUTO
 * sem nunca descontar o gateway. Este teste fixa o comportamento corrigido:
 * comissão incide sobre o LÍQUIDO (bruto menos reserva de gateway), e a
 * reserva vira um lançamento próprio no ledger (nunca "some" da
 * reconciliação contábil).
 */
final class PedidoTransitionServiceSplitLiquidoTest extends TestCase
{
    protected function setUp(): void
    {
        $pdo = getPDO();
        foreach (['payout_ledger_entries', 'pedido_idempotency', 'pagamentos', 'pedidos', 'veiculos', 'usuarios', 'configuracoes'] as $table) {
            try {
                $pdo->exec("DELETE FROM {$table}");
            } catch (Throwable) {
            }
        }

        $pdo->exec("
            INSERT INTO usuarios (id, nome, email, senha_hash, telefone, cpf, tipo) VALUES
            (1, 'Cliente Teste', 'cliente.split@example.com', 'hash', '11999999999', '11111111111', 'cliente')
        ");
        $pdo->exec("
            INSERT INTO veiculos (id, usuario_id, placa, marca, modelo, ano, cor, tipo) VALUES
            (1, 1, 'TST1A23', 'Marca Teste', 'Modelo Teste', 2020, 'Prata', 'carro')
        ");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('tempo_expiracao_min', '5')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('raio_inicial_km', '10')");
    }

    private function criarPedidoEPagamento(int $id, float $custo): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO pedidos (id, status, custo_estimado, cliente_id, veiculo_id, lat_origem, lng_origem, endereco_origem, lat_destino, lng_destino, endereco_destino) VALUES ({$id}, 'aguardando_pagamento', {$custo}, 1, 1, -23.55, -46.63, 'Origem', -23.56, -46.64, 'Destino')");
        $pdo->exec("INSERT INTO pagamentos (pedido_id, metodo, status, valor_total) VALUES ({$id}, 'mercadopago', 'pendente', {$custo})");
    }

    public function testComissao20ComReservaGateway45NuncaEstouraCemPorCento(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.20')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('reserva_gateway_percentual', '0.045')");
        $this->criarPedidoEPagamento(301, 200.00);

        $result = PedidoTransitionService::approvePayment(301, 'mp_split_301', '{}');
        $this->assertTrue($result->ok, (string)$result->error);

        $pag = $pdo->query("SELECT valor_guincho, valor_plataforma FROM pagamentos WHERE pedido_id = 301")->fetch(PDO::FETCH_ASSOC);

        // líquido = 200 * (1-0.045) = 191.00; plataforma = 191*0.20 = 38.20; guincho = 152.80.
        $this->assertEqualsWithDelta(191.00 * 0.20, (float)$pag['valor_plataforma'], 0.01);
        $this->assertEqualsWithDelta(191.00 * 0.80, (float)$pag['valor_guincho'], 0.01);

        // Nunca pode somar mais que o bruto recebido — a falha original do
        // usuário (21%+80%+gateway > 100%) nunca deve voltar a acontecer.
        $somaSplit = (float)$pag['valor_guincho'] + (float)$pag['valor_plataforma'];
        $this->assertLessThanOrEqual(200.00, $somaSplit, 'guincho + plataforma NUNCA pode ultrapassar o valor bruto recebido.');
    }

    public function testLedgerReconciliaTresPartesIgualAoBruto(): void
    {
        $pdo = getPDO();
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.20')");
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('reserva_gateway_percentual', '0.045')");
        $this->criarPedidoEPagamento(302, 200.00);

        $result = PedidoTransitionService::approvePayment(302, 'mp_split_302', '{}');
        $this->assertTrue($result->ok, (string)$result->error);

        $entradas = $pdo->query("SELECT entry_type, valor FROM payout_ledger_entries WHERE pedido_id = 302")->fetchAll(PDO::FETCH_ASSOC);
        $somaPorTipo = [];
        foreach ($entradas as $e) {
            $somaPorTipo[$e['entry_type']] = ($somaPorTipo[$e['entry_type']] ?? 0) + (float)$e['valor'];
        }

        $this->assertArrayHasKey('reserva_gateway', $somaPorTipo, 'A reserva de gateway precisa virar um lançamento próprio — dinheiro não pode simplesmente sumir do ledger.');

        $somaTotal = ($somaPorTipo['credito_guincho'] ?? 0) + ($somaPorTipo['credito_plataforma'] ?? 0) + ($somaPorTipo['reserva_gateway'] ?? 0);
        $this->assertEqualsWithDelta(200.00, $somaTotal, 0.01, 'guincho + plataforma + reserva de gateway deve reconciliar exatamente com o valor bruto.');
    }

    public function testSemConfiguracaoDeReservaUsaDefaultDeProducao(): void
    {
        // Config real de produção grava reserva_gateway_percentual junto com
        // comissao_plataforma (ver tools/aplicar_comissao_20_80_liquido.php)
        // — mas se por algum motivo faltar, o código tem que ter um default
        // seguro (4,5%), não presumir 0% (que reproduziria o bug original
        // do usuário em qualquer ambiente que esqueça de configurar).
        $pdo = getPDO();
        $pdo->exec("INSERT INTO configuracoes (chave, valor) VALUES ('comissao_plataforma', '0.20')");
        // reserva_gateway_percentual DELIBERADAMENTE ausente.
        $this->criarPedidoEPagamento(303, 100.00);

        $result = PedidoTransitionService::approvePayment(303, 'mp_split_303', '{}');
        $this->assertTrue($result->ok, (string)$result->error);

        $pag = $pdo->query("SELECT valor_guincho, valor_plataforma FROM pagamentos WHERE pedido_id = 303")->fetch(PDO::FETCH_ASSOC);
        $liquidoEsperado = 100.00 * (1 - 0.045);
        $this->assertEqualsWithDelta($liquidoEsperado * 0.20, (float)$pag['valor_plataforma'], 0.01);
        $this->assertEqualsWithDelta($liquidoEsperado * 0.80, (float)$pag['valor_guincho'], 0.01);
    }
}
